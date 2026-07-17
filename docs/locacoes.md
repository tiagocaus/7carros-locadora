# Modulo de Locacoes

Sistema de gestao de locacoes de veiculos de curta duracao (diarias).

## Visao Geral

O modulo de locacoes gerencia todo o ciclo de vida de uma locacao:
- Criacao de reservas por grupo/categoria, com veiculo especifico opcional, taxas e parcelas
- Registro de saida (entrega do veiculo ao cliente)
- Substituicao de veiculos durante a locacao
- Registro de devolucao (retorno do veiculo)
- Assinatura digital pelo cliente
- Impressao de documentos (fatura, documento, checklist, recibo)

## Estrutura de Arquivos

```
app/
├── Models/
│   ├── Locacao.php               # Model principal
│   ├── LocacaoVeiculo.php        # Veiculos da locacao (historico)
│   └── LocacaoTaxaServico.php    # Taxas e servicos
├── Controllers/
│   └── LocacoesController.php    # CRUD + acoes especiais
└── Views/
    └── pages/locacoes/
        ├── index.php              # Listagem
        ├── adicionar.php          # Formulario (criar/editar)
        ├── offcanvas-impressao.php # Painel de impressao
        └── imprimir/
            ├── fatura.php
            ├── documento.php
            ├── checklist.php
            ├── recibo.php
            └── combos (fatura_documento, fatura_checklist, etc.)
```

### PDF tipo `documento` (modelo customizado)

Mesmo padrão de contratos: header/footer HTML no `LocacoesController::imprimir` e margens centralizadas em `PdfHelper` ([pdf.md](./pdf.md)).

### PDF tipo `fatura`

A fatura da locação mantém a ordem principal: Dados do Cliente, Dados da Locação, Composição da Fatura, Totais e Pagamentos. Dados complementares aparecem depois dos pagamentos quando houver informação: condutor adicional, referências/intervenientes (fiadores, avalistas e testemunhas), histórico de veículos/substituições e multas vinculadas.

Em Dados do Cliente, a fatura exibe o endereço completo do cliente quando cadastrado. Em locações com plano KMC abertas ou em reserva, a linha do plano na Composição da Fatura informa a franquia por dia e o total de km permitido no período.

Na aba Resumo do formulario, locacoes com plano KMC e franquia maior que zero exibem a mesma informacao logo abaixo da diaria. O total permitido e recalculado dinamicamente pela formula `franquia diaria * dias da locacao`.

## Fluxo de Status

```
R (Reserva) ──registrarSaida()──> A (Aberto) ──registrarDevolucao()──> F (Fechado)
```

### Criacao de Reserva
- Reserva (`status = R`) e reserva pendente (`status = P`) **nao precisam selecionar veiculo especifico**, mas podem gravar um veiculo como preferencia operacional.
- A pratica operacional esperada e reservar o **grupo/categoria**; quando um veiculo especifico for selecionado na reserva, ele representa preferencia, nao bloqueio de disponibilidade.
- Reserva pode ser criada apenas com grupo/categoria (`id_grupo`), gravando `id_veiculo = NULL` em `locacoes_veiculos`, ou com `id_veiculo` preenchido como preferencia.
- Se nenhum grupo/veiculo for informado, a locacao pode ser criada sem registro em `locacoes_veiculos`, quando o fluxo de tela permitir.
- A tela de reserva deve mostrar o campo de veiculo, mas sem obrigatoriedade.
- Veiculo especifico so e obrigatorio ao abrir a locacao/registrar saida (`status = A`) ou em fluxos de fechamento que dependam de veiculo ativo.
- Ao registrar saida, se o veiculo preferido ainda estiver disponivel, ele deve ser usado. Se nao estiver disponivel, a tela deve exigir a selecao de outro veiculo disponivel do grupo. A partir desse momento a locacao passa a ter veiculo ativo para checklist, rastreabilidade financeira e devolucao.
- Na impressao, reserva confirmada (`status = R`) deve ser apresentada como **Voucher**, nao como fatura. O offcanvas de impressao mostra todas as opcoes disponiveis; os rotulos que usam "Fatura" passam a usar "Voucher" nesse status. Reserva pendente (`status = P`) nao entra nessa regra.

### R → A (Registrar Saida)
- Registra data/hora de saida
- Grava odometro e combustivel de saida
- Atualiza status do veiculo para "L" (Locado)
- Pode atualizar dados de bloqueio/caucao
- Em documentos personalizados, `{{locacao.tanque_saida}}` exibe o nível de saída como fração legível (`Reserva`, `1/2`, `Cheio`, etc.).

