<?php

namespace App\Models\Relatorios;

/**
 * Model para relatórios da categoria Comparativos.
 *
 * 4 relatórios:
 *  - 11.1 Comparativo Mensal/Anual
 *  - 11.2 Comparativo entre Filiais
 *  - 11.3 Ranking de Veículos
 *  - 11.4 Análise de Tendências
 */
class ComparativosReport extends BaseReportModel
{
    // =====================================================
    // 11.1 COMPARATIVO MENSAL/ANUAL
    // =====================================================

    /**
     * Comparativo entre dois períodos: atual vs anterior.
     *
     * Se $dataInicioAnterior/$dataFimAnterior não forem informados, calcula
     * automaticamente o período anterior equivalente (mesmo número de dias).
     */
    public function mensalAnual(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $dataInicioAnterior = '',
        string $dataFimAnterior = ''
    ): array {
        // Calcular período anterior automaticamente se não informado
        if (empty($dataInicioAnterior) || empty($dataFimAnterior)) {
            $diasPeriodo = max(1, $this->daysBetween($dataInicio, $dataFim));
            $dataFimAnterior = date('Y-m-d', strtotime($dataInicio . ' -1 day'));
            $dataInicioAnterior = date('Y-m-d', strtotime($dataFimAnterior . ' -' . ($diasPeriodo - 1) . ' days'));
        }

        $atual = $this->indicadoresPeriodo($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId);
        $anterior = $this->indicadoresPeriodo($dataInicioAnterior, $dataFimAnterior, $filialWhere, $filialParams, $filialId);

        $details = [];
        $indicadores = [
            'faturamento' => 'Faturamento',
            'qtd_locacoes' => 'Quantidade de Locações',
            'qtd_contratos' => 'Quantidade de Contratos',
            'ticket_medio' => 'Ticket Médio',
            'receita_financeira' => 'Receita Financeira',
            'despesa_financeira' => 'Despesa Financeira',
            'lucro' => 'Lucro (Receita − Despesa)',
        ];

        foreach ($indicadores as $key => $label) {
            $valAtual = (float) ($atual[$key] ?? 0);
            $valAnterior = (float) ($anterior[$key] ?? 0);
            $variacaoAbs = $valAtual - $valAnterior;
            $variacaoPct = $this->pct($variacaoAbs, abs($valAnterior));

            $details[] = [
                'indicador' => $label,
                'key' => $key,
                'atual' => round($valAtual, 2),
                'anterior' => round($valAnterior, 2),
                'variacao_abs' => round($variacaoAbs, 2),
                'variacao_pct' => $variacaoPct,
                'tendencia' => $variacaoAbs > 0 ? 'up' : ($variacaoAbs < 0 ? 'down' : 'flat'),
                'is_currency' => in_array($key, ['faturamento', 'ticket_medio', 'receita_financeira', 'despesa_financeira', 'lucro']),
            ];
        }

        return [
            'totals' => [
                'periodo_atual' => $dataInicio . ' a ' . $dataFim,
                'periodo_anterior' => $dataInicioAnterior . ' a ' . $dataFimAnterior,
                'faturamento_atual' => round($atual['faturamento'], 2),
                'faturamento_anterior' => round($anterior['faturamento'], 2),
                'variacao_faturamento_pct' => $this->pct($atual['faturamento'] - $anterior['faturamento'], abs($anterior['faturamento'])),
                'qtd_locacoes_atual' => (int) $atual['qtd_locacoes'],
                'qtd_locacoes_anterior' => (int) $anterior['qtd_locacoes'],
            ],
            'details' => $details,
            'chart' => [
                'labels' => array_map(fn($d) => $d['indicador'], $details),
                'datasets' => [
                    ['label' => 'Período Anterior', 'data' => array_map(fn($d) => $d['anterior'], $details)],
                    ['label' => 'Período Atual', 'data' => array_map(fn($d) => $d['atual'], $details)],
                ],
            ],
        ];
    }

