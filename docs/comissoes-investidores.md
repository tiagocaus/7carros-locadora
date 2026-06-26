# Comissões de Investidores

Sistema de cálculo e controle de comissões para fornecedores investidores. Investidores cedem veículos à locadora e recebem repasses baseados em regras configuradas por grupo.

## Status de Implementação

- **Fases 1-6**: ✅ Implementadas (CRUD, cron, UI, permissões)
- **Fase 7**: ✅ Hook de pagamento — integrado no `FinanceiroController` (manual) e `PagamentoPublicoController` (webhook)
- **Fase 8**: ✅ Split Service criado — interface, factory, NullSplitService, AsaasSplitService. Integração no fluxo de cobrança pendente (requer teste sandbox)
## Tipos de Comissão

Configurados na tabela `grupos` (campos `comissao_investidor_tipo` e `comissao_investidor_valor`).

| Tipo | Descrição | Quando Gera | Gerador |
|------|-----------|-------------|---------|
| `percentual_locadora` | Locadora fica com X% do valor da fatura | Ao pagar fatura | `calcularComissaoPorPagamento()` |
| `fixo_locadora` | Locadora fica com R$ fixo por fatura | Ao pagar fatura | `calcularComissaoPorPagamento()` |
| `fixo_locadora_mensal` | Locadora recebe R$ fixo/mês por veículo | Cron dia 1 às 06:00 | `GerarComissoesMensaisJob` |
| `fixo_investidor_mensal` | Investidor recebe R$ fixo/mês por veículo | Cron dia 1 às 06:00 | `GerarComissoesMensaisJob` |

## Tabela `comissoes_investidores`

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | INT PK | Auto-incremento |
| `chave` | VARCHAR | Tenant key |
| `id_fornecedor` | INT FK | Investidor (fornecedores) |
| `id_veiculo` | INT FK | Veículo cedido |
| `id_grupo` | INT FK | Grupo do veículo (config comissão) |
| `tipo_origem` | ENUM | `locacao`, `contrato`, `mensal` |
| `id_locacao` | INT | Se origem = locação |
| `id_contrato` | INT | Se origem = contrato |
| `id_financeiro_origem` | INT | Fatura que gerou a comissão |
| `id_financeiro` | INT FK | Lançamento do repasse (quando pago) |
| `valor_base` | DECIMAL(10,2) | Valor da fatura |
| `comissao_tipo` | VARCHAR(50) | Tipo aplicado |
| `comissao_percentual` | DECIMAL(5,2) | % (se percentual_locadora) |
| `comissao_valor_fixo` | DECIMAL(10,2) | Valor fixo (se tipo fixo) |
| `valor_comissao_locadora` | DECIMAL(10,2) | Parte da locadora |
| `valor_repasse_investidor` | DECIMAL(10,2) | Parte do investidor |
| `status` | ENUM | `pendente`, `pago`, `cancelado` |
| `data_referencia` | DATE | Data de referência |
| `data_pagamento` | DATE | Quando foi pago |
| `created_at` | DATETIME | Criação |
| `updated_at` | DATETIME | Atualização |

## Campos do Fornecedor Investidor

Campos adicionais na tabela `fornecedores`:

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `investidor` | TINYINT(1) | 1 = é investidor |
| `split_gateway` | ENUM | Gateway (asaas, gerencianet, stripe, inter) |
| `split_gateway_conta` | VARCHAR(100) | Wallet/conta no gateway |
| `pix_chave` | VARCHAR(100) | Chave PIX |
| `pix_tipo` | ENUM | cpf, cnpj, email, telefone, aleatoria |
| `banco_codigo` | VARCHAR(10) | Código do banco |
| `banco_agencia` | VARCHAR(10) | Agência |
| `banco_conta` | VARCHAR(20) | Número da conta |
| `banco_tipo` | ENUM | corrente, poupanca |

## Arquitetura

### Arquivos

