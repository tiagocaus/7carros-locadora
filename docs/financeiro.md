# Modulo Financeiro

Sistema de gerenciamento de lancamentos financeiros (contas a pagar e a receber) com estrutura de Fatura + Itens.

## Visao Geral

O modulo Financeiro gerencia lancamentos do tipo:
- **Receitas (R)**: Contas a receber de clientes
- **Despesas (D)**: Contas a pagar para fornecedores

Cada lancamento pode conter multiplos itens, permitindo detalhamento por veiculo, plano de conta, etc.

## Estrutura das Tabelas

### Tabela `financeiro` (Fatura/Cabecalho)

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | INT UNSIGNED | Chave primaria |
| chave | VARCHAR(45) | Identificador do tenant |
| tipo | CHAR(1) | 'R' = Receita, 'D' = Despesa |
| documento | VARCHAR(100) | Numero do documento/NF |
| descricao | VARCHAR(500) | Descricao do lancamento |
| id_cliente | INT UNSIGNED | FK para clientes (quando tipo='R') |
| id_fornecedor | INT UNSIGNED | FK para fornecedores (quando tipo='D') |
| id_forma_pagamento | INT UNSIGNED | FK para formas_de_pagamentos |
| id_gateway | INT UNSIGNED NULL | Gateway efetivamente usado; dimensao operacional |
| id_conta | INT UNSIGNED | FK para contas |
| id_contrato | INT UNSIGNED | FK para contratos (quando origem e contrato) |
| id_locacao | INT UNSIGNED | FK para locacoes (quando origem e locacao) |
| id_veiculo | INT UNSIGNED | FK para veiculos (veiculo ativo no momento da criacao) |
| data_venci | DATE | Data de vencimento |
| data_pago | DATE | Data do pagamento (quando pago) |
| valor_subtotal | DECIMAL(15,2) | Cache de SUM(financeiro_itens.valor). Mantido automaticamente pelos triggers — nao setar manualmente quando ha itens. Excecao: parcelas filhas (sem itens proprios) recebem o valor diretamente |
| juros | DECIMAL(15,2) | Valor de juros |
| multa | DECIMAL(15,2) | Valor de multa |
| desconto | DECIMAL(15,2) | Valor de desconto |
| valor_total | DECIMAL(15,2) | valor_subtotal + juros + multa - desconto |
| valor_taxa | DECIMAL(10,2) NOT NULL DEFAULT 0.00 | Taxa retida pela operadora nesta parcela |
| valor_liquido | DECIMAL(15,2) GENERATED STORED | valor_total - valor_taxa (auto-calculado pelo MySQL) |
| taxa_percentual_snapshot | DECIMAL(5,2) NULL | Snapshot da taxa % da forma de pagamento no momento da criacao |
| taxa_fixa_snapshot | DECIMAL(10,2) NULL | Snapshot da taxa fixa total no momento da criacao |
| taxa_fixa_parcela_snapshot | DECIMAL(10,2) NULL | Snapshot da taxa fixa por parcela no momento da criacao |
| pago | CHAR(1) | 'S' = Sim, 'N' = Nao |
| parcela | INT | Numero da parcela |
| total_parcelas | INT | Total de parcelas |
| id_financeiro_origem | INT | FK para lancamento origem (parcelamento) |
| id_financeiro_taxa_origem | INT NULL | Receita que originou uma despesa automatica de taxa |
| observacoes | TEXT | Observacoes adicionais |
| created_at | DATETIME | Data de criacao |
| updated_at | DATETIME | Data de atualizacao |

### Tabela `financeiro_itens` (Itens/Detalhamento)

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | INT UNSIGNED | Chave primaria |
| chave | VARCHAR(45) | Identificador do tenant |
| id_financeiro | INT UNSIGNED | FK para financeiro |
| id_veiculo | INT UNSIGNED | FK para veiculos (opcional) |
| id_plano_de_conta | INT UNSIGNED | FK para planos_de_contas |
| descricao | VARCHAR(500) | Descricao do item |
| valor | DECIMAL(15,2) | Valor do item |
| ordem | INT UNSIGNED | Ordem de exibicao |
| created_at | DATETIME | Data de criacao |
| updated_at | DATETIME | Data de atualizacao |

### Relacionamentos

```
financeiro 1:N financeiro_itens (CASCADE DELETE)
financeiro N:1 clientes
financeiro N:1 fornecedores
financeiro N:1 formas_de_pagamentos
financeiro N:1 contas
financeiro N:1 contratos (SET NULL on delete)
financeiro N:1 locacoes (SET NULL on delete)
financeiro N:1 veiculos (SET NULL on delete)
financeiro 1:0..1 financeiro (receita -> despesa de taxa, CASCADE DELETE)
financeiro_itens N:1 veiculos
financeiro_itens N:1 planos_de_contas
```

## Taxas de meios de pagamento

`valor_total` continua representando a receita bruta e `valor_liquido` mostra
o valor apos a retencao. Quando uma receita com `valor_taxa > 0` recebe baixa,
o sistema tambem cria uma despesa paga no mesmo valor. Isso preserva o Livro
Caixa no modelo bruto: entrada integral e saida da taxa.

