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

Em fluxos de edicao, envie somente campos cujo valor normalizado realmente
mudou. Reenvios sem alteracao nao devem criar uma nova entrada de auditoria.

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
- `id_funcionario` - Usuário logado (de `$_SESSION['user_id']`) ou `0` para
  webhooks, CRONs e outros processos automáticos sem funcionário em sessão
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
- **Cliente pagador**: registra o nome anterior/novo e a confirmação de que o
  usuário foi informado; lançamentos financeiros existentes não são alterados

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
    id_funcionario INT UNSIGNED NOT NULL,
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

## Auditoria operacional das locacoes

Na edicao e confirmacao de reservas, `LocacaoEstadoOperacional` compara os dados
persistidos antes/depois dentro da transacao bloqueada da locacao. Status,
datas operacionais, veiculo/grupo, odometros, combustivel e disponibilidade
nao dependem de `_audit_changes`. Labels operacionais recebidos do formulario
sao descartados; os demais campos continuam usando FormAudit.

O log de atualizacao contem as diferencas operacionais reais, sem duplicar logs
de saida/devolucao. O credito de devolucao gerado permanece identificado no log.
A confirmacao P->R possui log proprio. A gravacao usa
`registrarComCamposNaTransacao()` e falhas impedem o commit. Operacoes rejeitadas
ou revertidas nao deixam logs de sucesso. Os snapshots usam IDs dos vinculos e
veiculos para distinguir alteracoes no historico.

## Exclusao de contrato/locacao com financeiro

`ExclusaoVinculoFinanceiroService` registra uma entrada por lancamento efetivamente
removido, incluindo os dependentes apagados junto com o principal. Cada entrada
contem ID, origem contrato/locacao, dados financeiros anteriores, itens e referencias
de transacoes sem payloads sensiveis. O log do principal registra quantidades e
valores em aberto, pagos e total. Ajustes de encerramento registram tambem motivo.
Todos usam `registrarComCamposNaTransacao()`; falha de auditoria reverte a exclusao.


## Aba IA/MCP — auditoria resumida

A tela Logs do Sistema inclui as abas Auditoria, Envios e IA/MCP. IA/MCP consulta
os registros existentes de `mcp_audit`; não copia dados para `logs` nem altera
o mecanismo de auditoria do transporte. A evolução de conteúdo abaixo acrescenta
a captura nas ferramentas. Requer `logs.visualizar`, como as outras
abas, e mostra somente o tenant autenticado.

A aba lista as 11 ferramentas de consulta definidas no catálogo MCP. Não exibe
eventos `request`, `token`, consentimento ou descoberta, evitando duplicar uma
chamada com os registros técnicos do transporte. Cada chamada é uma linha;
uma pergunta do ChatGPT pode gerar várias chamadas e novas tentativas.
O endpoint MCP não recebe automaticamente uma transcrição da conversa. O repasse
de pergunta descrito na evolução abaixo acrescenta texto informado pela IA.

Campos: data/hora, funcionário, aplicativo aprovado, operação legível e nome da
ferramenta, prévias de solicitação/retorno, resultado e duração em milissegundos.
Registros retornados fica no painel de detalhes. A referência
técnica da requisição aparece no tooltip do resultado. `success` é Concluída;
`blocked` é Não concluída, pois a auditoria histórica não distingue todas as
causas de falha. Registros retornados é a quantidade da página, detalhe ou
agrupamento retornado, e não o total encontrado na base.

`GET /api/logs/mcp` aceita `page` (padrão 1), `perPage` (padrão 10, máximo 100) e
`search` (até 200 caracteres). Retorna `success`, `data` e `pagination`, seguindo
as APIs de logs. As linhas contêm somente `id`, `data`, `usuario_nome`,
`aplicativo`, `evento`, `operacao`, `resultado`, `registros`, `duracao_ms` e
`request_id`. A paginação informa também `returned`. Sem busca, `total` e
`totalPages` são null; com busca, há contagem. Os resultados são ordenados por ID
decrescente. A busca contempla funcionário, aplicativo, nome técnico e nome
traduzido da operação.

