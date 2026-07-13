# Sistema de Logs

Sistema de auditoria e logging de segurança multi-tenant.

## Visão Geral

O sistema possui dois níveis de logging:

| Nível | Tabela | Service | Propósito |
|-------|--------|---------|-----------|
| **Auditoria** | `logs` | `AuditLogService` | Operações CRUD e ações de usuários |
| **Segurança** | `security_logs` | `SecurityLogService` | Eventos de segurança (rate limit, fingerprint, honeypot) |

Ambos são isolados por tenant (`chave`).

---

## AuditLogService

**Arquivo:** `app/Services/AuditLogService.php`

Registra todas as operações de usuários para rastreabilidade e auditoria.

### Métodos Disponíveis

#### `registrar(string $mensagem): int`
Log simples sem campos alterados. Para ações que não envolvem alteração de dados (login, logout, acesso, exclusões).

```php
AuditLogService::registrar('João Silva, excluiu o cliente [Empresa ABC]');
```

#### `registrarComCampos(string $mensagem, array $campos): int`
Para processos sem frontend (crons, scripts, batch). Os campos são estruturados manualmente.

```php
AuditLogService::registrarComCampos(
    'Sistema, renovou contrato automaticamente [Contrato #123]',
    [
        AuditLogService::campo('Data Vigência', '2025-01-01', '2026-01-01', 'Dados Gerais'),
        AuditLogService::campo('Status', 'Ativo', 'Renovado', 'Dados Gerais'),
    ]
);
```

Em exclusoes em lote, registre uma entrada de auditoria por entidade realmente
excluida. Isso preserva a pesquisa individual e impede que registros ignorados
por vinculo ou permissao aparecam como removidos. O financeiro usa a mensagem
`excluiu em lote o lancamento financeiro` e reaproveita os mesmos campos
detalhados da exclusao individual.

#### `registrarComAuditFrontend(string $mensagem, ?string $auditData, ?string $auditChanges): int`
Para formulários com captura via JavaScript. Os dados vêm dos campos `_audit_data` (cadastro) ou `_audit_changes` (edição).

```php
// No controller
$dados = $request->all();

AuditLogService::registrarComAuditFrontend(
    "{$nomeUsuario}, adicionou cliente [{$dados['nome_rsocial']}]",
    $dados['_audit_data'] ?? null,
    null
);
```

#### `registrarAcesso(): int`
Helper para log de login no sistema.

```php
// AuthController.php - ao fazer login
AuditLogService::registrarAcesso();
// Registra: "João Silva, Entrou no sistema []"
```

#### `campo(string $label, $de, $para, ?string $aba = null): array`
Helper para criar estrutura de campo alterado.

```php
AuditLogService::campo('Nome', 'Valor Antigo', 'Valor Novo', 'Aba Principal');
// Retorna: ['aba' => 'Aba Principal', 'label' => 'Nome', 'de' => 'Valor Antigo', 'para' => 'Valor Novo']
```

### Dados Capturados Automaticamente

- `chave` - Tenant (de `$_SESSION['chave']`)
- `id_funcionario` - Usuário logado (de `$_SESSION['user_id']`)
- `ip` - Endereço IP (de `$_SERVER['REMOTE_ADDR']`)
- `data` - Data/hora do evento

---

## Trait Auditable

**Arquivo:** `app/Traits/Auditable.php`

Adiciona auditoria automática em Models CRUD. Simplifica o registro de logs em operações padrão.

### Requisitos do Model

O Model deve implementar os métodos:
- `buscarPorId(int $id): ?array`
- `criar(array $dados): int`
- `atualizar(int $id, array $dados): int`
- `deletar(int $id): int`

### Métodos Fornecidos

| Método | Descrição | Formato da Mensagem |
|--------|-----------|---------------------|
| `criarComAuditoria($dados)` | Cria e registra log | `"{usuario}, adicionou {entidade} [{identificador}]"` |
| `atualizarComAuditoria($id, $dados)` | Atualiza e registra log | `"{usuario}, atualizou {entidade} [{identificador}]"` |
| `deletarComAuditoria($id)` | Deleta e registra log | `"{usuario}, excluiu {entidade} [{identificador}]"` |

### Métodos Customizáveis