A despesa usa o plano escolhido na forma de pagamento ou, por padrao,
`3.4.1.21 - Taxas de meios de pagamento`. Gateway e plano de contas sao
conceitos diferentes: o primeiro identifica o processador; o segundo classifica
a natureza contabil da despesa. O vinculo unico por origem garante idempotencia.

Baixas por tela financeira, lote, contrato, locacao e webhook usam a mesma
regra central. Baixa parcial gera apenas a taxa proporcional e estorno remove a
despesa. Em gateway, a taxa efetiva (`fee`, ou `amount - net_amount`) substitui
a estimativa configurada quando estiver disponivel.

### Retroativo de taxas

O script `scripts/backfill-taxas-meios-pagamento.php` contabiliza receitas
pagas anteriores a esta funcionalidade. O ambiente deve ser informado
explicitamente em producao; sem a flag, o padrao seguro e `development`.

```bash
# Previa: nao grava dados
php scripts/backfill-taxas-meios-pagamento.php --env=production

# Aplicacao apos validar a previa
php scripts/backfill-taxas-meios-pagamento.php --env=production --apply

# Restricao opcional a um tenant
php scripts/backfill-taxas-meios-pagamento.php --env=production --tenant=CHAVE
```

O script mostra ambiente e banco antes dos resultados, rejeita ambientes
diferentes de `development` e `production` e ignora receitas que ja possuem
despesa de taxa vinculada.

Na previa, uma receita aparece somente quando for do tipo `R`, estiver paga,
tiver `valor_taxa > 0` e ainda nao possuir uma despesa ligada por
`id_financeiro_taxa_origem`. Por isso, antes de usar `--apply`, valide a
quantidade e o total exibidos contra a consulta de auditoria do banco.

Cada receita e processada em transacao. Se ocorrer uma falha, o script informa
o ID financeiro, reverte integralmente aquele item e termina com codigo de erro.
Como o vinculo da despesa e unico, a execucao pode ser repetida com seguranca
apos a correcao da causa; os itens concluidos nao serao duplicados.

## Triggers Automaticos

O campo `valor_total` eh mantido automaticamente por triggers:

- `trg_financeiro_itens_after_insert`: Recalcula ao inserir item
- `trg_financeiro_itens_after_update`: Recalcula ao atualizar item
- `trg_financeiro_itens_after_delete`: Recalcula ao excluir item

Em producao, esses triggers devem ser criados/recriados conectado como
`7carros_locador@localhost`, sem `CREATE DEFINER=...` explicito no SQL. Isso
mantem o `DEFINER` no padrao documentado em `docs/database.md` e evita erros do
tipo "The user specified as a definer ... does not exist" ao atualizar
lancamentos.

Formula do trigger:
```sql
valor_total = (
    SELECT COALESCE(SUM(valor), 0) FROM financeiro_itens
    WHERE id_financeiro = NEW.id_financeiro
) + COALESCE(juros, 0) + COALESCE(multa, 0) - COALESCE(desconto, 0)
```

**Nota sobre `valor_liquido`:** Como eh uma coluna GENERATED STORED (`valor_total - valor_taxa`), o MySQL recalcula automaticamente quando `valor_total` ou `valor_taxa` mudam. Os triggers de `financeiro_itens` NAO precisam se preocupar com `valor_liquido`.

## API Endpoints

### Listagem
```
GET /api/financeiro
Params: page, perPage, search
Response: { success: true, data: [...], pagination: {...} }
```

### Buscar por ID
```
GET /api/financeiro/{id}
Response: { success: true, data: { ...financeiro, itens: [...] } }
```

### Faturas por Veículo
```
GET /api/veiculos/{id}/faturas
Response: { success: true, data: [...faturas] }
```

Lista faturas a receber (`tipo='R'`) e a pagar (`tipo='D'`) vinculadas ao veículo
pela coluna `financeiro.id_veiculo` ou por itens em `financeiro_itens.id_veiculo`.
O endpoint é usado pela aba **Faturas** da tela de edição do veículo e respeita
tenant e acesso por filial. A listagem usa `data_venci` e `data_pago`; o schema
atual não possui `data_emissao`.

### Criar
```
POST /financeiro/salvar
Body: { tipo, descricao, id_cliente, id_fornecedor, ..., itens: [...] }
Response: { success: true, id: 123, message: "..." }
```

### Parcelas Geradas por Contratos

Quando o financeiro e gerado a partir de contrato, o comando de parcelas define
como a fatura sera dividida e quais serao os vencimentos. Comandos simples de dia
da semana (`Seg`, `Ter`, `Qua`, etc.) nao parcelam: eles geram uma unica parcela
com vencimento ajustado para o dia informado. Parcelamento semanal deve ser
explicito com `wX` ou `wX-Dia`, por exemplo `w4-Seg`.

Na autorenovacao, as parcelas financeiras precisam ser criadas ou confirmadas
como ja existentes antes de o contrato avancar para a proxima `data_renovacao`.
Esse fluxo evita contrato renovado sem boleto/fatura correspondente.

### Atualizar
```
POST /financeiro/{id}/atualizar
Body: { tipo, descricao, ..., itens: [...] }
Response: { success: true, message: "..." }
```

