<?php

namespace App\Models\Relatorios;

/**
 * Relatorios da categoria CLIENTES (grupo 4).
 *
 * 4.1 Por Cliente            -> porCliente()
 * 4.2 Aniversariantes        -> aniversariantes()
 * 4.3 CNH Vencidas           -> cnhVencidas()
 * 4.4 Top Clientes           -> topClientes()
 * 4.5 Frequencia             -> frequencia()
 * 4.6 Tempo de Relacionamento-> tempoRelacionamento()
 * 4.7 Ocorrencias            -> ocorrencias()
 * 4.8 Inativos               -> inativos()
 */
class ClientesReport extends BaseReportModel
{
    /**
     * Aplica filtro de filial.
     * Para tabela locacoes usa `id_matriz_filial_retirada`; para clientes usa `id_matriz_filial`.
     */
    private function applyFilial($query, string $where, array $params, string $id, string $coluna, string $alias): void
    {
        if (!empty($id)) {
            $query->whereRaw("{$alias}.{$coluna} = ?", [(int) $id]);
        } elseif (!empty($where)) {
            $query->whereRaw($where, $params);
        }
    }

    // =====================================================
    // 4.1 — POR CLIENTE
    // =====================================================
    public function porCliente(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        $queryLocacoes = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw("
                l.id_cliente,
                COALESCE(NULLIF(l.cliente_nome, ''), cl.nome_rsocial, '-') AS cliente_nome,
                cl.cpf_cnpj,
                cl.tipo AS cliente_tipo,
                COUNT(*) AS total_locacoes,
                MIN(l.data_saida) AS primeira_locacao,
                MAX(l.data_saida) AS ultima_locacao,
                COALESCE(SUM(l.total_pagar), 0) AS faturamento_total,
                COALESCE(SUM(l.dias), 0) AS dias_total
            ")
            ->leftJoinRaw('clientes', 'cl', 'cl.id = l.id_cliente AND cl.chave = l.chave')
            ->whereRaw('l.data_saida BETWEEN ? AND ?', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
            ->groupBy(['l.id_cliente', 'l.cliente_nome', 'cl.nome_rsocial', 'cl.cpf_cnpj', 'cl.tipo']);

        $this->applyFilial($queryLocacoes, $filialWhere, $filialParams, $filialId, 'id_matriz_filial_retirada', 'l');
        $rowsLocacoes = $queryLocacoes->get();

        $queryContratos = $this->qb
            ->table('contratos', 'c')
            ->selectRaw("
                c.id_cliente,
                COALESCE(cl.nome_rsocial, '-') AS cliente_nome,
                cl.cpf_cnpj,
                cl.tipo AS cliente_tipo,
                COUNT(*) AS total_locacoes,
                MIN(c.data_ini) AS primeira_locacao,
                MAX(c.data_ini) AS ultima_locacao,
                COALESCE(SUM(c.total_pagar), 0) AS faturamento_total,
                COALESCE(SUM(GREATEST(DATEDIFF(COALESCE(c.data_fim, c.data_ini), c.data_ini) + 1, 1)), 0) AS dias_total
            ")
            ->leftJoinRaw('clientes', 'cl', 'cl.id = c.id_cliente AND cl.chave = c.chave')
            ->whereRaw('c.data_ini BETWEEN ? AND ?', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
            ->groupBy(['c.id_cliente', 'cl.nome_rsocial', 'cl.cpf_cnpj', 'cl.tipo']);

        $this->applyFilial($queryContratos, $filialWhere, $filialParams, $filialId, 'id_matriz_filial_retirada', 'c');

        $agrupado = [];
        foreach (array_merge($rowsLocacoes, $queryContratos->get()) as $r) {
            $idCliente = !empty($r['id_cliente']) ? (int) $r['id_cliente'] : 0;
            $nomeCliente = trim((string) ($r['cliente_nome'] ?? ''));
            $key = $idCliente > 0 ? 'id:' . $idCliente : 'nome:' . strtolower($nomeCliente);

            if (!isset($agrupado[$key])) {
                $agrupado[$key] = [
                    'id_cliente' => $idCliente > 0 ? $idCliente : null,
                    'cliente' => $nomeCliente !== '' ? $nomeCliente : '-',
                    'cpf_cnpj' => $r['cpf_cnpj'] ?? '',
                    'tipo' => $r['cliente_tipo'] ?? '',
                    'total_locacoes' => 0,
                    'primeira_locacao' => null,
                    'ultima_locacao' => null,
                    'faturamento_total' => 0.0,
                    'dias_total' => 0.0,
                ];
            }

            $agrupado[$key]['total_locacoes'] += (int) $r['total_locacoes'];
            $agrupado[$key]['faturamento_total'] += (float) $r['faturamento_total'];
            $agrupado[$key]['dias_total'] += (float) $r['dias_total'];

            if (!empty($r['primeira_locacao']) && (
                empty($agrupado[$key]['primeira_locacao']) || $r['primeira_locacao'] < $agrupado[$key]['primeira_locacao']
            )) {
                $agrupado[$key]['primeira_locacao'] = $r['primeira_locacao'];
            }

            if (!empty($r['ultima_locacao']) && (
                empty($agrupado[$key]['ultima_locacao']) || $r['ultima_locacao'] > $agrupado[$key]['ultima_locacao']
            )) {
                $agrupado[$key]['ultima_locacao'] = $r['ultima_locacao'];
            }
        }

        $details = array_map(function ($r) {
            $qtd = (int) $r['total_locacoes'];
            $fat = (float) $r['faturamento_total'];
            $dias = (float) $r['dias_total'];
            return [
                'id_cliente' => $r['id_cliente'],
                'cliente' => $r['cliente'],
                'cpf_cnpj' => $r['cpf_cnpj'],
                'tipo' => $r['tipo'],
                'total_locacoes' => $qtd,
                'primeira_locacao' => $r['primeira_locacao'],
                'ultima_locacao' => $r['ultima_locacao'],
                'faturamento_total' => $fat,
                'ticket_medio' => $this->safeDivide($fat, $qtd, 2),
                'dias_medio' => $this->safeDivide($dias, $qtd, 1),
            ];
        }, array_values($agrupado));

        usort($details, fn($a, $b) => $b['faturamento_total'] <=> $a['faturamento_total']);

        $totals = [
            'qtd_clientes' => count($details),
            'total_locacoes' => (int) array_sum(array_column($details, 'total_locacoes')),
            'faturamento_total' => (float) array_sum(array_column($details, 'faturamento_total')),
            'ticket_medio' => $this->safeDivide(
                (float) array_sum(array_column($details, 'faturamento_total')),
                (int) array_sum(array_column($details, 'total_locacoes')),
                2
            ),
        ];

        // Top 10 chart
        $top = array_slice($details, 0, 10);
        return [
            'totals' => $totals,
            'details' => ['lista' => $details],
            'chart' => [
                'labels' => array_map(fn($r) => $r['cliente'], $top),
                'data' => array_map(fn($r) => $r['faturamento_total'], $top),
            ],
        ];
    }

    // =====================================================
    // 4.2 — ANIVERSARIANTES
    // =====================================================
    public function aniversariantes(
        int $mes,
        ?int $dia,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        $query = $this->qb
            ->table('clientes', 'c')
            ->selectRaw("
                c.id, c.nome_rsocial, c.cpf_cnpj, c.nascimento,
                TIMESTAMPDIFF(YEAR, c.nascimento, CURDATE()) AS idade
            ")
            ->whereNotNull('c.nascimento')
            ->whereRaw('MONTH(c.nascimento) = ?', [$mes]);

        if ($dia !== null) {
            $query->whereRaw('DAY(c.nascimento) = ?', [$dia]);
        }

        $this->applyFilial($query, $filialWhere, $filialParams, $filialId, 'id_matriz_filial', 'c');

        $query->orderByRaw('DAY(c.nascimento) ASC, c.nome_rsocial ASC');
        $rows = $query->get();

        $details = [];
        foreach ($rows as $r) {
            // Buscar contatos
            $email = $this->qb->table('contatos_emails', 'e')
                ->select(['e.email'])
                ->where('e.entidade_tipo', '=', 'cliente')
                ->where('e.entidade_id', '=', (int) $r['id'])
                ->where('e.principal', '=', 'S')
                ->first();
            $tel = $this->qb->table('contatos_telefones', 't')
                ->select(['t.telefone'])
                ->where('t.entidade_tipo', '=', 'cliente')
                ->where('t.entidade_id', '=', (int) $r['id'])
                ->where('t.principal', '=', 'S')
                ->first();

            // Última locação e total
            $estat = $this->qb->table('locacoes', 'l')
                ->selectRaw('MAX(l.data_saida) AS ultima, COUNT(*) AS total')
                ->where('l.id_cliente', '=', (int) $r['id'])
                ->first();

            $details[] = [
                'id_cliente' => (int) $r['id'],
                'nome' => $r['nome_rsocial'] ?: '-',
                'cpf_cnpj' => $r['cpf_cnpj'] ?? '',
                'nascimento' => $r['nascimento'],
                'idade' => (int) $r['idade'],
                'email' => $email['email'] ?? '',
                'telefone' => $tel['telefone'] ?? '',
                'ultima_locacao' => $estat['ultima'] ?? null,
                'total_locacoes' => (int) ($estat['total'] ?? 0),
            ];
        }

        $totals = [
            'qtd_aniversariantes' => count($details),
            'idade_media' => $this->safeDivide((int) array_sum(array_column($details, 'idade')), count($details), 1),
        ];

        // Chart: distribuicao por dia do mes
        $porDia = [];
        foreach ($details as $d) {
            if (!$d['nascimento']) continue;
            $diaMes = (int) substr($d['nascimento'], 8, 2);
            $porDia[$diaMes] = ($porDia[$diaMes] ?? 0) + 1;
        }
        ksort($porDia);

        return [
            'totals' => $totals,
            'details' => ['lista' => $details],
            'chart' => [
                'labels' => array_map(fn($d) => 'Dia ' . $d, array_keys($porDia)),
                'data' => array_values($porDia),
            ],
        ];
    }

    // =====================================================
    // 4.3 — CNH VENCIDAS
    // =====================================================
    public function cnhVencidas(
        string $statusFiltro,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        $query = $this->qb
            ->table('clientes', 'c')
            ->selectRaw("
                c.id, c.nome_rsocial, c.cpf_cnpj, c.cnh_numero, c.cnh_validade,
                DATEDIFF(c.cnh_validade, CURDATE()) AS dias_para_vencer
            ")
            ->whereNotNull('c.cnh_validade')
            ->whereRaw('c.cnh_validade <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)');

        if ($statusFiltro === 'vencida') {
            $query->whereRaw('c.cnh_validade < CURDATE()');
        } elseif ($statusFiltro === '30') {
            $query->whereRaw('c.cnh_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)');
        } elseif ($statusFiltro === '60') {
            $query->whereRaw('c.cnh_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)');
        } elseif ($statusFiltro === '90') {
            $query->whereRaw('c.cnh_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)');
        }

        $this->applyFilial($query, $filialWhere, $filialParams, $filialId, 'id_matriz_filial', 'c');

        $query->orderByRaw('c.cnh_validade ASC');
        $rows = $query->get();

        $details = [];
        $countVencida = 0; $count30 = 0; $count60 = 0; $count90 = 0;
        foreach ($rows as $r) {
            $dias = (int) ($r['dias_para_vencer'] ?? 0);
            // Status semantico
            if ($dias < 0) { $statusKey = 'vencida'; $countVencida++; }
            elseif ($dias <= 30) { $statusKey = '30'; $count30++; }
            elseif ($dias <= 60) { $statusKey = '60'; $count60++; }
            else { $statusKey = '90'; $count90++; }

            // Locação ativa?
            $ativa = $this->qb->table('locacoes', 'l')
                ->selectRaw('COUNT(*) AS qtd')
                ->where('l.id_cliente', '=', (int) $r['id'])
                ->whereRaw("l.status IN ('A','R')")
                ->first();

            // Contato
            $tel = $this->qb->table('contatos_telefones', 't')
                ->select(['t.telefone'])
                ->where('t.entidade_tipo', '=', 'cliente')
                ->where('t.entidade_id', '=', (int) $r['id'])
                ->where('t.principal', '=', 'S')
                ->first();
            $email = $this->qb->table('contatos_emails', 'e')
                ->select(['e.email'])
                ->where('e.entidade_tipo', '=', 'cliente')
                ->where('e.entidade_id', '=', (int) $r['id'])
                ->where('e.principal', '=', 'S')
                ->first();

            $details[] = [
                'id_cliente' => (int) $r['id'],
                'cliente' => $r['nome_rsocial'] ?: '-',
                'cpf_cnpj' => $r['cpf_cnpj'] ?? '',
                'cnh_numero' => $r['cnh_numero'] ?? '',
                'cnh_validade' => $r['cnh_validade'],
                'dias_para_vencer' => $dias,
                'status' => $statusKey,
                'tem_locacao_ativa' => (int) ($ativa['qtd'] ?? 0) > 0,
                'telefone' => $tel['telefone'] ?? '',
                'email' => $email['email'] ?? '',
            ];
        }

        $totals = [
            'total' => count($details),
            'vencidas' => $countVencida,
            'vence_30' => $count30,
            'vence_60' => $count60,
            'vence_90' => $count90,
        ];

        return [
            'totals' => $totals,
            'details' => ['lista' => $details],
            'chart' => [
                'labels' => [
                    \t('modules.relatorios.clientes.cnh_vencidas.faixa_vencidas'),
                    '1-30 ' . \t('modules.relatorios.clientes.cnh_vencidas.dias'),
                    '31-60 ' . \t('modules.relatorios.clientes.cnh_vencidas.dias'),
                    '61-90 ' . \t('modules.relatorios.clientes.cnh_vencidas.dias'),
                ],
                'data' => [$countVencida, $count30, $count60, $count90],
            ],
        ];
    }

    // =====================================================
    // 4.4 — TOP CLIENTES
    // =====================================================
    public function topClientes(
        string $dataInicio,
        string $dataFim,
        string $criterio,
        int $limite,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        $criterio = in_array($criterio, ['valor', 'locacoes'], true) ? $criterio : 'valor';
        $orderCol = $criterio === 'locacoes' ? 'total_locacoes' : 'faturamento_total';

        $query = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw("
                l.id_cliente,
                l.cliente_nome,
                cl.cpf_cnpj, cl.tipo AS cliente_tipo,
                COUNT(*) AS total_locacoes,
                COALESCE(SUM(l.total_pagar), 0) AS faturamento_total,
                MIN(l.data_saida) AS desde
            ")
            ->leftJoin('clientes', 'cl', 'l.id_cliente', '=', 'cl.id')
            ->whereRaw('l.data_saida BETWEEN ? AND ?', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
            ->whereNotNull('l.id_cliente')
            ->groupBy(['l.id_cliente', 'l.cliente_nome', 'cl.cpf_cnpj', 'cl.tipo'])
            ->orderByRaw("{$orderCol} DESC");

        $this->applyFilial($query, $filialWhere, $filialParams, $filialId, 'id_matriz_filial_retirada', 'l');

        $rows = $query->get();
        $rows = array_slice($rows, 0, max(1, $limite));

        $details = [];
        $pos = 1;
        foreach ($rows as $r) {
            $qtd = (int) $r['total_locacoes'];
            $fat = (float) $r['faturamento_total'];
            $details[] = [
                'posicao' => $pos++,
                'id_cliente' => (int) $r['id_cliente'],
                'cliente' => $r['cliente_nome'] ?: '-',
                'cpf_cnpj' => $r['cpf_cnpj'] ?? '',
                'tipo' => $r['cliente_tipo'] ?? '',
                'total_locacoes' => $qtd,
                'faturamento_total' => $fat,
                'ticket_medio' => $this->safeDivide($fat, $qtd, 2),
                'desde' => $r['desde'],
            ];
        }

        $totals = [
            'qtd_clientes' => count($details),
            'faturamento_total' => (float) array_sum(array_column($details, 'faturamento_total')),
            'total_locacoes' => (int) array_sum(array_column($details, 'total_locacoes')),
            'criterio' => $criterio,
        ];

        return [
            'totals' => $totals,
            'details' => ['lista' => $details, 'criterio' => $criterio],
            'chart' => [
                'labels' => array_map(fn($r) => $r['cliente'], $details),
                'data' => array_map(
                    fn($r) => $criterio === 'locacoes' ? $r['total_locacoes'] : $r['faturamento_total'],
                    $details
                ),
            ],
        ];
    }

    // =====================================================
    // 4.5 — FREQUENCIA
    // =====================================================
    public function frequencia(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        $query = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw("
                l.id_cliente, l.cliente_nome,
                COUNT(*) AS total_locacoes,
                MIN(l.data_saida) AS primeira,
                MAX(l.data_saida) AS ultima,
                DATEDIFF(MAX(l.data_saida), MIN(l.data_saida)) AS dias_relacionamento
            ")
            ->whereRaw('l.data_saida BETWEEN ? AND ?', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
            ->whereNotNull('l.id_cliente')
            ->groupBy(['l.id_cliente', 'l.cliente_nome'])
            ->orderByRaw('total_locacoes DESC');

        $this->applyFilial($query, $filialWhere, $filialParams, $filialId, 'id_matriz_filial_retirada', 'l');

        $rows = $query->get();

        $contagem = ['frequente' => 0, 'regular' => 0, 'esporadico' => 0, 'infrequente' => 0, 'unica' => 0];

        $details = array_map(function ($r) use (&$contagem) {
            $total = (int) $r['total_locacoes'];
            $diasRel = (int) ($r['dias_relacionamento'] ?? 0);
            $intervaloMedio = $total > 1 ? round($diasRel / ($total - 1), 1) : null;

            // Classificacao
            if ($intervaloMedio === null) {
                $classificacao = 'unica'; $contagem['unica']++;
            } elseif ($intervaloMedio <= 30) {
                $classificacao = 'frequente'; $contagem['frequente']++;
            } elseif ($intervaloMedio <= 90) {
                $classificacao = 'regular'; $contagem['regular']++;
            } elseif ($intervaloMedio <= 180) {
                $classificacao = 'esporadico'; $contagem['esporadico']++;
            } else {
                $classificacao = 'infrequente'; $contagem['infrequente']++;
            }

            return [
                'id_cliente' => (int) $r['id_cliente'],
                'cliente' => $r['cliente_nome'] ?: '-',
                'total_locacoes' => $total,
                'primeira' => $r['primeira'],
                'ultima' => $r['ultima'],
                'intervalo_medio' => $intervaloMedio,
                'classificacao' => $classificacao,
            ];
        }, $rows);

        $totals = [
            'qtd_clientes' => count($details),
            'frequente' => $contagem['frequente'],
            'regular' => $contagem['regular'],
            'esporadico' => $contagem['esporadico'],
            'infrequente' => $contagem['infrequente'],
            'unica' => $contagem['unica'],
        ];

        return [
            'totals' => $totals,
            'details' => ['lista' => $details],
            'chart' => [
                'labels' => [
                    \t('modules.relatorios.clientes.frequencia.classe_frequente'),
                    \t('modules.relatorios.clientes.frequencia.classe_regular'),
                    \t('modules.relatorios.clientes.frequencia.classe_esporadico'),
                    \t('modules.relatorios.clientes.frequencia.classe_infrequente'),
                    \t('modules.relatorios.clientes.frequencia.classe_unica'),
                ],
                'data' => [
                    $contagem['frequente'],
                    $contagem['regular'],
                    $contagem['esporadico'],
                    $contagem['infrequente'],
                    $contagem['unica'],
                ],
            ],
        ];
    }

    // =====================================================
    // 4.6 — TEMPO DE RELACIONAMENTO (LIFETIME)
    // =====================================================
    public function tempoRelacionamento(
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        $query = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw("
                l.id_cliente, l.cliente_nome,
                MIN(l.data_saida) AS desde,
                MAX(l.data_saida) AS ultima,
                COUNT(*) AS total_locacoes,
                COALESCE(SUM(l.total_pagar), 0) AS faturamento_lifetime,
                TIMESTAMPDIFF(MONTH, MIN(l.data_saida), CURDATE()) AS meses
            ")
            ->whereNotNull('l.id_cliente')
            ->groupBy(['l.id_cliente', 'l.cliente_nome'])
            ->orderByRaw('faturamento_lifetime DESC');

        $this->applyFilial($query, $filialWhere, $filialParams, $filialId, 'id_matriz_filial_retirada', 'l');

        $rows = $query->get();

        $details = array_map(fn($r) => [
            'id_cliente' => (int) $r['id_cliente'],
            'cliente' => $r['cliente_nome'] ?: '-',
            'desde' => $r['desde'],
            'meses' => (int) $r['meses'],
            'total_locacoes' => (int) $r['total_locacoes'],
            'faturamento_lifetime' => (float) $r['faturamento_lifetime'],
            'ultima_locacao' => $r['ultima'],
        ], $rows);

        $totals = [
            'qtd_clientes' => count($details),
            'idade_media_meses' => $this->safeDivide((int) array_sum(array_column($details, 'meses')), count($details), 1),
            'faturamento_total' => (float) array_sum(array_column($details, 'faturamento_lifetime')),
            'ltv_medio' => $this->safeDivide(
                (float) array_sum(array_column($details, 'faturamento_lifetime')),
                count($details),
                2
            ),
        ];

        // Chart: distribuicao por anos de relacionamento
        $porAno = ['<1' => 0, '1-2' => 0, '2-5' => 0, '5+' => 0];
        foreach ($details as $d) {
            $meses = $d['meses'];
            if ($meses < 12) $porAno['<1']++;
            elseif ($meses < 24) $porAno['1-2']++;
            elseif ($meses < 60) $porAno['2-5']++;
            else $porAno['5+']++;
        }

        return [
            'totals' => $totals,
            'details' => ['lista' => $details],
            'chart' => [
                'labels' => array_map(fn($k) => $k . ' ' . \t('modules.relatorios.clientes.tempo_relacionamento.anos'), array_keys($porAno)),
                'data' => array_values($porAno),
            ],
        ];
    }

    // =====================================================
    // 4.7 — OCORRENCIAS
    // =====================================================
    public function ocorrencias(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        $details = [];

        // Devoluções atrasadas
        $qAtraso = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw("
                l.id, l.codigo, l.id_cliente, l.cliente_nome,
                l.data_chegada AS data, l.data_prevista, l.data_chegada,
                DATEDIFF(l.data_chegada, l.data_prevista) AS dias_atraso,
                l.total_pagar AS valor
            ")
            ->whereRaw('l.status = ?', ['F'])
            ->whereNotNull('l.data_chegada')
            ->whereRaw('l.data_chegada > l.data_prevista')
            ->whereRaw('l.data_chegada BETWEEN ? AND ?', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59']);
        $this->applyFilial($qAtraso, $filialWhere, $filialParams, $filialId, 'id_matriz_filial_retirada', 'l');
        foreach ($qAtraso->get() as $r) {
            $details[] = [
                'data' => $r['data'],
                'tipo' => 'devolucao_atrasada',
                'cliente' => $r['cliente_nome'] ?: '-',
                'id_cliente' => $r['id_cliente'] ? (int) $r['id_cliente'] : null,
                'locacao' => $r['codigo'] ?? '-',
                'descricao' => sprintf(\t('modules.relatorios.clientes.ocorrencias.atraso_desc'), (int) $r['dias_atraso']),
                'valor' => (float) $r['valor'],
                'status' => 'finalizada',
            ];
        }

        // Inadimplência (faturas vencidas pendentes)
        $qInad = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw("
                f.id, f.codigo, f.id_cliente, cl.nome_rsocial AS cliente_nome,
                f.id_locacao, l.codigo AS loc_codigo,
                f.data_venci AS data,
                DATEDIFF(CURDATE(), f.data_venci) AS dias_atraso,
                f.valor_total AS valor, f.descricao
            ")
            ->leftJoin('clientes', 'cl', 'f.id_cliente', '=', 'cl.id')
            ->leftJoin('locacoes', 'l', 'f.id_locacao', '=', 'l.id')
            ->whereRaw('f.tipo = ?', ['R'])
            ->whereRaw('f.pago = ?', ['N'])
            ->whereRaw('f.data_venci < CURDATE()')
            ->whereRaw('f.data_venci BETWEEN ? AND ?', [$dataInicio, $dataFim]);
        $this->applyFilial($qInad, $filialWhere, $filialParams, $filialId, 'id_matriz_filial', 'f');
        foreach ($qInad->get() as $r) {
            $details[] = [
                'data' => $r['data'],
                'tipo' => 'inadimplencia',
                'cliente' => $r['cliente_nome'] ?: '-',
                'id_cliente' => $r['id_cliente'] ? (int) $r['id_cliente'] : null,
                'locacao' => $r['loc_codigo'] ?? ($r['codigo'] ?? '-'),
                'descricao' => $r['descricao'] ?: sprintf(\t('modules.relatorios.clientes.ocorrencias.inad_desc'), (int) $r['dias_atraso']),
                'valor' => (float) $r['valor'],
                'status' => 'pendente',
            ];
        }

        // Ordenar por data desc
        usort($details, fn($a, $b) => strcmp($b['data'] ?? '', $a['data'] ?? ''));

        // Totalizadores
        $countAtraso = count(array_filter($details, fn($d) => $d['tipo'] === 'devolucao_atrasada'));
        $countInad = count(array_filter($details, fn($d) => $d['tipo'] === 'inadimplencia'));
        $valorTotal = (float) array_sum(array_column($details, 'valor'));

        $totals = [
            'qtd_ocorrencias' => count($details),
            'qtd_atrasos' => $countAtraso,
            'qtd_inadimplencia' => $countInad,
            'valor_total' => $valorTotal,
        ];

        // Chart: top 10 clientes com mais ocorrências
        $porCliente = [];
        foreach ($details as $d) {
            $key = $d['cliente'];
            $porCliente[$key] = ($porCliente[$key] ?? 0) + 1;
        }
        arsort($porCliente);
        $top = array_slice($porCliente, 0, 10, true);

        return [
            'totals' => $totals,
            'details' => ['lista' => $details],
            'chart' => [
                'labels' => array_keys($top),
                'data' => array_values($top),
            ],
        ];
    }

    // =====================================================
    // 4.8 — INATIVOS
    // =====================================================
    public function inativos(
        int $diasMinimo,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        $diasMinimo = max(1, $diasMinimo);

        // Query base: clientes + max(data_saida) das locacoes
        $query = $this->qb
            ->table('clientes', 'c')
            ->selectRaw("
                c.id, c.nome_rsocial, c.cpf_cnpj,
                MAX(l.data_saida) AS ultima_locacao,
                COALESCE(COUNT(l.id), 0) AS total_locacoes,
                COALESCE(SUM(l.total_pagar), 0) AS faturamento
            ")
            ->leftJoin('locacoes', 'l', 'l.id_cliente', '=', 'c.id')
            ->groupBy(['c.id', 'c.nome_rsocial', 'c.cpf_cnpj'])
            ->havingRaw('MAX(l.data_saida) IS NULL OR DATEDIFF(CURDATE(), MAX(l.data_saida)) >= ?', [$diasMinimo])
            ->orderByRaw('ultima_locacao DESC');

        $this->applyFilial($query, $filialWhere, $filialParams, $filialId, 'id_matriz_filial', 'c');

        $rows = $query->get();

        $details = [];
        foreach ($rows as $r) {
            $tel = $this->qb->table('contatos_telefones', 't')
                ->select(['t.telefone'])
                ->where('t.entidade_tipo', '=', 'cliente')
                ->where('t.entidade_id', '=', (int) $r['id'])
                ->where('t.principal', '=', 'S')
                ->first();

            $diasInativo = !empty($r['ultima_locacao'])
                ? (int) ((\App\Helpers\DateHelper::timestamp() - strtotime((string) $r['ultima_locacao'])) / 86400)
                : null;

            $details[] = [
                'id_cliente' => (int) $r['id'],
                'cliente' => $r['nome_rsocial'] ?: '-',
                'cpf_cnpj' => $r['cpf_cnpj'] ?? '',
                'ultima_locacao' => $r['ultima_locacao'],
                'dias_inativo' => $diasInativo,
                'total_locacoes' => (int) $r['total_locacoes'],
                'faturamento' => (float) $r['faturamento'],
                'telefone' => $tel['telefone'] ?? '',
                'nunca_locou' => empty($r['ultima_locacao']),
            ];
        }

        $totals = [
            'qtd_inativos' => count($details),
            'qtd_nunca_locaram' => count(array_filter($details, fn($d) => $d['nunca_locou'])),
            'faturamento_perdido' => (float) array_sum(array_column($details, 'faturamento')),
            'media_dias_inativo' => $this->safeDivide(
                (int) array_sum(array_filter(array_column($details, 'dias_inativo'))),
                count(array_filter($details, fn($d) => $d['dias_inativo'] !== null)),
                0
            ),
        ];

        // Chart: distribuicao por faixa de inatividade
        $faixas = ['nunca' => 0, '6-12m' => 0, '1-2a' => 0, '2-5a' => 0, '5+a' => 0];
        foreach ($details as $d) {
            if ($d['nunca_locou']) { $faixas['nunca']++; continue; }
            $dias = $d['dias_inativo'] ?? 0;
            if ($dias < 365) $faixas['6-12m']++;
            elseif ($dias < 730) $faixas['1-2a']++;
            elseif ($dias < 1825) $faixas['2-5a']++;
            else $faixas['5+a']++;
        }

        $labelsTr = [
            'nunca' => \t('modules.relatorios.clientes.inativos.faixa_nunca'),
            '6-12m' => \t('modules.relatorios.clientes.inativos.faixa_6_12m'),
            '1-2a' => \t('modules.relatorios.clientes.inativos.faixa_1_2a'),
            '2-5a' => \t('modules.relatorios.clientes.inativos.faixa_2_5a'),
            '5+a' => \t('modules.relatorios.clientes.inativos.faixa_5_mais'),
        ];

        return [
            'totals' => $totals,
            'details' => ['lista' => $details],
            'chart' => [
                'labels' => array_map(fn($k) => $labelsTr[$k] ?? $k, array_keys($faixas)),
                'data' => array_values($faixas),
            ],
        ];
    }
}