### A → F (Registrar Devolucao)
- Registra data/hora de chegada
- Grava odometro e combustivel de entrada
- Exige `data_chegada`, `odometro_entrada` e `combustivel_entrada`
- Bloqueia devolucao quando `odometro_entrada` for menor que `odometro_saida`
- Calcula automaticamente:
  - `odometro_usado = odometro_entrada - odometro_saida`
  - `km_excedente` (se plano KMC e ultrapassou franquia)
  - `combustivel_usado = combustivel_saida - combustivel_entrada`
  - `combustivel_valor = combustivel_usado * veiculos.valor_por_fracao` quando `combustivel_usado > 0`
- Antes de fechar, exige que o saldo financeiro efetivo lancado seja igual ao total
  esperado: `total_pagar_final + total_avarias`
- Parcelas pendentes nao bloqueiam o fechamento; a regra exige lancamento, nao pagamento
- Atualiza status do veiculo para "D" (Disponivel)
- Em documentos personalizados, `{{locacao.tanque_chegada}}` exibe o nível de chegada como fração legível (`Reserva`, `1/2`, `Cheio`, etc.).
- Apos fechar (`status = F`), a locacao deixa de ter veiculo ativo porque
  `locacoes_veiculos.data_entrada` fica preenchida. Listagens e telas de
  exibicao devem mostrar o ultimo veiculo do historico da locacao.
- Na tela de edicao, ao trocar status de `A` para `F`, o select de veiculo
  deve preservar/injetar o veiculo atual da locacao mesmo que ele nao apareca
  na busca de veiculos disponiveis. O endpoint `/api/veiculos/por-grupo`
  continua retornando apenas veiculos disponiveis para novas selecoes.

## Tabelas do Banco

### Tabela `locacoes`

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | INT UNSIGNED | Chave primaria |
| chave | VARCHAR(45) | Identificador do tenant |
| codigo | VARCHAR(15) | Codigo unico gerado (`L` + 7 alfanumericos, ex: `L9K3P7QA`) |
| sequencia | INT | Sequencia incremental por tenant |
| status | CHAR(1) | R = Reserva, A = Aberto, F = Fechado |
| id_cliente | INT UNSIGNED | FK para clientes |
| cliente_nome | VARCHAR | Nome desnormalizado |
| id_matriz_filial_retirada | INT UNSIGNED | Filial de retirada |
| id_matriz_filial_devolucao | INT UNSIGNED | Filial de devolucao |
| data_saida | DATETIME | Data/hora de saida |
| data_prevista | DATETIME | Data/hora prevista de retorno |
| data_chegada | DATETIME | Data/hora real de retorno |
| dias | INT | Duracao em dias |
| id_forma_pagamento | INT UNSIGNED | FK forma de pagamento |
| id_conta | INT UNSIGNED | FK conta bancaria |
| valor_desconto | DECIMAL | Desconto aplicado |
| total_fatura | DECIMAL | Total da fatura |
| total_pagar | DECIMAL | Total a pagar |
| id_conta_bloqueio | INT UNSIGNED | (legado) Conta para bloqueio |
| bloqueio_tipo | VARCHAR | (legado) Tipo |
| bloqueio_valor | DECIMAL | (legado) Valor |
| bloqueio_prazo_devolucao | INT | (legado) Dias |
| bloqueio_data_devolucao | DATE | (legado) Data devolucao |
| id_bloqueio_ativo | INT UNSIGNED | FK locacoes_bloqueios (hold ativo) |
| condutor_adicional | JSON | Array de condutores adicionais |
| array_fiadores | JSON | Array de fiadores |
| array_avalistas | JSON | Array de avalistas |
| array_testemunhas | JSON | Array de testemunhas |
| id_funcionario | INT UNSIGNED | Funcionario que criou |
| obs | TEXT | Observacoes |
| created_at | TIMESTAMP | Data de criacao |
| updated_at | DATETIME | Data de atualizacao |

### Minutos de Tolerancia

O campo `minuto_tolerancia` reduz o tempo cobrado no calculo de `dias` em todos os status da locacao. Para reservas, pendentes e locacoes abertas (`R`, `P`, `A`), o calculo usa o intervalo entre `data_saida` e `data_prevista`; para locacoes fechadas (`F`), usa `data_saida` e `data_chegada`. Alteracoes no campo na tela devem recalcular imediatamente dias, diaria e resumo.

