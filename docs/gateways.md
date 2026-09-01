# Gateways de Pagamento

## Visao Geral

Sistema multi-gateway para processamento de pagamentos online com link publico de pagamento. Suporta 12 gateways (Brasil, Paraguai e Internacional) com arquitetura baseada em Interface + Factory Pattern.

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
| **Bradesco** | `bradesco` | BR | PIX, Boleto | APIs Pix e Cobranca com OAuth2 + mTLS |
| **Itau** | `itau` | BR | PIX, Boleto | REST com mTLS (BoleCode) |
| **Banco Santander** | `santander` | BR | PIX, Boleto/Boleto SX | REST com OAuth2 + mTLS |
| **Bancard** | `bancard` | PY | Cartao | REST (vPOS 2.0) |
| **Pagopar** | `pagopar` | PY | Cartao, Transferencia | REST |

---

## Arquitetura de Classes

```
app/Services/Gateways/
├── PaymentGatewayInterface.php  # Interface com metodos padronizados
├── AbstractPaymentGateway.php   # Classe base com helpers HTTP, logging, validacao
├── GatewayFactory.php           # Factory para instanciar gateways
├── GatewayCertificateService.php # Upload/validacao/extracao de certificados A1
├── AsaasGateway.php
├── StripeGateway.php
├── SquareGateway.php
├── CoraGateway.php
├── EfipayGateway.php
├── InterGateway.php
├── SicoobGateway.php
├── BradescoGateway.php
├── ItauGateway.php
├── SantanderGateway.php
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
| `getCertificateConfig()` | `array|null` | Informa se o gateway aceita/exige certificado e formatos permitidos |
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

A liberacao de holds e centralizada em
`AuthorizationHoldReleaseService`. Exclusoes de locacoes/reservas e contratos
usam politica estrita: se o gateway nao confirmar que o limite deixou de estar
retido, o registro principal nao e excluido. O encerramento de tenant pelo
WHMCS usa o mesmo servico em modo de melhor esforco.

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

### Certificados Bancarios

Os gateways Cora, EfiPay, Inter, Sicoob, Bradesco, Itau e Santander usam a secao generica de certificado. O upload possui dois modos explicitos:

- **PFX/P12 completo**: um unico arquivo que contem o certificado publico e a chave privada. A senha e opcional, pois PKCS#12 tambem pode ser emitido com senha vazia;
- **Certificado publico + chave privada**: certificado `.pem`/`.crt`/`.cer` acompanhado da chave correspondente `.pem`/`.key`. A passphrase e informada somente quando a chave estiver protegida.

O formulario possui uma unica acao de persistencia: **Salvar**. Quando novos arquivos sao selecionados, o sistema salva os dados do gateway e envia o certificado na mesma operacao iniciada pelo usuario. O modo escolhido fica registrado em `credentials` como `certificado_modo` (`pkcs12` ou `pem_pair`); configuracoes anteriores sem esse metadado continuam compativeis pela inferencia de `certificado_formato`.

Nao existe "certificado privado" nesse fluxo: o segundo arquivo e a **chave privada**. Um certificado publico isolado nao e suficiente para autenticacao mTLS. O sistema valida validade e correspondencia da chave, preserva a cadeia de certificados intermediarios, armazena os artefatos com permissao `0600` em `storage/certificates/gateways` e guarda a senha de PFX/P12 criptografada em `credentials`.

| Gateway | Regra do certificado |
|---------|-----------------------|
| Cora | Use o certificado e a private key fornecidos pela Cora para o mesmo ambiente; nao use A1 fiscal generico |
| EfiPay | Obrigatorio para Pix; uma configuracao somente da API Cobrancas/Boleto nao exige certificado |
| Inter | Use o CRT e a KEY da mesma integracao ou um PFX gerado a partir desse par |
| Sicoob | O certificado publico cadastrado no banco deve pertencer ao mesmo A1/PFX ou a mesma chave privada usada pelo sistema |
| Bradesco | O banco recebe somente a parte publica; o sistema conserva o par completo para mTLS |
| Itau | Producao usa o certificado dinamico e a chave criada com o CSR; sandbox nao exige mTLS |
| Santander | Use o certificado de cliente com a cadeia completa e a chave correspondente; compartilhe com o banco somente a parte publica |

Nao cadastre caminhos de arquivos pelo formulario. Durante chamadas mTLS, `GatewayCertificateService` prepara o certificado e a chave para cURL e remove artefatos temporarios ao final. Credenciais antigas que ainda contenham `certificate_path` ou `private_key_path` permanecem como fallback de execucao; ao enviar um certificado pela tela, os campos legados sao removidos. Remover um certificado obrigatorio tambem inativa o gateway.

#### Autenticacao Sicoob

O Sicoob usa OAuth2 `client_credentials` com mTLS. Os escopos devem ser
solicitados por produto, sem misturar permissoes de APIs que nao estejam
habilitadas no aplicativo do cooperado:

- Pix: `cob.read cob.write pix.read pix.write`;
- Boleto: `boletos_inclusao boletos_consulta boletos_alteracao`.

Os escopos de webhook nao fazem parte do token enquanto o sistema nao executar
cadastro ou manutencao de webhook pela API. Quando Pix e Boleto estiverem
habilitados, cada conjunto deve ser autenticado e armazenado em cache
separadamente. Uma rejeicao OAuth deve ser classificada por `invalid_scope`,
`invalid_client`/`unauthorized_client`, erro mTLS ou codigo HTTP, sem registrar
ou exibir token, Client ID completo, certificado, secret ou corpo bruto.

#### Autenticacao por produto

A selecao de Pix e Boleto na interface nao implica, por si so, que o gateway
deva solicitar dois tokens. A autenticacao deve acompanhar a API oficial
efetivamente chamada:

| Gateway | Estrategia |
|---------|------------|
| Asaas | Uma API key no header `access_token` para todos os meios de pagamento |
| Cora | Um token OAuth para a API de invoices; o boleto pode incluir QR Code Pix |
| EfiPay | O SDK seleciona e armazena separadamente os tokens das APIs Pix e Cobrancas |
| Inter | Cobrança V3/BolePix usa `boleto-cobranca.read/write`; rotas `/pix/v2/cob` e de devolucao usam somente seus escopos Pix necessarios |
| Sicoob | Tokens separados para Pix e Boleto, com escopos distintos |
| Bradesco | Credenciais e tokens separados para Pix e Cobranca |
| Itau | Um token para o produto BoleCode, que combina boleto e Pix |
| Santander | Tokens separados para Pix Recebimentos e API de Cobranca |

O teste de conexao recebe `_pix_enabled` e `_boleto_enabled` internamente e
deve validar somente os produtos ativos. Na EfiPay, Pix exige certificado e
uma consulta somente leitura na API Pix, enquanto Boleto e validado na API
Cobrancas. No Santander, chave Pix e tipo da chave sao obrigatorios apenas para
Pix; Workspace ID e codigo do convenio sao obrigatorios apenas para Boleto.

No Inter, o cache de token e indexado pelo perfil de autorizacao. Nao se deve
enviar o token de `boleto-cobranca` a endpoints `/pix/v2`, nem solicitar todos
os escopos em conjunto: consultas de cobranca Pix usam `cob.read` e devolucoes
usam `pix.write`. A emissao atualmente chamada de Pix continua sendo uma
cobranca BolePix pela API Cobranca V3, portanto usa o perfil de Cobranca.

Rotas autenticadas para certificados de gateways:

| Rota | Metodo | Descricao |
|------|--------|-----------|
| `/gateways-pagamento/{id}/certificado` | POST multipart | Envia/substitui certificado; `chave_privada` acompanha PEM/CRT/CER |
| `/gateways-pagamento/{id}/certificado/remover` | POST | Remove certificado e metadados do gateway |

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

Rotas individuais por gateway: `/webhook/asaas`, `/webhook/stripe`, `/webhook/square`, `/webhook/cora`, `/webhook/efipay`, `/webhook/inter`, `/webhook/sicoob`, `/webhook/bradesco`, `/webhook/itau`, `/webhook/santander`, `/webhook/bancard`, `/webhook/pagopar`. O Santander tambem aceita o alias `/webhook/santander/pix` exigido pelo cadastro do webhook Pix.

#### Banco Santander

A integracao usa duas APIs oficiais independentes: Pix Recebimentos (`cobv`, consulta, cancelamento e devolucao) e API de Cobranca v2 (`workspaces`, registro e consulta de boletos). Ambas usam `client_id`, `client_secret`, OAuth2 client credentials e mTLS. O cadastro exige tambem chave Pix, tipo da chave, Workspace ID e codigo do convenio. Cartoes e Pix Automatico nao fazem parte deste gateway.

O webhook Pix do Santander exige `GET` habilitado para validacao e envia eventos por `POST`. Antes de aceitar uma notificacao como paga, `SantanderGateway::validateWebhookSignature()` consulta a cobranca na API autenticada do banco; um payload isolado nao liquida o financeiro local.

#### Banco Bradesco

A integracao executavel usa a API Pix Bradesco v2. Em producao, a autenticacao ocorre em `https://qrpix.bradesco.com.br/oauth/token` e os recursos em `https://qrpix.bradesco.com.br/v2`; homologacao usa os mesmos caminhos no host `qrpix-h.bradesco.com.br`. O token OAuth2 usa `client_credentials`, autenticacao HTTP Basic com `client_id` e `client_secret`, e certificado de cliente mTLS. O cadastro tambem exige a chave Pix recebedora.

