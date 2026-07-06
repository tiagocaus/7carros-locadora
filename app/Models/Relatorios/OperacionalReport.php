<?php

namespace App\Models\Relatorios;

/**
 * Relatorios da categoria OPERACIONAL (grupo 6).
 *
 * 6.1 Checklists Realizados   -> checklistsRealizados()
 * 6.2 Avarias e Sinistros     -> avariasSinistros()
 * 6.3 Multas de Transito      -> multasTransito()
 * 6.4 Devolucoes Antecipadas  -> devolucoesAntecipadas()
 * 6.5 Devolucoes Atrasadas    -> devolucoesAtrasadas()
 * 6.6 Reservas Canceladas     -> reservasCanceladas()
 * 6.7 Turnaround              -> turnaround()
 * 6.8 Combustivel             -> combustivel()
 */
class OperacionalReport extends BaseReportModel
{
    /**
     * Aplica filtro de filial usando alias/coluna informados.
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
    // 6.1 — CHECKLISTS REALIZADOS
    // =====================================================
    public function checklistsRealizados(
        string $dataInicio,
        string $dataFim,
        string $tipoMomento,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        $query = $this->qb
            ->table('checklist', 'ch')
            ->selectRaw("
                ch.id, ch.codigo, ch.tipo, ch.status, ch.data_saida, ch.data_entrada,
                ch.questoes_saida, ch.vistoria_saida, ch.questoes_entrada, ch.vistoria_entrada,
                ch.id_locacao, ch.id_funcionario, ch.id_veiculo,
                v.placa, v.modelo AS veiculo_modelo,
                f.nome AS funcionario_nome,
                l.codigo AS locacao_codigo, l.cliente_nome
            ")
            ->leftJoin('veiculos', 'v', 'ch.id_veiculo', '=', 'v.id')
            ->leftJoin('funcionarios', 'f', 'ch.id_funcionario', '=', 'f.id')
            ->leftJoin('locacoes', 'l', 'ch.id_locacao', '=', 'l.id')
            ->whereRaw('(ch.data_saida BETWEEN ? AND ? OR ch.data_entrada BETWEEN ? AND ?)', [
                $dataInicio . ' 00:00:00',
                $dataFim . ' 23:59:59',
                $dataInicio . ' 00:00:00',
                $dataFim . ' 23:59:59',
            ])
            ->orderByRaw('COALESCE(ch.data_entrada, ch.data_saida) DESC');

        $this->applyFilial($query, $filialWhere, $filialParams, $filialId, 'id_matriz_filial', 'v');

        $rows = $query->get();

        $details = [];
        $totalOk = 0; $totalProb = 0; $totalChecks = 0; $checksComProblema = 0;
        $porFuncionario = [];

        foreach ($rows as $r) {
            $etapas = [];
            if (!empty($r['data_saida']) && $r['data_saida'] >= $dataInicio . ' 00:00:00' && $r['data_saida'] <= $dataFim . ' 23:59:59') {
                $etapas[] = [
                    'momento' => ($r['tipo'] ?? '') === 'V' ? 'S' : 'N',
                    'data' => $r['data_saida'],
                    'questoes' => $r['questoes_saida'] ?? '',
                    'vistoria' => $r['vistoria_saida'] ?? '',
                ];
            }
            if (($r['tipo'] ?? '') === 'V' && !empty($r['data_entrada']) && $r['data_entrada'] >= $dataInicio . ' 00:00:00' && $r['data_entrada'] <= $dataFim . ' 23:59:59') {
                $etapas[] = [
                    'momento' => 'C',
                    'data' => $r['data_entrada'],
                    'questoes' => $r['questoes_entrada'] ?? '',
                    'vistoria' => $r['vistoria_entrada'] ?? '',
                ];
            }

            foreach ($etapas as $etapa) {
                if (!empty($tipoMomento) && $etapa['momento'] !== $tipoMomento) {
                    continue;
                }

            // Decodifica questoes (JSON)
            $itensOk = 0; $itensProb = 0; $totalItens = 0;
            $questoes = !empty($etapa['questoes']) ? json_decode($etapa['questoes'], true) : null;
            if (is_array($questoes)) {
                foreach ($questoes as $q) {
                    $totalItens++;
                    if (isset($q['opt']) && (string) $q['opt'] === '1') {
                        $itensOk++;
                    } else {
                        $itensProb++;
                    }
                }
            }

            // Conta fotos da vistoria
            $vistoria = !empty($etapa['vistoria']) ? json_decode($etapa['vistoria'], true) : null;
            $qtdFotos = 0;
            if (is_array($vistoria)) {
                foreach ($vistoria as $v) {
                    if (!empty($v['foto'])) $qtdFotos++;
                    if (is_array($v) && isset($v['fotos']) && is_array($v['fotos'])) $qtdFotos += count($v['fotos']);
                }
            }

            $totalChecks++;
            $totalOk += $itensOk;
            $totalProb += $itensProb;
            if ($itensProb > 0) $checksComProblema++;

            $funcNome = $r['funcionario_nome'] ?: '-';
            $porFuncionario[$funcNome] = ($porFuncionario[$funcNome] ?? 0) + 1;

            $details[] = [
                'id' => (int) $r['id'],
                'codigo' => $r['codigo'] ?? '-',
                'data_checklist' => $etapa['data'],
                'momento' => $etapa['momento'],
                'placa' => $r['placa'] ?? '-',
                'veiculo_modelo' => $r['veiculo_modelo'] ?? '',
                'locacao_codigo' => $r['locacao_codigo'] ?? '-',
                'cliente_nome' => $r['cliente_nome'] ?? '-',
                'funcionario_nome' => $funcNome,
                'itens_ok' => $itensOk,
                'itens_problema' => $itensProb,
                'total_itens' => $totalItens,
                'qtd_fotos' => $qtdFotos,
                'tanque' => '',
                'odometro' => null,
            ];
            }
        }

        $totals = [
            'total_checklists' => $totalChecks,
            'total_itens_ok' => $totalOk,
            'total_itens_problema' => $totalProb,
            'taxa_problema' => $this->pct((float) $checksComProblema, (float) $totalChecks, 1),
        ];

        // Top 10 funcionarios
        arsort($porFuncionario);
        $top = array_slice($porFuncionario, 0, 10, true);

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
    // 6.2 — AVARIAS E SINISTROS (proxy via checklists com itens problema)
    // =====================================================
    public function avariasSinistros(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        // Busca checklists no periodo (depois filtra os que tem itens com problema)
        $query = $this->qb
            ->table('checklist', 'ch')
            ->selectRaw("
                ch.id, ch.codigo, ch.tipo, ch.data_saida, ch.data_entrada,
                ch.questoes_saida, ch.questoes_entrada,
                ch.observacoes_saida, ch.observacoes_entrada,
                v.placa, v.modelo AS veiculo_modelo,
                l.codigo AS locacao_codigo, l.cliente_nome, l.id_cliente
            ")
            ->leftJoin('veiculos', 'v', 'ch.id_veiculo', '=', 'v.id')
            ->leftJoin('locacoes', 'l', 'ch.id_locacao', '=', 'l.id')
            ->whereRaw('(ch.data_saida BETWEEN ? AND ? OR ch.data_entrada BETWEEN ? AND ?)', [
                $dataInicio . ' 00:00:00',
                $dataFim . ' 23:59:59',
                $dataInicio . ' 00:00:00',
                $dataFim . ' 23:59:59',
            ])
            ->orderByRaw('COALESCE(ch.data_entrada, ch.data_saida) DESC');

        $this->applyFilial($query, $filialWhere, $filialParams, $filialId, 'id_matriz_filial', 'v');

        $rows = $query->get();

        $details = [];
        $countLeve = 0; $countMedia = 0; $countSinistro = 0;

        foreach ($rows as $r) {
            $etapas = [];
            if (!empty($r['data_saida']) && $r['data_saida'] >= $dataInicio . ' 00:00:00' && $r['data_saida'] <= $dataFim . ' 23:59:59') {
                $etapas[] = [
                    'data' => $r['data_saida'],
                    'questoes' => $r['questoes_saida'] ?? '',
                    'obs' => $r['observacoes_saida'] ?? '',
                ];
            }
            if (($r['tipo'] ?? '') === 'V' && !empty($r['data_entrada']) && $r['data_entrada'] >= $dataInicio . ' 00:00:00' && $r['data_entrada'] <= $dataFim . ' 23:59:59') {
                $etapas[] = [
                    'data' => $r['data_entrada'],
                    'questoes' => $r['questoes_entrada'] ?? '',
                    'obs' => $r['observacoes_entrada'] ?? '',
                ];
            }

            foreach ($etapas as $etapa) {
            $itensProb = [];
            $questoes = !empty($etapa['questoes']) ? json_decode($etapa['questoes'], true) : null;
            if (is_array($questoes)) {
                foreach ($questoes as $q) {
                    if (isset($q['opt']) && (string) $q['opt'] !== '1') {
                        $itensProb[] = $q['content'] ?? '-';
                    }
                }
            }

            // Pula checklists sem problemas
            if (empty($itensProb) && empty($etapa['obs'])) continue;

            // Classificacao por quantidade de itens reportados
            $qtd = count($itensProb);
            if ($qtd === 0) {
                $tipo = 'leve'; $countLeve++;
            } elseif ($qtd <= 2) {
                $tipo = 'leve'; $countLeve++;
            } elseif ($qtd <= 5) {
                $tipo = 'media'; $countMedia++;
            } else {
                $tipo = 'sinistro'; $countSinistro++;
            }

            $details[] = [
                'data' => $etapa['data'],
                'placa' => $r['placa'] ?? '-',
                'veiculo_modelo' => $r['veiculo_modelo'] ?? '',
                'cliente_nome' => $r['cliente_nome'] ?? '-',
                'locacao_codigo' => $r['locacao_codigo'] ?? '-',
                'tipo' => $tipo,
                'descricao' => !empty($itensProb)
                    ? implode(', ', array_slice($itensProb, 0, 5)) . (count($itensProb) > 5 ? '...' : '')
                    : substr($etapa['obs'] ?? '', 0, 200),
                'qtd_itens' => $qtd,
                'codigo' => $r['codigo'] ?? '-',
            ];
            }
        }

        $totals = [
            'total_avarias' => count($details),
            'qtd_leve' => $countLeve,
            'qtd_media' => $countMedia,
            'qtd_sinistro' => $countSinistro,
        ];

        return [
            'totals' => $totals,
            'details' => ['lista' => $details],
            'chart' => [
                'labels' => [
                    \t('modules.relatorios.operacional.avarias_sinistros.tipo_leve'),
                    \t('modules.relatorios.operacional.avarias_sinistros.tipo_media'),
                    \t('modules.relatorios.operacional.avarias_sinistros.tipo_sinistro'),
                ],
                'data' => [$countLeve, $countMedia, $countSinistro],
            ],
        ];
    }

    // =====================================================
    // 6.3 — MULTAS DE TRANSITO
    // =====================================================
    public function multasTransito(
        string $dataInicio,
        string $dataFim,
        string $statusFiltro,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        $query = $this->qb
            ->table('multas', 'm')
            ->selectRaw("
                m.id, m.data_hora, m.data_vencimento, m.valor, m.descri,
                m.cidade, m.estado, m.local, m.pago, m.status_processamento,
                m.id_locacao, m.id_cliente, m.id_veiculo,
                v.placa, v.modelo AS veiculo_modelo,
                cl.nome_rsocial AS cliente_nome,
                l.codigo AS locacao_codigo
            ")
            ->leftJoin('veiculos', 'v', 'm.id_veiculo', '=', 'v.id')
            ->leftJoin('clientes', 'cl', 'm.id_cliente', '=', 'cl.id')
            ->leftJoin('locacoes', 'l', 'm.id_locacao', '=', 'l.id')
            ->whereRaw('m.data_hora BETWEEN ? AND ?', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
            ->orderByRaw('m.data_hora DESC');

        if (!empty($statusFiltro)) {
            $query->whereRaw('m.status_processamento = ?', [$statusFiltro]);
        }

        $this->applyFilial($query, $filialWhere, $filialParams, $filialId, 'id_matriz_filial', 'm');

        $rows = $query->get();

        $details = [];
        $valorTotal = 0; $countPagas = 0; $countPendentes = 0;
        $porTipo = [];

        foreach ($rows as $r) {
            $valor = (float) ($r['valor'] ?? 0);
            $valorTotal += $valor;
            if (($r['pago'] ?? 'N') === 'S') $countPagas++;
            else $countPendentes++;

            $tipo = trim($r['descri'] ?? '') ?: '-';
            $tipoCurto = substr($tipo, 0, 40);
            $porTipo[$tipoCurto] = ($porTipo[$tipoCurto] ?? 0) + 1;

            $details[] = [
                'id' => (int) $r['id'],
                'data_hora' => $r['data_hora'],
                'data_vencimento' => $r['data_vencimento'],
                'placa' => $r['placa'] ?? '-',
                'veiculo_modelo' => $r['veiculo_modelo'] ?? '',
                'locacao_codigo' => $r['locacao_codigo'] ?? '-',
                'cliente_nome' => $r['cliente_nome'] ?? '-',
                'descricao' => $tipo,
                'cidade' => $r['cidade'] ?? '',
                'estado' => $r['estado'] ?? '',
                'valor' => $valor,
                'pago' => $r['pago'] ?? 'N',
                'status_processamento' => $r['status_processamento'] ?? '',
            ];
        }

        $totals = [
            'total_multas' => count($details),
            'valor_total' => $valorTotal,
            'qtd_pagas' => $countPagas,
            'qtd_pendentes' => $countPendentes,
        ];

        // Chart: top 10 tipos de multa
        arsort($porTipo);
        $top = array_slice($porTipo, 0, 10, true);

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
    // 6.4 — DEVOLUCOES ANTECIPADAS
    // =====================================================
    public function devolucoesAntecipadas(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        $query = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw("
                l.id, l.codigo, l.cliente_nome, l.id_cliente,
                l.data_saida, l.data_prevista, l.data_chegada,
                l.dias, l.total_pagar,
                DATEDIFF(l.data_prevista, l.data_chegada) AS dias_antecipado,
                v.placa, v.modelo AS veiculo_modelo
            ")
            ->leftJoin('locacoes_veiculos', 'lv', 'lv.id_locacao', '=', 'l.id')
            ->leftJoin('veiculos', 'v', 'lv.id_veiculo', '=', 'v.id')
            ->whereRaw('l.status = ?', ['F'])
            ->whereNotNull('l.data_chegada')
            ->whereRaw('l.data_chegada < l.data_prevista')
            ->whereRaw('l.data_chegada BETWEEN ? AND ?', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
            ->orderByRaw('l.data_chegada DESC');

        $this->applyFilial($query, $filialWhere, $filialParams, $filialId, 'id_matriz_filial_retirada', 'l');

        $rows = $query->get();

        $details = [];
        $totalDiasAnt = 0;
        $porFaixa = ['1-3' => 0, '4-7' => 0, '8-15' => 0, '16+' => 0];

        foreach ($rows as $r) {
            $dias = (int) ($r['dias_antecipado'] ?? 0);
            if ($dias <= 0) continue;

            $totalDiasAnt += $dias;

            if ($dias <= 3) $porFaixa['1-3']++;
            elseif ($dias <= 7) $porFaixa['4-7']++;
            elseif ($dias <= 15) $porFaixa['8-15']++;
            else $porFaixa['16+']++;

            $details[] = [
                'id_locacao' => (int) $r['id'],
                'codigo' => $r['codigo'] ?? '-',
                'cliente_nome' => $r['cliente_nome'] ?? '-',
                'placa' => $r['placa'] ?? '-',
                'veiculo_modelo' => $r['veiculo_modelo'] ?? '',
                'data_saida' => $r['data_saida'],
                'data_prevista' => $r['data_prevista'],
                'data_chegada' => $r['data_chegada'],
                'dias_antecipado' => $dias,
                'dias_total' => (int) ($r['dias'] ?? 0),
                'total_pagar' => (float) ($r['total_pagar'] ?? 0),
            ];
        }

        $qtd = count($details);
        $totals = [
            'total_devolucoes_antecipadas' => $qtd,
            'media_dias_antecipados' => $this->safeDivide($totalDiasAnt, $qtd, 1),
            'total_dias_antecipados' => $totalDiasAnt,
        ];

        return [
            'totals' => $totals,
            'details' => ['lista' => $details],
            'chart' => [
                'labels' => array_map(fn($k) => $k . ' ' . \t('modules.relatorios.operacional.devolucoes_antecipadas.dias'), array_keys($porFaixa)),
                'data' => array_values($porFaixa),
            ],
        ];
    }

    // =====================================================
    // 6.5 — DEVOLUCOES ATRASADAS
    // =====================================================
    public function devolucoesAtrasadas(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        $query = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw("
                l.id, l.codigo, l.cliente_nome, l.id_cliente,
                l.data_saida, l.data_prevista, l.data_chegada, l.status,
                l.dias, l.total_pagar,
                DATEDIFF(l.data_chegada, l.data_prevista) AS dias_atraso,
                v.placa, v.modelo AS veiculo_modelo
            ")
            ->leftJoin('locacoes_veiculos', 'lv', 'lv.id_locacao', '=', 'l.id')
            ->leftJoin('veiculos', 'v', 'lv.id_veiculo', '=', 'v.id')
            ->whereRaw('l.status = ?', ['F'])
            ->whereNotNull('l.data_chegada')
            ->whereRaw('l.data_chegada > l.data_prevista')
            ->whereRaw('l.data_chegada BETWEEN ? AND ?', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
            ->orderByRaw('l.data_chegada DESC');

        $this->applyFilial($query, $filialWhere, $filialParams, $filialId, 'id_matriz_filial_retirada', 'l');

        $rows = $query->get();

        $details = [];
        $totalDiasAtraso = 0;
        $porFaixa = ['1-1' => 0, '2-3' => 0, '4-7' => 0, '8+' => 0];

        foreach ($rows as $r) {
            $dias = (int) ($r['dias_atraso'] ?? 0);
            if ($dias <= 0) continue;

            $totalDiasAtraso += $dias;

            if ($dias === 1) $porFaixa['1-1']++;
            elseif ($dias <= 3) $porFaixa['2-3']++;
            elseif ($dias <= 7) $porFaixa['4-7']++;
            else $porFaixa['8+']++;

            $details[] = [
                'id_locacao' => (int) $r['id'],
                'codigo' => $r['codigo'] ?? '-',
                'cliente_nome' => $r['cliente_nome'] ?? '-',
                'placa' => $r['placa'] ?? '-',
                'veiculo_modelo' => $r['veiculo_modelo'] ?? '',
                'data_saida' => $r['data_saida'],
                'data_prevista' => $r['data_prevista'],
                'data_chegada' => $r['data_chegada'],
                'dias_atraso' => $dias,
                'dias_total' => (int) ($r['dias'] ?? 0),
                'total_pagar' => (float) ($r['total_pagar'] ?? 0),
            ];
        }

        $qtd = count($details);
        $totals = [
            'total_devolucoes_atrasadas' => $qtd,
            'media_dias_atraso' => $this->safeDivide($totalDiasAtraso, $qtd, 1),
            'total_dias_atraso' => $totalDiasAtraso,
        ];

        return [
            'totals' => $totals,
            'details' => ['lista' => $details],
            'chart' => [
                'labels' => array_map(fn($k) => $k . ' ' . \t('modules.relatorios.operacional.devolucoes_atrasadas.dias'), array_keys($porFaixa)),
                'data' => array_values($porFaixa),
            ],
        ];
    }

    // =====================================================
    // 6.6 — RESERVAS CANCELADAS / EXPIRADAS
    // =====================================================
    public function reservasCanceladas(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        // Reservas (status R) cuja data_prevista ja passou e nunca foram convertidas
        $query = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw("
                l.id, l.codigo, l.cliente_nome, l.id_cliente,
                l.data_saida AS data_prevista_saida,
                l.data_prevista,
                l.dias, l.total_pagar,
                l.created_at AS data_reserva,
                DATEDIFF(l.data_saida, l.created_at) AS antecedencia,
                v.placa, v.modelo AS veiculo_modelo
            ")
            ->leftJoin('locacoes_veiculos', 'lv', 'lv.id_locacao', '=', 'l.id')
            ->leftJoin('veiculos', 'v', 'lv.id_veiculo', '=', 'v.id')
            ->whereRaw('l.status = ?', ['R'])
            ->whereRaw('l.data_saida < CURDATE()')
            ->whereRaw('l.created_at BETWEEN ? AND ?', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
            ->orderByRaw('l.created_at DESC');

        $this->applyFilial($query, $filialWhere, $filialParams, $filialId, 'id_matriz_filial_retirada', 'l');

        $rows = $query->get();

        $details = [];
        $valorPerdido = 0;
        $porAntecedencia = ['<7d' => 0, '7-15d' => 0, '15-30d' => 0, '30+d' => 0];

        foreach ($rows as $r) {
            $valor = (float) ($r['total_pagar'] ?? 0);
            $valorPerdido += $valor;
            $ant = (int) ($r['antecedencia'] ?? 0);
            if ($ant < 7) $porAntecedencia['<7d']++;
            elseif ($ant <= 15) $porAntecedencia['7-15d']++;
            elseif ($ant <= 30) $porAntecedencia['15-30d']++;
            else $porAntecedencia['30+d']++;

            $details[] = [
                'id_locacao' => (int) $r['id'],
                'codigo' => $r['codigo'] ?? '-',
                'cliente_nome' => $r['cliente_nome'] ?? '-',
                'placa' => $r['placa'] ?? '-',
                'veiculo_modelo' => $r['veiculo_modelo'] ?? '',
                'data_reserva' => $r['data_reserva'],
                'data_prevista_saida' => $r['data_prevista_saida'],
                'data_prevista_devolucao' => $r['data_prevista'],
                'antecedencia' => $ant,
                'dias_reserva' => (int) ($r['dias'] ?? 0),
                'valor_perdido' => $valor,
            ];
        }

        $totals = [
            'total_canceladas' => count($details),
            'valor_perdido' => $valorPerdido,
        ];

        return [
            'totals' => $totals,
            'details' => ['lista' => $details],
            'chart' => [
                'labels' => array_keys($porAntecedencia),
                'data' => array_values($porAntecedencia),
            ],
        ];
    }

    // =====================================================
    // 6.7 — TURNAROUND (Tempo de Retorno entre locacoes)
    // =====================================================
    public function turnaround(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        $query = $this->qb
            ->table('locacoes_veiculos', 'lv')
            ->selectRaw("
                lv.id_veiculo,
                v.placa, v.modelo AS veiculo_modelo,
                l.id AS id_locacao, l.codigo, l.data_chegada, l.data_saida
            ")
            ->leftJoin('locacoes', 'l', 'lv.id_locacao', '=', 'l.id')
            ->leftJoin('veiculos', 'v', 'lv.id_veiculo', '=', 'v.id')
            ->whereRaw('l.status = ?', ['F'])
            ->whereNotNull('l.data_chegada')
            ->whereRaw('l.data_chegada BETWEEN ? AND ?', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
            ->orderByRaw('lv.id_veiculo ASC, l.data_chegada ASC');

        $this->applyFilial($query, $filialWhere, $filialParams, $filialId, 'id_matriz_filial_retirada', 'l');

        $rows = $query->get();

        // Agrupa por veiculo
        $porVeiculo = [];
        foreach ($rows as $r) {
            $vid = (int) ($r['id_veiculo'] ?? 0);
            if (!$vid) continue;
            $porVeiculo[$vid][] = $r;
        }

        $details = [];
        $totalHoras = 0;
        $porFaixa = ['<24h' => 0, '24-48h' => 0, '2-7d' => 0, '7+d' => 0];

        foreach ($porVeiculo as $vid => $locs) {
            $count = count($locs);
            for ($i = 0; $i < $count - 1; $i++) {
                $devolucao = $locs[$i]['data_chegada'] ?? null;
                // Proxima saida do mesmo veiculo
                $proxSaida = null;
                $proxLocacao = null;
                for ($j = $i + 1; $j < $count; $j++) {
                    if (!empty($locs[$j]['data_saida'])) {
                        $proxSaida = $locs[$j]['data_saida'];
                        $proxLocacao = $locs[$j];
                        break;
                    }
                }
                if (!$devolucao || !$proxSaida) continue;

                $devTs = strtotime($devolucao);
                $saiTs = strtotime($proxSaida);
                if ($devTs === false || $saiTs === false || $saiTs < $devTs) continue;

                $horas = round(($saiTs - $devTs) / 3600, 1);
                $totalHoras += $horas;

                if ($horas < 24) $porFaixa['<24h']++;
                elseif ($horas < 48) $porFaixa['24-48h']++;
                elseif ($horas < 168) $porFaixa['2-7d']++;
                else $porFaixa['7+d']++;

                $details[] = [
                    'placa' => $locs[$i]['placa'] ?? '-',
                    'veiculo_modelo' => $locs[$i]['veiculo_modelo'] ?? '',
                    'locacao_anterior' => $locs[$i]['codigo'] ?? '-',
                    'data_chegada' => $devolucao,
                    'proxima_locacao' => $proxLocacao['codigo'] ?? '-',
                    'data_saida_proxima' => $proxSaida,
                    'turnaround_horas' => $horas,
                ];
            }
        }

        $qtd = count($details);
        $totals = [
            'total_periodos' => $qtd,
            'turnaround_medio_horas' => $this->safeDivide($totalHoras, $qtd, 1),
            'turnaround_total_horas' => $totalHoras,
        ];

        // Ordena por turnaround desc
        usort($details, fn($a, $b) => $b['turnaround_horas'] <=> $a['turnaround_horas']);

        return [
            'totals' => $totals,
            'details' => ['lista' => $details],
            'chart' => [
                'labels' => array_keys($porFaixa),
                'data' => array_values($porFaixa),
            ],
        ];
    }

    // =====================================================
    // 6.8 — COMBUSTIVEL
    // =====================================================
    public function combustivel(
        string $dataInicio,
        string $dataFim,
        string $filialWhere,
        array $filialParams,
        string $filialId = ''
    ): array {
        // Niveis de combustivel ficam em locacoes_veiculos.combustivel_saida/_entrada
        // (escala numerica 1..8 representando oitavos de tanque).
        $query = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw("
                l.id AS id_locacao, l.codigo, l.cliente_nome,
                l.data_saida, l.data_chegada,
                v.placa, v.modelo AS veiculo_modelo,
                lv.id_veiculo, lv.combustivel_saida, lv.combustivel_entrada
            ")
            ->leftJoin('locacoes_veiculos', 'lv', 'lv.id_locacao', '=', 'l.id')
            ->leftJoin('veiculos', 'v', 'lv.id_veiculo', '=', 'v.id')
            ->whereRaw('l.status = ?', ['F'])
            ->whereNotNull('l.data_chegada')
            ->whereRaw('l.data_chegada BETWEEN ? AND ?', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
            ->orderByRaw('l.data_chegada DESC');

        $this->applyFilial($query, $filialWhere, $filialParams, $filialId, 'id_matriz_filial_retirada', 'l');

        $rows = $query->get();

        $details = [];
        $countComDif = 0;

        foreach ($rows as $r) {
            $saida = $r['combustivel_saida'] ?? null;
            $chegada = $r['combustivel_entrada'] ?? null;

            $hasSaida = $saida !== null && $saida !== '';
            $hasChegada = $chegada !== null && $chegada !== '';
            if (!$hasSaida && !$hasChegada) continue;

            $valSaida = $hasSaida ? (int) $saida : null;
            $valChegada = $hasChegada ? (int) $chegada : null;

            $diferenca = ($valSaida !== null && $valChegada !== null) ? ($valChegada - $valSaida) : null;
            $temDiferenca = $diferenca !== null && $diferenca < 0;
            if ($temDiferenca) $countComDif++;

            $details[] = [
                'id_locacao' => (int) $r['id_locacao'],
                'codigo' => $r['codigo'] ?? '-',
                'cliente_nome' => $r['cliente_nome'] ?? '-',
                'placa' => $r['placa'] ?? '-',
                'veiculo_modelo' => $r['veiculo_modelo'] ?? '',
                'nivel_saida' => $valSaida !== null ? ($valSaida . '/8') : '-',
                'nivel_chegada' => $valChegada !== null ? ($valChegada . '/8') : '-',
                'diferenca' => $diferenca,
                'tem_diferenca' => $temDiferenca,
                'data_chegada' => $r['data_chegada'],
            ];
        }

        $qtd = count($details);
        $totals = [
            'total_locacoes' => $qtd,
            'qtd_com_diferenca' => $countComDif,
            'taxa_diferenca' => $this->pct((float) $countComDif, (float) $qtd, 1),
        ];

        // Chart: distribuicao por nivel de chegada (0/8 .. 8/8)
        $porNivel = [];
        for ($i = 0; $i <= 8; $i++) $porNivel[$i . '/8'] = 0;
        foreach ($details as $d) {
            if ($d['nivel_chegada'] !== '-') {
                $porNivel[$d['nivel_chegada']] = ($porNivel[$d['nivel_chegada']] ?? 0) + 1;
            }
        }

        return [
            'totals' => $totals,
            'details' => ['lista' => $details],
            'chart' => [
                'labels' => array_keys($porNivel),
                'data' => array_values($porNivel),
            ],
        ];
    }
}