```php
// Sobrescreva para personalizar a mensagem de log
protected function getEntidadeAuditoria(): string
{
    return 'o cliente';  // Padrão: 'o registro'
}

protected function getCampoIdentificador(): string
{
    return 'nome_rsocial';  // Padrão: 'id'
}
```

### Exemplo de Implementação

```php
<?php

namespace App\Models;

use App\Traits\Auditable;

class Cliente extends Model
{
    use Auditable;

    protected function getEntidadeAuditoria(): string
    {
        return 'o cliente';
    }

    protected function getCampoIdentificador(): string
    {
        return 'nome_rsocial';
    }

    // Métodos CRUD obrigatórios...
    public function buscarPorId(int $id): ?array { /* ... */ }
    public function criar(array $dados): int { /* ... */ }
    public function atualizar(int $id, array $dados): int { /* ... */ }
    public function deletar(int $id): int { /* ... */ }
}
```

```php
// No Controller
$cliente = new Cliente();

// Criar com auditoria (captura _audit_data do frontend)
$id = $cliente->criarComAuditoria($dados);
// Log: "João Silva, adicionou o cliente [Empresa ABC]"

// Atualizar com auditoria (captura _audit_changes do frontend)
$cliente->atualizarComAuditoria($id, $dadosNovos);
// Log: "João Silva, atualizou o cliente [Empresa ABC]" + campos alterados

// Deletar com auditoria
$cliente->deletarComAuditoria($id);
// Log: "João Silva, excluiu o cliente [Empresa ABC]"
```

---

## SecurityLogService

**Arquivo:** `app/Services/SecurityLogService.php`

Registra eventos de segurança para análise e ajuste de regras de proteção.

### Tipos de Eventos

| Tipo | Descrição | Score |
|------|-----------|-------|
| `rate_limit` | Limite de requisições excedido | 0 |
| `fingerprint` | Padrão suspeito detectado | Variável (0-100) |
| `quota` | Quota de recursos excedida | 0 |
| `honeypot` | Acesso a endpoint armadilha | 100 (máximo) |
| `block` | IP bloqueado | 100 |
| `suspicious` | Comportamento suspeito genérico | Variável |
| `cross_tenant_attempt` | Tentativa de acesso a registro de outro tenant | Variável (15 por tentativa) |

### Métodos Disponíveis

#### `log($eventType, $ipAddress, $endpoint, $details, $score, $actionTaken, $userId, $chave): int`
Método genérico para qualquer evento.

#### `logRateLimit($ipAddress, $endpoint, $currentHits, $limit, $userId, $chave): int`
```php
SecurityLogService::logRateLimit(
    '192.168.1.1',
    '/api/clientes',
    65,  // hits atuais
    60,  // limite
    $userId,
    $chave
);
```

#### `logFingerprint($ipAddress, $endpoint, $score, $factors, $actionTaken, $userId, $chave): int`
```php
SecurityLogService::logFingerprint(
    '192.168.1.1',
    '/api/clientes',
    75,  // score de suspeita
    ['missing_headers', 'suspicious_user_agent'],
    'throttled',
    $userId,
    $chave
);
```

#### `logHoneypot($ipAddress, $endpoint, $userId, $chave): int`
```php
// Acesso a endpoint armadilha - bane automaticamente
SecurityLogService::logHoneypot('192.168.1.1', '/api/v2/users', null, null);
```

#### `logBlock($ipAddress, $endpoint, $reason, $duration, $userId, $chave): int`
```php
SecurityLogService::logBlock(
    '192.168.1.1',
    '/api/clientes',
    'Rate limit exceeded 3x',
    3600,  // 1 hora
    $userId,
    $chave
);
```

#### `cleanup(): int`
Remove logs antigos conforme configuração de retenção (padrão: 30 dias).
```php
$removidos = SecurityLogService::cleanup();
```

#### `getStats($startDate, $endDate): array`
Estatísticas por período.
```php
$stats = SecurityLogService::getStats('2025-01-01', '2025-01-31');
// Retorna: [{event_type, total, unique_ips, avg_score}, ...]
```

#### `getTopSuspiciousIps($limit, $days): array`
IPs mais suspeitos.
```php
$ips = SecurityLogService::getTopSuspiciousIps(10, 7);
// Retorna: [{ip_address, total_events, max_score, event_types}, ...]
```

---

## CrossTenantDetectionService

