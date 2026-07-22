<?php

namespace App\Models\Relatorios;

use App\Core\Auth;

/**
 * Model para relatorios financeiros
 *
 * Metodos de agregacao para os 10 relatorios financeiros:
 * - Movimentacoes
 * - Faturamento
 * - DRE (Demonstrativo de Resultado)
 * - Livro Caixa
 * - Contas Bancarias
 * - Plano de Contas
 * - Projecao de Receitas
 * - Rentabilidade
 * - Inadimplencia
 * - Taxas e Servicos
 */
class FinanceiroReport extends BaseReportModel
{
    /**
     * Extrai descricao traduzida do campo JSON descricao_i18n
     */
    private function extractDescricaoI18n(?string $json): string
    {
        if (empty($json)) {
            return '';
        }

        $translations = json_decode($json, true);
        if (!is_array($translations)) {
            return '';
        }

        $locale = current_locale();

        if (isset($translations[$locale])) {
            return $translations[$locale];
        }

        // Fallback para pt_BR
        if (isset($translations['pt_BR'])) {
            return $translations['pt_BR'];
        }

        // Fallback para primeiro valor disponivel
        return reset($translations) ?: '';
    }

    /**
     * Aplica filtros de filial na query
     */
    private function applyFilialFilter($query, string $filialWhere, array $filialParams, string $filialId, string $prefix = 'f'): void
    {
        if (!empty($filialWhere)) {
            $filialWherePrefixed = str_replace('id_matriz_filial', "{$prefix}.id_matriz_filial", $filialWhere);
            $query->whereRaw($filialWherePrefixed, $filialParams);
        }

        if (!empty($filialId)) {
            $query->where("{$prefix}.id_matriz_filial", '=', (int) $filialId);
        }
    }

    /**
     * Deriva a origem de um lancamento financeiro
     */
    private function deriveOrigem(array $row): string
    {
        if (!empty($row['id_locacao'])) {
            return 'locacao';
        }
        if (!empty($row['id_contrato'])) {
            return 'contrato';
        }
        return 'manual';
    }

    // -------------------------------------------------------------------------
    // 1. Movimentacoes
    // -------------------------------------------------------------------------

