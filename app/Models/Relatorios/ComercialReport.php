<?php

namespace App\Models\Relatorios;

/**
 * Model para relatórios da categoria Comercial.
 *
 * 5 relatórios:
 *  - 8.1 Taxa de Conversão
 *  - 8.2 Origem das Locações (após M1)
 *  - 8.3 Promoções Utilizadas
 *  - 8.4 Descontos Concedidos
 *  - 8.5 Análise de Temporada
 */
class ComercialReport extends BaseReportModel
{
    // =====================================================
    // 8.1 TAXA DE CONVERSÃO
    // =====================================================

    /**
     * Conversão = (status A + status F) / total. Perdas = status C / total.
     * Status: R=reserva, A=ativa, F=fechada, C=cancelada.
     */
    public function taxaConversao(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $funcionarioId = ''
    ): array {
        $query = $this->qb
            ->table('locacoes', 'l')
            ->select(['l.status'])
            ->selectRaw('COUNT(*) AS qtd, COALESCE(SUM(l.total_fatura), 0) AS faturamento')
            ->whereBetween('l.data_saida', $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59');

        if (!empty($filialWhere)) {
            $query->whereRaw(str_replace('id_matriz_filial', 'l.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $query->where('l.id_matriz_filial_retirada', '=', (int) $filialId);
        if (!empty($funcionarioId)) $query->where('l.id_funcionario', '=', (int) $funcionarioId);

        $rows = $query->groupBy('l.status')->get();

        $statusMap = ['R' => 'Reservas', 'A' => 'Ativas', 'F' => 'Fechadas', 'C' => 'Canceladas'];
        $totals = ['R' => 0, 'A' => 0, 'F' => 0, 'C' => 0];
        $faturamento = ['R' => 0.0, 'A' => 0.0, 'F' => 0.0, 'C' => 0.0];

        foreach ($rows as $r) {
            $st = $r['status'];
            if (isset($totals[$st])) {
                $totals[$st] = (int) $r['qtd'];
                $faturamento[$st] = (float) $r['faturamento'];
            }
        }

        $totalGeral = array_sum($totals);
        $convertidas = $totals['A'] + $totals['F'];
        $taxaConversao = $this->pct($convertidas, $totalGeral);
        $taxaCancelamento = $this->pct($totals['C'], $totalGeral);

        $details = [];
        foreach ($statusMap as $st => $label) {
            $details[] = [
                'status' => $st,
                'status_label' => $label,
                'qtd' => $totals[$st],
                'faturamento' => round($faturamento[$st], 2),
                'pct' => $this->pct($totals[$st], $totalGeral),
            ];
        }

        return [
            'totals' => [
                'total_geral' => $totalGeral,
                'reservas' => $totals['R'],
                'convertidas' => $convertidas,
                'canceladas' => $totals['C'],
                'taxa_conversao' => $taxaConversao,
                'taxa_cancelamento' => $taxaCancelamento,
            ],
            'details' => $details,
            'chart' => [
                'labels' => array_values($statusMap),
                'datasets' => [['label' => 'Quantidade', 'data' => array_values($totals)]],
            ],
        ];
    }

    // =====================================================
    // 8.2 ORIGEM DAS LOCAÇÕES
    // =====================================================

    /**
     * Agrega locacoes + contratos por canal (após M1). NULL → "Sem canal".
     */
    public function origemLocacoes(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        $porCanal = [];

        // Locações
        $queryL = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw("COALESCE(l.canal, 'sem_canal') AS canal_key, COUNT(*) AS qtd, COALESCE(SUM(l.total_fatura), 0) AS faturamento")
            ->whereIn('l.status', ['A', 'F'])
            ->whereBetween('l.data_saida', $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59');

        if (!empty($filialWhere)) {
            $queryL->whereRaw(str_replace('id_matriz_filial', 'l.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryL->where('l.id_matriz_filial_retirada', '=', (int) $filialId);

        foreach ($queryL->groupBy("COALESCE(l.canal, 'sem_canal')")->get() as $r) {
            $c = $r['canal_key'];
            $porCanal[$c] = $porCanal[$c] ?? ['qtd_locacoes' => 0, 'qtd_contratos' => 0, 'faturamento' => 0.0];
            $porCanal[$c]['qtd_locacoes'] += (int) $r['qtd'];
            $porCanal[$c]['faturamento'] += (float) $r['faturamento'];
        }

        // Contratos
        $queryC = $this->qb
            ->table('contratos', 'c')
            ->selectRaw("COALESCE(c.canal, 'sem_canal') AS canal_key, COUNT(*) AS qtd, COALESCE(SUM(c.total_fatura), 0) AS faturamento")
            ->whereIn('c.status', ['A', 'F'])
            ->whereBetween('c.data_ini', $dataInicio, $dataFim);

        if (!empty($filialWhere)) {
            $queryC->whereRaw(str_replace('id_matriz_filial', 'c.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryC->where('c.id_matriz_filial_retirada', '=', (int) $filialId);

        foreach ($queryC->groupBy("COALESCE(c.canal, 'sem_canal')")->get() as $r) {
            $c = $r['canal_key'];
            $porCanal[$c] = $porCanal[$c] ?? ['qtd_locacoes' => 0, 'qtd_contratos' => 0, 'faturamento' => 0.0];
            $porCanal[$c]['qtd_contratos'] += (int) $r['qtd'];
            $porCanal[$c]['faturamento'] += (float) $r['faturamento'];
        }

        $details = [];
        $totQtd = 0;
        $totFat = 0.0;

        foreach ($porCanal as $canal => $agg) {
            $qtdTotal = $agg['qtd_locacoes'] + $agg['qtd_contratos'];
            $ticket = $qtdTotal > 0 ? $agg['faturamento'] / $qtdTotal : 0;
            $details[] = [
                'canal' => $canal,
                'canal_label' => $this->labelCanal($canal),
                'qtd_locacoes' => $agg['qtd_locacoes'],
                'qtd_contratos' => $agg['qtd_contratos'],
                'qtd_total' => $qtdTotal,
                'faturamento' => round($agg['faturamento'], 2),
                'ticket_medio' => round($ticket, 2),
            ];
            $totQtd += $qtdTotal;
            $totFat += $agg['faturamento'];
        }

        usort($details, fn($a, $b) => $b['faturamento'] <=> $a['faturamento']);

        // Calcular % participação
        foreach ($details as &$d) {
            $d['pct_participacao'] = $this->pct($d['qtd_total'], $totQtd);
        }
        unset($d);

        return [
            'totals' => [
                'qtd_canais' => count($details),
                'qtd_total' => $totQtd,
                'faturamento_total' => round($totFat, 2),
                'ticket_medio_geral' => $totQtd > 0 ? round($totFat / $totQtd, 2) : 0,
            ],
            'details' => $details,
            'chart' => [
                'labels' => array_map(fn($d) => $d['canal_label'], $details),
                'datasets' => [['label' => 'Faturamento', 'data' => array_map(fn($d) => $d['faturamento'], $details)]],
            ],
        ];
    }

    private function labelCanal(string $canal): string
    {
        return match ($canal) {
            'balcao' => 'Balcão/Presencial',
            'telefone' => 'Telefone',
            'website' => 'Website',
            'whatsapp' => 'WhatsApp',
            'app' => 'Aplicativo',
            'ota' => 'OTA (Online Travel Agency)',
            'parceiro' => 'Parceiros/Convênios',
            'indicacao' => 'Indicação',
            'redes_sociais' => 'Redes Sociais',
            'google_ads' => 'Google Ads',
            'outros' => 'Outros',
            'sem_canal' => 'Sem canal informado',
            default => $canal,
        };
    }

    // =====================================================
    // 8.3 PROMOÇÕES UTILIZADAS
    // =====================================================

    /**
     * Para cada promoção: qtd usos em locações no período, desconto total, receita gerada.
     */
    public function promocoesUtilizadas(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        $query = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw('l.promocao_codigo, COUNT(*) AS qtd_usos, COALESCE(SUM(l.valor_desconto), 0) AS desconto_total, COALESCE(SUM(l.total_fatura), 0) AS receita')
            ->whereIn('l.status', ['A', 'F'])
            ->whereNotNull('l.promocao_codigo')
            ->where('l.promocao_codigo', '!=', '')
            ->whereBetween('l.data_saida', $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59');

        if (!empty($filialWhere)) {
            $query->whereRaw(str_replace('id_matriz_filial', 'l.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $query->where('l.id_matriz_filial_retirada', '=', (int) $filialId);

        $rows = $query->groupBy('l.promocao_codigo')->get();

        // Map de codigo -> nome da promocao
        $codigos = array_map(fn($r) => $r['promocao_codigo'], $rows);
        $promocoesMap = $this->buscarPromocoesPorCodigo($codigos);

        $details = [];
        $totUsos = 0;
        $totDesconto = 0.0;
        $totReceita = 0.0;

        foreach ($rows as $r) {
            $codigo = $r['promocao_codigo'];
            $info = $promocoesMap[$codigo] ?? null;

            $details[] = [
                'codigo' => $codigo,
                'nome' => $info['nome'] ?? $codigo,
                'tipo' => $info['tipo'] ?? '-',
                'valor_promocao' => isset($info['valor']) ? round((float) $info['valor'], 2) : 0,
                'qtd_usos' => (int) $r['qtd_usos'],
                'desconto_total' => round((float) $r['desconto_total'], 2),
                'receita' => round((float) $r['receita'], 2),
                'desconto_medio' => $r['qtd_usos'] > 0 ? round((float) $r['desconto_total'] / (int) $r['qtd_usos'], 2) : 0,
            ];

            $totUsos += (int) $r['qtd_usos'];
            $totDesconto += (float) $r['desconto_total'];
            $totReceita += (float) $r['receita'];
        }

        usort($details, fn($a, $b) => $b['qtd_usos'] <=> $a['qtd_usos']);

        return [
            'totals' => [
                'qtd_promocoes' => count($details),
                'qtd_usos_total' => $totUsos,
                'desconto_total' => round($totDesconto, 2),
                'receita_gerada' => round($totReceita, 2),
                'desconto_medio_geral' => $totUsos > 0 ? round($totDesconto / $totUsos, 2) : 0,
            ],
            'details' => $details,
            'chart' => [
                'labels' => array_map(fn($d) => $d['nome'], array_slice($details, 0, 10)),
                'datasets' => [['label' => 'Usos', 'data' => array_map(fn($d) => $d['qtd_usos'], array_slice($details, 0, 10))]],
            ],
        ];
    }

    private function buscarPromocoesPorCodigo(array $codigos): array
    {
        if (empty($codigos)) return [];
        $rows = $this->qb
            ->table('promocoes')
            ->select(['codigo', 'nome', 'tipo', 'valor', 'validade'])
            ->whereIn('codigo', $codigos)
            ->get();
        $map = [];
        foreach ($rows as $r) $map[$r['codigo']] = $r;
        return $map;
    }

    // =====================================================
    // 8.4 DESCONTOS CONCEDIDOS
    // =====================================================

    /**
     * Agrupa descontos por funcionário (locacoes + contratos com valor_desconto > 0).
     */
    public function descontosConcedidos(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        $porFuncionario = [];

        // Locações
        $queryL = $this->qb
            ->table('locacoes', 'l')
            ->select(['l.id_funcionario'])
            ->selectRaw('COUNT(*) AS qtd, COALESCE(SUM(l.valor_desconto), 0) AS desconto, COALESCE(SUM(l.total_fatura), 0) AS receita')
            ->whereIn('l.status', ['A', 'F'])
            ->whereRaw('COALESCE(l.valor_desconto, 0) > 0')
            ->whereBetween('l.data_saida', $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59');

        if (!empty($filialWhere)) {
            $queryL->whereRaw(str_replace('id_matriz_filial', 'l.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryL->where('l.id_matriz_filial_retirada', '=', (int) $filialId);

        foreach ($queryL->groupBy('l.id_funcionario')->get() as $r) {
            $fid = (int) ($r['id_funcionario'] ?? 0);
            $porFuncionario[$fid] = $porFuncionario[$fid] ?? ['qtd' => 0, 'desconto' => 0.0, 'receita' => 0.0];
            $porFuncionario[$fid]['qtd'] += (int) $r['qtd'];
            $porFuncionario[$fid]['desconto'] += (float) $r['desconto'];
            $porFuncionario[$fid]['receita'] += (float) $r['receita'];
        }

        // Contratos (se contratos.valor_desconto existir)
        $queryC = $this->qb
            ->table('contratos', 'c')
            ->select(['c.id_funcionario'])
            ->selectRaw('COUNT(*) AS qtd, COALESCE(SUM(c.valor_desconto), 0) AS desconto, COALESCE(SUM(c.total_fatura), 0) AS receita')
            ->whereIn('c.status', ['A', 'F'])
            ->whereRaw('COALESCE(c.valor_desconto, 0) > 0')
            ->whereBetween('c.data_ini', $dataInicio, $dataFim);

        if (!empty($filialWhere)) {
            $queryC->whereRaw(str_replace('id_matriz_filial', 'c.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryC->where('c.id_matriz_filial_retirada', '=', (int) $filialId);

        try {
            foreach ($queryC->groupBy('c.id_funcionario')->get() as $r) {
                $fid = (int) ($r['id_funcionario'] ?? 0);
                $porFuncionario[$fid] = $porFuncionario[$fid] ?? ['qtd' => 0, 'desconto' => 0.0, 'receita' => 0.0];
                $porFuncionario[$fid]['qtd'] += (int) $r['qtd'];
                $porFuncionario[$fid]['desconto'] += (float) $r['desconto'];
                $porFuncionario[$fid]['receita'] += (float) $r['receita'];
            }
        } catch (\Throwable $e) {
            // Se contratos.valor_desconto não existir, ignora silenciosamente
        }

        $idsFuncionarios = array_keys($porFuncionario);
        $funcionariosInfo = $this->buscarFuncionariosBasico($idsFuncionarios);

        $details = [];
        $totDesconto = 0.0;
        $totReceita = 0.0;
        $totQtd = 0;

        foreach ($porFuncionario as $fid => $agg) {
            $nome = $funcionariosInfo[$fid]['nome'] ?? ($fid > 0 ? "Funcionário #{$fid}" : 'Sem funcionário');
            $pctDesconto = $this->pct($agg['desconto'], $agg['receita']);

            $details[] = [
                'id_funcionario' => $fid,
                'funcionario' => $nome,
                'qtd' => $agg['qtd'],
                'desconto_total' => round($agg['desconto'], 2),
                'receita_base' => round($agg['receita'], 2),
                'pct_desconto' => $pctDesconto,
                'desconto_medio' => $agg['qtd'] > 0 ? round($agg['desconto'] / $agg['qtd'], 2) : 0,
            ];

            $totDesconto += $agg['desconto'];
            $totReceita += $agg['receita'];
            $totQtd += $agg['qtd'];
        }

        usort($details, fn($a, $b) => $b['desconto_total'] <=> $a['desconto_total']);

        return [
            'totals' => [
                'qtd_funcionarios' => count($details),
                'qtd_locacoes_com_desconto' => $totQtd,
                'desconto_total' => round($totDesconto, 2),
                'receita_base_total' => round($totReceita, 2),
                'pct_desconto_geral' => $this->pct($totDesconto, $totReceita),
                'desconto_medio_geral' => $totQtd > 0 ? round($totDesconto / $totQtd, 2) : 0,
            ],
            'details' => $details,
            'chart' => [
                'labels' => array_map(fn($d) => $d['funcionario'], array_slice($details, 0, 10)),
                'datasets' => [['label' => 'Desconto Total', 'data' => array_map(fn($d) => $d['desconto_total'], array_slice($details, 0, 10))]],
            ],
        ];
    }

    private function buscarFuncionariosBasico(array $ids): array
    {
        if (empty($ids)) return [];
        $idsValidos = array_filter($ids, fn($id) => $id > 0);
        if (empty($idsValidos)) return [];
        $rows = $this->qb
            ->table('funcionarios', 'fu')
            ->select(['fu.id', 'fu.nome'])
            ->whereIn('fu.id', $idsValidos)
            ->get();
        $map = [];
        foreach ($rows as $r) $map[(int) $r['id']] = $r;
        return $map;
    }

    // =====================================================
    // 8.5 ANÁLISE DE TEMPORADA
    // =====================================================

    /**
     * Para cada temporada (ativa, do país padrão), conta locações cuja data_saida
     * cai dentro do intervalo recorrente (mes/dia inicio → mes/dia fim).
     * Trata virada de ano (mes_inicio > mes_fim) com OR.
     */
    public function analiseTemporada(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $pais = 'BR'
    ): array {
        // Listar temporadas ativas do país
        $temporadas = $this->qb
            ->table('temporadas', 't')
            ->select(['t.id', 't.nome', 't.mes_inicio', 't.dia_inicio', 't.mes_fim', 't.dia_fim'])
            ->where('t.ativo', '=', 1)
            ->where('t.pais', '=', $pais)
            ->orderBy('t.mes_inicio', 'ASC')
            ->orderBy('t.dia_inicio', 'ASC')
            ->get();

        if (empty($temporadas)) {
            return [
                'totals' => ['qtd_temporadas' => 0, 'qtd_locacoes' => 0, 'faturamento' => 0, 'ticket_medio' => 0],
                'details' => [],
                'chart' => ['labels' => [], 'datasets' => []],
            ];
        }

        $details = [];
        $totLoc = 0;
        $totFat = 0.0;

        foreach ($temporadas as $t) {
            $mi = (int) $t['mes_inicio'];
            $di = (int) $t['dia_inicio'];
            $mf = (int) $t['mes_fim'];
            $df = (int) $t['dia_fim'];
            $ini = $mi * 100 + $di;
            $fim = $mf * 100 + $df;

            // Cláusula SQL para checar se MONTH(data_saida)*100+DAY(data_saida) cai no intervalo
            // Caso normal: ini <= fim. Caso virada de ano: ini > fim (ex.: 1215 a 0228) → OR
            $expr = ($ini <= $fim)
                ? "(MONTH(l.data_saida)*100 + DAY(l.data_saida)) BETWEEN {$ini} AND {$fim}"
                : "((MONTH(l.data_saida)*100 + DAY(l.data_saida)) >= {$ini} OR (MONTH(l.data_saida)*100 + DAY(l.data_saida)) <= {$fim})";

            $query = $this->qb
                ->table('locacoes', 'l')
                ->selectRaw('COUNT(*) AS qtd, COALESCE(SUM(l.total_fatura), 0) AS faturamento')
                ->whereIn('l.status', ['A', 'F'])
                ->whereBetween('l.data_saida', $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59')
                ->whereRaw($expr);

            if (!empty($filialWhere)) {
                $query->whereRaw(str_replace('id_matriz_filial', 'l.id_matriz_filial_retirada', $filialWhere), $filialParams);
            }
            if (!empty($filialId)) $query->where('l.id_matriz_filial_retirada', '=', (int) $filialId);

            $row = $query->first();
            $qtd = (int) ($row['qtd'] ?? 0);
            $fat = (float) ($row['faturamento'] ?? 0);
            $ticket = $qtd > 0 ? $fat / $qtd : 0;

            $details[] = [
                'id_temporada' => (int) $t['id'],
                'temporada' => $t['nome'],
                'periodo' => sprintf('%02d/%02d a %02d/%02d', $di, $mi, $df, $mf),
                'qtd_locacoes' => $qtd,
                'faturamento' => round($fat, 2),
                'ticket_medio' => round($ticket, 2),
            ];

            $totLoc += $qtd;
            $totFat += $fat;
        }

        usort($details, fn($a, $b) => $b['faturamento'] <=> $a['faturamento']);

        return [
            'totals' => [
                'qtd_temporadas' => count($details),
                'qtd_locacoes' => $totLoc,
                'faturamento' => round($totFat, 2),
                'ticket_medio' => $totLoc > 0 ? round($totFat / $totLoc, 2) : 0,
            ],
            'details' => $details,
            'chart' => [
                'labels' => array_map(fn($d) => $d['temporada'], $details),
                'datasets' => [
                    ['label' => 'Faturamento', 'data' => array_map(fn($d) => $d['faturamento'], $details)],
                ],
            ],
        ];
    }
}
