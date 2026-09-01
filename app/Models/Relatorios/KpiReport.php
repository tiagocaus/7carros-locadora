<?php

namespace App\Models\Relatorios;

use App\Classes\QueryBuilder;
use App\Core\Auth;
use App\Helpers\DateHelper;

/**
 * Model para relatórios de KPIs / Indicadores de Desempenho
 *
 * Métodos de agregação para os 8 relatórios de KPIs:
 * - Taxa de Ocupação
 * - RevPAR
 * - ADR (Diária Média)
 * - Ticket Médio
 * - Tempo Médio de Locação
 * - % Receitas Adicionais
 * - Margem Bruta por Dia
 * - ROI por Veículo
 */
class KpiReport extends BaseReportModel
{
    /**
     * Taxa de Ocupação da Frota
     *
     * Fórmula: (Dias Locados / Dias Disponíveis) × 100
     *
     * @return array{totals: array, details: array, chart: array}
     */
    public function taxaOcupacao(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $grupoId = '',
        string $veiculoId = ''
    ): array {
        $resumo = $this->resumirOcupacaoPeriodo(
            $dataInicio,
            $dataFim,
            $filialWhere,
            $filialParams,
            $filialId,
            $grupoId,
            $veiculoId
        );

        // Dados para gráfico (evolução mensal)
        $chartData = $this->chartTaxaOcupacaoMensal($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, $grupoId, $veiculoId);

        return [
            'totals' => $resumo['totals'],
            'details' => $resumo['details'],
            'chart' => $chartData,
        ];
    }

