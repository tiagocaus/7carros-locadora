# Central de Multas - Integracao com sistema de consultas online

> Especificacao tecnica completa para integracao com a API de consultas online,
> sistema de saldo prepago e Central de Multas do 7Carros Locadora.

---

## 1. Visao Geral

### Regra obrigatoria de nomenclatura no front-end

O front-end **nunca deve exibir o termo tecnico do provedor** para clientes. Isso vale
para textos visiveis e tambem para qualquer codigo ou payload que o tenant consiga
inspecionar no navegador: ids/classes HTML, nomes de funcoes/variaveis JS renderizadas,
valores de `option`, atributos `data-*` e JSON retornado para telas. Em labels, botoes,
alertas, mensagens de erro, tooltips, modais, historico e PDFs visiveis ao tenant, use
sempre:

- `Consulta Online`
- `consultas online`
- `sistema de consultas online`

O nome tecnico do provedor deve ficar restrito a contexto interno: classes,
services, controllers, rotas internas, tabelas, colunas, variaveis de ambiente,
logs tecnicos e documentacao tecnica de integracao. Quando um valor interno precisa
chegar ao front-end, exponha um alias neutro, por exemplo `consulta_online`,
`evento_online` ou `status_online`.

### 1.1 Objetivo

Transformar o modulo de multas atual (100% manual) em uma **Central de Multas inteligente** integrada com a API de consultas online, permitindo:

- Consulta automatica de infracoes de transito
- Recebimento de eventos/notificacoes em tempo real
- Indicacao de real infrator (transferencia de pontos)
- Indicacao de principal condutor
- Download de documentos (NA, NP, CRLV)
- Gestao de saldo prepago com recargas via PIX e cartao

### 1.2 Modelo de Negocio

```
┌─────────────────────────────────────────────────────────────────────┐
│                        MODELO DE NEGOCIO                            │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  7Carros possui CONTRATO UNICO com o provedor de consultas online  │
│  Cada tenant (locadora) usa o servico atraves da 7Carros           │
│                                                                     │
│  Custo para o tenant:                                               │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │  Preco SERPRO (fixo, configuravel via ENV)                  │   │
│  │  + Markup % (configuravel via ENV, padrao 10%)              │   │
│  │  = Valor cobrado do saldo do tenant                         │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                     │
│  Exemplo: Consulta = R$0,43 + 10% = R$0,47                        │
│  Exemplo: Evento   = R$1,07 + 10% = R$1,18                        │
│                                                                     │
│  O saldo eh PRE-PAGO. Tenant recarrega antes de usar.             │
│  Recargas via PIX (Banco Inter) ou Cartao (Stripe) vao            │
│  para a conta da 7Carros (dados na ENV).                           │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### 1.3 Tabela de Precos SERPRO (Referencia)

Os precos da SERPRO variam por volume acumulado. A 7Carros define via ENV o preco atual da faixa vigente.

**Consultas:**

| Faixa | Quantidade        | Valor Unitario |
|-------|-------------------|----------------|
| 1     | 1 a 250           | R$ 0,43        |
| 2     | 251 a 700         | R$ 0,42        |
| 3     | 701 a 3.000       | R$ 0,41        |
| 4     | 3.001 a 10.000    | R$ 0,39        |
| 5     | 10.001 a 80.000   | R$ 0,37        |
| 6     | 80.001 a 400.000  | R$ 0,36        |
| 7     | 400.001 a 1.300M  | R$ 0,33        |
| 8     | acima de 1.300M   | R$ 0,31        |

**Eventos:**

| Faixa | Quantidade        | Valor Unitario |
|-------|-------------------|----------------|
| 1     | 1 a 60            | R$ 1,07        |
| 2     | 61 a 300          | R$ 1,05        |
| 3     | 301 a 1.000       | R$ 1,01        |
| 4     | 1.001 a 8.000     | R$ 0,97        |
| 5     | 8.001 a 50.000    | R$ 0,93        |
| 6     | 50.001 a 200.000  | R$ 0,87        |
| 7     | 200.001 a 800.000 | R$ 0,81        |
| 8     | acima de 800.000  | R$ 0,75        |

> Base legal: Portaria SENATRAN No 461/2025

---

## 2. Arquitetura do Sistema

### 2.1 Fluxo Geral

```
┌─────────────┐     ┌──────────────────────┐     ┌──────────────────┐
│   TENANT    │     │    7CARROS SYSTEM     │     │ Consultas Online  │
│  (Locadora) │     │                      │     │      API          │
│             │     │  ┌────────────────┐  │     │                  │
│  Dashboard  │────▶│  │ SerproService  │──┼────▶│  /consultas/     │
│  Central    │     │  └───────┬────────┘  │     │  /transacional/  │
│  Multas     │     │          │           │     │  /notificacoes/  │
│             │     │  ┌───────▼────────┐  │     │  /gerenciamento/ │
│             │◀────│  │ SaldoService   │  │     │                  │
│             │     │  └───────┬────────┘  │     └────────┬─────────┘
└──────┬──────┘     │          │           │              │
       │            │  ┌───────▼────────┐  │     ┌────────▼─────────┐
       │            │  │  Transacoes    │  │     │    Webhook       │
       │            │  │  (Historico)   │  │     │  POST /serpro/wh │
       │            │  └────────────────┘  │     └──────────────────┘
       │            └──────────────────────┘
       │
       │  Recargas
       ▼
┌──────────────┐    ┌──────────────┐
│  PIX         │    │  Stripe      │
│  Banco Inter │    │  Cartao      │
│  (QR Code)   │    │  (auto-rec.) │
└──────┬───────┘    └──────┬───────┘
       │                    │
       │   Webhook          │   Webhook
       ▼                    ▼
┌──────────────────────────────────┐
│  Confirma pagamento              │
│  → Credita saldo do tenant      │
│  → Registra em serpro_transacoes │
└──────────────────────────────────┘
```

### 2.2 Fluxo de Consulta de Multas

```
┌──────────┐    ┌───────────────┐    ┌──────────────┐    ┌──────────────┐
│  Tenant  │───▶│  Verifica     │───▶│  Chama API   │───▶│ Cria/Atualiza│
│  solicita│    │  saldo >= R$  │    │  SERPRO       │    │  multas no   │
│  consulta│    │  preco_consult│    │  GET /infra-  │    │  sistema     │
│  (manual │    │               │    │  coes/placa/  │    │              │
│  ou auto)│    └───────┬───────┘    └──────┬───────┘    └──────┬───────┘
└──────────┘            │                    │                   │
                        │ Saldo              │                   │
                        │ insuficiente       ▼                   ▼
                        ▼             ┌──────────────┐  ┌──────────────┐
                 ┌───────────┐       │ Debita saldo │  │ Log consulta │
                 │ Erro:     │       │ serpro_saldo  │  │ serpro_con-  │
                 │ "Saldo    │       └──────────────┘  │ sultas_log   │
                 │ insufic." │                          └──────────────┘
                 │           │
                 │ Se auto-  │
                 │ recarga ON│
                 │ → tenta   │
                 │ recarregar│
                 └───────────┘
