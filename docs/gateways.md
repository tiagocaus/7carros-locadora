# Gateways de Pagamento

## Visao Geral

Sistema multi-gateway para processamento de pagamentos online com link publico de pagamento. Suporta 11 gateways (Brasil, Paraguai e Internacional) com arquitetura baseada em Interface + Factory Pattern.

## Gateways Disponiveis

| Gateway | Codigo | Pais | Metodos | SDK/Abordagem |
|---------|--------|------|---------|---------------|
| **Asaas** | `asaas` | BR | PIX, Boleto, Cartao | `codephix/asaas-sdk` |
| **Stripe** | `stripe` | INTL | Cartao, Digital Wallets | `stripe/stripe-php` |
| **Square** | `square` | INTL | Cartao, Digital Wallets | `square/square` |
| **Cora** | `cora` | BR | PIX, Boleto | REST com mTLS |
| **EfiPay** | `efipay` | BR | PIX, Boleto | `efipay/sdk-php-apis-efi` |
| **Inter** | `inter` | BR | PIX, Boleto | REST com mTLS |
| **Sicoob** | `sicoob` | BR | PIX, Boleto | REST com OAuth2 + mTLS |
| **Bradesco** | `bradesco` | BR | PIX, Boleto | REST com certificado .pfx |
| **Itau** | `itau` | BR | PIX, Boleto | REST com mTLS (BoleCode) |
| **Bancard** | `bancard` | PY | Cartao | REST (vPOS 2.0) |
| **Pagopar** | `pagopar` | PY | Cartao, Transferencia | REST |

---

## Arquitetura de Classes

```
app/Services/Gateways/
├── PaymentGatewayInterface.php  # Interface com metodos padronizados
├── AbstractPaymentGateway.php   # Classe base com helpers HTTP, logging, validacao
├── GatewayFactory.php           # Factory para instanciar gateways
├── AsaasGateway.php
├── StripeGateway.php
├── SquareGateway.php
├── CoraGateway.php
├── EfipayGateway.php
├── InterGateway.php
├── SicoobGateway.php
├── BradescoGateway.php
├── ItauGateway.php
├── BancardGateway.php
└── PagoparGateway.php
```

### PaymentGatewayInterface

Metodos principais:

| Metodo | Retorno | Descricao |
|--------|---------|-----------|
| `getCode()` | `string` | Codigo do gateway (ex: 'asaas') |
| `getName()` | `string` | Nome de exibicao |
| `getCountry()` | `string` | Pais: 'BR', 'PY', 'INTL' |
| `getSupportedMethods()` | `array` | ['pix', 'boleto', 'credit_card', 'debit_card'] |
| `getSupportedCurrencies()` | `array` | Codigos ISO 4217 (ex: ['BRL']) |
| `getConfigSchema()` | `array` | Schema dos campos de configuracao |
| `validateCredentials(array $credentials)` | `array` | {valid, message} |
| `createCharge(array $data)` | `array` | {success, external_id, status, payment_url, pix_code, barcode, ...} |
| `getChargeStatus(string $externalId)` | `array` | {success, status, paid_at} |
| `refund(string $externalId, ?float $amount)` | `array` | {success, refund_id} |
| `cancel(string $externalId)` | `array` | {success, message} |
| `validateWebhookSignature(array $payload, array $headers)` | `bool` | Valida autenticidade |
| `parseWebhookPayload(array $payload)` | `array` | {event, external_id, status, paid_at} |
| `tokenizeCard(array $cardData)` | `array` | {success, token, brand, last_digits} |
| `supportsTransparentCheckout()` | `bool` | Checkout inline |
| `supportsCardStorage()` | `bool` | Tokenizacao de cartao |
| `isSandbox()` | `bool` | Modo sandbox |

### AuthorizationHoldInterface

Interface para gateways que suportam pre-autorizacao (bloqueio no cartao).
Implementada por: `StripeGateway` (Square suportara no futuro).
Usada em: **Locacoes** (`LocacoesController`) e **Contratos** (`ContratosController`).

| Metodo | Retorno | Descricao |
|--------|---------|-----------|
| `supportsAuthorizationHold()` | `bool` | Se suporta holds |
| `createHold(array $data)` | `array` | {success, external_id, status, expires_at, client_secret} |
| `captureHold(string $externalId, ?float $amount)` | `array` | {success, status} - captura total ou parcial |
| `releaseHold(string $externalId)` | `array` | {success, status} - libera sem cobrar |
| `getHoldStatus(string $externalId)` | `array` | {success, status, amount, captured_amount} |

**Stripe**: Usa `PaymentIntent` com `capture_method='manual'`. Hold padrao 7 dias, extended ate 31 dias.

### AbstractPaymentGateway