| Arquivo | Função |
|---------|--------|
| `app/Models/ComissaoInvestidor.php` | CRUD, paginação, filtros, totais |
| `app/Services/ComissaoInvestidorService.php` | Cálculo, pagamento, cancelamento, resumo |
| `app/Controllers/ComissoesInvestidoresController.php` | API REST (7 endpoints) |
| `app/Views/pages/comissoes-investidores/index.php` | UI com filtros, cards de totais, tabela paginada |
| `app/Crons/Jobs/GerarComissoesMensaisJob.php` | Gera comissões mensais (dia 1 às 06:00) |
| `app/Services/Split/SplitServiceInterface.php` | Interface para split de pagamento |
| `app/Services/Split/SplitServiceFactory.php` | Factory por gateway |
| `app/Services/Split/AsaasSplitService.php` | Implementação Asaas |
| `app/Services/Split/NullSplitService.php` | Null object para gateways sem split |
| `app/Models/Fornecedor.php` | CRUD de fornecedores com suporte investidor |
| `app/Views/pages/fornecedores/index.php` | Lista de fornecedores |
| `app/Views/pages/fornecedores/adicionar.php` | Formulário de fornecedor |

### Migrações

| Arquivo | Descrição |
|---------|-----------|
| `00115_add_comissao_investidor_grupos.php` | Campos de comissão em `grupos` |
| `00116_update_fornecedores_investidor.php` | Campos de investidor em `fornecedores` |
| `00117_create_comissoes_investidores.php` | Tabela `comissoes_investidores` |
| `00118_add_plano_conta_comissoes.php` | Plano de conta "Comissões Investidores" |
| `00119_add_comissoes_permissions.php` | Permissões RBAC |

## Rotas

### Páginas

| Método | Rota | Controller | Descrição |
|--------|------|------------|-----------|
| GET | `/pages/comissoes-investidores` | `view()` | Tela de gestão |

### API (protegidas com `api_csrf`, `rate_limit`, `throttle`)

| Método | Rota | Controller | Descrição |
|--------|------|------------|-----------|
| GET | `/api/comissoes-investidores` | `index()` | Lista paginada com filtros |
| GET | `/api/comissoes-investidores/totais` | `totais()` | Totais por status |
| GET | `/api/comissoes-investidores/resumo` | `resumo()` | Resumo por investidor |
| GET | `/api/comissoes-investidores/{id}` | `show()` | Detalhe de uma comissão |

### Ações (protegidas com `csrf`, `rate_limit`)

| Método | Rota | Controller | Descrição |
|--------|------|------------|-----------|
| POST | `/comissoes-investidores/{id}/pagar` | `pagar()` | Marca como pago + cria financeiro |
| POST | `/comissoes-investidores/{id}/cancelar` | `cancelar()` | Cancela (estorna financeiro se pago) |

## Fluxos

### Comissão por pagamento de fatura (Fase 7)

```
Fatura paga (manual ou webhook)
  → processarComissaoPorFinanceiro($idFinanceiro)
  → Resolve veículo via locação/contrato (LocacaoVeiculo ou ContratoVeiculo)
  → calcularComissaoPorPagamento($financeiro, $veiculo)
    → Verifica: veículo tem id_fornecedor?
    → Verifica: fornecedor é investidor?
    → Verifica: grupo tem comissao_investidor_tipo?
    → Ignora tipos mensais
    → Verifica duplicata por id_financeiro_origem (uma comissão por fatura)
    → Calcula valores → Cria comissão (status=pendente)
```

**Entry points**:
- `FinanceiroController::update()` — pagamento manual (após `atualizarComAuditoria`)
- `PagamentoPublicoController::marcarFinanceiroPago()` — webhook de gateway

### Split Service (Fase 8)

```
Ao criar cobrança no gateway (INTEGRAÇÃO FUTURA):
  → SplitServiceFactory::create($gatewayCode, $credentials)
  → Se suportaSplit() e fornecedor tem split_gateway_conta
  → configurarSplit($externalId, [['wallet_id' => ..., 'valor' => ...]])
  → Gateway repassa automaticamente ao investidor
```

**Gateways suportados**: Asaas. Demais retornam `NullSplitService`.

**Exemplo de integração futura** (ao criar cobrança no gateway):