**Arquivo:** `app/Services/CrossTenantDetectionService.php`

Detecta e loga quando um usuário tenta acessar IDs de registros que pertencem a outro tenant. Trabalha em conjunto com o `SecurityLogService`.

### Problema Resolvido

Quando um usuário solicita um registro por ID (ex: `GET /api/clientes/123`), o QueryBuilder filtra automaticamente por `chave` e retorna `null` se o registro não pertencer ao tenant atual. Porém, sem este serviço, não havia como saber se:
- O ID simplesmente não existe
- O ID existe, mas pertence a outro tenant (tentativa suspeita)

### Métodos Disponíveis

#### `check(string $table, int $id, ?string $chaveAtual = null): CrossTenantCheckResult`
Verifica se um ID existe em outro tenant e loga a tentativa.

```php
use App\Services\CrossTenantDetectionService;

$cliente = $model->buscarPorId($id);

if (!$cliente) {
    // Verifica se foi tentativa cross-tenant
    $result = CrossTenantDetectionService::check('clientes', $id);

    if ($result->isCrossTenant) {
        // Foi logado automaticamente no security_logs
        // Score foi incrementado
    }

    Response::json(['message' => 'Cliente não encontrado'], 404);
}
```

#### `isUserSuspicious(?int $userId = null): bool`
Verifica se o usuário atual está com comportamento suspeito (muitas tentativas).

```php
if (CrossTenantDetectionService::isUserSuspicious()) {
    // Usuário fez 5+ tentativas nos últimos 5 minutos
    // Pode implementar ação adicional (throttling, alerta, etc.)
}
```

#### `getUserAttemptStats(?int $userId = null): array`
Obtém estatísticas de tentativas do usuário.

```php
$stats = CrossTenantDetectionService::getUserAttemptStats();
// [
//     'attempt_count' => 3,
//     'is_suspicious' => false,
//     'threshold' => 5
// ]
```

#### `clearAttemptCount(int $userId): bool`
Limpa contagem de tentativas (para testes ou reset manual).

```php
CrossTenantDetectionService::clearAttemptCount($userId);
```

### CrossTenantCheckResult

Classe retornada pelo método `check()`:

| Propriedade | Tipo | Descrição |
|-------------|------|-----------|
| `exists` | bool | O ID existe no sistema (qualquer tenant) |
| `isCrossTenant` | bool | O ID pertence a outro tenant |
| `wasLogged` | bool | Foi registrado no security_logs |
| `attemptCount` | int | Número de tentativas recentes do usuário |
| `suspicionScore` | int | Score de suspeita acumulado (0-100) |

### Configuração

**Arquivo:** `app/Config/Security.php`

```php
public const CROSS_TENANT = [
    'enabled' => true,

    // Tabelas monitoradas
    'monitored_tables' => [
        'clientes',
        'contratos',
        'veiculos',
        'financeiro',
        'funcionarios',
        'reservas',
        'manutencoes',
    ],

    'attempt_threshold' => 5,    // Tentativas antes de marcar suspeito
    'attempt_window' => 300,     // Janela de 5 minutos
    'cache_ttl' => 60,           // Cache de verificação
    'score_per_attempt' => 15,   // Score por tentativa
    'max_score' => 100,
];
```

### Detalhes do Log

Quando uma tentativa cross-tenant é detectada, o log inclui:

```json
{
    "table": "clientes",
    "target_id": 123,
    "target_chave_hash": "4888***********",
    "attempt_count": 2,
    "user_agent": "Mozilla/5.0..."
}
```

**Nota:** A chave do tenant alvo é parcialmente ocultada por segurança.

---

## Trait DetectsCrossTenant

**Arquivo:** `app/Traits/DetectsCrossTenant.php`

Trait para adicionar detecção automática de tentativas cross-tenant em Models.

### Requisitos do Model

O Model deve implementar:
- `buscarPorId(int $id): ?array`

### Métodos Fornecidos

| Método | Descrição |
|--------|-----------|
| `buscarPorIdComDeteccao($id)` | Busca por ID e detecta cross-tenant automaticamente |
| `isCrossTenantAttempt($id)` | Verifica se ID é tentativa cross-tenant |
| `checkCrossTenantBatch($ids)` | Verifica múltiplos IDs |

### Exemplo de Implementação