Fornece:
- `httpRequest()` e `httpRequestWithCert()` - Helpers para chamadas HTTP/mTLS
- `logTransaction()` - Logging automatico em `financeiro_transacoes`
- `toCents()`, `fromCents()`, `formatAmount()` - Conversao de valores
- `generateTxId()` - Geracao de IDs de transacao
- `validateCPF()`, `validateCNPJ()` - Validacao de documentos
- `mapStatus()` e `getBaseUrl()` - Metodos abstratos para subclasses
- `supportsAuthorizationHold()` - Retorna `false` por padrao

### GatewayFactory

```php
use App\Services\Gateways\GatewayFactory;

// Criar instancia de gateway
$gateway = GatewayFactory::create('asaas', $credentials, $sandbox, $gatewayId);

// Listar gateways disponiveis
$disponiveis = GatewayFactory::getAvailableGateways();

// Filtrar por pais ou metodo
$brasileiros = GatewayFactory::getGatewaysByCountry('BR');
$comPix = GatewayFactory::getGatewaysByMethod('pix');

// Verificar se gateway existe
$existe = GatewayFactory::exists('stripe'); // true
```

---

## Banco de Dados

### Tabela `gateways_pagamento`

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | INT UNSIGNED PK | Identificador |
| chave | VARCHAR(45) | Chave do tenant |
| gateway_code | VARCHAR(50) | Codigo: asaas, stripe, etc. |
| nome | VARCHAR(100) | Nome de exibicao |
| credentials | TEXT | JSON criptografado AES-256-CBC |
| currencies | TEXT | JSON com moedas suportadas |
| ambiente | ENUM('sandbox','production') | Ambiente |
| status | ENUM('A','I') | Ativo/Inativo |
| pix_enabled | TINYINT(1) | PIX habilitado |
| boleto_enabled | TINYINT(1) | Boleto habilitado |
| credit_card_enabled | TINYINT(1) | Cartao credito habilitado |
| debit_card_enabled | TINYINT(1) | Cartao debito habilitado |
| webhook_url | VARCHAR(255) | URL gerada pelo sistema |
| webhook_secret | VARCHAR(255) | Secret para validacao |
| ordem | INT UNSIGNED | Ordem de exibicao |

**Criptografia**: Credenciais sao criptografadas com AES-256-CBC usando `hash('sha256', APP_KEY)`. IV de 16 bytes aleatorios eh prefixado ao ciphertext e tudo codificado em base64.

### Tabela `gateways_filiais`

Relacionamento N:N entre gateways e filiais.

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | INT UNSIGNED PK | Identificador |
| id_gateway | INT UNSIGNED FK | FK para gateways_pagamento |
| id_matriz_filial | INT UNSIGNED FK | FK para matrizes_filiais |
| chave | VARCHAR(45) | Chave do tenant |

### Tabela `pagamentos_links`

Links publicos de pagamento.

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | INT PK | Identificador |
| chave | VARCHAR(45) | Chave do tenant |
| codigo | VARCHAR(32) UNIQUE | Codigo publico do link (hex) |
| id_financeiro | INT FK | FK para financeiro |
| id_cliente | INT FK NULL | FK para clientes |
| valor | DECIMAL(10,2) | Valor da cobranca |
| descricao | TEXT | Descricao |
| expires_at | DATETIME | Expiracao |
| status | ENUM | pending, paid, expired, cancelled |
| id_transacao_paga | INT NULL | FK para transacao que pagou |
| ip_pagamento | VARCHAR(45) | IP do pagador |
| user_agent_pagamento | TEXT | User-agent do pagador |

### Tabela `formas_pagamento_gateways`

Vinculo entre formas de pagamento e gateways. Documentado em [formas-pagamento.md](./formas-pagamento.md).

### Colunas adicionadas em `financeiro_transacoes` (migration 00202)

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id_gateway | INT UNSIGNED FK | FK para gateways_pagamento |
| payment_method | VARCHAR(50) | pix, boleto, credit_card, debit_card |
| payment_url | TEXT | URL de pagamento do gateway |
| pix_code | TEXT | Codigo PIX copia e cola |
| barcode | VARCHAR(255) | Codigo de barras boleto |
| expires_at | DATETIME | Expiracao da cobranca |
| paid_at | DATETIME | Data do pagamento |
| refunded_at | DATETIME | Data do reembolso |
| webhook_received_at | DATETIME | Data do ultimo webhook |

---

## Rotas

### Publicas (sem autenticacao)

```
GET  /pagar/{codigo}                                   # Pagina publica de pagamento
POST /pagar/{codigo}/processar                         # Processa pagamento
GET  /pagar/{codigo}/status                            # Consulta status
GET  /pagar/{codigo}/gateway/{gatewayId}/capabilities  # Capacidades do gateway
GET  /pagar/{codigo}/cartoes                           # Lista cartoes salvos
POST /pagar/{codigo}/tokenizar                         # Tokeniza cartao
POST /pagar/{codigo}/salvar-cartao                     # Salva cartao tokenizado
```