A leitura usa McpAuditLog → QueryBuilder, conexão Singleton e
`table(...)->withChave()` com contexto obrigatório. O JOIN com funcionários
confere também a chave; mcp_clients é global. Funcionário ou aplicativo ausente
recebe indicação de indisponibilidade na interface. Não remover o escopo tenant
nem copiar o padrão legado `withoutChave()->where(chave)` do Model Log.
A API não retorna mensagens internas de exceção. Textos da nova aba são escapados
e respostas atrasadas são descartadas ao mudar de aba, busca ou página.

A entrega inicial não tinha migration nem coleta de conteúdo; a evolução abaixo
adiciona solicitação e retorno. O histórico de metadados existente permanece disponível.
A nova aba tem traduções nos cinco idiomas suportados. O JavaScript está no
script inline da view PHP, conforme a tela existente; nenhum asset JS/CSS ou
arquivo minificado foi alterado.

Validação local em 16/11/2026: 213 verificações de integração MCP, incluindo
isolamento, JOINs, permissões, paginação/busca e estados vazios da nova API;
51 CIMD/piloto e 14 CGI/SAPI aprovadas. O teste do JavaScript renderizado cobre
escape de textos/atributos, respostas fora de ordem entre abas, paginação,
estado vazio e mensagens de erro seguras. Lint PHP e verificação de sintaxe JS
aprovados. Não houve envio de e-mail, SMS ou WhatsApp.

Validação em produção em 16/11/2026 após publicação por FTP e conferência dos
hashes: a aba IA/MCP exibiu cinco chamadas históricas, com aplicativo ChatGPT,
funcionário, operações, resultados, quantidades e duração. A busca traduzida
“Listar veículos” retornou três entradas; a busca sem correspondências exibiu
“Nenhum log encontrado” e 0–0 de 0 registros. As abas Auditoria e Envios
continuaram carregando seus históricos. A conferência foi somente de leitura.


## Solicitação e retorno MCP — evolução de 16/11/2026

Na entrega inicial desta evolução, a listagem IA/MCP tinha nove colunas: data,
usuário, aplicativo, operação,
solicitação ao MCP, retorno do MCP, resultado, duração e ações. Prévias têm no
máximo 255 caracteres, incluindo reticências, com acentos e emojis preservados.
Os nomes se referem à chamada da ferramenta e aos dados devolvidos, não à pergunta
original ou à resposta final redigida pelo ChatGPT.

O olho está disponível em todas as linhas e abre o offcanvas global existente por
`openOffcanvasContent`, com JSON formatado e escapado, os metadados, registros
retornados e referência técnica. O conteúdo é buscado sob demanda, não enviado
inteiro na listagem. Respostas atrasadas não abrem detalhes após troca de aba,
busca/página ou seleção de outro registro. Erros usam o alerta global.

`GET /api/logs/mcp` acrescenta `solicitacao_previa`, `retorno_previa` e
`conteudo_disponivel`; SQL lê no máximo 256 caracteres por prévia e o Controller
aplica o limite final de 255 com reticências. A busca e a paginação mantêm as regras
anteriores; a busca não varre os payloads completos. `GET /api/logs/mcp/{id}`
retorna `success` e `data`, com os metadados e `solicitacao`/`retorno` como strings
JSON ou null. Exige `logs.visualizar`, autenticação e tenant; registros de outro
tenant, inexistentes ou eventos técnicos recebem o mesmo 404. Erros internos
recebem mensagem genérica.

A migration `00442_add_mcp_audit_payloads.php` adiciona duas colunas LONGTEXT NULL
sem backfill. Registros anteriores exibem “Conteúdo não registrado nesta chamada”,
mas preservam seus metadados no olho. Novas chamadas guardam solicitação e retorno
na mesma inserção, incluindo o erro público em falhas da ferramenta. Nenhum dado
de autenticação, SQL ou exceção interna é copiado. O conteúdo pode incluir os dados
pessoais e financeiros autorizados na consulta original; por decisão do responsável,
`logs.visualizar` permite sua leitura no tenant e não haverá expiração automática.
Isso substitui a restrição da entrega resumida contra persistir argumentos/retornos.

Validação local: migration aplicada exclusivamente por
`php migrate.php --env=development`; 223 verificações de integração MCP,
51 CIMD/piloto e 14 CGI/SAPI aprovadas. Testes cobrem captura exata da resposta,
argumentos declarados, erro público, isolamento do detalhe, permissão, previews
Unicode, histórico nulo e conteúdo completo. O teste de interface cobre nove
colunas, quantidade apenas no olho, escape HTML no painel, estado vazio e respostas
atrasadas. Nenhuma mensagem externa foi enviada pelos testes locais.