### Excluir
```
POST /financeiro/{id}/excluir
Response: { success: true, message: "..." }
```

### Link de Pagamento e Alteracoes de Valor

Links publicos de pagamento sao estaveis: o mesmo `/pagar/{codigo}` deve
continuar valido enquanto a receita estiver pendente. `pagamentos_links.valor`
e apenas um snapshot/cache para exibicao e deve ser sincronizado com
`financeiro.valor_total` antes de exibir ou processar o pagamento.

Campos que disparam a invalidacao quando mudam de fato:

- `valor_subtotal`, `valor_total`, `juros`, `multa`, `desconto`
- `data_venci`
- `id_forma_pagamento`
- `id_cliente`
- soma dos `itens`
- `pago` quando altera o status do lancamento

O fluxo usa `PagamentoLinkSyncService` para:

1. localizar o link publico reaproveitavel do lancamento;
2. sincronizar valor, cliente, descricao, vencimento do link e status `pending`;
3. localizar transacoes `charge` abertas do lancamento;
4. cancelar a cobranca externa via gateway quando existir `external_id` e os
   dados da fatura tiverem mudado;
5. manter o mesmo codigo publico para que mensagens ja enviadas continuem
   abrindo a fatura atualizada.

### Datas de Vencimento, Juros e Cobrancas Automaticas

Regras financeiras baseadas em vencimento usam a data de negocio do tenant,
calculada pelo `DateHelper` com o contexto da `chave` ativo. CRONs de cobranca,
juros/multa, autorrenovacao e encargos nao devem usar `CURDATE()` ou `NOW()` do
MySQL para decidir se uma fatura venceu, vence amanha ou deve ser reenviada.

- `data_venci`, `data_pago` e periodos de cobranca usam `DATE` em `Y-m-d`.
- Comparacoes de vencimento devem receber `today()` /
  `DateHelper::todayForDatabase()` como parametro SQL.
- Janelas como D-1 e proximos vencimentos devem usar
  `DateHelper::addDaysForDatabase()`.
- Campos tecnicos como `last_sent_at`, `created_at`, `updated_at` e expiracao
  de link usam `DateHelper::systemNow()`/`now()` parametrizado.

Se o gateway informar que a cobranca antiga ja foi paga, o sistema nao cria nova
cobranca silenciosamente. O pagamento deve ser reconciliado pelo fluxo normal de
status/webhook antes de nova emissao. Se o gateway estiver indisponivel ou recusar
o cancelamento de uma cobranca ainda pagavel, a alteracao financeira ou o novo
pagamento online e bloqueado para evitar dois boletos/cobrancas validos para a
mesma fatura.

No CRON de juros/multa (`CalculateOverdueFeesJob`), esse caso conhecido de
cobranca ja paga no gateway e tratado como bloqueio parcial: os demais
lancamentos continuam sendo atualizados e o job pode finalizar com sucesso,
registrando os IDs bloqueados no resumo. Erros diferentes desse bloqueio seguem
marcando o job como falha.

`GET /api/financeiro/{id}/link-pagamento` deve reutilizar o link existente do
financeiro sempre que a fatura estiver pendente. Caso valor, vencimento, cliente,
juros, multa, desconto ou itens tenham mudado, o link e atualizado sem trocar o
codigo publico.

### Impressao de Faturas

```
GET /pages/financeiro/offcanvas-impressao?id={id}
GET /financeiro/{id}/imprimir/fatura
POST /financeiro/{id}/enviar
```

O PDF de fatura financeira atende receitas (`tipo='R'`) e despesas (`tipo='D'`).
Na secao de descricao, o PDF mantem o texto livre original e exibe abaixo um bloco
**Veiculo(s)** com placa, marca e modelo. O bloco combina o veiculo vinculado ao
lancamento (`financeiro.id_veiculo`) e os veiculos dos itens
(`financeiro_itens.id_veiculo`), um por linha e sem repetir placas equivalentes.
A presenca da placa no texto livre nao suprime os dados estruturados do veiculo.
Receitas exibem os dados do cliente; despesas exibem os dados do fornecedor. O
envio por e-mail, WhatsApp ou SMS continua restrito a receitas com cliente, pois
representa envio de cobranca. Despesas podem ser impressas em PDF, mas nao sao
enviadas ao fornecedor por esse fluxo.

### Parcelas

```
GET /api/financeiro/{id}/parcelas
Response: { success: true, data: [...parcelas], pagination: {...} }
```

```
POST /financeiro/parcelas/atualizar-lote
Body: { ids: [1,2,3], campos: { data_venci: "2025-04-01", pago: "S" } }
Response: { success: true, message: "X parcelas atualizadas" }
Permissao: financeiro.editar
```

```
POST /financeiro/parcelas/excluir-lote
Body: { ids: [2,3] }
Response: { success: true, message: "X parcelas excluidas" }
Permissao: financeiro.excluir
Nota: Nao permite excluir a parcela origem (id_financeiro_origem=NULL)
```

### Exclusao multipla de lancamentos

As telas **Lancamentos Financeiros** e **Editar cliente > Faturas** permitem
selecionar varios registros visiveis e exclui-los em uma unica confirmacao.

