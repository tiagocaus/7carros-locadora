<?php

namespace App\Models\Relatorios;

use App\Core\Auth;

/**
 * Model para relatórios da categoria Veicular
 *
 * Métodos de agregação para os 11 relatórios:
 * - Manutenções
 * - Lucro por Veículo
 * - Despesas Veicular
 * - Veículo/Cliente
 * - Licenciamento
 * - Disponibilidade
 * - Taxa de Ocupação por Grupo
 * - Depreciação
 * - Tempo Médio Parado
 * - Quilometragem Média
 * - Custo Total de Propriedade (TCO)
 */
class VeicularReport extends BaseReportModel
{
    /**
     * Manutenções Veicular
     *
     * Lista manutenções no período com totalizadores de custo, dias parados
     * e custo por km rodado. Agrupa custos no eixo mensal para gráfico.
     *
     * Status: C=Criada (pendente), A=Aberta (em andamento), F=Fechada (concluída)
     *
     * @return array{totals: array, details: array, chart: array}
     */
    public function manutencoes(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $statusFiltro = '',
        string $oficinaId = '',
        array $veiculoIds = []
    ): array {
        $details = $this->detalhesManutencoes(
            $dataInicio, $dataFim, $filialWhere, $filialParams,
            $filialId, $statusFiltro, $oficinaId, $veiculoIds
        );

        // Totalizadores
        $totalManutencoes = count($details);
        $custoTotal = 0.0;
        $diasParadosTotal = 0;
        $kmRodadoTotal = 0;

        foreach ($details as $row) {
            $custoTotal += (float) $row['valor'];
            $diasParadosTotal += (int) $row['dias_parado'];
            // Para custo/km usamos diferença entre odo_retorno e odo_enviado quando ambos existem
            if (!empty($row['_km_diff'])) {
                $kmRodadoTotal += (int) $row['_km_diff'];
            }
        }

        $custoMedio = $this->safeDivide($custoTotal, $totalManutencoes);
        $custoPorKm = $this->safeDivide($custoTotal, $kmRodadoTotal, 4);

        // Limpar campo auxiliar `_km_diff` antes de retornar
        foreach ($details as &$row) {
            unset($row['_km_diff']);
        }
        unset($row);

        return [
            'totals' => [
                'total_manutencoes' => $totalManutencoes,
                'custo_total' => round($custoTotal, 2),
                'custo_medio' => $custoMedio,
                'dias_parados_total' => $diasParadosTotal,
                'custo_por_km' => $custoPorKm,
            ],
            'details' => $details,
            'chart' => $this->chartManutencoesMensal(
                $dataInicio, $dataFim, $filialWhere, $filialParams,
                $filialId, $statusFiltro, $oficinaId, $veiculoIds
            ),
        ];
    }

    /**
     * Detalhes das manutenções no período
     */
    private function detalhesManutencoes(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId,
        string $statusFiltro,
        string $oficinaId,
        array $veiculoIds
    ): array {
        $query = $this->qb
            ->table('manutencoes', 'm')
            ->select([
                'm.id',
                'm.os',
                'm.status',
                'm.motivo',
                'm.data_enviado',
                'm.data_retorno',
                'm.odo_enviado',
                'm.odo_retorno',
                'm.total_servicos',
                'm.total_pago',
                'm.total_pendente',
                'v.placa',
                'v.marca',
                'v.modelo',
            ])
            ->selectRaw('o.empresa AS oficina_nome')
            ->leftJoin('veiculos', 'v', 'm.id_veiculo', '=', 'v.id')
            ->leftJoin('oficinas', 'o', 'm.id_oficina', '=', 'o.id')
            ->whereRaw('COALESCE(m.data_enviado, m.created_at) >= ?', [$dataInicio . ' 00:00:00'])
            ->whereRaw('COALESCE(m.data_enviado, m.created_at) <= ?', [$dataFim . ' 23:59:59']);

        if (!empty($filialWhere)) {
            $filialPrefixed = str_replace('id_matriz_filial', 'm.id_matriz_filial', $filialWhere);
            $query->whereRaw($filialPrefixed, $filialParams);
        }

        if (!empty($filialId)) {
            $query->where('m.id_matriz_filial', '=', (int) $filialId);
        }

        if (!empty($statusFiltro)) {
            $query->where('m.status', '=', $statusFiltro);
        }

        if (!empty($oficinaId)) {
            $query->where('m.id_oficina', '=', (int) $oficinaId);
        }

        if (!empty($veiculoIds)) {
            $query->whereIn('m.id_veiculo', $veiculoIds);
        }

        $rows = $query->orderByRaw('COALESCE(m.data_enviado, m.created_at) DESC')->get();

        return array_map(function ($r) {
            $diasParado = 0;
            if (!empty($r['data_enviado'])) {
                $fim = !empty($r['data_retorno']) ? $r['data_retorno'] : date('Y-m-d H:i:s');
                $diasParado = max(0, (int) ceil((strtotime($fim) - strtotime($r['data_enviado'])) / 86400));
            }
            $kmDiff = 0;
            if (!empty($r['odo_enviado']) && !empty($r['odo_retorno'])) {
                $ini = (int) preg_replace('/\D/', '', $r['odo_enviado']);
                $fim = (int) preg_replace('/\D/', '', $r['odo_retorno']);
                $kmDiff = max(0, $fim - $ini);
            }
            return [
                'id' => (int) $r['id'],
                'os' => $r['os'] ?? '',
                'placa' => $r['placa'] ?? '-',
                'veiculo' => trim(($r['marca'] ?? '') . ' ' . ($r['modelo'] ?? '')) ?: '-',
                'descricao' => $r['motivo'] ?? '',
                'oficina' => $r['oficina_nome'] ?? '-',
                'data_entrada' => $r['data_enviado'],
                'data_saida' => $r['data_retorno'],
                'dias_parado' => $diasParado,
                'valor' => round((float) ($r['total_servicos'] ?? 0), 2),
                'pago' => round((float) ($r['total_pago'] ?? 0), 2),
                'pendente' => round((float) ($r['total_pendente'] ?? 0), 2),
                'km' => $r['odo_enviado'] ?? '',
                'status' => $r['status'] ?? '',
                'status_label' => $this->labelStatusManutencao($r['status'] ?? ''),
                '_km_diff' => $kmDiff,
            ];
        }, $rows);
    }