```php
use App\Services\Split\SplitServiceFactory;
use App\Services\ComissaoInvestidorService;

// Após criar cobrança no gateway ($externalId = ID retornado)
$veiculo = $veiculoModel->buscarPorId($locacao['id_veiculo']);
$grupo = $grupoModel->buscarPorId($veiculo['id_grupo']);

if ($veiculo['id_fornecedor'] && $grupo['comissao_investidor_tipo']) {
    $fornecedor = $fornecedorModel->buscarPorId($veiculo['id_fornecedor']);

    if ($fornecedor['split_gateway_conta']) {
        $comissaoService = new ComissaoInvestidorService();
        $calculo = $comissaoService->calcularValores(
            $grupo['comissao_investidor_tipo'],
            $grupo['comissao_investidor_valor'],
            $valorCobranca
        );

        $splitService = SplitServiceFactory::create(
            $fornecedor['split_gateway'] ?? 'asaas',
            $credentials,
            $sandbox
        );
        $splitService->configurarSplit($externalId, [
            ['wallet_id' => $fornecedor['split_gateway_conta'], 'valor' => $calculo['investidor']]
        ]);
    }
}
```

### Comissão mensal (funcionando)

```
Cron (dia 1 às 06:00) → GerarComissoesMensaisJob
  → Busca veículos com investidor + grupo mensal
  → Para cada veículo: verifica duplicata do mês → cria comissão
  → Envia email resumo para APP_COMPANY_EMAIL
```

### Pagamento de comissão (funcionando)

```
UI: Usuário clica "Pagar" → POST /comissoes-investidores/{id}/pagar
  → marcarComoPago()
  → Cria lançamento em financeiro (tipo=despesa, id_fornecedor=investidor)
  → Vincula via id_financeiro
  → Log de auditoria
```

### Cancelamento (funcionando)

```
UI: Usuário clica "Cancelar" → POST /comissoes-investidores/{id}/cancelar
  → Se já pago: estorna lançamento financeiro
  → Atualiza status=cancelado
  → Log de auditoria com motivo
```

### Relatório Fornecedor Investidor

O relatório `/pages/relatorios/fornecedores/investidor` mostra resumo por investidor e detalhamento por veículo.

- A configuração em `grupos.comissao_investidor_tipo` e `grupos.comissao_investidor_valor` define a regra de comissão, mas não cria valores retroativos por si só.
- Receita gerada, comissão devida, comissão paga e saldo são calculados a partir dos registros em `comissoes_investidores` no período filtrado.
- O detalhamento por veículo deve indicar quando não existe comissão gerada: grupo sem comissão, sem fatura paga no período ou comissão mensal ainda não gerada.
- Comissões já geradas continuam aparecendo nos valores financeiros do período mesmo se o veículo estiver inativo no cadastro atual; esses veículos aparecem como histórico/inativo no detalhe e não entram na contagem operacional.
- Veículos com disponibilidade inativa (`V`, `RO`, `E`) não entram na contagem operacional do relatório.
- O filtro "Fornecedor investidor" usa `/api/fornecedores/investidores/select` e restringe o relatório ao fornecedor selecionado.
- O filtro de filial restringe veículos e comissões pela filial do veículo, além do controle de acesso multi-filial do usuário.
- O filtro visual "Modelo" define a apresentação: `Detalhado` é o padrão e mostra os veículos de cada fornecedor logo abaixo; `Agrupado` mostra somente fornecedores. Esse filtro não altera totais.

## Permissões

| Permissão | Descrição |
|-----------|-----------|
| `comissoes_investidores.visualizar` | Ver lista de comissões |
| `comissoes_investidores.pagar` | Marcar como pago |
| `comissoes_investidores.cancelar` | Cancelar comissão |
| `comissoes_investidores.exportar` | Exportar relatórios |

## i18n

Traduções em `app/Lang/{locale}/modules/comissoes_investidores.php` para pt_BR, en_US, es_ES, it_IT, pt_PT.

## Menu

Link no navbar em Financeiro: "Comissões Investidores" (`fas fa-hand-holding-usd`).

## Pendente

- [ ] Testar geração de comissão ao pagar fatura de locação
- [ ] Testar geração de comissão ao pagar fatura de contrato
- [ ] Integrar split no fluxo de criação de cobrança
- [ ] Testar split automático com conta Asaas sandbox
