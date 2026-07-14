# Formas de Pagamento

## Visao Geral

O modulo de Formas de Pagamento permite configurar diferentes metodos de pagamento com suporte a:
- **Formas de pagamento** (Boleto, Cartao, PIX, Dinheiro, etc.)
- **Comandos de parcelas** (tabela independente para definir parcelamento)
- **Taxas de cobranca** (fixa, por parcela, percentual)
- **Desconto por antecipacao** (pagamento antes do vencimento)
- **Multa e juros** por atraso

## Arquitetura

O sistema separa **formas de pagamento** (configuracoes de taxas/descontos) de **comandos de parcelas** (regras de parcelamento):

| Conceito | Exemplo | Tabela |
|----------|---------|--------|
| **Forma** | Boleto, Cartao de Credito, PIX | `formas_pagamento` |
| **Comando** | 0 (a vista), 1-12 (mensal), w36 (semanal) | `formas_pagamento_comandos` |

Esta separacao permite:
- 1 forma por tipo de pagamento por tenant (sem duplicatas)
- Comandos de parcelas reutilizaveis entre formas
- Selecao independente de forma + comando no contrato
- Comandos padrao do sistema (chave=0) + personalizados por tenant

---

## Campos da Tabela `formas_pagamento`

| Campo | Tipo | Descricao |
|-------|------|-----------|
| id | INT | Identificador unico |
| chave | VARCHAR(45) | Chave do tenant |
| nome | VARCHAR(100) | Nome da forma (Boleto, PIX, Cartao, etc.) |
| lancar_pago | VARCHAR(1) | S = Lancar automaticamente como pago |
| onde_exibir | VARCHAR(50) | Onde exibir (1=Site, 2=Sistema, 3=Aplicativo). Multiplos valores separados por virgula |
| status | VARCHAR(1) | A = Ativo, I = Inativo |
| multa | DECIMAL(10,2) | Percentual de multa por atraso |
| juros_por_dia | DECIMAL(10,3) | Percentual de juros por dia de atraso |
| taxa_fixa | DECIMAL(10,2) | Taxa fixa total, diluida entre parcelas |
| taxa_fixa_parcela | DECIMAL(10,2) | Taxa fixa cobrada em cada parcela |
| taxa_percentual_parcela | DECIMAL(5,2) | Percentual cobrado sobre cada parcela |
| id_plano_de_conta_taxa | INT NULL | Conta de despesa da taxa; nulo usa o plano global 3.4.1.21 |
| desconto_antecipacao_dias | INT | Dias antes do vencimento para aplicar desconto |
| desconto_antecipacao_percentual | DECIMAL(5,2) | Percentual de desconto por antecipacao |

## Tabela `formas_pagamento_comandos`

Tabela de comandos de parcelas. Registros com `chave='0'` sao do sistema (nao editaveis pelo tenant). Tenant pode criar comandos personalizados com sua propria chave.

| Campo | Tipo | Descricao |
|-------|------|-----------|
| id | INT | Identificador unico |
| chave | VARCHAR(45) | '0' = sistema, ou chave do tenant |
| comando | VARCHAR(255) | String parseavel (ex: "0", "1-12", "w36") |
| descricao | TEXT | Descricao do comando |
| status | VARCHAR(1) | A = Ativo, I = Inativo |

### Comandos Padrao do Sistema (chave=0)

| # | comando | descricao |
|---|---------|-----------|
| 1 | `0` | Pagamento a vista, sem parcelamento |
| 2 | `15` | Pagamento unico para daqui a 15 dias |
| 3 | `1-12` | Parcelas mensais de 1 a 12 vezes |
| 4 | `7/14/21/28` | 4 parcelas com prazos estabelecidos (7, 14, 21 e 28 dias) |
| 5 | `Seg` | Vencimento toda Segunda-feira |
| 6 | `d15` | Vencimento todo dia 15 de cada mes |
| 7 | `w36` | 36 parcelas semanais |
| 8 | `w36-Seg` | 36 parcelas semanais com vencimento toda Segunda-feira |

## Relacionamento com Gateways de Pagamento

Uma forma de pagamento pode ser vinculada a um ou mais gateways de pagamento atraves da tabela `formas_pagamento_gateways`.

### Tabela `formas_pagamento_gateways`