    /**
     * Calcula indicadores agregados de um período.
     */
    private function indicadoresPeriodo(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId
    ): array {
        // Locações faturadas no período (status A/F)
        $queryL = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw('COUNT(*) AS qtd, COALESCE(SUM(l.total_fatura), 0) AS faturamento')
            ->whereIn('l.status', ['A', 'F'])
            ->whereBetween('l.data_saida', $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59');

        if (!empty($filialWhere)) {
            $queryL->whereRaw(str_replace('id_matriz_filial', 'l.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryL->where('l.id_matriz_filial_retirada', '=', (int) $filialId);

        $locRow = $queryL->first();
        $qtdLoc = (int) ($locRow['qtd'] ?? 0);
        $fatLoc = (float) ($locRow['faturamento'] ?? 0);

        // Contratos faturados no período
        $queryC = $this->qb
            ->table('contratos', 'c')
            ->selectRaw('COUNT(*) AS qtd, COALESCE(SUM(c.total_fatura), 0) AS faturamento')
            ->whereIn('c.status', ['A', 'F'])
            ->whereBetween('c.data_ini', $dataInicio, $dataFim);

        if (!empty($filialWhere)) {
            $queryC->whereRaw(str_replace('id_matriz_filial', 'c.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryC->where('c.id_matriz_filial_retirada', '=', (int) $filialId);

        $contRow = $queryC->first();
        $qtdCont = (int) ($contRow['qtd'] ?? 0);
        $fatCont = (float) ($contRow['faturamento'] ?? 0);

        // Receita financeira (tipo R)
        $queryFR = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw('COALESCE(SUM(f.valor_total), 0) AS receita')
            ->where('f.tipo', '=', 'R')
            ->whereBetween('f.data_venci', $dataInicio, $dataFim);

        if (!empty($filialWhere)) {
            $queryFR->whereRaw(str_replace('id_matriz_filial', 'f.id_matriz_filial', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryFR->where('f.id_matriz_filial', '=', (int) $filialId);

        $receita = (float) ($queryFR->first()['receita'] ?? 0);

        // Despesa financeira (tipo D)
        $queryFD = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw('COALESCE(SUM(f.valor_total), 0) AS despesa')
            ->where('f.tipo', '=', 'D')
            ->whereBetween('f.data_venci', $dataInicio, $dataFim);

        if (!empty($filialWhere)) {
            $queryFD->whereRaw(str_replace('id_matriz_filial', 'f.id_matriz_filial', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryFD->where('f.id_matriz_filial', '=', (int) $filialId);

        $despesa = (float) ($queryFD->first()['despesa'] ?? 0);

        $faturamento = $fatLoc + $fatCont;
        $qtdTotal = $qtdLoc + $qtdCont;
        $ticketMedio = $qtdTotal > 0 ? $faturamento / $qtdTotal : 0;

        return [
            'faturamento' => $faturamento,
            'qtd_locacoes' => $qtdLoc,
            'qtd_contratos' => $qtdCont,
            'ticket_medio' => $ticketMedio,
            'receita_financeira' => $receita,
            'despesa_financeira' => $despesa,
            'lucro' => $receita - $despesa,
        ];
    }

    // =====================================================
    // 11.2 COMPARATIVO ENTRE FILIAIS
    // =====================================================

    /**
     * Performance agregada por filial no período.
     */
    public function entreFiliais(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams
    ): array {
        // Filiais acessíveis ao usuário
        $queryF = $this->qb
            ->table('matrizes_filiais', 'mf')
            ->select(['mf.id', 'mf.nome_fantasia', 'mf.cidade']);

        if (!empty($filialWhere)) {
            $queryF->whereRaw(str_replace('id_matriz_filial', 'mf.id', $filialWhere), $filialParams);
        }

        $filiais = $queryF->orderBy('mf.nome_fantasia', 'ASC')->get();

        // Veículos por filial
        $veiculosPorFilial = $this->countVeiculosPorFilial($filialWhere, $filialParams);

        // Locações por filial
        $queryL = $this->qb
            ->table('locacoes', 'l')
            ->select(['l.id_matriz_filial_retirada AS id_filial'])
            ->selectRaw('COUNT(*) AS qtd, COALESCE(SUM(l.total_fatura), 0) AS faturamento')
            ->whereIn('l.status', ['A', 'F'])
            ->whereBetween('l.data_saida', $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59');
        if (!empty($filialWhere)) {
            $queryL->whereRaw(str_replace('id_matriz_filial', 'l.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        $locPorFilial = [];
        foreach ($queryL->groupBy('l.id_matriz_filial_retirada')->get() as $r) {
            $fid = (int) ($r['id_filial'] ?? 0);
            $locPorFilial[$fid] = ['qtd' => (int) $r['qtd'], 'faturamento' => (float) $r['faturamento']];
        }

        // Contratos por filial
        $queryC = $this->qb
            ->table('contratos', 'c')
            ->select(['c.id_matriz_filial_retirada AS id_filial'])
            ->selectRaw('COUNT(*) AS qtd, COALESCE(SUM(c.total_fatura), 0) AS faturamento')
            ->whereIn('c.status', ['A', 'F'])
            ->whereBetween('c.data_ini', $dataInicio, $dataFim);
        if (!empty($filialWhere)) {
            $queryC->whereRaw(str_replace('id_matriz_filial', 'c.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        $contPorFilial = [];
        foreach ($queryC->groupBy('c.id_matriz_filial_retirada')->get() as $r) {
            $fid = (int) ($r['id_filial'] ?? 0);
            $contPorFilial[$fid] = ['qtd' => (int) $r['qtd'], 'faturamento' => (float) $r['faturamento']];
        }

        $details = [];
        $totQtdLoc = 0;
        $totQtdCont = 0;
        $totFat = 0.0;
        $totVeiculos = 0;

        foreach ($filiais as $f) {
            $fid = (int) $f['id'];
            $qtdLoc = $locPorFilial[$fid]['qtd'] ?? 0;
            $qtdCont = $contPorFilial[$fid]['qtd'] ?? 0;
            $fatLoc = $locPorFilial[$fid]['faturamento'] ?? 0;
            $fatCont = $contPorFilial[$fid]['faturamento'] ?? 0;
            $faturamento = $fatLoc + $fatCont;
            $qtd = $qtdLoc + $qtdCont;
            $veiculos = $veiculosPorFilial[$fid] ?? 0;
            $ticketMedio = $qtd > 0 ? $faturamento / $qtd : 0;

            $details[] = [
                'id_filial' => $fid,
                'filial' => $f['nome_fantasia'] ?? ('Filial #' . $fid),
                'cidade' => $f['cidade'] ?? '',
                'veiculos' => $veiculos,
                'qtd_locacoes' => $qtdLoc,
                'qtd_contratos' => $qtdCont,
                'qtd_total' => $qtd,
                'faturamento' => round($faturamento, 2),
                'ticket_medio' => round($ticketMedio, 2),
            ];

            $totQtdLoc += $qtdLoc;
            $totQtdCont += $qtdCont;
            $totFat += $faturamento;
            $totVeiculos += $veiculos;
        }

        usort($details, fn($a, $b) => $b['faturamento'] <=> $a['faturamento']);
        // Adicionar ranking
        foreach ($details as $i => &$d) {
            $d['ranking'] = $i + 1;
        }
        unset($d);

        $totQtd = $totQtdLoc + $totQtdCont;

        return [
            'totals' => [
                'qtd_filiais' => count($details),
                'total_veiculos' => $totVeiculos,
                'total_locacoes' => $totQtdLoc,
                'total_contratos' => $totQtdCont,
                'faturamento_total' => round($totFat, 2),
                'ticket_medio_geral' => $totQtd > 0 ? round($totFat / $totQtd, 2) : 0,
            ],
            'details' => $details,
            'chart' => [
                'labels' => array_map(fn($d) => $d['filial'], $details),
                'datasets' => [
                    ['label' => 'Faturamento', 'data' => array_map(fn($d) => $d['faturamento'], $details)],
                ],
            ],
        ];
    }

    private function countVeiculosPorFilial(string $filialWhere, array $filialParams): array
    {
        $query = $this->qb
            ->table('veiculos', 'v')
            ->select(['v.id_matriz_filial AS id_filial'])
            ->selectRaw('COUNT(*) AS qtd')
            ->where('v.disponibilidade', '!=', 'V');

        if (!empty($filialWhere)) {
            $query->whereRaw(str_replace('id_matriz_filial', 'v.id_matriz_filial', $filialWhere), $filialParams);
        }

        $out = [];
        foreach ($query->groupBy('v.id_matriz_filial')->get() as $r) {
            $out[(int) $r['id_filial']] = (int) $r['qtd'];
        }
        return $out;
    }

    // =====================================================
    // 11.3 RANKING DE VEÍCULOS
    // =====================================================

    /**
     * Ranking de veículos por critério: receita, qtd_locacoes ou taxa_ocupacao.
     * Retorna Top 10 e Bottom 10 (ordenados desc para top, asc para bottom).
     */
    public function rankingVeiculos(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $criterio = 'receita',
        string $grupoId = ''
    ): array {
        $criterio = in_array($criterio, ['receita', 'qtd_locacoes', 'taxa_ocupacao'], true) ? $criterio : 'receita';
        $diasPeriodo = $this->daysBetween($dataInicio, $dataFim) ?: 1;

        // Receita por veículo (itens financeiros com fallback no cabeçalho)
        $valorPorVeiculo = [];
        if ($criterio === 'receita') {
            $valorPorVeiculo = $this->somaReceitaFinanceira($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, $grupoId);
        }

        // Qtd locações + contratos por veículo
        $qtdPorVeiculo = $this->qtdLocacoesContratosPorVeiculo($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, $grupoId);
        if ($criterio === 'qtd_locacoes') {
            $valorPorVeiculo = $qtdPorVeiculo;
        }

        // Dias locados por veículo (para taxa_ocupacao)
        if ($criterio === 'taxa_ocupacao') {
            $diasLocadosMap = $this->diasLocadosPorVeiculoSimples($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, $grupoId);
            foreach ($diasLocadosMap as $vid => $dias) {
                $valorPorVeiculo[$vid] = $this->pct((float) $dias, (float) $diasPeriodo);
            }
        }

        // Buscar info dos veículos
        $idsVeiculos = array_keys($valorPorVeiculo);
        $veiculosInfo = $this->buscarVeiculosBasico($idsVeiculos);

        $details = [];
        foreach ($veiculosInfo as $vid => $vinfo) {
            $valor = (float) ($valorPorVeiculo[$vid] ?? 0);
            $details[] = [
                'id' => (int) $vid,
                'placa' => $vinfo['placa'] ?? '-',
                'veiculo' => trim(($vinfo['marca'] ?? '') . ' ' . ($vinfo['modelo'] ?? '')) ?: '-',
                'grupo' => $vinfo['grupo_nome'] ?? '-',
                'valor' => round($valor, $criterio === 'taxa_ocupacao' ? 2 : 2),
                'qtd_locacoes' => (int) ($qtdPorVeiculo[$vid] ?? 0),
            ];
        }

        // Ordenar desc para top, depois separar
        usort($details, fn($a, $b) => $b['valor'] <=> $a['valor']);
        foreach ($details as $i => &$d) $d['ranking'] = $i + 1;
        unset($d);

        $top10 = array_slice($details, 0, 10);
        $bottom10 = array_slice(array_reverse($details), 0, 10);
        // Reordenar bottom para asc (pior primeiro)
        usort($bottom10, fn($a, $b) => $a['valor'] <=> $b['valor']);
        foreach ($bottom10 as $i => &$d) $d['ranking'] = $i + 1;
        unset($d);

        $totalValor = array_sum(array_column($details, 'valor'));
        $maxValor = !empty($details) ? max(array_column($details, 'valor')) : 0;
        $mediaValor = !empty($details) ? $totalValor / count($details) : 0;

        return [
            'totals' => [
                'criterio' => $criterio,
                'qtd_veiculos' => count($details),
                'valor_total' => round($totalValor, 2),
                'valor_maximo' => round($maxValor, 2),
                'valor_medio' => round($mediaValor, 2),
            ],
            'details' => [
                'top10' => $top10,
                'bottom10' => $bottom10,
                'all' => $details,
            ],
            'chart' => [
                'labels' => array_map(fn($d) => $d['placa'], $top10),
                'datasets' => [['label' => $this->labelCriterio($criterio), 'data' => array_map(fn($d) => $d['valor'], $top10)]],
            ],
        ];
    }

    private function labelCriterio(string $c): string
    {
        return match ($c) {
            'receita' => 'Receita (R$)',
            'qtd_locacoes' => 'Qtd Locações',
            'taxa_ocupacao' => 'Taxa de Ocupação (%)',
            default => $c,
        };
    }

    private function somaReceitaFinanceira(string $dataInicio, string $dataFim, string $filialWhere, array $filialParams, string $filialId, string $grupoId): array
    {
        return $this->financeiroVeiculoSomas('R', $dataInicio, $dataFim, [
            'date_field' => 'data_venci',
            'filial_where' => $filialWhere,
            'filial_params' => $filialParams,
            'filial_id' => $filialId,
            'grupo_id' => $grupoId,
        ]);
    }

    private function qtdLocacoesContratosPorVeiculo(string $dataInicio, string $dataFim, string $filialWhere, array $filialParams, string $filialId, string $grupoId): array
    {
        $out = [];

        $queryL = $this->qb
            ->table('locacoes_veiculos', 'lv')
            ->select(['lv.id_veiculo'])
            ->selectRaw('COUNT(*) AS qtd')
            ->innerJoin('locacoes', 'l', 'lv.id_locacao', '=', 'l.id')
            ->whereIn('l.status', ['A', 'F'])
            ->whereBetween('lv.data_saida', $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59');
        if (!empty($filialWhere)) {
            $queryL->whereRaw(str_replace('id_matriz_filial', 'l.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryL->where('l.id_matriz_filial_retirada', '=', (int) $filialId);
        if (!empty($grupoId)) $queryL->where('lv.id_grupo', '=', (int) $grupoId);

        foreach ($queryL->groupBy('lv.id_veiculo')->get() as $r) {
            $vid = (int) $r['id_veiculo'];
            $out[$vid] = ($out[$vid] ?? 0) + (int) $r['qtd'];
        }

        $queryC = $this->qb
            ->table('contratos_veiculos', 'cv')
            ->select(['cv.id_veiculo'])
            ->selectRaw('COUNT(*) AS qtd')
            ->innerJoin('contratos', 'c', 'cv.id_contrato', '=', 'c.id')
            ->whereIn('c.status', ['A', 'F'])
            ->whereBetween('cv.data_saida', $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59');
        if (!empty($filialWhere)) {
            $queryC->whereRaw(str_replace('id_matriz_filial', 'c.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryC->where('c.id_matriz_filial_retirada', '=', (int) $filialId);
        if (!empty($grupoId)) $queryC->where('cv.id_grupo', '=', (int) $grupoId);

        foreach ($queryC->groupBy('cv.id_veiculo')->get() as $r) {
            $vid = (int) $r['id_veiculo'];
            $out[$vid] = ($out[$vid] ?? 0) + (int) $r['qtd'];
        }

        return $out;
    }

    private function diasLocadosPorVeiculoSimples(string $dataInicio, string $dataFim, string $filialWhere, array $filialParams, string $filialId, string $grupoId): array
    {
        $out = [];

        $queryL = $this->qb
            ->table('locacoes_veiculos', 'lv')
            ->select(['lv.id_veiculo'])
            ->selectRaw("COALESCE(SUM(DATEDIFF(LEAST(COALESCE(lv.data_entrada, '{$dataFim}'), '{$dataFim}'), GREATEST(lv.data_saida, '{$dataInicio}')) + 1), 0) AS dias")
            ->innerJoin('locacoes', 'l', 'lv.id_locacao', '=', 'l.id')
            ->where('l.status', '!=', 'C')
            ->whereRaw('lv.data_saida <= ?', [$dataFim])
            ->whereRaw("COALESCE(lv.data_entrada, '{$dataFim}') >= ?", [$dataInicio]);
        if (!empty($filialWhere)) {
            $queryL->whereRaw(str_replace('id_matriz_filial', 'l.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryL->where('l.id_matriz_filial_retirada', '=', (int) $filialId);
        if (!empty($grupoId)) $queryL->where('lv.id_grupo', '=', (int) $grupoId);

        foreach ($queryL->groupBy('lv.id_veiculo')->get() as $r) {
            $vid = (int) $r['id_veiculo'];
            $out[$vid] = ($out[$vid] ?? 0) + (int) $r['dias'];
        }

        return $out;
    }

    private function buscarVeiculosBasico(array $ids): array
    {
        if (empty($ids)) return [];
        $rows = $this->qb
            ->table('veiculos', 'v')
            ->select(['v.id', 'v.placa', 'v.marca', 'v.modelo'])
            ->selectRaw('g.nome AS grupo_nome')
            ->leftJoin('grupos', 'g', 'v.id_grupo', '=', 'g.id')
            ->whereIn('v.id', $ids)
            ->get();
        $map = [];
        foreach ($rows as $r) $map[(int) $r['id']] = $r;
        return $map;
    }

    // =====================================================
    // 11.4 ANÁLISE DE TENDÊNCIAS
    // =====================================================

    /**
     * Tendências por granularidade (dia/semana/mês). Indicadores:
     *  - receita financeira (financeiro tipo R)
     *  - qtd locações
     *  - ticket médio
     * Inclui média móvel de 3 períodos para suavização.
     */
    public function tendencias(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $granularidade = 'mes'
    ): array {
        $granularidade = in_array($granularidade, ['dia', 'semana', 'mes'], true) ? $granularidade : 'mes';
        $formato = match ($granularidade) {
            'dia' => '%Y-%m-%d',
            'semana' => '%x-W%v',
            'mes' => '%Y-%m',
        };

        // Receita
        $queryR = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("DATE_FORMAT(f.data_venci, '{$formato}') AS periodo, COALESCE(SUM(f.valor_total), 0) AS receita")
            ->where('f.tipo', '=', 'R')
            ->whereBetween('f.data_venci', $dataInicio, $dataFim);
        if (!empty($filialWhere)) {
            $queryR->whereRaw(str_replace('id_matriz_filial', 'f.id_matriz_filial', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryR->where('f.id_matriz_filial', '=', (int) $filialId);
        $receitaPorPeriodo = [];
        foreach ($queryR->groupBy("DATE_FORMAT(f.data_venci, '{$formato}')")->orderByRaw("DATE_FORMAT(f.data_venci, '{$formato}')")->get() as $r) {
            $receitaPorPeriodo[$r['periodo']] = (float) $r['receita'];
        }

        // Locações
        $queryL = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw("DATE_FORMAT(l.data_saida, '{$formato}') AS periodo, COUNT(*) AS qtd, COALESCE(SUM(l.total_fatura), 0) AS faturamento")
            ->whereIn('l.status', ['A', 'F'])
            ->whereBetween('l.data_saida', $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59');
        if (!empty($filialWhere)) {
            $queryL->whereRaw(str_replace('id_matriz_filial', 'l.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryL->where('l.id_matriz_filial_retirada', '=', (int) $filialId);
        $locPorPeriodo = [];
        foreach ($queryL->groupBy("DATE_FORMAT(l.data_saida, '{$formato}')")->orderByRaw("DATE_FORMAT(l.data_saida, '{$formato}')")->get() as $r) {
            $locPorPeriodo[$r['periodo']] = ['qtd' => (int) $r['qtd'], 'faturamento' => (float) $r['faturamento']];
        }

        // Universo de períodos
        $periodos = array_unique(array_merge(array_keys($receitaPorPeriodo), array_keys($locPorPeriodo)));
        sort($periodos);

        // Montar séries
        $serieReceita = [];
        $serieQtdLocacoes = [];
        $serieTicketMedio = [];
        foreach ($periodos as $p) {
            $rec = (float) ($receitaPorPeriodo[$p] ?? 0);
            $qtd = (int) ($locPorPeriodo[$p]['qtd'] ?? 0);
            $fat = (float) ($locPorPeriodo[$p]['faturamento'] ?? 0);
            $ticket = $qtd > 0 ? round($fat / $qtd, 2) : 0;
            $serieReceita[] = round($rec, 2);
            $serieQtdLocacoes[] = $qtd;
            $serieTicketMedio[] = $ticket;
        }

        // Média móvel de 3 períodos
        $mediaMovel = function (array $serie): array {
            $n = count($serie);
            $out = [];
            for ($i = 0; $i < $n; $i++) {
                $ini = max(0, $i - 2);
                $slice = array_slice($serie, $ini, $i - $ini + 1);
                $out[] = round(array_sum($slice) / max(1, count($slice)), 2);
            }
            return $out;
        };

        // Tendência: comparar primeiro 1/3 vs último 1/3 do período
        $tendencia = function (array $serie): array {
            $n = count($serie);
            if ($n < 2) return ['direction' => 'flat', 'variacao_pct' => 0];
            $tercio = max(1, (int) floor($n / 3));
            $inicio = array_slice($serie, 0, $tercio);
            $fim = array_slice($serie, -$tercio);
            $mediaIni = array_sum($inicio) / count($inicio);
            $mediaFim = array_sum($fim) / count($fim);
            $variacao = $this->pct($mediaFim - $mediaIni, abs($mediaIni));
            $direction = $variacao > 5 ? 'up' : ($variacao < -5 ? 'down' : 'flat');
            return ['direction' => $direction, 'variacao_pct' => $variacao];
        };

        $tRec = $tendencia($serieReceita);
        $tLoc = $tendencia($serieQtdLocacoes);
        $tTick = $tendencia($serieTicketMedio);

        $details = [
            [
                'indicador' => 'Receita',
                'is_currency' => true,
                'tendencia' => $tRec['direction'],
                'variacao_pct' => $tRec['variacao_pct'],
                'serie' => $serieReceita,
                'media_movel' => $mediaMovel($serieReceita),
            ],
            [
                'indicador' => 'Quantidade de Locações',
                'is_currency' => false,
                'tendencia' => $tLoc['direction'],
                'variacao_pct' => $tLoc['variacao_pct'],
                'serie' => $serieQtdLocacoes,
                'media_movel' => $mediaMovel($serieQtdLocacoes),
            ],
            [
                'indicador' => 'Ticket Médio',
                'is_currency' => true,
                'tendencia' => $tTick['direction'],
                'variacao_pct' => $tTick['variacao_pct'],
                'serie' => $serieTicketMedio,
                'media_movel' => $mediaMovel($serieTicketMedio),
            ],
        ];

        return [
            'totals' => [
                'granularidade' => $granularidade,
                'qtd_periodos' => count($periodos),
                'receita_total' => round(array_sum($serieReceita), 2),
                'qtd_locacoes_total' => array_sum($serieQtdLocacoes),
                'ticket_medio_geral' => array_sum($serieQtdLocacoes) > 0 ? round(array_sum(array_map(fn($a, $b) => $a * $b, $serieTicketMedio, $serieQtdLocacoes)) / array_sum($serieQtdLocacoes), 2) : 0,
                'tendencia_receita' => $tRec['direction'],
                'variacao_receita_pct' => $tRec['variacao_pct'],
            ],
            'details' => $details,
            'chart' => [
                'labels' => $periodos,
                'datasets' => [
                    ['label' => 'Receita', 'data' => $serieReceita, 'tipo' => 'line'],
                    ['label' => 'Receita (Média Móvel)', 'data' => $mediaMovel($serieReceita), 'tipo' => 'line', 'dashed' => true],
                ],
                'serie_locacoes' => $serieQtdLocacoes,
                'serie_ticket' => $serieTicketMedio,
            ],
        ];
    }
}
