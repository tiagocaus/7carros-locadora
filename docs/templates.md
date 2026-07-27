# Sistema de Templates de Mensagem

Sistema para gerenciar templates de mensagens enviadas por email, WhatsApp e SMS, com suporte a personalização por tenant e internacionalização.

## Visão Geral

### Arquitetura

```
┌─────────────────────────────────────────────────────────────┐
│                    TEMPLATE SYSTEM                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────────┐                                    │
│  │ message_template_   │  Tipos de template do sistema      │
│  │ types               │  (welcome, rental_confirmation...) │
│  └─────────┬───────────┘                                    │
│            │                                                │
│            ▼                                                │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ message_templates                                    │   │
│  │ ┌─────────────────┐  ┌─────────────────────────────┐│   │
│  │ │ chave = '0'     │  │ chave = {tenant}            ││   │
│  │ │ (padrão sistema)│  │ (customizado por empresa)   ││   │
│  │ └─────────────────┘  └─────────────────────────────┘│   │
│  └─────────────────────────────────────────────────────┘   │
│                       │                                     │
│                       ▼                                     │
│            ┌─────────────────────┐                          │
│            │ MessageTemplate     │                          │
│            │ Service             │                          │
│            └─────────┬───────────┘                          │
│                      │                                      │
│            ┌─────────┴───────────┐                          │
│            ▼                     ▼                          │
│  ┌─────────────────┐  ┌─────────────────┐                   │
│  │ TemplateRenderer│  │ TemplateVariables│                  │
│  └─────────────────┘  └─────────────────┘                   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Canais Suportados

| Canal | Descrição | Editor | Limite |
|-------|-----------|--------|--------|
| **email** | HTML rico com layout base | TinyMCE | - |
| **whatsapp** | Texto com markdown básico | Textarea | 4096 chars |
| **sms** | Texto puro | Textarea | 160 chars |

### Templates Disponíveis

| Slug | Categoria | Canais | Descrição |
|------|-----------|--------|-----------|
| `welcome` | onboarding | email, whatsapp | Boas-vindas ao cliente |
| `rental_confirmation` | rental | email, whatsapp, sms | Confirmação de locação |
| `contract_confirmation` | rental | email, whatsapp | Confirmação de contrato |
| `signature_request` | rental | email, whatsapp, sms | Pedido de assinatura digital |
| `return_reminder` | reminder | email, whatsapp, sms | Lembrete de devolução |
| `payment_reminder` | reminder | email, whatsapp, sms | Lembrete de pagamento |
| `invoice_generated` | billing | email, whatsapp | Fatura gerada |
| `overdue_notice` | billing | email, whatsapp, sms | Aviso de atraso |
| `cnh_expiring` | reminder | email, whatsapp | CNH próxima do vencimento |

`payment_reminder` deve receber `fatura.link_boleto` quando a origem for parcela de contrato, pois o fluxo cria/reutiliza `pagamentos_links` antes de enfileirar a mensagem. O link publico deve ser estavel: mensagens antigas continuam abrindo a fatura atualizada quando valor, vencimento, juros, multa ou desconto mudarem.

Notificacoes com contexto de fatura devem informar a parcela quando
`fatura.parcela > 0`. O `MessageTemplateService` acrescenta a descricao
localizada automaticamente ao corpo se o template nao usar uma das variaveis
de parcela. Isso tambem protege templates customizados antigos. O assunto do
email nao e alterado. Valores com `parcela = 0` sao omitidos.

## Estrutura de Dados

### Tabela `message_template_types`

Define os tipos de template disponíveis no sistema.

```sql
CREATE TABLE message_template_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) UNIQUE NOT NULL,
    name_key VARCHAR(100) NOT NULL,           -- Chave i18n
    description_key VARCHAR(100),             -- Chave i18n
    category ENUM('onboarding', 'rental', 'reminder', 'billing'),
    channels JSON NOT NULL,                   -- ["email", "whatsapp", "sms"]
    available_variables JSON NOT NULL,        -- ["cliente", "empresa", "locacao"]
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0
);
```

### Tabela `message_templates`

Templates do sistema (padrão e customizados). A coluna `chave` define se é padrão ou customizado:
- `chave = '0'` → Template padrão do sistema
- `chave = {tenant}` → Template customizado por empresa

```sql
CREATE TABLE message_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(45) NOT NULL,               -- '0' = padrão, {tenant} = customizado
    template_type_id INT UNSIGNED NOT NULL,
    locale VARCHAR(10) NOT NULL,              -- pt_BR, en_US, es_ES, pt_PT, it_IT
    channel ENUM('email', 'sms', 'whatsapp'),
    subject VARCHAR(255),                     -- Apenas email
    content LONGTEXT NOT NULL,
    content_plain TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT UNSIGNED,
    updated_by INT UNSIGNED,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    UNIQUE KEY (chave, template_type_id, locale, channel),
    FOREIGN KEY (template_type_id) REFERENCES message_template_types(id)
);
```

## Classes Principais

### MessageTemplateService

**Arquivo:** `app/Services/MessageTemplateService.php`

Service principal para gerenciamento de templates.

```php
$service = new MessageTemplateService($mysqli, $chave);