| Campo | Tipo | Descricao |
|-------|------|-----------|
| id | INT | Identificador unico |
| id_forma_pagamento | INT | FK para formas_pagamento.id |
| id_gateway | INT | FK para gateways_pagamento.id |
| chave | VARCHAR(45) | Chave do tenant |
| created_at | TIMESTAMP | Data de criacao |

### Comportamento

- **Com gateway(s) vinculado(s):** A forma de pagamento aparece em todas as telas e permite processamento de pagamento online automatico
- **Sem gateway vinculado:** A forma de pagamento aparece em contratos, lancamentos, etc., mas nao processa pagamento online automatico (uso para dinheiro, transferencias manuais, etc.)

### Uso no Model

```php
$model = new FormaPagamento();

// Buscar gateways vinculados a uma forma de pagamento
$gateways = $model->buscarGateways($idFormaPagamento);

// Sincronizar gateways vinculados
$model->sincronizarGateways($idFormaPagamento, [1, 2, 3], $chave);
```

### Valores de `onde_exibir`

| Valor | Descricao |
|-------|-----------|
| 1 | Site |
| 2 | Sistema |
| 3 | Aplicativo |

**Exemplo:** `"1,2,3"` = exibir em Site, Sistema e Aplicativo

---

## Comandos de Parcelas

O campo `comando` em `formas_pagamento_comandos` aceita comandos especiais para definir como as parcelas serao geradas.

### Formatos Disponiveis

| Comando | Exemplo | Descricao |
|---------|---------|-----------|
| `0` | `0` | Pagamento a vista (sem parcelamento) |
| Numero inteiro | `15` | Pagamento unico para daqui a X dias |
| `X-Y` | `1-12` | Gera parcelas mensais de X ate Y (ex: 1x, 2x, 3x... 12x) |
| `X/Y/Z/...` | `7/14/21/28` | Prazos especificos em dias (gera N parcelas) |
| Dia da semana | `Seg` | Parcela unica com vencimento no dia da semana especificado |
| `dX` | `d5`, `d15` | Vencimento no dia X de cada mes (1-31) |
| `wX` | `w36` | X parcelas semanais |
| `wX-Dia` | `w36-Seg` | X parcelas semanais com vencimento no dia especificado |

### Detalhes dos Comandos

#### Pagamento a Vista (`0`)
- Gera uma unica parcela com vencimento imediato
- Usado para pagamentos em dinheiro, PIX, etc.

#### Prazo Fixo (numero inteiro)
- **Exemplo:** `15` = parcela unica vencendo em 15 dias
- **Exemplo:** `30` = parcela unica vencendo em 30 dias

#### Parcelas Mensais (`X-Y`)
- **Exemplo:** `1-12` = permite parcelar de 1x ate 12x
- **Exemplo:** `0-6` = permite a vista (0) ou ate 6x
- O primeiro numero deve ser menor que o segundo

#### Prazos Estabelecidos (`X/Y/Z/...`)
- **Exemplo:** `7/14/21/28` = 4 parcelas com vencimentos em 7, 14, 21 e 28 dias
- **Exemplo:** `30/60/90` = 3 parcelas com vencimentos em 30, 60 e 90 dias
- Cada numero representa dias a partir da data base

#### Dia da Semana (`Dom`, `Seg`, `Ter`, `Qua`, `Qui`, `Sex`, `Sab`)
- **Exemplo:** `Seg` = parcela unica com vencimento na proxima segunda-feira
- O comando deve conter apenas um dia da semana. `Seg,Qua,Sex` nao e um comando valido.
- Dia da semana simples define vencimento, nao parcelamento. Para parcelar semanalmente, use `wX` ou `wX-Dia`.
- Respeitar maiusculas/minusculas: `Seg` (nao `seg` ou `SEG`)

#### Dia do Mes (`dX`)
- **Exemplo:** `d5` = vencimento todo dia 5
- **Exemplo:** `d15` = vencimento todo dia 15
- Valores validos: d1 ate d31

#### Parcelas Semanais (`wX`)
- **Exemplo:** `w4` = 4 parcelas semanais
- **Exemplo:** `w52` = 52 parcelas semanais (1 ano)

#### Parcelas Semanais com Dia (`wX-Dia`)
- **Exemplo:** `w4-Seg` = 4 parcelas semanais, vencendo as segundas
- **Exemplo:** `w12-Sex` = 12 parcelas semanais, vencendo as sextas

### Validacao