    /**
     * Movimentacoes financeiras no periodo
     *
     * @return array{totals: array, details: array, chart: array, total: int}
     */
    public function movimentacoes(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $tipo = '',
        string $planoContaId = '',
        string $contaId = '',
        string $status = '',
        int $page = 1,
        int $perPage = 20
    ): array {
        // --- Totais ---
        $queryTotals = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                COALESCE(SUM(CASE WHEN f.tipo = 'R' THEN f.valor_total ELSE 0 END), 0) AS total_receitas,
                COALESCE(SUM(CASE WHEN f.tipo = 'D' THEN f.valor_total ELSE 0 END), 0) AS total_despesas,
                COUNT(*) AS quantidade
            ")
            ->whereRaw('f.data_criada BETWEEN ? AND ?', [$dataInicio, $dataFim]);

        $this->applyFilialFilter($queryTotals, $filialWhere, $filialParams, $filialId);

        if (!empty($tipo)) {
            $queryTotals->whereRaw('f.tipo = ?', [$tipo]);
        }
        if (!empty($planoContaId)) {
            $queryTotals->whereRaw('f.id_plano_de_conta = ?', [(int) $planoContaId]);
        }
        if (!empty($contaId)) {
            $queryTotals->whereRaw('f.id_conta = ?', [(int) $contaId]);
        }
        if (!empty($status)) {
            $queryTotals->whereRaw('f.pago = ?', [$status]);
        }

        $resultTotals = $queryTotals->first();

        $totalReceitas = (float) ($resultTotals['total_receitas'] ?? 0);
        $totalDespesas = (float) ($resultTotals['total_despesas'] ?? 0);
        $saldo = $totalReceitas - $totalDespesas;
        $quantidade = (int) ($resultTotals['quantidade'] ?? 0);

        // --- Contagem para paginacao ---
        $queryCount = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw('COUNT(*) AS total')
            ->whereRaw('f.data_criada BETWEEN ? AND ?', [$dataInicio, $dataFim]);

        $this->applyFilialFilter($queryCount, $filialWhere, $filialParams, $filialId);

        if (!empty($tipo)) {
            $queryCount->whereRaw('f.tipo = ?', [$tipo]);
        }
        if (!empty($planoContaId)) {
            $queryCount->whereRaw('f.id_plano_de_conta = ?', [(int) $planoContaId]);
        }
        if (!empty($contaId)) {
            $queryCount->whereRaw('f.id_conta = ?', [(int) $contaId]);
        }
        if (!empty($status)) {
            $queryCount->whereRaw('f.pago = ?', [$status]);
        }

        $totalRows = (int) ($queryCount->first()['total'] ?? 0);

        // --- Detalhes paginados ---
        $queryDetails = $this->qb
            ->table('financeiro', 'f')
            ->select([
                'f.id',
                'f.data_criada',
                'f.tipo',
                'f.descricao',
                'f.valor_total',
                'f.pago',
                'f.id_locacao',
                'f.id_contrato',
            ])
            ->selectRaw('pc.descricao_i18n AS categoria_i18n')
            ->selectRaw('cb.nome AS conta_nome')
            ->selectRaw('fp.nome AS forma_pagamento_nome')
            ->leftJoin('planos_de_contas', 'pc', 'f.id_plano_de_conta', '=', 'pc.id')
            ->leftJoin('contas_bancarias', 'cb', 'f.id_conta', '=', 'cb.id')
            ->leftJoin('formas_pagamento', 'fp', 'f.id_forma_pagamento', '=', 'fp.id')
            ->whereRaw('f.data_criada BETWEEN ? AND ?', [$dataInicio, $dataFim])
            ->orderByDesc('f.data_criada')
            ->paginate($page, $perPage);

        $this->applyFilialFilter($queryDetails, $filialWhere, $filialParams, $filialId);

        if (!empty($tipo)) {
            $queryDetails->whereRaw('f.tipo = ?', [$tipo]);
        }
        if (!empty($planoContaId)) {
            $queryDetails->whereRaw('f.id_plano_de_conta = ?', [(int) $planoContaId]);
        }
        if (!empty($contaId)) {
            $queryDetails->whereRaw('f.id_conta = ?', [(int) $contaId]);
        }
        if (!empty($status)) {
            $queryDetails->whereRaw('f.pago = ?', [$status]);
        }

        $rows = $queryDetails->get();

        $details = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'data' => $row['data_criada'],
                'tipo' => $row['tipo'],
                'categoria' => $this->extractDescricaoI18n($row['categoria_i18n'] ?? null),
                'descricao' => $row['descricao'] ?? '',
                'valor' => (float) $row['valor_total'],
                'status' => $row['pago'] === 'S' ? 'pago' : 'pendente',
                'conta' => $row['conta_nome'] ?? '-',
                'forma_pagamento' => $row['forma_pagamento_nome'] ?? '-',
                'origem' => $this->deriveOrigem($row),
            ];
        }, $rows);

        // --- Chart: receitas vs despesas por mes ---
        $queryChart = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                YEAR(f.data_criada) AS ano,
                MONTH(f.data_criada) AS mes,
                COALESCE(SUM(CASE WHEN f.tipo = 'R' THEN f.valor_total ELSE 0 END), 0) AS receitas,
                COALESCE(SUM(CASE WHEN f.tipo = 'D' THEN f.valor_total ELSE 0 END), 0) AS despesas
            ")
            ->whereRaw('f.data_criada BETWEEN ? AND ?', [$dataInicio, $dataFim])
            ->groupBy('ano')
            ->groupBy('mes')
            ->orderByRaw('ano ASC, mes ASC');

        $this->applyFilialFilter($queryChart, $filialWhere, $filialParams, $filialId);

        if (!empty($tipo)) {
            $queryChart->whereRaw('f.tipo = ?', [$tipo]);
        }
        if (!empty($planoContaId)) {
            $queryChart->whereRaw('f.id_plano_de_conta = ?', [(int) $planoContaId]);
        }
        if (!empty($contaId)) {
            $queryChart->whereRaw('f.id_conta = ?', [(int) $contaId]);
        }
        if (!empty($status)) {
            $queryChart->whereRaw('f.pago = ?', [$status]);
        }

        $chartRows = $queryChart->get();

        $chartLabels = [];
        $chartReceitas = [];
        $chartDespesas = [];

        foreach ($chartRows as $cr) {
            $chartLabels[] = str_pad($cr['mes'], 2, '0', STR_PAD_LEFT) . '/' . $cr['ano'];
            $chartReceitas[] = (float) $cr['receitas'];
            $chartDespesas[] = (float) $cr['despesas'];
        }

        return [
            'totals' => [
                'total_receitas' => $totalReceitas,
                'total_despesas' => $totalDespesas,
                'saldo' => $saldo,
                'quantidade' => $quantidade,
            ],
            'details' => $details,
            'chart' => [
                'labels' => $chartLabels,
                'receitas' => $chartReceitas,
                'despesas' => $chartDespesas,
            ],
            'total' => $totalRows,
        ];
    }

    // -------------------------------------------------------------------------
    // 2. Faturamento
    // -------------------------------------------------------------------------

    /**
     * Faturamento (receitas) no periodo
     *
     * @return array{totals: array, details: array, chart: array}
     */
    public function faturamento(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $formaPagamentoId = '',
        string $statusPagamento = 'S'
    ): array {
        $statusPagamento = in_array($statusPagamento, ['S', 'N', 'all'], true)
            ? $statusPagamento
            : 'S';

        // --- Totais ---
        $queryTotals = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                COALESCE(SUM(f.valor_total), 0) AS faturamento_bruto,
                COALESCE(SUM(f.desconto), 0) AS descontos,
                COUNT(*) AS total_lancamentos
            ")
            ->whereRaw('f.tipo = ?', ['R'])
            ->whereRaw('f.data_criada BETWEEN ? AND ?', [$dataInicio, $dataFim]);

        $this->applyFilialFilter($queryTotals, $filialWhere, $filialParams, $filialId);

        if (!empty($formaPagamentoId)) {
            $queryTotals->whereRaw('f.id_forma_pagamento = ?', [(int) $formaPagamentoId]);
        }
        if ($statusPagamento !== 'all') {
            $queryTotals->whereRaw('f.pago = ?', [$statusPagamento]);
        }

        $resultTotals = $queryTotals->first();

        $faturamentoBruto = (float) ($resultTotals['faturamento_bruto'] ?? 0);
        $descontos = (float) ($resultTotals['descontos'] ?? 0);
        $faturamentoLiquido = $faturamentoBruto - $descontos;
        $totalLancamentos = (int) ($resultTotals['total_lancamentos'] ?? 0);

        // --- Detalhes por origem ---
        $queryOrigem = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                CASE
                    WHEN f.id_locacao IS NOT NULL AND f.id_locacao > 0 THEN 'locacao'
                    WHEN f.id_contrato IS NOT NULL AND f.id_contrato > 0 THEN 'contrato'
                    ELSE 'manual'
                END AS origem,
                COUNT(*) AS quantidade,
                COALESCE(SUM(f.valor_total), 0) AS valor_total
            ")
            ->whereRaw('f.tipo = ?', ['R'])
            ->whereRaw('f.data_criada BETWEEN ? AND ?', [$dataInicio, $dataFim])
            ->groupBy('origem')
            ->orderByRaw('valor_total DESC');

        $this->applyFilialFilter($queryOrigem, $filialWhere, $filialParams, $filialId);

        if (!empty($formaPagamentoId)) {
            $queryOrigem->whereRaw('f.id_forma_pagamento = ?', [(int) $formaPagamentoId]);
        }
        if ($statusPagamento !== 'all') {
            $queryOrigem->whereRaw('f.pago = ?', [$statusPagamento]);
        }

        $porOrigem = $queryOrigem->get();

        $porOrigemDetails = array_map(function ($row) use ($faturamentoBruto) {
            return [
                'nome' => $row['origem'],
                'qtd' => (int) $row['quantidade'],
                'valor' => (float) $row['valor_total'],
                'percentual' => $this->pct((float) $row['valor_total'], $faturamentoBruto),
            ];
        }, $porOrigem);

        // --- Detalhes por forma de pagamento ---
        $queryPagamento = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                fp.nome AS forma_pagamento,
                COUNT(*) AS quantidade,
                COALESCE(SUM(f.valor_total), 0) AS valor_total
            ")
            ->leftJoin('formas_pagamento', 'fp', 'f.id_forma_pagamento', '=', 'fp.id')
            ->whereRaw('f.tipo = ?', ['R'])
            ->whereRaw('f.data_criada BETWEEN ? AND ?', [$dataInicio, $dataFim])
            ->groupBy(['f.id_forma_pagamento', 'fp.nome'])
            ->orderByRaw('valor_total DESC');

        $this->applyFilialFilter($queryPagamento, $filialWhere, $filialParams, $filialId);

        if (!empty($formaPagamentoId)) {
            $queryPagamento->whereRaw('f.id_forma_pagamento = ?', [(int) $formaPagamentoId]);
        }
        if ($statusPagamento !== 'all') {
            $queryPagamento->whereRaw('f.pago = ?', [$statusPagamento]);
        }

        $porPagamento = $queryPagamento->get();

        $porPagamentoDetails = array_map(function ($row) use ($faturamentoBruto) {
            return [
                'nome' => $row['forma_pagamento'] ?? '-',
                'qtd' => (int) $row['quantidade'],
                'valor' => (float) $row['valor_total'],
                'percentual' => $this->pct((float) $row['valor_total'], $faturamentoBruto),
            ];
        }, $porPagamento);

        // --- Chart: evolucao mensal do faturamento ---
        $queryChart = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                YEAR(f.data_criada) AS ano,
                MONTH(f.data_criada) AS mes,
                COALESCE(SUM(f.valor_total), 0) AS faturamento
            ")
            ->whereRaw('f.tipo = ?', ['R'])
            ->whereRaw('f.data_criada BETWEEN ? AND ?', [$dataInicio, $dataFim])
            ->groupBy('ano')
            ->groupBy('mes')
            ->orderByRaw('ano ASC, mes ASC');

        $this->applyFilialFilter($queryChart, $filialWhere, $filialParams, $filialId);

        if (!empty($formaPagamentoId)) {
            $queryChart->whereRaw('f.id_forma_pagamento = ?', [(int) $formaPagamentoId]);
        }
        if ($statusPagamento !== 'all') {
            $queryChart->whereRaw('f.pago = ?', [$statusPagamento]);
        }

        $chartRows = $queryChart->get();

        $chartLabels = [];
        $chartValues = [];

        foreach ($chartRows as $cr) {
            $chartLabels[] = str_pad($cr['mes'], 2, '0', STR_PAD_LEFT) . '/' . $cr['ano'];
            $chartValues[] = (float) $cr['faturamento'];
        }

        return [
            'totals' => [
                'faturamento_bruto' => $faturamentoBruto,
                'descontos' => $descontos,
                'faturamento_liquido' => $faturamentoLiquido,
                'total_lancamentos' => $totalLancamentos,
            ],
            'details' => [
                'por_origem' => $porOrigemDetails,
                'por_forma_pagamento' => $porPagamentoDetails,
            ],
            'chart' => [
                'labels' => $chartLabels,
                'datasets' => [
                    [
                        'label' => t('modules.relatorios.financeiro.faturamento.chart_evolucao'),
                        'data' => $chartValues,
                    ],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // 3. DRE (Demonstrativo de Resultado do Exercicio)
    // -------------------------------------------------------------------------

    /**
     * DRE - Demonstrativo de Resultado do Exercicio
     *
     * @return array{totals: array, details: array, chart: array}
     */
    public function dre(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $statusPagamento = 'S'
    ): array {
        $statusPagamento = in_array($statusPagamento, ['S', 'N', 'all'], true)
            ? $statusPagamento
            : 'S';

        // --- Receita bruta ---
        $queryReceita = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                COALESCE(SUM(f.valor_total), 0) AS receita_bruta,
                COALESCE(SUM(f.desconto), 0) AS deducoes
            ")
            ->whereRaw('f.tipo = ?', ['R'])
            ->whereRaw('f.data_criada BETWEEN ? AND ?', [$dataInicio, $dataFim]);

        $this->applyFilialFilter($queryReceita, $filialWhere, $filialParams, $filialId);

        if ($statusPagamento !== 'all') {
            $queryReceita->whereRaw('f.pago = ?', [$statusPagamento]);
        }

        $resultReceita = $queryReceita->first();
        $receitaBruta = (float) ($resultReceita['receita_bruta'] ?? 0);
        $deducoes = (float) ($resultReceita['deducoes'] ?? 0);
        $receitaLiquida = $receitaBruta - $deducoes;

        // --- Despesas agrupadas por plano de contas ---
        $queryDespesas = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                pc.hierarquia,
                pc.descricao_i18n,
                pc.tipo AS tipo_plano,
                COALESCE(SUM(f.valor_total), 0) AS valor
            ")
            ->leftJoin('planos_de_contas', 'pc', 'f.id_plano_de_conta', '=', 'pc.id')
            ->whereRaw('f.tipo = ?', ['D'])
            ->whereRaw('f.data_criada BETWEEN ? AND ?', [$dataInicio, $dataFim])
            ->groupBy(['f.id_plano_de_conta', 'pc.hierarquia', 'pc.descricao_i18n', 'pc.tipo'])
            ->orderByRaw('pc.hierarquia ASC');

        $this->applyFilialFilter($queryDespesas, $filialWhere, $filialParams, $filialId);

        if ($statusPagamento !== 'all') {
            $queryDespesas->whereRaw('f.pago = ?', [$statusPagamento]);
        }

        $despesasRows = $queryDespesas->get();

        // Separar custos operacionais (hierarquia comeca com '2') e despesas operacionais (hierarquia comeca com '3')
        $custosOperacionais = 0;
        $despesasOperacionais = 0;
        $despesaItems = [];

        foreach ($despesasRows as $row) {
            $valor = (float) $row['valor'];
            $hierarquia = $row['hierarquia'] ?? '';
            $descricao = $this->extractDescricaoI18n($row['descricao_i18n'] ?? null);

            if (str_starts_with($hierarquia, '2')) {
                $custosOperacionais += $valor;
            } else {
                $despesasOperacionais += $valor;
            }

            $despesaItems[] = [
                'hierarquia' => $hierarquia,
                'descricao' => $descricao,
                'valor' => $valor,
                'tipo_plano' => $row['tipo_plano'] ?? '',
            ];
        }

        $lucroBruto = $receitaLiquida - $custosOperacionais;
        $lucroOperacional = $lucroBruto - $despesasOperacionais;
        $lucroLiquido = $lucroOperacional;

        // --- Montar detalhes estruturados do DRE ---
        $details = [];

        // Receita Bruta
        $details[] = [
            'label' => t('modules.relatorios.financeiro.dre.receita_bruta'),
            'valor' => $receitaBruta,
            'percentual' => 100.0,
            'indent' => 0,
            'type' => 'header',
        ];

        // Deducoes
        $details[] = [
            'label' => t('modules.relatorios.financeiro.dre.deducoes'),
            'valor' => -$deducoes,
            'percentual' => $this->pct($deducoes, $receitaBruta),
            'indent' => 1,
            'type' => 'value',
        ];

        // Receita Liquida
        $details[] = [
            'label' => t('modules.relatorios.financeiro.dre.receita_liquida'),
            'valor' => $receitaLiquida,
            'percentual' => $this->pct($receitaLiquida, $receitaBruta),
            'indent' => 0,
            'type' => 'subtotal',
        ];

        // Custos Operacionais
        $details[] = [
            'label' => t('modules.relatorios.financeiro.dre.custos_operacionais'),
            'valor' => -$custosOperacionais,
            'percentual' => $this->pct($custosOperacionais, $receitaBruta),
            'indent' => 0,
            'type' => 'header',
        ];

        foreach ($despesaItems as $item) {
            if (str_starts_with($item['hierarquia'], '2')) {
                $details[] = [
                    'label' => $item['descricao'] ?: $item['hierarquia'],
                    'valor' => -$item['valor'],
                    'percentual' => $this->pct($item['valor'], $receitaBruta),
                    'indent' => 2,
                    'type' => 'value',
                ];
            }
        }

        // Lucro Bruto
        $details[] = [
            'label' => t('modules.relatorios.financeiro.dre.lucro_bruto'),
            'valor' => $lucroBruto,
            'percentual' => $this->pct($lucroBruto, $receitaBruta),
            'indent' => 0,
            'type' => 'subtotal',
        ];

        // Despesas Operacionais
        $details[] = [
            'label' => t('modules.relatorios.financeiro.dre.despesas_operacionais'),
            'valor' => -$despesasOperacionais,
            'percentual' => $this->pct($despesasOperacionais, $receitaBruta),
            'indent' => 0,
            'type' => 'header',
        ];

        foreach ($despesaItems as $item) {
            if (!str_starts_with($item['hierarquia'], '2')) {
                $details[] = [
                    'label' => $item['descricao'] ?: $item['hierarquia'],
                    'valor' => -$item['valor'],
                    'percentual' => $this->pct($item['valor'], $receitaBruta),
                    'indent' => 2,
                    'type' => 'value',
                ];
            }
        }

        // Lucro Operacional
        $details[] = [
            'label' => t('modules.relatorios.financeiro.dre.lucro_operacional'),
            'valor' => $lucroOperacional,
            'percentual' => $this->pct($lucroOperacional, $receitaBruta),
            'indent' => 0,
            'type' => 'subtotal',
        ];

        // Lucro Liquido
        $details[] = [
            'label' => t('modules.relatorios.financeiro.dre.lucro_liquido'),
            'valor' => $lucroLiquido,
            'percentual' => $this->pct($lucroLiquido, $receitaBruta),
            'indent' => 0,
            'type' => 'subtotal',
        ];

        return [
            'totals' => [
                'receita_bruta' => $receitaBruta,
                'deducoes' => $deducoes,
                'receita_liquida' => $receitaLiquida,
                'custos_operacionais' => $custosOperacionais,
                'lucro_bruto' => $lucroBruto,
                'despesas_operacionais' => $despesasOperacionais,
                'lucro_operacional' => $lucroOperacional,
                'lucro_liquido' => $lucroLiquido,
            ],
            'details' => $details,
            'chart' => [],
        ];
    }

    // -------------------------------------------------------------------------
    // 4. Livro Caixa
    // -------------------------------------------------------------------------

    /**
     * Livro Caixa - movimentacoes pagas com saldo acumulado
     *
     * @return array{totals: array, details: array, chart: array, total: int}
     */
    public function livroCaixa(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $contaId = '',
        int $page = 1,
        int $perPage = 20,
        bool $considerarSaldoInicial = true
    ): array {
        // --- Saldo inicial (todos os pagos antes do periodo) ---
        $saldoInicial = 0.0;
        if ($considerarSaldoInicial) {
            $querySaldoInicial = $this->qb
                ->table('financeiro', 'f')
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN f.tipo = 'R' THEN f.valor_total ELSE 0 END), 0) -
                    COALESCE(SUM(CASE WHEN f.tipo = 'D' THEN f.valor_total ELSE 0 END), 0) AS saldo_inicial
                ")
                ->whereRaw('f.pago = ?', ['S'])
                ->whereRaw('f.data_pago < ?', [$dataInicio]);

            $this->applyFilialFilter($querySaldoInicial, $filialWhere, $filialParams, $filialId);

            if (!empty($contaId)) {
                $querySaldoInicial->whereRaw('f.id_conta = ?', [(int) $contaId]);
            }

            $saldoInicial = (float) ($querySaldoInicial->first()['saldo_inicial'] ?? 0);
        }

        // --- Totais do periodo ---
        $queryTotals = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                COALESCE(SUM(CASE WHEN f.tipo = 'R' THEN f.valor_total ELSE 0 END), 0) AS total_entradas,
                COALESCE(SUM(CASE WHEN f.tipo = 'D' THEN f.valor_total ELSE 0 END), 0) AS total_saidas
            ")
            ->whereRaw('f.pago = ?', ['S'])
            ->whereRaw('f.data_pago BETWEEN ? AND ?', [$dataInicio, $dataFim]);

        $this->applyFilialFilter($queryTotals, $filialWhere, $filialParams, $filialId);

        if (!empty($contaId)) {
            $queryTotals->whereRaw('f.id_conta = ?', [(int) $contaId]);
        }

        $resultTotals = $queryTotals->first();
        $totalEntradas = (float) ($resultTotals['total_entradas'] ?? 0);
        $totalSaidas = (float) ($resultTotals['total_saidas'] ?? 0);
        $saldoFinal = $saldoInicial + $totalEntradas - $totalSaidas;

        // --- Contagem para paginacao ---
        $queryCount = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw('COUNT(*) AS total')
            ->whereRaw('f.pago = ?', ['S'])
            ->whereRaw('f.data_pago BETWEEN ? AND ?', [$dataInicio, $dataFim]);

        $this->applyFilialFilter($queryCount, $filialWhere, $filialParams, $filialId);

        if (!empty($contaId)) {
            $queryCount->whereRaw('f.id_conta = ?', [(int) $contaId]);
        }

        $totalRows = (int) ($queryCount->first()['total'] ?? 0);

        // --- Detalhes paginados ---
        // Para calcular saldo acumulado, precisamos do offset para somar antes
        $semPaginacao = $perPage <= 0;
        $offset = $semPaginacao ? 0 : ($page - 1) * $perPage;

        // Saldo acumulado ate o offset (para iniciar o running total corretamente)
        $saldoAteOffset = $saldoInicial;
        if ($offset > 0) {
            $querySaldoOffset = $this->qb
                ->table('financeiro', 'f')
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN f.tipo = 'R' THEN f.valor_total ELSE 0 END), 0) -
                    COALESCE(SUM(CASE WHEN f.tipo = 'D' THEN f.valor_total ELSE 0 END), 0) AS saldo_offset
                ")
                ->whereRaw('f.pago = ?', ['S'])
                ->whereRaw('f.data_pago BETWEEN ? AND ?', [$dataInicio, $dataFim])
                ->orderByRaw('f.data_pago ASC, f.id ASC')
                ->limit($offset);

            $this->applyFilialFilter($querySaldoOffset, $filialWhere, $filialParams, $filialId);

            if (!empty($contaId)) {
                $querySaldoOffset->whereRaw('f.id_conta = ?', [(int) $contaId]);
            }

            // Usar subquery para somar os primeiros N registros
            $querySubOffset = $this->qb
                ->table('financeiro', 'f')
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN f.tipo = 'R' THEN f.valor_total ELSE 0 END), 0) AS entradas_offset,
                    COALESCE(SUM(CASE WHEN f.tipo = 'D' THEN f.valor_total ELSE 0 END), 0) AS saidas_offset
                ")
                ->whereRaw('f.pago = ?', ['S'])
                ->whereRaw('f.data_pago BETWEEN ? AND ?', [$dataInicio, $dataFim]);

            $this->applyFilialFilter($querySubOffset, $filialWhere, $filialParams, $filialId);

            if (!empty($contaId)) {
                $querySubOffset->whereRaw('f.id_conta = ?', [(int) $contaId]);
            }

            // Buscar IDs dos primeiros N registros para calcular saldo
            $queryIdsOffset = $this->qb
                ->table('financeiro', 'f')
                ->select(['f.id'])
                ->whereRaw('f.pago = ?', ['S'])
                ->whereRaw('f.data_pago BETWEEN ? AND ?', [$dataInicio, $dataFim])
                ->orderByRaw('f.data_pago ASC, f.id ASC')
                ->limit($offset);

            $this->applyFilialFilter($queryIdsOffset, $filialWhere, $filialParams, $filialId);

            if (!empty($contaId)) {
                $queryIdsOffset->whereRaw('f.id_conta = ?', [(int) $contaId]);
            }

            $idsOffset = $queryIdsOffset->pluck('id');

            if (!empty($idsOffset)) {
                $querySumOffset = $this->qb
                    ->table('financeiro', 'f')
                    ->selectRaw("
                        COALESCE(SUM(CASE WHEN f.tipo = 'R' THEN f.valor_total ELSE 0 END), 0) AS entradas,
                        COALESCE(SUM(CASE WHEN f.tipo = 'D' THEN f.valor_total ELSE 0 END), 0) AS saidas
                    ")
                    ->whereIn('f.id', $idsOffset);

                $resultOffset = $querySumOffset->first();
                $saldoAteOffset += (float) ($resultOffset['entradas'] ?? 0) - (float) ($resultOffset['saidas'] ?? 0);
            }
        }

        $queryDetails = $this->qb
            ->table('financeiro', 'f')
            ->select([
                'f.id',
                'f.data_pago',
                'f.descricao',
                'f.tipo',
                'f.valor_total',
            ])
            ->selectRaw('cl.nome_rsocial AS cliente_nome')
            ->selectRaw('fo.nome_rsocial AS fornecedor_nome')
            ->selectRaw('cb.nome AS conta_nome')
            ->selectRaw('fp.nome AS forma_pagamento_nome')
            ->leftJoin('clientes', 'cl', 'f.id_cliente', '=', 'cl.id')
            ->leftJoin('fornecedores', 'fo', 'f.id_fornecedor', '=', 'fo.id')
            ->leftJoin('contas_bancarias', 'cb', 'f.id_conta', '=', 'cb.id')
            ->leftJoin('formas_pagamento', 'fp', 'f.id_forma_pagamento', '=', 'fp.id')
            ->whereRaw('f.pago = ?', ['S'])
            ->whereRaw('f.data_pago BETWEEN ? AND ?', [$dataInicio, $dataFim])
            ->orderByRaw('f.data_pago ASC, f.id ASC');

        if (!$semPaginacao) {
            $queryDetails->paginate($page, $perPage);
        }

        $this->applyFilialFilter($queryDetails, $filialWhere, $filialParams, $filialId);

        if (!empty($contaId)) {
            $queryDetails->whereRaw('f.id_conta = ?', [(int) $contaId]);
        }

        $rows = $queryDetails->get();

        // Calcular saldo acumulado (running total)
        $saldoCorrente = $saldoAteOffset;
        $details = [];

        foreach ($rows as $row) {
            $valor = (float) $row['valor_total'];
            $entrada = $row['tipo'] === 'R' ? $valor : 0;
            $saida = $row['tipo'] === 'D' ? $valor : 0;
            $saldoCorrente += $entrada - $saida;
            $pessoaTipo = $row['tipo'] === 'R' ? 'cliente' : 'fornecedor';
            $pessoaNome = $row['tipo'] === 'R'
                ? ($row['cliente_nome'] ?? '')
                : ($row['fornecedor_nome'] ?? '');
            $contaNome = trim((string) ($row['conta_nome'] ?? ''));
            $formaPagamentoNome = trim((string) ($row['forma_pagamento_nome'] ?? ''));

            $details[] = [
                'id' => (int) $row['id'],
                'data' => $row['data_pago'],
                'pessoa_tipo' => $pessoaNome !== '' ? $pessoaTipo : '',
                'pessoa_nome' => $pessoaNome,
                'descricao' => $row['descricao'] ?? '',
                'historico' => $row['descricao'] ?? '',
                'conta' => $contaNome !== '' ? $contaNome : '-',
                'forma_pagamento' => $formaPagamentoNome !== '' ? $formaPagamentoNome : '-',
                'entrada' => $entrada,
                'saida' => $saida,
                'saldo' => round($saldoCorrente, 2),
            ];
        }

        return [
            'totals' => [
                'saldo_inicial' => $saldoInicial,
                'total_entradas' => $totalEntradas,
                'total_saidas' => $totalSaidas,
                'saldo_final' => $saldoFinal,
                'considerar_saldo_inicial' => $considerarSaldoInicial,
            ],
            'details' => $details,
            'chart' => [],
            'total' => $totalRows,
        ];
    }

    // -------------------------------------------------------------------------
    // 5. Contas Bancarias
    // -------------------------------------------------------------------------

    /**
     * Saldos por conta bancaria
     *
     * @return array{totals: array, details: array, chart: array}
     */
    public function contasBancarias(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        $queryContas = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                cb.id AS conta_id,
                cb.nome AS conta_nome,
                cb.banco,
                COALESCE(SUM(CASE WHEN f.tipo = 'R' THEN f.valor_total ELSE 0 END), 0) AS entradas,
                COALESCE(SUM(CASE WHEN f.tipo = 'D' THEN f.valor_total ELSE 0 END), 0) AS saidas
            ")
            ->leftJoin('contas_bancarias', 'cb', 'f.id_conta', '=', 'cb.id')
            ->whereRaw('f.pago = ?', ['S'])
            ->whereRaw('f.data_pago BETWEEN ? AND ?', [$dataInicio, $dataFim])
            ->groupBy(['f.id_conta', 'cb.id', 'cb.nome', 'cb.banco'])
            ->orderByRaw('cb.nome ASC');

        $this->applyFilialFilter($queryContas, $filialWhere, $filialParams, $filialId);

        $rows = $queryContas->get();

        $totalEntradas = 0;
        $totalSaidas = 0;
        $details = [];

        foreach ($rows as $row) {
            $entradas = (float) $row['entradas'];
            $saidas = (float) $row['saidas'];
            $saldo = $entradas - $saidas;

            $totalEntradas += $entradas;
            $totalSaidas += $saidas;

            $details[] = [
                'id' => (int) ($row['conta_id'] ?? 0),
                'conta' => $row['conta_nome'] ?? '-',
                'banco' => $row['banco'] ?? '-',
                'entradas' => $entradas,
                'saidas' => $saidas,
                'saldo' => $saldo,
            ];
        }

        $saldoGeral = $totalEntradas - $totalSaidas;
        $totalContas = count($details);

        // --- Chart: saldo por conta ---
        $chartLabels = [];
        $chartValues = [];

        foreach ($details as $d) {
            $chartLabels[] = $d['conta'];
            $chartValues[] = $d['saldo'];
        }

        return [
            'totals' => [
                'total_entradas' => $totalEntradas,
                'total_saidas' => $totalSaidas,
                'saldo_geral' => $saldoGeral,
                'total_contas' => $totalContas,
            ],
            'details' => $details,
            'chart' => [
                'labels' => $chartLabels,
                'data' => $chartValues,
                'datasets' => [
                    [
                        'label' => t('modules.relatorios.financeiro.saldo'),
                        'data' => $chartValues,
                    ],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // 6. Plano de Contas
    // -------------------------------------------------------------------------

    /**
     * Agregacao financeira por plano de contas
     *
     * @return array{totals: array, details: array, chart: array}
     */
    public function planoContas(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        $queryPlano = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                pc.id AS plano_id,
                pc.hierarquia,
                pc.descricao_i18n,
                pc.tipo AS tipo_plano,
                f.tipo AS tipo_financeiro,
                COALESCE(SUM(f.valor_total), 0) AS valor
            ")
            ->leftJoin('planos_de_contas', 'pc', 'f.id_plano_de_conta', '=', 'pc.id')
            ->whereRaw('f.data_criada BETWEEN ? AND ?', [$dataInicio, $dataFim])
            ->groupBy(['f.id_plano_de_conta', 'f.tipo', 'pc.id', 'pc.hierarquia', 'pc.descricao_i18n', 'pc.tipo'])
            ->orderByRaw('pc.hierarquia ASC');

        $this->applyFilialFilter($queryPlano, $filialWhere, $filialParams, $filialId);

        $rows = $queryPlano->get();

        $totalReceitas = 0;
        $totalDespesas = 0;
        $details = [];
        $totalGeral = 0;

        // Primeiro passo: calcular total geral para percentuais
        foreach ($rows as $row) {
            $valor = (float) $row['valor'];
            $totalGeral += $valor;

            if ($row['tipo_financeiro'] === 'R') {
                $totalReceitas += $valor;
            } else {
                $totalDespesas += $valor;
            }
        }

        // Segundo passo: montar detalhes com percentuais
        foreach ($rows as $row) {
            $valor = (float) $row['valor'];
            $descricao = $this->extractDescricaoI18n($row['descricao_i18n'] ?? null);

            $details[] = [
                'plano_id' => (int) ($row['plano_id'] ?? 0),
                'codigo' => $row['hierarquia'] ?? '',
                'descricao' => $descricao ?: '-',
                'tipo' => $row['tipo_financeiro'] ?? '',
                'valor' => $valor,
                'percentual' => $this->pct($valor, $totalGeral),
            ];
        }

        $totalCategorias = count($details);

        // --- Chart: top 10 categorias por valor (horizontal bar) ---
        // Ordenar por valor absoluto desc, pegar top 10
        $chartItems = $details;
        usort($chartItems, function ($a, $b) {
            return abs($b['valor']) <=> abs($a['valor']);
        });
        $chartItems = array_slice($chartItems, 0, 10);

        $chartLabels = [];
        $chartValues = [];

        foreach ($chartItems as $item) {
            $chartLabels[] = $item['descricao'];
            $chartValues[] = $item['valor'];
        }

        return [
            'totals' => [
                'total_receitas' => $totalReceitas,
                'total_despesas' => $totalDespesas,
                'total_categorias' => $totalCategorias,
            ],
            'details' => $details,
            'chart' => [
                'labels' => $chartLabels,
                'data' => $chartValues,
                'datasets' => [
                    [
                        'label' => t('modules.relatorios.financeiro.valor'),
                        'data' => $chartValues,
                    ],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // 7. Projecao de Receitas
    // -------------------------------------------------------------------------

    /**
     * Projecao de receitas futuras
     *
     * @return array{totals: array, details: array, chart: array}
     */
    public function projecaoReceitas(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        // --- Receita confirmada: recebiveis futuros pendentes ---
        $queryConfirmada = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("COALESCE(SUM(f.valor_total), 0) AS receita_confirmada")
            ->whereRaw('f.tipo = ?', ['R'])
            ->whereRaw('f.pago = ?', ['N'])
            ->whereRaw('f.data_venci >= ?', [$dataInicio]);

        $this->applyFilialFilter($queryConfirmada, $filialWhere, $filialParams, $filialId);

        $receitaConfirmada = (float) ($queryConfirmada->first()['receita_confirmada'] ?? 0);

        // --- Contratos ativos e seu valor mensal estimado ---
        $queryContratos = $this->qb
            ->table('contratos', 'c')
            ->selectRaw("
                COUNT(*) AS contratos_ativos,
                COALESCE(SUM(
                    CASE
                        WHEN c.contagem = 'mes' THEN c.total_pagar / GREATEST(c.dias, 1)
                        WHEN c.contagem = 'semana' THEN (c.total_pagar / GREATEST(c.dias, 1)) * 4.33
                        WHEN c.contagem = 'ano' THEN (c.total_pagar / GREATEST(c.dias, 1)) / 12
                        ELSE (c.total_pagar / GREATEST(c.dias, 1)) * 30
                    END
                ), 0) AS valor_mensal_contratos
            ")
            ->whereRaw('c.status = ?', ['A']);

        if (!empty($filialWhere)) {
            $filialWherePrefixed = str_replace('id_matriz_filial', 'c.id_matriz_filial_retirada', $filialWhere);
            $queryContratos->whereRaw($filialWherePrefixed, $filialParams);
        }
        if (!empty($filialId)) {
            $queryContratos->where('c.id_matriz_filial_retirada', '=', (int) $filialId);
        }

        $resultContratos = $queryContratos->first();
        $contratosAtivos = (int) ($resultContratos['contratos_ativos'] ?? 0);
        $valorMensalContratos = (float) ($resultContratos['valor_mensal_contratos'] ?? 0);

        // --- Media mensal dos ultimos 3 meses (para projecao) ---
        $tresMesesAtras = \App\Helpers\DateHelper::addMonthsForDatabase(-3, $dataInicio);
        $ontem = \App\Helpers\DateHelper::addDaysForDatabase(-1, $dataInicio);

        $queryMedia = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("COALESCE(SUM(f.valor_total), 0) AS receita_3m")
            ->whereRaw('f.tipo = ?', ['R'])
            ->whereRaw('f.pago = ?', ['S'])
            ->whereRaw('f.data_pago BETWEEN ? AND ?', [$tresMesesAtras, $ontem]);

        $this->applyFilialFilter($queryMedia, $filialWhere, $filialParams, $filialId);

        $receita3m = (float) ($queryMedia->first()['receita_3m'] ?? 0);
        $mediaMensal = $this->safeDivide($receita3m, 3);

        // --- Projecao por mes (proximos 3 meses) ---
        $details = [];
        $totalConfirmada = 0;
        $totalProjetada = 0;

        $inicio = new \DateTime($dataInicio);

        for ($i = 0; $i < 3; $i++) {
            $mesInicio = clone $inicio;
            $mesInicio->modify("+{$i} months");
            $mesFim = clone $mesInicio;
            $mesFim->modify('last day of this month');

            $mesLabel = $mesInicio->format('m/Y');
            $mesInicioStr = $mesInicio->format('Y-m-d');
            $mesFimStr = $mesFim->format('Y-m-d');

            // Recebiveis confirmados para este mes
            $queryMesConf = $this->qb
                ->table('financeiro', 'f')
                ->selectRaw("COALESCE(SUM(f.valor_total), 0) AS confirmada")
                ->whereRaw('f.tipo = ?', ['R'])
                ->whereRaw('f.pago = ?', ['N'])
                ->whereRaw('f.data_venci BETWEEN ? AND ?', [$mesInicioStr, $mesFimStr]);

            $this->applyFilialFilter($queryMesConf, $filialWhere, $filialParams, $filialId);

            $mesConfirmada = (float) ($queryMesConf->first()['confirmada'] ?? 0);

            // Projetada: media mensal + contratos ativos - confirmada (para nao duplicar)
            $mesProjetada = max(0, $mediaMensal + $valorMensalContratos - $mesConfirmada);

            $totalConfirmada += $mesConfirmada;
            $totalProjetada += $mesProjetada;

            $details[] = [
                'mes' => $mesLabel,
                'confirmada' => $mesConfirmada,
                'projetada' => $mesProjetada,
                'total' => $mesConfirmada + $mesProjetada,
            ];
        }

        $receitaProjetada = $totalProjetada;
        $receitaTotal = $receitaConfirmada + $receitaProjetada;

        // --- Chart: linha com confirmada vs projetada ---
        $chartLabels = array_column($details, 'mes');
        $chartConfirmada = array_column($details, 'confirmada');
        $chartProjetada = array_column($details, 'projetada');

        return [
            'totals' => [
                'receita_confirmada' => $receitaConfirmada,
                'receita_projetada' => $receitaProjetada,
                'receita_total' => $receitaTotal,
                'contratos_ativos' => $contratosAtivos,
            ],
            'details' => $details,
            'chart' => [
                'labels' => $chartLabels,
                'confirmada' => $chartConfirmada,
                'projetada' => $chartProjetada,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // 8. Rentabilidade
    // -------------------------------------------------------------------------

    /**
     * Rentabilidade por dimensao (grupo/veiculo/filial/cliente)
     *
     * @return array{totals: array, details: array, chart: array, total: int}
     */
    public function rentabilidade(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $dimensao = 'grupo',
        int $page = 1,
        int $perPage = 20
    ): array {
        // Definir agrupamento e joins baseado na dimensao
        $dimensao = in_array($dimensao, ['grupo', 'veiculo', 'filial', 'cliente'], true) ? $dimensao : 'grupo';

        switch ($dimensao) {
            case 'grupo':
                $groupField = 'v.id_grupo';
                $nameField = 'g.nome';
                $nameAlias = 'dimensao_nome';
                $joinTable = 'veiculos';
                $joinAlias = 'v';
                $joinOn = ['f.id_veiculo', 'v.id'];
                $extraJoinTable = 'grupos';
                $extraJoinAlias = 'g';
                $extraJoinOn = ['v.id_grupo', 'g.id'];
                break;

            case 'veiculo':
                $groupField = 'f.id_veiculo';
                $nameField = "CONCAT(v.placa, ' - ', v.marca, ' ', v.modelo)";
                $nameAlias = 'dimensao_nome';
                $joinTable = 'veiculos';
                $joinAlias = 'v';
                $joinOn = ['f.id_veiculo', 'v.id'];
                $extraJoinTable = null;
                $extraJoinAlias = null;
                $extraJoinOn = null;
                break;

            case 'filial':
                $groupField = 'f.id_matriz_filial';
                $nameField = 'mf.razao_social';
                $nameAlias = 'dimensao_nome';
                $joinTable = 'matrizes_filiais';
                $joinAlias = 'mf';
                $joinOn = ['f.id_matriz_filial', 'mf.id'];
                $extraJoinTable = null;
                $extraJoinAlias = null;
                $extraJoinOn = null;
                break;

            case 'cliente':
                $groupField = 'f.id_cliente';
                $nameField = 'cl.nome_rsocial';
                $nameAlias = 'dimensao_nome';
                $joinTable = 'clientes';
                $joinAlias = 'cl';
                $joinOn = ['f.id_cliente', 'cl.id'];
                $extraJoinTable = null;
                $extraJoinAlias = null;
                $extraJoinOn = null;
                break;
        }

        // --- Totais gerais ---
        $queryTotals = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                COALESCE(SUM(CASE WHEN f.tipo = 'R' THEN f.valor_total ELSE 0 END), 0) AS receita_total,
                COALESCE(SUM(CASE WHEN f.tipo = 'D' THEN f.valor_total ELSE 0 END), 0) AS custos_total
            ")
            ->whereRaw('f.data_criada BETWEEN ? AND ?', [$dataInicio, $dataFim]);

        $this->applyFilialFilter($queryTotals, $filialWhere, $filialParams, $filialId);

        $resultTotals = $queryTotals->first();
        $receitaTotal = (float) ($resultTotals['receita_total'] ?? 0);
        $custosTotal = (float) ($resultTotals['custos_total'] ?? 0);
        $lucroTotal = $receitaTotal - $custosTotal;
        $margemMedia = $this->pct($lucroTotal, $receitaTotal);

        if (in_array($dimensao, ['grupo', 'veiculo'], true)) {
            $receitas = $this->financeiroVeiculoRows('R', $dataInicio, $dataFim, [
                'date_field' => 'data_criada',
                'filial_where' => $filialWhere,
                'filial_params' => $filialParams,
                'filial_id' => $filialId,
            ]);
            $custos = $this->financeiroVeiculoRows('D', $dataInicio, $dataFim, [
                'date_field' => 'data_criada',
                'filial_where' => $filialWhere,
                'filial_params' => $filialParams,
                'filial_id' => $filialId,
            ]);

            $agrupado = [];
            $receitaAgrupada = 0.0;
            $custosAgrupados = 0.0;
            foreach ([['rows' => $receitas, 'campo' => 'receita'], ['rows' => $custos, 'campo' => 'custos']] as $grupoRows) {
                foreach ($grupoRows['rows'] as $row) {
                    $key = $dimensao === 'grupo'
                        ? 'g:' . (int) ($row['id_grupo'] ?? 0)
                        : 'v:' . (int) $row['id_veiculo'];

                    if (!isset($agrupado[$key])) {
                        $agrupado[$key] = [
                            'dimensao_nome' => $dimensao === 'grupo'
                                ? (($row['grupo_nome'] ?? '') ?: 'Sem grupo')
                                : trim(($row['placa'] ?? '-') . ' - ' . ($row['marca'] ?? '') . ' ' . ($row['modelo'] ?? '')),
                            'receita' => 0.0,
                            'custos' => 0.0,
                        ];
                    }

                    $valor = (float) $row['valor'];
                    $agrupado[$key][$grupoRows['campo']] += $valor;
                    if ($grupoRows['campo'] === 'receita') {
                        $receitaAgrupada += $valor;
                    } else {
                        $custosAgrupados += $valor;
                    }
                }
            }

            $receitaOutros = max(0.0, $receitaTotal - $receitaAgrupada);
            $custosOutros = max(0.0, $custosTotal - $custosAgrupados);
            if ($receitaOutros > 0 || $custosOutros > 0) {
                $agrupado['outros'] = [
                    'dimensao_nome' => 'Outros',
                    'receita' => $receitaOutros,
                    'custos' => $custosOutros,
                ];
            }

            $allDetails = array_map(function ($row) use ($receitaTotal) {
                $receita = (float) $row['receita'];
                $custos = (float) $row['custos'];
                $lucro = $receita - $custos;

                return [
                    'dimensao' => $row['dimensao_nome'] ?: '-',
                    'receita' => $receita,
                    'custos' => $custos,
                    'lucro' => $lucro,
                    'margem' => $this->pct($lucro, $receita),
                    'participacao' => $this->pct($receita, $receitaTotal),
                ];
            }, array_values($agrupado));

            usort($allDetails, fn($a, $b) => $b['receita'] <=> $a['receita']);

            $totalRows = count($allDetails);
            $offset = max(0, ($page - 1) * $perPage);
            $details = array_slice($allDetails, $offset, $perPage);

            $chartItems = array_slice($details, 0, 10);

            return [
                'totals' => [
                    'receita_total' => $receitaTotal,
                    'custos_total' => $custosTotal,
                    'lucro_total' => $lucroTotal,
                    'margem_media' => $margemMedia,
                ],
                'details' => $details,
                'chart' => [
                    'labels' => array_column($chartItems, 'dimensao'),
                    'data' => array_column($chartItems, 'lucro'),
                ],
                'total' => $totalRows,
            ];
        }

        // --- Contagem para paginacao ---
        $queryCount = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("COUNT(DISTINCT COALESCE({$groupField}, 0)) AS total")
            ->leftJoin($joinTable, $joinAlias, $joinOn[0], '=', $joinOn[1])
            ->whereRaw('f.data_criada BETWEEN ? AND ?', [$dataInicio, $dataFim]);

        if ($extraJoinTable) {
            $queryCount->leftJoin($extraJoinTable, $extraJoinAlias, $extraJoinOn[0], '=', $extraJoinOn[1]);
        }

        $this->applyFilialFilter($queryCount, $filialWhere, $filialParams, $filialId);

        $totalRows = (int) ($queryCount->first()['total'] ?? 0);

        // --- Detalhes paginados ---
        $queryDetails = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                COALESCE({$nameField}, 'Outros') AS {$nameAlias},
                COALESCE(SUM(CASE WHEN f.tipo = 'R' THEN f.valor_total ELSE 0 END), 0) AS receita,
                COALESCE(SUM(CASE WHEN f.tipo = 'D' THEN f.valor_total ELSE 0 END), 0) AS custos
            ")
            ->leftJoin($joinTable, $joinAlias, $joinOn[0], '=', $joinOn[1])
            ->whereRaw('f.data_criada BETWEEN ? AND ?', [$dataInicio, $dataFim])
            ->groupBy([$groupField, $nameField])
            ->orderByRaw('receita DESC')
            ->paginate($page, $perPage);

        if ($extraJoinTable) {
            $queryDetails->leftJoin($extraJoinTable, $extraJoinAlias, $extraJoinOn[0], '=', $extraJoinOn[1]);
        }

        $this->applyFilialFilter($queryDetails, $filialWhere, $filialParams, $filialId);

        $rows = $queryDetails->get();

        $details = array_map(function ($row) use ($receitaTotal) {
            $receita = (float) $row['receita'];
            $custos = (float) $row['custos'];
            $lucro = $receita - $custos;
            $margem = $this->pct($lucro, $receita);
            $participacao = $this->pct($receita, $receitaTotal);

            return [
                'dimensao' => $row['dimensao_nome'] ?? '-',
                'receita' => $receita,
                'custos' => $custos,
                'lucro' => $lucro,
                'margem' => $margem,
                'participacao' => $participacao,
            ];
        }, $rows);

        // --- Chart: top 10 por lucro ---
        $chartItems = array_slice($details, 0, 10);
        $chartLabels = array_column($chartItems, 'dimensao');
        $chartLucro = array_column($chartItems, 'lucro');

        return [
            'totals' => [
                'receita_total' => $receitaTotal,
                'custos_total' => $custosTotal,
                'lucro_total' => $lucroTotal,
                'margem_media' => $margemMedia,
            ],
            'details' => $details,
            'chart' => [
                'labels' => $chartLabels,
                'data' => $chartLucro,
            ],
            'total' => $totalRows,
        ];
    }

    // -------------------------------------------------------------------------
    // 9. Inadimplencia
    // -------------------------------------------------------------------------

    /**
     * Analise de inadimplencia
     *
     * @return array{totals: array, details: array, chart: array}
     */
    public function inadimplencia(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        // --- Total a receber (todas as receitas pendentes) ---
        $queryAReceber = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("COALESCE(SUM(f.valor_total), 0) AS total_a_receber")
            ->whereRaw('f.tipo = ?', ['R'])
            ->whereRaw('f.pago = ?', ['N']);

        $this->applyFilialFilter($queryAReceber, $filialWhere, $filialParams, $filialId);

        $totalAReceber = (float) ($queryAReceber->first()['total_a_receber'] ?? 0);

        // --- Total vencido (receitas pendentes com data_venci < hoje) ---
        $queryVencido = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                COALESCE(SUM(f.valor_total), 0) AS total_vencido,
                COUNT(DISTINCT f.id_cliente) AS total_clientes
            ")
            ->whereRaw('f.tipo = ?', ['R'])
            ->whereRaw('f.pago = ?', ['N'])
            ->whereRaw('f.data_venci < CURDATE()');

        $this->applyFilialFilter($queryVencido, $filialWhere, $filialParams, $filialId);

        $resultVencido = $queryVencido->first();
        $totalVencido = (float) ($resultVencido['total_vencido'] ?? 0);
        $totalClientes = (int) ($resultVencido['total_clientes'] ?? 0);
        $taxaInadimplencia = $this->pct($totalVencido, $totalAReceber);

        // --- Detalhes: top devedores ---
        $queryDevedores = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                cl.nome_rsocial AS cliente_nome,
                f.id_cliente,
                COALESCE(SUM(f.valor_total), 0) AS valor_vencido,
                COUNT(*) AS qtd_faturas,
                MAX(DATEDIFF(CURDATE(), f.data_venci)) AS maior_atraso
            ")
            ->leftJoin('clientes', 'cl', 'f.id_cliente', '=', 'cl.id')
            ->whereRaw('f.tipo = ?', ['R'])
            ->whereRaw('f.pago = ?', ['N'])
            ->whereRaw('f.data_venci < CURDATE()')
            ->whereNotNull('f.id_cliente')
            ->groupBy(['f.id_cliente', 'cl.nome_rsocial'])
            ->orderByRaw('valor_vencido DESC');

        $this->applyFilialFilter($queryDevedores, $filialWhere, $filialParams, $filialId);

        $devedores = $queryDevedores->get();

        $details = array_map(function ($row) {
            return [
                'id_cliente' => (int) $row['id_cliente'],
                'cliente' => $row['cliente_nome'] ?? '-',
                'valor_vencido' => (float) $row['valor_vencido'],
                'faturas' => (int) $row['qtd_faturas'],
                'maior_atraso' => (int) $row['maior_atraso'],
            ];
        }, $devedores);

        // --- Chart: aging buckets ---
        $queryAging = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                CASE
                    WHEN DATEDIFF(CURDATE(), f.data_venci) BETWEEN 1 AND 15 THEN '1-15'
                    WHEN DATEDIFF(CURDATE(), f.data_venci) BETWEEN 16 AND 30 THEN '16-30'
                    WHEN DATEDIFF(CURDATE(), f.data_venci) BETWEEN 31 AND 60 THEN '31-60'
                    WHEN DATEDIFF(CURDATE(), f.data_venci) BETWEEN 61 AND 90 THEN '61-90'
                    ELSE '>90'
                END AS faixa,
                COALESCE(SUM(f.valor_total), 0) AS valor,
                COUNT(*) AS quantidade
            ")
            ->whereRaw('f.tipo = ?', ['R'])
            ->whereRaw('f.pago = ?', ['N'])
            ->whereRaw('f.data_venci < CURDATE()')
            ->groupBy('faixa')
            ->orderByRaw("
                CASE faixa
                    WHEN '1-15' THEN 1
                    WHEN '16-30' THEN 2
                    WHEN '31-60' THEN 3
                    WHEN '61-90' THEN 4
                    ELSE 5
                END ASC
            ");

        $this->applyFilialFilter($queryAging, $filialWhere, $filialParams, $filialId);

        $agingRows = $queryAging->get();

        $aging = [
            'faixa_1_15' => 0.0,
            'faixa_16_30' => 0.0,
            'faixa_31_60' => 0.0,
            'faixa_61_90' => 0.0,
            'faixa_90_plus' => 0.0,
        ];
        $agingKeyByRange = [
            '1-15' => 'faixa_1_15',
            '16-30' => 'faixa_16_30',
            '31-60' => 'faixa_31_60',
            '61-90' => 'faixa_61_90',
            '>90' => 'faixa_90_plus',
        ];
        $chartLabels = [];
        $chartValues = [];

        foreach ($agingRows as $ar) {
            $range = (string) $ar['faixa'];
            $value = (float) $ar['valor'];
            if (isset($agingKeyByRange[$range])) {
                $aging[$agingKeyByRange[$range]] = $value;
            }

            $chartLabels[] = $ar['faixa'] . ' ' . t('modules.relatorios.financeiro.inadimplencia.dias');
            $chartValues[] = $value;
        }

        return [
            'totals' => [
                'total_a_receber' => $totalAReceber,
                'total_vencido' => $totalVencido,
                'taxa_inadimplencia' => $taxaInadimplencia,
                'total_clientes' => $totalClientes,
            ],
            'details' => $details,
            'aging' => $aging,
            'chart' => [
                'labels' => $chartLabels,
                'data' => $chartValues,
                'datasets' => [
                    [
                        'label' => t('modules.relatorios.financeiro.valor_vencido'),
                        'data' => $chartValues,
                    ],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // 10. Taxas e Servicos
    // -------------------------------------------------------------------------

    /**
     * Receita de taxas e servicos cobrados em locacoes
     *
     * @return array{totals: array, details: array, chart: array}
     */
    public function taxasServicos(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        // Taxas de locações
        $queryTaxas = $this->qb
            ->table('locacoes_taxaseservicos', 'lt')
            ->selectRaw("lt.nome, COUNT(*) AS quantidade, COALESCE(SUM(lt.valor_total), 0) AS valor_total")
            ->innerJoin('locacoes', 'l', 'l.id', '=', 'lt.id_locacao')
            ->where('l.status', '!=', 'C')
            ->whereRaw('l.data_saida BETWEEN ? AND ?', [$dataInicio, $dataFim])
            ->groupBy('lt.nome')
            ->orderByRaw('valor_total DESC');

        if (!empty($filialWhere)) {
            $filialWherePrefixed = str_replace('id_matriz_filial', 'l.id_matriz_filial_retirada', $filialWhere);
            $queryTaxas->whereRaw($filialWherePrefixed, $filialParams);
        }

        if (!empty($filialId)) {
            $queryTaxas->where('l.id_matriz_filial_retirada', '=', (int) $filialId);
        }

        $rows = $queryTaxas->get();

        $receitaTotal = 0;
        $totalCobradas = 0;
        $details = [];

        foreach ($rows as $row) {
            $valorTotal = (float) $row['valor_total'];
            $quantidade = (int) $row['quantidade'];

            $receitaTotal += $valorTotal;
            $totalCobradas += $quantidade;

            $details[] = [
                'nome' => $row['nome'] ?? '-',
                'quantidade' => $quantidade,
                'valor_total' => $valorTotal,
                'ticket_medio' => $this->safeDivide($valorTotal, $quantidade),
                'percentual' => 0, // calculado abaixo
            ];
        }

        // Atualizar percentuais agora que temos o total
        foreach ($details as &$d) {
            $d['percentual'] = $this->pct($d['valor_total'], $receitaTotal);
        }
        unset($d);

        $ticketMedio = $this->safeDivide($receitaTotal, $totalCobradas);

        // --- Chart: pie chart com top taxas ---
        $chartLabels = [];
        $chartValues = [];

        foreach ($details as $d) {
            $chartLabels[] = $d['nome'];
            $chartValues[] = $d['valor_total'];
        }

        return [
            'totals' => [
                'receita_total' => $receitaTotal,
                'total_cobradas' => $totalCobradas,
                'ticket_medio' => $ticketMedio,
            ],
            'details' => $details,
            'chart' => [
                'labels' => $chartLabels,
                'data' => $chartValues,
                'datasets' => [
                    [
                        'label' => t('modules.relatorios.financeiro.taxas_servicos'),
                        'data' => $chartValues,
                    ],
                ],
            ],
        ];
    }
}