```

### 2.3 Fluxo de Recarga de Saldo

```
RECARGA PIX:
┌──────────┐    ┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│  Tenant  │───▶│  Informa     │───▶│  InterGateway│───▶│  Exibe QR    │
│  clica   │    │  valor       │    │  gera PIX    │    │  Code + copia│
│  "Recarga│    │  (min R$100) │    │  (cob imediata)│  │  e cola      │
│   PIX"   │    └──────────────┘    └──────────────┘    └──────┬───────┘
└──────────┘                                                    │
                                                     Tenant paga│
                                                                ▼
                                                   ┌──────────────────┐
                                                   │ Webhook Inter    │
                                                   │ POST /webhook/   │
                                                   │ serpro-pix       │
                                                   └────────┬─────────┘
                                                            │
                                                            ▼
                                                   ┌──────────────────┐
                                                   │ Credita saldo    │
                                                   │ Registra transac.│
                                                   │ Status: confirmado│
                                                   └──────────────────┘

RECARGA CARTAO (STRIPE):
┌──────────┐    ┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│  Tenant  │───▶│  Informa     │───▶│StripeGateway │───▶│  Processa    │
│  clica   │    │  valor       │    │ PaymentIntent│    │  pagamento   │
│  "Recarga│    │  (min R$100) │    │ ou Checkout  │    │              │
│   Card"  │    └──────────────┘    └──────────────┘    └──────┬───────┘
└──────────┘                                                    │
                                                                ▼
AUTO-RECARGA (se ativada):                             ┌──────────────┐
┌──────────────┐    ┌──────────────┐                   │ Credita saldo│
│ Saldo < R$10 │───▶│ Cobra R$100  │                   │ Registra     │
│ (verificado  │    │ do cartao    │                   │ transacao    │
│ apos cada    │    │ salvo (saved │                   └──────────────┘
│ debito)      │    │ payment      │
└──────────────┘    │ method)      │
                    └──────────────┘
```

### 2.4 Fluxo de Eventos (Webhook SERPRO)

```
┌──────────────┐    ┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│ SERPRO envia  │───▶│  POST        │───▶│  Valida      │───▶│  Identifica  │
│ evento       │    │  /webhook/   │    │  token de    │    │  tenant pelo │
│ (nova multa, │    │  serpro       │    │  autorizacao │    │  CNPJ/placa  │
│ vencimento,  │    │              │    │              │    │              │
│ etc.)        │    └──────────────┘    └──────────────┘    └──────┬───────┘
└──────────────┘                                                    │
                                                                    ▼
                                                    ┌──────────────────────┐
                                                    │ Verifica saldo tenant│
                                                    │ Debita valor evento  │
                                                    │ Cria/atualiza multa  │
                                                    │ Log em serpro_trans.  │
                                                    │ Notifica tenant      │
                                                    └──────────────────────┘