Implantação: publicar a migration por FTP; o responsável executa manualmente
`php migrate.php --env=production` na raiz do servidor. A publicação do código
dependente aguarda a confirmação da execução. Não usar scripts alternativos nem
aplicar o DDL diretamente. Não há alteração de assets JS/CSS: o script continua
inline na view PHP.


Publicação e conferência em produção concluídas em 16/11/2026: o responsável
confirmou a execução manual da migration; somente depois foram publicados os
arquivos dependentes por FTP, com cópias anteriores e hashes verificados.
No mesmo chat do ChatGPT foi enviada novamente a pergunta sobre o total de
veículos. A resposta foi 19 veículos; a chamada `listar_veiculos` registrou
`pagina=1`, `por_pagina=10`, sucesso e 49 ms, referência
`1c7619c05cf424e6f275879b5dd9b435`. A nova linha exibiu as prévias e o olho abriu
os parâmetros e retorno formatados, com dez registros retornados no painel.
Um registro histórico também abriu com seus metadados e indicação de conteúdo
não registrado. A quantidade saiu da tabela. Não houve alterações de dados de
negócio durante a verificação; foi uma consulta real autenticada pelo ChatGPT.


## Pergunta repassada pela IA e retorno somente no olho — 16/11/2026

A listagem atual tem oito colunas: data, usuário, aplicativo, operação, Solicitação
ao MCP, resultado, duração e ações. Solicitação ao MCP mostra a pergunta informada
pela IA, com no máximo 255 caracteres Unicode incluindo reticências, em vez do JSON
técnico. Sem texto, mostra “Pergunta não informada pela IA”. Retorno do MCP sai da
listagem; sua gravação e visualização integral no olho permanecem.

O painel mostra “Pergunta do cliente (informada pela IA)” com o texto integral,
preservando espaços/quebras de linha, e mantém os JSONs de solicitação técnica,
retorno e os metadados. Todo conteúdo é escapado. A pergunta é repassada pelo
cliente MCP; não representa uma cópia original verificada independentemente.
As instruções pedem fidelidade literal, sem reformular ou inventar.

`GET /api/logs/mcp` acrescenta `pergunta_previa` (string/null, até 255 caracteres)
e preserva os campos anteriores para compatibilidade. A extração SQL lê somente
um prefixo limitado da pergunta serializada no JSON existente; não envia a pergunta inteira nem o
retorno completo. `GET /api/logs/mcp/{id}` acrescenta `pergunta_usuario`
(string/null). JSON inválido, ausência, null e valores que não sejam string são
tratados como pergunta indisponível. Não há backfill nem migration. Mantêm-se
`logs.visualizar`, isolamento por tenant, ordenação, paginação e busca atuais.
A busca não passa a varrer perguntas/payloads. Retenção continua sem expiração.

A camada de ferramentas recebe o novo argumento opcional e o grava em
`request_payload.argumentos.pergunta_usuario`, separado dos filtros e das
projeções de dados. Texto informado não modifica a consulta nem a autorização.
O schema local e a extração JSON foram validados via terminal antes da alteração.
Os testes incluem as 11 ferramentas, compatibilidade sem pergunta, mensagens
literais Unicode/quebras de linha, múltiplas chamadas/páginas e continuação curta;
API e interface verificam truncamento só da prévia, detalhe integral, histórico
antigo/JSON inválido, ausência da coluna de retorno e escape de HTML.
O script permanece inline na view PHP; nenhum asset JS/CSS foi alterado.

Validação local desta evolução: 280 verificações de integração MCP, 51
CIMD/piloto e 14 CGI/SAPI aprovadas; teste de interface, lint PHP e sintaxe JS
aprovados. Sem execução de migrations nesta entrega.


Na validação externa, a pergunta chegou integralmente no JSON, mas a extração
`JSON_UNQUOTE` do MariaDB 10.2.44 de produção substituiu o emoji por “?”. O diagnóstico
read-only comparou o JSON original com o valor extraído e confirmou a diferença.
A leitura agora mantém o JSON da pergunta serializado pelo banco e o decodifica
em PHP, evitando essa conversão. Na lista, SQL limita a 3.074 caracteres JSON
(256 pontos de código de até 12 caracteres escapados, mais as aspas); o Model
recupera somente o sufixo eventualmente cortado e devolve no máximo 256 caracteres
Unicode, para o Controller gerar a prévia final de 255. No olho, decodifica a
pergunta integral. A gravação continua usando JSON_UNESCAPED_UNICODE.
A correção foi validada via SELECTs no servidor de produção com o registro real
e quatro cenários no formato efetivamente gravado: emojis longos, escapes de
controles, limite da prévia e barras literais. Não houve escrita no banco pelo
diagnóstico. Os testes locais incluem ainda bordas de escape e pares substitutos.