```
POST /financeiro/excluir-lote
Body: { ids: [10, 11, 12] }
Permissao: financeiro.excluir
```

O endpoint normaliza os IDs, aplica isolamento por tenant, valida o acesso a
filial e executa as mesmas verificacoes da exclusao individual. O processamento
e parcial: registros validos sao excluidos e os bloqueados retornam em
`data.ignorados`, com identificador e motivo. Lancamentos pagos seguem a mesma
regra da exclusao individual. Cada registro removido gera seu proprio log de
auditoria com os dados anteriores do lancamento.

### Selects (para formularios)
```
GET /api/financeiro/clientes
GET /api/financeiro/fornecedores
GET /api/financeiro/planos-de-contas
GET /api/financeiro/formas-pagamento
GET /api/financeiro/veiculos
GET /api/financeiro/contas
```

## Permissoes

| Permissao | Descricao |
|-----------|-----------|
| `financeiro.visualizar` | Visualizar listagem e detalhes |
| `financeiro.criar` | Criar novos lancamentos |
| `financeiro.editar` | Editar lancamentos existentes |
| `financeiro.excluir` | Excluir lancamentos |

## Integracao com Outras Telas

### Como adicionar lancamento financeiro de outra tela

Outras telas do sistema (contratos, OS, vendas, etc.) podem criar lancamentos financeiros programaticamente.

#### Exemplo: Criar lancamento a partir de um contrato

```php
use App\Models\Financeiro;
use App\Models\FinanceiroItem;

// Instanciar models
$financeiro = new Financeiro();
$financeiroItem = new FinanceiroItem();

// Dados do cabecalho
$dadosCabecalho = [
    'chave'              => $_SESSION['chave'],
    'tipo'               => 'R', // Receita
    'documento'          => 'CONTRATO-' . $contrato['numero'],
    'descricao'          => 'Locacao de veiculo - Contrato #' . $contrato['numero'],
    'id_cliente'         => $contrato['id_cliente'],
    'id_forma_pagamento' => $contrato['id_forma_pagamento'],
    'id_conta'           => $contrato['id_conta'],
    'data_emissao'       => date('Y-m-d'),
    'data_venci'         => $contrato['data_vencimento'],
    'valor_subtotal'    => 0, // Sera calculado pelos itens
    'pago'               => 'N',
];

// Criar cabecalho
// Nota: valor_taxa e snapshots sao calculados automaticamente pelo Model
// quando tipo='R' e id_forma_pagamento esta presente
$idFinanceiro = $financeiro->criar($dadosCabecalho);

// Adicionar itens
$itens = [
    [
        'id_veiculo'       => $contrato['id_veiculo'],
        'id_plano_de_conta' => 1, // Receita de Locacao
        'descricao'        => 'Diarias de locacao',
        'valor'            => $contrato['valor_locacao'],
    ],
    [
        'id_veiculo'       => $contrato['id_veiculo'],
        'id_plano_de_conta' => 2, // Seguro
        'descricao'        => 'Seguro do veiculo',
        'valor'            => $contrato['valor_seguro'],
    ],
];

// Salvar todos os itens de uma vez
$financeiroItem->salvarTodos($idFinanceiro, $_SESSION['chave'], $itens);

// Recalcular total (opcional - triggers ja fazem isso)
$financeiro->recalcularTotal($idFinanceiro);
```

#### Exemplo: Criar despesa para manutencao

```php
use App\Models\Financeiro;
use App\Models\FinanceiroItem;

$financeiro = new Financeiro();
$financeiroItem = new FinanceiroItem();

// Cabecalho da despesa
$dadosCabecalho = [
    'chave'              => $_SESSION['chave'],
    'tipo'               => 'D', // Despesa
    'documento'          => 'OS-' . $ordemServico['numero'],
    'descricao'          => 'Manutencao - OS #' . $ordemServico['numero'],
    'id_fornecedor'      => $ordemServico['id_fornecedor'],
    'id_forma_pagamento' => $ordemServico['id_forma_pagamento'],
    'id_conta'           => $ordemServico['id_conta'],
    'data_emissao'       => date('Y-m-d'),
    'data_venci'         => $ordemServico['data_vencimento'],
    'valor_subtotal'    => 0,
    'pago'               => 'N',
];

$idFinanceiro = $financeiro->criar($dadosCabecalho);

// Itens baseados nos servicos da OS
foreach ($ordemServico['servicos'] as $servico) {
    $financeiroItem->criar([
        'chave'             => $_SESSION['chave'],
        'id_financeiro'     => $idFinanceiro,
        'id_veiculo'        => $ordemServico['id_veiculo'],
        'id_plano_de_conta' => $servico['id_plano_de_conta'],
        'descricao'         => $servico['descricao'],
        'valor'             => $servico['valor'],
    ]);
}
```

#### Manutencoes: configuracao e parcelas geradas

Na aba **Financeiro** de Adicionar/Editar Manutencoes, a secao
**Configuracao do Lancamento** funciona apenas como modelo para gerar a lista de
parcelas. A ordem dos campos da configuracao e:

1. Conta bancaria
2. Forma de pagamento
3. Parcelas
4. 1o Vencimento
5. Intervalo (dias)
6. Pago?