```

### 2.5 Fluxo de Indicacao de Real Infrator

```
┌──────────┐    ┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│  Tenant  │───▶│  Seleciona   │───▶│  Informa CPF │───▶│  Chama API   │
│  clica   │    │  multa na    │    │  e CNH do    │    │  SERPRO POST  │
│  "Indicar│    │  central     │    │  locatario   │    │  /realinfrator│
│  Infrator│    │              │    │  (cliente do │    │  /indicacoes/ │
│  "        │   │              │    │  contrato)   │    │  inserir     │
└──────────┘    └──────────────┘    └──────────────┘    └──────┬───────┘
                                                               │
                                                               ▼
                                                    ┌──────────────────┐
                                                    │ Salva indicacao  │
                                                    │ serpro_indicacoes │
                                                    │ Status: enviado  │
                                                    │ Debita consulta  │
                                                    │ do saldo         │
                                                    └──────────────────┘
```

---

## 3. Banco de Dados

### 3.1 Novas Tabelas

#### serpro_configuracoes
Configuracoes da integracao SERPRO por tenant.

```sql
CREATE TABLE serpro_configuracoes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave           VARCHAR(45) NOT NULL,                -- multi-tenancy
    cnpj_empresa    VARCHAR(14) NOT NULL,                -- CNPJ da locadora na SERPRO

    -- Consulta automatica
    auto_consulta_ativo     TINYINT(1) DEFAULT 0,        -- 0=desativado, 1=ativado
    intervalo_dias_consulta INT DEFAULT 7,               -- a cada X dias
    ultima_consulta_em      DATETIME NULL,               -- data da ultima consulta auto

    -- Eventos automaticos
    auto_eventos_ativo      TINYINT(1) DEFAULT 0,        -- 0=desativado, 1=ativado
    webhook_registrado      TINYINT(1) DEFAULT 0,        -- se webhook ja foi registrado na SERPRO

    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_serpro_config_chave (chave)
);
```

#### serpro_saldo
Saldo prepago de cada tenant para uso da API SERPRO.

```sql
CREATE TABLE serpro_saldo (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave                   VARCHAR(45) NOT NULL,           -- multi-tenancy

    saldo                   DECIMAL(10,2) DEFAULT 0.00,     -- saldo atual em BRL

    -- Auto-recarga Stripe
    auto_recarga_ativo      TINYINT(1) DEFAULT 0,           -- 0=desativado, 1=ativado
    auto_recarga_valor      DECIMAL(10,2) DEFAULT 100.00,   -- valor da recarga automatica
    auto_recarga_limite     DECIMAL(10,2) DEFAULT 10.00,    -- quando saldo cair abaixo de X

    -- Dados Stripe para recorrencia
    stripe_customer_id      VARCHAR(255) NULL,               -- cus_XXXXX
    stripe_payment_method_id VARCHAR(255) NULL,              -- pm_XXXXX

    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_serpro_saldo_chave (chave)
);
```

#### serpro_transacoes
Historico completo de todas as transacoes (recargas e debitos).

```sql
CREATE TABLE serpro_transacoes (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave               VARCHAR(45) NOT NULL,               -- multi-tenancy

    tipo                ENUM(
                            'recarga_pix',
                            'recarga_cartao',
                            'recarga_manual',       -- ajuste manual admin
                            'consulta',
                            'evento',
                            'indicacao'
                        ) NOT NULL,

    -- Valores detalhados (para auditoria interna da 7Carros)
    valor_serpro        DECIMAL(10,4) DEFAULT 0.0000,       -- custo real SERPRO
    valor_markup        DECIMAL(10,4) DEFAULT 0.0000,       -- valor do markup (%)
    valor_total         DECIMAL(10,2) NOT NULL,              -- valor final cobrado/creditado

    -- Saldo snapshot
    saldo_anterior      DECIMAL(10,2) NOT NULL,
    saldo_posterior     DECIMAL(10,2) NOT NULL,

    -- Descricao e referencia
    descricao           VARCHAR(255) NULL,                   -- "Consulta infracoes placa ABC1D23"
    referencia          VARCHAR(100) NULL,                   -- placa, id_multa, etc.

    -- Dados do pagamento externo (recargas)
    external_id         VARCHAR(255) NULL,                   -- txid PIX ou pi_XXXXX Stripe
    payment_method      VARCHAR(50) NULL,                    -- 'pix', 'credit_card'
    payment_url         VARCHAR(500) NULL,                   -- URL checkout Stripe
    pix_code            TEXT NULL,                            -- copia e cola PIX
    pix_qrcode          TEXT NULL,                            -- QR Code base64

    status              ENUM('pendente', 'confirmado', 'falha', 'cancelado', 'estornado')
                        DEFAULT 'pendente',

    confirmado_em       DATETIME NULL,                       -- quando pagamento foi confirmado
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_serpro_trans_chave (chave),
    INDEX idx_serpro_trans_tipo (tipo),
    INDEX idx_serpro_trans_status (status),
    INDEX idx_serpro_trans_created (created_at),
    INDEX idx_serpro_trans_external (external_id)
);
```

#### serpro_consultas_log
Log tecnico detalhado de cada chamada a API SERPRO.

```sql
CREATE TABLE serpro_consultas_log (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave               VARCHAR(45) NOT NULL,               -- multi-tenancy

    tipo_operacao       VARCHAR(50) NOT NULL,                -- 'consulta_infracoes', 'consulta_veiculo',
                                                             -- 'consulta_crlv', 'consulta_na_pdf',
                                                             -- 'consulta_np_pdf', 'indicar_real_infrator',
                                                             -- 'indicar_principal_condutor', etc.

    placa               VARCHAR(10) NULL,
    endpoint            VARCHAR(500) NOT NULL,               -- URL completa chamada

    request_headers     JSON NULL,                           -- headers enviados
    request_payload     JSON NULL,                           -- body enviado (POST/PUT)
    response_status     INT NULL,                            -- HTTP status code
    response_payload    JSON NULL,                           -- body recebido

    status              ENUM('sucesso', 'erro', 'timeout') DEFAULT 'sucesso',
    erro_mensagem       VARCHAR(500) NULL,                   -- mensagem de erro se houver

    id_serpro_transacao INT UNSIGNED NULL,                   -- FK para serpro_transacoes
    duracao_ms          INT NULL,                            -- tempo de resposta em ms

    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_serpro_log_chave (chave),
    INDEX idx_serpro_log_placa (placa),
    INDEX idx_serpro_log_tipo (tipo_operacao),
    INDEX idx_serpro_log_created (created_at)
);
```

#### serpro_indicacoes
Indicacoes de real infrator e principal condutor.

```sql
CREATE TABLE serpro_indicacoes (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave               VARCHAR(45) NOT NULL,               -- multi-tenancy

    tipo                ENUM('real_infrator', 'principal_condutor') NOT NULL,

    -- Referencia interna
    id_multa            INT UNSIGNED NULL,                   -- FK multas (para real infrator)
    id_veiculo          INT UNSIGNED NULL,                   -- FK veiculos
    id_cliente          INT UNSIGNED NULL,                   -- FK clientes (locatario indicado)
    id_contrato         INT UNSIGNED NULL,                   -- FK contratos
    id_locacao          INT UNSIGNED NULL,                   -- FK locacoes

    -- Dados da placa/infracao
    placa               VARCHAR(10) NOT NULL,
    codigo_orgao        VARCHAR(20) NULL,                    -- chave da infracao SERPRO
    numero_ait          VARCHAR(30) NULL,                    -- auto de infracao
    codigo_infracao     VARCHAR(20) NULL,

    -- Dados do indicado
    cpf_indicado        VARCHAR(14) NOT NULL,
    nome_indicado       VARCHAR(150) NULL,
    cnh_indicado        VARCHAR(20) NULL,

    -- Resposta SERPRO
    chave_indicacao     VARCHAR(50) NULL,                    -- chave retornada pela SERPRO
    status_serpro       VARCHAR(50) DEFAULT 'enviado',       -- enviado, processando, aceito,
                                                              -- rejeitado, cancelado, expirado
    motivo_rejeicao     VARCHAR(500) NULL,
    documento_assinado  TEXT NULL,                            -- PDF base64 do documento assinado

    data_indicacao      DATETIME NULL,
    data_resposta       DATETIME NULL,
    data_expiracao      DATETIME NULL,

    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_serpro_ind_chave (chave),
    INDEX idx_serpro_ind_placa (placa),
    INDEX idx_serpro_ind_tipo (tipo),
    INDEX idx_serpro_ind_multa (id_multa),
    INDEX idx_serpro_ind_status (status_serpro),
    INDEX idx_serpro_ind_chave_ind (chave_indicacao)
);
```

### 3.2 Alteracoes na Tabela `multas`

Novos campos para integracao SERPRO:

```sql
ALTER TABLE multas
    -- Chaves SERPRO (identificadores unicos da infracao)
    ADD COLUMN codigo_orgao          VARCHAR(20) NULL AFTER foto,
    ADD COLUMN numero_ait            VARCHAR(30) NULL AFTER codigo_orgao,
    ADD COLUMN codigo_infracao       VARCHAR(20) NULL AFTER numero_ait,

    -- Origem do registro
    ADD COLUMN origem                ENUM('manual', 'serpro_consulta', 'serpro_evento')
                                     DEFAULT 'manual' AFTER codigo_infracao,

    -- Status de processamento
    ADD COLUMN status_processamento  ENUM(
                                        'novo',
                                        'pendente_indicacao',
                                        'indicacao_enviada',
                                        'indicacao_aceita',
                                        'indicacao_rejeitada',
                                        'transferido',
                                        'pago',
                                        'cancelado'
                                     ) DEFAULT 'novo' AFTER origem,

    -- Valores adicionais
    ADD COLUMN valor_desconto_40     DECIMAL(10,2) NULL AFTER status_processamento,

    -- PDFs da SERPRO
    ADD COLUMN na_pdf_path           VARCHAR(255) NULL AFTER valor_desconto_40,
    ADD COLUMN np_pdf_path           VARCHAR(255) NULL AFTER na_pdf_path,

    -- Datas de notificacao
    ADD COLUMN data_notificacao_autuacao   DATE NULL AFTER np_pdf_path,
    ADD COLUMN data_notificacao_penalidade DATE NULL AFTER data_notificacao_autuacao,

    -- Sync
    ADD COLUMN serpro_sync_at        DATETIME NULL AFTER data_notificacao_penalidade,

    -- Indices
    ADD INDEX idx_multas_codigo_orgao (codigo_orgao),
    ADD INDEX idx_multas_numero_ait (numero_ait),
    ADD INDEX idx_multas_origem (origem),
    ADD INDEX idx_multas_status_proc (status_processamento);
```

### 3.3 Diagrama de Relacionamentos

```
                    ┌──────────────────┐
                    │  serpro_config    │
                    │  (1 por tenant)  │
                    └────────┬─────────┘
                             │ chave
                             │
┌──────────────┐    ┌────────▼─────────┐    ┌──────────────────┐
│ serpro_saldo │    │     multas       │    │ serpro_indicacoes │
│ (1 por       │    │ (campos novos +  │    │ (N por multa)    │
│  tenant)     │    │  existentes)     │◀───│                  │
└──────┬───────┘    └────────┬─────────┘    └──────────────────┘
       │                     │
       │                     │
       ▼                     ▼
┌──────────────┐    ┌──────────────────┐
│ serpro_trans  │    │ serpro_consultas  │
│ (N por       │    │ _log             │
│  tenant)     │    │ (N por tenant)   │
└──────────────┘    └──────────────────┘
       ▲
       │ id_serpro_transacao
       │
