<?php

namespace App\Models\Relatorios;

/**
 * Relatorios da categoria CONTRATOS / LOCACOES (grupo 5).
 *
 * 5.1 Visao Geral             -> visaoGeral()
 * 5.2 Por Periodo             -> porPeriodo()
 * 5.3 Por Forma de Pagamento  -> porFormaPagamento()
 * 5.4 Extensoes               -> extensoes()
 * 5.5 Trocas de Veiculo       -> trocasVeiculo()
 */
class ContratosReport extends BaseReportModel
{
    private function applyFilialFilter($query, string $filialWhere, array $filialParams, string $filialId, string $prefix = 'l'): void
    {
        if (!empty($filialId)) {
            $query->whereRaw("{$prefix}.id_matriz_filial_retirada = ?", [(int) $filialId]);
        } elseif (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }
    }

    /**
     * 5.1 — Visao Geral das locacoes.
     *
     * @return array{totals: array, details: array, chart: array}
     */
    public function visaoGeral(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $statusFiltro = ''
    ): array {
        // --- Lista detalhada ---
        $query = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw("
                l.id, l.codigo, l.status,
                l.data_saida, l.data_prevista, l.data_chegada,
                l.dias, l.total_fatura, l.total_pagar, l.valor_desconto,
                l.cliente_nome,
                fp.nome AS forma_pagamento_nome,
                f.nome AS funcionario_nome,
                lv.id_veiculo,
                v.placa AS veiculo_placa,
                CONCAT(COALESCE(v.marca, ''), ' ', COALESCE(v.modelo, '')) AS veiculo_descricao
            ")
            ->leftJoin('formas_pagamento', 'fp', 'l.id_forma_pagamento', '=', 'fp.id')
            ->leftJoin('funcionarios', 'f', 'l.id_funcionario', '=', 'f.id')
            ->leftJoinRaw('locacoes_veiculos', 'lv', 'l.id = lv.id_locacao AND lv.data_entrada IS NULL')
            ->leftJoin('veiculos', 'v', 'lv.id_veiculo', '=', 'v.id')
            ->whereRaw('l.data_saida BETWEEN ? AND ?', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
            ->orderByRaw('l.data_saida DESC');

        if (!empty($statusFiltro)) {
            $query->whereRaw('l.status = ?', [$statusFiltro]);
        }

        $this->applyFilialFilter($query, $filialWhere, $filialParams, $filialId);

        $rows = $query->get();

        $details = array_map(fn($r) => [
            'id' => (int) $r['id'],
            'codigo' => $r['codigo'] ?? '-',
            'cliente' => $r['cliente_nome'] ?? '-',
            'veiculo_placa' => $r['veiculo_placa'] ?? '-',
            'veiculo_descricao' => trim($r['veiculo_descricao'] ?? ''),
            'data_saida' => $r['data_saida'],
            'data_prevista' => $r['data_prevista'],
            'data_chegada' => $r['data_chegada'],
            'dias' => (int) $r['dias'],
            'total_fatura' => (float) ($r['total_fatura'] ?? 0),
            'total_pagar' => (float) ($r['total_pagar'] ?? 0),
            'valor_desconto' => (float) ($r['valor_desconto'] ?? 0),
            'forma_pagamento' => $r['forma_pagamento_nome'] ?? '-',
            'funcionario' => $r['funcionario_nome'] ?? '-',
            'status' => $r['status'] ?? '',
        ], $rows);

        // --- Totalizadores ---
        $totalLocacoes = count($details);
        $valorTotal = (float) array_sum(array_column($details, 'total_pagar'));
        $diasSoma = array_sum(array_column($details, 'dias'));
        $mediaDias = $this->safeDivide($diasSoma, $totalLocacoes, 1);
        $ticketMedio = $this->safeDivide($valorTotal, $totalLocacoes, 2);

        $totals = [
            'total_locacoes' => $totalLocacoes,
            'valor_total' => $valorTotal,
            'media_dias' => $mediaDias,
            'ticket_medio' => $ticketMedio,
        ];

        // --- Chart: distribuicao por status ---
        $statusCount = [];
        foreach ($details as $d) {
            $s = $d['status'] ?: '?';
            $statusCount[$s] = ($statusCount[$s] ?? 0) + 1;
        }
        $statusLabels = [
            'A' => \t('modules.relatorios.contratos.visao_geral.status_ativo'),
            'F' => \t('modules.relatorios.contratos.visao_geral.status_finalizado'),
            'R' => \t('modules.relatorios.contratos.visao_geral.status_reserva'),
            'P' => \t('modules.relatorios.contratos.visao_geral.status_pendente'),
        ];
        $labels = [];
        $values = [];
        foreach ($statusCount as $s => $c) {
            $labels[] = $statusLabels[$s] ?? $s;
            $values[] = $c;
        }

        return [
            'totals' => $totals,
            'details' => ['lista' => $details],
            'chart' => ['labels' => $labels, 'data' => $values],
        ];
    }

    /**
     * 5.2 — Locacoes Por Periodo (agrupamento temporal).
     *
     * Granularidade: 'dia' | 'semana' | 'mes' | 'trimestre' | 'ano'
     */
    public function porPeriodo(
        string $dataInicio,
        string $dataFim,
        string $granularidade,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        $granularidade = in_array($granularidade, ['dia', 'semana', 'mes', 'trimestre', 'ano'], true) ? $granularidade : 'mes';

        $expr = match($granularidade) {
            'dia' => "DATE_FORMAT(l.data_saida, '%Y-%m-%d')",
            'semana' => "DATE_FORMAT(DATE_SUB(l.data_saida, INTERVAL WEEKDAY(l.data_saida) DAY), '%Y-%m-%d')",
            'trimestre' => "CONCAT(YEAR(l.data_saida), '-Q', QUARTER(l.data_saida))",
            'ano' => "DATE_FORMAT(l.data_saida, '%Y')",
            default => "DATE_FORMAT(l.data_saida, '%Y-%m')",
        };

        $query = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw("
                {$expr} AS periodo,
                COUNT(*) AS qtd_locacoes,
                COALESCE(SUM(l.dias), 0) AS dias_soma,
                COALESCE(SUM(l.total_pagar), 0) AS receita
            ")
            ->whereRaw('l.data_saida BETWEEN ? AND ?', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
            ->groupBy('periodo')
            ->orderByRaw('periodo ASC');

        $this->applyFilialFilter($query, $filialWhere, $filialParams, $filialId);

        $rows = $query->get();

        $details = [];
        $previousReceita = null;
        foreach ($rows as $r) {
            $qtd = (int) $r['qtd_locacoes'];
            $receita = (float) $r['receita'];
            $ticketMedio = $this->safeDivide($receita, $qtd, 2);
            $variacao = null;
            if ($previousReceita !== null && $previousReceita > 0) {
                $variacao = round((($receita - $previousReceita) / $previousReceita) * 100, 2);
            }

            $details[] = [
                'periodo' => $r['periodo'],
                'periodo_label' => $this->formatPeriodoLabel($r['periodo'], $granularidade),
                'qtd_locacoes' => $qtd,
                'dias' => (int) $r['dias_soma'],
                'receita' => $receita,
                'ticket_medio' => $ticketMedio,
                'variacao_pct' => $variacao,
            ];
            $previousReceita = $receita;
        }

        $totals = [
            'qtd_locacoes' => array_sum(array_column($details, 'qtd_locacoes')),
            'dias' => array_sum(array_column($details, 'dias')),
            'receita' => (float) array_sum(array_column($details, 'receita')),
            'ticket_medio' => $this->safeDivide(
                (float) array_sum(array_column($details, 'receita')),
                (int) array_sum(array_column($details, 'qtd_locacoes')),
                2
            ),
        ];

        $labels = array_column($details, 'periodo_label');
        $chartData = array_column($details, 'receita');

        return [
            'totals' => $totals,
            'details' => ['lista' => $details, 'granularidade' => $granularidade],
            'chart' => ['labels' => $labels, 'data' => $chartData],
        ];
    }

    /**
     * 5.3 — Locacoes Por Forma de Pagamento.
     */
    public function porFormaPagamento(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        $query = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw("
                l.id_forma_pagamento,
                fp.nome AS forma_pagamento_nome,
                COUNT(*) AS qtd_locacoes,
                COALESCE(SUM(l.total_pagar), 0) AS valor_total
            ")
            ->leftJoin('formas_pagamento', 'fp', 'l.id_forma_pagamento', '=', 'fp.id')
            ->whereRaw('l.data_saida BETWEEN ? AND ?', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
            ->groupBy(['l.id_forma_pagamento', 'fp.nome'])
            ->orderByRaw('valor_total DESC');

        $this->applyFilialFilter($query, $filialWhere, $filialParams, $filialId);

        $rows = $query->get();

        $totalLocacoes = (int) array_sum(array_column($rows, 'qtd_locacoes'));
        $valorTotal = (float) array_sum(array_column($rows, 'valor_total'));

        $details = array_map(function ($r) use ($totalLocacoes, $valorTotal) {
            $qtd = (int) $r['qtd_locacoes'];
            $vt = (float) $r['valor_total'];
            return [
                'id_forma_pagamento' => $r['id_forma_pagamento'] ? (int) $r['id_forma_pagamento'] : null,
                'forma_pagamento' => $r['forma_pagamento_nome'] ?: \t('modules.relatorios.contratos.por_forma_pagamento.sem_forma'),
                'qtd_locacoes' => $qtd,
                'pct_locacoes' => $this->pct($qtd, $totalLocacoes),
                'valor_total' => $vt,
                'pct_valor' => $this->pct($vt, $valorTotal),
                'ticket_medio' => $this->safeDivide($vt, $qtd, 2),
            ];
        }, $rows);

        $totals = [
            'qtd_formas' => count($details),
            'total_locacoes' => $totalLocacoes,
            'valor_total' => $valorTotal,
            'ticket_medio' => $this->safeDivide($valorTotal, $totalLocacoes, 2),
        ];

        $labels = array_column($details, 'forma_pagamento');
        $chartData = array_column($details, 'valor_total');

        return [
            'totals' => $totals,
            'details' => ['lista' => $details],
            'chart' => ['labels' => $labels, 'data' => $chartData],
        ];
    }

    /**
     * 5.4 — Extensoes de Contrato.
     *
     * Heuristica: locacoes onde a devolucao real (data_chegada) ocorreu apos
     * a previsao (data_prevista). Os dias adicionais sao tratados como "extensao".
     */
    public function extensoes(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        $query = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw("
                l.id, l.codigo, l.cliente_nome,
                l.data_saida, l.data_prevista, l.data_chegada,
                l.dias, l.total_pagar,
                DATEDIFF(l.data_chegada, l.data_prevista) AS dias_extensao,
                lv.id_veiculo, v.placa AS veiculo_placa,
                CONCAT(COALESCE(v.marca,''), ' ', COALESCE(v.modelo,'')) AS veiculo_descricao
            ")
            ->leftJoinRaw('locacoes_veiculos', 'lv', 'l.id = lv.id_locacao AND lv.data_entrada IS NULL')
            ->leftJoin('veiculos', 'v', 'lv.id_veiculo', '=', 'v.id')
            ->whereRaw('l.status = ?', ['F'])
            ->whereNotNull('l.data_chegada')
            ->whereRaw('l.data_chegada > l.data_prevista')
            ->whereRaw('l.data_saida BETWEEN ? AND ?', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
            ->orderByRaw('dias_extensao DESC');

        $this->applyFilialFilter($query, $filialWhere, $filialParams, $filialId);

        $rows = $query->get();

        $details = array_map(fn($r) => [
            'id' => (int) $r['id'],
            'codigo' => $r['codigo'] ?? '-',
            'cliente' => $r['cliente_nome'] ?? '-',
            'veiculo_placa' => $r['veiculo_placa'] ?? '-',
            'veiculo_descricao' => trim($r['veiculo_descricao'] ?? ''),
            'data_saida' => $r['data_saida'],
            'data_prevista' => $r['data_prevista'],
            'data_chegada' => $r['data_chegada'],
            'dias_originais' => (int) $r['dias'],
            'dias_extensao' => (int) $r['dias_extensao'],
            'total_pagar' => (float) $r['total_pagar'],
        ], $rows);

        // --- Total de locacoes no periodo (pra calcular % com extensao) ---
        $qTotalLocacoes = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw('COUNT(*) AS total')
            ->whereRaw('l.status = ?', ['F'])
            ->whereRaw('l.data_saida BETWEEN ? AND ?', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59']);
        $this->applyFilialFilter($qTotalLocacoes, $filialWhere, $filialParams, $filialId);
        $totalLocacoes = (int) ($qTotalLocacoes->first()['total'] ?? 0);

        $qtdExtensoes = count($details);
        $diasSoma = array_sum(array_column($details, 'dias_extensao'));

        $totals = [
            'qtd_extensoes' => $qtdExtensoes,
            'pct_extensoes' => $this->pct($qtdExtensoes, $totalLocacoes),
            'media_dias' => $this->safeDivide($diasSoma, $qtdExtensoes, 1),
            'receita_extensoes' => (float) array_sum(array_column($details, 'total_pagar')),
        ];

        // --- Chart: distribuicao por faixa de dias de extensao ---
        $faixas = ['1-3' => 0, '4-7' => 0, '8-15' => 0, '16-30' => 0, '30+' => 0];
        foreach ($details as $d) {
            $de = $d['dias_extensao'];
            if ($de <= 3) $faixas['1-3']++;
            elseif ($de <= 7) $faixas['4-7']++;
            elseif ($de <= 15) $faixas['8-15']++;
            elseif ($de <= 30) $faixas['16-30']++;
            else $faixas['30+']++;
        }

        return [
            'totals' => $totals,
            'details' => ['lista' => $details],
            'chart' => [
                'labels' => array_map(fn($k) => $k . ' ' . \t('modules.relatorios.contratos.extensoes.dias'), array_keys($faixas)),
                'data' => array_values($faixas),
            ],
        ];
    }

    /**
     * 5.5 — Trocas de Veiculo.
     *
     * Locacoes que tiveram veiculo trocado durante a vigencia
     * (mais de 1 registro em locacoes_veiculos para a mesma id_locacao).
     */
    public function trocasVeiculo(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        // 1. Identificar locacoes com 2+ veiculos (= teve troca)
        $query = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw("
                l.id, l.codigo, l.cliente_nome, l.data_saida,
                l.id_matriz_filial_retirada,
                COUNT(lv.id) AS qtd_veiculos
            ")
            ->innerJoin('locacoes_veiculos', 'lv', 'lv.id_locacao', '=', 'l.id')
            ->whereRaw('l.data_saida BETWEEN ? AND ?', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
            ->groupBy(['l.id', 'l.codigo', 'l.cliente_nome', 'l.data_saida', 'l.id_matriz_filial_retirada'])
            ->havingRaw('qtd_veiculos > 1');
        $this->applyFilialFilter($query, $filialWhere, $filialParams, $filialId);

        $rows = $query->get();

        $details = [];
        foreach ($rows as $loc) {
            $idLoc = (int) $loc['id'];

            // Pegar todos os veiculos dessa locacao em ordem cronologica
            $veiculos = $this->qb
                ->table('locacoes_veiculos', 'lv')
                ->selectRaw("
                    lv.id, lv.id_veiculo, lv.data_saida, lv.data_entrada,
                    lv.motivo_saida,
                    v.placa, v.marca, v.modelo,
                    lv.valor_plano_km_pago + lv.valor_plano_km_livre + lv.valor_plano_km_controlado AS valor_diaria
                ")
                ->leftJoin('veiculos', 'v', 'lv.id_veiculo', '=', 'v.id')
                ->where('lv.id_locacao', '=', $idLoc)
                ->orderByRaw('lv.data_saida ASC, lv.id ASC')
                ->get();

            // Montar pares (anterior, novo) com diferenca de valor diaria
            for ($i = 1; $i < count($veiculos); $i++) {
                $old = $veiculos[$i - 1];
                $new = $veiculos[$i];
                $details[] = [
                    'id_locacao' => $idLoc,
                    'codigo' => $loc['codigo'] ?? '-',
                    'cliente' => $loc['cliente_nome'] ?? '-',
                    'veiculo_old_placa' => $old['placa'] ?? '-',
                    'veiculo_old_descricao' => trim(($old['marca'] ?? '') . ' ' . ($old['modelo'] ?? '')),
                    'veiculo_new_placa' => $new['placa'] ?? '-',
                    'veiculo_new_descricao' => trim(($new['marca'] ?? '') . ' ' . ($new['modelo'] ?? '')),
                    'data_troca' => $new['data_saida'],
                    'motivo' => $old['motivo_saida'] ?? '',
                    'valor_diaria_old' => (float) $old['valor_diaria'],
                    'valor_diaria_new' => (float) $new['valor_diaria'],
                    'diferenca' => (float) $new['valor_diaria'] - (float) $old['valor_diaria'],
                ];
            }
        }

        $totals = [
            'qtd_trocas' => count($details),
            'qtd_locacoes_afetadas' => count($rows),
            'media_diferenca' => $this->safeDivide(
                (float) array_sum(array_column($details, 'diferenca')),
                count($details),
                2
            ),
            'soma_diferenca' => (float) array_sum(array_column($details, 'diferenca')),
        ];

        // --- Chart: trocas por motivo ---
        $motivos = [];
        foreach ($details as $d) {
            $m = trim($d['motivo']) ?: \t('modules.relatorios.contratos.trocas_veiculo.sem_motivo');
            $motivos[$m] = ($motivos[$m] ?? 0) + 1;
        }
        arsort($motivos);

        return [
            'totals' => $totals,
            'details' => ['lista' => $details],
            'chart' => [
                'labels' => array_keys($motivos),
                'data' => array_values($motivos),
            ],
        ];
    }

    private function formatPeriodoLabel(string $periodo, string $granularidade): string
    {
        switch ($granularidade) {
            case 'dia':
            case 'semana':
                // YYYY-MM-DD -> DD/MM/YYYY
                $p = explode('-', $periodo);
                return count($p) === 3 ? "{$p[2]}/{$p[1]}/{$p[0]}" : $periodo;
            case 'mes':
                // YYYY-MM -> MM/YYYY
                $p = explode('-', $periodo);
                return count($p) === 2 ? "{$p[1]}/{$p[0]}" : $periodo;
            case 'trimestre':
                return $periodo; // ex: "2026-Q1"
            case 'ano':
                return $periodo;
        }
        return $periodo;
    }
}