Quando todos os campos estao preenchidos, o front gera a tabela **Parcelas
geradas**. Cada linha da tabela e editavel e define os dados finais que serao
salvos em `financeiro`: conta bancaria, forma de pagamento, vencimento, valor e
status pago/nao pago.

O backend deve tratar `parcelas_geradas` como fonte de verdade quando esse campo
for enviado. A soma dos valores das parcelas precisa bater com o total dos itens
selecionados da manutencao. Se uma parcela for marcada como paga, o lancamento e
criado com `pago = 'S'` e `data_pago` preenchida com a data operacional atual.

### Consultar lancamentos pendentes

```php
// Lancamentos pendentes de um cliente
$pendentesCliente = $financeiro->listarPendentesCliente($idCliente);

// Lancamentos pendentes de um fornecedor
$pendentesFornecedor = $financeiro->listarPendentesFornecedor($idFornecedor);
```

### Marcar como pago

```php
$financeiro->atualizar($idFinanceiro, [
    'pago'      => 'S',
    'data_pago' => date('Y-m-d'),
]);
```

### Pagamento parcial (baixa parcial)

O financeiro v1 nao possui tabela de baixas manuais nem status persistido de
`parcial`. Para manter os relatorios e integracoes coerentes com o modelo atual
(`financeiro.pago = S/N`), o pagamento parcial eh feito por **desdobramento da
fatura**.

Exemplo:

```
Fatura original pendente: R$ 500,00
Valor recebido:           R$ 200,00
Diferenca criada:         R$ 300,00
```

Resultado:

```
#123  Fatura original     R$ 200,00  pago = S
#456  Diferenca do #123   R$ 300,00  pago = N
```

Endpoint:

```
POST /financeiro/{id}/baixa-parcial
Body: {
  valor_pago: 200.00,
  data_pago: "2026-06-18",
  data_venci_diferenca: "2026-07-18"
}
Response: {
  success: true,
  data: {
    id_original: 123,
    id_diferenca: 456,
    valor_original: 500.00,
    valor_pago: 200.00,
    valor_diferenca: 300.00
  }
}
Permissao: financeiro.editar
```

Regras:

- Aceita somente lancamentos pendentes (`pago = N`).
- `valor_pago` deve ser maior que zero e menor que `valor_total`.
- A operacao ocorre em transacao atomica.
- O lancamento original eh marcado como pago pelo valor recebido.
- Um novo lancamento pendente eh criado para a diferenca, mantendo cliente,
  fornecedor/funcionario, conta, forma de pagamento, plano de contas, filial,
  contrato/locacao e veiculo quando existirem.
- Se houver itens em `financeiro_itens`, os itens sao rateados
  proporcionalmente entre o lancamento pago e a diferenca; o ultimo item absorve
  eventuais centavos.
- Links publicos de pagamento pendentes do lancamento original sao cancelados
  para evitar cobranca do valor antigo.
- Comissao de investidor, quando aplicavel, deve considerar somente a parte
  efetivamente marcada como paga.

## Arquivos do Modulo

```
app/
├── Controllers/
│   └── FinanceiroController.php
├── Models/
│   ├── Financeiro.php
│   └── FinanceiroItem.php
├── Views/
│   └── pages/
│       └── financeiro/
│           ├── index.php        # Listagem
│           └── adicionar.php    # Formulario criar/editar
├── Database/
│   └── migrations/
│       ├── 00108_prepare_financeiro_refactor.php
│       ├── 00109_create_financeiro_itens.php
│       ├── 00110_migrate_financeiro_to_itens.php
│       ├── 00111_add_financeiro_triggers.php
│       ├── 00112_add_financeiro_itens_fk.php
│       ├── 00113_add_parcela_fields.php
│       ├── 00277_add_taxa_columns_financeiro.php
│       ├── 00278_add_fee_columns_financeiro_transacoes.php
│       ├── 00287_add_id_veiculo_to_financeiro.php
│       └── 00288_backfill_financeiro_id_veiculo.php
└── Routes/
    └── web.php  # Rotas adicionadas
```

## Regras de Negocio

1. **Tipo obrigatorio**: Todo lancamento deve ser Receita (R) ou Despesa (D)
2. **Cliente/Fornecedor**: Receitas requerem cliente, Despesas requerem fornecedor
3. **Itens**: Minimo 1 item por lancamento
4. **Valor total**: Calculado automaticamente via triggers
5. **Multi-tenancy**: Todos os registros sao filtrados por `chave`
6. **Filiais**: Filtro opcional via FilialHelper quando aplicavel
7. **Auditoria**: Todas as operacoes sao registradas via trait Auditable

## Rastreabilidade Veicular

O campo `financeiro.id_veiculo` vincula cada registro financeiro ao veiculo que estava ativo no momento da criacao. Essencial para rastreabilidade em substituicoes veiculares.

### Como funciona

- Parcelas de **contratos** recebem automaticamente o `id_veiculo` ativo via `ContratoVeiculo::buscarAtivo()`
- Parcelas de **locacoes** recebem automaticamente o `id_veiculo` ativo via `LocacaoVeiculo::buscarAtivo()`
- Reservas de locacao podem ser apenas por grupo/categoria. Antes da saida, elas podem nao ter `id_veiculo`; nesse caso as parcelas geradas antes da alocacao do veiculo ficam sem rastreio veicular no cabecalho.
- `Financeiro::criarParcelas()` propaga o `id_veiculo` do registro base para as parcelas filhas
- Em locacoes, a geracao automatica cria parcelas do saldo restante da locacao, considerando o total final menos o total ja lancado

