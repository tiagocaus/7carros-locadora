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

- **Bloqueio mestre da empresa/filial:** todo envio de tenant exige
  `id_matriz_filial` e consulta `matrizes_filiais.notificacao_email`,
  `notificacao_sms` ou `notificacao_whatsapp`. Se o canal estiver em `N`, a
  publicacao falha antes da geracao de anexos e antes de chegar ao RabbitMQ.
- **WhatsApp:** exige `id_matriz_filial` e instancia conectada via `Whatsapp::buscarConectadaPorFilial()`. Sem instancia conectada, a chamada falha imediatamente e nao cria item pendente na fila.
- **SMS:** exige `id_matriz_filial` e conexao validada via `Sms::buscarValidadaPorFilial()`. Sem conexao validada, a chamada falha imediatamente e nao cria item pendente na fila.
- **Email:** nao bloqueia a publicacao, porque pode usar SMTP da filial ou fallback do ENV.

Bloqueio mestre e ausencia de conexao valida lancam
`NotificationChannelUnavailableException`. Essa excecao representa uma
notificacao opcional ignorada por configuracao, nao falha de banco, RabbitMQ ou
provedor. Fluxos que tentam automaticamente todos os canais devem captura-la
sem registrar erro no Apache; excecoes inesperadas continuam sendo registradas.

Excecao: `queue_system_message('whatsapp', ...)` usa credenciais do sistema e nao passa pela validacao de instancia da filial.

O worker repete a validacao do bloqueio mestre imediatamente antes do provedor.
Assim, uma mensagem que estava na fila quando o canal foi desativado recebe
status `skipped`, sem retry e sem envio.

### Hierarquia de Autorizacao

Uma configuracao de outra filial do mesmo tenant nunca libera o envio. A
mensagem usa a matriz/filial vinculada ao contrato, locacao, cobranca, reserva,
NFS-e ou outro registro de origem:

1. o canal precisa estar ativo na matriz/filial;
2. para clientes, o contato tambem precisa autorizar o canal;
3. WhatsApp e SMS exigem, adicionalmente, conexao valida da mesma filial.

Para clientes:

- email usa todos os enderecos com `recebe_email = 'S'`;
- WhatsApp usa todos os telefones com `whatsapp = 'S'`;
- SMS usa todos os telefones com `sms = 'S'`;
- destinatarios repetidos sao deduplicados;
- cada copia e revalidada novamente pelo worker.

O controle `matrizes_filiais.notificacao_cobranca_vencida` e especifico do
CRON financeiro: em `N`, impede apenas o `overdue_notice` automatico da filial.
Ele nao desativa lembretes pre-vencimento, cobrancas manuais nem outros tipos
de mensagem.

Excecoes permitidas sao restritas a recuperacao de senha de cliente ou
funcionario solicitada pelo usuario, testes tecnicos manuais e mensagens
globais da plataforma marcadas explicitamente com bypass. O bypass de senha e
aceito somente para email; ele nao libera SMS ou WhatsApp. Mensagens comerciais
enviadas com credenciais da 7Carros continuam respeitando a empresa/filial.

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
| 7Carros → Tenant (sistema) | `queue_system_message()` | WhatsApp de reserva do site, alerta de seguranca |

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

Para notificacoes financeiras, inclua `fatura.parcela` e
`fatura.total_parcelas` no contexto. Se `parcela > 0`, a renderizacao garante a
identificacao localizada no corpo de email, WhatsApp e SMS, inclusive para
templates customizados que ainda nao possuem essas variaveis. `1 de 1` e
exibido; `parcela = 0` e omitido. O assunto do email permanece inalterado.

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

