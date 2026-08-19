# Modulo de Taxas e Servicos

Sistema de gestao de taxas e servicos adicionais para contratos de locacao.

## Visao Geral

O modulo permite cadastrar taxas e servicos que podem ser adicionados aos contratos, como:
- Taxa de limpeza
- Seguro adicional
- Taxa de administracao
- Servico de entrega/devolucao
- Equipamentos extras (GPS, cadeirinha, etc.)

Cada taxa pode ter regras de calculo diferentes (fixo, por periodo, percentual sobre total).

## Estrutura de Arquivos

```
app/
├── Models/
│   ├── TaxaServico.php           # Cadastro de taxas
│   └── ContratoTaxaServico.php   # Taxas vinculadas a contratos
├── Controllers/
│   └── TaxasServicosController.php
└── Views/
    └── pages/taxas-e-servicos/
        ├── index.php             # Listagem
        └── adicionar.php         # Formulario (criar/editar)
```

## Banco de Dados

### Tabela `taxaseservicos`

Cadastro de taxas disponiveis para uso em contratos.

| Campo | Tipo | Descricao |
|-------|------|-----------|
| id | INT | PK |
| chave | VARCHAR(45) | Multi-tenancy |
| nome | VARCHAR(100) | Nome da taxa |
| base_calculo | VARCHAR(3) | Modo de calculo: FIX, PER, VLT |
| tipo_valor | VARCHAR(3) | Tipo: MON (moeda), POR (%) |
| valor | DECIMAL(10,2) | Para `POR`, valor oficial do percentual. Para `MON`, fallback/display preenchido com o primeiro valor positivo das filiais; o valor oficial vive em `taxaseservicos_valores_filiais` |
| aplicar | VARCHAR(1) | Auto-aplicar: S ou N |
| onde_usar | VARCHAR(15) | Canais: SIS, SITE, APP |
| created_at | TIMESTAMP | Data de criacao |
| updated_at | DATETIME | Data de atualizacao |

### Tabela `taxaseservicos_filiais`

Relacionamento N:N entre taxas e filiais.

| Campo | Tipo | Descricao |
|-------|------|-----------|
| id | INT | PK |
| id_taxaservico | INT | FK para taxaseservicos |
| id_matriz_filial | INT | FK para matrizes_filiais |
| chave | VARCHAR(45) | Multi-tenancy |
| created_at | TIMESTAMP | Data de criacao |

**Constraint:** UNIQUE(id_taxaservico, id_matriz_filial)

### Tabela `taxaseservicos_valores_filiais`

Valor monetario da taxa **por filial**, quando `tipo_valor='MON'`. Cada filial guarda o valor na propria moeda. Taxas com `tipo_valor='POR'` (percentual) ignoram esta tabela — usam `taxaseservicos.valor` direto.

Para `MON`, `taxaseservicos.valor` nao deve ser tratado como fonte oficial. Ele existe como fallback/display para listagens, compatibilidade e casos antigos sem valor por filial. Ao criar ou atualizar uma taxa monetaria, o backend deve gravar:
- `taxaseservicos_valores_filiais.valor`: valor oficial de cada filial selecionada.
- `taxaseservicos.valor`: primeiro valor positivo informado nas filiais, apenas como fallback/display.

Nunca salve `taxaseservicos.valor = 0` para `MON` quando existem valores por filial.

| Campo | Tipo | Descricao |
|-------|------|-----------|
| id | INT | PK |
| chave | VARCHAR(45) | Multi-tenancy |
| id_taxaservico | INT | FK para taxaseservicos (CASCADE) |
| id_matriz_filial | INT | FK para matrizes_filiais (CASCADE) |
| valor | DECIMAL(10,2) | Valor na moeda da filial |
| created_at | DATETIME | |
| updated_at | DATETIME | |

**Constraint:** UNIQUE(id_taxaservico, id_matriz_filial)

**Uso:**
- UI (`taxas-e-servicos/adicionar.php`): tabela dinâmica aparece quando `tipo_valor=MON` — uma linha por filial marcada, com símbolo de moeda correspondente.
- Backend: `TaxaServico::resolverValor($taxa, $filialId)` retorna o valor correto (`tsvf.valor` se `MON` com filial, senao `t.valor`). Ja esta embutido em `listarParaSelect()`, `listarAutoAplicar()` e listagem paginada.
- Consumidores (`ContratosController`, `LocacoesController`): usam `resolverValor()` pra popular `valor_unitario` no snapshot, passando a filial de retirada.

Migrations: 00315 (schema), 00316 (backfill inicial) e 00371 (backfill do fallback/display zerado).

### Tabela `contratos_taxaseservicos`

Taxas vinculadas a um contrato especifico (snapshot dos valores).

