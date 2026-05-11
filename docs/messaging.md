# Sistema de Mensageria com RabbitMQ

Sistema de fila de mensagens para processar envios de email, SMS e WhatsApp em segundo plano.

## Visao Geral

O sistema utiliza **RabbitMQ** como broker de mensagens. Quando uma mensagem precisa ser enviada, ela e adicionada a fila e processada em segundo plano por um worker CRON.

### Caracteristicas

- Processamento assincrono via RabbitMQ
- Rastreamento completo no banco de dados (tabela `messages_queue`)
- Retry automatico com limite configuravel
- Multi-tenancy: resolve credenciais do tenant automaticamente
- Credenciais por filial (SMTP, WhatsApp, SMS)
- Templates com i18n (idioma do cliente)

## Arquitetura

### Fluxo de Resolucao de Credenciais

```
PUBLICACAO (contexto web/cron)          CONSUMO (ProcessMessageQueueJob)
──────────────────────────────          ────────────────────────────────

queue_template_message()                RabbitMQ → ProcessMessageQueueJob
queue_message()                            │
queue_system_message()                     ├─ Define $_SESSION['chave']
     │                                     │
     │ id_matriz_filial injetado           ▼
     │ automaticamente da sessao      EmailService.send()
     ▼                                  → Smtp::buscarValidadaPorFilial()
MessageQueueService                     → Tenant com SMTP? Usa do tenant
  valida canal tenant                   → Sem SMTP? Fallback para ENV
  salva BD + publica RabbitMQ

                                       WhatsAppService.send()
                                        → Whatsapp::buscarConectadaPorFilial()
                                        → Tenant com instancia? Usa do tenant
                                        → Sem instancia? FALHA (nao envia)

                                       SmsService.send()
                                        → Sms::buscarValidadaPorFilial()
                                        → Tenant com SMS? Usa do tenant
                                        → Sem SMS? FALHA (nao envia)
```

### Comportamento por Canal

| Canal | Tenant TEM config | Tenant SEM config |
|-------|------------------|-------------------|
| **Email** | Usa SMTP do tenant | Fallback para ENV (7Carros) |
| **WhatsApp** | Usa instancia do tenant | Falha (nao envia) |
| **SMS** | Usa provedor do tenant | Falha (nao envia) |

### Validacao Antes da Fila

`MessageQueueService::publish()` valida canais de tenant antes de registrar a mensagem como `pending`:

- **WhatsApp:** exige `id_matriz_filial` e instancia conectada via `Whatsapp::buscarConectadaPorFilial()`. Sem instancia conectada, a chamada falha imediatamente e nao cria item pendente na fila.
- **SMS:** exige `id_matriz_filial` e conexao validada via `Sms::buscarValidadaPorFilial()`. Sem conexao validada, a chamada falha imediatamente e nao cria item pendente na fila.
- **Email:** nao bloqueia a publicacao, porque pode usar SMTP da filial ou fallback do ENV.

Excecao: `queue_system_message('whatsapp', ...)` usa credenciais do sistema e nao passa pela validacao de instancia da filial.

## Configuracao

### Variaveis de Ambiente

```env
# RabbitMQ
RABBITMQ_HOST=rabbitmq.hostcia.net
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_QUEUE_NAME=messages_queue

# Processamento da Fila
QUEUE_MAX_MESSAGES_PER_RUN=50
QUEUE_MAX_ATTEMPTS=3
QUEUE_CONSUME_TIMEOUT=30

# SMTP do Sistema (fallback quando tenant nao tem SMTP)
MAIL_HOST=mail.7carros.com
MAIL_PORT=587
MAIL_USERNAME=notification@7carros.com
MAIL_PASSWORD=***
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=notification@7carros.com
MAIL_FROM_NAME="7Carros Locadora"

# WhatsApp do Sistema (usado por queue_system_message)
WHATSAPP_API_URL=https://provedor.example.com
WHATSAPP_API_ADMIN_TOKEN=***
WHATSAPP_API_INSTANCE_TOKEN=***   # token da instancia system 7Carros

# Desenvolvimento
DEV_ALLOWED_NOTIFICATION_TENANT=1111111111111
```

### CRON

```bash
# Executar a cada 1 minuto
* * * * * /usr/bin/php /path/to/project/cron.php >> /path/to/project/storage/logs/cron/execution.log 2>&1
```

## Uso

### Qual Metodo Usar?

| Cenario | Metodo | Exemplo |
|---------|--------|---------|
| Tenant → Cliente (com template) | `queue_template_message()` | Confirmacao de locacao, cobranca, boas-vindas |
| Tenant → Cliente/Interno (sem template) | `queue_message()` | Alerta de manutencao, notificacao interna |
| 7Carros → Tenant (sistema) | `queue_system_message()` | Reserva do site, alerta de seguranca |

---

### 1. `queue_template_message()` — Tenant → Cliente

