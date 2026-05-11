# Multi-Tenancy Architecture

This document explains how the 7Carros Locadora system implements multi-tenant data isolation using session-based tenant identification.

## Overview

The system supports multiple rental companies (tenants) within a single application instance. Each tenant's data is completely isolated from other tenants using a unique identifier called `chave` (key).

**Architecture Type:** Session-based tenant isolation with database-level filtering

## How It Works

### The `chave` Session Variable

Every authenticated user has a `chave` value stored in their session:

```php
$_SESSION['chave'] = '4888241374E8C6275SD9B4CACFD091F96'; // Unique identifier for this rental company
```

This `chave` identifies which rental company (tenant) the user belongs to. All database queries automatically filter by this value to ensure data isolation.

### Automatic Tenant Filtering

The QueryBuilder class automatically adds `chave` filtering to all queries:

```php
// User's session has: $_SESSION['chave'] = '4888241374E8C6275SD9B4CACFD091F96'

// This query:
$clientes = $qb->select('clientes');

// Automatically becomes:
// SELECT * FROM clientes WHERE chave = '4888241374E8C6275SD9B4CACFD091F96'
```

This happens transparently - you don't need to manually add `chave` conditions to every query.

## Database Schema Requirements

### Tenant-Specific Tables

Any table that stores tenant-specific data **must** include a `chave` column:

```sql
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(45) NOT NULL,  -- Tenant identifier
    nome_rsocial VARCHAR(255),
    cpf_cnpj VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_chave (chave)  -- Index for performance
);
```

Always index the `chave` column for query performance.

### Shared/Public Tables

Some tables store data shared across all tenants (e.g., configuration, lookup tables). These tables should **not** have a `chave` column.

Examples:
- System configuration tables
- Country/state/city reference data
- Shared product catalogs
- System logs

## Using QueryBuilder with Multi-Tenancy

### Default Behavior (Automatic Filtering)

By default, all QueryBuilder operations filter by `$_SESSION['chave']`:

```php
use App\Classes\QueryBuilder;

$qb = new QueryBuilder($mysqli);

// SELECT - automatically filtered
$clientes = $qb->select('clientes');
// Executes: SELECT * FROM clientes WHERE chave = $_SESSION['chave']

// INSERT - automatically adds chave
$id = $qb->insert('clientes', [
    'nome_rsocial' => 'João Silva',
    'cpf_cnpj' => '12345678900'
]);
// Executes: INSERT INTO clientes (chave, nome_rsocial, cpf_cnpj)
//           VALUES ($_SESSION['chave'], 'João Silva', '12345678900')

// UPDATE - automatically filters
$qb->update('clientes', ['email' => 'novo@email.com'], 'id = ?', [123]);
// Executes: UPDATE clientes SET email = 'novo@email.com'
//           WHERE id = 123 AND chave = $_SESSION['chave']

// DELETE - automatically filters
$qb->delete('clientes', 'id = ?', [123]);
// Executes: DELETE FROM clientes
//           WHERE id = 123 AND chave = $_SESSION['chave']
```

### Disabling Automatic Filtering

For shared/public tables, disable automatic `chave` filtering using `withoutChave()`:

```php
// Query shared table without chave filtering
$estados = $qb->withoutChave()->select('estados');

// Get system configuration (shared across tenants)
$config = $qb->withoutChave()->getRow('system_config', ['*'], 'config_key = ?', ['max_upload_size']);
```

**Important:** Only use `withoutChave()` on tables that genuinely don't have a `chave` column. Using it on tenant-specific tables can cause data leakage.

## Models and Multi-Tenancy

### Model Examples

Models automatically benefit from QueryBuilder's tenant filtering:

```php
<?php
namespace App\Models;

use App\Classes\QueryBuilder;

class Cliente {
    private QueryBuilder $qb;

    public function __construct(QueryBuilder $qb) {
        $this->qb = $qb;
    }

    // Automatically filtered by chave
    public function listarAtivos(): array {
        return $this->qb->select('clientes', ['*'], 'situacao = ?', ['A']);
    }

    // Automatically adds chave on insert
    public function criar(array $dados): int {
        return $this->qb->insert('clientes', $dados);
    }

    // Automatically filters by chave
    public function buscarPorId(int $id): ?array {
        $resultado = $this->qb->select('clientes', ['*'], 'id = ?', [$id]);
        return $resultado[0] ?? null;
    }
}
```

### Shared Data Models

For models that access shared tables:

```php
<?php
namespace App\Models;

use App\Classes\QueryBuilder;

class Estado {
    private QueryBuilder $qb;

    public function __construct(QueryBuilder $qb) {
        $this->qb = $qb;
    }

    // Shared table - disable chave filtering
    public function listarTodos(): array {
        return $this->qb->withoutChave()->select('estados', ['*'], '1=1', [], 'nome ASC');
    }
}
```

## Session Management

### Setting Tenant Context

During authentication, set the user's `chave`:

```php
<?php
// After successful login
$_SESSION['chave'] = $usuario['chave'];
$_SESSION['user_id'] = $usuario['id'];
$_SESSION['user_name'] = $usuario['nome'];
```

### Validating Tenant Context

Before processing requests, ensure the user has a valid tenant context:

```php
<?php
// In middleware or at start of request
if (empty($_SESSION['chave'])) {
    // User not authenticated or session expired
    header('Location: /login');
    exit;
}
```

### Switching Tenants (Admin Only)

If a system administrator needs to access multiple tenants:

```php
<?php
// Store original chave
$_SESSION['original_chave'] = $_SESSION['chave'];

// Switch to target tenant
$_SESSION['chave'] = $target_chave;

// ... perform operations ...

// Switch back
$_SESSION['chave'] = $_SESSION['original_chave'];
```

**Warning:** Only implement tenant switching for trusted admin users with proper authorization checks.

## Security Considerations

### Data Isolation Guarantees

The QueryBuilder enforces these guarantees:

1. **SELECT:** Users can only see data from their tenant
2. **INSERT:** New records automatically belong to the user's tenant
3. **UPDATE:** Users can only modify their tenant's data
4. **DELETE:** Users can only delete their tenant's data

### Bypassing Protection

The `withoutChave()` method bypasses tenant filtering. Use it **apenas** nos casos específicos listados abaixo.

✅ **Safe uses (use only in these contexts):**

| Contexto | Por quê | Exemplos reais |
|----------|---------|----------------|
| CRON jobs cross-tenant | Processa dados de todos os tenants | `app/Crons/Jobs/*` |
| Autenticação (pré-sessão) | `$_SESSION['chave']` ainda não existe | `app/Core/Auth.php` |
| Migrations | Rodam sem sessão | `app/Database/migrations/**` |
| Provisioning de tenant | O tenant em si ainda não existe | `TenantProvisioningService.php` |
| Webhooks externos | Servidor externo chama sem sessão | `SerproWebhookController.php` |
| Rotas públicas | Clientes finais sem login | `PagamentoPublicoController.php`, `PublicWebsiteController.php` |
| Templates globais (`chave='0'`) | Recursos compartilhados pelo sistema | `MessageTemplateService.php` |
| Tabelas sem coluna `chave` | Não há o que filtrar | `security_login_attempts`, `changelog` |

❌ **Dangerous uses (NÃO faça):**
- CRUD normal de entidade do tenant (Cliente, Veículo, Contrato, etc.)
- `withoutChave()` seguido de `where('chave', '=', $chave)` — é redundante, remova ambos
- `withoutChave()` seguido de `where('id', '=', $id)` — abre bug de cross-tenant (outro tenant pode acessar pelo ID)
- Qualquer operação disparada por input do usuário sem validação

### SQL Injection Protection

Even with automatic `chave` filtering, always use prepared statements:

```php
// Safe - uses prepared statements
$resultado = $qb->select('clientes', ['*'], 'cpf_cnpj = ?', [$_POST['cpf']]);

// DANGEROUS - concatenation (don't do this!)
$resultado = $qb->query("SELECT * FROM clientes WHERE cpf_cnpj = '{$_POST['cpf']}'");
```

### Detecção de Tentativas Cross-Tenant

O sistema pode detectar quando um usuário tenta acessar IDs de registros que pertencem a outro tenant. Isso é útil para identificar comportamento suspeito (usuários "curiosos" testando IDs aleatórios).

#### Como Funciona

1. Usuário solicita registro por ID (ex: `GET /api/clientes/123`)
2. QueryBuilder filtra por `chave` → retorna `null`
3. `CrossTenantDetectionService::check()` verifica se o ID existe em outro tenant
4. Se existir → registra no `security_logs` com evento `cross_tenant_attempt`
5. Resposta continua sendo 404 (não revela existência)

#### Uso com Trait

```php
use App\Traits\DetectsCrossTenant;

class Cliente extends Model
{
    use DetectsCrossTenant;

    // ...
}

// No Controller
$cliente = $model->buscarPorIdComDeteccao($id);  // Detecta automaticamente
```