### Tabela `locacoes_caucoes`

Armazena o deposito de garantia da locacao. Substitui as colunas legadas de caucao em
`locacoes` e permite controlar forma de pagamento, lancamento financeiro e devolucao sem
misturar esses dados no cadastro principal da locacao.

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | INT UNSIGNED PK | Identificador |
| chave | VARCHAR(20) | Tenant |
| id_locacao | INT UNSIGNED FK | FK locacoes (CASCADE) |
| id_cliente | INT UNSIGNED NULL | FK cliente vinculado a locacao |
| id_conta | INT UNSIGNED NULL | FK conta bancaria da caucao |
| id_cartao | INT UNSIGNED NULL | FK clientes_cartoes, quando aplicavel |
| id_forma_pagamento | INT UNSIGNED NULL | FK forma de pagamento |
| id_financeiro_entrada | INT UNSIGNED NULL | Lancamento de entrada da caucao |
| id_financeiro_devolucao | INT UNSIGNED NULL | Lancamento de devolucao da caucao |
| valor | DECIMAL(10,2) | Valor do deposito de garantia |
| prazo_devolucao | INT NULL | Dias para devolver a caucao |
| data_devolucao | DATE NULL | Data efetiva da devolucao |
| lancar_financeiro | TINYINT(1) | Indica se deve gerar financeiro |
| status | ENUM | ativa, devolvida ou cancelada |
| legacy_tipo | VARCHAR(100) NULL | Valor legado de `caucao_tipo`, preservado para auditoria/migracao |
| observacoes | TEXT NULL | Observacoes da caucao |
| created_at | TIMESTAMP | Data de criacao |
| updated_at | DATETIME | Data de atualizacao |

### Tabela `locacoes_bloqueios`

Rastreia authorization holds (pre-autorizacao) no cartao de credito do cliente.
Bloqueio = reserva de valor no limite do cartao, sem cobrar. Pode ser capturado ou liberado.

**Rotacao automatica**: Holds expiram em 7 dias (Stripe padrao). O cron job
`RotateAuthorizationHoldsJob` (diario 06:30) rotaciona holds 2 dias antes de expirar:
libera o hold atual e cria um novo, mantendo o bloqueio ativo indefinidamente.
Este job cobre tanto `locacoes_bloqueios` quanto `contratos_bloqueios`.

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | INT UNSIGNED PK | Identificador |
| chave | VARCHAR(20) | Tenant |
| id_locacao | INT UNSIGNED FK | FK locacoes (CASCADE) |
| id_cliente | INT UNSIGNED FK | FK clientes |
| id_cartao | INT UNSIGNED FK | FK clientes_cartoes (RESTRICT) |
| id_gateway | INT UNSIGNED FK | FK gateways_pagamento (RESTRICT) |
| gateway_code | VARCHAR(50) | 'stripe' ou 'square' |
| external_id | VARCHAR(255) | ID do gateway (pi_xxx no Stripe) |
| valor | DECIMAL(10,2) | Valor do hold |
| moeda | VARCHAR(3) | Moeda (default 'BRL') |
| status | ENUM | pending, authorized, captured, released, expired, failed |
| autorizado_em | DATETIME | Quando autorizado |
| capturado_em | DATETIME | Quando capturado |
| liberado_em | DATETIME | Quando liberado |
| expira_em | DATETIME | Quando o hold expira (7 ou 31 dias) |
| valor_capturado | DECIMAL(10,2) | Valor capturado (captura parcial) |
| payload | JSON | Resposta raw do gateway |

**Model**: `App\Models\LocacaoBloqueio`

### Tabela `locacoes_veiculos`

