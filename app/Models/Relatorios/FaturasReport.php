<?php

namespace App\Models\Relatorios;

/**
 * Relatorios da categoria FATURAS.
 *
 * 7.1 Faturas Vencidas / A Vencer  -> faturasVencidasAVencer()
 * 7.2 Faturas por Veiculo (futuro)
 * 7.3 Pagar/Receber (futuro)
 */
class FaturasReport extends BaseReportModel
{
    /**
     * Aplica filtro de filial (mesmo padrao do FinanceiroReport).
     */
    private function applyFilialFilter($query, string $filialWhere, array $filialParams, string $filialId, string $prefix = 'f'): void
    {
        if (!empty($filialId)) {
            $query->whereRaw("{$prefix}.id_matriz_filial = ?", [(int) $filialId]);
        } elseif (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }
    }

    private function applyClienteFilter($query, string $clienteId, string $prefix = 'f'): void
    {
        if ($clienteId !== '') {
            $query->whereRaw("{$prefix}.id_cliente = ?", [(int) $clienteId]);
        }
    }

    private function applyPeriodoVencimentoFilter($query, string $dataInicio, string $dataFim, string $prefix = 'f'): void
    {
        $query->whereRaw("{$prefix}.data_venci BETWEEN ? AND ?", [$dataInicio, $dataFim]);
    }

    private function applyFornecedorFilter($query, string $fornecedorId, string $prefix = 'f'): void
    {
        if ($fornecedorId !== '') {
            $query->whereRaw("{$prefix}.id_fornecedor = ?", [(int) $fornecedorId]);
        }
    }