#### Tabelas Monitoradas

- `clientes`, `contratos`, `veiculos`, `financeiro`, `funcionarios`, `reservas`, `ordem_servico`

**Documentação completa:** Veja **[logs.md](./logs.md#crosstenantdetectionservice)** para todos os métodos e configurações.

## Testing Multi-Tenancy

### Test Data Setup

Create test tenants with isolated data:

```sql
-- Tenant A
INSERT INTO clientes (chave, nome_rsocial) VALUES ('TENANT_A', 'Cliente A1');
INSERT INTO clientes (chave, nome_rsocial) VALUES ('TENANT_A', 'Cliente A2');

-- Tenant B
INSERT INTO clientes (chave, nome_rsocial) VALUES ('TENANT_B', 'Cliente B1');
INSERT INTO clientes (chave, nome_rsocial) VALUES ('TENANT_B', 'Cliente B2');
```

### Verify Isolation

Test that queries respect tenant boundaries:

```php
// Set tenant A context
$_SESSION['chave'] = '4888241374E8C62DDD9B4C3CFD091F96';
$resultadoA = $qb->select('clientes');
// Should only return Cliente A1 and A2

// Switch to tenant B
$_SESSION['chave'] = '4888241374E8C62DDD9B4C3CFD091F96';
$resultadoB = $qb->select('clientes');
// Should only return Cliente B1 and B2

assert(count($resultadoA) === 2);
assert(count($resultadoB) === 2);
assert($resultadoA[0]['chave'] === 'TENANT_A');
assert($resultadoB[0]['chave'] === 'TENANT_B');
```

### Unit Testing with Tenants

Mock session data in tests:

```php
class ClienteServiceTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();

        // Set test tenant context
        $_SESSION['chave'] = '4888241374E8C62DDD9B4C3CFD091F96';
    }

    public function testListarClientes() {
        $service = new ClienteService();
        $clientes = $service->listar();

        // Verify all results belong to test tenant
        foreach ($clientes as $cliente) {
            $this->assertEquals('4888241374E8C62DDD9B4C3CFD091F96', $cliente['chave']);
        }
    }
}
```

## Common Pitfalls

### Forgetting to Add `chave` Column

**Problem:**
```sql
CREATE TABLE veiculos (
    id INT PRIMARY KEY,
    placa VARCHAR(10)
    -- Missing chave column!
);
```

**Solution:** Always include `chave` in tenant-specific tables:
```sql
CREATE TABLE veiculos (
    id INT PRIMARY KEY,
    chave VARCHAR(45) NOT NULL,
    placa VARCHAR(10),
    INDEX idx_chave (chave)
);
```

### Using `withoutChave()` Incorrectly

**Anti-padrão #1 — redundante:**
```php
// ❌ ERRADO — withoutChave + where('chave') re-implementa o filtro automático
return $this->qb
    ->table('clientes')
    ->withoutChave()
    ->where('chave', '=', $chave)
    ->get();

// ✅ CORRETO — deixa o filtro automático cuidar
return $this->qb
    ->table('clientes')
    ->get();
```

**Anti-padrão #2 — bug de cross-tenant (grave):**
```php
// ❌ ERRADO — sem filtro de chave, usuário do tenant A pode acessar
// registro do tenant B passando o ID (que chega da URL/form)
public function buscarPorId(int $id): ?array
{
    return $this->qb
        ->table('clientes')
        ->withoutChave()
        ->where('id', '=', $id)
        ->first();
}

// ✅ CORRETO — o filtro automático retorna null se o ID pertencer a
// outro tenant (isolamento preservado)
public function buscarPorId(int $id): ?array
{
    return $this->qb
        ->table('clientes')
        ->where('id', '=', $id)
        ->first();
}
```

**Quando com JOIN e alias:**
```php
// ✅ CORRETO — filtro automático qualifica com o alias ('c.chave')
return $this->qb
    ->table('clientes', 'c')
    ->leftJoin('grupos', 'g', 'c.id_grupo', '=', 'g.id')
    ->where('c.id', '=', $id)
    ->first();
// Gera: WHERE c.id = ? AND c.chave = $_SESSION['chave']
```

**⚠️ Evitar:** `table('clientes c')` (alias embutido no nome) — o QueryBuilder só detecta o alias quando passado como segundo parâmetro. Sem isso, o filtro automático gera `WHERE chave = ?` sem prefixo, o que pode dar "ambiguous column" em JOINs.

### Missing Session Validation

**Problem:**
```php
// No check if $_SESSION['chave'] exists
$clientes = $qb->select('clientes');
```

**Solution:** Always validate session before database operations:
```php
if (empty($_SESSION['chave'])) {
    throw new Exception('Tenant context not set');
}
$clientes = $qb->select('clientes');
```

### Hardcoding `chave` Values

**Problem:**
```php
// Don't hardcode tenant identifiers!
$_SESSION['chave'] = '4888241374E8C62DDD9B4C3CFD091F96';
```

**Solution:** Get `chave` from authentication:
```php
// During login, retrieve from database
$usuario = authenticate($username, $password);
$_SESSION['chave'] = $usuario['chave'];
```

## Performance Optimization

### Index the `chave` Column

Always create an index on `chave` for performance:

```sql
CREATE INDEX idx_chave ON clientes(chave);
CREATE INDEX idx_chave ON contratos(chave);
CREATE INDEX idx_chave ON veiculos(chave);
```

### Composite Indexes

For common query patterns, create composite indexes:

```sql
-- If you often filter by chave + status
CREATE INDEX idx_chave_status ON clientes(chave, situacao);

-- If you often filter by chave + date range
CREATE INDEX idx_chave_data ON contratos(chave, data_inicio);
```

### Query Analysis

Check query performance with EXPLAIN:

```sql
EXPLAIN SELECT * FROM clientes WHERE chave = '4888241374E8C62DDD9B4C3CFD091F96' AND situacao = 'A';
```

Ensure the query uses the `chave` index.

## Migration Example

When creating migrations, include `chave` column using the fluent API:

```php
<?php

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('clientes', function ($table) {
            $table->id();
            $table->string('chave', 45);
            $table->string('nome_rsocial', 255);
            $table->string('cpf_cnpj', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->timestamps();
            $table->index('chave');
            $table->index(['chave', 'cpf_cnpj']);
        });
    }

    public function down(): void
    {
        $this->drop('clientes');
    }
};
```

See **[migrations.md](./migrations.md)** for complete migration documentation.

## Segunda Camada: Filtros por Filiais

Além do isolamento por `chave` (tenant), o sistema possui uma **segunda camada de controle de acesso** baseada em filiais. Essa camada permite que funcionários acessem apenas registros das filiais às quais estão vinculados.

### Duas Camadas de Isolamento

| Camada | Mecanismo | Aplicação | Automático? |
|--------|-----------|-----------|-------------|
| 1ª - Tenant | `chave` via QueryBuilder | Todo o sistema | ✅ Sim |
| 2ª - Filiais | `FilialHelper` | Por módulo | ❌ Manual |

### Quando Usar Cada Camada

**Primeira Camada (chave):**
- Sempre ativa automaticamente
- Isolamento total entre empresas diferentes
- Gerenciada pelo QueryBuilder

**Segunda Camada (FilialHelper):**
- Ativada manualmente por módulo
- Restringe acesso dentro do mesmo tenant
- Funcionários só veem registros das filiais permitidas
- Administradores sem restrição veem tudo

### Exemplo de Uso Combinado

```php
use App\Helpers\FilialHelper;

// 1ª camada: QueryBuilder filtra automaticamente por chave
// 2ª camada: FilialHelper adiciona filtro por filiais
[$filialWhere, $filialParams] = FilialHelper::whereFiliais('id_matriz_filial');

$clientes = $model->listarPaginado($page, $perPage, $search, $filialWhere, $filialParams);
// Resultado: clientes do tenant atual + filiais permitidas do funcionário
```

### Verificação de Acesso Individual

Para operações em registros específicos (show, update, delete):

```php
$registro = $model->buscarPorId($id);

// 1ª camada: QueryBuilder já filtrou por chave
// 2ª camada: Verificar acesso à filial
if (!FilialHelper::temAcessoFilial($registro['id_matriz_filial'] ?? null)) {
    Response::json(['erro' => 'Acesso negado'], 403);
    return;
}
```

**Documentação completa:** Veja **[FilialHelper](./filial-helper.md)** para todos os métodos disponíveis e padrões de implementação.

---

## Related Documentation

- **FilialHelper:** `docs/filial-helper.md` - Filtros de acesso por filiais (segunda camada)
- **Logs & Segurança:** `docs/logs.md` - Auditoria, SecurityLogService e CrossTenantDetectionService
- **QueryBuilder:** `docs/querybuilder.md` - Database abstraction layer
- **Database:** `docs/database.md` - Schema and migrations
- **Architecture:** `docs/architecture.md` - System design
- **Best Practices:** `docs/best-practices.md` - Security guidelines