```php
<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\DetectsCrossTenant;

class Cliente extends Model
{
    use Auditable;
    use DetectsCrossTenant;

    public function buscarPorId(int $id): ?array
    {
        return $this->qb->table('clientes')->where('id', '=', $id)->first();
    }
}
```

### Uso no Controller

```php
$clienteModel = new Cliente();

// Opção 1: Com detecção automática (recomendado)
$cliente = $clienteModel->buscarPorIdComDeteccao($id);

if (!$cliente) {
    // Se foi cross-tenant, já foi logado automaticamente
    Response::json(['message' => 'Cliente não encontrado'], 404);
    return;
}

// Opção 2: Verificação manual
$cliente = $clienteModel->buscarPorId($id);

if (!$cliente) {
    CrossTenantDetectionService::check('clientes', $id);
    Response::json(['message' => 'Cliente não encontrado'], 404);
    return;
}
```

### Models com Trait Configurada

| Model | Tabela |
|-------|--------|
| `Cliente` | clientes |
| `Veiculo` | veiculos |
| `Financeiro` | financeiro |
| `Funcionario` | funcionarios |
| `Manutencao` | manutencoes |

---

## Integração Frontend (FormAudit)

**Arquivo:** `public/assets/js/form-audit.js`

Sistema JavaScript que captura automaticamente campos de formulários, detecta alterações e gera JSON estruturado para auditoria.

### Como Funciona

```
1. DOMContentLoaded
   ↓
2. Auto-inicializa formulários (exceto [data-no-audit])
   ↓
3. setTimeout(800ms) → captureInitial() → Salva estado inicial
   ↓
4. Usuário interage com o formulário
   ↓
5. submit → injectHiddenFields() → Injeta campos ocultos com JSON
   ↓
6. FormData enviada ao servidor com _audit_data ou _audit_changes
```

### Campos Especiais

| Campo | Uso | Conteúdo |
|-------|-----|----------|
| `_audit_data` | Cadastro (novo registro) | JSON com todos os valores preenchidos |
| `_audit_changes` | Edição (registro existente) | JSON apenas com campos alterados |
| `_audit_initial` | Referência (não enviado) | Valores iniciais para comparação |

### Campos Ignorados

```javascript
CONFIG.ignoredFields = [
    'id', 'chave', 'created_at', 'updated_at', 'data_cadastro',
    'senha', 'password', 'foto', 'foto_base64', 'foto_url', '_token',
    '_audit_data', '_audit_initial', '_audit_changes'
];

CONFIG.ignoredTypes = ['hidden', 'submit', 'button', 'reset', 'file'];
```

**Exceção:** Campos hidden que são arrays (ex: `parcelas[0][valor]`) NÃO são ignorados.

### Formatação Automática

| Tipo de Campo | Formatação |
|---------------|------------|
| `input-moeda` | Converte para `R$ 1.234,56` via `Currency.format()` |
| `date` | Converte de `yyyy-mm-dd` para `dd/mm/yyyy` |
| `datetime-local` | Converte para `dd/mm/yyyy HH:MM` |
| `checkbox` | Exibe "Sim" / "Não" |
| `radio` | Busca label do input checked |
| `select` | Captura o TEXTO VISÍVEL, não o value |

### API Pública

```javascript
FormAudit.init(form)                    // Inicializa auditoria em um form
FormAudit.capture(form)                 // Captura estado atual
FormAudit.captureInitial(form)          // Salva estado inicial
FormAudit.recapture(form)               // Re-captura após AJAX
FormAudit.getChanges(form)              // Obtém apenas mudanças
FormAudit.getAuditData(form)            // Obtém dados formatados para envio
FormAudit.injectHiddenFields(form)      // Injeta campos ocultos
FormAudit.registerHandler(pageId, handler) // Registra handler especializado
FormAudit.getHandler(form)              // Obtém handler do formulário
FormAudit.detectPageHandler(form)       // Detecta ID da página
```

### Formato de Saída

**Formato Agrupado por Aba:**
```json
{
  "Dados Pessoais": [
    {"label": "Nome", "de": null, "para": "João Silva"},
    {"label": "CPF", "de": null, "para": "123.456.789-00"}
  ],
  "Endereço": [
    {"label": "Cidade", "de": null, "para": "São Paulo"}
  ]
}
```