### Substituicao veicular

Quando um veiculo eh substituido durante um contrato/locacao:
- Parcelas **ja criadas** mantêm o `id_veiculo` do veiculo antigo (nao sao alteradas)
- Parcelas **criadas apos** a troca recebem o `id_veiculo` do novo veiculo

### Metodos que preenchem `id_veiculo`

| Metodo | Modelo | Descricao |
|--------|--------|-----------|
| `salvarParcelasContrato()` | Contrato | Gera parcelas de contrato com veiculo ativo |
| `adicionarParcelaAvulsa()` | Contrato | Parcela avulsa com veiculo ativo |
| `gerarParcelas()` | Locacao | Gera parcelas do saldo restante com veiculo ativo |
| `adicionarParcela()` | Locacao | Parcela avulsa com veiculo ativo |
| `criarParcelas()` | Financeiro | Propaga `id_veiculo` do registro base |

### Cobranca automatica de parcelas de contrato

Na criacao de um contrato novo, quando o front envia parcelas para `/api/contratos/{id}/gerar-parcelas` com `salvar=true` e `from_creation=true`, o backend salva as parcelas em `financeiro`, cria ou reutiliza o link em `pagamentos_links` para cada parcela e enfileira o template `payment_reminder` para email, WhatsApp e SMS. O envio e assincrono via `messages_queue`; indisponibilidade de um canal fica registrada no resumo da resposta e nao desfaz as parcelas.

As notificacoes de cliente exibem a identificacao da parcela sempre que
`financeiro.parcela > 0`: por exemplo, `Parcela 1 de 1` ou `Parcela 2 de 12`.
Quando `total_parcelas` nao estiver preenchido, exibem somente o numero da
parcela. Lancamentos com `parcela = 0` nao mostram esse dado. A regra vale para
envios por template, cobranca agrupada do CRON e envio manual de fatura, sem
alterar o assunto do email.

### Cobranca automatica por CRON

O cron `SendFinanceiroCobrancasJob` envia cobrancas de receitas pendentes com cliente:

- 1 dia antes do vencimento: template `payment_reminder`.
- Depois de vencida: template `overdue_notice`, no maximo uma vez a cada 7 dias por fatura/canal.
- Email e enviado quando o cliente possui email. WhatsApp e SMS dependem de telefone no cliente e conexao validada/conectada na filial.
- As faturas elegiveis sao agrupadas por tenant e cliente. Cada execucao enfileira no maximo uma mensagem por cliente/canal, com secoes separadas para faturas a vencer e vencidas.
- Todas as faturas do lote ficam vinculadas ao mesmo `message_id` em `financeiro_cobrancas_notificacoes`.
- Lotes com uma unica fatura preservam o template customizavel do tipo de aviso; lotes com duas ou mais usam o resumo consolidado.
- O controle de duplicidade fica em `financeiro_cobrancas_notificacoes`.

Antes de enfileirar a mensagem, o cron cria ou reutiliza o link publico da fatura por `PagamentoLinkSyncService`. O link segue estavel e reflete valor, vencimento, juros, multa e desconto atuais.

### Diferenca entre `financeiro.id_veiculo` e `financeiro_itens.id_veiculo`

- **`financeiro.id_veiculo`**: Nivel cabecalho. Usado para parcelas de contratos/locacoes (que nao criam itens em `financeiro_itens`). Preenchido automaticamente pelo sistema.
- **`financeiro_itens.id_veiculo`**: Nivel item. Usado para lancamentos financeiros manuais/avulsos (despesas de manutencao, multas, etc). Preenchido pelo usuario ao criar itens.

## Parcelamento

O modulo financeiro suporta parcelamento de lancamentos. A criacao de parcelas eh feita atomicamente junto com o lancamento principal via `POST /financeiro/salvar`.

### Vencimento em links de pagamento

Links publicos de pagamento usam `financeiro.data_venci` como fonte de verdade para gerar cobrancas em gateways. Ao processar pagamento online, a cobranca externa deve manter o vencimento original quando ele for hoje ou futuro. Se a fatura ja estiver vencida, a cobranca externa deve ser criada com vencimento na data de hoje.

### Juros e multa de parcelas vencidas

O cron `CalculateOverdueFeesJob` recalcula automaticamente, uma vez ao dia, `juros`, `multa` e `valor_total` de receitas pendentes vencidas. A fonte da regra eh a forma de pagamento vinculada ao lancamento (`financeiro.id_forma_pagamento`): `formas_pagamento.multa` define o percentual unico de multa e `formas_pagamento.juros_por_dia` define o percentual diario de juros.

### Estrutura de Dados

```
Parcela 1 (id=10, parcela=1, total_parcelas=3, id_financeiro_origem=NULL)  <- pai
Parcela 2 (id=11, parcela=2, total_parcelas=3, id_financeiro_origem=10)    <- filha
Parcela 3 (id=12, parcela=3, total_parcelas=3, id_financeiro_origem=10)    <- filha
```