Nas reservas do site, os emails internos tambem usam `queue_message()`. Os
destinatarios sao funcionarios ativos da filial de retirada cujas roles possuem
`notificacoes.novas_reservas`; o endereco vem de `funcionarios.email`, nao do
email geral da empresa. Veja [Website — Notificacoes por email de uma nova
reserva do site](website.md#notificacoes-por-email-de-uma-nova-reserva-do-site).

---

### 3. `queue_system_message()` — 7Carros → Tenant

Para mensagens da plataforma 7Carros para tenants. Usa credenciais do ENV.

- **Email:** Usa SMTP do ENV + layout `layout-system.php` (branding 7Carros)
- **WhatsApp:** Usa `WHATSAPP_API_INSTANCE_TOKEN` do ENV + prefixo `*[7Carros]*`

```php
// Notificar o celular da empresa matriz sobre nova reserva do site
queue_system_message('whatsapp', [
    'to' => $tenant['telefone'],
    'message' => "Nova reserva recebida!\n\nCliente: Joao Silva\nVeiculo: Honda Civic 2024",
    'id_matriz_filial' => $tenant['id'],
], $tenant['chave']);
// Resultado WhatsApp: "*[7Carros]*\nNova reserva recebida!\n\nCliente: Joao Silva..."

// Alerta de seguranca
queue_system_message('email', [
    'to' => 'admin@7carros.com',
    'subject' => '[ALERTA] Tentativa de acesso cross-tenant',
    'body' => $alertBody,
], null, true);
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
queue_system_message('email', [...], null, true); // Alerta global com bypass explicito
queue_system_message('whatsapp', [
    ...,
    'id_matriz_filial' => $filialId,
], $chave); // Mensagem comercial: usa ENV, mas respeita o bloqueio da filial

// Tenant enviando para seu cliente
queue_template_message('welcome', 'email', [...]); // Usa SMTP do tenant
queue_message('whatsapp', [...]);                    // Usa instancia do tenant
```

### Preferencia de Email do Cliente

Cada endereco de cliente possui a flag `contatos_emails.recebe_email` (`S`/`N`).
Para email destinado a cliente, nunca publique diretamente com
`queue_message('email', ...)`: use `queue_client_email()` ou
`queue_template_message()`, informando `cliente.id` no contexto.

- Todos os enderecos com `recebe_email = 'S'` recebem uma copia individual.
- A estrela de email principal nao altera a preferencia de envio.
- Se nenhum endereco estiver autorizado, o envio automatico e ignorado; fluxos
  manuais devem informar isso ao usuario antes de gerar anexos.
- O worker revalida a preferencia antes do SMTP. Se o endereco for desmarcado
  enquanto estiver na fila, a mensagem recebe status `skipped`.
- A unica excecao e o template `cliente_nova_senha`, solicitado pelo proprio
  cliente. Ele usa o endereco informado na recuperacao mesmo que esteja
  desmarcado, para nao bloquear o acesso a conta.

```php
$ids = queue_client_email($clienteId, [
    'to_name' => $cliente['nome_rsocial'],
    'subject' => 'Documento disponivel',
    'body' => '<p>Segue o documento solicitado.</p>',
    'id_matriz_filial' => $filialId,
], $chave);
```

### Preferencia de WhatsApp e SMS do Cliente

Para destinatarios clientes, use `queue_client_phone()` em vez de publicar
WhatsApp ou SMS diretamente. O helper cria uma mensagem para cada telefone
autorizado e inclui os metadados usados na revalidacao do worker:

```php
$ids = queue_client_phone('whatsapp', $clienteId, [
    'message' => 'Seu contrato esta disponivel.',
    'id_matriz_filial' => $filialId,
], $chave);
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

// Email direto para todos os enderecos autorizados do cliente
function queue_client_email(
    int $clienteId,
    array $payload,
    ?string $chave = null,
    ?string $batchId = null,
    bool $ignorarPreferencia = false // uso interno: recuperacao de senha
): array;

// WhatsApp/SMS para todos os telefones autorizados do cliente
function queue_client_phone(
    string $channel,     // 'whatsapp' ou 'sms'
    int $clienteId,
    array $payload,
    ?string $chave = null,
    ?string $batchId = null
): array;

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
    ?string $chave = null,
    bool $ignorarBloqueioEmpresa = false // apenas mensagens globais da plataforma
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

**Ultima atualizacao**: 2026-07-24
