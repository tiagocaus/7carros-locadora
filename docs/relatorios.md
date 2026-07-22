# Documentação de Relatórios

Este documento especifica o comportamento e as informações que cada relatório do sistema deve apresentar. Use como guia de implementação.

> **Guia técnico**: Para arquitetura, padrões de código e checklist de como criar novos relatórios, consulte [relatorios-dev.md](relatorios-dev.md).

---

## Índice

1. [KPIs / Indicadores de Desempenho](#1-kpis--indicadores-de-desempenho)
2. [Financeiro](#2-financeiro)
3. [Veicular](#3-veicular)
4. [Clientes](#4-clientes)
5. [Contratos/Locações](#5-contratoslocações)
6. [Operacional](#6-operacional)
7. [Faturas](#7-faturas)
8. [Comercial](#8-comercial)
9. [Fornecedores](#9-fornecedores)
10. [Funcionários](#10-funcionários)
11. [Comparativos](#11-comparativos)

---

## Padrões Gerais

### Filtros Comuns (aplicáveis a todos os relatórios)
- **Período**: Data inicial e final (obrigatório)
- **Filial**: Dropdown com opção "Todas" (respeitar permissões do usuário)

### Layout Padrão
- Cabeçalho com logo da empresa, nome do relatório, período e data de geração
- Filtros aplicados visíveis no topo
- Totalizadores destacados
- Rodapé com paginação e "Gerado por [usuário] em [data/hora]"

### Permissões
- Cada relatório deve ter sua própria permissão no sistema de roles
- Nomenclatura: `relatorios.[grupo].[nome]` (ex: `relatorios.kpis.taxa_ocupacao`)

---

## 1. KPIs / Indicadores de Desempenho

### 1.1 Taxa de Ocupação da Frota

**Objetivo**: Mostrar o percentual de veículos que estão efetivamente locados em relação ao total disponível.

**Fórmula**: `(Dias Locados / Dias Disponíveis) × 100`

**Filtros específicos**:
- Grupo de veículos
- Veículo específico
- Status do veículo (ativo/inativo)

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Total de veículos | Quantidade de veículos ativos no período |
| Dias disponíveis | Total de dias × quantidade de veículos |
| Dias locados | Soma dos dias em que veículos estiveram locados |
| Dias parados | Dias disponíveis - Dias locados |
| Taxa de ocupação (%) | Percentual calculado |
| Meta | Se configurada, mostrar meta vs realizado |

**Visualizações**:
- Gráfico de linha: evolução diária/semanal/mensal da taxa
- Gráfico de pizza: ocupados vs parados
- Tabela detalhada por veículo com sua taxa individual
- Ranking: veículos mais e menos utilizados

**Alertas visuais**:
- Verde: ≥ 70%
- Amarelo: 50-69%
- Vermelho: < 50%

---

### 1.2 RevPAR (Receita por Veículo Disponível/Dia)

**Objetivo**: Medir a eficiência da receita considerando tanto preço quanto ocupação.

**Fórmula**: `Receita Total de Locação / Total de Dias Disponíveis`

**Filtros específicos**:
- Grupo de veículos
- Categoria de veículo

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Receita total de locação | Soma das diárias no período |
| Total de dias disponíveis | Dias × veículos ativos |
| RevPAR | Valor calculado (R$) |
| RevPAR por grupo | Breakdown por categoria de veículo |
| Variação vs período anterior | Percentual de variação |

**Visualizações**:
- Gráfico de barras: RevPAR por grupo de veículos
- Gráfico de linha: evolução do RevPAR ao longo do tempo
- Comparativo com período anterior

---

### 1.3 Diária Média (ADR - Average Daily Rate)

**Objetivo**: Calcular o valor médio da diária praticada.

**Fórmula**: `Receita Total de Locação / Número de Diárias Vendidas`

**Filtros específicos**:
- Grupo de veículos
- Tipo de locação (diária, semanal, mensal)
- Com/sem promoção

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Receita total | Soma das locações no período |
| Quantidade de diárias | Total de dias locados |
| Diária média geral | Valor calculado |
| Diária média por grupo | Breakdown por categoria |
| Diária mínima praticada | Menor valor cobrado |
| Diária máxima praticada | Maior valor cobrado |
| Variação vs tabela base | Desconto/acréscimo médio aplicado |

**Visualizações**:
- Gráfico de barras: diária média por grupo
- Gráfico de linha: evolução da diária média
- Histograma: distribuição das diárias praticadas

---

### 1.4 Margem Bruta por Dia

**Objetivo**: Medir o lucro bruto médio por dia de locação.

**Fórmula**: `(Receita - Custos Variáveis Diretos) / Dias Locados`

**Custos variáveis considerados**:
- Combustível (diferença)
- Lavagem
- Manutenções durante locação
- Taxas de gateway de pagamento

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Receita total | Faturamento de locações |
| Custos variáveis | Soma dos custos diretos |
| Margem bruta total | Receita - Custos |
| Dias locados | Total de diárias |
| Margem bruta/dia | Valor calculado |
| % Margem | Margem / Receita × 100 |

**Visualizações**:
- Gráfico de barras: margem por grupo de veículos
- Gráfico de linha: evolução da margem
- Tabela com breakdown de custos

---

### 1.5 Receita por Veículo

**Objetivo**: Identificar quanto cada veículo gera de receita.

**Filtros específicos**:
- Grupo de veículos
- Status (ativo/inativo)
- Ordenação (maior/menor receita)

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Placa | Identificação do veículo |
| Modelo | Marca/modelo |
| Grupo | Categoria do veículo |
| Receita locação | Valor faturado em diárias |
| Receita taxas/serviços | Adicionais cobrados |
| Receita total | Soma |
| Dias locados | Quantidade de dias |
| Receita/dia | Média por dia locado |
| % do faturamento total | Participação no total |

**Visualizações**:
- Ranking dos 10 veículos mais rentáveis
- Ranking dos 10 veículos menos rentáveis
- Gráfico de Pareto (80/20)
- Mapa de calor por grupo

---

### 1.6 % Receitas Adicionais

**Objetivo**: Medir a participação de taxas e serviços adicionais no faturamento total.

**Fórmula**: `(Receita de Adicionais / Receita Total) × 100`

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Receita de locação | Apenas diárias |
| Receita de adicionais | Taxas e serviços |
| Receita total | Soma |
| % Adicionais | Percentual calculado |
| Breakdown por tipo | Detalhamento por taxa/serviço |

**Detalhamento de adicionais**:
- Proteção/seguro
- Condutor adicional
- Cadeirinha infantil
- GPS
- Wi-Fi
- Quilometragem extra
- Combustível
- Limpeza
- Multas repassadas
- Outros

**Visualizações**:
- Gráfico de pizza: composição da receita
- Gráfico de barras: receita por tipo de adicional
- Ranking de adicionais mais vendidos

---

### 1.7 Tempo Médio de Locação

**Objetivo**: Identificar a duração média das locações.

**Fórmula**: `Soma dos Dias de Todas as Locações / Número de Locações`

**Filtros específicos**:
- Tipo de cliente (PF/PJ)
- Grupo de veículos
- Faixa de duração

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Total de locações | Quantidade no período |
| Total de dias | Soma das durações |
| Média de dias | Valor calculado |
| Mediana | Valor central |
| Moda | Duração mais frequente |
| Mínimo | Menor duração |
| Máximo | Maior duração |

**Distribuição por faixa**:
- 1 dia
- 2-3 dias
- 4-7 dias (semana)
- 8-15 dias
- 16-30 dias (mensal)
- > 30 dias

**Visualizações**:
- Histograma de distribuição
- Gráfico de pizza por faixa
- Comparativo PF vs PJ

---

### 1.8 ROI por Veículo

**Objetivo**: Calcular o retorno sobre investimento de cada veículo.

**Fórmula**: `((Receita - Custos) / Valor de Aquisição) × 100`

**Custos considerados**:
- Manutenções
- IPVA
- Licenciamento
- Seguro
- Depreciação
- Combustível (diferenças)
- Multas não repassadas

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Placa | Identificação |
| Valor de aquisição | Preço de compra |
| Tempo de frota | Meses desde aquisição |
| Receita acumulada | Total gerado |
| Custos acumulados | Total de despesas |
| Lucro líquido | Receita - Custos |
| ROI (%) | Percentual calculado |
| ROI anualizado | Projeção para 12 meses |
| Payback | Meses para recuperar investimento |

**Visualizações**:
- Ranking por ROI
- Gráfico de dispersão: ROI vs Tempo de frota
- Análise de payback por grupo

---

## 2. Financeiro

### 2.1 Movimentações Financeiras

**Objetivo**: Visão completa de todas as movimentações financeiras.

**Filtros específicos**:
- Tipo de lançamento (receita/despesa)
- Categoria/plano de contas
- Conta bancária/caixa
- Status (pago/pendente/cancelado)
- Centro de custo

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Data | Data do lançamento |
| Tipo | Receita ou Despesa |
| Categoria | Plano de contas |
| Descrição | Detalhamento |
| Valor | Montante |
| Status | Pago/Pendente/Cancelado |
| Conta | Conta bancária ou caixa |
| Origem | Locação, manual, etc. |

**Totalizadores**:
- Total de receitas
- Total de despesas
- Saldo do período
- Quantidade de lançamentos

**Visualizações**:
- Tabela paginada com todos os lançamentos
- Gráfico de barras: receitas vs despesas por dia/semana/mês
- Fluxo de caixa visual

---

### 2.2 Faturamento

**Objetivo**: Mostrar todo o faturamento da empresa.

**Filtros específicos**:
- Origem (locação, taxa, serviço, venda)
- Cliente
- Forma de pagamento
- Situação da fatura: Pagas (padrão), Não pagas ou Todas

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Faturamento bruto | Total faturado |
| Descontos concedidos | Total de descontos |
| Faturamento líquido | Bruto - Descontos |
| Por origem | Breakdown por tipo |
| Por forma de pagamento | Breakdown por método |
| Por filial | Breakdown por unidade |

**Detalhamento por origem**:
- Locações (diárias)
- Taxas e serviços
- Multas repassadas
- Danos/avarias cobrados
- Outros

**Visualizações**:
- Gráfico de linha: evolução do faturamento
- Gráfico de pizza: composição por origem
- Comparativo com período anterior

---

### 2.3 Demonstrativos de Resultados (DRE)

**Objetivo**: Apresentar o resultado operacional da empresa.

**Filtros específicos**:
- Situação da fatura: Pagas (padrão), Não pagas ou Todas

O filtro de situação é aplicado tanto às receitas quanto às despesas do demonstrativo.

**Estrutura do DRE**:

```
(+) RECEITA BRUTA
    Locações
    Taxas e Serviços
    Outras Receitas
(-) DEDUÇÕES
    Descontos
    Impostos sobre receita
(=) RECEITA LÍQUIDA

(-) CUSTOS OPERACIONAIS
    Manutenção de veículos
    Combustível
    Depreciação
    Seguros
    IPVA/Licenciamento
(=) LUCRO BRUTO

(-) DESPESAS OPERACIONAIS
    Pessoal (salários, encargos)
    Aluguel
    Energia/Água/Telefone
    Marketing
    Sistemas/Software
    Despesas administrativas
(=) LUCRO OPERACIONAL

(+/-) RESULTADO FINANCEIRO
    Receitas financeiras
    Despesas financeiras
(=) LUCRO ANTES DO IR

(-) IMPOSTOS
(=) LUCRO LÍQUIDO
```

**Visualizações**:
- Estrutura vertical do DRE
- Gráfico de waterfall
- Comparativo com períodos anteriores
- % de cada linha sobre a receita

---

### 2.4 Livro de Caixa

**Objetivo**: Registro cronológico de todas as movimentações de caixa.

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Data/Hora | Momento da movimentação |
| Histórico | Descrição da operação |
| Conta | Conta bancária/caixa da movimentação |
| Forma de pag. | Forma de pagamento da movimentação |
| Entrada | Valor de entrada |
| Saída | Valor de saída |
| Saldo | Saldo acumulado |
| Responsável | Usuário que registrou |
| Referência | Locação, fatura, etc. |

**Totalizadores**:
- Saldo inicial do período
- Total de entradas
- Total de saídas
- Saldo final

**Recursos**:
- Impressão em formato de livro caixa oficial
- Assinatura digital do responsável
- Fechamento de caixa por turno/dia

---

### 2.5 Contas Bancárias/Caixas

**Objetivo**: Posição e movimentação de cada conta.

**Informações a exibir por conta**:

| Campo | Descrição |
|-------|-----------|
| Conta | Nome da conta |
| Banco | Instituição financeira |
| Saldo inicial | Saldo no início do período |
| Entradas | Total de créditos |
| Saídas | Total de débitos |
| Saldo final | Saldo no fim do período |
| Saldo atual | Posição em tempo real |

**Detalhamento**:
- Lista de movimentações por conta
- Conciliação bancária (se integrado)
- Transferências entre contas

**Visualizações**:
- Cards com saldo de cada conta
- Gráfico de linha: evolução do saldo
- Gráfico de barras: movimentação por conta

---

### 2.6 Plano de Contas

**Objetivo**: Análise por categoria do plano de contas.

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Código | Código da conta |
| Descrição | Nome da categoria |
| Tipo | Receita/Despesa |
| Valor no período | Total movimentado |
| % do total | Participação |
| Variação | vs período anterior |

**Estrutura hierárquica**:
- Exibir em formato de árvore (conta pai → filhas)
- Permitir expandir/colapsar níveis
- Totalizadores por nível

**Visualizações**:
- Treemap de despesas
- Gráfico de barras horizontais
- Comparativo período anterior

---

### 2.7 Projeção de Receitas

**Objetivo**: Projetar receitas futuras baseado em histórico e reservas.

**Base de cálculo**:
- Reservas confirmadas
- Contratos ativos (mensalistas)
- Média histórica de walk-ins
- Sazonalidade

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Receita confirmada | Reservas + contratos ativos |
| Receita projetada | Estimativa baseada em histórico |
| Receita total esperada | Soma |
| Intervalo de confiança | Mínimo - Máximo esperado |

**Visualizações**:
- Gráfico de linha: projeção para próximos 30/60/90 dias
- Área de confiança (otimista/pessimista)
- Comparativo projeção vs realizado (meses anteriores)

---

### 2.8 Análise de Rentabilidade

**Objetivo**: Analisar rentabilidade por diferentes dimensões.

**Dimensões disponíveis**:
- Por grupo de veículos
- Por veículo
- Por cliente
- Por filial
- Por funcionário (vendedor)

**Informações a exibir**:

| Dimensão | Receita | Custos | Lucro | Margem (%) | Participação (%) |
|----------|---------|--------|-------|------------|------------------|
| [Item] | | | | | |

**Visualizações**:
- Matriz de rentabilidade
- Gráfico de Pareto
- Quadrante BCG (participação vs crescimento)

---

### 2.9 Inadimplência Geral

**Objetivo**: Panorama completo da inadimplência da empresa.

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Total a receber | Todas as faturas em aberto |
| Total vencido | Faturas vencidas |
| Taxa de inadimplência | % vencido / total |
| Aging (envelhecimento) | Por faixa de atraso |
| Por cliente | Maiores devedores |
| Por filial | Inadimplência por unidade |

**Faixas de aging**:
- 1-15 dias
- 16-30 dias
- 31-60 dias
- 61-90 dias
- > 90 dias

**Visualizações**:
- Gráfico de barras: aging
- Lista de maiores devedores
- Evolução da inadimplência ao longo do tempo

---

### 2.10 Taxas e Serviços Cobrados

**Objetivo**: Detalhar a receita gerada por taxas e serviços adicionais.

**Informações a exibir**:

| Taxa/Serviço | Quantidade | Valor Total | Ticket Médio | % do Total |
|--------------|------------|-------------|--------------|------------|
| Proteção básica | | | | |
| Proteção total | | | | |
| Condutor adicional | | | | |
| Cadeirinha | | | | |
| GPS | | | | |
| Km extra | | | | |
| ... | | | | |

**Visualizações**:
- Ranking de taxas mais vendidas
- Gráfico de pizza: composição
- Evolução ao longo do tempo

---

## 3. Veicular

### 3.1 Manutenções Veicular

**Objetivo**: Controle completo das manutenções realizadas.

**Filtros específicos**:
- Tipo de manutenção (preventiva/corretiva)
- Status (pendente/em andamento/concluída)
- Oficina
- Veículo
- Faixa de valor

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Veículo | Placa e modelo |
| Tipo | Preventiva/Corretiva |
| Descrição | Serviço realizado |
| Oficina | Onde foi feito |
| Data entrada | Início |
| Data saída | Conclusão |
| Dias parado | Tempo de indisponibilidade |
| Valor | Custo da manutenção |
| Km | Quilometragem no momento |

**Totalizadores**:
- Total de manutenções
- Custo total
- Custo médio por manutenção
- Dias parados totais
- Custo por km rodado

**Visualizações**:
- Gráfico de barras: custo por veículo
- Timeline de manutenções
- Preventivas vs Corretivas (pizza)

---

### 3.2 Lucro por Veículo

**Objetivo**: Calcular o lucro líquido gerado por cada veículo.

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Placa | Identificação |
| Modelo | Marca/modelo |
| Receita total | Locações + adicionais |
| (-) Manutenções | Custos de manutenção |
| (-) Combustível | Diferenças de combustível |
| (-) IPVA | Proporcional ao período |
| (-) Licenciamento | Proporcional |
| (-) Seguro | Proporcional |
| (-) Depreciação | Calculada |
| (=) Lucro líquido | Resultado |
| Margem (%) | Lucro / Receita |

**Visualizações**:
- Ranking por lucro
- Gráfico de waterfall por veículo
- Scatter: Receita vs Custos

---

### 3.3 Despesas Veicular

**Objetivo**: Detalhar todas as despesas relacionadas aos veículos.

**Categorias de despesas**:
- Manutenção preventiva
- Manutenção corretiva
- Combustível
- IPVA
- Licenciamento
- Seguro
- Multas
- Lavagem
- Pneus
- Documentação
- Outros

**Informações a exibir**:

| Veículo | Manutenção | Combustível | IPVA | Licenc. | Seguro | Multas | Outros | Total |
|---------|------------|-------------|------|---------|--------|--------|--------|-------|
| [Placa] | | | | | | | | |

**Visualizações**:
- Stacked bar chart por veículo
- Treemap de despesas
- Evolução mensal de despesas

---

### 3.4 Veículo/Cliente

**Objetivo**: Histórico de locações por veículo mostrando os clientes.

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Veículo | Placa e modelo |
| Cliente | Nome do cliente |
| Período | Data início - fim |
| Dias | Duração |
| Valor | Total da locação |
| Km inicial | Quilometragem na saída |
| Km final | Quilometragem na devolução |
| Km rodado | Diferença |
| Ocorrências | Avarias, multas, etc. |

**Agrupamento**:
- Por veículo (mostrando todos os clientes)
- Por cliente (mostrando todos os veículos que locou)

---

### 3.5 Licenciamento

**Objetivo**: Controle de documentação e licenciamento dos veículos.

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Placa | Identificação |
| Modelo | Marca/modelo |
| IPVA vencimento | Data de vencimento |
| IPVA status | Pago/Pendente/Vencido |
| IPVA valor | Valor do imposto |
| Licenciamento vencimento | Data |
| Licenciamento status | Pago/Pendente/Vencido |
| Seguro vencimento | Data |
| Seguro status | Ativo/Vencido |
| Revisão | Próxima revisão (km/data) |

**Alertas**:
- Vermelho: Vencido
- Amarelo: Vence em 30 dias
- Verde: Em dia

**Visualizações**:
- Calendário de vencimentos
- Lista de pendências ordenada por urgência

---

### 3.6 Disponibilidade

**Objetivo**: Mostrar a disponibilidade da frota em tempo real e histórico.

**Visão atual (tempo real)**:

| Status | Quantidade | % |
|--------|------------|---|
| Disponível | | |
| Locado | | |
| Reservado | | |
| Em manutenção | | |
| Indisponível | | |

**Visão histórica**:
- Calendário com ocupação por dia
- Heatmap de ocupação (dias da semana × hora)

**Detalhamento por veículo**:
- Timeline visual de cada veículo no período
- Status em cada momento

---

### 3.7 Taxa de Ocupação por Grupo

**Objetivo**: Comparar a ocupação entre diferentes categorias de veículos.

**Informações a exibir**:

| Grupo | Veículos | Dias Disponíveis | Dias Locados | Taxa (%) | Receita | RevPAR |
|-------|----------|------------------|--------------|----------|---------|--------|
| Econômico | | | | | | |
| Intermediário | | | | | | |
| Executivo | | | | | | |
| SUV | | | | | | |
| Pickup | | | | | | |

**Visualizações**:
- Gráfico de barras comparativo
- Ranking de grupos
- Evolução por grupo ao longo do tempo

---

### 3.8 Depreciação de Frota

**Objetivo**: Calcular e acompanhar a depreciação dos veículos.

**Métodos de cálculo**:
- Linear (padrão)
- Por quilometragem
- FIPE

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Placa | Identificação |
| Modelo/Ano | Veículo |
| Valor aquisição | Preço de compra |
| Data aquisição | Quando foi comprado |
| Valor atual (FIPE) | Se disponível |
| Depreciação acumulada | Total depreciado |
| Valor contábil | Aquisição - Depreciação |
| Depreciação mensal | Valor mensal |
| Depreciação no período | Valor do período selecionado |

**Totalizadores**:
- Valor total da frota (aquisição)
- Depreciação acumulada total
- Valor contábil da frota

---

### 3.9 Tempo Médio Parado

**Objetivo**: Identificar a ociosidade dos veículos.

**Informações a exibir**:

| Veículo | Dias Disponíveis | Dias Parado | % Ociosidade | Motivos |
|---------|------------------|-------------|--------------|---------|
| [Placa] | | | | |

**Motivos de parada**:
- Sem demanda
- Manutenção
- Documentação pendente
- Aguardando inspeção
- Outros

**Visualizações**:
- Ranking de veículos mais ociosos
- Gráfico de pizza: motivos de parada
- Custo da ociosidade (dias × valor diária)

---

### 3.10 Quilometragem Média

**Objetivo**: Analisar o uso dos veículos em termos de quilometragem.

**Informações a exibir**:

| Veículo | Km Inicial | Km Final | Km Rodado | Km/Dia | Locações | Km/Locação |
|---------|------------|----------|-----------|--------|----------|------------|
| [Placa] | | | | | | |

**Totalizadores**:
- Km total da frota no período
- Média de km por veículo
- Média de km por locação

**Alertas**:
- Veículos com km muito acima da média (desgaste acelerado)
- Veículos com km muito abaixo (subutilização)

---

### 3.11 Custo Total de Propriedade (TCO)

**Objetivo**: Calcular o custo total para manter cada veículo na frota.

**Componentes do TCO**:
- Depreciação
- Financiamento (juros)
- IPVA
- Licenciamento
- Seguro
- Manutenções
- Combustível
- Pneus
- Multas

**Informações a exibir**:

| Veículo | Depreciação | IPVA | Seguro | Manutenção | Outros | TCO Total | TCO/mês | TCO/km |
|---------|-------------|------|--------|------------|--------|-----------|---------|--------|
| [Placa] | | | | | | | | |

**Visualizações**:
- Breakdown do TCO por componente
- Comparativo TCO vs Receita
- Veículos com TCO crítico

---

## 4. Clientes

### 4.1 Contrato/Locações (por cliente)

**Objetivo**: Histórico consolidado de contratos e locações por cliente.

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Cliente | Nome/Razão social |
| CPF/CNPJ | Documento |
| Nº Contratos/Locações | Quantidade total |
| Primeira contratação/locação | Data |
| Última contratação/locação | Data |
| Faturamento total | Valor acumulado |
| Ticket médio | Média por contrato/locação |
| Dias médios | Duração média |

**Detalhamento por cliente**:
- Lista consolidada de contratos e locações
- Veículos mais locados
- Formas de pagamento preferidas
- Histórico de ocorrências

---

### 4.2 Aniversariantes

**Objetivo**: Lista de clientes que fazem aniversário no período.

**Filtros específicos**:
- Mês
- Dia específico
- Faixa etária
- Apenas clientes ativos (locaram nos últimos X meses)

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Nome | Nome do cliente |
| Data nascimento | Data completa |
| Idade | Anos completos |
| Telefone | Para contato |
| Email | Para contato |
| Última locação | Quando locou pela última vez |
| Total locações | Histórico |

**Ações sugeridas**:
- Enviar mensagem de parabéns
- Oferecer desconto especial
- Exportar lista para campanha

---

### 4.3 CNH Vencidas

**Objetivo**: Clientes com CNH vencida ou próxima do vencimento.

**Filtros específicos**:
- Status (vencida/vencendo em 30/60/90 dias)
- Apenas clientes ativos

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Cliente | Nome |
| CPF | Documento |
| CNH | Número |
| Vencimento | Data |
| Dias para vencer | Ou dias vencido |
| Telefone | Contato |
| Email | Contato |
| Locação ativa? | Se tem locação em andamento |

**Alertas**:
- Vermelho: CNH vencida
- Amarelo: Vence em 30 dias
- Laranja: Vence em 60 dias

**Ações**:
- Notificar cliente
- Bloquear novas locações (se configurado)

---

### 4.4 Top Clientes (Ranking)

**Objetivo**: Identificar os clientes mais valiosos.

**Critérios de ranking**:
- Por faturamento
- Por quantidade de locações
- Por frequência
- Por tempo de relacionamento

**Informações a exibir**:

| Posição | Cliente | Tipo | Locações | Faturamento | Ticket Médio | Desde |
|---------|---------|------|----------|-------------|--------------|-------|
| 1 | | PF/PJ | | | | |
| ... | | | | | | |

**Análises**:
- Top 10/20/50 clientes
- Concentração de receita (Pareto)
- Clientes VIP (critérios customizáveis)

---

### 4.5 Frequência de Locações

**Objetivo**: Analisar a frequência com que clientes locam.

**Classificação**:
- Frequente: ≥ 1 locação/mês
- Regular: 1 locação a cada 2-3 meses
- Esporádico: 1 locação a cada 4-6 meses
- Infrequente: > 6 meses entre locações

**Informações a exibir**:

| Cliente | Total Locações | Primeira | Última | Intervalo Médio | Classificação |
|---------|----------------|----------|--------|-----------------|---------------|
| | | | | | |

**Visualizações**:
- Distribuição por classificação
- Gráfico de recência vs frequência

---

### 4.6 Tempo de Relacionamento

**Objetivo**: Analisar a longevidade do relacionamento com clientes.

**Informações a exibir**:

| Cliente | Desde | Meses | Total Locações | Faturamento Lifetime | Última Locação |
|---------|-------|-------|----------------|---------------------|----------------|
| | | | | | |

**Métricas**:
- LTV (Lifetime Value) por cliente
- Idade média do relacionamento
- Taxa de retenção

---

### 4.7 Histórico de Ocorrências

**Objetivo**: Registrar e analisar problemas com clientes.

**Tipos de ocorrências**:
- Avarias em veículos
- Multas de trânsito
- Devolução atrasada
- Inadimplência
- Reclamações
- Acidentes
- Documentação irregular
- Uso indevido do veículo

**Informações a exibir**:

| Data | Cliente | Tipo | Locação | Descrição | Valor | Status | Resolução |
|------|---------|------|---------|-----------|-------|--------|-----------|
| | | | | | | | |

**Análises**:
- Clientes com mais ocorrências
- Tipos mais frequentes
- Custo total de ocorrências

---

### 4.8 Clientes Inativos

**Objetivo**: Identificar clientes que não locam há algum tempo.

**Critério**: Clientes sem locação nos últimos X meses (configurável, padrão 6 meses)

**Informações a exibir**:

| Cliente | Última Locação | Dias Inativo | Total Histórico | Faturamento | Contato |
|---------|----------------|--------------|-----------------|-------------|---------|
| | | | | | |

**Ações sugeridas**:
- Campanha de reativação
- Pesquisa de satisfação
- Oferta especial

---

## 5. Contratos/Locações

### 5.1 Visão Geral

**Objetivo**: Visão completa de todos os contratos e locações.

**Filtros específicos**:
- Status (ativo/finalizado/cancelado)
- Tipo (diária/semanal/mensal/anual)
- Veículo
- Cliente
- Funcionário responsável

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Nº Contrato | Identificação |
| Cliente | Nome |
| Veículo | Placa/Modelo |
| Data início | Início da locação |
| Data fim prevista | Previsão de devolução |
| Data fim real | Devolução efetiva |
| Dias | Duração |
| Valor diária | Preço praticado |
| Valor total | Montante da locação |
| Adicionais | Taxas e serviços |
| Status | Ativo/Finalizado/Cancelado |

**Totalizadores**:
- Total de locações
- Valor total
- Média de dias
- Ticket médio

---

### 5.2 Por Período

**Objetivo**: Análise de locações por período de tempo.

**Agrupamentos disponíveis**:
- Por dia
- Por semana
- Por mês
- Por trimestre
- Por ano

**Informações a exibir**:

| Período | Locações | Dias | Receita | Ticket Médio | Variação |
|---------|----------|------|---------|--------------|----------|
| | | | | | |

**Visualizações**:
- Gráfico de linha: evolução
- Heatmap: dia da semana × hora
- Sazonalidade: meses do ano

---

### 5.3 Por Forma de Pagamento

**Objetivo**: Analisar locações por método de pagamento.

**Informações a exibir**:

| Forma de Pagamento | Locações | % | Valor Total | % | Ticket Médio |
|--------------------|----------|---|-------------|---|--------------|
| Cartão de crédito | | | | | |
| Cartão de débito | | | | | |
| PIX | | | | | |
| Dinheiro | | | | | |
| Boleto | | | | | |
| Faturado | | | | | |

**Visualizações**:
- Gráfico de pizza
- Evolução ao longo do tempo

---

### 5.4 Extensões de Contrato

**Objetivo**: Analisar locações que foram estendidas.

**Informações a exibir**:

| Contrato | Cliente | Veículo | Período Original | Extensão | Novo Fim | Motivo | Valor Adicional |
|----------|---------|---------|------------------|----------|----------|--------|-----------------|
| | | | | | | | |

**Métricas**:
- % de locações com extensão
- Média de dias de extensão
- Receita adicional de extensões

---

### 5.5 Trocas de Veículo

**Objetivo**: Registrar trocas de veículo durante locações.

**Informações a exibir**:

| Contrato | Cliente | Veículo Original | Veículo Novo | Data Troca | Motivo | Diferença Valor |
|----------|---------|------------------|--------------|------------|--------|-----------------|
| | | | | | | |

**Motivos comuns**:
- Upgrade solicitado
- Downgrade
- Problema mecânico
- Manutenção preventiva
- Indisponibilidade
- Preferência do cliente

---

## 6. Operacional

### 6.1 Checklists Realizados

**Objetivo**: Controlar a execução de checklists de entrada/saída.

**Filtros específicos**:
- Tipo (entrada/saída)
- Veículo
- Funcionário
- Período
- Com/sem pendências

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Data/Hora | Momento do checklist |
| Tipo | Entrada ou Saída |
| Veículo | Placa |
| Locação | Referência |
| Funcionário | Quem realizou |
| Itens OK | Quantidade |
| Itens com problema | Quantidade |
| Observações | Notas |
| Fotos | Quantidade de fotos |

**Métricas**:
- Total de checklists realizados
- Taxa de checklists com problemas
- Tempo médio de execução
- Funcionários com mais checklists

---

### 6.2 Avarias e Sinistros

**Objetivo**: Controle detalhado de danos aos veículos.

**Classificação**:
- Avaria leve (arranhão, amassado pequeno)
- Avaria média (dano significativo)
- Sinistro (perda parcial ou total)

**Informações a exibir**:

| Data | Veículo | Cliente | Locação | Tipo | Descrição | Valor Reparo | Valor Cobrado | Status |
|------|---------|---------|---------|------|-----------|--------------|---------------|--------|
| | | | | | | | | |

**Métricas**:
- Total de avarias no período
- Custo total de reparos
- Valor total cobrado
- Diferença (prejuízo)
- Taxa de avarias (% das locações)

---

### 6.3 Multas de Trânsito

**Objetivo**: Gerenciar multas recebidas durante locações.

**Informações a exibir**:

| Campo | Descrição |
|-------|-----------|
| Data infração | Quando ocorreu |
| Veículo | Placa |
| Locação | Referência |
| Cliente | Responsável |
| Tipo multa | Descrição da infração |
| Local | Onde ocorreu |
| Valor | Montante da multa |
| Pontuação | Pontos na CNH |
| Status | Pendente/Indicado/Pago/Recurso |
| Cobrado cliente | Sim/Não |

**Fluxo**:
1. Recebimento da multa
2. Identificação do condutor
3. Indicação (se aplicável)
4. Cobrança do cliente
5. Pagamento

---

### 6.4 Devoluções Antecipadas

**Objetivo**: Analisar locações devolvidas antes do previsto.

**Informações a exibir**:

| Contrato | Cliente | Veículo | Previsão | Devolução | Dias Antecipado | Motivo | Impacto Receita |
|----------|---------|---------|----------|-----------|-----------------|--------|-----------------|
| | | | | | | | |

**Motivos comuns**:
- Viagem cancelada
- Problema com veículo
- Insatisfação
- Mudança de planos
- Outros

**Métricas**:
- % de locações com devolução antecipada
- Média de dias antecipados
- Perda de receita estimada

---

### 6.5 Devoluções Atrasadas

**Objetivo**: Controlar locações com devolução após o prazo.

**Informações a exibir**:

| Contrato | Cliente | Veículo | Previsão | Devolução | Dias Atraso | Valor Adicional | Multa | Status |
|----------|---------|---------|----------|-----------|-------------|-----------------|-------|--------|
| | | | | | | | | |

**Ações**:
- Alertas automáticos
- Contato com cliente
- Cobrança de diárias adicionais
- Aplicação de multa

**Métricas**:
- % de locações com atraso
- Média de dias de atraso
- Receita adicional de atrasos

---

### 6.6 Reservas Canceladas

**Objetivo**: Analisar reservas que não se converteram em locações.

**Informações a exibir**:

| Data Reserva | Cliente | Veículo | Período Previsto | Data Cancelamento | Motivo | Antecedência | Penalidade |
|--------------|---------|---------|------------------|-------------------|--------|--------------|------------|
| | | | | | | | |

**Motivos comuns**:
- Mudança de planos
- Encontrou preço melhor
- Problema pessoal
- Insatisfação no atendimento
- No-show (não compareceu)
- Veículo indisponível
- Outros

**Métricas**:
- Total de reservas
- Total de cancelamentos
- Taxa de cancelamento (%)
- Receita perdida estimada

---

### 6.7 Turnaround (Tempo de Retorno)

**Objetivo**: Medir o tempo entre a devolução e a próxima locação.

**Definição**: Tempo desde a devolução do veículo até sair novamente locado.

**Informações a exibir**:

| Veículo | Devolução | Próxima Saída | Turnaround (horas) | Atividades |
|---------|-----------|---------------|---------------------|------------|
| | | | | |

**Atividades no turnaround**:
- Checklist de entrada
- Lavagem
- Abastecimento
- Inspeção
- Pequenos reparos
- Checklist de saída

**Métricas**:
- Turnaround médio
- Turnaround por grupo de veículo
- Meta vs realizado

---

### 6.8 Combustível

**Objetivo**: Controlar diferenças de combustível nas locações.

**Informações a exibir**:

| Locação | Veículo | Cliente | Nível Saída | Nível Devolução | Diferença (L) | Valor Cobrado | Status |
|---------|---------|---------|-------------|-----------------|---------------|---------------|--------|
| | | | | | | | |

**Métricas**:
- Total de litros de diferença
- Valor total cobrado
- Locações com cobrança de combustível (%)

---

## 7. Faturas

### 7.1 Vencidas/A Vencer

**Objetivo**: Gestão de faturas por status de vencimento.

**Visões**:
- Vencidas: Faturas com vencimento passado e não pagas
- A vencer: Faturas futuras

**Informações a exibir**:

| Fatura | Cliente | Vencimento | Valor Original | Juros/Multa | Valor Total | Dias | Status |
|--------|---------|------------|----------------|-------------|-------------|------|--------|
| | | | | | | | |

**Aging (Vencidas)**:
- 1-7 dias
- 8-15 dias
- 16-30 dias
- 31-60 dias
- 61-90 dias
- > 90 dias

**A vencer**:
- Hoje
- Próximos 7 dias
- Próximos 15 dias
- Próximos 30 dias

---

### 7.2 Por Veículo

**Objetivo**: Faturas associadas a cada veículo.

**Filtros**:
- Período
- Filial
- Visualização: Agrupado por veículo ou Individualizado
- Veículo: Todos ou um veículo específico

**Informações a exibir**:

**Visualização agrupada**:

| Veículo | Total Faturas | Valor Total | Pagas | Pendentes | Vencidas |
|---------|---------------|-------------|-------|-----------|----------|
| | | | | | |

**Visualização individualizada**:

| Veículo | Fatura | Cliente | Descrição | Vencimento | Valor Total | Status |
|---------|--------|---------|-----------|------------|-------------|--------|
| | | | | | | |

**Detalhamento**: O filtro de veículo permite restringir a visão agrupada ou individualizada a um veículo específico.

---

### 7.3 Pagar/Receber

**Objetivo**: Visão consolidada de contas a pagar e receber.

**Filtros específicos**:
- Cliente: restringe **Contas a Receber** ao cliente selecionado. Se usado sem fornecedor, o lado de contas a pagar fica vazio para manter o saldo coerente com o subconjunto filtrado.
- Fornecedor: restringe **Contas a Pagar** ao fornecedor selecionado. Se usado sem cliente, o lado de contas a receber fica vazio.
- Veículo: restringe contas a pagar e receber pelo veículo associado no cabeçalho (`financeiro.id_veiculo`) ou nos itens (`financeiro_itens.id_veiculo`).
- Status: todos, pago, pendente ou vencido. Aplica nos dois lados.

**Contas a Receber**:

| Vencimento | Cliente | Descrição | Valor | Status |
|------------|---------|-----------|-------|--------|
| | | | | |

**Contas a Pagar**:

| Vencimento | Fornecedor | Descrição | Valor | Status |
|------------|------------|-----------|-------|--------|
| | | | | |

**Resumo**:
- Total a receber
- Total a pagar
- Saldo (receber - pagar)
- Projeção de fluxo

---

## 8. Comercial

### 8.1 Taxa de Conversão

**Objetivo**: Medir a efetividade comercial (orçamentos → locações).

**Funil**:
1. Contatos/Consultas recebidas
2. Orçamentos gerados
3. Reservas confirmadas
4. Locações efetivadas

**Informações a exibir**:

| Etapa | Quantidade | Taxa Conversão | Valor Médio |
|-------|------------|----------------|-------------|
| Contatos | | - | |
| Orçamentos | | % do anterior | |
| Reservas | | % do anterior | |
| Locações | | % do anterior | |

**Conversão geral**: Locações / Contatos × 100

**Análises**:
- Por funcionário
- Por canal de origem
- Por período

---

### 8.2 Origem das Locações

**Objetivo**: Identificar os canais mais efetivos.

**Canais**:
- Balcão/Presencial
- Telefone
- Website
- WhatsApp
- Aplicativo
- OTA (Online Travel Agency)
- Parceiros/Convênios
- Indicação de clientes
- Redes sociais
- Google Ads
- Outros

**Informações a exibir**:

| Canal | Contatos | Locações | Conversão | Receita | Ticket Médio | CAC |
|-------|----------|----------|-----------|---------|--------------|-----|
| | | | | | | |

---

### 8.3 Promoções Utilizadas

**Objetivo**: Avaliar a efetividade das promoções.

**Informações a exibir**:

| Promoção | Período | Locações | Desconto Total | Receita Gerada | ROI |
|----------|---------|----------|----------------|----------------|-----|
| | | | | | |

**Análises**:
- Promoções mais utilizadas
- Impacto no ticket médio
- Incremento de locações vs período sem promoção

---

### 8.4 Descontos Concedidos

**Objetivo**: Controlar os descontos aplicados.

**Informações a exibir**:

| Período | Locações | Valor Bruto | Descontos | Valor Líquido | % Desconto Médio |
|---------|----------|-------------|-----------|---------------|------------------|
| | | | | | |

**Detalhamento**:
- Por funcionário (quem deu o desconto)
- Por tipo de desconto (promoção, negociação, fidelidade)
- Por cliente

**Alertas**:
- Funcionários com desconto acima da média
- Descontos fora da política

---

### 8.5 Análise de Temporada

**Objetivo**: Entender o comportamento sazonal do negócio.

**Dimensões**:
- Meses do ano
- Dias da semana
- Feriados e eventos
- Alta vs baixa temporada

**Informações a exibir**:

| Período | Locações | Ocupação | Diária Média | Receita | Variação vs Média |
|---------|----------|----------|--------------|---------|-------------------|
| Janeiro | | | | | |
| ... | | | | | |

**Visualizações**:
- Gráfico de linha: sazonalidade anual
- Heatmap: dias da semana
- Comparativo com anos anteriores

---

## 9. Fornecedores

### 9.1 Compras e Pagamentos

**Objetivo**: Análise de compras e relacionamento com fornecedores.

**Informações a exibir**:

| Fornecedor | CNPJ | Categoria | Compras (Qtd) | Valor Total | Ticket Médio | Última Compra |
|------------|------|-----------|---------------|-------------|--------------|---------------|
| | | | | | | |

**Detalhamento por fornecedor**:
- Lista de compras/pagamentos
- Histórico de preços
- Avaliação de qualidade (se houver)

---

### 9.2 Fornecedor Investidor

**Objetivo**: Relatório específico para fornecedores que investem veículos na frota.

**Informações a exibir**:

| Investidor | Veículos | Valor Investido | Receita Gerada | Comissão Devida | Comissão Paga | Saldo |
|------------|----------|-----------------|----------------|-----------------|---------------|-------|
| | | | | | | |

**Detalhamento por investidor**:
- Lista de veículos
- Performance de cada veículo
- Histórico de pagamentos de comissão
- Extrato do período

**Diagnóstico por veículo**:
- O relatório deve mostrar cada veículo vinculado ao investidor com placa/modelo, grupo, tipo/valor de comissão configurado, receita gerada, comissão pendente, comissão paga e saldo.
- A receita e as comissões vêm de registros já gerados em `comissoes_investidores` no período; a configuração do grupo apenas define a regra para novas gerações.
- Quando não houver comissão gerada para um veículo elegível, exibir um diagnóstico claro: comissão gerada, sem fatura paga no período, grupo sem comissão ou comissão mensal ainda não gerada.
- Comissões históricas geradas no período devem aparecer nos valores financeiros mesmo se o veículo estiver inativo no cadastro atual; nesse caso, o veículo deve aparecer no detalhamento como histórico/inativo, sem entrar na contagem de veículos ativos.
- Para contagem de veículos ativos, excluir os status inativos padrão do sistema: `V`, `RO` e `E`.

**Filtros específicos**:
- Fornecedor investidor (opcional): lista apenas fornecedores com `investidor = 1` e filtra resumo, veículos e comissões do investidor selecionado.
- Filial (opcional): filtra veículos ativos e comissões pela filial do veículo, respeitando também as filiais permitidas ao usuário.
- Modelo: `Detalhado` é o padrão e mostra cada fornecedor e, logo abaixo, seus veículos com recuo visual; `Agrupado` mostra apenas fornecedores. O filtro não altera totais nem consulta de dados.

---

## 10. Funcionários

### 10.1 Vendas

**Objetivo**: Relatório de vendas/locações por funcionário.

**Informações a exibir**:

| Funcionário | Cargo | Locações | Receita | Ticket Médio | Adicionais Vendidos | Taxa Conversão |
|-------------|-------|----------|---------|--------------|---------------------|----------------|
| | | | | | | |

---

### 10.2 Comissões

**Objetivo**: Cálculo e controle de comissões dos funcionários.

**Informações a exibir**:

| Funcionário | Receita Base | % Comissão | Valor Comissão | Bônus | Total | Status Pgto |
|-------------|--------------|------------|----------------|-------|-------|-------------|
| | | | | | | |

**Regras de comissão** (configuráveis):
- % sobre locações
- % sobre adicionais
- Bônus por meta atingida
- Escalonamento por faixa

---

### 10.3 Produtividade

**Objetivo**: Medir a eficiência de cada funcionário.

**Métricas de produtividade**:
- Locações por dia trabalhado
- Receita por dia trabalhado
- Checklists realizados
- Tempo médio de atendimento
- Ocorrências/problemas

**Informações a exibir**:

| Funcionário | Dias Trabalhados | Locações | Locações/Dia | Receita | Receita/Dia | Checklists |
|-------------|------------------|----------|--------------|---------|-------------|------------|
| | | | | | | |

---

### 10.4 Metas vs Realizado

**Objetivo**: Acompanhar o atingimento de metas individuais e coletivas.

**Informações a exibir**:

| Funcionário | Meta Receita | Realizado | % Atingido | Meta Locações | Realizado | % Atingido |
|-------------|--------------|-----------|------------|---------------|-----------|------------|
| | | | | | | |
| **TOTAL** | | | | | | |

**Visualizações**:
- Gráfico de barras: meta vs realizado
- Velocímetro/gauge de atingimento
- Projeção de fechamento do mês

---

## 11. Comparativos

### 11.1 Comparativo Mensal/Anual

**Objetivo**: Comparar indicadores entre períodos.

**Indicadores comparados**:
- Faturamento
- Locações
- Ocupação
- Ticket médio
- Lucro

**Informações a exibir**:

| Indicador | Período Atual | Período Anterior | Variação (R$) | Variação (%) |
|-----------|---------------|------------------|---------------|--------------|
| | | | | |

**Visualizações**:
- Gráfico de barras lado a lado
- Gráfico de linha com múltiplas séries
- Tabela com setas indicando tendência

---

### 11.2 Comparativo entre Filiais

**Objetivo**: Comparar a performance das diferentes unidades.

**Informações a exibir**:

| Filial | Veículos | Locações | Ocupação | Receita | Ticket Médio | Margem | Ranking |
|--------|----------|----------|----------|---------|--------------|--------|---------|
| Matriz | | | | | | | |
| Filial 1 | | | | | | | |
| Filial 2 | | | | | | | |
| **TOTAL** | | | | | | | |

**Visualizações**:
- Mapa com performance por região
- Gráfico de barras comparativo
- Ranking de filiais

---

### 11.3 Ranking de Veículos

**Objetivo**: Classificar veículos por diferentes critérios.

**Critérios disponíveis**:
- Receita gerada
- Taxa de ocupação
- Lucro líquido
- ROI
- Quantidade de locações
- Avaliação dos clientes

**Top 10 / Bottom 10**:

| Posição | Veículo | Grupo | Critério | Valor |
|---------|---------|-------|----------|-------|
| | | | | |

**Insights automáticos**:
- Veículos candidatos a expansão (alta demanda)
- Veículos candidatos a venda (baixa performance)
- Grupos mais rentáveis

---

### 11.4 Análise de Tendências

**Objetivo**: Projetar cenários futuros baseado em histórico.

**Indicadores analisados**:
- Receita
- Locações
- Ocupação
- Ticket médio

**Informações a exibir**:

| Indicador | Tendência | Projeção 3 meses | Projeção 6 meses | Confiança |
|-----------|-----------|------------------|------------------|-----------|
| Receita | ↑ Crescimento | R$ X | R$ Y | Alta |
| Locações | → Estável | N | M | Média |
| ... | | | | |

**Visualizações**:
- Gráfico de linha com linha de tendência
- Área de confiança (intervalo)
- Previsão vs histórico (validação do modelo)

**Metodologia**:
- Média móvel
- Regressão linear
- Sazonalidade (se detectada)

---

## Referências Técnicas

### Tabelas Principais Envolvidas

| Relatório | Tabelas |
|-----------|---------|
| Locações | `locacoes`, `clientes`, `veiculos`, `funcionarios` |
| Financeiro | `lancamentos`, `faturas`, `contas_bancarias`, `plano_contas` |
| Veículos | `veiculos`, `grupos`, `manutencoes`, `veiculos_despesas` |
| Clientes | `clientes`, `locacoes`, `faturas` |
| Operacional | `checklists`, `avarias`, `multas`, `reservas` |

### Permissões Sugeridas

```
relatorios.kpis.*
relatorios.financeiro.*
relatorios.veicular.*
relatorios.clientes.*
relatorios.contratos.*
relatorios.operacional.*
relatorios.faturas.*
relatorios.comercial.*
relatorios.fornecedores.*
relatorios.funcionarios.*
relatorios.comparativos.*
```

### Exportação

Todos os relatórios devem suportar:
- **PDF**: Layout otimizado para impressão
- **Excel**: Dados brutos + gráficos
- **CSV**: Apenas dados para integração

---

## Changelog

| Data | Versão | Descrição |
|------|--------|-----------|
| 2026-01-27 | 1.0 | Documentação inicial com 65 relatórios |