- A **primeira parcela** eh o lancamento principal (pai), com `id_financeiro_origem = NULL`
- As demais parcelas apontam para a primeira via `id_financeiro_origem`
- Campos: `parcela` (numero), `total_parcelas` (total), `id_financeiro_origem` (FK)

### Fluxo de Criacao

1. Usuario preenche dados na aba "Dados Principais"
2. Na aba "Parcelamento", configura: numero de parcelas (2-48), data da 1a parcela, intervalo (dias/semanas/meses/anos)
3. Clica "Gerar Preview" - frontend calcula datas e valores (arredondamento na ultima parcela)
4. Ao salvar, o payload inclui array `parcelas[]` junto com os dados do lancamento
5. No Controller (`salvar()`), a 1a parcela eh o lancamento criado; as demais sao criadas via `Financeiro::criarParcelas()`
6. As sequencias financeiras sao reservadas em lote via `SequenciaHelper::proximasSequencias()` antes da criacao das parcelas, reduzindo locks em `matrizes_filiais` em parcelamentos grandes
7. Toda operacao ocorre em transacao atomica

### Fluxo de Edicao (Lote)

Na aba "Parcelamento" do modo editar:
- Grid com todas as parcelas (checkbox, numero, vencimento, valor, status, acoes)
- **Edicao em lote**: seleciona parcelas -> altera data de vencimento e/ou status de pagamento -> `POST /financeiro/parcelas/atualizar-lote`
- **Exclusao em lote**: seleciona parcelas -> confirma exclusao -> `POST /financeiro/parcelas/excluir-lote` (nao permite excluir parcela origem)
- **Edicao individual**: clica "Editar" na parcela -> carrega no formulario da aba Principal

### Metodos do Model

| Metodo | Descricao |
|--------|-----------|
| `listarParcelas(int $idOrigem)` | Lista todas as parcelas (pai + filhas) ordenadas por numero |
| `contarParcelas(int $idOrigem)` | Conta total de parcelas incluindo a origem |
| `criarParcelas(int $idOrigem, array $parcelas, array $dadosBase, ?array $sequenciasReservadas = null)` | Cria parcelas em lote herdando dados do pai |
| `atualizarParcelasLote(array $ids, array $campos, string $chave)` | Atualiza campos de multiplas parcelas |
| `excluirParcelasLote(array $ids, string $chave)` | Exclui parcelas (protege a origem) |
| `ehParcelaOrigem(int $id)` | Verifica se eh a parcela pai |
| `buscarIdOrigem(int $id)` | Busca o ID da parcela origem |
| `calcularTaxaParcela(array $formaPagamento, float $valorParcela, int $totalParcelas)` | Calcula taxa por parcela |

## Validacoes

### Cabecalho
- `tipo`: Obrigatorio, deve ser 'R' ou 'D'
- `descricao`: Obrigatorio, max 500 caracteres
- `id_cliente`: Obrigatorio se tipo='R'
- `id_fornecedor`: Obrigatorio se tipo='D'
- `data_venci`: Obrigatorio, formato Y-m-d

### Itens
- `valor`: Obrigatorio, deve ser > 0
- `descricao`: Opcional, max 500 caracteres
- `id_plano_de_conta`: Recomendado para classificacao contabil

### Plano de Contas - Bloqueio vs Caucao

Bloqueio e Caucao sao conceitos distintos com planos de contas separados:

| Hierarquia | Descricao | Tipo | Uso |
|------------|-----------|------|-----|
| 1.1.5 | Bloqueio | A | Grupo (pre-autorizacao no cartao) |
| 1.1.5.01 | Bloqueio entrada | A | **NAO usar** - bloqueio nao gera financeiro |
| 1.1.5.02 | Bloqueio saida | A | **NAO usar** - bloqueio nao gera financeiro |
| 1.1.6 | Caucao | A | Grupo (deposito de garantia real) |
| 1.1.6.01 | Caucao entrada | A | Recebimento do deposito (tipo R) |
| 1.1.6.02 | Caucao saida | A | Devolucao do deposito (tipo D) |
| 3.4.1.22 | Devolucao/Reembolso de locacao | D | Credito ao cliente por devolucao antecipada ou reducao do total final da locacao |

**Bloqueio** = authorization hold no cartao via Stripe. NAO gera lancamento financeiro. Registrado em `locacoes_bloqueios`.
**Caucao** = deposito real. Gera lancamento financeiro com plano 1.1.6.01 (entrada) e 1.1.6.02 (saida na devolucao).

### Devolucao/Reembolso de Locacao

Quando uma locacao e fechada com total final menor que as receitas ja lancadas,
o modulo de locacoes pode criar uma fatura de devolucao:

- `financeiro.tipo = D`
- `financeiro.id_locacao` preenchido
- `financeiro.id_cliente` preenchido
- `financeiro.id_plano_de_conta` apontando para a hierarquia `3.4.1.22`
- `pago = N` por padrao, para posterior baixa no financeiro

Esse lancamento compensa o saldo financeiro efetivo da locacao, mas nao altera nem
remove a receita original. Devolucoes de caucao continuam usando `1.1.6.02` e nao
devem ser tratadas como credito de diaria/taxa de locacao.