// Buscar template (custom > default > fallback pt_BR)
$template = $service->getTemplate('welcome', 'email', 'pt_BR');

// Renderizar template com contexto
$rendered = $service->render('rental_confirmation', 'email', [
    'cliente' => ['nome' => 'João', 'email' => 'joao@email.com'],
    'empresa' => ['nome_fantasia' => 'ABC Locadora'],
    'locacao' => ['numero' => 'LOC-001', 'data_retirada' => '2024-12-15'],
    'veiculo' => ['placa' => 'ABC-1234', 'modelo' => 'Civic']
]);

// $rendered = [
//     'subject' => 'Confirmação de Locação #LOC-001',
//     'content' => '<html>...</html>',       // Com layout (email)
//     'content_plain' => 'Texto puro...',
//     'locale' => 'pt_BR',
//     'channel' => 'email',
//     'type_slug' => 'rental_confirmation',
//     'is_custom' => false
// ]

// Salvar template customizado
$id = $service->saveTemplate('welcome', 'email', 'pt_BR', [
    'subject' => 'Bem-vindo!',
    'content' => '<h2>Olá {{cliente.nome}}!</h2>...'
], $userId);

// Restaurar para padrão
$service->restoreDefault('welcome', 'email', 'pt_BR');

// Listar tipos
$types = $service->getTemplateTypes('rental'); // Filtro por categoria

// Variáveis disponíveis
$vars = $service->getAvailableVariables('rental_confirmation', 'pt_BR');

// Validar template
$errors = $service->validateTemplate('welcome', $content);

// Preview (destaca variáveis)
$preview = $service->preview($content);
```

### TemplateRenderer

**Arquivo:** `app/I18n/TemplateRenderer.php`

Renderiza templates substituindo variáveis.

```php
$renderer = new TemplateRenderer();
$renderer->setLocale('pt_BR');

// Renderizar template
$html = $renderer->render($template, $context);

// Extrair variáveis
$vars = $renderer->extractVariables($template);
// ['cliente.nome', 'empresa.cnpj', 'locacao.data_retirada']

// Validar variáveis
$errors = $renderer->validateVariables($template, ['cliente', 'empresa']);

// Converter para texto puro
$text = $renderer->toPlainText($html);

// Converter template legado
$new = $renderer->convertLegacy('Olá $cNome!');
// 'Olá {{cliente.nome}}!'
```

### TemplateVariables

**Arquivo:** `app/I18n/TemplateVariables.php`

Define e resolve variáveis disponíveis.

```php
// Todas as variáveis
$all = TemplateVariables::getAll();

// Variáveis de uma entidade
$clienteVars = TemplateVariables::getForEntity('cliente');

// Resolver valor
$value = TemplateVariables::resolve('cliente.cpf_cnpj', $context, 'pt_BR');
// '123.456.789-00' (formatado)

// Verificar existência
$exists = TemplateVariables::exists('cliente.nome'); // true

// Para frontend (com labels traduzidos)
$frontend = TemplateVariables::getForFrontend('pt_BR');
```

## Sistema de Variáveis

### Formato

```
{{entidade.campo}}