Cobranças com vencimento sao criadas com `PUT /cobv/{txid}`. Consulta, remocao e devolucao usam, respectivamente, `/cobv/{txid}`, o status `REMOVIDA_PELO_USUARIO_RECEBEDOR` e `/pix/{endToEndId}/devolucao/{id}`. O identificador local e persistido como `pix_{txid}`. Notificacoes Pix somente sao aceitas como pagas depois de uma consulta autenticada ao banco.

O produto Boleto/Cobranca do Bradesco possui contrato e credenciais proprios, embora possa usar o mesmo certificado de cliente vinculado no banco. O gateway anuncia `pix` e `boleto`; para ativar boleto, exige Client ID, Client Secret, CNPJ do beneficiario, carteira/ID do produto e numero da negociacao. A API Cobranca usa OAuth2 client credentials com mTLS e endpoints proprios para registro, consulta e baixa. A opcao nao deve ser habilitada sem o indicador 175 ativo no contrato do cliente.

No controller, cada rota individual `POST` deve ter um wrapper (`webhookAsaas`, `webhookStripe`, etc.) chamando o handler generico `PagamentoPublicoController::webhook($request, $gatewayCode)`. Isso evita erro 500 por metodo inexistente e centraliza idempotencia, validacao de assinatura e atualizacao de transacao.