┌──────┴───────┐
│ serpro_con-   │
│ sultas_log   │
└──────────────┘
```

---

## 4. Variaveis de Ambiente (ENV)

### 4.1 Novas ENVs

```env
# ============================
# API de consultas online
# ============================
SERPRO_AMBIENTE=homologacao                   # homologacao | producao
SERPRO_BEARER_TOKEN=                          # JWT token usado em homologacao
SERPRO_HOMOLOGACAO_BASE_URL=https://hom-efrotas.np.estaleiro.serpro.gov.br/efrotas/api
SERPRO_HOMOLOGACAO_BASE_URL_TRANSACIONAL=https://hom-efrotas.np.estaleiro.serpro.gov.br/efrotas/api/transacional
SERPRO_HOMOLOGACAO_BASE_URL_CRLV=https://hom-efrotas.np.estaleiro.serpro.gov.br/efrotas/api/crlv
SERPRO_PRODUCAO_BASE_URL=https://efrotas.estaleiro.serpro.gov.br/efrotas/api
SERPRO_PRODUCAO_BASE_URL_TRANSACIONAL=https://efrotas.estaleiro.serpro.gov.br/efrotas/api/transacional
SERPRO_PRODUCAO_BASE_URL_CRLV=https://efrotas.estaleiro.serpro.gov.br/efrotas/api/crlv
SERPRO_CERT_PATH=storage/certificates/7carros/certificate.pfx
SERPRO_CERT_PASSWORD=                         # senha do certificado digital A1/PFX usado em producao
SERPRO_CERT_TYPE=P12                          # P12/PFX ou PEM
SERPRO_CERT_KEY_PATH=                         # obrigatorio apenas quando SERPRO_CERT_TYPE=PEM e chave estiver separada
SERPRO_CERT_KEY_PASSWORD=                     # senha da chave PEM, se houver
SERPRO_WEBHOOK_SECRET=                        # token para validar webhooks recebidos

# ============================
# SERPRO - Precos e Markup
# ============================
SERPRO_PRECO_CONSULTA=0.43                    # preco atual da faixa vigente (consulta)
SERPRO_PRECO_EVENTO=1.07                      # preco atual da faixa vigente (evento)
SERPRO_PRECO_INDICACAO=1.07                   # preco configurado para indicacao de condutor
SERPRO_PRECO_COSULTA_DADOSVEICULO=0.43        # preco da consulta de dados cadastrais do veiculo
SERPRO_PRECO_COSULTA_CRLV=0.43                # preco da consulta de CRLV
SERPRO_MARKUP_PERCENT=10                      # % adicionado ao preco SERPRO

# ============================
# SERPRO - Recarga (conta 7Carros)
# ============================
SERPRO_RECARGA_MINIMA=100.00                  # valor minimo de recarga manual
SERPRO_AUTO_RECARGA_VALOR=100.00              # valor padrao da auto-recarga
SERPRO_AUTO_RECARGA_LIMITE=10.00              # saldo minimo para disparar auto-recarga
```

### 4.2 ENVs Existentes Reutilizadas

```env
# Banco Inter (PIX) - ja existem
INTER_BASE_URL=https://cdpj.partners.bancointer.com.br
INTER_CLIENT_ID=
INTER_CLIENT_SECRET=
INTER_CERT_PATH=
INTER_KEY_PATH=
INTER_PIX_KEY=

# Stripe (Cartao) - ja existem
STRIPE_PUBLIC_KEY=
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
```

---

## 5. Endpoints da API SERPRO Utilizados

### 5.1 Consultas (GET)

| Operacao                        | Metodo | Endpoint                                                                           |
|---------------------------------|--------|------------------------------------------------------------------------------------|
| Listar veiculos da frota        | GET    | `/consultas/v1/veiculos?cnpjFilial={cnpj}&pagina={p}&quantidade={q}`               |
| Consultar veiculo por placa     | GET    | `/consultas/v1/veiculos/placa/{placa}`                                             |
| Verificar se pertence a frota   | GET    | `/consultas/v1/veiculos/placa/{placa}/pertence`                                    |
| Consultar roubo/furto           | GET    | `/consultas/v1/veiculos/placa/{placa}/roubo-furto`                                 |
| Consultar recall pendente       | GET    | `/consultas/v1/veiculos/placa/{placa}/recall`                                      |
| Consultar restricoes judiciais  | GET    | `/consultas/v1/veiculos/placa/{placa}/restricoes-judiciais`                        |
| Listar infracoes do veiculo     | GET    | `/consultas/v1/infracoes/placa/{placa}`                                            |
| Detalhe da infracao             | GET    | `/consultas/v1/infracoes/codigoOrgao/{co}/numeroAit/{na}/codigoInfracao/{ci}`      |
| PDF da NA                       | GET    | `/consultas/sne/pdf/placa/{p}/codigoOrgao/{co}/numeroAit/{na}/codigoInfracao/{ci}/NA` |
| PDF da NP                       | GET    | `/consultas/sne/pdf/placa/{p}/codigoOrgao/{co}/numeroAit/{na}/codigoInfracao/{ci}/NP` |
| CRLV do veiculo                 | GET    | `{SERPRO_*_BASE_URL_CRLV}/v1/documento/placa/{placa}`                              |
| Notificacoes por periodo        | GET    | `/notificacoes/v1/dataInicio/{di}/dataFim/{df}`                                    |

### 5.2 Gerenciamento de Eventos

| Operacao                   | Metodo | Endpoint                          | Body                                |
|----------------------------|--------|-----------------------------------|-------------------------------------|
| Listar eventos ativos      | GET    | `/autorizador/v1/eventos`         | -                                   |
| Ativar/Desativar evento    | PUT    | `/autorizador/v1/eventos`         | `{"eventosPermitidos":[{"codigo":1,"ativo":true}]}` |
| Consultar URL webhook      | GET    | `/autorizador/v1/endpoint`        | -                                   |
| Registrar URL webhook      | PUT    | `/autorizador/v1/endpoint`        | `{"url":"...","header":"X-Webhook-Secret","valor":"..."}` |
| Remover URL webhook        | DELETE | `/autorizador/v1/endpoint/{id}`   | -                                   |

#### Cadastro idempotente do webhook

O cadastro do endpoint pode retornar HTTP 409 com mensagem equivalente a `Endpoint ja existe na base de dados do Efrotas`.
Esse retorno nao deve ser tratado como falha automaticamente:

1. Consultar `GET /autorizador/v1/endpoint`.
2. Se a URL cadastrada for a URL esperada (`{APP_URL}/webhook/multas-online/eventos`), considerar o webhook registrado e seguir para ativar eventos.
3. Se a URL cadastrada for diferente, bloquear a ativacao e orientar a remocao/ajuste do endpoint atual.

O servidor atual nao repassa de forma confiavel o header `Authorization` para PHP em chamadas publicas de webhook. Por isso, o endpoint deve ser cadastrado na Consulta Online com `header=X-Webhook-Secret` e `valor={SERPRO_WEBHOOK_SECRET}`.

Depois de validar ou cadastrar o webhook, ativar o evento de notificacao de autuacao com `PUT /autorizador/v1/eventos` e body `{"eventosPermitidos":[{"codigo":1,"ativo":true}]}`.

### 5.3 Transacional - Real Infrator

| Operacao                   | Metodo | Endpoint                                                                    |
|----------------------------|--------|-----------------------------------------------------------------------------|
| Inserir indicacao          | POST   | `/transacional/v1/realinfrator/indicacoes/inserir`                          |
| Cancelar indicacao         | POST   | `/transacional/v1/realinfrator/indicacoes/{chave}/cancelar`                 |
| Status da indicacao        | GET    | `/transacional/v1/realinfrator/indicacoes/{chave}/status`                   |
| Documento assinado         | GET    | `/transacional/v1/realinfrator/indicacoes/{chave}/{co}/{na}/{ci}/documentoAssinado` |
| Historico por infracao     | GET    | `/transacional/v1/realinfrator/indicacoes/historico/{co}/{na}/{ci}`         |

### 5.4 Transacional - Principal Condutor

| Operacao                   | Metodo | Endpoint                                                                    |
|----------------------------|--------|-----------------------------------------------------------------------------|
| Inserir indicacao          | POST   | `/transacional/v1/principalcondutor/indicacoes/inserir`                     |
| Excluir indicacao          | POST   | `/transacional/v1/principalcondutor/indicacoes/excluir`                     |
| Status da indicacao        | GET    | `/transacional/v1/principalcondutor/indicacoes/status?chaveIndicacao={ch}`  |
| Historico                  | GET    | `/transacional/v1/principalcondutor/indicacoes/historico?placa={p}&...`     |

### 5.5 Transacional - Boleto SNE

> Status: HOMOLOGACAO INTERNA (ainda nao disponivel para clientes externos)

| Operacao                   | Metodo | Endpoint                                                  |
|----------------------------|--------|-----------------------------------------------------------|
| Solicitar boleto           | POST   | `/transacional/v1/boleto/solicitarBoleto`                 |
| Reconhecer infracao        | POST   | `/transacional/v1/boleto/reconhecerInfracao`              |
| PDF do boleto              | GET    | `/transacional/v1/boleto/consultarPdfBoleto`              |
| Dados de pagamento         | GET    | `/transacional/v1/boleto/consultarDadosPagamento`         |

### 5.6 Autenticacao

```
Accept: application/json
Content-Type: application/json (POST/PUT)
```

- A troca de ambiente eh feita apenas por `SERPRO_AMBIENTE`.
- Homologacao: usa `SERPRO_HOMOLOGACAO_*` e envia `Authorization: Bearer {SERPRO_BEARER_TOKEN}`.
- Producao: usa `SERPRO_PRODUCAO_*` e certificado digital mTLS. PFX/P12 usa `SERPRO_CERT_PATH` + `SERPRO_CERT_PASSWORD`; PEM usa `SERPRO_CERT_PATH` + `SERPRO_CERT_KEY_PATH`.
- As URLs de homologacao/producao sao obrigatorias no `.env`; o PHP nao mantem fallback hardcoded para endpoints SERPRO.
- Em producao, `SERPRO_BEARER_TOKEN` eh ignorado mesmo que esteja preenchido.
- Se o PFX antigo falhar no cURL com erro de certificado, converta para PEM com OpenSSL em modo `-legacy` e configure `SERPRO_CERT_TYPE=PEM`.

**Rate limit:** 15 conexoes/segundo por IP (HTTP 429 se exceder)

**Ambiente de homologacao:**
- URL Base: `https://hom-efrotas.np.estaleiro.serpro.gov.br/efrotas/api`
- URL Transacional: `https://hom-efrotas.np.estaleiro.serpro.gov.br/efrotas/api/transacional`
- CNPJ Teste: `33683111000107`
- Placas Teste: `SAV0741` a `SAV0750`