### Desabilitar Auditoria

```html
<!-- Formulário sem auditoria automática -->
<form data-no-audit>
  ...
</form>
```

---

## Handlers Especializados

Para páginas complexas com arrays dinâmicos, abas múltiplas ou campos com comportamentos especiais, é possível criar **handlers especializados** que substituem o handler genérico.

### Como Funciona

1. Ao abrir uma página, o `FormAudit` detecta automaticamente a URL
2. Se existir handler registrado para a página, usa ele
3. Se não existir, usa o handler genérico (retrocompatível)

### Detecção de Página

A detecção é feita em ordem de prioridade:

```javascript
// 1. Data attribute no form (maior prioridade)
<form data-audit-handler="financeiro-adicionar">

// 2. Inferir da URL: /pages/{modulo}/{acao}.php → "modulo-acao"
// Ex: /pages/financeiro/adicionar.php → "financeiro-adicionar"

// 3. Fallback: handler genérico
```

### Interface do Handler

```javascript
const MeuHandler = {
    config: {
        ignoredFields: [],      // Campos extras a ignorar
        customLabels: {},       // Labels customizados por campo
        valueLabels: {}         // Transformação de valores
    },

    // Captura estado atual do formulário
    capture(form) {
        // Retorna objeto agrupado por aba
        return {
            "Nome da Aba": [
                { label: "Campo", de: null, para: "valor" }
            ]
        };
    },

    // Captura estado inicial
    captureInitial(form) {
        return this.capture(form);
    },

    // Detecta alterações entre inicial e atual
    getChanges(form, initialData) {
        // Retorna apenas campos alterados
        return {
            "Nome da Aba": [
                { label: "Campo", de: "valor antigo", para: "valor novo" }
            ]
        };
    }
};
```

### Registrar Handler

```javascript
// No arquivo do handler
FormAudit.registerHandler('minha-pagina', MeuHandler);
```

### Carregamento Condicional

Os handlers são carregados condicionalmente no layout:

```php
<!-- app/Views/layouts/iframe.php -->
<script src="<?= asset('js/form-audit.js'); ?>"></script>

<?php if (strpos($requestUri, 'financeiro/adicionar') !== false): ?>
<script src="<?= asset('js/audit-handlers/financeiro-adicionar.js'); ?>"></script>
<?php endif; ?>
```

---

## Handler: Financeiro

**Arquivo:** `public/assets/js/audit-handlers/financeiro-adicionar.js`

Handler para a página de Lançamento Financeiro (`financeiro/adicionar`).

### Particularidades Tratadas

- **Itens dinâmicos** (`itens[INDEX][campo]`): descrição, veículo, plano de contas, valor
- **Parcelas geradas** (`parcelas[INDEX][campo]`): parcela, vencimento, valor
- **Config de parcelas**: ignorado automaticamente se não houver parcelas
- **Labels customizados** para campos de vínculo (Cliente, Fornecedor, etc.)
- **Transformação de valores**: Tipo (D→"Despesa", R→"Receita"), Pago (S→"Sim", N→"Não")

### Exemplo de Saída - Cadastro

```json
{
  "Dados Principais": [
    {"label": "Tipo", "de": null, "para": "Despesa (Pagar)"},
    {"label": "Conta Bancária", "de": null, "para": "Bradesco - Conta Corrente"},
    {"label": "Forma de Pagamento", "de": null, "para": "Boleto"},
    {"label": "Plano de Contas", "de": null, "para": "3.1.01 - Despesas Operacionais"},
    {"label": "Descrição", "de": null, "para": "Manutenção veículo ABC-1234"},
    {"label": "Data Criação", "de": null, "para": "15/01/2025"},
    {"label": "Data Vencimento", "de": null, "para": "20/01/2025"},
    {"label": "Fornecedor", "de": null, "para": "Auto Peças Silva"},
    {"label": "Subtotal", "de": null, "para": "R$ 1.500,00"},
    {"label": "Itens do Lançamento", "de": null, "para": [
      {"Descrição": "Troca de óleo", "Veículo": "ABC-1234 - Fiat Uno", "Valor": "R$ 200,00"},
      {"Descrição": "Filtro de ar", "Veículo": "ABC-1234 - Fiat Uno", "Valor": "R$ 150,00"}
    ]}
  ],
  "Parcelamento": [
    {"label": "Parcelas", "de": null, "para": [
      {"Parcela": "1/3", "Vencimento": "20/01/2025", "Valor": "R$ 500,00"},
      {"Parcela": "2/3", "Vencimento": "20/02/2025", "Valor": "R$ 500,00"},
      {"Parcela": "3/3", "Vencimento": "20/03/2025", "Valor": "R$ 500,00"}
    ]}
  ]
}
```

