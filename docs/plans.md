# Subscription Plans

This document describes the subscription tiers (planos de assinatura) available in the 7Carros Locadora system.

## Overview

The system offers 7 subscription plans with varying feature limits. Each tenant (rental company) is assigned a plan that determines their vehicle capacity, branch limits, and available features.

**Plan Configuration Location:** `app/Config/Planos.php`

## Available Plans

### G - Gratuito (Free)

**Target:** Trial users and small operations

**Limits:**
- **Vehicles:** 3
- **Branches:** 1 (filiais)
- **Features:** Basic rental management only

**Use Case:** Testing the platform before committing to a paid plan.

---

### P0 - Junior

**Target:** Small independent rental businesses

**Limits:**
- **Vehicles:** 3
- **Branches:** 1
- **Features:**
  - Basic rental management
  - Fine checking (consulta de multas)

**Pricing:** Entry-level paid tier

---

### P1 - Iniciante (Starter)

**Target:** Growing small businesses

**Limits:**
- **Vehicles:** 5
- **Branches:** 1
- **Features:** Full feature set for single location

**Use Case:** Small rental company outgrowing the free plan.

---

### P2 - Intermediário (Intermediate)

**Target:** Established single-location businesses

**Limits:**
- **Vehicles:** 10
- **Branches:** 1
- **Features:** Full feature set for single location

**Use Case:** Medium-sized rental operation at one location.

---

### P3 - Avançado (Advanced)

**Target:** Multi-location businesses

**Limits:**
- **Vehicles:** 20
- **Branches:** 3
- **Features:** Full feature set with multi-branch support

**Use Case:** Regional rental company with multiple locations.

---

### P4 - Ilimitado (Unlimited)

**Target:** Large rental operations

**Limits:**
- **Vehicles:** Unlimited
- **Branches:** Unlimited
- **Features:** All features with no restrictions

**Pricing:** Fixed monthly fee

**Use Case:** Large rental companies or franchises.

---

### P6 - Ilimitado MB (Unlimited per MB)

**Target:** Enterprise customers with variable usage

**Limits:**
- **Vehicles:** Unlimited
- **Branches:** Unlimited
- **Features:** All features with no restrictions

**Pricing:** Pay-per-use model based on storage (megabytes)

**Use Case:** Enterprise customers preferring usage-based billing.

## Accessing Plan Configuration

### In PHP Code

Load the plan configuration array:

```php
<?php
require_once __DIR__ . '/app/Config/Planos.php';

// Access specific plan
$plano = PLANOS['P3'];

echo $plano['plano_nome'];      // "Avançado"
echo $plano['limite_veiculos']; // 20
echo $plano['limite_filiais'];  // 3
```

### Plan Data Structure

Each plan is an associative array with these keys:

```php
[
    'plano_codigo' => 'P3',          // Unique plan identifier
    'plano_nome' => 'Avançado',      // Display name
    'limite_veiculos' => 20,         // Vehicle limit (null = unlimited)
    'limite_filiais' => 3,           // Branch limit (null = unlimited)
    'recursos' => [                   // Available features (if applicable)
        'multi_filial' => true,
        // ...
    ]
]
```

## Checking Plan Limits

### Vehicle Limit Check

Before adding a vehicle, verify the tenant hasn't exceeded their plan limit:

```php
<?php
class VeiculoService {
    public function verificarLimiteVeiculos(): bool {
        require_once __DIR__ . '/../Config/Planos.php';

        // Get tenant's plan from database
        $tenant = $this->qb->getRow('tenants', ['plano'], 'chave = ?', [$_SESSION['chave']]);
        $planoConfig = PLANOS[$tenant['plano']];

        // If unlimited (null), allow
        if ($planoConfig['limite_veiculos'] === null) {
            return true;
        }

        // Count current vehicles
        $count = $this->qb->getValue('veiculos', 'COUNT(*)', 'chave = ?', [$_SESSION['chave']]);

        return $count < $planoConfig['limite_veiculos'];
    }

    public function adicionarVeiculo(array $dados): int {
        if (!$this->verificarLimiteVeiculos()) {
            throw new PlanLimitException('Limite de veículos atingido. Faça upgrade do plano.');
        }

        return $this->qb->insert('veiculos', $dados);
    }
}
```

### Inactive Vehicle Availability (Disponibilidade Inativa)

Vehicles with certain availability statuses are considered **inactive** and do NOT count towards the plan's vehicle limit. This allows tenants to free up slots when vehicles are sold, stolen, or excluded.

**Inactive statuses** (defined in `Veiculo::DISPONIBILIDADE_INATIVA`):