    /**
     * Custos de manutenção agregados por mês para gráfico
     */
    private function chartManutencoesMensal(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId,
        string $statusFiltro,
        string $oficinaId,
        array $veiculoIds
    ): array {
        $query = $this->qb
            ->table('manutencoes', 'm')
            ->selectRaw("DATE_FORMAT(COALESCE(m.data_enviado, m.created_at), '%Y-%m') AS mes, COALESCE(SUM(m.total_servicos), 0) AS custo, COUNT(*) AS qtd")
            ->whereRaw('COALESCE(m.data_enviado, m.created_at) >= ?', [$dataInicio . ' 00:00:00'])
            ->whereRaw('COALESCE(m.data_enviado, m.created_at) <= ?', [$dataFim . ' 23:59:59']);

        if (!empty($filialWhere)) {
            $filialPrefixed = str_replace('id_matriz_filial', 'm.id_matriz_filial', $filialWhere);
            $query->whereRaw($filialPrefixed, $filialParams);
        }
        if (!empty($filialId)) $query->where('m.id_matriz_filial', '=', (int) $filialId);
        if (!empty($statusFiltro)) $query->where('m.status', '=', $statusFiltro);
        if (!empty($oficinaId)) $query->where('m.id_oficina', '=', (int) $oficinaId);
        if (!empty($veiculoIds)) $query->whereIn('m.id_veiculo', $veiculoIds);

        $rows = $query->groupBy("DATE_FORMAT(COALESCE(m.data_enviado, m.created_at), '%Y-%m')")
            ->orderByRaw("DATE_FORMAT(COALESCE(m.data_enviado, m.created_at), '%Y-%m')")
            ->get();

        $labels = [];
        $custos = [];
        $qtds = [];
        foreach ($rows as $r) {
            $labels[] = $r['mes'];
            $custos[] = round((float) $r['custo'], 2);
            $qtds[] = (int) $r['qtd'];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Custo (R$)', 'data' => $custos],
                ['label' => 'Quantidade', 'data' => $qtds],
            ],
        ];
    }

    private function labelStatusManutencao(string $status): string
    {
        return match ($status) {
            'C' => 'Pendente',
            'A' => 'Em andamento',
            'F' => 'Concluída',
            default => $status,
        };
    }

    // =====================================================
    // 3.2 LUCRO POR VEÍCULO
    // =====================================================

    /**
     * Lucro por Veículo
     *
     * Receita: locacoes.total_fatura (status A/F) + contratos.total_fatura (status A/F),
     * agregado por veículo via locacoes_veiculos / contratos_veiculos.
     * Despesa: manutencoes.total_servicos + multas.valor + veiculos_encargos.valor por id_veiculo.
     * Lucro = Receita − Despesa; Margem = Lucro / Receita.
     */
    public function lucroVeiculo(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $grupoId = '',
        string $veiculoId = ''
    ): array {
        $receitaPorVeiculo = $this->somaReceitaPorVeiculo($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, $grupoId, $veiculoId);
        $manutPorVeiculo = $this->somaManutencoesPorVeiculo($dataInicio, $dataFim, $filialId, $veiculoId);
        $multasPorVeiculo = $this->somaMultasPorVeiculo($dataInicio, $dataFim, $filialId, $veiculoId);
        $encargosPorVeiculo = $this->somaEncargosPorVeiculo($dataInicio, $dataFim, $veiculoId);

        // Lista de veículos (todos que tiveram qualquer movimento OU foram filtrados)
        $idsVeiculos = array_unique(array_merge(
            array_keys($receitaPorVeiculo),
            array_keys($manutPorVeiculo),
            array_keys($multasPorVeiculo),
            array_keys($encargosPorVeiculo)
        ));

        $veiculosInfo = $this->buscarVeiculosInfo($idsVeiculos);

        $details = [];
        $totalReceita = 0.0;
        $totalManut = 0.0;
        $totalMultas = 0.0;
        $totalEncargos = 0.0;

        foreach ($veiculosInfo as $vid => $vinfo) {
            $receita = (float) ($receitaPorVeiculo[$vid] ?? 0);
            $manut = (float) ($manutPorVeiculo[$vid] ?? 0);
            $mults = (float) ($multasPorVeiculo[$vid] ?? 0);
            $encs = (float) ($encargosPorVeiculo[$vid] ?? 0);
            $despesa = $manut + $mults + $encs;
            $lucro = $receita - $despesa;
            $margem = $this->pct($lucro, $receita);

            $details[] = [
                'id' => (int) $vid,
                'placa' => $vinfo['placa'] ?? '-',
                'veiculo' => trim(($vinfo['marca'] ?? '') . ' ' . ($vinfo['modelo'] ?? '')) ?: '-',
                'grupo' => $vinfo['grupo_nome'] ?? '-',
                'receita' => round($receita, 2),
                'manutencao' => round($manut, 2),
                'multas' => round($mults, 2),
                'encargos' => round($encs, 2),
                'despesa_total' => round($despesa, 2),
                'lucro' => round($lucro, 2),
                'margem' => $margem,
            ];

            $totalReceita += $receita;
            $totalManut += $manut;
            $totalMultas += $mults;
            $totalEncargos += $encs;
        }

        usort($details, fn($a, $b) => $b['lucro'] <=> $a['lucro']);

        $totalDespesa = $totalManut + $totalMultas + $totalEncargos;
        $lucroTotal = $totalReceita - $totalDespesa;

        return [
            'totals' => [
                'receita_total' => round($totalReceita, 2),
                'despesa_total' => round($totalDespesa, 2),
                'lucro_total' => round($lucroTotal, 2),
                'margem_geral' => $this->pct($lucroTotal, $totalReceita),
                'qtd_veiculos' => count($details),
            ],
            'details' => $details,
            'chart' => $this->chartLucroTopN($details, 10),
        ];
    }

    /**
     * Soma de receita por id_veiculo no período usando financeiro.id_veiculo direto.
     *
     * Pré-requisito: migration 00344_backfill_financeiro_id_veiculo.php aplicada,
     * para que registros pré-2026 também tenham id_veiculo preenchido.
     *
     * Captura: receitas de locação, contrato, parcelas avulsas, adicionais —
     * tudo que tiver tipo='R' e id_veiculo preenchido.
     */
    private function somaReceitaPorVeiculo(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId,
        string $grupoId,
        string $veiculoId
    ): array {
        $query = $this->qb
            ->table('financeiro', 'f')
            ->select(['f.id_veiculo'])
            ->selectRaw('COALESCE(SUM(f.valor_total), 0) AS receita')
            ->where('f.tipo', '=', 'R')
            ->whereNotNull('f.id_veiculo')
            ->whereBetween('f.data_venci', $dataInicio, $dataFim);

        if (!empty($filialWhere)) {
            $query->whereRaw(str_replace('id_matriz_filial', 'f.id_matriz_filial', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $query->where('f.id_matriz_filial', '=', (int) $filialId);
        if (!empty($veiculoId)) $query->where('f.id_veiculo', '=', (int) $veiculoId);

        // Filtro de grupo exige JOIN em veiculos
        if (!empty($grupoId)) {
            $query->innerJoin('veiculos', 'v', 'f.id_veiculo', '=', 'v.id')
                  ->where('v.id_grupo', '=', (int) $grupoId);
        }

        $rows = $query->groupBy('f.id_veiculo')->get();

        $resultado = [];
        foreach ($rows as $r) {
            $resultado[(int) $r['id_veiculo']] = (float) $r['receita'];
        }
        return $resultado;
    }

    private function somaManutencoesPorVeiculo(string $dataInicio, string $dataFim, string $filialId, string $veiculoId): array
    {
        $query = $this->qb
            ->table('manutencoes', 'm')
            ->select(['m.id_veiculo'])
            ->selectRaw('COALESCE(SUM(m.total_servicos), 0) AS valor')
            ->whereRaw('COALESCE(m.data_enviado, m.created_at) >= ?', [$dataInicio . ' 00:00:00'])
            ->whereRaw('COALESCE(m.data_enviado, m.created_at) <= ?', [$dataFim . ' 23:59:59'])
            ->whereNotNull('m.id_veiculo');

        if (!empty($filialId)) $query->where('m.id_matriz_filial', '=', (int) $filialId);
        if (!empty($veiculoId)) $query->where('m.id_veiculo', '=', (int) $veiculoId);

        $rows = $query->groupBy('m.id_veiculo')->get();
        $out = [];
        foreach ($rows as $r) $out[(int) $r['id_veiculo']] = (float) $r['valor'];
        return $out;
    }

    private function somaMultasPorVeiculo(string $dataInicio, string $dataFim, string $filialId, string $veiculoId): array
    {
        $query = $this->qb
            ->table('multas', 'mt')
            ->select(['mt.id_veiculo'])
            ->selectRaw('COALESCE(SUM(mt.valor), 0) AS valor')
            ->whereBetween('mt.data_hora', $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59')
            ->whereNotNull('mt.id_veiculo');

        if (!empty($filialId)) $query->where('mt.id_matriz_filial', '=', (int) $filialId);
        if (!empty($veiculoId)) $query->where('mt.id_veiculo', '=', (int) $veiculoId);

        $rows = $query->groupBy('mt.id_veiculo')->get();
        $out = [];
        foreach ($rows as $r) $out[(int) $r['id_veiculo']] = (float) $r['valor'];
        return $out;
    }

    private function somaEncargosPorVeiculo(string $dataInicio, string $dataFim, string $veiculoId): array
    {
        $query = $this->qb
            ->table('veiculos_encargos', 've')
            ->select(['ve.id_veiculo'])
            ->selectRaw('COALESCE(SUM(ve.valor), 0) AS valor')
            ->whereBetween('ve.vencimento', $dataInicio, $dataFim)
            ->where('ve.ativo', '=', 1);

        if (!empty($veiculoId)) $query->where('ve.id_veiculo', '=', (int) $veiculoId);

        $rows = $query->groupBy('ve.id_veiculo')->get();
        $out = [];
        foreach ($rows as $r) $out[(int) $r['id_veiculo']] = (float) $r['valor'];
        return $out;
    }

    private function buscarVeiculosInfo(array $ids): array
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

    private function chartLucroTopN(array $details, int $n): array
    {
        $top = array_slice($details, 0, $n);
        $labels = array_map(fn($r) => $r['placa'], $top);
        $receita = array_map(fn($r) => $r['receita'], $top);
        $despesa = array_map(fn($r) => $r['despesa_total'], $top);
        $lucro = array_map(fn($r) => $r['lucro'], $top);

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Receita', 'data' => $receita],
                ['label' => 'Despesa', 'data' => $despesa],
                ['label' => 'Lucro', 'data' => $lucro],
            ],
        ];
    }

    // =====================================================
    // 3.3 DESPESAS VEICULAR
    // =====================================================

    /**
     * Despesas Veicular
     *
     * Detalha despesas por categoria (Manutenção, Multas, Encargos, Outros) por veículo.
     */
    public function despesasVeicular(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $grupoId = '',
        string $veiculoId = ''
    ): array {
        $manutPorVeiculo = $this->somaManutencoesPorVeiculo($dataInicio, $dataFim, $filialId, $veiculoId);
        $multasPorVeiculo = $this->somaMultasPorVeiculo($dataInicio, $dataFim, $filialId, $veiculoId);
        $encargosPorVeiculo = $this->somaEncargosPorVeiculo($dataInicio, $dataFim, $veiculoId);
        $outrasPorVeiculo = $this->somaOutrasDespesasPorVeiculo($dataInicio, $dataFim, $filialId, $veiculoId);

        $idsVeiculos = array_unique(array_merge(
            array_keys($manutPorVeiculo),
            array_keys($multasPorVeiculo),
            array_keys($encargosPorVeiculo),
            array_keys($outrasPorVeiculo)
        ));

        if (!empty($grupoId) && !empty($idsVeiculos)) {
            $idsVeiculos = $this->filtrarVeiculosPorGrupo($idsVeiculos, (int) $grupoId);
        }

        $veiculosInfo = $this->buscarVeiculosInfo($idsVeiculos);

        $details = [];
        $tot = ['manutencao' => 0.0, 'multas' => 0.0, 'encargos' => 0.0, 'outros' => 0.0];

        foreach ($veiculosInfo as $vid => $vinfo) {
            $manut = (float) ($manutPorVeiculo[$vid] ?? 0);
            $mults = (float) ($multasPorVeiculo[$vid] ?? 0);
            $encs = (float) ($encargosPorVeiculo[$vid] ?? 0);
            $outr = (float) ($outrasPorVeiculo[$vid] ?? 0);
            $total = $manut + $mults + $encs + $outr;

            $details[] = [
                'id' => (int) $vid,
                'placa' => $vinfo['placa'] ?? '-',
                'veiculo' => trim(($vinfo['marca'] ?? '') . ' ' . ($vinfo['modelo'] ?? '')) ?: '-',
                'grupo' => $vinfo['grupo_nome'] ?? '-',
                'manutencao' => round($manut, 2),
                'multas' => round($mults, 2),
                'encargos' => round($encs, 2),
                'outros' => round($outr, 2),
                'total' => round($total, 2),
            ];

            $tot['manutencao'] += $manut;
            $tot['multas'] += $mults;
            $tot['encargos'] += $encs;
            $tot['outros'] += $outr;
        }

        usort($details, fn($a, $b) => $b['total'] <=> $a['total']);

        $totalGeral = $tot['manutencao'] + $tot['multas'] + $tot['encargos'] + $tot['outros'];

        return [
            'totals' => [
                'manutencao_total' => round($tot['manutencao'], 2),
                'multas_total' => round($tot['multas'], 2),
                'encargos_total' => round($tot['encargos'], 2),
                'outros_total' => round($tot['outros'], 2),
                'total_geral' => round($totalGeral, 2),
                'qtd_veiculos' => count($details),
            ],
            'details' => $details,
            'chart' => [
                'labels' => ['Manutenção', 'Multas', 'Encargos', 'Outros'],
                'datasets' => [[
                    'label' => 'Despesas (R$)',
                    'data' => [
                        round($tot['manutencao'], 2),
                        round($tot['multas'], 2),
                        round($tot['encargos'], 2),
                        round($tot['outros'], 2),
                    ],
                ]],
            ],
        ];
    }

    private function somaOutrasDespesasPorVeiculo(string $dataInicio, string $dataFim, string $filialId, string $veiculoId): array
    {
        $query = $this->qb
            ->table('financeiro', 'f')
            ->select(['f.id_veiculo'])
            ->selectRaw('COALESCE(SUM(f.valor_total), 0) AS valor')
            ->where('f.tipo', '=', 'D')
            ->whereNotNull('f.id_veiculo')
            ->whereBetween('f.data_venci', $dataInicio, $dataFim)
            ->whereNull('f.id_multa')
            ->whereNull('f.id_oficina');

        if (!empty($filialId)) $query->where('f.id_matriz_filial', '=', (int) $filialId);
        if (!empty($veiculoId)) $query->where('f.id_veiculo', '=', (int) $veiculoId);

        $rows = $query->groupBy('f.id_veiculo')->get();
        $out = [];
        foreach ($rows as $r) $out[(int) $r['id_veiculo']] = (float) $r['valor'];
        return $out;
    }

    private function filtrarVeiculosPorGrupo(array $ids, int $grupoId): array
    {
        if (empty($ids)) return [];
        $rows = $this->qb
            ->table('veiculos')
            ->select(['id'])
            ->whereIn('id', $ids)
            ->where('id_grupo', '=', $grupoId)
            ->get();
        return array_map(fn($r) => (int) $r['id'], $rows);
    }

    // =====================================================
    // 3.4 VEÍCULO/CLIENTE
    // =====================================================

    /**
     * Veículo/Cliente — histórico de locações e contratos por veículo, mostrando clientes.
     */
    public function veiculoCliente(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $grupoId = '',
        string $veiculoId = '',
        string $clienteId = ''
    ): array {
        $rows = [];

        // Locações
        $queryL = $this->qb
            ->table('locacoes_veiculos', 'lv')
            ->select([
                'lv.id_veiculo',
                'lv.data_saida',
                'lv.data_entrada',
                'lv.odometro_saida',
                'lv.odometro_entrada',
                'l.id AS id_locacao',
                'l.codigo',
                'l.cliente_nome',
                'l.id_cliente',
                'l.total_fatura',
            ])
            ->selectRaw("'L' AS origem")
            ->selectRaw('v.placa, v.marca, v.modelo')
            ->selectRaw('g.nome AS grupo_nome')
            ->innerJoin('locacoes', 'l', 'lv.id_locacao', '=', 'l.id')
            ->leftJoin('veiculos', 'v', 'lv.id_veiculo', '=', 'v.id')
            ->leftJoin('grupos', 'g', 'v.id_grupo', '=', 'g.id')
            ->whereIn('l.status', ['A', 'F'])
            ->whereBetween('lv.data_saida', $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59');

        if (!empty($filialWhere)) {
            $queryL->whereRaw(str_replace('id_matriz_filial', 'l.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryL->where('l.id_matriz_filial_retirada', '=', (int) $filialId);
        if (!empty($grupoId)) $queryL->where('lv.id_grupo', '=', (int) $grupoId);
        if (!empty($veiculoId)) $queryL->where('lv.id_veiculo', '=', (int) $veiculoId);
        if (!empty($clienteId)) $queryL->where('l.id_cliente', '=', (int) $clienteId);

        $locacoes = $queryL->orderBy('lv.data_saida', 'DESC')->get();

        foreach ($locacoes as $r) {
            $rows[] = $this->mapearLinhaVeiculoCliente($r, 'L');
        }

        // Contratos
        $queryC = $this->qb
            ->table('contratos_veiculos', 'cv')
            ->select([
                'cv.id_veiculo',
                'cv.data_saida',
                'cv.data_entrada',
                'cv.odometro_saida',
                'cv.odometro_entrada',
                'c.id AS id_contrato',
                'c.codigo',
                'c.cliente_nome',
                'c.id_cliente',
                'c.total_fatura',
            ])
            ->selectRaw("'C' AS origem")
            ->selectRaw('v.placa, v.marca, v.modelo')
            ->selectRaw('g.nome AS grupo_nome')
            ->innerJoin('contratos', 'c', 'cv.id_contrato', '=', 'c.id')
            ->leftJoin('veiculos', 'v', 'cv.id_veiculo', '=', 'v.id')
            ->leftJoin('grupos', 'g', 'v.id_grupo', '=', 'g.id')
            ->whereIn('c.status', ['A', 'F'])
            ->whereBetween('cv.data_saida', $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59');

        if (!empty($filialWhere)) {
            $queryC->whereRaw(str_replace('id_matriz_filial', 'c.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryC->where('c.id_matriz_filial_retirada', '=', (int) $filialId);
        if (!empty($grupoId)) $queryC->where('cv.id_grupo', '=', (int) $grupoId);
        if (!empty($veiculoId)) $queryC->where('cv.id_veiculo', '=', (int) $veiculoId);
        if (!empty($clienteId)) $queryC->where('c.id_cliente', '=', (int) $clienteId);

        $contratos = $queryC->orderBy('cv.data_saida', 'DESC')->get();

        foreach ($contratos as $r) {
            $rows[] = $this->mapearLinhaVeiculoCliente($r, 'C');
        }

        usort($rows, fn($a, $b) => strcmp($b['data_inicio'] ?? '', $a['data_inicio'] ?? ''));

        // Totalizadores
        $totalReceita = 0.0;
        $totalDias = 0;
        $totalKm = 0;
        $clientesUnicos = [];

        foreach ($rows as $r) {
            $totalReceita += (float) $r['valor'];
            $totalDias += (int) $r['dias'];
            $totalKm += (int) $r['km_rodado'];
            if (!empty($r['id_cliente'])) $clientesUnicos[$r['id_cliente']] = true;
        }

        return [
            'totals' => [
                'qtd_locacoes' => count($rows),
                'qtd_clientes' => count($clientesUnicos),
                'receita_total' => round($totalReceita, 2),
                'dias_total' => $totalDias,
                'km_total' => $totalKm,
            ],
            'details' => $rows,
            'chart' => [],
        ];
    }

    private function mapearLinhaVeiculoCliente(array $r, string $origem): array
    {
        $dataIni = $r['data_saida'] ?? '';
        $dataFim = $r['data_entrada'] ?? '';
        $dias = 0;
        if (!empty($dataIni)) {
            $fim = !empty($dataFim) ? $dataFim : date('Y-m-d H:i:s');
            $dias = max(0, (int) ceil((strtotime($fim) - strtotime($dataIni)) / 86400));
        }

        $kmIni = (int) ($r['odometro_saida'] ?? 0);
        $kmFim = (int) ($r['odometro_entrada'] ?? 0);
        $kmRodado = ($kmFim > 0 && $kmFim >= $kmIni) ? ($kmFim - $kmIni) : 0;

        return [
            'tipo' => $origem === 'L' ? 'Locação' : 'Contrato',
            'codigo' => $r['codigo'] ?? '',
            'placa' => $r['placa'] ?? '-',
            'veiculo' => trim(($r['marca'] ?? '') . ' ' . ($r['modelo'] ?? '')) ?: '-',
            'grupo' => $r['grupo_nome'] ?? '-',
            'cliente' => $r['cliente_nome'] ?? '-',
            'id_cliente' => (int) ($r['id_cliente'] ?? 0),
            'data_inicio' => $dataIni,
            'data_fim' => $dataFim,
            'dias' => $dias,
            'km_inicial' => $kmIni,
            'km_final' => $kmFim,
            'km_rodado' => $kmRodado,
            'valor' => round((float) ($r['total_fatura'] ?? 0), 2),
        ];
    }

    // =====================================================
    // 3.5 LICENCIAMENTO
    // =====================================================

    /**
     * Licenciamento — controle de IPVA, Licenciamento, Seguro e demais encargos por veículo.
     *
     * Status calculado a partir de `vencimento` vs hoje:
     *  - vencido: vencimento < hoje E não pago
     *  - prox_30: vencimento <= hoje + 30 dias E não pago
     *  - em_dia: pago OU vencimento > hoje + 30 dias
     */
    public function licenciamento(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $grupoId = '',
        string $statusFiltro = ''
    ): array {
        $hoje = date('Y-m-d');
        $limiteAlerta = date('Y-m-d', strtotime('+30 days'));

        $query = $this->qb
            ->table('veiculos_encargos', 've')
            ->select([
                've.id',
                've.id_veiculo',
                've.nome',
                've.descricao',
                've.valor',
                've.vencimento',
                've.recorrencia',
                've.id_financeiro',
                've.ativo',
            ])
            ->selectRaw('v.placa, v.marca, v.modelo, v.id_grupo')
            ->selectRaw('g.nome AS grupo_nome')
            ->selectRaw('f.pago AS financeiro_pago')
            ->innerJoin('veiculos', 'v', 've.id_veiculo', '=', 'v.id')
            ->leftJoin('grupos', 'g', 'v.id_grupo', '=', 'g.id')
            ->leftJoin('financeiro', 'f', 've.id_financeiro', '=', 'f.id')
            ->where('ve.ativo', '=', 1)
            ->whereNotNull('ve.vencimento')
            ->whereBetween('ve.vencimento', $dataInicio, $dataFim);

        if (!empty($filialWhere)) {
            $query->whereRaw(str_replace('id_matriz_filial', 'v.id_matriz_filial', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $query->where('v.id_matriz_filial', '=', (int) $filialId);
        if (!empty($grupoId)) $query->where('v.id_grupo', '=', (int) $grupoId);

        $rows = $query->orderBy('ve.vencimento', 'ASC')->get();

        $details = [];
        $tot = ['vencido' => 0, 'prox_30' => 0, 'em_dia' => 0, 'valor_total' => 0.0];

        foreach ($rows as $r) {
            $venci = $r['vencimento'];
            $pago = ($r['financeiro_pago'] ?? 'N') === 'S';

            if ($pago || $venci > $limiteAlerta) {
                $status = 'em_dia';
            } elseif ($venci < $hoje) {
                $status = 'vencido';
            } else {
                $status = 'prox_30';
            }

            if (!empty($statusFiltro) && $status !== $statusFiltro) continue;

            $details[] = [
                'id' => (int) $r['id'],
                'id_veiculo' => (int) $r['id_veiculo'],
                'placa' => $r['placa'] ?? '-',
                'veiculo' => trim(($r['marca'] ?? '') . ' ' . ($r['modelo'] ?? '')) ?: '-',
                'grupo' => $r['grupo_nome'] ?? '-',
                'tipo' => $r['nome'] ?? '-',
                'descricao' => $r['descricao'] ?? '',
                'valor' => round((float) ($r['valor'] ?? 0), 2),
                'vencimento' => $venci,
                'recorrencia' => $r['recorrencia'] ?? 'nenhuma',
                'pago' => $pago,
                'status' => $status,
                'status_label' => $this->labelStatusLicenciamento($status),
            ];

            $tot[$status]++;
            $tot['valor_total'] += (float) $r['valor'];
        }

        // Chart: distribuição por tipo
        $porTipo = [];
        foreach ($details as $d) {
            $tipo = $d['tipo'];
            $porTipo[$tipo] = ($porTipo[$tipo] ?? 0) + $d['valor'];
        }

        return [
            'totals' => [
                'total_encargos' => count($details),
                'vencidos' => $tot['vencido'],
                'prox_30' => $tot['prox_30'],
                'em_dia' => $tot['em_dia'],
                'valor_total' => round($tot['valor_total'], 2),
            ],
            'details' => $details,
            'chart' => [
                'labels' => array_keys($porTipo),
                'datasets' => [['label' => 'Valor (R$)', 'data' => array_map(fn($v) => round($v, 2), array_values($porTipo))]],
            ],
        ];
    }

    private function labelStatusLicenciamento(string $status): string
    {
        return match ($status) {
            'vencido' => 'Vencido',
            'prox_30' => 'Vence em 30 dias',
            'em_dia' => 'Em dia',
            default => $status,
        };
    }

    // =====================================================
    // 3.6 DISPONIBILIDADE
    // =====================================================

    /**
     * Disponibilidade — snapshot atual da frota por status (D/L/R/O/E/V/AV/UI/RO).
     *
     * Não usa filtro de período (estado atual). Filtros: filial, grupo.
     */
    public function disponibilidade(
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $grupoId = ''
    ): array {
        $query = $this->qb
            ->table('veiculos', 'v')
            ->select(['v.id', 'v.placa', 'v.marca', 'v.modelo', 'v.disponibilidade', 'v.odometro'])
            ->selectRaw('g.nome AS grupo_nome')
            ->leftJoin('grupos', 'g', 'v.id_grupo', '=', 'g.id');

        if (!empty($filialWhere)) {
            $query->whereRaw(str_replace('id_matriz_filial', 'v.id_matriz_filial', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $query->where('v.id_matriz_filial', '=', (int) $filialId);
        if (!empty($grupoId)) $query->where('v.id_grupo', '=', (int) $grupoId);

        $rows = $query->orderBy('v.placa', 'ASC')->get();

        $contagem = [];
        $details = [];
        foreach ($rows as $r) {
            $disp = $r['disponibilidade'] ?? '?';
            $contagem[$disp] = ($contagem[$disp] ?? 0) + 1;
            $details[] = [
                'id' => (int) $r['id'],
                'placa' => $r['placa'] ?? '-',
                'veiculo' => trim(($r['marca'] ?? '') . ' ' . ($r['modelo'] ?? '')) ?: '-',
                'grupo' => $r['grupo_nome'] ?? '-',
                'odometro' => (int) ($r['odometro'] ?? 0),
                'status' => $disp,
                'status_label' => $this->labelDisponibilidade($disp),
            ];
        }

        $totalFrota = count($details);
        $disponiveis = $contagem['D'] ?? 0;
        $locados = $contagem['L'] ?? 0;
        $reservados = $contagem['R'] ?? 0;
        $oficina = ($contagem['O'] ?? 0) + ($contagem['E'] ?? 0);
        $outros = $totalFrota - $disponiveis - $locados - $reservados - $oficina;

        // Chart: doughnut por status
        $chartLabels = [];
        $chartData = [];
        foreach (['D', 'L', 'R', 'O', 'E', 'V', 'AV', 'UI', 'RO'] as $s) {
            if (!empty($contagem[$s])) {
                $chartLabels[] = $this->labelDisponibilidade($s);
                $chartData[] = $contagem[$s];
            }
        }

        return [
            'totals' => [
                'total_frota' => $totalFrota,
                'disponiveis' => $disponiveis,
                'locados' => $locados,
                'reservados' => $reservados,
                'oficina' => $oficina,
                'outros' => max(0, $outros),
                'taxa_ocupacao_atual' => $this->pct($locados + $reservados, $totalFrota),
            ],
            'details' => $details,
            'chart' => [
                'labels' => $chartLabels,
                'datasets' => [['label' => 'Veículos', 'data' => $chartData]],
            ],
        ];
    }

    private function labelDisponibilidade(string $status): string
    {
        return match ($status) {
            'D' => 'Disponível',
            'L' => 'Locado',
            'R' => 'Reservado',
            'O' => 'Em manutenção',
            'E' => 'Em manutenção',
            'V' => 'Vendido',
            'AV' => 'Aguardando venda',
            'UI' => 'Inativo',
            'RO' => 'Reservado oficina',
            default => $status,
        };
    }

    // =====================================================
    // 3.8 DEPRECIAÇÃO DE FROTA
    // =====================================================

    /**
     * Depreciação linear: vida útil 5 anos, valor residual 20% do valor_compra.
     * Calcula valor contábil atual e depreciação no período selecionado.
     */
    public function depreciacao(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $grupoId = '',
        string $veiculoId = ''
    ): array {
        $vidaUtilAnos = 5;
        $residualPct = 0.20;

        $query = $this->qb
            ->table('veiculos', 'v')
            ->select(['v.id', 'v.placa', 'v.marca', 'v.modelo', 'v.ano', 'v.valor_compra', 'v.data_compra', 'v.valor_venda', 'v.data_venda'])
            ->selectRaw('g.nome AS grupo_nome')
            ->leftJoin('grupos', 'g', 'v.id_grupo', '=', 'g.id')
            ->where('v.disponibilidade', '!=', 'V')
            ->whereNotNull('v.valor_compra')
            ->where('v.valor_compra', '>', 0);

        if (!empty($filialWhere)) {
            $query->whereRaw(str_replace('id_matriz_filial', 'v.id_matriz_filial', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $query->where('v.id_matriz_filial', '=', (int) $filialId);
        if (!empty($grupoId)) $query->where('v.id_grupo', '=', (int) $grupoId);
        if (!empty($veiculoId)) $query->where('v.id', '=', (int) $veiculoId);

        $rows = $query->orderBy('v.placa', 'ASC')->get();

        $details = [];
        $totAquisicao = 0.0;
        $totDeprecAcumulada = 0.0;
        $totDeprecPeriodo = 0.0;
        $totValorContabil = 0.0;

        foreach ($rows as $r) {
            $valorCompra = (float) $r['valor_compra'];
            $valorResidual = $valorCompra * $residualPct;
            $base = max(0, $valorCompra - $valorResidual);
            $deprecAnual = $base / $vidaUtilAnos;
            $deprecMensal = $deprecAnual / 12;

            // Idade do veículo em anos
            $dataCompra = !empty($r['data_compra']) ? $r['data_compra'] : null;
            $idadeAnos = 0.0;
            if ($dataCompra) {
                $idadeAnos = max(0, (strtotime('today') - strtotime($dataCompra)) / 86400 / 365.25);
            }
            $idadeAnos = min($idadeAnos, (float) $vidaUtilAnos);
            $deprecAcumulada = round($deprecAnual * $idadeAnos, 2);
            $valorContabil = round(max($valorResidual, $valorCompra - $deprecAcumulada), 2);

            // Depreciação no período (proporcional aos meses do período sobre a vida útil restante)
            $diasPeriodo = $this->daysBetween($dataInicio, $dataFim);
            $deprecPeriodo = round($deprecAnual / 365.25 * $diasPeriodo, 2);
            // Limitar à depreciação restante
            $maxDeprec = max(0, $base - $deprecAcumulada);
            $deprecPeriodo = min($deprecPeriodo, $maxDeprec);

            $details[] = [
                'id' => (int) $r['id'],
                'placa' => $r['placa'] ?? '-',
                'veiculo' => trim(($r['marca'] ?? '') . ' ' . ($r['modelo'] ?? '')) ?: '-',
                'ano' => $r['ano'] ?? '',
                'grupo' => $r['grupo_nome'] ?? '-',
                'valor_compra' => round($valorCompra, 2),
                'data_compra' => $dataCompra,
                'idade_anos' => round($idadeAnos, 2),
                'depreciacao_mensal' => round($deprecMensal, 2),
                'depreciacao_acumulada' => $deprecAcumulada,
                'depreciacao_periodo' => $deprecPeriodo,
                'valor_contabil' => $valorContabil,
                'pct_depreciado' => $this->pct($deprecAcumulada, $valorCompra),
            ];

            $totAquisicao += $valorCompra;
            $totDeprecAcumulada += $deprecAcumulada;
            $totDeprecPeriodo += $deprecPeriodo;
            $totValorContabil += $valorContabil;
        }

        usort($details, fn($a, $b) => $b['depreciacao_acumulada'] <=> $a['depreciacao_acumulada']);

        return [
            'totals' => [
                'qtd_veiculos' => count($details),
                'valor_aquisicao_total' => round($totAquisicao, 2),
                'depreciacao_acumulada_total' => round($totDeprecAcumulada, 2),
                'depreciacao_periodo_total' => round($totDeprecPeriodo, 2),
                'valor_contabil_total' => round($totValorContabil, 2),
                'pct_depreciado_geral' => $this->pct($totDeprecAcumulada, $totAquisicao),
            ],
            'details' => $details,
            'chart' => [
                'labels' => array_map(fn($d) => $d['placa'], array_slice($details, 0, 10)),
                'datasets' => [
                    ['label' => 'Valor Aquisição', 'data' => array_map(fn($d) => $d['valor_compra'], array_slice($details, 0, 10))],
                    ['label' => 'Valor Contábil', 'data' => array_map(fn($d) => $d['valor_contabil'], array_slice($details, 0, 10))],
                ],
            ],
        ];
    }

    // =====================================================
    // 3.9 TEMPO MÉDIO PARADO
    // =====================================================

    /**
     * Tempo Médio Parado por veículo no período.
     * dias_parados = dias_periodo − dias_locados (locações + contratos sobrepostos).
     */
    public function tempoParado(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $grupoId = '',
        string $veiculoId = ''
    ): array {
        $diasPeriodo = $this->daysBetween($dataInicio, $dataFim) ?: 1;

        $diasLocadosPorVeiculo = $this->diasLocadosPorVeiculo(
            $dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, $grupoId, $veiculoId
        );

        $queryV = $this->qb
            ->table('veiculos', 'v')
            ->select(['v.id', 'v.placa', 'v.marca', 'v.modelo', 'v.disponibilidade'])
            ->selectRaw('g.nome AS grupo_nome')
            ->leftJoin('grupos', 'g', 'v.id_grupo', '=', 'g.id')
            ->where('v.disponibilidade', '!=', 'V');

        if (!empty($filialWhere)) {
            $queryV->whereRaw(str_replace('id_matriz_filial', 'v.id_matriz_filial', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryV->where('v.id_matriz_filial', '=', (int) $filialId);
        if (!empty($grupoId)) $queryV->where('v.id_grupo', '=', (int) $grupoId);
        if (!empty($veiculoId)) $queryV->where('v.id', '=', (int) $veiculoId);

        $rows = $queryV->get();

        $details = [];
        $totDiasParados = 0;
        $totDiasLocados = 0;

        foreach ($rows as $r) {
            $vid = (int) $r['id'];
            $diasLocados = (int) ($diasLocadosPorVeiculo[$vid] ?? 0);
            $diasParados = max(0, $diasPeriodo - $diasLocados);

            $details[] = [
                'id' => $vid,
                'placa' => $r['placa'] ?? '-',
                'veiculo' => trim(($r['marca'] ?? '') . ' ' . ($r['modelo'] ?? '')) ?: '-',
                'grupo' => $r['grupo_nome'] ?? '-',
                'disponibilidade' => $r['disponibilidade'] ?? '?',
                'dias_periodo' => $diasPeriodo,
                'dias_locados' => $diasLocados,
                'dias_parados' => $diasParados,
                'pct_ociosidade' => $this->pct($diasParados, $diasPeriodo),
            ];

            $totDiasParados += $diasParados;
            $totDiasLocados += $diasLocados;
        }

        usort($details, fn($a, $b) => $b['dias_parados'] <=> $a['dias_parados']);

        $totVeiculos = count($details);
        $totDiasDisponiveis = $totVeiculos * $diasPeriodo;

        return [
            'totals' => [
                'qtd_veiculos' => $totVeiculos,
                'dias_periodo' => $diasPeriodo,
                'dias_locados_total' => $totDiasLocados,
                'dias_parados_total' => $totDiasParados,
                'pct_ociosidade_geral' => $this->pct($totDiasParados, $totDiasDisponiveis),
                'media_dias_parado' => $totVeiculos > 0 ? round($totDiasParados / $totVeiculos, 1) : 0,
            ],
            'details' => $details,
            'chart' => [
                'labels' => array_map(fn($d) => $d['placa'], array_slice($details, 0, 10)),
                'datasets' => [
                    ['label' => 'Dias Parados', 'data' => array_map(fn($d) => $d['dias_parados'], array_slice($details, 0, 10))],
                    ['label' => 'Dias Locados', 'data' => array_map(fn($d) => $d['dias_locados'], array_slice($details, 0, 10))],
                ],
            ],
        ];
    }

    /**
     * Soma de dias locados por id_veiculo no período (locações + contratos sobrepostos).
     */
    private function diasLocadosPorVeiculo(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId,
        string $grupoId,
        string $veiculoId
    ): array {
        $resultado = [];

        $queryL = $this->qb
            ->table('locacoes_veiculos', 'lv')
            ->select(['lv.id_veiculo'])
            ->selectRaw("COALESCE(SUM(DATEDIFF(LEAST(COALESCE(lv.data_entrada, '{$dataFim}'), '{$dataFim}'), GREATEST(lv.data_saida, '{$dataInicio}')) + 1), 0) AS dias_locados")
            ->innerJoin('locacoes', 'l', 'lv.id_locacao', '=', 'l.id')
            ->where('l.status', '!=', 'C')
            ->whereRaw('lv.data_saida <= ?', [$dataFim])
            ->whereRaw("COALESCE(lv.data_entrada, '{$dataFim}') >= ?", [$dataInicio]);

        if (!empty($filialWhere)) {
            $queryL->whereRaw(str_replace('id_matriz_filial', 'l.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryL->where('l.id_matriz_filial_retirada', '=', (int) $filialId);
        if (!empty($grupoId)) $queryL->where('lv.id_grupo', '=', (int) $grupoId);
        if (!empty($veiculoId)) $queryL->where('lv.id_veiculo', '=', (int) $veiculoId);

        foreach ($queryL->groupBy('lv.id_veiculo')->get() as $r) {
            $vid = (int) $r['id_veiculo'];
            $resultado[$vid] = ($resultado[$vid] ?? 0) + (int) $r['dias_locados'];
        }

        $queryC = $this->qb
            ->table('contratos_veiculos', 'cv')
            ->select(['cv.id_veiculo'])
            ->selectRaw("COALESCE(SUM(DATEDIFF(LEAST(COALESCE(cv.data_entrada, '{$dataFim}'), '{$dataFim}'), GREATEST(cv.data_saida, '{$dataInicio}')) + 1), 0) AS dias_locados")
            ->innerJoin('contratos', 'c', 'cv.id_contrato', '=', 'c.id')
            ->where('c.status', '!=', 'C')
            ->whereRaw('cv.data_saida <= ?', [$dataFim])
            ->whereRaw("COALESCE(cv.data_entrada, '{$dataFim}') >= ?", [$dataInicio]);

        if (!empty($filialWhere)) {
            $queryC->whereRaw(str_replace('id_matriz_filial', 'c.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryC->where('c.id_matriz_filial_retirada', '=', (int) $filialId);
        if (!empty($grupoId)) $queryC->where('cv.id_grupo', '=', (int) $grupoId);
        if (!empty($veiculoId)) $queryC->where('cv.id_veiculo', '=', (int) $veiculoId);

        foreach ($queryC->groupBy('cv.id_veiculo')->get() as $r) {
            $vid = (int) $r['id_veiculo'];
            $resultado[$vid] = ($resultado[$vid] ?? 0) + (int) $r['dias_locados'];
        }

        return $resultado;
    }

    // =====================================================
    // 3.10 QUILOMETRAGEM MÉDIA
    // =====================================================

    /**
     * Quilometragem rodada por veículo no período (soma de odometro_entrada − odometro_saida
     * em locacoes_veiculos + contratos_veiculos).
     */
    public function quilometragemMedia(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $grupoId = '',
        string $veiculoId = ''
    ): array {
        $kmPorVeiculo = [];
        $qtdLocacoesPorVeiculo = [];

        // Locações
        $queryL = $this->qb
            ->table('locacoes_veiculos', 'lv')
            ->select(['lv.id_veiculo'])
            ->selectRaw('COALESCE(SUM(GREATEST(0, lv.odometro_entrada - lv.odometro_saida)), 0) AS km, COUNT(*) AS qtd')
            ->innerJoin('locacoes', 'l', 'lv.id_locacao', '=', 'l.id')
            ->whereIn('l.status', ['A', 'F'])
            ->whereNotNull('lv.odometro_entrada')
            ->whereBetween('lv.data_saida', $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59');

        if (!empty($filialWhere)) {
            $queryL->whereRaw(str_replace('id_matriz_filial', 'l.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryL->where('l.id_matriz_filial_retirada', '=', (int) $filialId);
        if (!empty($grupoId)) $queryL->where('lv.id_grupo', '=', (int) $grupoId);
        if (!empty($veiculoId)) $queryL->where('lv.id_veiculo', '=', (int) $veiculoId);

        foreach ($queryL->groupBy('lv.id_veiculo')->get() as $r) {
            $vid = (int) $r['id_veiculo'];
            $kmPorVeiculo[$vid] = ($kmPorVeiculo[$vid] ?? 0) + (int) $r['km'];
            $qtdLocacoesPorVeiculo[$vid] = ($qtdLocacoesPorVeiculo[$vid] ?? 0) + (int) $r['qtd'];
        }

        // Contratos
        $queryC = $this->qb
            ->table('contratos_veiculos', 'cv')
            ->select(['cv.id_veiculo'])
            ->selectRaw('COALESCE(SUM(GREATEST(0, cv.odometro_entrada - cv.odometro_saida)), 0) AS km, COUNT(*) AS qtd')
            ->innerJoin('contratos', 'c', 'cv.id_contrato', '=', 'c.id')
            ->whereIn('c.status', ['A', 'F'])
            ->whereNotNull('cv.odometro_entrada')
            ->whereBetween('cv.data_saida', $dataInicio . ' 00:00:00', $dataFim . ' 23:59:59');

        if (!empty($filialWhere)) {
            $queryC->whereRaw(str_replace('id_matriz_filial', 'c.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryC->where('c.id_matriz_filial_retirada', '=', (int) $filialId);
        if (!empty($grupoId)) $queryC->where('cv.id_grupo', '=', (int) $grupoId);
        if (!empty($veiculoId)) $queryC->where('cv.id_veiculo', '=', (int) $veiculoId);

        foreach ($queryC->groupBy('cv.id_veiculo')->get() as $r) {
            $vid = (int) $r['id_veiculo'];
            $kmPorVeiculo[$vid] = ($kmPorVeiculo[$vid] ?? 0) + (int) $r['km'];
            $qtdLocacoesPorVeiculo[$vid] = ($qtdLocacoesPorVeiculo[$vid] ?? 0) + (int) $r['qtd'];
        }

        $idsVeiculos = array_keys($kmPorVeiculo);
        $veiculosInfo = $this->buscarVeiculosInfo($idsVeiculos);
        $diasPeriodo = $this->daysBetween($dataInicio, $dataFim) ?: 1;

        $details = [];
        $totKm = 0;
        $totLocacoes = 0;

        foreach ($veiculosInfo as $vid => $vinfo) {
            $km = (int) ($kmPorVeiculo[$vid] ?? 0);
            $qtd = (int) ($qtdLocacoesPorVeiculo[$vid] ?? 0);
            $kmDia = $diasPeriodo > 0 ? round($km / $diasPeriodo, 1) : 0;
            $kmLocacao = $qtd > 0 ? round($km / $qtd, 0) : 0;

            $details[] = [
                'id' => (int) $vid,
                'placa' => $vinfo['placa'] ?? '-',
                'veiculo' => trim(($vinfo['marca'] ?? '') . ' ' . ($vinfo['modelo'] ?? '')) ?: '-',
                'grupo' => $vinfo['grupo_nome'] ?? '-',
                'km_total' => $km,
                'qtd_locacoes' => $qtd,
                'km_dia' => $kmDia,
                'km_locacao' => $kmLocacao,
            ];

            $totKm += $km;
            $totLocacoes += $qtd;
        }

        usort($details, fn($a, $b) => $b['km_total'] <=> $a['km_total']);

        return [
            'totals' => [
                'qtd_veiculos' => count($details),
                'km_total' => $totKm,
                'qtd_locacoes' => $totLocacoes,
                'media_km_veiculo' => count($details) > 0 ? round($totKm / count($details), 0) : 0,
                'media_km_locacao' => $totLocacoes > 0 ? round($totKm / $totLocacoes, 0) : 0,
                'media_km_dia' => round($totKm / $diasPeriodo, 1),
            ],
            'details' => $details,
            'chart' => [
                'labels' => array_map(fn($d) => $d['placa'], array_slice($details, 0, 10)),
                'datasets' => [['label' => 'Km Rodado', 'data' => array_map(fn($d) => $d['km_total'], array_slice($details, 0, 10))]],
            ],
        ];
    }

    // =====================================================
    // 3.11 CUSTO TOTAL DE PROPRIEDADE (TCO)
    // =====================================================

    /**
     * TCO — soma depreciação no período + manutenções + multas + encargos por veículo.
     * Mostra TCO total, TCO/mês, TCO/km.
     */
    public function tco(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = '',
        string $grupoId = '',
        string $veiculoId = ''
    ): array {
        // Reaproveitar lógica de despesas
        $manut = $this->somaManutencoesPorVeiculo($dataInicio, $dataFim, $filialId, $veiculoId);
        $mults = $this->somaMultasPorVeiculo($dataInicio, $dataFim, $filialId, $veiculoId);
        $encs = $this->somaEncargosPorVeiculo($dataInicio, $dataFim, $veiculoId);

        // Depreciação no período por veículo
        $depResult = $this->depreciacao($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, $grupoId, $veiculoId);
        $depPorVeiculo = [];
        foreach ($depResult['details'] as $d) {
            $depPorVeiculo[$d['id']] = $d['depreciacao_periodo'];
        }

        // KM rodado por veículo
        $kmResult = $this->quilometragemMedia($dataInicio, $dataFim, $filialWhere, $filialParams, $filialId, $grupoId, $veiculoId);
        $kmPorVeiculo = [];
        foreach ($kmResult['details'] as $d) {
            $kmPorVeiculo[$d['id']] = $d['km_total'];
        }

        $idsVeiculos = array_unique(array_merge(
            array_keys($depPorVeiculo),
            array_keys($manut),
            array_keys($mults),
            array_keys($encs),
            array_keys($kmPorVeiculo)
        ));

        if (!empty($grupoId) && !empty($idsVeiculos)) {
            $idsVeiculos = $this->filtrarVeiculosPorGrupo($idsVeiculos, (int) $grupoId);
        }

        $veiculosInfo = $this->buscarVeiculosInfo($idsVeiculos);
        $diasPeriodo = $this->daysBetween($dataInicio, $dataFim) ?: 1;
        $mesesPeriodo = $diasPeriodo / 30;

        $details = [];
        $tot = ['depreciacao' => 0.0, 'manutencao' => 0.0, 'multas' => 0.0, 'encargos' => 0.0];

        foreach ($veiculosInfo as $vid => $vinfo) {
            $dep = (float) ($depPorVeiculo[$vid] ?? 0);
            $mn = (float) ($manut[$vid] ?? 0);
            $mt = (float) ($mults[$vid] ?? 0);
            $en = (float) ($encs[$vid] ?? 0);
            $tco = $dep + $mn + $mt + $en;
            $km = (int) ($kmPorVeiculo[$vid] ?? 0);
            $tcoMes = $mesesPeriodo > 0 ? round($tco / $mesesPeriodo, 2) : 0;
            $tcoKm = $km > 0 ? round($tco / $km, 4) : 0;

            $details[] = [
                'id' => (int) $vid,
                'placa' => $vinfo['placa'] ?? '-',
                'veiculo' => trim(($vinfo['marca'] ?? '') . ' ' . ($vinfo['modelo'] ?? '')) ?: '-',
                'grupo' => $vinfo['grupo_nome'] ?? '-',
                'depreciacao' => round($dep, 2),
                'manutencao' => round($mn, 2),
                'multas' => round($mt, 2),
                'encargos' => round($en, 2),
                'tco_total' => round($tco, 2),
                'tco_mes' => $tcoMes,
                'km_rodado' => $km,
                'tco_km' => $tcoKm,
            ];

            $tot['depreciacao'] += $dep;
            $tot['manutencao'] += $mn;
            $tot['multas'] += $mt;
            $tot['encargos'] += $en;
        }

        usort($details, fn($a, $b) => $b['tco_total'] <=> $a['tco_total']);

        $tcoTotal = $tot['depreciacao'] + $tot['manutencao'] + $tot['multas'] + $tot['encargos'];

        return [
            'totals' => [
                'qtd_veiculos' => count($details),
                'tco_total' => round($tcoTotal, 2),
                'depreciacao_total' => round($tot['depreciacao'], 2),
                'manutencao_total' => round($tot['manutencao'], 2),
                'multas_total' => round($tot['multas'], 2),
                'encargos_total' => round($tot['encargos'], 2),
                'tco_medio_veiculo' => count($details) > 0 ? round($tcoTotal / count($details), 2) : 0,
            ],
            'details' => $details,
            'chart' => [
                'labels' => ['Depreciação', 'Manutenção', 'Multas', 'Encargos'],
                'datasets' => [[
                    'label' => 'TCO Componentes (R$)',
                    'data' => [
                        round($tot['depreciacao'], 2),
                        round($tot['manutencao'], 2),
                        round($tot['multas'], 2),
                        round($tot['encargos'], 2),
                    ],
                ]],
            ],
        ];
    }

    // =====================================================
    // 3.7 TAXA DE OCUPAÇÃO POR GRUPO
    // =====================================================

    /**
     * Taxa de Ocupação por Grupo — agrega ocupação e receita por id_grupo.
     *
     * Reaproveita lógica de KpiReport::taxaOcupacao mas com agregação por grupo.
     */
    public function ocupacaoPorGrupo(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        $diasPeriodo = $this->daysBetween($dataInicio, $dataFim) ?: 1;

        // Total de veículos por grupo
        $queryV = $this->qb
            ->table('veiculos', 'v')
            ->select(['v.id_grupo'])
            ->selectRaw('COUNT(*) AS total_veiculos, g.nome AS grupo_nome')
            ->leftJoin('grupos', 'g', 'v.id_grupo', '=', 'g.id')
            ->where('v.disponibilidade', '!=', 'V');

        if (!empty($filialWhere)) {
            $queryV->whereRaw(str_replace('id_matriz_filial', 'v.id_matriz_filial', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryV->where('v.id_matriz_filial', '=', (int) $filialId);

        $rowsV = $queryV->groupBy('v.id_grupo')->get();

        $grupos = [];
        foreach ($rowsV as $r) {
            $gid = (int) ($r['id_grupo'] ?? 0);
            $grupos[$gid] = [
                'id_grupo' => $gid,
                'grupo' => $r['grupo_nome'] ?? 'Sem grupo',
                'total_veiculos' => (int) $r['total_veiculos'],
                'dias_locados' => 0,
                'receita' => 0.0,
            ];
        }

        // Dias locados por grupo (locações)
        $queryDL = $this->qb
            ->table('locacoes_veiculos', 'lv')
            ->select(['lv.id_grupo'])
            ->selectRaw("COALESCE(SUM(DATEDIFF(LEAST(COALESCE(lv.data_entrada, '{$dataFim}'), '{$dataFim}'), GREATEST(lv.data_saida, '{$dataInicio}')) + 1), 0) AS dias_locados")
            ->innerJoin('locacoes', 'l', 'lv.id_locacao', '=', 'l.id')
            ->where('l.status', '!=', 'C')
            ->whereRaw('lv.data_saida <= ?', [$dataFim])
            ->whereRaw("COALESCE(lv.data_entrada, '{$dataFim}') >= ?", [$dataInicio]);

        if (!empty($filialWhere)) {
            $queryDL->whereRaw(str_replace('id_matriz_filial', 'l.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryDL->where('l.id_matriz_filial_retirada', '=', (int) $filialId);

        foreach ($queryDL->groupBy('lv.id_grupo')->get() as $r) {
            $gid = (int) ($r['id_grupo'] ?? 0);
            if (isset($grupos[$gid])) $grupos[$gid]['dias_locados'] += (int) $r['dias_locados'];
        }

        // Dias locados por grupo (contratos)
        $queryDC = $this->qb
            ->table('contratos_veiculos', 'cv')
            ->select(['cv.id_grupo'])
            ->selectRaw("COALESCE(SUM(DATEDIFF(LEAST(COALESCE(cv.data_entrada, '{$dataFim}'), '{$dataFim}'), GREATEST(cv.data_saida, '{$dataInicio}')) + 1), 0) AS dias_locados")
            ->innerJoin('contratos', 'c', 'cv.id_contrato', '=', 'c.id')
            ->where('c.status', '!=', 'C')
            ->whereRaw('cv.data_saida <= ?', [$dataFim])
            ->whereRaw("COALESCE(cv.data_entrada, '{$dataFim}') >= ?", [$dataInicio]);

        if (!empty($filialWhere)) {
            $queryDC->whereRaw(str_replace('id_matriz_filial', 'c.id_matriz_filial_retirada', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryDC->where('c.id_matriz_filial_retirada', '=', (int) $filialId);

        foreach ($queryDC->groupBy('cv.id_grupo')->get() as $r) {
            $gid = (int) ($r['id_grupo'] ?? 0);
            if (isset($grupos[$gid])) $grupos[$gid]['dias_locados'] += (int) $r['dias_locados'];
        }

        // Receita por grupo (via financeiro -> veiculos.id_grupo)
        $queryR = $this->qb
            ->table('financeiro', 'f')
            ->select(['v.id_grupo'])
            ->selectRaw('COALESCE(SUM(f.valor_total), 0) AS receita')
            ->innerJoin('veiculos', 'v', 'f.id_veiculo', '=', 'v.id')
            ->where('f.tipo', '=', 'R')
            ->whereNotNull('f.id_veiculo')
            ->whereBetween('f.data_venci', $dataInicio, $dataFim);

        if (!empty($filialWhere)) {
            $queryR->whereRaw(str_replace('id_matriz_filial', 'f.id_matriz_filial', $filialWhere), $filialParams);
        }
        if (!empty($filialId)) $queryR->where('f.id_matriz_filial', '=', (int) $filialId);

        foreach ($queryR->groupBy('v.id_grupo')->get() as $r) {
            $gid = (int) ($r['id_grupo'] ?? 0);
            if (isset($grupos[$gid])) $grupos[$gid]['receita'] += (float) $r['receita'];
        }

        // Montar details
        $details = [];
        $totVeiculos = 0;
        $totDiasDisponiveis = 0;
        $totDiasLocados = 0;
        $totReceita = 0.0;

        foreach ($grupos as $g) {
            $diasDispGrupo = $g['total_veiculos'] * $diasPeriodo;
            $taxa = $this->pct($g['dias_locados'], $diasDispGrupo);
            $revpar = $this->safeDivide($g['receita'], $diasDispGrupo);

            $details[] = [
                'id_grupo' => $g['id_grupo'],
                'grupo' => $g['grupo'],
                'total_veiculos' => $g['total_veiculos'],
                'dias_disponiveis' => $diasDispGrupo,
                'dias_locados' => $g['dias_locados'],
                'taxa_ocupacao' => $taxa,
                'receita' => round($g['receita'], 2),
                'revpar' => round($revpar, 2),
            ];

            $totVeiculos += $g['total_veiculos'];
            $totDiasDisponiveis += $diasDispGrupo;
            $totDiasLocados += $g['dias_locados'];
            $totReceita += $g['receita'];
        }

        usort($details, fn($a, $b) => $b['taxa_ocupacao'] <=> $a['taxa_ocupacao']);

        return [
            'totals' => [
                'qtd_grupos' => count($details),
                'total_veiculos' => $totVeiculos,
                'dias_locados' => $totDiasLocados,
                'taxa_geral' => $this->pct($totDiasLocados, $totDiasDisponiveis),
                'receita_total' => round($totReceita, 2),
                'revpar_geral' => round($this->safeDivide($totReceita, $totDiasDisponiveis), 2),
            ],
            'details' => $details,
            'chart' => [
                'labels' => array_map(fn($d) => $d['grupo'], $details),
                'datasets' => [
                    ['label' => 'Taxa Ocupação (%)', 'data' => array_map(fn($d) => $d['taxa_ocupacao'], $details)],
                ],
            ],
        ];
    }
}