    /**
     * Monta a frota historicamente presente no período.
     *
     * O dia da compra entra na disponibilidade e o dia da venda nao entra.
     * Vendidos sem data ficam sempre fora: sem data_venda nao existe base
     * confiavel para reconstruir em quais relatorios historicos eles entrariam.
     */
    private function buscarFrotaNoPeriodo(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId,
        string $grupoId,
        string $veiculoId
    ): array {
        $query = $this->qb
            ->table('veiculos', 'v')
            ->select([
                'v.id',
                'v.placa',
                'v.marca',
                'v.modelo',
                'v.ano',
                'v.id_grupo',
                'v.data_compra',
                'v.data_venda',
                'v.disponibilidade',
            ])
            ->selectRaw('g.nome AS grupo_nome')
            ->leftJoin('grupos', 'g', 'v.id_grupo', '=', 'g.id');

        $this->aplicarFiltroFrotaNoPeriodo($query, $dataInicio, $dataFim);

        if (!empty($filialWhere)) {
            $query->whereRaw(str_replace('id_matriz_filial', 'v.id_matriz_filial', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) {
            $query->where('v.id_matriz_filial', '=', (int) $filialId);
        }
        if (!empty($grupoId)) {
            $query->where('v.id_grupo', '=', (int) $grupoId);
        }
        if (!empty($veiculoId)) {
            $query->where('v.id', '=', (int) $veiculoId);
        }

        $veiculos = $query->orderBy('v.placa', 'ASC')->get();
        $frota = [];

        foreach ($veiculos as $veiculo) {
            $inicioDisponibilidade = $dataInicio;
            if (!empty($veiculo['data_compra']) && $veiculo['data_compra'] > $inicioDisponibilidade) {
                $inicioDisponibilidade = $veiculo['data_compra'];
            }

            $fimDisponibilidade = $dataFim;
            if (($veiculo['disponibilidade'] ?? '') === 'V' && !empty($veiculo['data_venda'])) {
                $fimDisponibilidade = min(
                    $fimDisponibilidade,
                    DateHelper::addDaysForDatabase(-1, $veiculo['data_venda'])
                );
            }

            if ($inicioDisponibilidade > $fimDisponibilidade) {
                continue;
            }

            $veiculo['disponibilidade_inicio'] = $inicioDisponibilidade;
            $veiculo['disponibilidade_fim'] = $fimDisponibilidade;
            $veiculo['dias_disponiveis'] = $this->daysBetween($inicioDisponibilidade, $fimDisponibilidade) + 1;
            $frota[] = $veiculo;
        }

        return $frota;
    }

    /** Aplica a mesma regra historica em todas as consultas de veiculos dos KPIs. */
    private function aplicarFiltroFrotaNoPeriodo(QueryBuilder $query, string $dataInicio, string $dataFim): void
    {
        $query
            ->whereRaw('(v.data_compra IS NULL OR v.data_compra <= ?)', [$dataFim])
            ->whereRaw(
                "(COALESCE(v.disponibilidade, '') NOT IN ('V', 'RO', 'E')
                    OR (v.disponibilidade = 'V' AND v.data_venda IS NOT NULL AND v.data_venda > ?))",
                [$dataInicio]
            );
    }

    /**
     * Conta dias civis ocupados por veiculo, unificando intervalos de locacoes
     * e contratos para que o mesmo dia nunca seja contado duas vezes.
     */
    private function calcularDiasOcupadosPorVeiculo(array $frota, string $dataInicio, string $dataFim): array
    {
        if ($frota === []) {
            return [];
        }

        $ids = array_map(static fn(array $veiculo): int => (int) $veiculo['id'], $frota);
        $inicioDia = $dataInicio . ' 00:00:00';
        $fimDia = $dataFim . ' 23:59:59';

        $locacoes = $this->qb
            ->table('locacoes_veiculos', 'lv')
            ->select(['lv.id_veiculo', 'lv.data_saida', 'lv.data_entrada'])
            ->innerJoin('locacoes', 'l', 'lv.id_locacao', '=', 'l.id')
            ->whereIn('lv.id_veiculo', $ids)
            ->where('l.status', '!=', 'C')
            ->whereRaw('lv.data_saida <= ?', [$fimDia])
            ->whereRaw('COALESCE(lv.data_entrada, ?) >= ?', [$fimDia, $inicioDia])
            ->get();

        $contratos = $this->qb
            ->table('contratos_veiculos', 'cv')
            ->select(['cv.id_veiculo', 'cv.data_saida', 'cv.data_entrada'])
            ->innerJoin('contratos', 'c', 'cv.id_contrato', '=', 'c.id')
            ->whereIn('cv.id_veiculo', $ids)
            ->where('c.status', '!=', 'C')
            ->whereRaw('cv.data_saida <= ?', [$fimDia])
            ->whereRaw('COALESCE(cv.data_entrada, ?) >= ?', [$fimDia, $inicioDia])
            ->get();

        $janelas = [];
        foreach ($frota as $veiculo) {
            $janelas[(int) $veiculo['id']] = [
                'inicio' => $veiculo['disponibilidade_inicio'],
                'fim' => $veiculo['disponibilidade_fim'],
            ];
        }

        $intervalos = [];
        foreach (array_merge($locacoes, $contratos) as $vinculo) {
            $idVeiculo = (int) ($vinculo['id_veiculo'] ?? 0);
            if (!isset($janelas[$idVeiculo]) || empty($vinculo['data_saida'])) {
                continue;
            }

            $inicio = max(substr($vinculo['data_saida'], 0, 10), $janelas[$idVeiculo]['inicio']);
            $fimVinculo = !empty($vinculo['data_entrada'])
                ? substr($vinculo['data_entrada'], 0, 10)
                : $dataFim;
            $fim = min($fimVinculo, $janelas[$idVeiculo]['fim']);

            if ($inicio <= $fim) {
                $intervalos[$idVeiculo][] = ['inicio' => $inicio, 'fim' => $fim];
            }
        }

        $diasPorVeiculo = array_fill_keys($ids, 0);
        foreach ($intervalos as $idVeiculo => $faixas) {
            usort($faixas, static fn(array $a, array $b): int => $a['inicio'] <=> $b['inicio']);
            $mescladas = [];

            foreach ($faixas as $faixa) {
                $ultima = array_key_last($mescladas);
                if ($ultima === null || $faixa['inicio'] > DateHelper::addDaysForDatabase(1, $mescladas[$ultima]['fim'])) {
                    $mescladas[] = $faixa;
                    continue;
                }

                if ($faixa['fim'] > $mescladas[$ultima]['fim']) {
                    $mescladas[$ultima]['fim'] = $faixa['fim'];
                }
            }

            foreach ($mescladas as $faixa) {
                $diasPorVeiculo[$idVeiculo] += $this->daysBetween($faixa['inicio'], $faixa['fim']) + 1;
            }
        }

        return $diasPorVeiculo;
    }

    private function resumirOcupacaoPeriodo(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId,
        string $grupoId,
        string $veiculoId
    ): array {
        $frota = $this->buscarFrotaNoPeriodo(
            $dataInicio,
            $dataFim,
            $filialWhere,
            $filialParams,
            $filialId,
            $grupoId,
            $veiculoId
        );
        $diasPorVeiculo = $this->calcularDiasOcupadosPorVeiculo($frota, $dataInicio, $dataFim);

        $detalhes = array_map(function (array $veiculo) use ($diasPorVeiculo): array {
            $diasDisponiveis = (int) $veiculo['dias_disponiveis'];
            $diasLocados = min($diasDisponiveis, max(0, (int) ($diasPorVeiculo[(int) $veiculo['id']] ?? 0)));

            return [
                'id' => $veiculo['id'],
                'placa' => $veiculo['placa'],
                'veiculo' => trim(($veiculo['marca'] ?? '') . ' ' . ($veiculo['modelo'] ?? '')),
                'ano' => $veiculo['ano'],
                'grupo' => $veiculo['grupo_nome'] ?? '-',
                'dias_locados' => $diasLocados,
                'dias_parados' => $diasDisponiveis - $diasLocados,
                'taxa_ocupacao' => $this->pct($diasLocados, $diasDisponiveis),
            ];
        }, $frota);

        usort($detalhes, static function (array $a, array $b): int {
            return ($b['dias_locados'] <=> $a['dias_locados']) ?: strcmp((string) $a['placa'], (string) $b['placa']);
        });

        $diasDisponiveis = array_sum(array_column($frota, 'dias_disponiveis'));
        $diasLocados = array_sum(array_column($detalhes, 'dias_locados'));
        $diasParados = $diasDisponiveis - $diasLocados;

        return [
            'totals' => [
                'total_veiculos' => count($detalhes),
                'dias_disponiveis' => $diasDisponiveis,
                'dias_locados' => $diasLocados,
                'dias_parados' => $diasParados,
                'taxa_ocupacao' => $this->pct($diasLocados, $diasDisponiveis),
            ],
            'details' => $detalhes,
        ];
    }

    /** Mantem os demais KPIs sobre a mesma contagem unica de dias ocupados. */
    private function calcularDiasLocados(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId,
        string $grupoId,
        string $veiculoId
    ): int {
        $resumo = $this->resumirOcupacaoPeriodo(
            $dataInicio,
            $dataFim,
            $filialWhere,
            $filialParams,
            $filialId,
            $grupoId,
            $veiculoId
        );

        return (int) $resumo['totals']['dias_locados'];
    }

    /**
     * Dados para gráfico de evolução mensal da taxa de ocupação
     */
    private function chartTaxaOcupacaoMensal(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId,
        string $grupoId,
        string $veiculoId
    ): array {
        $labels = [];
        $values = [];

        $inicio = new \DateTime($dataInicio);
        $fim = new \DateTime($dataFim);

        // Se período <= 31 dias, agrupar por semana; senão por mês
        $diffDias = $this->daysBetween($dataInicio, $dataFim);

        if ($diffDias <= 31) {
            // Agrupar por semana
            $current = clone $inicio;
            while ($current <= $fim) {
                $weekEnd = clone $current;
                $weekEnd->modify('+6 days');
                if ($weekEnd > $fim) {
                    $weekEnd = clone $fim;
                }

                $labels[] = $current->format('d/m');
                $resumo = $this->resumirOcupacaoPeriodo(
                    $current->format('Y-m-d'),
                    $weekEnd->format('Y-m-d'),
                    $filialWhere,
                    $filialParams,
                    $filialId,
                    $grupoId,
                    $veiculoId
                );
                $values[] = $resumo['totals']['taxa_ocupacao'];

                $current->modify('+7 days');
            }
        } else {
            // Agrupar por mês
            $current = clone $inicio;
            while ($current <= $fim) {
                $monthStart = clone $current;
                $monthEnd = clone $current;
                $monthEnd->modify('last day of this month');
                if ($monthEnd > $fim) {
                    $monthEnd = clone $fim;
                }
                if ($monthStart < $inicio) {
                    $monthStart = clone $inicio;
                }

                $labels[] = $monthStart->format('m/Y');
                $resumo = $this->resumirOcupacaoPeriodo(
                    $monthStart->format('Y-m-d'),
                    $monthEnd->format('Y-m-d'),
                    $filialWhere,
                    $filialParams,
                    $filialId,
                    $grupoId,
                    $veiculoId
                );
                $values[] = $resumo['totals']['taxa_ocupacao'];

                $current->modify('first day of next month');
            }
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => t('modules.relatorios.kpis.taxa_ocupacao.chart_label'),
                    'data' => $values,
                ],
            ],
        ];
    }

    /**
     * RevPAR (Receita por Veículo Disponível/Dia)
     *
     * Fórmula: Receita Total de Locação / Total de Dias Disponíveis
     */
    public function revpar(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $grupoId = ''
    ): array {
        $frota = $this->buscarFrotaNoPeriodo(
            $dataInicio,
            $dataFim,
            $filialWhere,
            $filialParams,
            $filialId,
            $grupoId,
            ''
        );
        $totalVeiculos = count($frota);
        $diasDisponiveis = array_sum(array_column($frota, 'dias_disponiveis'));

        // Receita total de locações no período
        $receita = $this->calcularReceitaLocacoes($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, $grupoId);

        $revpar = $this->safeDivide($receita, $diasDisponiveis);

        // RevPAR por grupo
        $porGrupo = $this->revparPorGrupo($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, $frota);

        // Chart evolução mensal
        $chartData = $this->chartRevparMensal($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, $grupoId);

        return [
            'totals' => [
                'receita_total' => $receita,
                'dias_disponiveis' => $diasDisponiveis,
                'total_veiculos' => $totalVeiculos,
                'revpar' => $revpar,
            ],
            'details' => $porGrupo,
            'chart' => $chartData,
        ];
    }

    /**
     * Calcula receita total das locações e contratos no período
     */
    private function calcularReceitaLocacoes(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId,
        string $grupoId
    ): float {
        $inicioDia = $dataInicio . ' 00:00:00';
        $fimDia = $dataFim . ' 23:59:59';

        // Receita de locações
        $queryLoc = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw('COALESCE(SUM(l.total_fatura), 0) AS receita')
            ->where('l.status', '!=', 'C')
            ->whereRaw('l.data_saida >= ?', [$inicioDia])
            ->whereRaw('l.data_saida <= ?', [$fimDia]);

        if (!empty($filialWhere)) {
            $filialWherePrefixed = str_replace('id_matriz_filial', 'l.id_matriz_filial_retirada', $filialWhere);
            $queryLoc->whereRaw($filialWherePrefixed, $filialParams);
        }

        if (!empty($filialId)) {
            $queryLoc->where('l.id_matriz_filial_retirada', '=', (int) $filialId);
        }

        if (!empty($grupoId)) {
            $queryLoc->whereRaw('EXISTS (SELECT 1 FROM locacoes_veiculos lv WHERE lv.id_locacao = l.id AND lv.id_grupo = ?)', [(int) $grupoId]);
        }

        $resultLoc = $queryLoc->first();
        $receitaLoc = (float) ($resultLoc['receita'] ?? 0);

        // Receita de contratos (proporcional ao período)
        $queryContr = $this->qb
            ->table('contratos', 'c')
            ->selectRaw('COALESCE(SUM(c.total_fatura), 0) AS receita')
            ->where('c.status', '!=', 'C')
            ->whereRaw('c.data_ini <= ?', [$fimDia])
            ->whereRaw('c.data_fim >= ?', [$inicioDia]);

        if (!empty($filialWhere)) {
            $filialWherePrefixed = str_replace('id_matriz_filial', 'c.id_matriz_filial_retirada', $filialWhere);
            $queryContr->whereRaw($filialWherePrefixed, $filialParams);
        }

        if (!empty($filialId)) {
            $queryContr->where('c.id_matriz_filial_retirada', '=', (int) $filialId);
        }

        if (!empty($grupoId)) {
            $queryContr->whereRaw('EXISTS (SELECT 1 FROM contratos_veiculos cv WHERE cv.id_contrato = c.id AND cv.id_grupo = ?)', [(int) $grupoId]);
        }

        $resultContr = $queryContr->first();
        $receitaContr = (float) ($resultContr['receita'] ?? 0);

        return $receitaLoc + $receitaContr;
    }

    /**
     * RevPAR por grupo de veículos
     */
    private function revparPorGrupo(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId,
        array $frota
    ): array {
        $grupos = [];
        foreach ($frota as $veiculo) {
            $idGrupo = (int) ($veiculo['id_grupo'] ?? 0);
            if ($idGrupo <= 0) {
                continue;
            }
            if (!isset($grupos[$idGrupo])) {
                $grupos[$idGrupo] = [
                    'id' => $idGrupo,
                    'nome' => $veiculo['grupo_nome'] ?? '-',
                    'total_veiculos' => 0,
                    'dias_disponiveis' => 0,
                ];
            }
            $grupos[$idGrupo]['total_veiculos']++;
            $grupos[$idGrupo]['dias_disponiveis'] += (int) $veiculo['dias_disponiveis'];
        }

        uasort($grupos, static fn(array $a, array $b): int => strcmp((string) $a['nome'], (string) $b['nome']));

        $result = [];
        foreach ($grupos as $grupo) {
            $totalVeiculos = (int) $grupo['total_veiculos'];
            $diasDisp = (int) $grupo['dias_disponiveis'];
            $receita = $this->calcularReceitaLocacoes(
                $dataInicio,
                $dataFim,
                $filialWhere,
                $filialParams,
                $filialId,
                $grupo['id'] > 0 ? (string) $grupo['id'] : ''
            );

            $result[] = [
                'grupo' => $grupo['nome'],
                'total_veiculos' => $totalVeiculos,
                'receita' => $receita,
                'dias_disponiveis' => $diasDisp,
                'revpar' => $this->safeDivide($receita, $diasDisp),
            ];
        }

        return $result;
    }

    /**
     * Chart evolução mensal do RevPAR
     */
    private function chartRevparMensal(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId,
        string $grupoId
    ): array {
        $labels = [];
        $values = [];

        $current = new \DateTime($dataInicio);
        $fim = new \DateTime($dataFim);

        while ($current <= $fim) {
            $monthStart = clone $current;
            $monthEnd = clone $current;
            $monthEnd->modify('last day of this month');
            if ($monthEnd > $fim) $monthEnd = clone $fim;
            if ($monthStart < new \DateTime($dataInicio)) $monthStart = new \DateTime($dataInicio);

            $labels[] = $monthStart->format('m/Y');

            $inicioSubperiodo = $monthStart->format('Y-m-d');
            $fimSubperiodo = $monthEnd->format('Y-m-d');
            $frota = $this->buscarFrotaNoPeriodo(
                $inicioSubperiodo,
                $fimSubperiodo,
                $filialWhere,
                $filialParams,
                $filialId,
                $grupoId,
                ''
            );
            $diasDisp = array_sum(array_column($frota, 'dias_disponiveis'));
            $receita = $this->calcularReceitaLocacoes($inicioSubperiodo, $fimSubperiodo, $filialWhere, $filialParams, $filialId, $grupoId);

            $values[] = $this->safeDivide($receita, $diasDisp);

            $current->modify('first day of next month');
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => 'RevPAR', 'data' => $values],
            ],
        ];
    }

    /**
     * Diária Média (ADR - Average Daily Rate)
     *
     * Fórmula: Receita Total / Número de Diárias Vendidas
     */
    public function adr(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $grupoId = ''
    ): array {
        $receita = $this->calcularReceitaLocacoes($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, $grupoId);
        $diasLocados = $this->calcularDiasLocados($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, $grupoId, '');

        $adr = $this->safeDivide($receita, $diasLocados);

        // ADR por grupo
        $porGrupo = $this->adrPorGrupo($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId);

        // Chart
        $chartData = $this->chartAdrMensal($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, $grupoId);

        return [
            'totals' => [
                'receita_total' => $receita,
                'dias_locados' => $diasLocados,
                'adr' => $adr,
            ],
            'details' => $porGrupo,
            'chart' => $chartData,
        ];
    }

    /**
     * ADR por grupo
     */
    private function adrPorGrupo(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId
    ): array {
        $grupos = $this->qb
            ->table('grupos', 'g')
            ->select(['g.id', 'g.nome'])
            ->orderBy('g.nome', 'ASC')
            ->get();

        $result = [];
        foreach ($grupos as $grupo) {
            $receita = $this->calcularReceitaLocacoes($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, (string) $grupo['id']);
            $dias = $this->calcularDiasLocados($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, (string) $grupo['id'], '');

            if ($dias === 0 && $receita == 0) continue;

            $result[] = [
                'grupo' => $grupo['nome'],
                'receita' => $receita,
                'dias_locados' => $dias,
                'adr' => $this->safeDivide($receita, $dias),
            ];
        }

        return $result;
    }

    /**
     * Chart evolução mensal do ADR
     */
    private function chartAdrMensal(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId,
        string $grupoId
    ): array {
        $labels = [];
        $values = [];

        $current = new \DateTime($dataInicio);
        $fim = new \DateTime($dataFim);

        while ($current <= $fim) {
            $monthStart = clone $current;
            $monthEnd = clone $current;
            $monthEnd->modify('last day of this month');
            if ($monthEnd > $fim) $monthEnd = clone $fim;

            $labels[] = $monthStart->format('m/Y');

            $receita = $this->calcularReceitaLocacoes($monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d'), $filialWhere, $filialParams, $filialId, $grupoId);
            $dias = $this->calcularDiasLocados($monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d'), $filialWhere, $filialParams, $filialId, $grupoId, '');

            $values[] = $this->safeDivide($receita, $dias);

            $current->modify('first day of next month');
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => t('modules.relatorios.kpis.adr.chart_label'), 'data' => $values],
            ],
        ];
    }

    /**
     * Ticket Médio
     *
     * Fórmula: Receita Total / Número de Locações+Contratos
     */
    public function ticketMedio(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        // Total de locações no período
        $queryLoc = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw('COUNT(*) AS total, COALESCE(SUM(l.total_fatura), 0) AS receita')
            ->where('l.status', '!=', 'C')
            ->whereRaw('l.data_saida >= ?', [$dataInicio])
            ->whereRaw('l.data_saida <= ?', [$dataFim]);

        if (!empty($filialWhere)) {
            $filialWherePrefixed = str_replace('id_matriz_filial', 'l.id_matriz_filial_retirada', $filialWhere);
            $queryLoc->whereRaw($filialWherePrefixed, $filialParams);
        }

        if (!empty($filialId)) {
            $queryLoc->where('l.id_matriz_filial_retirada', '=', (int) $filialId);
        }

        $resLoc = $queryLoc->first();

        // Total de contratos no período
        $queryContr = $this->qb
            ->table('contratos', 'c')
            ->selectRaw('COUNT(*) AS total, COALESCE(SUM(c.total_fatura), 0) AS receita')
            ->where('c.status', '!=', 'C')
            ->whereRaw('c.data_ini <= ?', [$dataFim])
            ->whereRaw('c.data_fim >= ?', [$dataInicio]);

        if (!empty($filialWhere)) {
            $filialWherePrefixed = str_replace('id_matriz_filial', 'c.id_matriz_filial_retirada', $filialWhere);
            $queryContr->whereRaw($filialWherePrefixed, $filialParams);
        }

        if (!empty($filialId)) {
            $queryContr->where('c.id_matriz_filial_retirada', '=', (int) $filialId);
        }

        $resContr = $queryContr->first();

        $totalLocacoes = (int) ($resLoc['total'] ?? 0);
        $totalContratos = (int) ($resContr['total'] ?? 0);
        $totalOperacoes = $totalLocacoes + $totalContratos;
        $receitaTotal = (float) ($resLoc['receita'] ?? 0) + (float) ($resContr['receita'] ?? 0);
        $ticketMedio = $this->safeDivide($receitaTotal, $totalOperacoes);

        return [
            'totals' => [
                'receita_total' => $receitaTotal,
                'total_locacoes' => $totalLocacoes,
                'total_contratos' => $totalContratos,
                'total_operacoes' => $totalOperacoes,
                'ticket_medio' => $ticketMedio,
            ],
            'details' => [],
            'chart' => [],
        ];
    }

    /**
     * Tempo Médio de Locação
     *
     * Fórmula: Soma dos Dias / Número de Locações
     */
    public function tempoMedioLocacao(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $grupoId = ''
    ): array {
        // Locações
        $queryLoc = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw('COUNT(*) AS total, COALESCE(SUM(l.dias), 0) AS total_dias, COALESCE(AVG(l.dias), 0) AS media, COALESCE(MIN(l.dias), 0) AS minimo, COALESCE(MAX(l.dias), 0) AS maximo')
            ->where('l.status', '!=', 'C')
            ->whereRaw('l.data_saida >= ?', [$dataInicio])
            ->whereRaw('l.data_saida <= ?', [$dataFim]);

        if (!empty($filialWhere)) {
            $filialWherePrefixed = str_replace('id_matriz_filial', 'l.id_matriz_filial_retirada', $filialWhere);
            $queryLoc->whereRaw($filialWherePrefixed, $filialParams);
        }

        if (!empty($filialId)) {
            $queryLoc->where('l.id_matriz_filial_retirada', '=', (int) $filialId);
        }

        if (!empty($grupoId)) {
            $queryLoc->whereRaw('EXISTS (SELECT 1 FROM locacoes_veiculos lv WHERE lv.id_locacao = l.id AND lv.id_grupo = ?)', [(int) $grupoId]);
        }

        $resLoc = $queryLoc->first();

        // Contratos
        $queryContr = $this->qb
            ->table('contratos', 'c')
            ->selectRaw('COUNT(*) AS total, COALESCE(SUM(c.dias), 0) AS total_dias, COALESCE(AVG(c.dias), 0) AS media, COALESCE(MIN(c.dias), 0) AS minimo, COALESCE(MAX(c.dias), 0) AS maximo')
            ->where('c.status', '!=', 'C')
            ->whereRaw('c.data_ini <= ?', [$dataFim])
            ->whereRaw('c.data_fim >= ?', [$dataInicio]);

        if (!empty($filialWhere)) {
            $filialWherePrefixed = str_replace('id_matriz_filial', 'c.id_matriz_filial_retirada', $filialWhere);
            $queryContr->whereRaw($filialWherePrefixed, $filialParams);
        }

        if (!empty($filialId)) {
            $queryContr->where('c.id_matriz_filial_retirada', '=', (int) $filialId);
        }

        if (!empty($grupoId)) {
            $queryContr->whereRaw('EXISTS (SELECT 1 FROM contratos_veiculos cv WHERE cv.id_contrato = c.id AND cv.id_grupo = ?)', [(int) $grupoId]);
        }

        $resContr = $queryContr->first();

        $totalLoc = (int) ($resLoc['total'] ?? 0);
        $totalContr = (int) ($resContr['total'] ?? 0);
        $totalOperacoes = $totalLoc + $totalContr;
        $totalDias = (int) ($resLoc['total_dias'] ?? 0) + (int) ($resContr['total_dias'] ?? 0);
        $mediaGeral = $this->safeDivide($totalDias, $totalOperacoes, 1);
        $minimo = min((int) ($resLoc['minimo'] ?? 0), (int) ($resContr['minimo'] ?? 0));
        $maximo = max((int) ($resLoc['maximo'] ?? 0), (int) ($resContr['maximo'] ?? 0));

        // Distribuição por faixa
        $distribuicao = $this->distribuicaoTempoLocacao($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, $grupoId);

        return [
            'totals' => [
                'total_operacoes' => $totalOperacoes,
                'total_dias' => $totalDias,
                'media_dias' => $mediaGeral,
                'minimo' => $minimo,
                'maximo' => $maximo,
            ],
            'details' => $distribuicao,
            'chart' => [
                'labels' => array_column($distribuicao, 'faixa'),
                'datasets' => [
                    ['label' => t('modules.relatorios.kpis.tempo_medio.chart_label'), 'data' => array_column($distribuicao, 'quantidade')],
                ],
            ],
        ];
    }

    /**
     * Distribuição de locações por faixa de duração
     */
    private function distribuicaoTempoLocacao(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId,
        string $grupoId
    ): array {
        $faixas = [
            ['label' => '1 ' . t('modules.relatorios.common.day'), 'min' => 1, 'max' => 1],
            ['label' => '2-3 ' . t('modules.relatorios.common.days'), 'min' => 2, 'max' => 3],
            ['label' => '4-7 ' . t('modules.relatorios.common.days'), 'min' => 4, 'max' => 7],
            ['label' => '8-15 ' . t('modules.relatorios.common.days'), 'min' => 8, 'max' => 15],
            ['label' => '16-30 ' . t('modules.relatorios.common.days'), 'min' => 16, 'max' => 30],
            ['label' => '> 30 ' . t('modules.relatorios.common.days'), 'min' => 31, 'max' => 99999],
        ];

        $result = [];

        foreach ($faixas as $faixa) {
            $queryLoc = $this->qb
                ->table('locacoes', 'l')
                ->selectRaw('COUNT(*) AS total')
                ->where('l.status', '!=', 'C')
                ->whereRaw('l.data_saida >= ?', [$dataInicio])
                ->whereRaw('l.data_saida <= ?', [$dataFim])
                ->whereRaw('l.dias >= ?', [$faixa['min']])
                ->whereRaw('l.dias <= ?', [$faixa['max']]);

            if (!empty($filialWhere)) {
                $filialWherePrefixed = str_replace('id_matriz_filial', 'l.id_matriz_filial_retirada', $filialWhere);
                $queryLoc->whereRaw($filialWherePrefixed, $filialParams);
            }

            if (!empty($filialId)) {
                $queryLoc->where('l.id_matriz_filial_retirada', '=', (int) $filialId);
            }

            if (!empty($grupoId)) {
                $queryLoc->whereRaw('EXISTS (SELECT 1 FROM locacoes_veiculos lv WHERE lv.id_locacao = l.id AND lv.id_grupo = ?)', [(int) $grupoId]);
            }

            $resLoc = $queryLoc->first();

            $queryContr = $this->qb
                ->table('contratos', 'c')
                ->selectRaw('COUNT(*) AS total')
                ->where('c.status', '!=', 'C')
                ->whereRaw('c.data_ini <= ?', [$dataFim])
                ->whereRaw('c.data_fim >= ?', [$dataInicio])
                ->whereRaw('c.dias >= ?', [$faixa['min']])
                ->whereRaw('c.dias <= ?', [$faixa['max']]);

            if (!empty($filialWhere)) {
                $filialWherePrefixed = str_replace('id_matriz_filial', 'c.id_matriz_filial_retirada', $filialWhere);
                $queryContr->whereRaw($filialWherePrefixed, $filialParams);
            }

            if (!empty($filialId)) {
                $queryContr->where('c.id_matriz_filial_retirada', '=', (int) $filialId);
            }

            if (!empty($grupoId)) {
                $queryContr->whereRaw('EXISTS (SELECT 1 FROM contratos_veiculos cv WHERE cv.id_contrato = c.id AND cv.id_grupo = ?)', [(int) $grupoId]);
            }

            $resContr = $queryContr->first();

            $total = (int) ($resLoc['total'] ?? 0) + (int) ($resContr['total'] ?? 0);

            $result[] = [
                'faixa' => $faixa['label'],
                'quantidade' => $total,
            ];
        }

        return $result;
    }

    /**
     * % Receitas Adicionais
     *
     * Fórmula: (Receita de Adicionais / Receita Total) × 100
     */
    public function receitasAdicionais(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        // Receita de taxas/serviços de locações
        $queryTaxasLoc = $this->qb
            ->table('locacoes_taxaseservicos', 'lt')
            ->selectRaw('lt.nome, COALESCE(SUM(lt.valor_total), 0) AS receita')
            ->innerJoin('locacoes', 'l', 'lt.id_locacao', '=', 'l.id')
            ->where('l.status', '!=', 'C')
            ->whereRaw('l.data_saida >= ?', [$dataInicio])
            ->whereRaw('l.data_saida <= ?', [$dataFim])
            ->groupBy('lt.nome')
            ->orderByRaw('receita DESC');

        if (!empty($filialWhere)) {
            $filialWherePrefixed = str_replace('id_matriz_filial', 'l.id_matriz_filial_retirada', $filialWhere);
            $queryTaxasLoc->whereRaw($filialWherePrefixed, $filialParams);
        }

        if (!empty($filialId)) {
            $queryTaxasLoc->where('l.id_matriz_filial_retirada', '=', (int) $filialId);
        }

        $taxasLoc = $queryTaxasLoc->get();

        // Receita de taxas/serviços de contratos
        $queryTaxasContr = $this->qb
            ->table('contratos_taxaseservicos', 'ct')
            ->selectRaw('ct.nome, COALESCE(SUM(ct.valor_total), 0) AS receita')
            ->innerJoin('contratos', 'c', 'ct.id_contrato', '=', 'c.id')
            ->where('c.status', '!=', 'C')
            ->whereRaw('c.data_ini <= ?', [$dataFim])
            ->whereRaw('c.data_fim >= ?', [$dataInicio])
            ->groupBy('ct.nome')
            ->orderByRaw('receita DESC');

        if (!empty($filialWhere)) {
            $filialWherePrefixed = str_replace('id_matriz_filial', 'c.id_matriz_filial_retirada', $filialWhere);
            $queryTaxasContr->whereRaw($filialWherePrefixed, $filialParams);
        }

        if (!empty($filialId)) {
            $queryTaxasContr->where('c.id_matriz_filial_retirada', '=', (int) $filialId);
        }

        $taxasContr = $queryTaxasContr->get();

        // Merge taxas
        $taxasMerge = [];
        foreach (array_merge($taxasLoc, $taxasContr) as $taxa) {
            $nome = $taxa['nome'];
            if (!isset($taxasMerge[$nome])) {
                $taxasMerge[$nome] = 0;
            }
            $taxasMerge[$nome] += (float) $taxa['receita'];
        }

        $totalAdicionais = array_sum($taxasMerge);
        $receitaTotal = $this->calcularReceitaLocacoes($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, '');
        $percentual = $this->pct($totalAdicionais, $receitaTotal);

        // Breakdown por tipo
        $breakdown = [];
        arsort($taxasMerge);
        foreach ($taxasMerge as $nome => $valor) {
            $breakdown[] = [
                'nome' => $nome,
                'receita' => $valor,
                'percentual' => $this->pct($valor, $receitaTotal),
            ];
        }

        $receitaLocacao = $receitaTotal - $totalAdicionais;

        return [
            'totals' => [
                'receita_locacao' => $receitaLocacao,
                'receita_adicionais' => $totalAdicionais,
                'receita_total' => $receitaTotal,
                'percentual_adicionais' => $percentual,
            ],
            'details' => $breakdown,
            'chart' => [
                'labels' => array_column($breakdown, 'nome'),
                'datasets' => [
                    ['label' => t('modules.relatorios.kpis.receitas_adicionais.chart_label'), 'data' => array_column($breakdown, 'receita')],
                ],
            ],
        ];
    }

    /**
     * Receita por Veículo
     */
    public function receitaPorVeiculo(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $grupoId = '',
        int $page = 1,
        int $perPage = 20
    ): array {
        $chave = Auth::chave();
        $inicioDia = $dataInicio . ' 00:00:00';
        $fimDia = $dataFim . ' 23:59:59';

        $query = $this->qb
            ->table('veiculos', 'v')
            ->select([
                'v.id',
                'v.placa',
                'v.marca',
                'v.modelo',
            ])
            ->selectRaw('g.nome AS grupo_nome')
            ->selectRaw("COALESCE((
                SELECT SUM(l2.total_fatura)
                FROM locacoes l2
                INNER JOIN locacoes_veiculos lv2 ON lv2.id_locacao = l2.id
                WHERE lv2.id_veiculo = v.id
                AND l2.chave = '{$chave}'
                AND l2.status != 'C'
                AND l2.data_saida >= '{$inicioDia}'
                AND l2.data_saida <= '{$fimDia}'
            ), 0) AS receita_locacoes")
            ->selectRaw("COALESCE((
                SELECT SUM(lt2.valor_total)
                FROM locacoes_taxaseservicos lt2
                INNER JOIN locacoes l3 ON lt2.id_locacao = l3.id
                INNER JOIN locacoes_veiculos lv3 ON lv3.id_locacao = l3.id
                WHERE lv3.id_veiculo = v.id
                AND l3.chave = '{$chave}'
                AND l3.status != 'C'
                AND l3.data_saida >= '{$inicioDia}'
                AND l3.data_saida <= '{$fimDia}'
            ), 0) AS receita_taxas")
            ->selectRaw("COALESCE((
                SELECT SUM(DATEDIFF(
                    LEAST(COALESCE(lv4.data_entrada, '{$fimDia}'), '{$fimDia}'),
                    GREATEST(lv4.data_saida, '{$inicioDia}')
                ) + 1)
                FROM locacoes_veiculos lv4
                INNER JOIN locacoes l4 ON lv4.id_locacao = l4.id
                WHERE lv4.id_veiculo = v.id
                AND l4.chave = '{$chave}'
                AND l4.status != 'C'
                AND lv4.data_saida <= '{$fimDia}'
                AND COALESCE(lv4.data_entrada, '{$fimDia}') >= '{$inicioDia}'
            ), 0) AS dias_locados")
            ->leftJoin('grupos', 'g', 'v.id_grupo', '=', 'g.id')
            ->orderByRaw('(receita_locacoes + receita_taxas) DESC')
            ->paginate($page, $perPage);

        $this->aplicarFiltroFrotaNoPeriodo($query, $dataInicio, $dataFim);

        if (!empty($filialWhere)) {
            $filialWherePrefixed = str_replace('id_matriz_filial', 'v.id_matriz_filial', $filialWhere);
            $query->whereRaw($filialWherePrefixed, $filialParams);
        }

        if (!empty($filialId)) {
            $query->where('v.id_matriz_filial', '=', (int) $filialId);
        }

        if (!empty($grupoId)) {
            $query->where('v.id_grupo', '=', (int) $grupoId);
        }

        $veiculos = $query->get();

        // Calcular receita total para %
        $receitaGeral = $this->calcularReceitaLocacoes($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, $grupoId);

        $details = array_map(function ($v) use ($receitaGeral) {
            $receitaLoc = (float) $v['receita_locacoes'];
            $receitaTaxas = (float) $v['receita_taxas'];
            $receitaTotal = $receitaLoc + $receitaTaxas;
            $diasLocados = max(0, (int) $v['dias_locados']);

            return [
                'id' => $v['id'],
                'placa' => $v['placa'],
                'veiculo' => trim(($v['marca'] ?? '') . ' ' . ($v['modelo'] ?? '')),
                'grupo' => $v['grupo_nome'] ?? '-',
                'receita_locacao' => $receitaLoc,
                'receita_taxas' => $receitaTaxas,
                'receita_total' => $receitaTotal,
                'dias_locados' => $diasLocados,
                'receita_dia' => $this->safeDivide($receitaTotal, $diasLocados),
                'percentual_faturamento' => $this->pct($receitaTotal, $receitaGeral),
            ];
        }, $veiculos);

        // Contagem total para paginação
        $queryCount = $this->qb
            ->table('veiculos', 'v')
            ->selectRaw('COUNT(*) AS total');

        $this->aplicarFiltroFrotaNoPeriodo($queryCount, $dataInicio, $dataFim);

        if (!empty($filialWhere)) {
            $filialWherePrefixed = str_replace('id_matriz_filial', 'v.id_matriz_filial', $filialWhere);
            $queryCount->whereRaw($filialWherePrefixed, $filialParams);
        }

        if (!empty($filialId)) {
            $queryCount->where('v.id_matriz_filial', '=', (int) $filialId);
        }

        if (!empty($grupoId)) {
            $queryCount->where('v.id_grupo', '=', (int) $grupoId);
        }

        $total = (int) ($queryCount->first()['total'] ?? 0);

        return [
            'totals' => [
                'receita_total' => $receitaGeral,
            ],
            'details' => $details,
            'chart' => [],
            'total' => $total,
        ];
    }

    /**
     * Margem Bruta por Dia
     *
     * Fórmula: (Receita - Custos Variáveis) / Dias Locados
     */
    public function margemBruta(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $grupoId = ''
    ): array {
        $chave = Auth::chave();

        $receita = $this->calcularReceitaLocacoes($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, $grupoId);
        $diasLocados = $this->calcularDiasLocados($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, $grupoId, '');
        $custos = $this->calcularCustosVariaveis($chave, $dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, $grupoId);

        $margemBruta = $receita - $custos;
        $margemPorDia = $this->safeDivide($margemBruta, $diasLocados);
        $percentualMargem = $this->pct($margemBruta, $receita);

        return [
            'totals' => [
                'receita_total' => $receita,
                'custos_variaveis' => $custos,
                'margem_bruta' => $margemBruta,
                'dias_locados' => $diasLocados,
                'margem_por_dia' => $margemPorDia,
                'percentual_margem' => $percentualMargem,
            ],
            'details' => [],
            'chart' => [],
        ];
    }

    /**
     * Calcula custos variáveis (despesas) no período
     */
    private function calcularCustosVariaveis(
        string $chave,
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId,
        string $grupoId
    ): float {
        if (!empty($grupoId)) {
            return (float) array_sum($this->financeiroVeiculoSomas('D', $dataInicio, $dataFim, [
                'date_field' => 'data_criada',
                'filial_where' => $filialWhere,
                'filial_params' => $filialParams,
                'filial_id' => $filialId,
                'grupo_id' => $grupoId,
            ]));
        }

        $query = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw('COALESCE(SUM(f.valor_total), 0) AS total_custos')
            ->where('f.tipo', '=', 'D')
            ->whereRaw('f.data_criada >= ?', [$dataInicio])
            ->whereRaw('f.data_criada <= ?', [$dataFim]);

        if (!empty($filialWhere)) {
            $filialWherePrefixed = str_replace('id_matriz_filial', 'f.id_matriz_filial', $filialWhere);
            $query->whereRaw($filialWherePrefixed, $filialParams);
        }

        if (!empty($filialId)) {
            $query->where('f.id_matriz_filial', '=', (int) $filialId);
        }

        $result = $query->first();
        return (float) ($result['total_custos'] ?? 0);
    }

    /**
     * ROI por Veículo
     *
     * Fórmula: ((Receita - Custos) / Valor de Aquisição) × 100
     */
    public function roiPorVeiculo(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $grupoId = '',
        int $page = 1,
        int $perPage = 20
    ): array {
        // Base: receita por veículo
        $result = $this->receitaPorVeiculo($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, $grupoId, $page, $perPage);

        // Enriquecer com dados de custo e ROI
        foreach ($result['details'] as &$veiculo) {
            $veiculoData = $this->buscarDadosVeiculo((int) $veiculo['id']);
            $custos = $this->calcularCustosVeiculo((int) $veiculo['id'], $dataInicio, $dataFim);

            $valorCompra = (float) ($veiculoData['valor_compra'] ?? 0);
            $receitaTotal = $veiculo['receita_total'];
            $lucroLiquido = $receitaTotal - $custos;
            $roi = $valorCompra > 0 ? round(($lucroLiquido / $valorCompra) * 100, 2) : 0;

            $veiculo['valor_compra'] = $valorCompra;
            $veiculo['custos'] = $custos;
            $veiculo['lucro_liquido'] = $lucroLiquido;
            $veiculo['roi'] = $roi;
        }
        unset($veiculo);

        // Ordenar por ROI desc
        usort($result['details'], fn($a, $b) => $b['roi'] <=> $a['roi']);

        return $result;
    }

    /**
     * Busca dados do veículo (valor_compra)
     */
    private function buscarDadosVeiculo(int $id): array
    {
        return $this->qb
            ->table('veiculos')
            ->select(['valor_compra', 'data_compra'])
            ->where('id', '=', $id)
            ->first() ?? [];
    }

    /**
     * Calcula custos financeiros de um veículo no período
     */
    private function calcularCustosVeiculo(int $veiculoId, string $dataInicio, string $dataFim): float
    {
        return (float) array_sum($this->financeiroVeiculoSomas('D', $dataInicio, $dataFim, [
            'date_field' => 'data_criada',
            'veiculo_id' => (string) $veiculoId,
        ]));
    }
}