Exemplos:
{{cliente.nome}}
{{empresa.cnpj}}
{{locacao.data_retirada}}
{{veiculo.descricao_completa}}
{{fatura.valor}}
{{outros.data_atual}}
```

### Entidades Disponíveis

#### `cliente`
| Variável | Tipo | Descrição |
|----------|------|-----------|
| `nome` | text | Nome completo |
| `primeiro_nome` | computed | Primeiro nome |
| `cpf_cnpj` | document | CPF ou CNPJ formatado |
| `email` | text | Email |
| `telefone` | phone | Telefone formatado |
| `endereco_completo` | computed | Endereço completo |
| `cnh_numero` | text | Número da CNH |
| `cnh_validade` | date | Validade da CNH |

#### `empresa`
| Variável | Tipo | Descrição |
|----------|------|-----------|
| `nome_fantasia` | text | Nome fantasia |
| `razao_social` | text | Razão social |
| `cnpj` | document | CNPJ formatado |
| `email` | text | Email |
| `telefone` | phone | Telefone formatado |
| `site` | text | Website |
| `endereco_completo` | computed | Endereço completo |

#### `locacao`
| Variável | Tipo | Descrição |
|----------|------|-----------|
| `numero` | text | Número da locação |
| `data_retirada` | date | Data de retirada |
| `hora_retirada` | text | Hora de retirada |
| `local_retirada` | text | Local de retirada |
| `data_devolucao` | date | Data de devolução |
| `hora_devolucao` | text | Hora de devolução |
| `local_devolucao` | text | Local de devolução |
| `valor_total` | currency | Valor total formatado |
| `quantidade_dias` | text | Quantidade de dias |

#### `veiculo`
| Variável | Tipo | Descrição |
|----------|------|-----------|
| `placa` | text | Placa |
| `modelo` | text | Modelo |
| `marca` | text | Marca |
| `ano` | text | Ano |
| `cor` | text | Cor |
| `descricao_completa` | computed | Marca Modelo Ano - Cor - Placa |

#### `contrato`
| Variável | Tipo | Descrição |
|----------|------|-----------|
| `numero` | text | Número do contrato |
| `data_inicio` | date | Data de início |
| `data_fim` | date | Data de fim |
| `valor_total` | currency | Valor total |

#### `fatura`
| Variável | Tipo | Descrição |
|----------|------|-----------|
| `numero` | text | Número da fatura |
| `valor` | currency | Valor formatado |
| `data_vencimento` | date | Data de vencimento |
| `status` | text | Status |
| `link_boleto` | text | Link do boleto |
| `pix_copia_cola` | text | Código PIX |
| `dias_atraso` | computed | Dias em atraso |
| `parcela` | text | Numero da parcela |
| `total_parcelas` | text | Total de parcelas |
| `parcela_descricao` | computed | Descricao localizada, por exemplo `Parcela 2 de 12` |

#### `outros`
| Variável | Tipo | Descrição |
|----------|------|-----------|
| `data_atual` | date | Data atual |
| `hora_atual` | text | Hora atual |
| `data_atual_extenso` | computed | Data por extenso |
| `ano_atual` | text | Ano atual |
| `link_portal` | text | Link do portal |
| `funcionario_nome` | text | Nome do funcionário |

### Formatação por Tipo

```php
// CURRENCY - Formatado por locale
{{locacao.valor_total}}
// pt_BR: R$ 1.500,00
// en_US: $ 1,500.00

// DATE - Formatado por locale
{{locacao.data_retirada}}
// pt_BR: 15/12/2024
// en_US: 12/15/2024

// PHONE - Formato brasileiro
{{cliente.telefone}}
// (11) 98765-4321

// DOCUMENT - CPF/CNPJ
{{cliente.cpf_cnpj}}
// 123.456.789-00

// COMPUTED - Calculado
{{cliente.primeiro_nome}}     // João
{{veiculo.descricao_completa}} // Honda Civic 2024 - Prata - ABC-1234
```

## Herança de Templates

### Prioridade de Busca

```
1. Template customizado (message_templates)
   WHERE chave = {tenant} AND template_type_id = ? AND locale = ? AND channel = ?
      │
      ├─ Encontrado → Retorna com is_custom = true
      │
      └─ Não encontrado
            │
            ▼
2. Template padrão (message_templates)
   WHERE chave = '0' AND template_type_id = ? AND locale = ? AND channel = ?
      │
      ├─ Encontrado → Retorna com is_custom = false
      │
      └─ Não encontrado
            │
            ▼
3. Fallback para pt_BR (se locale != 'pt_BR')
   Recursivamente busca com locale = 'pt_BR'
      │
      └─ Não encontrado → Retorna null