Para comunicacoes com clientes usando templates traduzidos. O idioma e determinado automaticamente:
1. `cliente.preferred_locale` → idioma do cliente
2. `empresa.locale` → idioma da filial
3. `pt_BR` → fallback

O `id_matriz_filial` e extraido automaticamente de `$context['empresa']['id']`.

```php
// Buscar dados
$cliente = $qb->getRow('clientes', ['*'], 'id = ?', [$clienteId]);
$empresa = $qb->getRow('matrizes_filiais', ['*'], 'id = ?', [$filialId]);
$locacao = $qb->getRow('locacoes', ['*'], 'id = ?', [$locacaoId]);
$veiculo = $qb->getRow('veiculos', ['*'], 'id = ?', [$veiculoId]);

// Enviar email (template traduzido para idioma do cliente)
queue_template_message('rental_confirmation', 'email', [
    'cliente' => $cliente,
    'empresa' => $empresa,
    'locacao' => $locacao,
    'veiculo' => $veiculo,
]);

// Enviar WhatsApp (mesmo template, versao plain text)
queue_template_message('rental_confirmation', 'whatsapp', [
    'cliente' => $cliente,
    'empresa' => $empresa,
    'locacao' => $locacao,
    'veiculo' => $veiculo,
]);

// Enviar SMS
queue_template_message('payment_reminder', 'sms', [
    'cliente' => $cliente,
    'empresa' => $empresa,
    'fatura' => $fatura,
]);
```

**Templates disponiveis:** Gerencie em **Configuracoes > Templates de Mensagem**.

---

### 2. `queue_message()` — Tenant → Cliente/Interno (sem template)

Para notificacoes internas ou mensagens sem template. O `id_matriz_filial` e injetado automaticamente da `$_SESSION['id_matriz_filial']` quando nao fornecido.

```php
// Email para funcionario (id_matriz_filial injetado da sessao)
queue_message('email', [
    'to' => 'funcionario@locadora.com',
    'to_name' => 'Joao',
    'subject' => 'Alerta de Manutencao',
    'body' => '<p>O veiculo ABC-1234 precisa de revisao.</p>',
]);

// WhatsApp com id_matriz_filial explicito (ex: em CRON sem sessao)
queue_message('whatsapp', [
    'to' => '5511999999999',
    'message' => 'Veiculo ABC-1234 precisa de revisao urgente.',
    'id_matriz_filial' => $idFilial,
]);

// SMS
queue_message('sms', [
    'to' => '5511999999999',
    'message' => 'Codigo de verificacao: 123456',
    'id_matriz_filial' => $idFilial,
]);

// Email com anexo
queue_message('email', [
    'to' => 'cliente@email.com',
    'subject' => 'Seu contrato',
    'body' => '<p>Segue em anexo.</p>',
    'attachments' => ['/storage/uploads/contrato_123.pdf'],
]);
```

---

### 3. `queue_system_message()` — 7Carros → Tenant

Para mensagens da plataforma 7Carros para tenants. Usa credenciais do ENV.

- **Email:** Usa SMTP do ENV + layout `layout-system.php` (branding 7Carros)
- **WhatsApp:** Usa `WHATSAPP_API_INSTANCE_TOKEN` do ENV + prefixo `*[7Carros]*`

```php
// Notificar tenant sobre nova reserva do site
queue_system_message('email', [
    'to' => $tenant['email'],
    'to_name' => $tenant['nome_fantasia'],
    'subject' => 'Nova Reserva Recebida',
    'body' => '<p>Uma nova reserva foi feita no seu site.</p>
               <p>Cliente: Joao Silva</p>
               <p>Veiculo: Honda Civic 2024</p>',
]);

queue_system_message('whatsapp', [
    'to' => $tenant['telefone'],
    'message' => "Nova reserva recebida!\n\nCliente: Joao Silva\nVeiculo: Honda Civic 2024",
]);
// Resultado WhatsApp: "*[7Carros]*\nNova reserva recebida!\n\nCliente: Joao Silva..."

// Alerta de seguranca
queue_system_message('email', [
    'to' => 'admin@7carros.com',
    'subject' => '[ALERTA] Tentativa de acesso cross-tenant',
    'body' => $alertBody,
]);
```

## Campos do Payload

### Email

| Campo | Obrigatorio | Descricao |
|-------|------------|-----------|
| `to` | Sim | Email do destinatario |
| `subject` | Sim | Assunto |
| `body` | Sim | Corpo HTML |
| `to_name` | Nao | Nome do destinatario |
| `body_text` | Nao | Versao texto plano |
| `cc` | Nao | Array de emails em copia |
| `bcc` | Nao | Array de emails em copia oculta |
| `reply_to` | Nao | Email para resposta |
| `reply_to_name` | Nao | Nome para resposta |
| `attachments` | Nao | Array de caminhos de arquivos |
| `id_matriz_filial` | Auto | Injetado da sessao se ausente |

### WhatsApp

