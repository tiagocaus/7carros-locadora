<?php

namespace App\Models\Relatorios;

/**
 * Model para relatórios da categoria Funcionários.
 *
 * 4 relatórios:
 *  - 10.1 Vendas
 *  - 10.2 Comissões (depende de tabela `comissoes_funcionarios`)
 *  - 10.3 Produtividade
 *  - 10.4 Metas vs Realizado (depende de tabela `metas_funcionarios`)
 */
class FuncionariosReport extends BaseReportModel
{
    // =====================================================
    // 10.1 VENDAS
    // =====================================================

    /**
     * Vendas (locacoes + contratos status A/F) por funcionario.
     */
    public function vendas(
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
            ->selectRaw('COUNT(*) AS qtd, COALESCE(SUM(l.total_fatura), 0) AS faturamento')
            ->whereIn('l.status', ['A', 'F'])
            ->whereBetween('l.data_saida', $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59');

        if (!empty($filialWhere)) {
            $queryL->whereRaw(str_replace('id_matriz_filial', 'l.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryL->where('l.id_matriz_filial_retirada', '=', (int) $filialId);

        foreach ($queryL->groupBy('l.id_funcionario')->get() as $r) {
            $fid = (int) ($r['id_funcionario'] ?? 0);
            $porFuncionario[$fid] = $porFuncionario[$fid] ?? ['qtd_loc' => 0, 'qtd_cont' => 0, 'fat_loc' => 0.0, 'fat_cont' => 0.0];
            $porFuncionario[$fid]['qtd_loc'] += (int) $r['qtd'];
            $porFuncionario[$fid]['fat_loc'] += (float) $r['faturamento'];
        }

        // Contratos
        $queryC = $this->qb
            ->table('contratos', 'c')
            ->select(['c.id_funcionario'])
            ->selectRaw('COUNT(*) AS qtd, COALESCE(SUM(c.total_fatura), 0) AS faturamento')
            ->whereIn('c.status', ['A', 'F'])
            ->whereBetween('c.data_ini', $dataInicio, $dataFim);

        if (!empty($filialWhere)) {
            $queryC->whereRaw(str_replace('id_matriz_filial', 'c.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryC->where('c.id_matriz_filial_retirada', '=', (int) $filialId);

        foreach ($queryC->groupBy('c.id_funcionario')->get() as $r) {
            $fid = (int) ($r['id_funcionario'] ?? 0);
            $porFuncionario[$fid] = $porFuncionario[$fid] ?? ['qtd_loc' => 0, 'qtd_cont' => 0, 'fat_loc' => 0.0, 'fat_cont' => 0.0];
            $porFuncionario[$fid]['qtd_cont'] += (int) $r['qtd'];
            $porFuncionario[$fid]['fat_cont'] += (float) $r['faturamento'];
        }

        $idsFuncionarios = array_keys($porFuncionario);
        $funcionariosInfo = $this->buscarFuncionariosBasico($idsFuncionarios);

        $details = [];
        $totQtdLoc = 0;
        $totQtdCont = 0;
        $totFat = 0.0;

        foreach ($porFuncionario as $fid => $agg) {
            $nome = $funcionariosInfo[$fid]['nome'] ?? ($fid > 0 ? "Funcionário #{$fid}" : 'Sem funcionário');
            $funcao = $funcionariosInfo[$fid]['role_name'] ?? '';
            $qtd = $agg['qtd_loc'] + $agg['qtd_cont'];
            $fat = $agg['fat_loc'] + $agg['fat_cont'];
            $ticket = $qtd > 0 ? $fat / $qtd : 0;

            $details[] = [
                'id_funcionario' => $fid,
                'funcionario' => $nome,
                'funcao' => $funcao,
                'qtd_locacoes' => $agg['qtd_loc'],
                'qtd_contratos' => $agg['qtd_cont'],
                'qtd_total' => $qtd,
                'faturamento' => round($fat, 2),
                'ticket_medio' => round($ticket, 2),
            ];

            $totQtdLoc += $agg['qtd_loc'];
            $totQtdCont += $agg['qtd_cont'];
            $totFat += $fat;
        }

        usort($details, fn($a, $b) => $b['faturamento'] <=> $a['faturamento']);
        foreach ($details as $i => &$d) $d['ranking'] = $i + 1;
        unset($d);

        $totQtd = $totQtdLoc + $totQtdCont;

        return [
            'totals' => [
                'qtd_funcionarios' => count($details),
                'qtd_locacoes' => $totQtdLoc,
                'qtd_contratos' => $totQtdCont,
                'qtd_total' => $totQtd,
                'faturamento_total' => round($totFat, 2),
                'ticket_medio_geral' => $totQtd > 0 ? round($totFat / $totQtd, 2) : 0,
            ],
            'details' => $details,
            'chart' => [
                'labels' => array_map(fn($d) => $d['funcionario'], array_slice($details, 0, 10)),
                'datasets' => [['label' => 'Faturamento', 'data' => array_map(fn($d) => $d['faturamento'], array_slice($details, 0, 10))]],
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
            ->select(['fu.id', 'fu.nome', 'r.name AS role_name', 'r.name AS funcao', 'fu.status'])
            ->leftJoin('funcionarios_roles', 'r', 'fu.id_role', '=', 'r.id')
            ->whereIn('fu.id', $idsValidos)
            ->get();
        $map = [];
        foreach ($rows as $r) $map[(int) $r['id']] = $r;
        return $map;
    }

    // =====================================================
    // 10.2 COMISSÕES
    // =====================================================

    /**
     * Comissões por funcionario na tabela `comissoes_funcionarios`.
     * Se vazia, retorna estrutura vazia com aviso (sem erro).
     */
    public function comissoes(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $statusFiltro = ''
    ): array {
        $query = $this->qb
            ->table('comissoes_funcionarios', 'cf')
            ->select(['cf.id_funcionario'])
            ->selectRaw('COUNT(*) AS qtd, COALESCE(SUM(cf.valor_base), 0) AS valor_base, COALESCE(SUM(cf.valor_comissao), 0) AS valor_comissao, COALESCE(SUM(cf.bonus), 0) AS bonus, COALESCE(SUM(cf.valor_total), 0) AS valor_total')
            ->whereBetween('cf.data_referencia', $dataInicio, $dataFim);

        if (!empty($filialWhere)) {
            $query->whereRaw(str_replace('id_matriz_filial', 'cf.id_matriz_filial', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $query->where('cf.id_matriz_filial', '=', (int) $filialId);
        if (!empty($statusFiltro)) $query->where('cf.status', '=', $statusFiltro);

        $aggPorFuncionario = [];
        foreach ($query->groupBy('cf.id_funcionario')->get() as $r) {
            $fid = (int) $r['id_funcionario'];
            $aggPorFuncionario[$fid] = [
                'qtd' => (int) $r['qtd'],
                'valor_base' => (float) $r['valor_base'],
                'valor_comissao' => (float) $r['valor_comissao'],
                'bonus' => (float) $r['bonus'],
                'valor_total' => (float) $r['valor_total'],
            ];
        }

        // Status breakdown por funcionario
        $statusPorFuncionario = [];
        foreach (['pendente', 'pago', 'cancelado'] as $st) {
            $queryS = $this->qb
                ->table('comissoes_funcionarios', 'cf')
                ->select(['cf.id_funcionario'])
                ->selectRaw('COALESCE(SUM(cf.valor_total), 0) AS valor')
                ->whereBetween('cf.data_referencia', $dataInicio, $dataFim)
                ->where('cf.status', '=', $st);

            if (!empty($filialWhere)) {
                $queryS->whereRaw(str_replace('id_matriz_filial', 'cf.id_matriz_filial', $filialWhere), $filialParams);
            }
            if (!empty($filialId)) $queryS->where('cf.id_matriz_filial', '=', (int) $filialId);

            foreach ($queryS->groupBy('cf.id_funcionario')->get() as $r) {
                $fid = (int) $r['id_funcionario'];
                $statusPorFuncionario[$fid][$st] = (float) $r['valor'];
            }
        }

        $idsFuncionarios = array_keys($aggPorFuncionario);
        $funcionariosInfo = $this->buscarFuncionariosBasico($idsFuncionarios);

        $details = [];
        $tot = ['valor_base' => 0.0, 'valor_comissao' => 0.0, 'bonus' => 0.0, 'valor_total' => 0.0, 'pendente' => 0.0, 'pago' => 0.0];

        foreach ($aggPorFuncionario as $fid => $agg) {
            $nome = $funcionariosInfo[$fid]['nome'] ?? "Funcionário #{$fid}";
            $sf = $statusPorFuncionario[$fid] ?? [];

            $details[] = [
                'id_funcionario' => $fid,
                'funcionario' => $nome,
                'qtd' => $agg['qtd'],
                'valor_base' => round($agg['valor_base'], 2),
                'valor_comissao' => round($agg['valor_comissao'], 2),
                'bonus' => round($agg['bonus'], 2),
                'valor_total' => round($agg['valor_total'], 2),
                'pct_comissao' => $this->pct($agg['valor_comissao'], $agg['valor_base']),
                'pendente' => round((float) ($sf['pendente'] ?? 0), 2),
                'pago' => round((float) ($sf['pago'] ?? 0), 2),
            ];

            $tot['valor_base'] += $agg['valor_base'];
            $tot['valor_comissao'] += $agg['valor_comissao'];
            $tot['bonus'] += $agg['bonus'];
            $tot['valor_total'] += $agg['valor_total'];
            $tot['pendente'] += (float) ($sf['pendente'] ?? 0);
            $tot['pago'] += (float) ($sf['pago'] ?? 0);
        }

        usort($details, fn($a, $b) => $b['valor_total'] <=> $a['valor_total']);

        return [
            'totals' => [
                'qtd_funcionarios' => count($details),
                'valor_base_total' => round($tot['valor_base'], 2),
                'valor_comissao_total' => round($tot['valor_comissao'], 2),
                'bonus_total' => round($tot['bonus'], 2),
                'valor_total_geral' => round($tot['valor_total'], 2),
                'pendente_total' => round($tot['pendente'], 2),
                'pago_total' => round($tot['pago'], 2),
                'has_data' => !empty($details),
            ],
            'details' => $details,
            'chart' => [
                'labels' => array_map(fn($d) => $d['funcionario'], array_slice($details, 0, 10)),
                'datasets' => [
                    ['label' => 'Comissão', 'data' => array_map(fn($d) => $d['valor_comissao'], array_slice($details, 0, 10))],
                    ['label' => 'Bônus', 'data' => array_map(fn($d) => $d['bonus'], array_slice($details, 0, 10))],
                ],
            ],
        ];
    }

    // =====================================================
    // 10.3 PRODUTIVIDADE
    // =====================================================

    /**
     * Produtividade: locacoes/dia, faturamento/dia, qtd checklists realizados.
     * "Dias trabalhados" = COUNT(DISTINCT DATE) com locacoes ou checklists no periodo.
     */
    public function produtividade(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        $porFuncionario = [];

        // Vendas (locacoes A/F) por funcionario com dias distintos
        $queryL = $this->qb
            ->table('locacoes', 'l')
            ->select(['l.id_funcionario'])
            ->selectRaw('COUNT(*) AS qtd, COALESCE(SUM(l.total_fatura), 0) AS faturamento, COUNT(DISTINCT DATE(l.data_saida)) AS dias')
            ->whereIn('l.status', ['A', 'F'])
            ->whereBetween('l.data_saida', $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59');

        if (!empty($filialWhere)) {
            $queryL->whereRaw(str_replace('id_matriz_filial', 'l.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryL->where('l.id_matriz_filial_retirada', '=', (int) $filialId);

        foreach ($queryL->groupBy('l.id_funcionario')->get() as $r) {
            $fid = (int) ($r['id_funcionario'] ?? 0);
            $porFuncionario[$fid] = $porFuncionario[$fid] ?? ['locacoes' => 0, 'faturamento' => 0.0, 'dias_loc' => 0, 'checklists' => 0];
            $porFuncionario[$fid]['locacoes'] = (int) $r['qtd'];
            $porFuncionario[$fid]['faturamento'] = (float) $r['faturamento'];
            $porFuncionario[$fid]['dias_loc'] = (int) $r['dias'];
        }

        // Checklists por funcionario
        $queryCk = $this->qb
            ->table('checklist', 'ch')
            ->select(['ch.id_funcionario'])
            ->selectRaw('COUNT(*) AS qtd')
            ->whereBetween('ch.created_at', $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59')
            ->whereNotNull('ch.id_funcionario');

        foreach ($queryCk->groupBy('ch.id_funcionario')->get() as $r) {
            $fid = (int) $r['id_funcionario'];
            $porFuncionario[$fid] = $porFuncionario[$fid] ?? ['locacoes' => 0, 'faturamento' => 0.0, 'dias_loc' => 0, 'checklists' => 0];
            $porFuncionario[$fid]['checklists'] = (int) $r['qtd'];
        }

        $idsFuncionarios = array_keys($porFuncionario);
        $funcionariosInfo = $this->buscarFuncionariosBasico($idsFuncionarios);

        $details = [];
        $totLoc = 0;
        $totFat = 0.0;
        $totCk = 0;

        foreach ($porFuncionario as $fid => $agg) {
            $nome = $funcionariosInfo[$fid]['nome'] ?? ($fid > 0 ? "Funcionário #{$fid}" : 'Sem funcionário');
            $dias = max(1, (int) $agg['dias_loc']);

            $details[] = [
                'id_funcionario' => $fid,
                'funcionario' => $nome,
                'dias_trabalhados' => (int) $agg['dias_loc'],
                'locacoes' => (int) $agg['locacoes'],
                'locacoes_dia' => round($agg['locacoes'] / $dias, 1),
                'faturamento' => round((float) $agg['faturamento'], 2),
                'faturamento_dia' => round((float) $agg['faturamento'] / $dias, 2),
                'checklists' => (int) $agg['checklists'],
            ];

            $totLoc += $agg['locacoes'];
            $totFat += $agg['faturamento'];
            $totCk += $agg['checklists'];
        }

        usort($details, fn($a, $b) => $b['faturamento'] <=> $a['faturamento']);

        return [
            'totals' => [
                'qtd_funcionarios' => count($details),
                'qtd_locacoes' => $totLoc,
                'faturamento_total' => round($totFat, 2),
                'qtd_checklists' => $totCk,
                'media_locacoes_funcionario' => count($details) > 0 ? round($totLoc / count($details), 1) : 0,
                'media_faturamento_funcionario' => count($details) > 0 ? round($totFat / count($details), 2) : 0,
            ],
            'details' => $details,
            'chart' => [
                'labels' => array_map(fn($d) => $d['funcionario'], array_slice($details, 0, 10)),
                'datasets' => [['label' => 'Faturamento', 'data' => array_map(fn($d) => $d['faturamento'], array_slice($details, 0, 10))]],
            ],
        ];
    }

    // =====================================================
    // 10.4 METAS VS REALIZADO
    // =====================================================

    /**
     * Compara metas cadastradas (`metas_funcionarios`) com realizado (locacoes A/F).
     * Period filter usa data_referencia da meta E data_saida das locacoes.
     */
    public function metasRealizado(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        // Buscar metas do periodo
        $queryM = $this->qb
            ->table('metas_funcionarios', 'mf')
            ->select(['mf.id_funcionario'])
            ->selectRaw('COALESCE(SUM(mf.meta_receita), 0) AS meta_receita, COALESCE(SUM(mf.meta_locacoes), 0) AS meta_locacoes')
            ->whereBetween('mf.data_referencia', $dataInicio, $dataFim);

        if (!empty($filialWhere)) {
            $queryM->whereRaw(str_replace('id_matriz_filial', 'mf.id_matriz_filial', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryM->where('mf.id_matriz_filial', '=', (int) $filialId);

        $metasPorFuncionario = [];
        foreach ($queryM->groupBy('mf.id_funcionario')->get() as $r) {
            $fid = (int) $r['id_funcionario'];
            $metasPorFuncionario[$fid] = [
                'meta_receita' => (float) $r['meta_receita'],
                'meta_locacoes' => (int) $r['meta_locacoes'],
            ];
        }

        if (empty($metasPorFuncionario)) {
            return [
                'totals' => [
                    'qtd_funcionarios' => 0,
                    'meta_receita_total' => 0,
                    'realizado_receita_total' => 0,
                    'meta_locacoes_total' => 0,
                    'realizado_locacoes_total' => 0,
                    'pct_atingimento_receita' => 0,
                    'pct_atingimento_locacoes' => 0,
                    'has_data' => false,
                ],
                'details' => [],
                'chart' => ['labels' => [], 'datasets' => []],
            ];
        }

        // Realizado por funcionario (locacoes A/F no periodo)
        $idsFuncionarios = array_keys($metasPorFuncionario);
        $queryR = $this->qb
            ->table('locacoes', 'l')
            ->select(['l.id_funcionario'])
            ->selectRaw('COUNT(*) AS qtd, COALESCE(SUM(l.total_fatura), 0) AS faturamento')
            ->whereIn('l.status', ['A', 'F'])
            ->whereIn('l.id_funcionario', $idsFuncionarios)
            ->whereBetween('l.data_saida', $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59');

        if (!empty($filialWhere)) {
            $queryR->whereRaw(str_replace('id_matriz_filial', 'l.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryR->where('l.id_matriz_filial_retirada', '=', (int) $filialId);

        $realizadoPorFuncionario = [];
        foreach ($queryR->groupBy('l.id_funcionario')->get() as $r) {
            $fid = (int) $r['id_funcionario'];
            $realizadoPorFuncionario[$fid] = [
                'qtd' => (int) $r['qtd'],
                'faturamento' => (float) $r['faturamento'],
            ];
        }

        $funcionariosInfo = $this->buscarFuncionariosBasico($idsFuncionarios);

        $details = [];
        $totMetaRec = 0.0;
        $totRealRec = 0.0;
        $totMetaLoc = 0;
        $totRealLoc = 0;

        foreach ($metasPorFuncionario as $fid => $meta) {
            $nome = $funcionariosInfo[$fid]['nome'] ?? "Funcionário #{$fid}";
            $real = $realizadoPorFuncionario[$fid] ?? ['qtd' => 0, 'faturamento' => 0];

            $pctReceita = $this->pct($real['faturamento'], $meta['meta_receita']);
            $pctLocacoes = $this->pct($real['qtd'], $meta['meta_locacoes']);

            $details[] = [
                'id_funcionario' => $fid,
                'funcionario' => $nome,
                'meta_receita' => round($meta['meta_receita'], 2),
                'realizado_receita' => round($real['faturamento'], 2),
                'pct_atingimento_receita' => $pctReceita,
                'meta_locacoes' => $meta['meta_locacoes'],
                'realizado_locacoes' => $real['qtd'],
                'pct_atingimento_locacoes' => $pctLocacoes,
            ];

            $totMetaRec += $meta['meta_receita'];
            $totRealRec += $real['faturamento'];
            $totMetaLoc += $meta['meta_locacoes'];
            $totRealLoc += $real['qtd'];
        }

        usort($details, fn($a, $b) => $b['pct_atingimento_receita'] <=> $a['pct_atingimento_receita']);

        return [
            'totals' => [
                'qtd_funcionarios' => count($details),
                'meta_receita_total' => round($totMetaRec, 2),
                'realizado_receita_total' => round($totRealRec, 2),
                'meta_locacoes_total' => $totMetaLoc,
                'realizado_locacoes_total' => $totRealLoc,
                'pct_atingimento_receita' => $this->pct($totRealRec, $totMetaRec),
                'pct_atingimento_locacoes' => $this->pct($totRealLoc, $totMetaLoc),
                'has_data' => true,
            ],
            'details' => $details,
            'chart' => [
                'labels' => array_map(fn($d) => $d['funcionario'], $details),
                'datasets' => [
                    ['label' => 'Meta Receita', 'data' => array_map(fn($d) => $d['meta_receita'], $details)],
                    ['label' => 'Realizado', 'data' => array_map(fn($d) => $d['realizado_receita'], $details)],
                ],
            ],
        ];
    }
}