---

## 6. Endpoints Internos do Sistema (Rotas)

### 6.1 Paginas (HTML via iframe)

```
GET /pages/multas/central              → CentralMultasController::view()
GET /pages/multas/central/saldo        → CentralMultasController::viewSaldo()
GET /pages/multas/central/indicacao    → CentralMultasController::viewIndicacao()
```

### 6.2 API - Central de Multas

```
GET    /api/multas/central/dashboard        → CentralMultasController::dashboard()
GET    /api/multas/central/stats             → CentralMultasController::stats()
```

### 6.3 API - Saldo e Recargas

```
GET    /api/serpro/saldo                     → SerproSaldoController::saldo()
GET    /api/serpro/transacoes                → SerproSaldoController::transacoes()
POST   /api/serpro/recarga/pix               → SerproSaldoController::recarregaPix()
POST   /api/serpro/recarga/stripe            → SerproSaldoController::recarregaStripe()
POST   /api/serpro/auto-recarga/ativar       → SerproSaldoController::ativarAutoRecarga()
POST   /api/serpro/auto-recarga/desativar    → SerproSaldoController::desativarAutoRecarga()
POST   /api/serpro/stripe/salvar-cartao      → SerproSaldoController::salvarCartao()
```

### 6.4 API - Consultas SERPRO

```
POST   /api/serpro/consultar/placa/{placa}        → SerproConsultaController::consultarPlaca()
POST   /api/serpro/consultar/todos-veiculos        → SerproConsultaController::consultarTodos()
GET    /api/serpro/consultar/infracao/{co}/{na}/{ci} → SerproConsultaController::detalheInfracao()
GET    /api/serpro/consultar/na-pdf/{co}/{na}/{ci}   → SerproConsultaController::downloadNA()
GET    /api/serpro/consultar/np-pdf/{co}/{na}/{ci}   → SerproConsultaController::downloadNP()
GET    /api/serpro/consultar/crlv/{placa}             → SerproConsultaController::crlv()
GET    /api/serpro/consultar/veiculo/{placa}           → SerproConsultaController::dadosVeiculo()
GET    /api/multas-online/crlv/{placa}                 → SerproConsultaController::crlv()
GET    /api/multas-online/veiculo/{placa}              → SerproConsultaController::dadosVeiculo()
POST   /api/serpro/configuracoes                       → SerproConsultaController::salvarConfig()
GET    /api/serpro/configuracoes                        → SerproConsultaController::getConfig()
GET    /api/serpro/consultas-log                        → SerproConsultaController::log()
```

### 6.5 API - Indicacoes

```
POST   /api/serpro/indicacao/real-infrator          → SerproIndicacaoController::indicarRealInfrator()
POST   /api/serpro/indicacao/real-infrator/cancelar  → SerproIndicacaoController::cancelarRealInfrator()
GET    /api/serpro/indicacao/real-infrator/status/{chave} → SerproIndicacaoController::statusRealInfrator()
POST   /api/serpro/indicacao/principal-condutor       → SerproIndicacaoController::indicarPrincipalCondutor()
POST   /api/serpro/indicacao/principal-condutor/excluir → SerproIndicacaoController::excluirPrincipalCondutor()
GET    /api/serpro/indicacao/principal-condutor/status  → SerproIndicacaoController::statusPrincipalCondutor()
GET    /api/serpro/indicacoes                           → SerproIndicacaoController::listar()
GET    /api/serpro/indicacoes/{id}                      → SerproIndicacaoController::detalhe()
```

### 6.6 Webhooks (Publicos - Sem CSRF)

```
POST   /webhook/serpro                → SerproWebhookController::handle()
POST   /webhook/serpro-pix            → SerproWebhookController::pixCallback()
POST   /webhook/serpro-stripe         → SerproWebhookController::stripeCallback()
```

### 6.7 CRON

```
SerproAutoConsultaJob  → Consulta multas de todos os tenants com auto_consulta_ativo=1
                         Executado a cada hora, verifica se intervalo_dias ja passou
```

---

## 7. Services (Backend)

### 7.1 SerproService

Responsavel por toda comunicacao com a API de consultas online.

