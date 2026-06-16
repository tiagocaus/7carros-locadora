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

## Fluxo de Status

```
R (Reserva) ──registrarSaida()──> A (Aberto) ──registrarDevolucao()──> F (Fechado)
```

### Criacao de Reserva
- Reserva (`status = R`) e reserva pendente (`status = P`) **nao precisam selecionar veiculo especifico**.
- A pratica operacional esperada e reservar o **grupo/categoria**: o cliente garante um grupo (ex: Economico, SUV) e recebe qualquer veiculo disponivel daquele grupo no momento da retirada.
- Reserva pode ser criada apenas com grupo/categoria (`id_grupo`), gravando `id_veiculo = NULL` em `locacoes_veiculos`.
- Se nenhum grupo/veiculo for informado, a locacao pode ser criada sem registro em `locacoes_veiculos`, quando o fluxo de tela permitir.
- Nao exibir mensagem em tela dizendo que veiculo e opcional; o comportamento deve ser silencioso e natural.
- Veiculo especifico so e obrigatorio ao abrir a locacao/registrar saida (`status = A`) ou em fluxos de fechamento que dependam de veiculo ativo.
- Ao registrar saida, a locadora escolhe um veiculo disponivel do grupo reservado. A partir desse momento a locacao passa a ter veiculo ativo para checklist, rastreabilidade financeira e devolucao.
- Na impressao, reserva confirmada (`status = R`) deve ser apresentada como **Voucher**, nao como fatura. O offcanvas de impressao mostra apenas Voucher e Documento para esse status. Reserva pendente (`status = P`) nao entra nessa regra.

### R → A (Registrar Saida)
- Registra data/hora de saida
- Grava odometro e combustivel de saida
- Atualiza status do veiculo para "L" (Locado)
- Pode atualizar dados de bloqueio/caucao

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
- Antes de fechar, exige parcelas financeiras lancadas com total igual ao total final da locacao
- Parcelas pendentes nao bloqueiam o fechamento; a regra exige lancamento, nao pagamento
- Atualiza status do veiculo para "D" (Disponivel)
- Apos fechar (`status = F`), a locacao deixa de ter veiculo ativo porque
  `locacoes_veiculos.data_entrada` fica preenchida. Listagens e telas de
  exibicao devem mostrar o ultimo veiculo do historico da locacao.

## Tabelas do Banco

### Tabela `locacoes`

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | INT UNSIGNED | Chave primaria |
| chave | VARCHAR(45) | Identificador do tenant |
| codigo | VARCHAR(15) | Codigo unico gerado (ex: L123AB) |
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
| caucao_valor | DECIMAL | Valor do deposito de garantia |
| caucao_tipo | VARCHAR | Forma pagamento (dinheiro, pix, cartao, cheque) |
| id_conta_caucao | INT UNSIGNED | FK conta bancaria da caucao |
| caucao_prazo_devolucao | INT | Dias para devolver caucao |
| caucao_data_devolucao | DATE | Data efetiva da devolucao |
| id_cartao_caucao | INT UNSIGNED | FK clientes_cartoes (se pago com cartao) |
| id_bloqueio_ativo | INT UNSIGNED | FK locacoes_bloqueios (hold ativo) |
| condutor_adicional | JSON | Array de condutores adicionais |
| array_fiadores | JSON | Array de fiadores |
| array_avalistas | JSON | Array de avalistas |
| array_testemunhas | JSON | Array de testemunhas |
| id_funcionario | INT UNSIGNED | Funcionario que criou |
| obs | TEXT | Observacoes |
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

Durante uma locacao aberta (status A), eh possivel substituir o veiculo:

### Fluxo
1. `LocacaoVeiculo::substituir()` recebe dados do veiculo antigo e novo
2. Registra devolucao do veiculo antigo (`data_entrada`, odometro, combustivel, `motivo_saida`)
3. Adiciona novo veiculo (`data_saida = agora`, `data_entrada = NULL`)
4. Se `manterValores = true`, copia precos/seguros do veiculo anterior
5. Se `manterValores = false`, carrega valores do grupo do novo veiculo

### API
```javascript
// Substituicao eh feita via update da locacao
await Api.post(`/locacoes/${locacaoId}/atualizar`, {
    // ... dados da locacao
    // Controller detecta mudanca de veiculo e chama substituir()
});
```

### Rastreabilidade Financeira

Ao substituir um veiculo, as parcelas financeiras ja criadas mantêm o `financeiro.id_veiculo` do veiculo antigo. Novas parcelas geradas apos a substituicao recebem automaticamente o `id_veiculo` do novo veiculo via `LocacaoVeiculo::buscarAtivo()`. Ver [financeiro.md](financeiro.md#rastreabilidade-veicular) para detalhes.

## Integracao Financeira

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
- Gera apenas o saldo restante: total final menos total ja lancado no financeiro
- Bloqueia a geracao quando nao houver saldo restante
- Ultima parcela absorve diferenca de arredondamento
- Vencimentos incrementam +1 mes
- Cada parcela recebe `id_veiculo` do veiculo ativo automaticamente
- Parcelamentos grandes reservam sequencias financeiras em lote via `SequenciaHelper::proximasSequencias()` para evitar locks repetidos em `matrizes_filiais`
- A Fatura PDF de locacoes desconta o total ja pago no financeiro (`tipo = R`, `pago = S`) do `TOTAL A PAGAR` e exibe a lista de pagamentos/parcelas com vencimento/data de pagamento.

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
// Retorna: total_locacao, total_lancado, total_pago, total_pendente, total_atrasado, diferenca
```

Na tela de locacao:

- `Total a pagar`: total final da locacao/fatura, incluindo diarias, taxas, descontos e encargos de devolucao
- `Total lancado`: soma das parcelas/lancamentos financeiros da locacao
- `Diferenca`: total final simulado menos total lancado; indica quanto ainda precisa ser lancado no financeiro
- `Valor pago`: soma das parcelas ja pagas
- `Saldo a pagar`: total final simulado menos valor pago; indica quanto ainda falta receber

### Metodos Financeiros

| Metodo | Descricao |
|--------|-----------|
| `gerarParcelas($id, $dados, $chave)` | Gera parcelas do saldo restante da locacao |
| `adicionarParcela($id, $dados, $chave)` | Adiciona parcela avulsa |
| `listarParcelas($id)` | Lista parcelas com status de pagamento |
| `atualizarParcela($id, $idParcela, $dados)` | Atualiza parcela pendente |
| `removerParcela($id, $idParcela)` | Remove parcela pendente |
| `resumoFinanceiro($id)` | Totais: pago, pendente, atrasado, diferenca |

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
POST /locacoes/{id}/atualizar                 → Atualizar (inclui transicoes R→A, A→F, substituicao)
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
| `locacoes.saida` | Registrar saida do veiculo (R→A) |
| `locacoes.devolucao` | Registrar devolucao do veiculo (A→F) |
| `locacoes.imprimir` | Imprimir documentos |

Atendentes devem possuir `locacoes.editar` e `locacoes.devolucao` para alterar
dados operacionais, substituir veiculo e registrar devolucao/fechamento da
locacao. Essas permissoes nao liberam cancelamento/exclusao.

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