| Campo | Tipo | Descricao |
|-------|------|-----------|
| id | INT | PK |
| id_contrato | INT | FK para contratos |
| id_taxa | INT | FK para taxaseservicos (nullable) |
| base_calculo | VARCHAR(3) | Snapshot do modo de calculo |
| tipo_valor | VARCHAR(3) | Snapshot do tipo |
| nome | VARCHAR(100) | Snapshot do nome |
| quantidade | INT | Quantidade aplicada |
| valor_unitario | DECIMAL(10,2) | Valor unitario |
| valor_total | DECIMAL(10,2) | Valor total calculado |
| origem | VARCHAR(12) | `contrato` para taxa contratual; `devolucao` para km, combustivel/carga e extras da devolucao |
| chave | VARCHAR(45) | Multi-tenancy |
| created_at | DATETIME | Data de criacao |

## Campos e Valores

### base_calculo

Define como o valor e aplicado ao contrato:

| Codigo | Nome | Comportamento |
|--------|------|---------------|
| FIX | Fixo | Valor unico, independente do periodo |
| PER | Por Periodo | Multiplica pelo numero de dias/periodos |
| VLT | Valor Total | Calcula sobre o valor total do aluguel |

### tipo_valor

Define a natureza do valor:

| Codigo | Nome | Formato |
|--------|------|---------|
| MON | Monetario | Valor em reais (R$) |
| POR | Porcentagem | Percentual (%) |

### aplicar

Define se a taxa e adicionada automaticamente em novos contratos:

| Valor | Significado |
|-------|-------------|
| S | Sim - adiciona automaticamente |
| N | Nao - requer selecao manual |

### onde_usar

Define onde a taxa esta disponivel (separado por virgula):

| Codigo | Significado |
|--------|-------------|
| SIS | Sistema (backend) |
| SITE | Website |
| APP | Aplicativo mobile |

## Regras de Calculo

### Tabela de Combinacoes

| base_calculo | tipo_valor | Formula |
|--------------|------------|---------|
| FIX | MON | `valor × quantidade` |
| FIX | POR | `(valor% × valor_unit) × quantidade` |
| PER | MON | `valor × quantidade × dias` |
| PER | POR | `(valor% × valor_diario) × quantidade × dias` |
| VLT | MON | `valor × quantidade` |
| VLT | POR | `(valor% × valor_total) × quantidade` |

### Exemplos Praticos

**Exemplo 1: Taxa de Limpeza (FIX + MON)**
- Valor: R$ 50,00
- Quantidade: 1
- Resultado: R$ 50,00

**Exemplo 2: Diaria de GPS (PER + MON)**
- Valor: R$ 15,00/dia
- Quantidade: 1
- Contrato: 7 dias
- Resultado: R$ 15 × 1 × 7 = R$ 105,00

**Exemplo 3: Taxa de Gerencia (VLT + POR)**
- Valor: 5%
- Aluguel total: R$ 1.000,00
- Quantidade: 1
- Resultado: 5% × R$ 1.000 = R$ 50,00

**Exemplo 4: Seguro Diario (PER + POR)**
- Valor: 2%
- Valor diario do aluguel: R$ 150,00
- Quantidade: 1
- Contrato: 7 dias
- Resultado: 2% × R$ 150 × 1 × 7 = R$ 21,00

## Rotas

### Paginas (requerem autenticacao)

| Metodo | Rota | Descricao |
|--------|------|-----------|
| GET | /pages/taxas-e-servicos | Listagem |
| GET | /pages/taxas-e-servicos/adicionar | Novo |
| GET | /pages/taxas-e-servicos/adicionar/{id} | Editar |

### API (requerem autenticacao)

| Metodo | Rota | Descricao |
|--------|------|-----------|
| GET | /api/taxas-e-servicos | Lista paginada |
| GET | /api/taxas-e-servicos/select | Para selects |
| GET | /api/taxas-e-servicos/buscar | Chosen server-side |
| GET | /api/taxas-e-servicos/{id} | Detalhes; aceita `id_filial` para validar o vinculo e resolver o valor monetario da filial |

### CRUD

| Metodo | Rota | Descricao |
|--------|------|-----------|
| POST | /taxas-e-servicos/salvar | Criar |
| POST | /taxas-e-servicos/{id}/atualizar | Atualizar |
| POST | /taxas-e-servicos/{id}/excluir | Excluir |

## Models

### TaxaServico.php

Gerencia o cadastro de taxas.

**Metodos principais:**

| Metodo | Descricao |
|--------|-----------|
| `listarPaginado()` | Lista com paginacao e filtro de filiais |
| `contar()` | Conta total com filtros |
| `buscarPorId($id)` | Busca taxa com filiais vinculadas |
| `listarFiliaisDaTaxa($id)` | Lista filiais de uma taxa |
| `criar($dados)` | Cria nova taxa |
| `atualizar($id, $dados)` | Atualiza taxa |
| `excluir($id)` | Remove taxa (valida vinculos) |
| `sincronizarFiliais($id, $filiais)` | Atualiza relacao N:N |
| `listarParaSelect()` | Retorna para chosen-select |
| `listarAutoAplicar()` | Taxas com aplicar='S' |