```
app/Services/SerproService.php

Metodos:
├── __construct()                          → Carrega config ENV
├── consultarVeiculos(cnpj, pagina, qtd)   → GET /consultas/v1/veiculos
├── consultarVeiculoPorPlaca(placa)        → GET /consultas/v1/veiculos/placa/{p}
├── verificarPertenceAFrota(placa)         → GET /consultas/v1/veiculos/placa/{p}/pertence
├── consultarRouboFurto(placa)             → GET /consultas/v1/veiculos/placa/{p}/roubo-furto
├── consultarRecall(placa)                 → GET /consultas/v1/veiculos/placa/{p}/recall
├── consultarRestricoesJudiciais(placa)    → GET /consultas/v1/veiculos/placa/{p}/restricoes-judiciais
├── consultarInfracoes(placa)              → GET /consultas/v1/infracoes/placa/{p}
├── consultarInfracaoDetalhe(co, na, ci)   → GET /consultas/v1/infracoes/codigoOrgao/...
├── downloadNAPdf(placa, co, na, ci)       → GET /consultas/sne/pdf/.../NA
├── downloadNPPdf(placa, co, na, ci)       → GET /consultas/sne/pdf/.../NP
├── consultarCRLV(placa)                   → GET /v1/documento/placa/{p}
├── consultarNotificacoes(dataIni, dataFim) → GET /notificacoes/v1/...
├── listarEventos()                        → GET /autorizador/v1/eventos
├── ativarEvento(tipoEvento, ativo)        → PUT /autorizador/v1/eventos
├── consultarUrlWebhook()                  → GET /autorizador/v1/endpoint
├── registrarUrlWebhook(url, headers)      → PUT /autorizador/v1/endpoint (header X-Webhook-Secret)
├── removerUrlWebhook()                    → DELETE /autorizador/v1/endpoint/{id}
├── indicarRealInfrator(dados)             → POST /transacional/v1/realinfrator/indicacoes/inserir
├── cancelarRealInfrator(chave, dados)     → POST /transacional/v1/realinfrator/indicacoes/{ch}/cancelar
├── statusRealInfrator(chave)              → GET /transacional/v1/realinfrator/indicacoes/{ch}/status
├── documentoAssinadoRI(chave, co, na, ci) → GET /transacional/v1/realinfrator/.../documentoAssinado
├── historicoRealInfrator(co, na, ci)      → GET /transacional/v1/realinfrator/indicacoes/historico/...
├── indicarPrincipalCondutor(dados)        → POST /transacional/v1/principalcondutor/indicacoes/inserir
├── excluirPrincipalCondutor(dados)        → POST /transacional/v1/principalcondutor/indicacoes/excluir
├── statusPrincipalCondutor(chave)         → GET /transacional/v1/principalcondutor/indicacoes/status
├── historicoPrincipalCondutor(params)     → GET /transacional/v1/principalcondutor/indicacoes/historico
│
├── (privados)
├── request(method, endpoint, body?)       → cURL com bearer token + rate limit
└── logConsulta(chave, tipo, placa, ...)   → Registra em serpro_consultas_log
```

### 7.2 SerproSaldoService

Gestao de saldo prepago e cobrancas.

```
app/Services/SerproSaldoService.php

Metodos:
├── getSaldo(chave)                        → Retorna saldo atual do tenant
├── temSaldoSuficiente(chave, valor)       → Verifica se saldo >= valor
├── debitar(chave, tipo, valor, ref, desc) → Debita saldo + registra transacao
├── creditar(chave, tipo, valor, extId)    → Credita saldo + registra transacao
├── calcularPrecoConsulta()                → ENV SERPRO_PRECO_CONSULTA * (1 + MARKUP/100)
├── calcularPrecoEvento()                  → ENV SERPRO_PRECO_EVENTO * (1 + MARKUP/100)
├── calcularPrecoIndicacao()               → ENV SERPRO_PRECO_INDICACAO * (1 + MARKUP/100)
├── verificarAutoRecarga(chave)            → Se saldo < limite, cobra Stripe automaticamente
├── gerarRecargaPix(chave, valor)          → InterGateway gera QR Code PIX
├── gerarRecargaStripe(chave, valor)       → StripeGateway cria PaymentIntent
├── confirmarRecargaPix(txid)              → Webhook PIX → credita saldo
├── confirmarRecargaStripe(piId)           → Webhook Stripe → credita saldo
├── salvarCartaoStripe(chave, pmId, cusId) → Salva dados do cartao para auto-recarga
├── ativarAutoRecarga(chave, pmId, cusId)  → Ativa auto-recarga + salva cartao
├── desativarAutoRecarga(chave)            → Desativa auto-recarga
└── listarTransacoes(chave, filtros)       → Historico paginado de transacoes
```

---

## 8. Interface - Central de Multas (Super Tela)

### 8.1 Layout Principal

```
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│                       CENTRAL DE MULTAS                                 │
│                                                                         │
│  ┌──────────┬──────────┬──────────┬──────────┬──────────────────────┐  │
│  │          │          │          │          │                      │  │
│  │  TOTAL   │ VENCIDAS │ A VENCER │ EM DIA   │  SALDO: R$ 87,50   │  │
│  │   76     │   23     │    8     │   45     │  [+ Recarregar]     │  │
│  │          │  R$4.200 │  R$1.500 │  R$8.100 │                      │  │
│  │  -----   │  -----   │  -----   │  -----   │  Auto-consulta: ON  │  │
│  │  R$13.8k │  vermelho│  amarelo │   verde  │  Eventos: ON        │  │
│  │          │          │          │          │  Ult. sync: 25/02   │  │
│  └──────────┴──────────┴──────────┴──────────┴──────────────────────┘  │
│                                                                         │
│  ┌─ ABAS ────────────────────────────────────────────────────────────┐ │
│  │                                                                    │ │
│  │  [Multas]  [Indicacoes]  [Consultas]  [Saldo]  [Configuracoes]   │ │
│  │                                                                    │ │
│  └────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### 8.2 Aba "Multas" (Padrao)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│  ┌─ ACOES RAPIDAS ───────────────────────────────────────────────────┐ │
│  │                                                                    │ │
│  │  [Buscar Multas Online]  [Buscar Todas as Placas]  [+ Nova Manual]│ │
│  │                                                                    │ │
│  └────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  ┌─ FILTROS ─────────────────────────────────────────────────────────┐ │
│  │                                                                    │ │
│  │  Buscar: [________________________]                                │ │
│  │                                                                    │ │
│  │  Placa: [_______]  Cliente: [_______________]                      │ │
│  │  Periodo: [__/__/____] a [__/__/____]                              │ │
│  │  Status: [Todos          v]  Origem: [Todos        v]             │ │
│  │  Pago: [Todos v]  Processamento: [Todos              v]          │ │
│  │                                                                    │ │
│  └────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  ┌─ TABELA ──────────────────────────────────────────────────────────┐ │
│  │                                                                    │ │
│  │  [ ] │ Placa   │ Infracao      │ Orgao    │ Valor  │ Venc.  │ Proc│ │
│  │  ────┼─────────┼───────────────┼──────────┼────────┼────────┼─────│ │
│  │  [ ] │ ABC1D23 │ Vel. 50% acima│ DETRAN-SP│ 293,47 │ 20/03  │ Novo│ │
│  │      │ (Online)│ AIT: SRPO-876 │          │        │  15d   │     │ │
│  │  ────┼─────────┼───────────────┼──────────┼────────┼────────┼─────│ │
│  │  [ ] │ XYZ9E87 │ Avancar sinal │ DER-RJ   │ 195,23 │ 25/03  │Indic│ │
│  │      │ (Manual)│               │          │        │  20d   │ado  │ │
│  │  ────┼─────────┼───────────────┼──────────┼────────┼────────┼─────│ │
│  │  [ ] │ QRS5F67 │ Estacionar    │ SMTR-SP  │  88,38 │ 10/02  │ ---│  │
│  │      │ (Evento)│ irregular     │          │        │ VENCIDA│     │ │
│  │                                                                    │ │
│  └────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  Acoes: [Selecionar] → [Indicar Real Infrator] [Principal Condutor]    │
│         [Baixar NA/NP] [Marcar Pago] [Editar] [Excluir]               │
│                                                                         │
│  Exibindo 1-20 de 76  [< 1 2 3 4 >]  [10|20|50] por pagina           │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### 8.3 Aba "Indicacoes"

```
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│  ┌─ RESUMO ──────────────────────────────────────────────────────────┐ │
│  │  Enviadas: 12  │  Aceitas: 8  │  Rejeitadas: 2  │  Pendentes: 2 │ │
│  └────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  ┌─ TABELA ──────────────────────────────────────────────────────────┐ │
│  │                                                                    │ │
│  │  Tipo    │ Placa   │ CPF Indicado  │ Status   │ Data     │ Acoes │ │
│  │  ────────┼─────────┼───────────────┼──────────┼──────────┼───────│ │
│  │  R.Infr. │ ABC1D23 │ ***456.789-** │ Aceita   │ 15/02/26 │ [Doc] │ │
│  │  P.Cond. │ XYZ9E87 │ ***123.456-** │ Pendente │ 20/02/26 │ [Sta] │ │
│  │  R.Infr. │ QRS5F67 │ ***789.012-** │ Rejeitada│ 10/02/26 │ [Det] │ │
│  │                                                                    │ │
│  └────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### 8.4 Aba "Saldo"

