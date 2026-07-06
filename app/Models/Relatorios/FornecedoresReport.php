<?php

namespace App\Models\Relatorios;

use App\Models\Veiculo;

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
        string $fornecedorId = '',
        string $filialId = ''
    ): array {
        $filialIdInt = (int) $filialId;

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
            ->select([
                'v.id',
                'v.id_fornecedor',
                'v.placa',
                'v.marca',
                'v.modelo',
                'v.valor_compra',
                'v.disponibilidade',
                'g.nome AS grupo_nome',
                'COALESCE(rg.comissao_tipo, rp.comissao_tipo, g.comissao_investidor_tipo) AS comissao_investidor_tipo',
                'COALESCE(rg.comissao_valor, rp.comissao_valor, g.comissao_investidor_valor) AS comissao_investidor_valor',
            ])
            ->leftJoinRaw('grupos', 'g', 'g.id = v.id_grupo AND g.chave = v.chave')
            ->leftJoinRaw('fornecedores_comissoes_regras', 'rg', 'rg.chave = v.chave AND rg.id_fornecedor = v.id_fornecedor AND rg.id_grupo = v.id_grupo AND rg.ativo = 1')
            ->leftJoinRaw('fornecedores_comissoes_regras', 'rp', 'rp.chave = v.chave AND rp.id_fornecedor = v.id_fornecedor AND rp.id_grupo IS NULL AND rp.ativo = 1')
            ->whereIn('v.id_fornecedor', $idsInvestidores)
            ->whereNotIn('v.disponibilidade', Veiculo::DISPONIBILIDADE_INATIVA);

        if (!empty($filialWhere)) {
            $queryVI->whereRaw(str_replace('id_matriz_filial', 'v.id_matriz_filial', $filialWhere), $filialParams);
        }
        if ($filialIdInt > 0) {
            $queryVI->where('v.id_matriz_filial', '=', $filialIdInt);
        }

        $veiculosPorInvestidor = [];
        foreach ($queryVI->orderBy('g.nome', 'ASC')->orderBy('v.placa', 'ASC')->get() as $r) {
            $fid = (int) $r['id_fornecedor'];
            if (!isset($veiculosPorInvestidor[$fid])) {
                $veiculosPorInvestidor[$fid] = [
                    'qtd_veiculos' => 0,
                    'valor_investido' => 0.0,
                    'veiculos' => [],
                ];
            }

            $veiculosPorInvestidor[$fid]['qtd_veiculos']++;
            $veiculosPorInvestidor[$fid]['valor_investido'] += (float) ($r['valor_compra'] ?? 0);
            $veiculosPorInvestidor[$fid]['veiculos'][(int) $r['id']] = [
                'id' => (int) $r['id'],
                'placa' => $r['placa'] ?? '',
                'veiculo' => trim(($r['marca'] ?? '') . ' ' . ($r['modelo'] ?? '')),
                'grupo' => $r['grupo_nome'] ?? '',
                'tipo_comissao' => $r['comissao_investidor_tipo'] ?? '',
                'valor_comissao_config' => (float) ($r['comissao_investidor_valor'] ?? 0),
                'valor_investido' => (float) ($r['valor_compra'] ?? 0),
                'receita_gerada' => 0.0,
                'comissao_devida' => 0.0,
                'comissao_paga' => 0.0,
                'saldo' => 0.0,
                'status_diagnostico' => empty($r['comissao_investidor_tipo'])
                    ? 'grupo_sem_comissao'
                    : 'sem_fatura_paga',
            ];
        }

        // Comissões geradas no período. Não depende do veículo ainda estar ativo.
        $comissoesPorInvestidor = [];
        $comissoesPorVeiculo = [];
        $comissoesHistoricasPorInvestidor = [];

        $queryCom = $this->qb
            ->table('comissoes_investidores', 'ci')
            ->select(['ci.id_fornecedor', 'ci.id_veiculo'])
            ->selectRaw("
                COALESCE(SUM(CASE WHEN ci.status = 'pendente' THEN ci.valor_repasse_investidor ELSE 0 END), 0) AS valor_pendente,
                COALESCE(SUM(CASE WHEN ci.status = 'pago' THEN ci.valor_repasse_investidor ELSE 0 END), 0) AS valor_pago,
                COALESCE(SUM(ci.valor_base), 0) AS receita_base,
                MAX(v.placa) AS placa,
                MAX(v.marca) AS marca,
                MAX(v.modelo) AS modelo,
                MAX(v.valor_compra) AS valor_compra,
                MAX(v.disponibilidade) AS disponibilidade,
                MAX(g.nome) AS grupo_nome,
                MAX(COALESCE(NULLIF(ci.comissao_tipo, ''), g.comissao_investidor_tipo)) AS comissao_tipo,
                MAX(
                    CASE
                        WHEN ci.comissao_tipo = 'percentual_locadora' THEN ci.comissao_percentual
                        WHEN ci.comissao_tipo IN ('fixo_locadora', 'fixo_locadora_mensal', 'fixo_investidor_mensal') THEN ci.comissao_valor_fixo
                        ELSE g.comissao_investidor_valor
                    END
                ) AS valor_comissao_config
            ")
            ->leftJoinRaw('veiculos', 'v', 'v.id = ci.id_veiculo AND v.chave = ci.chave')
            ->leftJoinRaw('grupos', 'g', 'g.id = COALESCE(ci.id_grupo, v.id_grupo) AND g.chave = ci.chave')
            ->whereIn('ci.id_fornecedor', $idsInvestidores)
            ->whereBetween('ci.data_referencia', $dataInicio, $dataFim)
            ->whereIn('ci.status', ['pendente', 'pago']);

        if (!empty($filialWhere)) {
            $queryCom->whereRaw(str_replace('id_matriz_filial', 'v.id_matriz_filial', $filialWhere), $filialParams);
        }
        if ($filialIdInt > 0) {
            $queryCom->where('v.id_matriz_filial', '=', $filialIdInt);
        }

        foreach ($queryCom->groupBy('ci.id_fornecedor')->groupBy('ci.id_veiculo')->get() as $r) {
            $fid = (int) $r['id_fornecedor'];
            $vid = (int) ($r['id_veiculo'] ?? 0);
            $pendente = (float) ($r['valor_pendente'] ?? 0);
            $pago = (float) ($r['valor_pago'] ?? 0);
            $receita = (float) ($r['receita_base'] ?? 0);

            $comissoesPorInvestidor[$fid]['pendente'] = [
                'valor' => (float) ($comissoesPorInvestidor[$fid]['pendente']['valor'] ?? 0) + $pendente,
                'receita_base' => (float) ($comissoesPorInvestidor[$fid]['pendente']['receita_base'] ?? 0),
            ];
            $comissoesPorInvestidor[$fid]['pago'] = [
                'valor' => (float) ($comissoesPorInvestidor[$fid]['pago']['valor'] ?? 0) + $pago,
                'receita_base' => (float) ($comissoesPorInvestidor[$fid]['pago']['receita_base'] ?? 0) + $receita,
            ];

            if ($vid > 0 && isset($veiculosPorInvestidor[$fid]['veiculos'][$vid])) {
                $comissoesPorVeiculo[$fid][$vid] = [
                    'pendente' => ['valor' => $pendente, 'receita_base' => 0.0],
                    'pago' => ['valor' => $pago, 'receita_base' => $receita],
                ];
                continue;
            }

            if ($receita > 0 || $pendente > 0 || $pago > 0) {
                $tipoComissao = $r['comissao_tipo'] ?? '';
                $statusDiagnostico = in_array($r['disponibilidade'] ?? '', Veiculo::DISPONIBILIDADE_INATIVA, true)
                    ? 'veiculo_inativo_com_comissao'
                    : 'comissao_gerada';

                $comissoesHistoricasPorInvestidor[$fid][] = [
                    'id' => $vid,
                    'placa' => $r['placa'] ?? '',
                    'veiculo' => trim(($r['marca'] ?? '') . ' ' . ($r['modelo'] ?? '')),
                    'grupo' => $r['grupo_nome'] ?? '',
                    'tipo_comissao' => $tipoComissao,
                    'valor_comissao_config' => round((float) ($r['valor_comissao_config'] ?? 0), 2),
                    'valor_investido' => round((float) ($r['valor_compra'] ?? 0), 2),
                    'receita_gerada' => round($receita, 2),
                    'comissao_devida' => round($pendente, 2),
                    'comissao_paga' => round($pago, 2),
                    'saldo' => round($pendente, 2),
                    'status_diagnostico' => $statusDiagnostico,
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
            $vi = $veiculosPorInvestidor[$fid] ?? ['qtd_veiculos' => 0, 'valor_investido' => 0, 'veiculos' => []];
            $com = $comissoesPorInvestidor[$fid] ?? [];

            $comissaoPendente = (float) ($com['pendente']['valor'] ?? 0);
            $comissaoPaga = (float) ($com['pago']['valor'] ?? 0);
            $receitaBase = (float) ($com['pago']['receita_base'] ?? 0);
            $saldo = $comissaoPendente; // saldo a pagar = comissão pendente
            $veiculosDetalhe = [];

            foreach ($vi['veiculos'] as $vid => $veiculo) {
                $comVeiculo = $comissoesPorVeiculo[$fid][$vid] ?? [];
                $pendenteVeiculo = (float) ($comVeiculo['pendente']['valor'] ?? 0);
                $pagoVeiculo = (float) ($comVeiculo['pago']['valor'] ?? 0);
                $receitaVeiculo = (float) ($comVeiculo['pendente']['receita_base'] ?? 0) + (float) ($comVeiculo['pago']['receita_base'] ?? 0);
                $statusDiagnostico = $veiculo['status_diagnostico'];

                if ($pendenteVeiculo > 0 || $pagoVeiculo > 0 || $receitaVeiculo > 0) {
                    $statusDiagnostico = 'comissao_gerada';
                } elseif (in_array($veiculo['tipo_comissao'], ['fixo_locadora_mensal', 'fixo_investidor_mensal'], true)) {
                    $statusDiagnostico = 'comissao_mensal_nao_gerada';
                }

                $veiculosDetalhe[] = [
                    'id' => $veiculo['id'],
                    'placa' => $veiculo['placa'],
                    'veiculo' => $veiculo['veiculo'],
                    'grupo' => $veiculo['grupo'],
                    'tipo_comissao' => $veiculo['tipo_comissao'],
                    'valor_comissao_config' => round($veiculo['valor_comissao_config'], 2),
                    'valor_investido' => round($veiculo['valor_investido'], 2),
                    'receita_gerada' => round($receitaVeiculo, 2),
                    'comissao_devida' => round($pendenteVeiculo, 2),
                    'comissao_paga' => round($pagoVeiculo, 2),
                    'saldo' => round($pendenteVeiculo, 2),
                    'status_diagnostico' => $statusDiagnostico,
                ];
            }

            $veiculosDetalhe = array_merge($veiculosDetalhe, $comissoesHistoricasPorInvestidor[$fid] ?? []);
            $temDadosNoFiltro = $vi['qtd_veiculos'] > 0 || $receitaBase > 0 || $comissaoPendente > 0 || $comissaoPaga > 0;
            if ($filialIdInt > 0 && $fornecedorId === '' && !$temDadosNoFiltro) {
                continue;
            }

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
                'veiculos' => $veiculosDetalhe,
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
                    ['label' => t('modules.relatorios.fornecedores.investidor.chart_comissao_devida'), 'data' => array_map(fn($d) => $d['comissao_devida'], array_slice($details, 0, 10))],
                    ['label' => t('modules.relatorios.fornecedores.investidor.chart_comissao_paga'), 'data' => array_map(fn($d) => $d['comissao_paga'], array_slice($details, 0, 10))],
                ],
            ],
        ];
    }
}