### ContratoTaxaServico.php

Gerencia taxas vinculadas a contratos.

**Metodos principais:**

| Metodo | Descricao |
|--------|-----------|
| `listarPorContrato($id)` | Lista taxas do contrato |
| `buscarPorId($id)` | Busca taxa especifica |
| `adicionar($contratoId, $dados)` | Adiciona taxa ao contrato |
| `atualizar($id, $dados)` | Atualiza taxa do contrato |
| `remover($id)` | Remove taxa do contrato |
| `removerTodas($contratoId)` | Remove todas as taxas |
| `sincronizar($contratoId, $taxas)` | Sincroniza lista de taxas |
| `calcularValorTotalTaxa()` | Calcula valor usando regras |
| `recalcularTaxas()` | Recalcula todas as taxas |
| `calcularTotal($contratoId)` | Soma total das taxas |

## Integracao com Contratos

### Auto-Aplicacao de Taxas

Ao criar um contrato sem taxas, o sistema busca automaticamente taxas com `aplicar='S'`:

```php
// ContratosController::store()
if (empty($dados['taxas'])) {
    $taxasAuto = $taxaServicoModel->listarAutoAplicar($chave, $filialId);
    // Adiciona automaticamente ao contrato
}
```

No website, `aplicar='S'` tem efeito obrigatorio quando `onde_usar` contem
`SITE`: a taxa aparece marcada e bloqueada para as filiais vinculadas em
`taxaseservicos_filiais`. O backend inclui a taxa no calculo e no snapshot da
locacao mesmo que o navegador omita ou adultere o ID. Taxas opcionais ou
obrigatorias de outra filial e taxas sem o canal `SITE` nao participam da
reserva.

O bloqueio do checkbox e apenas uma protecao de UX. A fonte de verdade e
`WebsiteReservaCalcService`, e `PublicWebsiteController::criarReserva` persiste
os servicos retornados em `breakdown.servicos`, nunca a lista bruta enviada pelo
navegador.

### Permissao para Editar Valores

A permissao `contratos.editar_valor_taxas` controla se o usuario pode alterar valores:

- **Sem permissao:** valores sao forcados a usar o cadastro original
- **Com permissao:** valores podem ser customizados por contrato

### Fluxo no Frontend

1. Usuario seleciona taxa no select (busca server-side filtrada pela filial de retirada)
2. Sistema consulta a taxa selecionada por ID e `id_filial`, carregando `base_calculo`, `tipo_valor` e o valor resolvido da filial
3. Usuario adiciona a lista
4. Ao salvar, dados sao enviados com snapshot dos valores
5. Backend recalcula totais usando as regras de calculo

O preenchimento da taxa selecionada nao pode depender apenas do preload do
Chosen Select, que retorna no maximo 50 registros. Resultados encontrados pela
busca remota devem ser consultados por ID antes de preencher os campos.

### Recalculo de Totais

Sempre que veiculos ou periodo mudam, taxas com `base_calculo=PER` ou `base_calculo=VLT` sao recalculadas automaticamente. No encerramento proporcional, somente registros com `origem=contrato` participam desse recalculo; registros de devolucao preservam o snapshot cobrado no ato e nao podem ser multiplicados novamente.

## Validacoes

### Ao Criar/Editar Taxa

- Nome obrigatorio
- Pelo menos uma filial deve ser selecionada
- Valor obrigatorio e maior que 0

No formulario, nenhuma filial selecionada deve ser exibida como
`Selecione as filiais...`. Esse estado nao representa todas as filiais: cada
filial participante precisa ser marcada explicitamente. Para valores monetarios,
cada filial selecionada recebe seu proprio campo de valor.

### Ao Excluir Taxa

- Verifica se existem contratos vinculados
- Se houver, impede exclusao com mensagem de erro

## Seguranca

- Todas as queries usam filtro automatico de `chave` (multi-tenancy) do QueryBuilder
- `withoutChave()` NAO eh usado — o filtro automatico cobre todos os casos do CRUD

## Migrations Relacionadas

| Migration | Descricao |
|-----------|-----------|
| 00143 | Criou `contratos_taxaseservicos` |
| 00149 | Migrou dados do formato antigo |
| 00160 | Criou `taxaseservicos_filiais` (N:N) |
| 00162 | Renomeou `aplicacao` → `base_calculo`, `valor_em` → `tipo_valor` |
| 00163 | Adicionou campos em `contratos_taxaseservicos` |
| 00315 | Criou `taxaseservicos_valores_filiais` (multi-moeda) |
| 00316 | Backfill: copiou `taxaseservicos.valor` pra cada filial quando `tipo_valor=MON` |
| 00419 | Identifica a origem das taxas e cria o snapshot de encerramento proporcional |