| Campo | Obrigatorio | Descricao |
|-------|------------|-----------|
| `to` | Sim | Numero de telefone |
| `message` | Sim* | Mensagem de texto |
| `media_url` | Sim* | URL de midia (alternativa a message) |
| `caption` | Nao | Legenda para midia |
| `id_matriz_filial` | Auto | Injetado da sessao se ausente |

### SMS

| Campo | Obrigatorio | Descricao |
|-------|------------|-----------|
| `to` | Sim | Numero de telefone |
| `message` | Sim | Mensagem de texto |
| `id_matriz_filial` | Sim | ID da filial (obrigatorio para SMS) |

### Campos Especiais

| Campo | Descricao |
|-------|-----------|
| `_system_message` | Quando `true`, usa credenciais do ENV. Adicionado automaticamente por `queue_system_message()` |
| `id_matriz_filial` | ID da filial para resolver SMTP/WhatsApp/SMS do tenant |

## Rastreamento

Tabela `messages_queue`:

| Campo | Descricao |
|-------|-----------|
| `id` | ID unico |
| `chave` | Tenant |
| `type` | email, sms, whatsapp |
| `status` | pending, processing, sent, failed, skipped |
| `payload` | JSON com dados da mensagem |
| `attempts` | Numero de tentativas |
| `error_message` | Ultimo erro |
| `batch_id` | ID de lote (opcional) |
| `created_at`, `updated_at`, `processed_at` | Timestamps |

### Retry Automatico

- **Tentativas maximas:** `QUEUE_MAX_ATTEMPTS` (padrao: 3)
- Se falhar e `attempts < max`: volta para `pending`
- Se `attempts >= max`: marca como `failed`

## Boas Praticas

### Sempre Use a Fila

```php
// ERRADO - bloqueia a aplicacao
$emailService = new EmailService();
$emailService->send([...]);

// CORRETO - assincrono
queue_message('email', [...]);
```

### id_matriz_filial

Em contexto web, `id_matriz_filial` e injetado automaticamente da `$_SESSION['id_matriz_filial']`.

Em contexto CRON (sem sessao), passe explicitamente:

```php
queue_message('email', [
    'to' => $email,
    'subject' => '...',
    'body' => '...',
    'id_matriz_filial' => $idFilial, // Obrigatorio em CRON
]);
```

### Mensagens de Sistema vs Tenant

```php
// 7Carros notificando um tenant
queue_system_message('email', [...]);   // Usa ENV, layout 7Carros
queue_system_message('whatsapp', [...]); // Usa ENV, prefixo *[7Carros]*

// Tenant enviando para seu cliente
queue_template_message('welcome', 'email', [...]); // Usa SMTP do tenant
queue_message('whatsapp', [...]);                    // Usa instancia do tenant
```

## Referencia de API

```php
// Mensagem com template traduzido (Tenant → Cliente)
function queue_template_message(
    string $templateSlug,
    string $channel,     // 'email', 'sms', 'whatsapp'
    array $context,      // ['cliente' => [...], 'empresa' => [...], ...]
    ?string $chave = null,
    ?string $batchId = null
): int;

// Mensagem direta (Tenant → Cliente/Interno)
function queue_message(
    string $type,        // 'email', 'sms', 'whatsapp'
    array $payload,      // Dados da mensagem
    ?string $chave = null,
    ?string $batchId = null
): int;

// Mensagem do sistema (7Carros → Tenant)
function queue_system_message(
    string $type,        // 'email', 'whatsapp'
    array $payload,      // Dados da mensagem
    ?string $chave = null
): int;
```

## Arquivos Principais

| Arquivo | Responsabilidade |
|---------|-----------------|
| `app/Helpers/helpers.php` | `queue_message()`, `queue_template_message()`, `queue_system_message()` |
| `app/Services/MessageQueueService.php` | Publica no RabbitMQ + salva no BD |
| `app/Services/EmailService.php` | Resolve SMTP (tenant → fallback ENV) e envia |
| `app/Services/WhatsAppService.php` | Resolve instancia (tenant, sem fallback) e envia |
| `app/Services/SmsService.php` | Resolve provedor por filial e envia |
| `app/Services/MessageTemplateService.php` | Renderiza templates com i18n |
| `app/Crons/Jobs/ProcessMessageQueueJob.php` | Worker que consome a fila |
| `app/Views/emails/layout.php` | Layout de email do tenant |
| `app/Views/emails/layout-system.php` | Layout de email da 7Carros |

## Documentacao Relacionada

- **[Templates](./templates.md)** - Sistema de templates de mensagem
- **[i18n](./i18n.md)** - Internacionalizacao
- **[Integracoes](./integrations.md)** - APIs externas (WhatsApp, ClickSend)
- **[Cron](./cron.md)** - Tarefas agendadas
- **[Multi-tenancy](./multi-tenancy.md)** - Isolamento de tenants

---

**Ultima atualizacao**: 2026-02-06