### Webhooks (sem autenticacao, sem CSRF)

```
GET  /webhook/{gateway_code}    # Diagnostico para abertura no navegador
POST /webhook/{gateway_code}    # Recebe notificacoes de cada gateway
```

Rotas individuais por gateway: `/webhook/asaas`, `/webhook/stripe`, `/webhook/square`, `/webhook/cora`, `/webhook/efipay`, `/webhook/inter`, `/webhook/sicoob`, `/webhook/bradesco`, `/webhook/itau`, `/webhook/bancard`, `/webhook/pagopar`.

No controller, cada rota individual `POST` deve ter um wrapper (`webhookAsaas`, `webhookStripe`, etc.) chamando o handler generico `PagamentoPublicoController::webhook($request, $gatewayCode)`. Isso evita erro 500 por metodo inexistente e centraliza idempotencia, validacao de assinatura e atualizacao de transacao.

As rotas `GET` existem apenas para diagnostico quando a URL e aberta no navegador. Elas devem retornar uma mensagem informando que o endpoint esta ativo e que eventos reais precisam usar `POST`; nao devem processar payload, validar assinatura, atualizar transacoes ou acessar dados de tenant.

#### Asaas

O Asaas envia eventos de cobranca por `POST` em JSON, com `event` no topo e os dados da cobranca em `payment`. O processamento deve usar `payment.id` como identificador externo da cobranca e `payment.externalReference` como fallback para reconciliar links publicos criados pelo sistema (`link_{id}`).

O endpoint deve responder `200` rapidamente para payloads validos recebidos do Asaas, mesmo quando o evento for apenas informativo ou quando a transacao local ainda nao puder ser reconciliada. Erros `400` devem ser evitados nesses casos para nao interromper a fila de webhooks no Asaas. Token/assinatura invalida continua retornando erro de autenticacao quando houver token configurado.

### Protegidas (requer autenticacao)

```
GET  /pages/gateways-pagamento              # View de listagem
GET  /pages/gateways-pagamento/adicionar    # View de formulario
GET  /api/gateways-pagamento                # API: listar paginado
GET  /api/gateways-pagamento/disponiveis    # API: gateways disponiveis
GET  /api/gateways-pagamento/{id}           # API: detalhes
POST /gateways-pagamento/salvar             # Criar gateway
POST /gateways-pagamento/{id}/atualizar     # Atualizar gateway
POST /gateways-pagamento/{id}/excluir       # Excluir gateway
POST /gateways-pagamento/{id}/status        # Toggle ativo/inativo
POST /api/gateways-pagamento/{id}/testar    # Testar conexao
```

---

## Fluxo de Pagamento Publico

```
1. Cliente acessa GET /pagar/{codigo}
2. Sistema valida link (existe, nao expirado, nao pago)
3. Busca gateways vinculados via formas_pagamento_gateways
4. Filtra por moeda do tenant e metodos habilitados
5. Exibe opcoes: PIX, Boleto, Cartao (separados por gateway)
6. Cliente escolhe metodo e gateway
7. POST /pagar/{codigo}/processar
8. Sistema cria cobranca via gateway->createCharge()
9. Retorna dados (QR Code PIX, codigo de barras, URL de pagamento)
10. Cliente paga
11. Gateway envia POST /webhook/{gateway_code}
12. Sistema valida assinatura e idempotencia
13. Atualiza transacao e marca link como pago
14. Atualiza financeiro (pago='S') e dispara comissao investidor
```

### Vencimento da cobranca no gateway

A data enviada ao gateway deve respeitar o vencimento real da fatura:

- Se `financeiro.data_venci` for hoje ou uma data futura, enviar essa mesma data como `due_date`
- Se `financeiro.data_venci` estiver vencida, enviar a data de hoje
- Se a data estiver ausente ou invalida, usar a data de hoje

Gateways nao devem aplicar fallback proprio como `+3 dias` ou `+1 dia`; a normalizacao deve ser feita pelo fluxo de pagamento publico e pelo helper comum dos gateways.

### Substituicao de cobrancas apos alteracao financeira

O link publico (`/pagar/{codigo}`) deve ser estavel e carregar sempre os dados
atuais do financeiro. Boletos e demais cobrancas externas nao devem ser editados
em aberto para refletir novo valor do financeiro. O comportamento padrao do
sistema e manter o mesmo link publico, cancelar a cobranca externa antiga e criar
uma nova cobranca quando o cliente escolher pagar.

Quando uma receita pendente muda valor, vencimento, cliente, forma de pagamento,
juros, multa, desconto ou soma dos itens:

1. `PagamentoLinkSyncService` localiza o link publico reaproveitavel e transacoes `charge` abertas.
2. O link publico e sincronizado com valor, cliente, descricao e vencimento atuais.
3. Para cada transacao com `external_id`, o sistema consulta o status no gateway.
4. Se a cobranca estiver aberta, chama `PaymentGatewayInterface::cancel($externalId)`.
5. Se o cancelamento for confirmado, a transacao local fica `cancelled`; o link publico continua `pending`.
6. O proximo processamento pelo mesmo `/pagar/{codigo}` cria uma nova cobranca externa com o valor atual.

Se o gateway retornar `paid`, `received`, `confirmed` ou equivalente, o sistema
marca a transacao local como paga e nao emite outro link automaticamente. Se o
gateway estiver indisponivel ou recusar o cancelamento de uma cobranca ainda
pagavel, a operacao que alteraria a fatura deve ser bloqueada para evitar que o
cliente pague boleto antigo depois de o valor ter mudado.

Para boleto, "atualizar" significa baixar/cancelar o boleto antigo no gateway e
emitir nova cobranca. Nao assuma que o gateway permite alterar valor ou vencimento
do mesmo boleto ja gerado.

---

## Seguranca

- **Credenciais**: Criptografadas com AES-256-CBC usando `APP_KEY`
- **Webhooks**: Validacao de assinatura especifica por gateway (HMAC, token, mTLS)
- **Idempotencia**: Verificacao se webhook ja foi processado (`webhookJaProcessado`)
- **Mascaramento**: Credenciais sao mascaradas na API de leitura (nunca expostas no frontend)
- **mTLS**: Gateways bancarios (Cora, Inter, Bradesco, Itau) usam certificados mutuos

---

## Models

### GatewayPagamento

```php
$model = new GatewayPagamento();

// Listar gateways ativos
$ativos = $model->listarAtivos();

// Buscar com credenciais descriptografadas (uso interno)
$gateway = $model->buscarPorIdComCredenciais($id);

// Listar para pagina publica (sem credenciais)
$gateways = $model->listarParaPagamentoPublico($chave);

// CRUD
$id = $model->criar($dados);
$model->atualizar($id, $dados);
$model->excluir($id);

// Filiais
$filiais = $model->buscarFiliais($gatewayId);
$model->sincronizarFiliais($gatewayId, [1, 2, 3], $chave);
```

### PagamentoLink

```php
$model = new PagamentoLink();

// Criar link
$id = $model->criar([
    'chave' => $chave,
    'id_financeiro' => $idFinanceiro,
    'id_cliente' => $idCliente,
    'valor' => 150.00,
    'descricao' => 'Parcela 1/3',
    'expires_at' => '2025-12-31 23:59:59'
]);

// Buscar por codigo (pagina publica - inclui dados do financeiro, cliente, empresa)
$link = $model->buscarPorCodigo($codigo);

// Marcar como pago
$model->marcarComoPago($id, $idTransacao, $ip, $userAgent);

// URL publica
$url = $model->getUrl($codigo); // {APP_URL}/pagar/{codigo}

// Expirar links vencidos (usado em CRON)
$model->atualizarExpirados();
```

---

## Arquivos do Modulo

| Arquivo | Descricao |
|---------|-----------|
| `app/Services/Gateways/PaymentGatewayInterface.php` | Interface padronizada |
| `app/Services/Gateways/AbstractPaymentGateway.php` | Classe base com helpers |
| `app/Services/Gateways/GatewayFactory.php` | Factory para instanciar gateways |
| `app/Services/Gateways/*Gateway.php` | 10 implementacoes de gateway |
| `app/Models/GatewayPagamento.php` | Model de gateways |
| `app/Models/PagamentoLink.php` | Model de links publicos |
| `app/Controllers/GatewaysPagamentoController.php` | CRUD de gateways (admin) |
| `app/Controllers/PagamentoPublicoController.php` | Pagina publica + webhooks |
| `app/Views/pages/gateways-pagamento/index.php` | Listagem de gateways |
| `app/Views/pages/gateways-pagamento/adicionar.php` | Formulario de gateway |
| `app/Views/public/pagar/index.php` | Pagina publica de pagamento |
| `app/Views/public/pagar/sucesso.php` | Pagina de sucesso |
| `app/Views/public/pagar/erro.php` | Pagina de erro |
| `app/Database/migrations/00200_create_gateways_pagamento.php` | Cria tabela gateways |
| `app/Database/migrations/00201_create_pagamentos_links.php` | Cria tabela links |
| `app/Database/migrations/00202_expand_financeiro_transacoes.php` | Expande transacoes |
| `app/Database/migrations/00203_migrate_formas_gateway_data.php` | Migra dados legado |
| `app/Database/migrations/00204_create_gateways_filiais.php` | Cria tabela filiais |
| `app/Database/migrations/00210_create_formas_pagamento_gateways.php` | Vinculo formas x gateways |