| Code | Label |
|------|-------|
| `V` | Vendido (Sold) |
| `RO` | Roubado (Stolen) |
| `E` | Excluído (Excluded) |

**Counting method:** `Veiculo::contarParaPlano(string $chave): int`
- Used by `PlanoLimiteHelper::contarRegistros('veiculos')` to count only active vehicles
- Excludes vehicles with `disponibilidade` IN (`V`, `RO`, `E`)
- The general `Veiculo::contar()` method still counts ALL vehicles (used for pagination)

**Reactivation validation** (`VeiculosController::update()`):
- When changing a vehicle FROM an inactive status (V/RO/E) TO any active status, the system checks `PlanoLimiteHelper::podeAdicionar('veiculos')`
- If the plan limit is already reached, returns 403 with translated message (`modules.veiculos.messages.plan_limit_reached`)
- Transitions between inactive statuses (e.g., V → RO) are always allowed

**Example flow:**
1. Tenant has plan P2 (limit: 10 vehicles), currently 10 active vehicles
2. Marks 1 vehicle as "Vendido" (V) → active count becomes 9
3. Can now add 1 new vehicle (9 < 10)
4. If tenant tries to change the sold vehicle back to "Disponível" (D) while at 10 active → blocked with modal alert

### Branch Limit Check

```php
<?php
class FilialService {
    public function verificarLimiteFiliais(): bool {
        require_once __DIR__ . '/../Config/Planos.php';

        $tenant = $this->qb->getRow('tenants', ['plano'], 'chave = ?', [$_SESSION['chave']]);
        $planoConfig = PLANOS[$tenant['plano']];

        if ($planoConfig['limite_filiais'] === null) {
            return true; // Unlimited
        }

        $count = $this->qb->getValue('filiais', 'COUNT(*)', 'chave = ?', [$_SESSION['chave']]);

        return $count < $planoConfig['limite_filiais'];
    }
}
```

### Feature Access Check

```php
<?php
class FeatureService {
    public function hasFeature(string $featureName): bool {
        require_once __DIR__ . '/../Config/Planos.php';

        $tenant = $this->qb->getRow('tenants', ['plano'], 'chave = ?', [$_SESSION['chave']]);
        $planoConfig = PLANOS[$tenant['plano']];

        return $planoConfig['recursos'][$featureName] ?? false;
    }
}

// Usage
if (!$featureService->hasFeature('multi_filial')) {
    throw new FeatureNotAvailableException('Este recurso não está disponível no seu plano.');
}
```

## Displaying Plan Information

### In Controllers

```php
<?php
class DashboardController {
    public function index() {
        require_once __DIR__ . '/../Config/Planos.php';

        // Get tenant's current plan
        $tenant = $this->qb->getRow('tenants', ['plano'], 'chave = ?', [$_SESSION['chave']]);
        $planoAtual = PLANOS[$tenant['plano']];

        // Count current usage
        $veiculosCount = $this->qb->getValue('veiculos', 'COUNT(*)', '1=1');
        $filiaisCount = $this->qb->getValue('filiais', 'COUNT(*)', '1=1');

        return $this->view('dashboard/index', [
            'plano' => $planoAtual,
            'veiculos_count' => $veiculosCount,
            'filiais_count' => $filiaisCount
        ]);
    }
}
```

### In Views

```php
<!-- dashboard/index.php -->
<div class="plan-info">
    <h3>Plano Atual: <?= htmlspecialchars($plano['plano_nome']) ?></h3>

    <div class="usage">
        <p>
            Veículos:
            <?= $veiculos_count ?>
            <?php if ($plano['limite_veiculos'] !== null): ?>
                / <?= $plano['limite_veiculos'] ?>
            <?php else: ?>
                (ilimitado)
            <?php endif; ?>
        </p>

        <p>
            Filiais:
            <?= $filiais_count ?>
            <?php if ($plano['limite_filiais'] !== null): ?>
                / <?= $plano['limite_filiais'] ?>
            <?php else: ?>
                (ilimitado)
            <?php endif; ?>
        </p>
    </div>

    <?php if ($veiculos_count >= $plano['limite_veiculos']): ?>
        <div class="alert alert-warning">
            Você atingiu o limite de veículos do seu plano.
            <a href="/upgrade">Faça upgrade</a>
        </div>
    <?php endif; ?>
</div>
```

## Plan Upgrades and Downgrades

### Upgrade Plan