O sistema valida o formato do comando antes de salvar. Formatos invalidos sao rejeitados com mensagem de erro.

### Inferencia de Label

O metodo `ComandoParcela::inferirLabel()` gera labels legiveis a partir do comando:

| Comando | Label Inferido |
|---------|----------------|
| `0` | a vista |
| `15` | 15 dias |
| `30/60/90` | 30/60/90 dias |
| `1-12` | ate 12x |
| `0-6` | ate 6x |
| `3-10` | 3x a 10x |
| `w4` | 4 semanas |
| `w36-Seg` | 36 semanas (Seg) |
| `d15` | dia 15 |
| `Seg` | Seg |

---

## Taxas de Cobranca

As taxas representam valores **retidos pelo meio de pagamento**. Elas nao sao
acrescidas ao valor cobrado do cliente. Deixe 0,00 para desativar.

### 1. Taxa Fixa Total (`taxa_fixa`)

Valor fixo que sera **diluido entre as parcelas**.

**Exemplo:**
- Taxa fixa: R$ 10,00
- Valor: R$ 200,00 em 2 parcelas
- Resultado: receita bruta de R$ 100,00 e despesa de R$ 5,00 por parcela paga

```
Taxa da parcela = taxa fixa / numero de parcelas
```

### 2. Taxa Fixa por Parcela (`taxa_fixa_parcela`)

Valor fixo cobrado **em cada parcela**, independente do valor.

**Exemplo:**
- Taxa por parcela: R$ 2,50
- Valor: R$ 200,00 em 4 parcelas
- Resultado: receita bruta de R$ 50,00 e despesa de R$ 2,50 por parcela paga
- Taxa total: R$ 10,00 (4 x R$ 2,50)

```
Taxa total = Taxa por parcela * Numero de parcelas
```

### 3. Taxa Percentual por Parcela (`taxa_percentual_parcela`)

Percentual cobrado **sobre o valor de cada parcela**.

**Exemplo:**
- Taxa: 5%
- Valor: R$ 200,00 em 2 parcelas (R$ 100,00 cada)
- Resultado: receita bruta de R$ 100,00 e despesa de R$ 5,00 por parcela paga
- Taxa total: R$ 10,00 (5% de R$ 100 x 2 parcelas)

```
Taxa parcela = Valor parcela * (Percentual / 100)
```

### Combinacao de Taxas

As taxas podem ser combinadas. Todas compoem a despesa retida pelo processador.

**Exemplo completo:**
- Valor: R$ 1.000,00 em 2 parcelas
- Taxa fixa total: R$ 20,00 (R$ 10,00 por parcela)
- Taxa fixa por parcela: R$ 3,00 (R$ 6,00 total)
- Taxa percentual: 2% (R$ 10,00 por parcela = R$ 20,00 total)

```
Taxa total = R$ 20,00 + R$ 6,00 + R$ 20,00 = R$ 46,00
Receita bruta = R$ 1.000,00
Despesa de taxas = R$ 46,00
Valor liquido = R$ 954,00
```

### Snapshot no Financeiro

Quando um lancamento financeiro do tipo receita (R) eh criado com `id_forma_pagamento`, o Model `Financeiro::criar()` calcula automaticamente a taxa da operadora e grava snapshots:

**Colunas gravadas em `financeiro`:**

| Coluna | Descricao |
|--------|-----------|
| `valor_taxa` | Taxa total retida pela operadora nesta parcela |
| `valor_liquido` | `valor_total - valor_taxa` (GENERATED STORED, sempre consistente) |
| `taxa_percentual_snapshot` | Valor de `taxa_percentual_parcela` no momento da criacao |
| `taxa_fixa_snapshot` | Valor de `taxa_fixa` no momento da criacao |
| `taxa_fixa_parcela_snapshot` | Valor de `taxa_fixa_parcela` no momento da criacao |

**Formula por parcela:**
```
valor_taxa = (taxa_fixa / totalParcelas) + taxa_fixa_parcela + (valorParcela * taxa_percentual / 100)
```

**Comportamento:**
- O calculo eh automatico e centralizado no Model — chamadores de `Financeiro::criar()` e `criarParcelas()` nao precisam calcular manualmente
- Snapshots servem para auditoria — taxas podem mudar no futuro, mas o registro mantém a config usada
- Se `valor_taxa` for passado explicitamente nos dados, o calculo automatico eh ignorado (override manual)
- Despesas (tipo='D') nunca tem taxa calculada — taxa so se aplica a receitas