---

## Handler: Manutenções

**Arquivo:** `public/assets/js/audit-handlers/manutencoes-adicionar.js`

Handler para a página de Manutenções (`manutencoes/adicionar`).

### Particularidades Tratadas

- **Transições de status**: C→"Criada", A→"Aberta", F→"Fechada"
- **Valores de tanque**: 0→"Reserva", 4→"1/2", 8→"Cheio", etc.
- **Itens com estados**: novo, editando, pago
- **Auto-preenchimento**: campos preenchidos ao mudar status são auditados
- **Checkboxes de serviços**: Trocou Óleo, Trocou Pneus

### Exemplo de Saída - Edição

```json
{
  "Dados": [
    {"label": "Status", "de": "Criada", "para": "Aberta"},
    {"label": "Veículo", "de": null, "para": "ABC-1234 - Fiat Uno"},
    {"label": "Oficina", "de": null, "para": "Oficina do João"},
    {"label": "Data Envio", "de": null, "para": "15/01/2025 14:30"},
    {"label": "Odômetro Envio", "de": null, "para": "45.230 km"},
    {"label": "Tanque Envio", "de": null, "para": "3/4"},
    {"label": "Motivo do Envio", "de": null, "para": "Revisão programada"},
    {"label": "Trocou Óleo", "de": null, "para": "Sim"}
  ],
  "Itens": [
    {"label": "Itens da Manutenção", "de": null, "para": [
      {"Descrição": "Troca de óleo", "Quantidade": "1,000 UN", "Valor Unitário": "R$ 150,00", "Valor Total": "R$ 150,00", "Status": "Pendente"},
      {"Descrição": "Filtro de óleo", "Quantidade": "1,000 UN", "Valor Unitário": "R$ 45,00", "Valor Total": "R$ 45,00", "Status": "Pendente"}
    ]}
  ]
}
```

---

## Criar Novo Handler

Para criar um handler para uma nova página complexa:

### 1. Criar o Arquivo

```
public/assets/js/audit-handlers/{modulo}-{acao}.js
```

### 2. Implementar a Interface

```javascript
(function() {
    'use strict';

    const MeuHandler = {
        config: {
            customLabels: {
                'campo_bd': 'Label Legível'
            },
            valueLabels: {
                'status': { 'A': 'Ativo', 'I': 'Inativo' }
            }
        },

        capture(form) {
            const data = {};

            // Capturar campos simples
            const campos = [];
            // ... lógica de captura

            data['Nome da Aba'] = campos;
            return data;
        },

        captureInitial(form) {
            return this.capture(form);
        },

        getChanges(form, initialData) {
            const current = this.capture(form);
            const changes = {};
            // ... lógica de comparação
            return changes;
        }
    };

    // Registrar
    if (window.FormAudit && FormAudit.registerHandler) {
        FormAudit.registerHandler('modulo-acao', MeuHandler);
    }
})();
```

### 3. Adicionar Carregamento no Layout

```php
<!-- app/Views/layouts/iframe.php -->
<?php if (strpos($requestUri, 'modulo/acao') !== false): ?>
<script src="<?= asset('js/audit-handlers/modulo-acao.js'); ?>"></script>
<?php endif; ?>
```

### Helpers Disponíveis

O FormAudit expõe helpers para uso nos handlers:

```javascript
FormAudit.helpers.cleanLabel(text)       // Remove asteriscos e avisos
FormAudit.helpers.formatFieldName(name)  // Formata nome do campo
FormAudit.helpers.getFieldLabel(field)   // Obtém label do campo
FormAudit.helpers.getFieldValue(field)   // Obtém valor formatado
FormAudit.helpers.getTabName(field)      // Obtém nome da aba
FormAudit.helpers.shouldIgnoreField(field) // Verifica se deve ignorar
FormAudit.helpers.normalizeValue(val)    // Normaliza para comparação
```

---