```php
<?php
class TenantService {
    public function upgradePlan(string $novoPlano): void {
        require_once __DIR__ . '/../Config/Planos.php';

        // Validate plan exists
        if (!isset(PLANOS[$novoPlano])) {
            throw new InvalidArgumentException('Plano inválido');
        }

        // Get current plan
        $tenant = $this->qb->getRow('tenants', ['plano'], 'chave = ?', [$_SESSION['chave']]);

        // Update plan
        $this->qb->update('tenants', ['plano' => $novoPlano], 'chave = ?', [$_SESSION['chave']]);

        // Log the change
        $this->logPlanChange($tenant['plano'], $novoPlano);

        // Send notification email
        $this->emailService->enviarConfirmacaoUpgrade($novoPlano);
    }
}
```

### Downgrade Validation

Before downgrading, ensure current usage fits within new plan limits:

```php
<?php
public function validarDowngrade(string $novoPlano): array {
    require_once __DIR__ . '/../Config/Planos.php';

    $errors = [];
    $novoPlanoConfig = PLANOS[$novoPlano];

    // Check vehicle limit
    if ($novoPlanoConfig['limite_veiculos'] !== null) {
        $veiculosCount = $this->qb->getValue('veiculos', 'COUNT(*)', '1=1');
        if ($veiculosCount > $novoPlanoConfig['limite_veiculos']) {
            $errors[] = "Você possui $veiculosCount veículos, mas o plano $novoPlano permite apenas {$novoPlanoConfig['limite_veiculos']}";
        }
    }

    // Check branch limit
    if ($novoPlanoConfig['limite_filiais'] !== null) {
        $filiaisCount = $this->qb->getValue('filiais', 'COUNT(*)', '1=1');
        if ($filiaisCount > $novoPlanoConfig['limite_filiais']) {
            $errors[] = "Você possui $filiaisCount filiais, mas o plano $novoPlano permite apenas {$novoPlanoConfig['limite_filiais']}";
        }
    }

    return $errors;
}

public function downgradePlan(string $novoPlano): void {
    $errors = $this->validarDowngrade($novoPlano);

    if (!empty($errors)) {
        throw new PlanDowngradeException(implode("\n", $errors));
    }

    $this->qb->update('tenants', ['plano' => $novoPlano], 'chave = ?', [$_SESSION['chave']]);
}
```

## Plan Comparison

### Generate Comparison Table

```php
<?php
class PlanController {
    public function comparison() {
        require_once __DIR__ . '/../Config/Planos.php';

        return $this->view('plans/comparison', [
            'planos' => PLANOS
        ]);
    }
}
```

### Comparison View

```php
<!-- plans/comparison.php -->
<table class="plan-comparison">
    <thead>
        <tr>
            <th>Recurso</th>
            <?php foreach ($planos as $codigo => $plano): ?>
                <th><?= htmlspecialchars($plano['plano_nome']) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Veículos</td>
            <?php foreach ($planos as $plano): ?>
                <td>
                    <?= $plano['limite_veiculos'] === null ? 'Ilimitado' : $plano['limite_veiculos'] ?>
                </td>
            <?php endforeach; ?>
        </tr>
        <tr>
            <td>Filiais</td>
            <?php foreach ($planos as $plano): ?>
                <td>
                    <?= $plano['limite_filiais'] === null ? 'Ilimitado' : $plano['limite_filiais'] ?>
                </td>
            <?php endforeach; ?>
        </tr>
    </tbody>
</table>
```

## Database Schema

The tenant table should store the plan code:

```sql
CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(45) UNIQUE NOT NULL,
    nome_empresa VARCHAR(255) NOT NULL,
    plano VARCHAR(10) NOT NULL DEFAULT 'G',  -- Plan code (G, P0, P1, etc.)
    plano_expiracao DATE,                      -- Plan expiration date
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_chave (chave),
    INDEX idx_plano (plano)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Middleware for Plan Enforcement

Create middleware to enforce plan limits across the application:

```php
<?php
namespace App\Middleware;

class PlanLimitMiddleware {
    public function handle($request, $next) {
        $tenant = getTenant($_SESSION['chave']);
        $plano = PLANOS[$tenant['plano']];

        // Check if plan is expired
        if ($tenant['plano_expiracao'] && strtotime($tenant['plano_expiracao']) < time()) {
            return redirect('/billing/expired');
        }

        // Store plan info in request for easy access
        $request->plan = $plano;

        return $next($request);
    }
}
```

## Related Documentation

- **Multi-Tenancy:** `docs/multi-tenancy.md` - Tenant isolation
- **Best Practices:** `docs/best-practices.md` - Security and coding standards
- **Architecture:** `docs/architecture.md` - System design
- **Database:** `docs/database.md` - Schema and migrations