As rotas `GET` existem apenas para diagnostico quando a URL e aberta no navegador. Elas devem retornar uma mensagem informando que o endpoint esta ativo e que eventos reais precisam usar `POST`; nao devem processar payload, validar assinatura, atualizar transacoes ou acessar dados de tenant.

#### Asaas

O Asaas envia eventos de cobranca por `POST` em JSON, com `event` no topo e os dados da cobranca em `payment`. O processamento deve usar `payment.id` como identificador externo da cobranca e `payment.externalReference` como fallback para reconciliar links publicos criados pelo sistema (`link_{id}`).

O endpoint deve responder `200` rapidamente para payloads validos recebidos do Asaas, mesmo quando o evento for apenas informativo ou quando a transacao local ainda nao puder ser reconciliada. Erros `400` devem ser evitados nesses casos para nao interromper a fila de webhooks no Asaas. Token/assinatura invalida continua retornando erro de autenticacao quando houver token configurado.

Eventos sem `externalReference` no formato `link_{id}` podem pertencer a
cobrancas criadas fora do sistema. Quando nao houver transacao local, eles sao
respondidos com `200` e `ignored=true`, sem gerar erro no Apache. Uma referencia
`link_{id}` nao reconciliada continua sendo registrada como alerta, pois indica
inconsistencia em um link criado pela plataforma.

O log detalhado de entrada fica desativado por padrao. Para diagnostico
temporario, habilite e depois desabilite novamente:

```env
PAYMENT_WEBHOOK_DEBUG=true
```

Mesmo em debug, o log inclui apenas gateway, evento, identificadores,
referencia e nomes das chaves recebidas; payload completo e credenciais nao
devem ser registrados.

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

## Contabilizacao da taxa do gateway

Quando a cobranca e confirmada, o sistema registra na receita e na despesa
vinculada o `id_gateway` efetivamente usado. O valor segue esta precedencia:

1. `financeiro_transacoes.fee`, quando positivo;
2. diferenca entre `amount` e `net_amount`;
3. `financeiro.valor_taxa`, calculado pela forma de pagamento.

O gateway e uma dimensao de analise e nao substitui o plano de contas. Por isso,
o padrao e um unico plano `3.4.1.21 - Taxas de meios de pagamento`, com opcao de
outro plano na forma de pagamento. Relatorios por gateway devem filtrar
`financeiro.id_gateway`, sem multiplicar desnecessariamente o plano contabil.

---

## Seguranca

- **Credenciais**: Criptografadas com AES-256-CBC usando `APP_KEY`
- **Webhooks**: Validacao de assinatura especifica por gateway (HMAC, token, mTLS)
- **Idempotencia**: Verificacao se webhook ja foi processado (`webhookJaProcessado`)
- **Mascaramento**: Credenciais sao mascaradas na API de leitura (nunca expostas no frontend)
- **mTLS**: Gateways bancarios (Cora, Inter, Sicoob, Bradesco, Itau) usam certificados mutuos

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
| `app/Database/migrations/00396_drop_formas_gateway.php` | Remove tabela legada `formas_gateway` |

### Legado Removido

A tabela `formas_gateway` era a estrutura antiga de configuracao de gateways por tenant/filial. Os dados foram migrados para `gateways_pagamento` pela migration `00203_migrate_formas_gateway_data.php`, e os vinculos atuais sao mantidos por `gateways_filiais` e `formas_pagamento_gateways`. A tabela legada foi removida pela migration `00396_drop_formas_gateway.php`.