```

### Salvamento

Ao salvar um template:
1. Verifica se existe customização
2. Se existe: UPDATE
3. Se não existe: INSERT
4. Registra `created_by`/`updated_by`

### Restauração

```php
$service->restoreDefault('welcome', 'email', 'pt_BR');
// DELETE FROM message_templates WHERE chave = ? AND ...
// Próxima busca usará o template padrão
```

## Layout Base de Email

**Arquivo:** `app/Views/emails/layout.php`

Emails são automaticamente envolvidos em um layout base.

O layout de tenant exibe a logo cadastrada na matriz principal por uma URL
absoluta e assinada. Quando a logo estiver ausente ou o arquivo nao existir, o
cabecalho usa apenas o nome fantasia. O rodape mantem telefone, email, razao
social, CNPJ e endereco. Esse mesmo layout deve ser aplicado a conteudos HTML
consolidados por `MessageTemplateService::renderEmailLayout()`, mesmo quando o
corpo nao vier de um template individual.

O layout padrao permanece limitado a `600px`. Conteudos tabulares que informam
`_email_layout = wide`, como lotes com duas ou mais cobrancas, usam `100%` da
largura disponivel com limite de `1000px`. Em telas de ate `640px`, o container
volta a `100%` e reduz os espacamentos para preservar a leitura.

### Estrutura

```html
<!DOCTYPE html>
<html>
<head>
    <title>{{empresa.nome_fantasia}}</title>
</head>
<body>
    <table width="600">
        <!-- ou width="100%" e max-width:1000px no modo wide -->
        <!-- HEADER -->
        <tr>
            <td style="background: #1a56db;">
                [logo do tenant, quando disponivel]
                <h1>{{empresa.nome_fantasia}}</h1>
            </td>
        </tr>

        <!-- CONTEÚDO (template renderizado) -->
        <tr>
            <td>
                {{content}}
            </td>
        </tr>

        <!-- FOOTER -->
        <tr>
            <td>
                {{empresa.telefone}}
                {{empresa.email}}
                {{empresa.razao_social}}
                {{empresa.cnpj}}
                {{empresa.endereco_completo}}
            </td>
        </tr>
    </table>
</body>
</html>
```

### Fluxo

```
1. Template é renderizado (variáveis substituídas)
2. Para emails: conteúdo é inserido no layout
3. {{content}} é substituído pelo conteúdo
4. Variáveis do layout também são renderizadas
5. Resultado final é HTML completo
```

## API Endpoints

### Listar Tipos

```http
GET /api/templates/types?category=rental
Authorization: templates.visualizar

Response:
{
    "success": true,
    "data": [
        {
            "id": 2,
            "slug": "rental_confirmation",
            "name": "Confirmação de Locação",
            "category": "rental",
            "channels": ["email", "whatsapp", "sms"],
            "available_variables": ["cliente", "empresa", "locacao", "veiculo"],
            "is_customized": true
        }
    ]
}
```

### Buscar Template

```http
GET /api/templates/rental_confirmation?channel=email&locale=pt_BR
Authorization: templates.visualizar

Response:
{
    "success": true,
    "data": {
        "slug": "rental_confirmation",
        "channel": "email",
        "locale": "pt_BR",
        "subject": "Confirmação de Locação #{{locacao.numero}}",
        "content": "<h2>Locação Confirmada!</h2>...",
        "is_custom": false,
        "type": { ... }
    }
}
```

### Preview

```http
GET /api/templates/rental_confirmation/preview?channel=email&locale=pt_BR
Authorization: templates.visualizar

Response:
{
    "success": true,
    "data": {
        "subject": "Confirmação de Locação #LOC-2024-001234",
        "content": "<html>...HTML renderizado com dados de exemplo...</html>",
        "content_plain": "Texto puro...",
        "locale": "pt_BR",
        "channel": "email"
    }
}
```

### Variáveis Disponíveis

```http
GET /api/templates/variables/rental_confirmation?locale=pt_BR
Authorization: templates.visualizar

Response:
{
    "success": true,
    "data": {
        "cliente": {
            "label": "Cliente",
            "variables": [
                {"variable": "{{cliente.nome}}", "label": "Nome", "example": "João da Silva"},
                {"variable": "{{cliente.email}}", "label": "Email", "example": "joao@email.com"}
            ]
        },
        "empresa": { ... },
        "locacao": { ... },
        "veiculo": { ... }
    }
}
```

### Salvar Template

```http
POST /api/templates/rental_confirmation
Authorization: templates.editar
Content-Type: application/json

{
    "channel": "email",
    "locale": "pt_BR",
    "subject": "Confirmação de Locação #{{locacao.numero}}",
    "content": "<h2>Locação Confirmada!</h2>..."
}