### Valores Cobrados na Devolucao de Contrato

Quando a devolucao de um contrato apura valor que o cliente deve pagar (km
excedente, combustivel/carga ou taxas e servicos extras), o sistema deve criar
automaticamente uma receita:

- `financeiro.tipo = R`
- `financeiro.id_contrato` preenchido
- `financeiro.id_cliente` preenchido a partir do contrato
- `financeiro.id_veiculo` preenchido apenas quando a devolucao envolver um unico veiculo
- `pago = S` somente quando o usuario marcar o recebimento na tela

Nao registrar esse caso como contas a pagar (`tipo = D`), pois e uma cobranca ao
cliente.

Na tela de devolucao de contrato, o total da locacao pode aparecer no resumo para
conferencia com o cliente, mas o financeiro novo da devolucao continua limitado
aos adicionais apurados naquele ato: km, combustivel/carga e taxas extras. Nao
some novamente `contratos.total_pagar` ao lancamento da devolucao, para evitar
cobranca duplicada da locacao.

### Multas

Multas cadastradas manualmente geram um lançamento financeiro vinculado por
`financeiro.id_multa` e `multas.id_financeiro`. O campo `multas.pagador` define
o sentido financeiro:

- `cliente`: receita (`financeiro.tipo='R'`) com `financeiro.id_cliente`.
- `empresa`: despesa (`financeiro.tipo='D'`) sem cliente/fornecedor.

### Manutencoes e cliente pagador

Na tela de nova/edicao de manutencao, o campo **Cliente responsavel pelo
pagamento** define o sentido dos novos lancamentos financeiros:

- com cliente: receita/conta a receber vinculada ao cliente;
- sem cliente: despesa da empresa.

Ao definir, trocar ou remover o cliente pagador, o usuario deve confirmar o
aviso antes de salvar. Se a manutencao ja possuir lancamentos financeiros, eles
nao sao alterados retroativamente; a nova definicao vale apenas para
lancamentos criados depois da alteracao. A auditoria registra a mudanca do
cliente e a confirmacao do aviso junto com os demais campos alterados.

## Migracao de Dados Legados

As migrations 00108-00112 cuidam da migracao dos dados existentes:

1. **00108**: Prepara estrutura (backup, renomeia colunas)
2. **00109**: Cria tabela `financeiro_itens`
3. **00110**: Migra dados existentes para itens (lotes de 5000)
4. **00111**: Cria triggers de sincronizacao
5. **00112**: Adiciona Foreign Keys

### Verificacao pos-migracao

```sql
-- Verificar 1:1 entre documentos e itens
SELECT
    (SELECT COUNT(*) FROM financeiro WHERE chave = '...') as docs,
    (SELECT COUNT(*) FROM financeiro_itens WHERE chave = '...') as itens;

-- Verificar valores migrados
SELECT COUNT(*) FROM financeiro f
JOIN financeiro_itens fi ON fi.id_financeiro = f.id
WHERE ABS(f.valor_subtotal - fi.valor) > 0.01;

-- Verificar calculo de valor_total
SELECT COUNT(*) FROM financeiro
WHERE ABS(valor_total - (valor_subtotal + COALESCE(juros,0) + COALESCE(multa,0) - COALESCE(desconto,0))) > 0.01;
```

### Rollback

```bash
php migrate.php rollback 00112
php migrate.php rollback 00111
php migrate.php rollback 00110
php migrate.php rollback 00109
php migrate.php rollback 00108  # Restaura do backup
```

## Padrao de Datas

Vencimentos, baixas, parcelas, juros/multa por atraso, links de pagamento, promissorias, caucao e relatorios financeiros devem usar `DateHelper`/helpers globais. Nao use `date()`, `time()`, `new DateTime()`, `new Date()` ou `NOW()/CURDATE()` diretamente em regra de negocio; calcule a data no helper e passe como parametro. SQL legado com agregacoes complexas deve ser migrado em etapa dedicada conforme [date.md](./date.md).

Na listagem agrupada de promissorias, a coluna **Vencimento** exibe `proximo_vencimento`: a menor `data_vencimento` entre as parcelas ainda pendentes. Promissorias quitadas ou sem parcela pendente exibem `-`. A API ja calcula esse campo em `Promissoria::listarAgrupado()` e a interface deve formata-lo com `DateHelper` conforme o locale do tenant.

Na inclusao e edicao de parcelas, o payload canonico usa `valor_parcela` e `data_vencimento`. O Controller normaliza o valor com `currency_parse()` e aceita `valor` apenas como compatibilidade temporaria. O formulario deve ser aberto pelo modal global `openPromissoriaParcelaModal`, conforme [modals.md](./modals.md).

Na coluna **Parcelas** da listagem de promissorias, o formato e `pagas/total`. Para registros atuais, ambos os valores sao calculados pelas linhas agrupadas no mesmo `codigo_base`. Registros legados isolados usam `total_parcelas` como denominador e calculam as parcelas pagas conhecidas pelo mesmo tenant, cliente, filial, instante de criacao e total declarado, sem alterar ou consolidar os dados historicos.