### Contabilizacao na baixa

Ao marcar a receita como paga, `FinanceiroTaxaService` cria uma despesa paga,
na mesma data, conta bancaria, filial, contrato/locacao e veiculo. A despesa
fica ligada pela coluna `financeiro.id_financeiro_taxa_origem`; o indice unico
por tenant torna o processamento idempotente.

- O plano configurado em `id_plano_de_conta_taxa` tem precedencia.
- Sem configuracao, usa-se o plano global `3.4.1.21 - Taxas de meios de pagamento`.
- O gateway e gravado em `financeiro.id_gateway` como dimensao operacional; nao
  e necessario criar um plano de contas para cada gateway.
- Em pagamento online, a taxa efetiva informada pela transacao tem precedencia.
  Se ela nao existir, usa-se `amount - net_amount`; por ultimo, o snapshot
  estimado em `financeiro.valor_taxa`.
- Baixa parcial rateia a taxa proporcionalmente. Estorno exclui a despesa
  vinculada. Uma nova baixa recria a despesa sem duplicidade.
- A despesa automatica nao pode ser editada ou excluida manualmente.

---

## Desconto por Antecipacao

Oferece desconto para clientes que pagam antes do vencimento.

### Configuracao

| Campo | Descricao |
|-------|-----------|
| desconto_antecipacao_dias | Quantos dias antes do vencimento para aplicar o desconto |
| desconto_antecipacao_percentual | Percentual de desconto |

### Como Funciona

1. O sistema verifica se a data de pagamento e X dias antes do vencimento
2. Se for, aplica o desconto percentual sobre o valor da parcela

**Exemplo:**
- Dias: 5
- Desconto: 3%
- Parcela: R$ 100,00
- Vencimento: 20/01/2025

Se pagar ate 15/01/2025 (5 dias antes):
- Desconto: R$ 3,00 (3% de R$ 100,00)
- Valor a pagar: R$ 97,00

Se pagar depois de 15/01/2025:
- Valor a pagar: R$ 100,00 (sem desconto)

---

## Multa e Juros por Atraso

### Multa (`multa`)

Percentual aplicado uma unica vez quando ha atraso.

**Exemplo:**
- Multa: 2%
- Parcela: R$ 100,00
- Atraso: 10 dias
- Multa aplicada: R$ 2,00

### Juros por Dia (`juros_por_dia`)

Percentual aplicado por dia de atraso.

**Exemplo:**
- Juros: 0,033% ao dia (1% ao mes)
- Parcela: R$ 100,00
- Atraso: 10 dias
- Juros aplicados: R$ 0,33 (0,033% x 10 dias)

### Aplicacao no Financeiro

O cron `CalculateOverdueFeesJob` usa `multa` e `juros_por_dia` da forma de pagamento vinculada ao lancamento financeiro para recalcular receitas vencidas e pendentes. Lançamentos pagos, despesas, sem forma vinculada ou com ambos os campos zerados nao recebem encargos automaticos.

---

## API Endpoints

### Formas de Pagamento

```
GET /api/formas-pagamento                  # Lista paginada (page, perPage, search)
GET /api/formas-pagamento/select           # Lista simples para selects (id, nome)
GET /api/formas-pagamento/{id}             # Detalhes
GET /api/formas-pagamento/{id}/calcular-taxas    # Calculo de taxas (query: valor, parcelas)
GET /api/formas-pagamento/{id}/calcular-desconto # Desconto antecipacao (query: valor, data_vencimento, data_pagamento)
POST /formas-pagamento/salvar              # Criar
POST /formas-pagamento/{id}/atualizar      # Atualizar
POST /formas-pagamento/{id}/excluir        # Excluir
```

### Comandos de Parcelas

```
GET /api/comandos-parcelas                 # Lista paginada (page, perPage, search)
GET /api/comandos-parcelas/select          # Lista para selects (sistema + tenant, ativos)
GET /api/comandos-parcelas/{id}            # Detalhes
POST /comandos-parcelas/salvar             # Criar (apenas tenant)
POST /comandos-parcelas/{id}/atualizar     # Atualizar (apenas tenant, nao sistema)
POST /comandos-parcelas/{id}/excluir       # Excluir (apenas tenant, nao sistema)
```