Response:
{
    "success": true,
    "message": "Template salvo com sucesso",
    "data": {"id": 123}
}
```

### Restaurar Padrão

```http
POST /api/templates/rental_confirmation/restore
Authorization: templates.editar
Content-Type: application/json

{
    "channel": "email",
    "locale": "pt_BR"
}

Response:
{
    "success": true,
    "message": "Template restaurado para o padrão"
}
```

## Exemplos de Uso

### Enviar Email de Confirmação

```php
use App\Services\MessageTemplateService;

$service = new MessageTemplateService($mysqli, currentChave());

// Preparar contexto
$context = [
    'cliente' => [
        'nome' => $cliente['nome_rsocial'],
        'email' => $cliente['email'],
        'cpf_cnpj' => $cliente['cpf_cnpj'],
        'telefone' => $cliente['telefone']
    ],
    'empresa' => [
        'nome_fantasia' => $empresa['nome_fantasia'],
        'razao_social' => $empresa['razao_social'],
        'cnpj' => $empresa['cnpj'],
        'email' => $empresa['email'],
        'telefone' => $empresa['telefone']
    ],
    'locacao' => [
        'numero' => $locacao['numero'],
        'data_retirada' => $locacao['data_retirada'],
        'hora_retirada' => $locacao['hora_retirada'],
        'valor_total' => $locacao['valor_total']
    ],
    'veiculo' => [
        'placa' => $veiculo['placa'],
        'modelo' => $veiculo['modelo'],
        'marca' => $veiculo['marca']
    ]
];

// Renderizar (locale do cliente ou fallback)
$rendered = $service->render(
    'rental_confirmation',
    'email',
    $context,
    $cliente['preferred_locale'] ?? null
);

if ($rendered) {
    // Enviar email
    $mailer->send(
        to: $context['cliente']['email'],
        subject: $rendered['subject'],
        html: $rendered['content'],
        text: $rendered['content_plain']
    );
}
```

### Enviar WhatsApp

```php
$rendered = $service->render('rental_confirmation', 'whatsapp', $context);

if ($rendered) {
    $whatsapp->send(
        to: $context['cliente']['telefone'],
        message: $rendered['content']
    );
}
```

### Validar Antes de Salvar

```php
$errors = $service->validateTemplate('welcome', $content);

if (!empty($errors)) {
    foreach ($errors as $error) {
        // $error['variable'] => '{{cliente.inexistente}}'
        // $error['error'] => 'variable_not_found'
        // $error['message'] => 'Variável não encontrada'
    }
}
```

## Permissões

| Permissão | Descrição |
|-----------|-----------|
| `templates.visualizar` | Visualizar e listar templates |
| `templates.editar` | Editar, salvar e restaurar templates |

## Arquivos Relacionados

| Arquivo | Descrição |
|---------|-----------|
| `app/Services/MessageTemplateService.php` | Service principal |
| `app/I18n/TemplateRenderer.php` | Renderização de templates |
| `app/I18n/TemplateVariables.php` | Definição de variáveis |
| `app/Controllers/MessageTemplateController.php` | API endpoints |
| `app/Models/MessageTemplate.php` | Model legado |
| `app/Views/emails/layout.php` | Layout base de emails |
| `app/Views/pages/configuracoes/templates/index.php` | Listagem |
| `app/Views/pages/configuracoes/templates/editar.php` | Editor |
| `public/assets/js/template-variables.js` | Seletor JS de variáveis |
| `app/Database/migrations/00059_create_message_template_tables.php` | Criação das tabelas |
| `app/Database/migrations/00060_seed_default_message_templates.php` | Templates padrão |

## Migrações

### Criar Tabelas
```bash
# 00059_create_message_template_tables.php
# Cria message_template_types e message_templates
php migrate.php
```

### Popular Templates pt_BR
```bash
# 00060_seed_default_message_templates.php
# Popula templates padrão (chave='0') em pt_BR para todos os tipos e canais
```

### Templates i18n
```bash
# 00063_seed_i18n_message_templates.php
# Adiciona templates padrão em outros idiomas (en_US, es_ES, pt_PT, it_IT)
```

### Unificar Tabelas
```bash
# 00138_unify_message_templates_tables.php
# Migrou dados de message_template_defaults para message_templates com chave='0'
# Removeu a tabela message_template_defaults
```