Historico de veiculos vinculados a locacao (suporte a substituicao).
Reservas podem ter linha sem veiculo especifico (`id_veiculo = NULL`) para guardar o grupo/categoria reservado.

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | INT UNSIGNED | Chave primaria |
| id_locacao | INT UNSIGNED | FK para locacoes (CASCADE) |
| id_veiculo | INT UNSIGNED | FK para veiculos (RESTRICT) |
| id_grupo | INT UNSIGNED | FK para grupos (SET NULL) |
| data_saida | DATETIME | Quando veiculo saiu (inicio uso) |
| data_entrada | DATETIME | Quando veiculo voltou (NULL = ativo) |
| plano | VARCHAR | KMC, KL ou KP/DI |
| valor_plano_km_pago | DECIMAL | Valor base/diaria do plano km cobrado/pago |
| valor_plano_km_livre | DECIMAL | Valor base/diaria do plano km livre |
| valor_plano_km_controlado | DECIMAL | Valor base/diaria do plano km controlado |
| km_franquia | INT | Franquia de km (plano KMC) |
| valor_km_excedente | DECIMAL | Valor por km excedente |
| seguro_carro | TINYINT | Seguro do veiculo ativo |
| valor_seguro_carro | DECIMAL | Valor do seguro |
| seguro_terceiros | TINYINT | Seguro terceiros ativo |
| valor_seguro_terceiros | DECIMAL | Valor seguro terceiros |
| odometro_saida | INT | Odometro na saida |
| odometro_entrada | INT | Odometro na entrada |
| odometro_usado | INT | Calculado: entrada - saida |
| combustivel_saida | INT | Nivel combustivel saida (fracoes) |
| combustivel_entrada | INT | Nivel combustivel entrada |
| combustivel_usado | INT | Calculado: saida - entrada |
| combustivel_valor | DECIMAL | Valor cobrado pelo combustivel |
| km_excedente | INT | Km alem da franquia (plano KMC) |
| motivo_saida | VARCHAR | Motivo da substituicao |
| acao_valores | VARCHAR | "manter" ou "grupo" |

### Mapeamento de valores por plano

- `KL` (Km Livre): o valor principal da diaria deve ser salvo em `valor_plano_km_livre`.
- `KMC` (Km Controlado): o valor principal da diaria deve ser salvo em `valor_plano_km_controlado`; `valor_km_excedente` permanece separado para cobrar km acima da franquia.
- `DI`/`KP` (Km Cobrado/Pago): o valor principal da diaria deve ser salvo em `valor_plano_km_pago`.

### Tabela `locacoes_taxaseservicos`

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | INT UNSIGNED | Chave primaria |
| id_locacao | INT UNSIGNED | FK para locacoes (CASCADE) |
| id_taxa | INT UNSIGNED | FK para taxaseservicos (SET NULL) |
| base_calculo | VARCHAR | FIX (fixo), PER (por periodo), VLT (valor total) |
| tipo_valor | VARCHAR | MON (monetario), POR (percentual) |
| nome | VARCHAR | Nome da taxa (snapshot) |
| quantidade | INT | Dias ou quantidade |
| valor_unitario | DECIMAL | Valor por unidade |
| valor_total | DECIMAL | Calculado: quantidade x valor_unitario |

## Substituicao Veicular

Durante uma locacao aberta (status A), eh possivel substituir o veiculo pela acao
dedicada de substituicao. A edicao normal da locacao nao deve trocar o veiculo.
Reservas (`status = R` ou `P`) continuam podendo alterar o veiculo/preferencia
diretamente no formulario de edicao.

### Fluxo
1. `LocacaoVeiculo::substituir()` recebe dados do veiculo antigo e novo
2. Registra devolucao do veiculo antigo (`data_entrada`, odometro, combustivel, `motivo_saida`)
3. Adiciona novo veiculo (`data_saida = agora`, `data_entrada = NULL`)
4. Se `manterValores = true`, copia precos/seguros do veiculo anterior
5. Se `manterValores = false`, carrega valores do grupo do novo veiculo

### API
```javascript
// Substituicao eh feita pela acao dedicada
await Api.post(`/locacoes/${locacaoId}/substituir`, {
    id_locacao_veiculo_antigo,
    id_veiculo_novo,
    odometro_entrada,
    combustivel_entrada,
    motivo_saida,
    plano_novo,
    manter_valores
});
```

Se uma locacao aberta (`A`) ou fechada (`F`) receber mudanca de `id_veiculo` em
`POST /locacoes/{id}/atualizar`, o backend deve bloquear a operacao. Locacoes
fechadas nao permitem substituicao; apenas exibem o historico.

### Rastreabilidade Financeira