```
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│  ┌─ SALDO ATUAL ─────────────────────────────────────────────────────┐ │
│  │                                                                    │ │
│  │            R$ 87,50                                                │ │
│  │                                                                    │ │
│  │  ┌──────────────────────┐  ┌──────────────────────┐               │ │
│  │  │  Recarregar via PIX  │  │ Recarregar via Cartao│               │ │
│  │  │     (Banco Inter)    │  │     (Stripe)         │               │ │
│  │  └──────────────────────┘  └──────────────────────┘               │ │
│  │                                                                    │ │
│  │  Valor: R$ [100,00_________]  (minimo R$ 100,00)                  │ │
│  │                                                                    │ │
│  └────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  ┌─ AUTO-RECARGA ────────────────────────────────────────────────────┐ │
│  │                                                                    │ │
│  │  [ON/OFF]  Recarregar R$ 100,00 automaticamente quando            │ │
│  │            o saldo chegar a R$ 10,00                               │ │
│  │                                                                    │ │
│  │  Cartao salvo: **** **** **** 4242 (Visa)  [Trocar cartao]       │ │
│  │                                                                    │ │
│  └────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  ┌─ HISTORICO DE TRANSACOES ─────────────────────────────────────────┐ │
│  │                                                                    │ │
│  │  Filtro: [Todos v]  Periodo: [__/__] a [__/__]                    │ │
│  │                                                                    │ │
│  │  Data/Hora      │ Tipo           │ Descricao         │ Valor      │ │
│  │  ───────────────┼────────────────┼───────────────────┼────────────│ │
│  │  27/02 14:30    │ + Recarga PIX  │ Recarga manual    │ +R$ 100,00│ │
│  │  27/02 14:35    │ - Consulta     │ Placa ABC1D23     │ -R$   0,47│ │
│  │  27/02 14:35    │ - Consulta     │ Placa XYZ9E87     │ -R$   0,47│ │
│  │  26/02 08:00    │ - Evento       │ Nova multa detect.│ -R$   1,18│ │
│  │  25/02 10:00    │ + Recarga Card │ Auto-recarga      │ +R$ 100,00│ │
│  │  20/02 09:15    │ - Consulta     │ Consulta em lote  │ -R$   4,70│ │
│  │                                                                    │ │
│  │  * Valores incluem taxa de servico                                │ │
│  │                                                                    │ │
│  │  Exibindo 1-20 de 145  [< 1 2 3 ... >]                           │ │
│  │                                                                    │ │
│  └────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### 8.5 Aba "Configuracoes"

```
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│  ┌─ DADOS DA EMPRESA ────────────────────────────────────────────────┐ │
│  │                                                                    │ │
│  │  CNPJ da Consulta Online: [12.345.678/0001-90]                    │ │
│  │                                                                    │ │
│  └────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  ┌─ CONSULTA AUTOMATICA ─────────────────────────────────────────────┐ │
│  │                                                                    │ │
│  │  [ON/OFF]  Consultar multas automaticamente                       │ │
│  │                                                                    │ │
│  │  Intervalo: a cada [7] dias                                       │ │
│  │  Ultima consulta: 25/02/2026 08:00                                │ │
│  │  Proxima consulta: 04/03/2026 08:00                               │ │
│  │                                                                    │ │
│  │  Custo estimado por consulta: R$ 0,47 x [32 veiculos] = R$ 15,04│ │
│  │                                                                    │ │
│  └────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  ┌─ EVENTOS AUTOMATICOS ─────────────────────────────────────────────┐ │
│  │                                                                    │ │
│  │  [ON/OFF]  Receber notificacoes de multas automaticamente         │ │
│  │                                                                    │ │
│  │  Webhook: Registrado em 20/02/2026                                │ │
│  │  Custo por evento: R$ 1,18                                        │ │
│  │                                                                    │ │
│  └────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  ┌─ PRECOS ATUAIS ───────────────────────────────────────────────────┐ │
│  │                                                                    │ │
│  │  Consulta: R$ 0,47 por placa                                     │ │
│  │  Evento:   R$ 1,18 por notificacao                               │ │
│  │                                                                    │ │
│  │  * Valores incluem taxa de servico                                │ │
│  │                                                                    │ │
│  └────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 9. Funcionalidades para Gestao de Frota de Excelencia

### 9.1 Recursos Importantes para Locadoras

| Funcionalidade | Descricao | Prioridade |
|----------------|-----------|------------|
| **Dashboard KPIs** | Total multas, valor, vencidas, a vencer, por status | Alta |
| **Alertas de vencimento** | Multas vencendo nos proximos 7/15/30 dias | Alta |
| **Indicacao Real Infrator** | Transferir pontos para locatario que cometeu infracao | Alta |
| **Principal Condutor** | Registrar condutor principal para cada veiculo | Alta |
| **Download NA/NP** | Baixar notificacoes de autuacao e penalidade em PDF | Alta |
| **Ranking veiculos** | Veiculos com mais multas (identifica problematicos) | Media |
| **Ranking clientes** | Clientes com mais multas (perfil de risco) | Media |
| **Consulta CRLV** | Verificar documento do veiculo online | Media |
| **Roubo/Furto** | Checar se veiculo tem alerta de roubo | Media |
| **Recall** | Verificar recalls pendentes | Media |
| **Restricoes judiciais** | Verificar se veiculo tem bloqueio judicial | Media |
| **Historico completo** | Timeline de todas as acoes por multa | Media |
| **Exportar relatorio** | CSV/PDF do historico de multas | Media |
| **Acoes em lote** | Indicar infrator/pagar multiplas multas de uma vez | Baixa |
| **Notificacao por email/WA** | Avisar locatario sobre multa | Baixa |
| **Boleto SNE** | Pagamento direto via boleto (quando SERPRO liberar) | Futura |

### 9.2 KPIs do Dashboard