Validação externa concluída em 16/11/2026 no mesmo chat do piloto. Após usar
“Atualizar ferramentas” em Gerenciar aplicativo no ChatGPT, as 11 descrições
passaram a anunciar `pergunta_usuario`; a conexão OAuth existente foi preservada.
A mensagem de teste teve duas linhas e emoji: “Consulte o 7Carros agora: quantos
veículos tenho no total?” seguida de “Pode conferir novamente, por favor? 🚙”.
O ChatGPT retornou 19 veículos e a chamada `listar_veiculos` registrou dez linhas,
49 ms, referência `a3c2284e631516e30e55d9c3604ad1e9`. O JSON recebido preservou
literalmente o texto; após corrigir a extração de Unicode do MariaDB antigo,
a listagem e o olho também mostraram o emoji e o painel preservou a quebra de
linha. Retorno do MCP não aparece na tabela de oito colunas e permanece nos
detalhes. Registros anteriores mostram “Pergunta não informada pela IA”.
Arquivos publicados por FTP com backups e hashes verificados. Nenhuma migration,
recriação de conexão ou alteração de permissões foi necessária.


## Painel MCP no padrão de Detalhes do Envio — 16/11/2026

O painel “Detalhes da chamada MCP” usa o mesmo padrão visual de Detalhes do Envio:
metadados em linhas compactas com três colunas de grid (rótulo com fundo cinza-claro
em uma coluna, valor nas duas seguintes), bordas e cantos arredondados. Preserva
os oito campos existentes. A pergunta, a solicitação técnica e o retorno ficam em
três blocos com cabeçalhos discretos e conteúdo completo. Texto longo e referência
técnica quebram linha; pergunta preserva espaços/quebras e JSON continua formatado.

A largura enviada ao offcanvas global é 40%, como Envios; até 768 px o CSS existente
ocupa 100% da largura. Não há novo modal, asset CSS/JS ou alteração no painel de
Envios. A mudança fica na renderização inline da view PHP. APIs, gravação,
permissões, traduções, carregamento sob demanda, mensagens de ausência, escape de
HTML e descarte de respostas atrasadas permanecem vigentes. Nenhuma migration.

Validação local: lint PHP, sintaxe JS e teste da interface renderizada aprovados.
O teste existente verifica largura, oito linhas de metadados, três blocos, conteúdo
integral da pergunta/retorno, escape de HTML, histórico sem conteúdo e respostas
fora de ordem. Publicação da view e documentação por FTP, com cópias anteriores
e hashes, seguida da conferência visual em produção.


Conferência em produção: painel desktop exibiu a largura de 40%, metadados
compactos e blocos delimitados, com pergunta literal e emoji preservados.
A tentativa de emulação móvel encontrou “Sessão expirada” antes de concluir a
conferência visual; não é registrada como teste móvel aprovado. A emulação foi
desativada, DevTools fechado e o dashboard autenticado voltou a ser observado.
O comportamento responsivo foi conferido no CSS existente (largura total até
768 px); não houve alteração de autenticação nem de estilos globais nesta entrega.


## Eventos de segurança MCP — 16/11/2026

`mcp_audit` também recebe `security_infraction`, `security_suspended`,
`security_released` e `security_quota`. São eventos técnicos exibidos na
administração IA/MCP, não na aba de consultas de Logs do Sistema. Funcionários
veem seus eventos; `mcp.administrar` permite ver os do tenant. A página apresenta
os últimos 50 registros visíveis, com data, funcionário/ator, motivo e request ID.
Liberação registra o administrador em `employee_id` e o funcionário afetado em
`request_payload.employee_id`. Eventos não duplicam perguntas, respostas pessoais,
SQL ou credenciais. Acesso e retenção do histórico de consultas permanecem iguais.
Veja as regras de bloqueio/cotas e implantação em [mcp.md](mcp.md).