Ao substituir um veiculo, as parcelas financeiras ja criadas mantêm o `financeiro.id_veiculo` do veiculo antigo. Novas parcelas geradas apos a substituicao recebem automaticamente o `id_veiculo` do novo veiculo via `LocacaoVeiculo::buscarAtivo()`. Ver [financeiro.md](financeiro.md#rastreabilidade-veicular) para detalhes.

## Integracao Financeira

### Código promocional

Locações e reservas internas validam códigos pelo canal `SIS`. Com código
informado, `valor_desconto` é calculado no servidor sobre o `total_fatura`; sem
código, o desconto manual permanece disponível. Em edições, o código inalterado
preserva o snapshot histórico quando o grupo também permanece igual. Promoções
restritas exigem que o grupo da reserva esteja entre os grupos participantes.
Veja [promocoes.md](./promocoes.md).

### Geracao de Parcelas

```php
// Gerar parcelas automaticas
$locacao->gerarParcelas($locacaoId, [
    'quantidade' => 3,
    'data_primeiro_vencimento' => '2025-04-01',
    'id_conta' => 1,
    'id_forma_pagamento' => 2,
], $chave);
```

- Divide o saldo restante em N parcelas iguais
- Quando recebe snapshot da tela, recalcula o total final antes de gerar
- O snapshot inclui os dados ainda nao salvos da devolucao (`odometro_ini`,
  `odometro_fim`, `km_controlado_franquia`, `km_valor` e `combustivel_fim`),
  garantindo que km excedente e combustivel entrem no saldo parcelavel
- O frontend envia `odometro_ini` e `odometro_fim` como inteiros. Por
  compatibilidade, o backend tambem normaliza valores mascarados (por exemplo,
  `72.870`) antes de calcular distancia e km excedente
- Gera apenas o saldo restante: total final somado as avarias cobradas, menos o
  total ja lancado no financeiro
- Bloqueia a geracao quando nao houver saldo restante
- Ultima parcela absorve diferenca de arredondamento
- Vencimentos incrementam +1 mes
- Cada parcela recebe `id_veiculo` do veiculo ativo automaticamente
- Parcelamentos grandes reservam sequencias financeiras em lote via `SequenciaHelper::proximasSequencias()` para evitar locks repetidos em `matrizes_filiais`
- A Fatura PDF de locacoes desconta o total ja pago no financeiro (`tipo = R`, `pago = S`) do `TOTAL A PAGAR` e exibe a lista de pagamentos/parcelas com vencimento/data de pagamento.

### Devolucao antecipada com credito

Ao fechar uma locacao aberta (`A -> F`), o sistema recalcula o total final com base
nos dados de devolucao informados na tela e concilia o financeiro pelas formulas:

```text
total_esperado = total_pagar_final + total_avarias
diferenca = total_esperado - total_lancado
```

`locacoes.total_pagar` continua representando apenas a locacao, sem incorporar
avarias, pois elas permanecem como receitas financeiras separadas no plano
`4.2.2.01`. Se `total_lancado` for maior que `total_esperado` (ex: locacao criada
para 2 dias e devolvida com 1 dia), a tela pergunta se deve criar uma fatura de
devolucao somente pela diferenca efetiva.

- O credito de devolucao e um lancamento `financeiro.tipo = D`, vinculado a
  `id_locacao`, com plano de contas `3.4.1.22` (Devolucao/Reembolso de locacao).
- A parcela/receita original permanece intacta para auditoria, inclusive quando
  ja estiver paga.
- O fechamento so prossegue apos confirmacao explicita do usuario.
- Se o saldo financeiro efetivo for menor que `total_esperado`, o fechamento
  continua bloqueado ate que uma parcela complementar seja lancada.
- Caucao/devolucao de caucao nao entra nesse calculo; somente creditos no plano
  `3.4.1.22` compensam receitas da locacao.

### Parcela Avulsa

```php
$locacao->adicionarParcela($locacaoId, [
    'valor' => 150.00,
    'data_venci' => '2025-05-15',
    'descricao' => 'Taxa adicional',
], $chave);
```

### Resumo Financeiro

```php
$resumo = $locacao->resumoFinanceiro($locacaoId);
// Retorna: total_locacao, total_avarias, total_esperado, total_lancado,
// total_receitas, total_credito_devolucao, total_pago, total_pendente,
// total_atrasado, diferenca
```

Na tela de locacao:

- `Total a pagar`: total final da locacao/fatura, incluindo diarias, taxas, descontos e encargos de devolucao
- `Avarias cobradas`: total de receitas de avaria (`financeiro.tipo = R`, plano `4.2.2.01`) vinculadas a locacao; entra no total cobrado do cliente
- `Total lancado`: saldo financeiro efetivo da locacao (receitas menos creditos de devolucao), sem lancamentos vinculados a multas
- `Valor reembolsado`: total de creditos de devolucao (`financeiro.tipo = D`, plano `3.4.1.22`) vinculados a locacao
- `Diferenca`: total final simulado, somado as avarias cobradas, menos total lancado; indica quanto ainda precisa ser lancado no financeiro
- `Valor pago`: soma das parcelas ja pagas
- `Saldo a pagar`: total final simulado, somado as avarias cobradas, menos valor pago e reembolsos; indica quanto ainda falta receber
- Em nova locacao/reserva ainda nao salva, a secao Pagamentos deve aparecer
  sem botoes ou acoes, exibindo apenas a orientacao para salvar antes de
  adicionar pagamento.

Na fatura PDF da locacao, o valor reembolsado aparece nos totais quando existir
credito de devolucao e reduz o `TOTAL A PAGAR`, sem misturar esse lancamento na
lista de pagamentos recebidos.

### Metodos Financeiros

| Metodo | Descricao |
|--------|-----------|
| `gerarParcelas($id, $dados, $chave)` | Gera parcelas do saldo restante da locacao |
| `adicionarParcela($id, $dados, $chave)` | Adiciona parcela avulsa |
| `listarParcelas($id)` | Lista parcelas com status de pagamento, sem lancamentos vinculados a multas |
| `atualizarParcela($id, $idParcela, $dados)` | Atualiza parcela pendente |
| `removerParcela($id, $idParcela)` | Remove parcela pendente |
| `resumoFinanceiro($id)` | Totais: pago, pendente, atrasado, diferenca, sem lancamentos vinculados a multas |
| `criarCreditoDevolucao($id, $valor, $chave)` | Cria fatura de devolucao/reembolso para excesso financeiro |

## API Endpoints

### Paginas
```
GET /pages/locacoes                           → Listagem
GET /pages/locacoes/adicionar                 → Formulario criar
GET /pages/locacoes/editar/{id}               → Formulario editar
GET /pages/locacoes/offcanvas-impressao       → Painel de impressao
GET /locacoes/{id}/imprimir                   → Gera PDF
```

### API (GET)
```
GET /api/locacoes                             → Lista paginada (search, status, filial)
GET /api/locacoes/{id}                        → Detalhes da locacao
GET /api/locacoes/{id}/veiculos               → Historico de veiculos
GET /api/locacoes/{id}/taxas                  → Taxas e servicos
GET /api/locacoes/{id}/parcelas               → Parcelas financeiras
GET /api/locacoes/{id}/resumo-financeiro      → Resumo financeiro
GET /api/locacoes/{id}/assinatura             → Dados da assinatura digital
```

### Acoes (POST)
```
POST /locacoes/salvar                         → Criar locacao
POST /locacoes/{id}/atualizar                 → Atualizar (inclui transicoes R→A, A→F; nao troca veiculo em A/F)
POST /locacoes/{id}/substituir                → Substituir veiculo de locacao aberta
POST /locacoes/{id}/excluir                   → Excluir locacao
POST /locacoes/{id}/limpar-assinatura         → Limpar assinatura para reassinar
POST /locacoes/{id}/enviar                    → Enviar por email/whatsapp/sms
POST /api/locacoes/{id}/gerar-parcelas        → Gerar parcelas
POST /api/locacoes/{id}/parcelas              → Adicionar parcela avulsa
POST /api/locacoes/{id}/parcelas/{id}/atualizar → Atualizar parcela
POST /api/locacoes/{id}/parcelas/{id}/excluir → Remover parcela
```

`POST /api/locacoes/{id}/gerar-parcelas` aceita os campos originais de parcelamento
(`quantidade`, `data_primeiro_vencimento`, `id_conta`, `id_forma_pagamento`) e pode
receber um snapshot da tela para recalcular o total antes da geracao:
`status`, `dias`, `plano`, `valor_desconto`, `seguro_carro`,
`seguro_carro_valor`, `seguro_terceiros`, `seguro_terceiros_valor`,
`combustivel_fim`, `condutor_adicional` e `taxas`.

Ao salvar uma locacao com status `F` (Fechado), a tela redireciona para
`/pages/locacoes`.

### Bloqueio e Caucao
```
POST /api/locacoes/{id}/bloqueio/criar        → Criar authorization hold no cartao
POST /api/locacoes/{id}/bloqueio/capturar     → Capturar hold (cobrar)
POST /api/locacoes/{id}/bloqueio/liberar      → Liberar hold sem cobrar
GET  /api/locacoes/{id}/bloqueio/status       → Consultar status/historico de holds
POST /api/locacoes/{id}/caucao/devolver       → Registrar devolucao do caucao (cria financeiro saida)
```

## Permissoes

| Permissao | Descricao |
|-----------|-----------|
| `locacoes.visualizar` | Visualizar listagem e detalhes |
| `locacoes.criar` | Criar novas locacoes |
| `locacoes.editar` | Editar locacoes existentes |
| `locacoes.cancelar` | Excluir locacoes |
| `locacoes.substituir` | Substituir veiculo de locacao aberta |
| `locacoes.saida` | Registrar saida do veiculo (R→A) |
| `locacoes.devolucao` | Registrar devolucao do veiculo (A→F) |
| `locacoes.imprimir` | Imprimir documentos |

Atendentes devem possuir `locacoes.editar`, `locacoes.substituir` e
`locacoes.devolucao` para alterar dados operacionais, substituir veiculo e
registrar devolucao/fechamento da locacao. Essas permissoes nao liberam
cancelamento/exclusao.

## Metodos do Model Principal (Locacao.php)

| Metodo | Descricao |
|--------|-----------|
| `listarPaginado(...)` | Lista com paginacao, busca e filtro de status; exibe veiculo ativo ou ultimo vinculado se fechada |
| `buscarPorId($id)` | Busca com dados completos (cliente, veiculo ativo ou ultimo vinculado) |
| `buscarPorCodigo($codigo)` | Busca por codigo unico |
| `criar($dados)` | Cria locacao com codigo auto-gerado |
| `atualizar($id, $dados)` | Atualiza dados da locacao |
| `deletar($id)` | Exclui e libera veiculo se nao fechada |
| `registrarSaida($id, $dados)` | Transicao R→A |
| `registrarDevolucao($id, $dados)` | Transicao A→F com calculos automaticos |
| `atualizarStatus($id, $status)` | Atualiza apenas o status |

## Metodos do Model de Veiculos (LocacaoVeiculo.php)

| Metodo | Descricao |
|--------|-----------|
| `listarPorLocacao($id)` | Lista historico de veiculos |
| `buscarAtivo($id)` | Retorna veiculo ativo (data_entrada IS NULL) |
| `buscarAtualOuUltimo($id)` | Retorna veiculo ativo ou, se nao houver, o ultimo vinculado |
| `adicionar($dados)` | Vincula veiculo a locacao |
| `devolver($id, $dados)` | Registra devolucao com calculos (km, combustivel) |
| `substituir($idAntigo, $dadosSaida, $dadosNovo, $manterValores)` | Substitui veiculo |
| `veiculoEstaLocado($veiculoId, $excluirLocacaoId)` | Verifica se ja esta locado |
| `findResponsavelByMulta($veiculoId, $dataHora)` | Identifica responsavel por multa |
| `carregarValoresGrupo($grupoId)` | Carrega precos do grupo |

## Diferencas de Contratos

| Aspecto | Locacoes | Contratos |
|---------|----------|-----------|
| Duracao | Curta (dias) | Longa (meses) |
| Veiculos | Um por vez | Multiplos simultaneos |
| Status | R→A→F (3 estados) | Multiplos estados complexos |
| Renovacao | Nao suportada | Auto-renovacao via cron |
| Financeiro | saldo restante gerado em parcelas | Estrutura mais complexa |
| Intervenientes | JSON (condutor, fiador, avalista, testemunha) | Campos dedicados |
| Impressao | 8 tipos de PDF (combos) | Tipos similares |

## Padrao de Datas

Datas de reserva, retirada e devolucao (`data_saida`, `data_prevista`, `data_chegada`) sao horarios operacionais locais e devem ser exibidas com `format_operational_datetime()` / `DateHelper.formatOperationalDateTime()`, sem conversao de timezone. Vencimentos, mensagens, PDFs e filtros devem usar `DateHelper`/helpers globais conforme o tipo de dado (`format_date()`, `format_datetime()` para instantes tecnicos, `DateHelper::addDaysForDatabase()`, `DateHelper::addMonthsForDatabase()`). Nao use `date()`, `time()`, `new DateTime()`, `new Date()` ou `NOW()/CURDATE()` diretamente em regra de negocio ou exibicao.