```
┌─────────────────────────────────────────────────┐
│             METRICAS PRINCIPAIS                  │
│                                                  │
│  Total de Multas      76  (+5 este mes)         │
│  Valor Total       R$ 13.800,00                  │
│  Multas Vencidas      23  (R$ 4.200)             │
│  Vencendo em 15d       8  (R$ 1.500)             │
│  Indicacoes Pendentes  2                         │
│  Veiculos Afetados    18  (de 32 na frota)      │
│                                                  │
│  Saldo Disponivel  R$ 87,50                     │
│  Consumo Mensal    R$ 47,00 (estimado)          │
│                                                  │
└─────────────────────────────────────────────────┘
```

---

## 10. Estrutura de Arquivos

### 10.1 Novos Arquivos

```
app/
├── Controllers/
│   ├── CentralMultasController.php        ← Super tela + dashboard
│   ├── SerproSaldoController.php          ← Saldo, recargas, transacoes
│   ├── SerproConsultaController.php       ← Consultas SERPRO + config
│   ├── SerproIndicacaoController.php      ← Indicacoes real infrator / P.C.
│   └── SerproWebhookController.php        ← Webhooks (SERPRO, PIX, Stripe)
│
├── Models/
│   ├── SerproConfiguracao.php
│   ├── SerproSaldo.php
│   ├── SerproTransacao.php
│   ├── SerproConsultaLog.php
│   └── SerproIndicacao.php
│
├── Services/
│   ├── SerproService.php                  ← Comunicacao com API SERPRO
│   └── SerproSaldoService.php             ← Gestao de saldo e cobrancas
│
├── Jobs/
│   └── SerproAutoConsultaJob.php          ← CRON auto-consulta
│
├── Views/pages/multas/
│   ├── central.php                        ← Tela principal
│   ├── saldo.php                          ← Aba de saldo
│   └── indicacao.php                      ← Formulario de indicacao
│
└── Database/migrations/
    ├── 00261_create_serpro_configuracoes.php
    ├── 00262_create_serpro_saldo.php
    ├── 00263_create_serpro_transacoes.php
    ├── 00264_create_serpro_consultas_log.php
    ├── 00265_create_serpro_indicacoes.php
    └── 00266_alter_multas_add_serpro_fields.php
```

### 10.2 Arquivos Modificados

```
app/Routes/web.php              ← Novas rotas (paginas, API, webhooks)
.env.example                    ← Novas variaveis SERPRO
app/Views/layouts/app.php       ← Novos modais globais (se necessario)
```

---

## 11. Permissoes (RBAC)

Novas permissoes a serem criadas:

| Permissao | Descricao |
|-----------|-----------|
| `multas.central.visualizar` | Ver a central de multas |
| `multas.serpro.consultar` | Executar consultas SERPRO |
| `multas.serpro.indicar` | Indicar real infrator / principal condutor |
| `multas.serpro.saldo` | Ver e gerenciar saldo |
| `multas.serpro.configurar` | Alterar configuracoes (auto-consulta, eventos) |

---

## 12. Seguranca

### 12.1 Webhook SERPRO
- Validacao por header `X-Webhook-Secret: {SERPRO_WEBHOOK_SECRET}`
- Compatibilidade temporaria com header legado `Authorization: Bearer {SERPRO_WEBHOOK_SECRET}`
- Sem CSRF (endpoint publico)
- Rate limit
- Log de todas as requisicoes recebidas

### 12.2 Webhook PIX/Stripe
- PIX: Validacao por certificado mTLS do Banco Inter
- Stripe: Validacao por `stripe-signature` header com `STRIPE_WEBHOOK_SECRET`
- Idempotencia: verificar `external_id` antes de creditar

### 12.3 Saldo
- Operacoes de saldo dentro de transaction MySQL para evitar race conditions
- `SELECT ... FOR UPDATE` no registro de saldo antes de debitar
- Log completo de todas as movimentacoes

### 12.4 Dados Sensiveis
- CPF parcialmente mascarado na interface (`***456.789-**`)
- Logs de API nao expoem dados pessoais ao tenant
- Bearer token SERPRO apenas no backend (ENV) e somente para homologacao
- Senha do certificado SERPRO apenas no backend (ENV)

---

## 13. Fases de Implementacao

### Fase 1 - Infraestrutura Base
- [x] Especificacao (este documento)
- [ ] Migracoes de banco (00261 a 00266)
- [ ] Models (SerproConfiguracao, SerproSaldo, SerproTransacao, SerproConsultaLog, SerproIndicacao)
- [ ] SerproService (comunicacao HTTP com SERPRO)
- [ ] SerproSaldoService (gestao de saldo)
- [ ] ENVs novas no .env.example

### Fase 2 - Saldo Prepago e Recargas
- [ ] SerproSaldoController
- [ ] Recarga PIX (InterGateway)
- [ ] Recarga Stripe (StripeGateway)
- [ ] Webhooks PIX e Stripe
- [ ] Auto-recarga Stripe
- [ ] Tela de saldo (Views)

### Fase 3 - Consultas SERPRO e Sincronizacao
- [ ] SerproConsultaController
- [ ] Consulta manual por placa
- [ ] Consulta em lote (todos veiculos)
- [ ] Download NA/NP PDFs
- [ ] Consulta CRLV
- [x] SerproWebhookController (eventos SERPRO)
- [ ] SerproAutoConsultaJob (CRON)
- [x] Configuracoes (auto-consulta, auto-eventos)

### Fase 4 - Real Infrator e Principal Condutor
- [ ] SerproIndicacaoController
- [ ] Indicacao de real infrator
- [ ] Indicacao de principal condutor
- [ ] Consulta de status
- [ ] Historico de indicacoes
- [ ] Tela de indicacao (Views)

### Fase 5 - Central de Multas (Super Tela)
- [ ] CentralMultasController
- [ ] Dashboard com KPIs
- [ ] Aba Multas (listagem + filtros avancados)
- [ ] Aba Indicacoes
- [ ] Aba Consultas (log)
- [ ] Aba Saldo
- [ ] Aba Configuracoes
- [ ] Acoes em lote
- [ ] Ranking veiculos/clientes

---

## 14. Testes e Validacao

### 14.1 Ambiente de Homologacao SERPRO

```
URL Base:       https://hom-efrotas.np.estaleiro.serpro.gov.br/efrotas/api
URL Transac.:   https://hom-efrotas.np.estaleiro.serpro.gov.br/efrotas/api/transacional
CNPJ Teste:     33683111000107
Placas Teste:   SAV0741, SAV0742, SAV0743, SAV0744, SAV0745,
                SAV0746, SAV0747, SAV0748, SAV0749, SAV0750
```

### 14.2 Cenarios de Teste

| Cenario | Esperado |
|---------|----------|
| Consultar multas placa SAV0741 | Retorna infracoes, debita saldo |
| Consultar com saldo zero | Erro "Saldo insuficiente" |
| Recarregar PIX R$100 | QR Code gerado, apos pagar saldo creditado |
| Recarregar Stripe R$100 | Pagamento processado, saldo creditado |
| Auto-recarga (saldo < R$10) | Stripe cobra R$100 automaticamente |
| Indicar real infrator | Indicacao enviada a SERPRO, status "enviado" |
| Webhook SERPRO (nova multa) | Multa criada no sistema, saldo debitado |
| Webhook com token invalido | Rejeitado com 401 |
| Consulta em lote (10 veiculos) | 10 consultas, 10 debitos |
| CRON auto-consulta | Consulta todos veiculos do tenant, debita cada |

---

> Documento gerado em 27/02/2026
> Base legal: Portaria SENATRAN No 461/2025
> API de consultas online v1
