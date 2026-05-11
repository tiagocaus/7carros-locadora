<?php

namespace App\Models\Relatorios;

/**
 * Model para relatórios da categoria Fornecedores.
 *
 * 2 relatórios:
 *  - 9.1 Compras e Pagamentos
 *  - 9.2 Fornecedor Investidor
 */
class FornecedoresReport extends BaseReportModel
{
    // =====================================================
    // 9.1 COMPRAS E PAGAMENTOS
    // =====================================================

    /**
     * Lista fornecedores com agregação de compras (despesas em financeiro com id_fornecedor)
     * no período. Mostra qtd, valor total, ticket médio e última compra.
     */
    public function compras(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $fornecedorId = ''
    ): array {
        $query = $this->qb
            ->table('financeiro', 'f')
            ->select(['f.id_fornecedor'])
            ->selectRaw('COUNT(*) AS qtd, COALESCE(SUM(f.valor_total), 0) AS valor_total, MAX(f.data_venci) AS ultima_compra')
            ->where('f.tipo', '=', 'D')
            ->whereNotNull('f.id_fornecedor')
            ->whereBetween('f.data_venci', $dataInicio, $dataFim);

        if (!empty($filialWhere)) {
            $query->whereRaw(str_replace('id_matriz_filial', 'f.id_matriz_filial', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $query->where('f.id_matriz_filial', '=', (int) $filialId);
        if (!empty($fornecedorId)) $query->where('f.id_fornecedor', '=', (int) $fornecedorId);

        $aggPorFornecedor = [];
        foreach ($query->groupBy('f.id_fornecedor')->get() as $r) {
            $fid = (int) $r['id_fornecedor'];
            $aggPorFornecedor[$fid] = [
                'qtd' => (int) $r['qtd'],
                'valor_total' => (float) $r['valor_total'],
                'ultima_compra' => $r['ultima_compra'],
            ];
        }

        // Buscar dados dos fornecedores
        $idsFornecedores = array_keys($aggPorFornecedor);
        $fornecedoresInfo = $this->buscarFornecedoresInfo($idsFornecedores);

        $details = [];
        $totQtd = 0;
        $totValor = 0.0;

        foreach ($fornecedoresInfo as $fid => $finfo) {
            $agg = $aggPorFornecedor[$fid] ?? null;
            if (!$agg) continue;

            $qtd = (int) $agg['qtd'];
            $valor = (float) $agg['valor_total'];
            $ticket = $qtd > 0 ? $valor / $qtd : 0;

            $details[] = [
                'id' => (int) $fid,
                'fornecedor' => $finfo['nome_rsocial'] ?? ('Fornecedor #' . $fid),
                'cpf_cnpj' => $finfo['cpf_cnpj'] ?? '',
                'investidor' => (int) ($finfo['investidor'] ?? 0) === 1,
                'qtd_compras' => $qtd,
                'valor_total' => round($valor, 2),
                'ticket_medio' => round($ticket, 2),
                'ultima_compra' => $agg['ultima_compra'],
            ];

            $totQtd += $qtd;
            $totValor += $valor;
        }

        usort($details, fn($a, $b) => $b['valor_total'] <=> $a['valor_total']);

        return [
            'totals' => [
                'qtd_fornecedores' => count($details),
                'qtd_compras_total' => $totQtd,
                'valor_total_geral' => round($totValor, 2),
                'ticket_medio_geral' => $totQtd > 0 ? round($totValor / $totQtd, 2) : 0,
            ],
            'details' => $details,
            'chart' => [
                'labels' => array_map(fn($d) => $d['fornecedor'], array_slice($details, 0, 10)),
                'datasets' => [['label' => 'Valor (R$)', 'data' => array_map(fn($d) => $d['valor_total'], array_slice($details, 0, 10))]],
            ],
        ];
    }

    private function buscarFornecedoresInfo(array $ids): array
    {
        if (empty($ids)) return [];
        $rows = $this->qb
            ->table('fornecedores', 'fo')
            ->select(['fo.id', 'fo.nome_rsocial', 'fo.cpf_cnpj', 'fo.investidor', 'fo.email'])
            ->whereIn('fo.id', $ids)
            ->get();
        $map = [];
        foreach ($rows as $r) $map[(int) $r['id']] = $r;
        return $map;
    }

    // =====================================================
    // 9.2 FORNECEDOR INVESTIDOR
    // =====================================================

    /**
     * Relatório de fornecedores investidores: veículos cedidos, receita gerada,
     * comissão devida (status pendente) vs paga.
     *
     * Doc relacionada: docs/comissoes-investidores.md
     */
    public function investidor(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $fornecedorId = ''
    ): array {
        // Listar investidores (tabela fornecedores não tem flag de ativo/inativo)
        $queryInv = $this->qb
            ->table('fornecedores', 'fo')
            ->select(['fo.id', 'fo.nome_rsocial', 'fo.cpf_cnpj', 'fo.email'])
            ->where('fo.investidor', '=', 1);

        if (!empty($fornecedorId)) {
            $queryInv->where('fo.id', '=', (int) $fornecedorId);
        }

        $investidores = $queryInv->orderBy('fo.nome_rsocial', 'ASC')->get();
        $idsInvestidores = array_map(fn($r) => (int) $r['id'], $investidores);

        if (empty($idsInvestidores)) {
            return [
                'totals' => [
                    'qtd_investidores' => 0,
                    'qtd_veiculos' => 0,
                    'valor_investido' => 0,
                    'receita_gerada' => 0,
                    'comissao_devida' => 0,
                    'comissao_paga' => 0,
                ],
                'details' => [],
                'chart' => ['labels' => [], 'datasets' => []],
            ];
        }

        // Veículos por investidor (id_fornecedor em veiculos)
        $queryVI = $this->qb
            ->table('veiculos', 'v')
            ->select(['v.id_fornecedor'])
            ->selectRaw('COUNT(*) AS qtd_veiculos, COALESCE(SUM(v.valor_compra), 0) AS valor_investido')
            ->whereIn('v.id_fornecedor', $idsInvestidores)
            ->where('v.disponibilidade', '!=', 'V');

        if (!empty($filialWhere)) {
            $queryVI->whereRaw(str_replace('id_matriz_filial', 'v.id_matriz_filial', $filialWhere), $filialParams);
        }

        $veiculosPorInvestidor = [];
        foreach ($queryVI->groupBy('v.id_fornecedor')->get() as $r) {
            $fid = (int) $r['id_fornecedor'];
            $veiculosPorInvestidor[$fid] = [
                'qtd_veiculos' => (int) $r['qtd_veiculos'],
                'valor_investido' => (float) $r['valor_investido'],
            ];
        }

        // Comissões por investidor (separado por status para evitar groupBy duplo)
        $comissoesPorInvestidor = [];
        foreach (['pendente', 'pago'] as $statusFiltro) {
            $queryCom = $this->qb
                ->table('comissoes_investidores', 'ci')
                ->select(['ci.id_fornecedor'])
                ->selectRaw('COALESCE(SUM(ci.valor_repasse_investidor), 0) AS valor, COALESCE(SUM(ci.valor_base), 0) AS receita_base')
                ->whereIn('ci.id_fornecedor', $idsInvestidores)
                ->whereBetween('ci.data_referencia', $dataInicio, $dataFim)
                ->where('ci.status', '=', $statusFiltro);

            foreach ($queryCom->groupBy('ci.id_fornecedor')->get() as $r) {
                $fid = (int) $r['id_fornecedor'];
                $comissoesPorInvestidor[$fid][$statusFiltro] = [
                    'valor' => (float) $r['valor'],
                    'receita_base' => (float) $r['receita_base'],
                ];
            }
        }

        $details = [];
        $tot = [
            'qtd_veiculos' => 0,
            'valor_investido' => 0.0,
            'receita_gerada' => 0.0,
            'comissao_devida' => 0.0,
            'comissao_paga' => 0.0,
        ];

        foreach ($investidores as $inv) {
            $fid = (int) $inv['id'];
            $vi = $veiculosPorInvestidor[$fid] ?? ['qtd_veiculos' => 0, 'valor_investido' => 0];
            $com = $comissoesPorInvestidor[$fid] ?? [];

            $comissaoPendente = (float) ($com['pendente']['valor'] ?? 0);
            $comissaoPaga = (float) ($com['pago']['valor'] ?? 0);
            $receitaBase = (float) ($com['pendente']['receita_base'] ?? 0) + (float) ($com['pago']['receita_base'] ?? 0);
            $saldo = $comissaoPendente; // saldo a pagar = comissão pendente

            $details[] = [
                'id' => $fid,
                'investidor' => $inv['nome_rsocial'] ?? ('Investidor #' . $fid),
                'cpf_cnpj' => $inv['cpf_cnpj'] ?? '',
                'qtd_veiculos' => $vi['qtd_veiculos'],
                'valor_investido' => round($vi['valor_investido'], 2),
                'receita_gerada' => round($receitaBase, 2),
                'comissao_devida' => round($comissaoPendente, 2),
                'comissao_paga' => round($comissaoPaga, 2),
                'saldo' => round($saldo, 2),
            ];

            $tot['qtd_veiculos'] += $vi['qtd_veiculos'];
            $tot['valor_investido'] += $vi['valor_investido'];
            $tot['receita_gerada'] += $receitaBase;
            $tot['comissao_devida'] += $comissaoPendente;
            $tot['comissao_paga'] += $comissaoPaga;
        }

        usort($details, fn($a, $b) => $b['saldo'] <=> $a['saldo']);

        return [
            'totals' => [
                'qtd_investidores' => count($details),
                'qtd_veiculos' => $tot['qtd_veiculos'],
                'valor_investido' => round($tot['valor_investido'], 2),
                'receita_gerada' => round($tot['receita_gerada'], 2),
                'comissao_devida' => round($tot['comissao_devida'], 2),
                'comissao_paga' => round($tot['comissao_paga'], 2),
            ],
            'details' => $details,
            'chart' => [
                'labels' => array_map(fn($d) => $d['investidor'], array_slice($details, 0, 10)),
                'datasets' => [
                    ['label' => 'Comissão Devida', 'data' => array_map(fn($d) => $d['comissao_devida'], array_slice($details, 0, 10))],
                    ['label' => 'Comissão Paga', 'data' => array_map(fn($d) => $d['comissao_paga'], array_slice($details, 0, 10))],
                ],
            ],
        ];
    }
}