## Arquivos do FormAudit

| Arquivo | Descrição |
|---------|-----------|
| `public/assets/js/form-audit.js` | Sistema base de captura |
| `public/assets/js/audit-handlers/financeiro-adicionar.js` | Handler para Financeiro |
| `public/assets/js/audit-handlers/manutencoes-adicionar.js` | Handler para Manutenções |
| `app/Views/layouts/iframe.php` | Carregamento condicional dos handlers |

---

## Tabelas de Banco

### Tabela `logs` (Auditoria)

```sql
CREATE TABLE logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(45) NOT NULL,
    id_funcionario INT,
    data DATETIME NOT NULL,
    ip VARCHAR(45),
    mensagem TEXT NOT NULL,
    campos_alterados JSON,
    INDEX idx_logs_data (chave, data)
);
```

### Tabela `security_logs` (Segurança)

```sql
CREATE TABLE security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type ENUM('rate_limit','fingerprint','quota','honeypot','block','suspicious') NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_id BIGINT,
    chave VARCHAR(45),
    endpoint VARCHAR(255) NOT NULL,
    details JSON,
    score INT DEFAULT 0,
    action_taken VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (ip_address),
    INDEX (event_type, created_at),
    INDEX (user_id, created_at)
);
```

---

## Configuração

**Arquivo:** `app/Config/Security.php`

```php
public const LOGGING = [
    'enabled' => true,
    'log_events' => [
        'rate_limit' => true,
        'fingerprint' => true,
        'quota' => true,
        'honeypot' => true,
        'block' => true,
        'suspicious' => true,
    ],
    'retention_days' => 30,
];
```

### Scores de Suspeita

| Faixa | Classificação | Ação |
|-------|---------------|------|
| 0-30 | Normal | Sem restrição |
| 31-50 | Suspeito | Throttling 0.5s |
| 51-70 | Muito Suspeito | Throttling 2s + alerta |
| 71-100 | Bot | Throttling 5s + bloqueio |

---

## API de Consulta

### `GET /api/logs`

Retorna logs paginados do tenant atual.

**Query Parameters:**
- `page` (int, default: 1)
- `perPage` (int, default: 10, max: 100)
- `search` (string, opcional) - busca em mensagem, nome do usuário, IP

**Permissão:** `logs.visualizar`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "data": "2025-01-15 10:30:45",
      "ip": "192.168.1.1",
      "mensagem": "João Silva, adicionou cliente [Empresa ABC]",
      "campos_alterados": "{...}",
      "usuario_nome": "João Silva"
    }
  ],
  "pagination": {
    "page": 1,
    "perPage": 10,
    "total": 500,
    "totalPages": 50,
    "hasNext": true,
    "hasPrev": false
  }
}
```

---

## Arquivos Relacionados

### Backend (PHP)

| Arquivo | Descrição |
|---------|-----------|
| `app/Services/AuditLogService.php` | Service de auditoria |
| `app/Services/SecurityLogService.php` | Service de segurança |
| `app/Services/CrossTenantDetectionService.php` | Service de detecção cross-tenant |
| `app/Services/CrossTenantCheckResult.php` | Classe de resultado cross-tenant |
| `app/Traits/Auditable.php` | Trait para auditoria em Models |
| `app/Traits/DetectsCrossTenant.php` | Trait para detecção cross-tenant |
| `app/Models/Log.php` | Model de logs |
| `app/Models/Security/SecurityLog.php` | Model de security logs |
| `app/Controllers/LogsController.php` | Controller da API |
| `app/Config/Security.php` | Configurações de segurança |
| `app/Middleware/RateLimitMiddleware.php` | Middleware de rate limiting |
| `app/Middleware/HoneypotMiddleware.php` | Middleware de honeypot |
| `app/Middleware/ThrottlingMiddleware.php` | Middleware de throttling |

### Frontend (JavaScript)

| Arquivo | Descrição |
|---------|-----------|
| `public/assets/js/form-audit.js` | Sistema base de captura de auditoria |
| `public/assets/js/audit-handlers/financeiro-adicionar.js` | Handler especializado para Financeiro |
| `public/assets/js/audit-handlers/manutencoes-adicionar.js` | Handler especializado para Manutenções |
| `app/Views/layouts/iframe.php` | Layout com carregamento condicional dos handlers |