    private function applyVeiculoFinanceiroFilter($query, string $veiculoId, string $prefix = 'f'): void
    {
        if ($veiculoId === '') {
            return;
        }

        $query->whereRaw(
            "({$prefix}.id_veiculo = ? OR EXISTS (
                SELECT 1
                FROM financeiro_itens fi
                WHERE fi.id_financeiro = {$prefix}.id
                    AND fi.chave = {$prefix}.chave
                    AND fi.id_veiculo = ?
            ))",
            [(int) $veiculoId, (int) $veiculoId]
        );
    }

    private function applyStatusContaFilter($query, string $status, string $prefix = 'f'): void
    {
        match ($status) {
            'pago' => $query->whereRaw("{$prefix}.pago = ?", ['S']),
            'pendente' => $query
                ->whereRaw("{$prefix}.pago = ?", ['N'])
                ->whereRaw("{$prefix}.data_venci >= CURDATE()"),
            'vencida' => $query
                ->whereRaw("{$prefix}.pago = ?", ['N'])
                ->whereRaw("{$prefix}.data_venci < CURDATE()"),
            default => null,
        };
    }

    /**
     * 7.1 - Faturas Vencidas / A Vencer.
     *
     * Visao "vencidas": faturas com data_venci < hoje E pago='N'
     *   - aging em buckets: 1-7, 8-15, 16-30, 31-60, 61-90, >90 dias
     *
     * Visao "a_vencer": faturas com data_venci >= hoje E pago='N'
     *   - buckets: hoje, 7d, 15d, 30d, >30d
     *
     * @param string $visao 'vencidas' ou 'a_vencer'
     * @param string $dataInicio Data inicial de vencimento (Y-m-d)
     * @param string $dataFim Data final de vencimento (Y-m-d)
     * @return array{totals: array, details: array, chart: array}
     */
    public function faturasVencidasAVencer(
        string $visao,
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $clienteId = ''
    ): array {
        $visao = $visao === 'a_vencer' ? 'a_vencer' : 'vencidas';

        // --- Totais gerais (vencidas + a vencer, independentes da visao escolhida) ---
        $queryTotaisVencidas = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                COALESCE(SUM(f.valor_total), 0) AS total_vencido,
                COUNT(*) AS qtd_vencidas
            ")
            ->whereRaw('f.tipo = ?', ['R'])
            ->whereRaw('f.pago = ?', ['N'])
            ->whereRaw('f.data_venci < CURDATE()');
        $this->applyPeriodoVencimentoFilter($queryTotaisVencidas, $dataInicio, $dataFim);
        $this->applyFilialFilter($queryTotaisVencidas, $filialWhere, $filialParams, $filialId);
        $this->applyClienteFilter($queryTotaisVencidas, $clienteId);

        $resultVencidas = $queryTotaisVencidas->first() ?? ['total_vencido' => 0, 'qtd_vencidas' => 0];

        $queryTotaisAVencer = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                COALESCE(SUM(f.valor_total), 0) AS total_a_vencer,
                COUNT(*) AS qtd_a_vencer
            ")
            ->whereRaw('f.tipo = ?', ['R'])
            ->whereRaw('f.pago = ?', ['N'])
            ->whereRaw('f.data_venci >= CURDATE()');
        $this->applyPeriodoVencimentoFilter($queryTotaisAVencer, $dataInicio, $dataFim);
        $this->applyFilialFilter($queryTotaisAVencer, $filialWhere, $filialParams, $filialId);
        $this->applyClienteFilter($queryTotaisAVencer, $clienteId);

        $resultAVencer = $queryTotaisAVencer->first() ?? ['total_a_vencer' => 0, 'qtd_a_vencer' => 0];

        $totals = [
            'total_vencido' => (float) $resultVencidas['total_vencido'],
            'qtd_vencidas' => (int) $resultVencidas['qtd_vencidas'],
            'total_a_vencer' => (float) $resultAVencer['total_a_vencer'],
            'qtd_a_vencer' => (int) $resultAVencer['qtd_a_vencer'],
        ];

        // --- Lista de faturas (depende da visao) ---
        $queryLista = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                f.id,
                f.codigo,
                f.sequencia,
                f.parcela,
                f.total_parcelas,
                f.descricao,
                f.data_venci,
                f.valor_subtotal,
                COALESCE(f.juros, 0) AS juros,
                COALESCE(f.multa, 0) AS multa,
                f.valor_total,
                cl.nome_rsocial AS cliente_nome,
                DATEDIFF(CURDATE(), f.data_venci) AS dias_atraso,
                DATEDIFF(f.data_venci, CURDATE()) AS dias_para_vencer
            ")
            ->leftJoin('clientes', 'cl', 'f.id_cliente', '=', 'cl.id')
            ->whereRaw('f.tipo = ?', ['R'])
            ->whereRaw('f.pago = ?', ['N']);

        if ($visao === 'vencidas') {
            $queryLista->whereRaw('f.data_venci < CURDATE()');
            $queryLista->orderByRaw('f.data_venci ASC');
        } else {
            $queryLista->whereRaw('f.data_venci >= CURDATE()');
            $queryLista->orderByRaw('f.data_venci ASC');
        }

        $this->applyPeriodoVencimentoFilter($queryLista, $dataInicio, $dataFim);
        $this->applyFilialFilter($queryLista, $filialWhere, $filialParams, $filialId);
        $this->applyClienteFilter($queryLista, $clienteId);

        $faturas = $queryLista->get();

        $details = array_map(function ($row) use ($visao) {
            $diasAtraso = (int) ($row['dias_atraso'] ?? 0);
            $diasAVencer = (int) ($row['dias_para_vencer'] ?? 0);

            return [
                'id' => (int) $row['id'],
                'codigo' => $row['codigo'] ?: ('#' . $row['sequencia']),
                'parcela_label' => $row['total_parcelas'] > 1
                    ? ($row['parcela'] . '/' . $row['total_parcelas'])
                    : '-',
                'cliente' => $row['cliente_nome'] ?? '-',
                'descricao' => $row['descricao'] ?? '',
                'data_venci' => $row['data_venci'],
                'valor_subtotal' => (float) $row['valor_subtotal'],
                'juros_multa' => (float) $row['juros'] + (float) $row['multa'],
                'valor_total' => (float) $row['valor_total'],
                'dias' => $visao === 'vencidas' ? $diasAtraso : $diasAVencer,
            ];
        }, $faturas);

        // --- Chart: aging (vencidas) ou prazos (a vencer) ---
        if ($visao === 'vencidas') {
            $queryAging = $this->qb
                ->table('financeiro', 'f')
                ->selectRaw("
                    CASE
                        WHEN DATEDIFF(CURDATE(), f.data_venci) BETWEEN 1 AND 7 THEN '1-7'
                        WHEN DATEDIFF(CURDATE(), f.data_venci) BETWEEN 8 AND 15 THEN '8-15'
                        WHEN DATEDIFF(CURDATE(), f.data_venci) BETWEEN 16 AND 30 THEN '16-30'
                        WHEN DATEDIFF(CURDATE(), f.data_venci) BETWEEN 31 AND 60 THEN '31-60'
                        WHEN DATEDIFF(CURDATE(), f.data_venci) BETWEEN 61 AND 90 THEN '61-90'
                        ELSE '90+'
                    END AS faixa,
                    COALESCE(SUM(f.valor_total), 0) AS valor
                ")
                ->whereRaw('f.tipo = ?', ['R'])
                ->whereRaw('f.pago = ?', ['N'])
                ->whereRaw('f.data_venci < CURDATE()')
                ->whereRaw('f.data_venci BETWEEN ? AND ?', [$dataInicio, $dataFim])
                ->groupBy('faixa')
                ->orderByRaw("CASE faixa WHEN '1-7' THEN 1 WHEN '8-15' THEN 2 WHEN '16-30' THEN 3 WHEN '31-60' THEN 4 WHEN '61-90' THEN 5 ELSE 6 END");
            $this->applyFilialFilter($queryAging, $filialWhere, $filialParams, $filialId);
            $this->applyClienteFilter($queryAging, $clienteId);

            $rows = $queryAging->get();
            $faixasOrdem = ['1-7', '8-15', '16-30', '31-60', '61-90', '90+'];
            $valorPorFaixa = [];
            foreach ($faixasOrdem as $f) {
                $valorPorFaixa[$f] = 0;
            }
            foreach ($rows as $r) {
                $valorPorFaixa[$r['faixa']] = (float) $r['valor'];
            }

            $labels = [];
            $values = [];
            foreach ($faixasOrdem as $f) {
                $labels[] = $f . ' ' . \t('modules.relatorios.faturas.vencidas_a_vencer.dias');
                $values[] = $valorPorFaixa[$f];
            }
        } else {
            $queryPrazos = $this->qb
                ->table('financeiro', 'f')
                ->selectRaw("
                    CASE
                        WHEN DATEDIFF(f.data_venci, CURDATE()) = 0 THEN 'hoje'
                        WHEN DATEDIFF(f.data_venci, CURDATE()) BETWEEN 1 AND 7 THEN '1-7'
                        WHEN DATEDIFF(f.data_venci, CURDATE()) BETWEEN 8 AND 15 THEN '8-15'
                        WHEN DATEDIFF(f.data_venci, CURDATE()) BETWEEN 16 AND 30 THEN '16-30'
                        ELSE '30+'
                    END AS faixa,
                    COALESCE(SUM(f.valor_total), 0) AS valor
                ")
                ->whereRaw('f.tipo = ?', ['R'])
                ->whereRaw('f.pago = ?', ['N'])
                ->whereRaw('f.data_venci >= CURDATE()')
                ->whereRaw('f.data_venci BETWEEN ? AND ?', [$dataInicio, $dataFim])
                ->groupBy('faixa')
                ->orderByRaw("CASE faixa WHEN 'hoje' THEN 1 WHEN '1-7' THEN 2 WHEN '8-15' THEN 3 WHEN '16-30' THEN 4 ELSE 5 END");
            $this->applyFilialFilter($queryPrazos, $filialWhere, $filialParams, $filialId);
            $this->applyClienteFilter($queryPrazos, $clienteId);

            $rows = $queryPrazos->get();
            $faixasOrdem = ['hoje', '1-7', '8-15', '16-30', '30+'];
            $valorPorFaixa = [];
            foreach ($faixasOrdem as $f) {
                $valorPorFaixa[$f] = 0;
            }
            foreach ($rows as $r) {
                $valorPorFaixa[$r['faixa']] = (float) $r['valor'];
            }

            $labelsTraduzidos = [
                'hoje' => \t('modules.relatorios.faturas.vencidas_a_vencer.hoje'),
                '1-7' => '1-7 ' . \t('modules.relatorios.faturas.vencidas_a_vencer.dias'),
                '8-15' => '8-15 ' . \t('modules.relatorios.faturas.vencidas_a_vencer.dias'),
                '16-30' => '16-30 ' . \t('modules.relatorios.faturas.vencidas_a_vencer.dias'),
                '30+' => '30+ ' . \t('modules.relatorios.faturas.vencidas_a_vencer.dias'),
            ];

            $labels = [];
            $values = [];
            foreach ($faixasOrdem as $f) {
                $labels[] = $labelsTraduzidos[$f];
                $values[] = $valorPorFaixa[$f];
            }
        }

        return [
            'totals' => $totals,
            'details' => [
                'visao' => $visao,
                'lista' => $details,
            ],
            'chart' => [
                'labels' => $labels,
                'data' => $values,
            ],
        ];
    }

    /**
     * 7.2 - Faturas por Veiculo.
     *
     * Para cada veiculo com faturas no periodo, retorna agregacao:
     * total_faturas, valor_total, total_pago, total_pendente, total_vencido.
     *
     * @return array{totals: array, details: array, chart: array}
     */
    public function faturasPorVeiculo(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $modo = 'agrupado',
        string $veiculoId = ''
    ): array {
        $modo = $modo === 'individualizado' ? 'individualizado' : 'agrupado';
        $veiculoId = ($veiculoId !== '' && ctype_digit($veiculoId) && (int) $veiculoId > 0) ? $veiculoId : '';

        if ($modo === 'individualizado') {
            return $this->faturasPorVeiculoIndividualizado($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, $veiculoId);
        }

        // --- Lista agregada por veiculo ---
        $query = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                v.id AS id_veiculo,
                v.placa,
                v.marca,
                v.modelo,
                v.ano,
                COUNT(*) AS total_faturas,
                COALESCE(SUM(f.valor_total), 0) AS valor_total,
                COALESCE(SUM(CASE WHEN f.pago = 'S' THEN f.valor_total ELSE 0 END), 0) AS total_pago,
                COALESCE(SUM(CASE WHEN f.pago = 'N' AND f.data_venci >= CURDATE() THEN f.valor_total ELSE 0 END), 0) AS total_pendente,
                COALESCE(SUM(CASE WHEN f.pago = 'N' AND f.data_venci < CURDATE() THEN f.valor_total ELSE 0 END), 0) AS total_vencido
            ")
            ->innerJoin('veiculos', 'v', 'f.id_veiculo', '=', 'v.id')
            ->whereRaw('f.tipo = ?', ['R'])
            ->whereRaw('f.data_criada BETWEEN ? AND ?', [$dataInicio, $dataFim])
            ->groupBy(['v.id', 'v.placa', 'v.marca', 'v.modelo', 'v.ano'])
            ->orderByRaw('valor_total DESC');

        $this->applyFilialFilter($query, $filialWhere, $filialParams, $filialId);

        if ($veiculoId !== '') {
            $query->whereRaw('f.id_veiculo = ?', [(int) $veiculoId]);
        }

        $rows = $query->get();

        $details = array_map(function ($row) {
            return [
                'id_veiculo' => (int) $row['id_veiculo'],
                'placa' => $row['placa'] ?? '-',
                'veiculo' => trim(($row['marca'] ?? '') . ' ' . ($row['modelo'] ?? '')),
                'ano' => $row['ano'] ?? null,
                'total_faturas' => (int) $row['total_faturas'],
                'valor_total' => (float) $row['valor_total'],
                'total_pago' => (float) $row['total_pago'],
                'total_pendente' => (float) $row['total_pendente'],
                'total_vencido' => (float) $row['total_vencido'],
            ];
        }, $rows);

        // --- Totalizadores gerais ---
        $totals = [
            'total_faturas' => array_sum(array_column($details, 'total_faturas')),
            'valor_total' => (float) array_sum(array_column($details, 'valor_total')),
            'total_pago' => (float) array_sum(array_column($details, 'total_pago')),
            'total_pendente' => (float) array_sum(array_column($details, 'total_pendente')),
            'total_vencido' => (float) array_sum(array_column($details, 'total_vencido')),
        ];

        // --- Chart: top 10 veiculos por valor ---
        $top = array_slice($details, 0, 10);
        $chartLabels = [];
        $chartData = [];
        foreach ($top as $r) {
            $chartLabels[] = $r['placa'] . ' - ' . $r['veiculo'];
            $chartData[] = $r['valor_total'];
        }

        return [
            'totals' => $totals,
            'details' => [
                'modo' => 'agrupado',
                'lista' => $details,
            ],
            'chart' => [
                'labels' => $chartLabels,
                'data' => $chartData,
            ],
        ];
    }

    private function faturasPorVeiculoIndividualizado(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $veiculoId = ''
    ): array {
        $query = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                f.id,
                f.codigo,
                f.sequencia,
                f.parcela,
                f.total_parcelas,
                f.descricao,
                f.data_venci,
                f.data_pago,
                f.pago,
                f.valor_total,
                v.id AS id_veiculo,
                v.placa,
                v.marca,
                v.modelo,
                v.ano,
                cl.nome_rsocial AS cliente_nome
            ")
            ->innerJoin('veiculos', 'v', 'f.id_veiculo', '=', 'v.id')
            ->leftJoin('clientes', 'cl', 'f.id_cliente', '=', 'cl.id')
            ->whereRaw('f.tipo = ?', ['R'])
            ->whereRaw('f.data_criada BETWEEN ? AND ?', [$dataInicio, $dataFim])
            ->orderByRaw('f.data_venci ASC, f.id ASC');

        $this->applyFilialFilter($query, $filialWhere, $filialParams, $filialId);

        if ($veiculoId !== '') {
            $query->whereRaw('f.id_veiculo = ?', [(int) $veiculoId]);
        }

        $rows = $query->get();

        $details = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'codigo' => $row['codigo'] ?: ('#' . $row['sequencia']),
                'parcela_label' => ((int) ($row['total_parcelas'] ?? 1)) > 1
                    ? (($row['parcela'] ?? 1) . '/' . ($row['total_parcelas'] ?? 1))
                    : '-',
                'id_veiculo' => (int) $row['id_veiculo'],
                'placa' => $row['placa'] ?? '-',
                'veiculo' => trim(($row['marca'] ?? '') . ' ' . ($row['modelo'] ?? '')),
                'ano' => $row['ano'] ?? null,
                'cliente' => $row['cliente_nome'] ?? '-',
                'descricao' => $row['descricao'] ?? '',
                'data_venci' => $row['data_venci'],
                'data_pago' => $row['data_pago'],
                'valor_total' => (float) $row['valor_total'],
                'status' => $this->statusConta($row['pago'] ?? null, $row['data_venci'] ?? null),
            ];
        }, $rows);

        $totals = [
            'total_faturas' => count($details),
            'valor_total' => (float) array_sum(array_column($details, 'valor_total')),
            'total_pago' => (float) array_sum(array_map(fn($r) => $r['status'] === 'pago' ? $r['valor_total'] : 0, $details)),
            'total_pendente' => (float) array_sum(array_map(fn($r) => $r['status'] === 'pendente' ? $r['valor_total'] : 0, $details)),
            'total_vencido' => (float) array_sum(array_map(fn($r) => $r['status'] === 'vencida' ? $r['valor_total'] : 0, $details)),
        ];

        $valorPorVeiculo = [];
        foreach ($details as $row) {
            $label = $row['placa'] . ' - ' . $row['veiculo'];
            $valorPorVeiculo[$label] = ($valorPorVeiculo[$label] ?? 0) + $row['valor_total'];
        }
        arsort($valorPorVeiculo);
        $top = array_slice($valorPorVeiculo, 0, 10, true);

        return [
            'totals' => $totals,
            'details' => [
                'modo' => 'individualizado',
                'lista' => $details,
            ],
            'chart' => [
                'labels' => array_keys($top),
                'data' => array_values($top),
            ],
        ];
    }

    /**
     * 7.3 - Contas a Pagar / a Receber.
     *
     * Visao consolidada das contas no periodo, separando entradas (tipo=R) e
     * saidas (tipo=D), com saldo previsto e timeline mensal.
     *
     * @return array{totals: array, details: array, chart: array}
     */
    public function pagarReceber(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $clienteId = '',
        string $fornecedorId = '',
        string $veiculoId = '',
        string $status = ''
    ): array {
        // --- Lista RECEBER (tipo=R) ---
        $qReceber = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                f.id, f.codigo, f.sequencia,
                f.descricao, f.data_venci, f.data_pago, f.pago,
                f.valor_total,
                cl.nome_rsocial AS cliente_nome
            ")
            ->leftJoin('clientes', 'cl', 'f.id_cliente', '=', 'cl.id')
            ->whereRaw('f.tipo = ?', ['R'])
            ->whereRaw('f.data_venci BETWEEN ? AND ?', [$dataInicio, $dataFim])
            ->orderByRaw('f.data_venci ASC');
        $this->applyFilialFilter($qReceber, $filialWhere, $filialParams, $filialId);
        $this->applyClienteFilter($qReceber, $clienteId);
        $this->applyVeiculoFinanceiroFilter($qReceber, $veiculoId);
        $this->applyStatusContaFilter($qReceber, $status);
        if ($fornecedorId !== '' && $clienteId === '') {
            $qReceber->whereRaw('1 = 0');
        }
        $rowsReceber = $qReceber->get();

        $listaReceber = array_map(fn($r) => $this->mapLinhaContaCliente($r), $rowsReceber);

        // --- Lista PAGAR (tipo=D) ---
        $qPagar = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                f.id, f.codigo, f.sequencia,
                f.descricao, f.data_venci, f.data_pago, f.pago,
                f.valor_total,
                fo.nome_rsocial AS fornecedor_nome
            ")
            ->leftJoin('fornecedores', 'fo', 'f.id_fornecedor', '=', 'fo.id')
            ->whereRaw('f.tipo = ?', ['D'])
            ->whereRaw('f.data_venci BETWEEN ? AND ?', [$dataInicio, $dataFim])
            ->orderByRaw('f.data_venci ASC');
        $this->applyFilialFilter($qPagar, $filialWhere, $filialParams, $filialId);
        $this->applyFornecedorFilter($qPagar, $fornecedorId);
        $this->applyVeiculoFinanceiroFilter($qPagar, $veiculoId);
        $this->applyStatusContaFilter($qPagar, $status);
        if ($clienteId !== '' && $fornecedorId === '') {
            $qPagar->whereRaw('1 = 0');
        }
        $rowsPagar = $qPagar->get();

        $listaPagar = array_map(fn($r) => $this->mapLinhaContaFornecedor($r), $rowsPagar);

        // --- Totalizadores ---
        $totalReceber = (float) array_sum(array_column($listaReceber, 'valor_total'));
        $totalPagar   = (float) array_sum(array_column($listaPagar, 'valor_total'));
        $saldo = $totalReceber - $totalPagar;

        $totals = [
            'total_receber' => $totalReceber,
            'total_pagar' => $totalPagar,
            'saldo' => $saldo,
            'qtd_receber' => count($listaReceber),
            'qtd_pagar' => count($listaPagar),
        ];

        // --- Chart: fluxo mensal coerente com as listas filtradas ---
        [$labels, $entradas, $saidas] = $this->buildFluxoMensalPagarReceber($listaReceber, $listaPagar);

        return [
            'totals' => $totals,
            'details' => [
                'receber' => $listaReceber,
                'pagar' => $listaPagar,
            ],
            'chart' => [
                'labels' => $labels,
                'datasets' => [
                    ['label' => \t('modules.relatorios.faturas.pagar_receber.entradas'), 'data' => $entradas, 'kind' => 'entrada'],
                    ['label' => \t('modules.relatorios.faturas.pagar_receber.saidas'), 'data' => $saidas, 'kind' => 'saida'],
                ],
            ],
        ];
    }

    private function buildFluxoMensalPagarReceber(array $listaReceber, array $listaPagar): array
    {
        $porMes = [];

        foreach ($listaReceber as $row) {
            if (empty($row['data_venci'])) {
                continue;
            }
            $mes = substr((string) $row['data_venci'], 0, 7);
            $porMes[$mes]['entrada'] = ($porMes[$mes]['entrada'] ?? 0) + (float) $row['valor_total'];
            $porMes[$mes]['saida'] = $porMes[$mes]['saida'] ?? 0;
        }

        foreach ($listaPagar as $row) {
            if (empty($row['data_venci'])) {
                continue;
            }
            $mes = substr((string) $row['data_venci'], 0, 7);
            $porMes[$mes]['entrada'] = $porMes[$mes]['entrada'] ?? 0;
            $porMes[$mes]['saida'] = ($porMes[$mes]['saida'] ?? 0) + (float) $row['valor_total'];
        }

        ksort($porMes);

        $labels = [];
        $entradas = [];
        $saidas = [];

        foreach ($porMes as $mes => $valores) {
            $labels[] = $this->formatMesLabel($mes);
            $entradas[] = (float) ($valores['entrada'] ?? 0);
            $saidas[] = (float) ($valores['saida'] ?? 0);
        }

        return [$labels, $entradas, $saidas];
    }

    private function mapLinhaContaCliente(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'codigo' => $r['codigo'] ?: ('#' . $r['sequencia']),
            'pessoa' => $r['cliente_nome'] ?? '-',
            'descricao' => $r['descricao'] ?? '',
            'data_venci' => $r['data_venci'],
            'data_pago' => $r['data_pago'],
            'valor_total' => (float) $r['valor_total'],
            'status' => $this->statusConta($r['pago'], $r['data_venci']),
        ];
    }

    private function mapLinhaContaFornecedor(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'codigo' => $r['codigo'] ?: ('#' . $r['sequencia']),
            'pessoa' => $r['fornecedor_nome'] ?? '-',
            'descricao' => $r['descricao'] ?? '',
            'data_venci' => $r['data_venci'],
            'data_pago' => $r['data_pago'],
            'valor_total' => (float) $r['valor_total'],
            'status' => $this->statusConta($r['pago'], $r['data_venci']),
        ];
    }

    private function statusConta(?string $pago, ?string $dataVenci): string
    {
        if ($pago === 'S') return 'pago';
        if ($dataVenci && $dataVenci < date('Y-m-d')) return 'vencida';
        return 'pendente';
    }

    private function formatMesLabel(string $ym): string
    {
        // ym = "2026-04" -> "04/2026"
        $parts = explode('-', $ym);
        if (count($parts) === 2) return $parts[1] . '/' . $parts[0];
        return $ym;
    }
}