**Resposta do select:**
```json
{
  "success": true,
  "data": [
    { "id": 1, "comando": "0", "descricao": "Pagamento a vista...", "origem": "sistema" },
    { "id": 9, "comando": "30/60/90", "descricao": "3 parcelas...", "origem": "personalizado" }
  ]
}
```

---

## Uso no Model

### FormaPagamento

```php
$model = new FormaPagamento();

// Listar para select
$formas = $model->listarParaSelect();

// Calcular taxas para R$ 1000,00 em 4 parcelas
$resultado = $model->calcularTaxas($idFormaPagamento, 1000.00, 4);

// Calcular desconto por antecipacao
$resultado = $model->calcularDescontoAntecipacao($id, 100.00, '2025-01-20', '2025-01-15');
```

### ComandoParcela

```php
$model = new ComandoParcela();

// Listar para select (sistema + tenant)
$comandos = $model->listarParaSelect();

// Buscar por ID
$comando = $model->buscarPorId($id);

// Parsing e calculo de datas (metodos estaticos)
$parsed = ComandoParcela::parseComando($comando['comando']);
$datas = ComandoParcela::calcularDatasVencimento($comando['comando'], $dataBase, $numParcelas);
$numParcelas = ComandoParcela::calcularNumParcelasAutomatico($comando['comando'], $dataInicio, $dataFim);
$label = ComandoParcela::inferirLabel($comando['comando']);
```

---

## Exemplos de Configuracao

### Cartao de Credito

| Campo | Valor |
|-------|-------|
| Nome | Cartao de Credito |
| Taxa percentual | 2.99% |
| Onde exibir | 1,2,3 (Site, Sistema, Aplicativo) |

### Boleto Bancario

| Campo | Valor |
|-------|-------|
| Nome | Boleto Bancario |
| Taxa fixa | R$ 3,50 |
| Desconto dias | 5 |
| Desconto % | 3% |
| Multa | 2% |
| Juros dia | 0.033% |
| Onde exibir | 1,2 (Site, Sistema) |

### PIX

| Campo | Valor |
|-------|-------|
| Nome | PIX |
| Lancar pago | Sim |
| Onde exibir | 1,2,3 (Site, Sistema, Aplicativo) |

### Dinheiro

| Campo | Valor |
|-------|-------|
| Nome | Dinheiro |
| Lancar pago | Sim |
| Onde exibir | 2 (Sistema) |

---

## Arquivos do Modulo

| Arquivo | Descricao |
|---------|-----------|
| `app/Models/FormaPagamento.php` | Model de formas de pagamento (taxas, descontos, filiais, gateways) |
| `app/Models/ComandoParcela.php` | Model de comandos de parcelas (parsing, calculo de datas) |
| `app/Controllers/FormasPagamentoController.php` | Controller CRUD (formas + comandos) |
| `app/Views/pages/formas-pagamento/index.php` | Listagem de formas |
| `app/Views/pages/formas-pagamento/adicionar.php` | Formulario de formas |
| `app/Views/pages/formas-pagamento/comandos.php` | CRUD de comandos de parcelas |
| `app/Database/migrations/00240_create_formas_pagamento_comandos.php` | Cria tabela + seed comandos padrao |
| `app/Database/migrations/00241_consolidate_formas_pagamento.php` | Consolida formas (1 por tipo) |
| `app/Database/migrations/00242_cleanup_formas_pagamento.php` | Remove colunas e tabela tipos |
| `app/Database/migrations/00243_add_comando_parcela_to_contratos.php` | Adiciona id_comando_parcela em contratos |

---

## Consideracoes Importantes

1. **Taxas sao cumulativas**: Se configurar taxa fixa + percentual, ambas serao aplicadas
2. **Desconto so aplica antes do vencimento**: Apos o vencimento, multa e juros sao aplicados
3. **Deixe 0 para desativar**: Qualquer taxa ou desconto com valor 0 sera ignorado
4. **Multi-tenant**: Cada empresa tem suas proprias formas de pagamento isoladas
5. **Comandos sistema vs tenant**: Comandos com chave=0 sao do sistema e nao podem ser editados/excluidos pelo tenant
6. **Selecao independente**: No contrato, forma de pagamento e comando de parcelas sao selecionados separadamente
7. **Persistencia no contrato**: O `id_comando_parcela` e salvo na tabela `contratos` para uso na renovacao automatica
