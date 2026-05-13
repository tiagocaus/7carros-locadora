<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Multa;
use App\Models\Veiculo;
use App\Models\SerproConfiguracao;
use App\Models\SerproConsultaLog;
use App\Services\SerproService;
use App\Services\SerproSaldoService;

/**
 * Controller de consultas online
 *
 * Gerencia consultas manuais e em lote de infracoes via API de consultas online,
 * download de PDFs (NA/NP), configuracoes do tenant e logs.
 */
class SerproConsultaController
{
    /**
     * Consulta infracoes de um veiculo por placa
     *
     * POST /api/multas-online/consultar-infracoes
     * Body: placa
     */
    public function consultarInfracoes(Request $request): void
    {
        try {
            $placa = strtoupper(trim($request->input('placa', '')));

            if (empty($placa)) {
                Response::json(['success' => false, 'message' => 'Placa obrigatoria'], 422);
                return;
            }

            // Verificar saldo
            $saldoService = new SerproSaldoService();
            if (!$saldoService->temSaldoParaConsultas(1)) {
                Response::json([
                    'success' => false,
                    'message' => 'Saldo insuficiente para consulta. Saldo atual: R$ ' . number_format($saldoService->getSaldo(), 2, ',', '.'),
                    'saldo_insuficiente' => true,
                ], 402);
                return;
            }

            // Debitar saldo
            $debito = $saldoService->debitarConsulta("Consulta infracoes placa {$placa}", $placa);

            // Consultar SERPRO
            $serpro = new SerproService();
            $resultado = $serpro->consultarInfracoes($placa);

            if (!$resultado['success']) {
                Response::json([
                    'success' => false,
                    'message' => $resultado['error'] ?? 'Erro ao consultar infracoes online',
                    'saldo_posterior' => $debito['saldo_posterior'],
                ], 502);
                return;
            }

            // Sincronizar multas no sistema
            $multasSincronizadas = $this->sincronizarInfracoes($placa, $resultado['data'] ?? []);

            Response::json([
                'success' => true,
                'data' => [
                    'infracoes' => $resultado['data'],
                    'multas_sincronizadas' => $multasSincronizadas,
                    'saldo_posterior' => $debito['saldo_posterior'],
                ],
                'message' => 'Consulta realizada com sucesso.',
            ]);
        } catch (\RuntimeException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage(),
                'saldo_insuficiente' => str_contains($e->getMessage(), 'Saldo insuficiente'),
            ], 402);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro na consulta: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consulta infracoes de todos os veiculos do tenant (lote)
     *
     * POST /api/multas-online/consultar-lote
     */
    public function consultarLote(Request $request): void
    {
        try {
            $veiculoModel = new Veiculo();
            $veiculos = $veiculoModel->listarPlacasBrasileiras();

            if (empty($veiculos)) {
                Response::json([
                    'success' => false,
                    'message' => 'Nenhum veiculo cadastrado.',
                ], 422);
                return;
            }

            $totalVeiculos = count($veiculos);

            // Verificar saldo para todas as consultas
            $saldoService = new SerproSaldoService();
            if (!$saldoService->temSaldoParaConsultas($totalVeiculos)) {
                $saldo = $saldoService->getSaldo();
                $custoTotal = $saldoService->getPrecoConsulta() * $totalVeiculos;
                Response::json([
                    'success' => false,
                    'message' => "Saldo insuficiente. Necessario R$ " . number_format($custoTotal, 2, ',', '.') . " para {$totalVeiculos} veiculos. Saldo: R$ " . number_format($saldo, 2, ',', '.'),
                    'saldo_insuficiente' => true,
                ], 402);
                return;
            }

            $serpro = new SerproService();
            $resultados = [];
            $totalInfracoes = 0;
            $erros = 0;

            foreach ($veiculos as $veiculo) {
                $placa = $veiculo['placa'];

                try {
                    // Debitar por consulta
                    $saldoService->debitarConsulta("Consulta lote placa {$placa}", $placa);

                    $resultado = $serpro->consultarInfracoes($placa);

                    if ($resultado['success']) {
                        $sincronizadas = $this->sincronizarInfracoes($placa, $resultado['data'] ?? []);
                        $totalInfracoes += $sincronizadas;
                        $resultados[] = [
                            'placa' => $placa,
                            'success' => true,
                            'infracoes' => count($resultado['data'] ?? []),
                            'novas' => $sincronizadas,
                        ];
                    } else {
                        $erros++;
                        $resultados[] = [
                            'placa' => $placa,
                            'success' => false,
                            'error' => $resultado['error'],
                        ];
                    }

                    // Respeitar rate limit (15 req/s) - pausa de 100ms entre consultas
                    usleep(100000);
                } catch (\Exception $e) {
                    $erros++;
                    $resultados[] = [
                        'placa' => $placa,
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // Atualizar data da ultima consulta
            $configModel = new SerproConfiguracao();
            $configModel->atualizarUltimaConsulta();

            Response::json([
                'success' => true,
                'data' => [
                    'total_veiculos' => $totalVeiculos,
                    'total_infracoes_novas' => $totalInfracoes,
                    'erros' => $erros,
                    'saldo_posterior' => $saldoService->getSaldo(),
                    'detalhes' => $resultados,
                ],
                'message' => "Consulta em lote finalizada. {$totalInfracoes} novas infracoes encontradas.",
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro na consulta em lote: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download de PDF (NA ou NP)
     *
     * GET /api/multas-online/pdf/{tipo}
     * Query: placa, codigo_orgao, numero_ait, codigo_infracao
     */
    public function downloadPdf(Request $request, string $tipo): void
    {
        try {
            $tipo = strtoupper($tipo);
            if (!in_array($tipo, ['NA', 'NP'], true)) {
                Response::json(['success' => false, 'message' => 'Tipo invalido. Use NA ou NP.'], 422);
                return;
            }

            $placa = strtoupper(trim($request->query('placa', '')));
            $codigoOrgao = $request->query('codigo_orgao', '');
            $numeroAit = $request->query('numero_ait', '');
            $codigoInfracao = $request->query('codigo_infracao', '');

            if (empty($placa) || empty($codigoOrgao) || empty($numeroAit) || empty($codigoInfracao)) {
                Response::json(['success' => false, 'message' => 'Parametros obrigatorios: placa, codigo_orgao, numero_ait, codigo_infracao'], 422);
                return;
            }

            $serpro = new SerproService();

            if ($tipo === 'NA') {
                $resultado = $serpro->downloadNAPdf($placa, $codigoOrgao, $numeroAit, $codigoInfracao);
            } else {
                $resultado = $serpro->downloadNPPdf($placa, $codigoOrgao, $numeroAit, $codigoInfracao);
            }

            if (!$resultado['success']) {
                Response::json([
                    'success' => false,
                    'message' => $resultado['error'] ?? "PDF {$tipo} nao disponivel",
                ], 502);
                return;
            }

            $pdfData = $resultado['data']['raw'] ?? $resultado['data']['pdf'] ?? null;

            if (empty($pdfData)) {
                Response::json(['success' => false, 'message' => "PDF {$tipo} vazio na resposta"], 404);
                return;
            }

            // Retornar base64 para o frontend exibir
            Response::json([
                'success' => true,
                'data' => [
                    'tipo' => $tipo,
                    'placa' => $placa,
                    'pdf_base64' => $pdfData,
                ],
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao baixar PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna configuracao SERPRO do tenant
     *
     * GET /api/multas-online/configuracao
     */
    public function getConfiguracao(Request $request): void
    {
        try {
            $configModel = new SerproConfiguracao();
            $config = $configModel->buscarPorChave();

            $serpro = new SerproService();

            Response::json([
                'success' => true,
                'data' => [
                    'configuracao' => $config,
                    'ambiente' => $serpro->getAmbiente(),
                ],
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao carregar configuracao: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Salva configuracao SERPRO do tenant
     *
     * POST /multas-online/configuracao/salvar
     * Body: cnpj_empresa, auto_consulta_ativo, intervalo_dias_consulta, auto_eventos_ativo
     */
    public function salvarConfiguracao(Request $request): void
    {
        try {
            $dados = [
                'cnpj_empresa' => $request->input('cnpj_empresa', ''),
                'auto_consulta_ativo' => (int) $request->input('auto_consulta_ativo', 0),
                'intervalo_dias_consulta' => (int) $request->input('intervalo_dias_consulta', 7),
                'auto_eventos_ativo' => (int) $request->input('auto_eventos_ativo', 0),
            ];

            if (empty($dados['cnpj_empresa'])) {
                Response::json(['success' => false, 'message' => 'CNPJ da empresa obrigatorio'], 422);
                return;
            }

            $configModel = new SerproConfiguracao();
            $configModel->salvar($dados);

            // Se auto_eventos ativado, registrar webhook na SERPRO
            if ($dados['auto_eventos_ativo']) {
                $this->registrarWebhookSerpro($configModel);
            }

            Response::json([
                'success' => true,
                'message' => 'Configuracao salva com sucesso.',
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao salvar configuracao: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle individual de configuracao de automacao
     *
     * POST /multas-online/configuracao/toggle
     * Body: campo (auto_consulta_ativo | auto_eventos_ativo), valor (0|1), intervalo_dias_consulta (opcional)
     */
    public function toggleConfiguracao(Request $request): void
    {
        try {
            $campo = $request->input('campo', '');
            $valor = (int) $request->input('valor', 0);

            $camposPermitidos = ['auto_consulta_ativo', 'auto_eventos_ativo'];
            if (!in_array($campo, $camposPermitidos, true)) {
                Response::json(['success' => false, 'message' => 'Campo invalido'], 422);
                return;
            }

            $configModel = new SerproConfiguracao();
            $config = $configModel->buscarPorChave();

            if (!$config) {
                Response::json([
                    'success' => false,
                    'message' => 'Configuracao de consulta online nao encontrada. Configure o CNPJ da empresa primeiro.',
                    'requires_setup' => true,
                ], 422);
                return;
            }

            $dados = [$campo => $valor];

            if ($campo === 'auto_consulta_ativo' && $valor === 1) {
                $intervalo = (int) $request->input('intervalo_dias_consulta', 0);
                if ($intervalo > 0) {
                    $dados['intervalo_dias_consulta'] = $intervalo;
                }
            }

            $configModel->salvar($dados);

            // Se auto_eventos ativado, registrar webhook na SERPRO
            if ($campo === 'auto_eventos_ativo' && $valor === 1) {
                $this->registrarWebhookSerpro($configModel);
            }

            Response::json([
                'success' => true,
                'message' => 'Configuracao atualizada com sucesso.',
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar configuracao: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lista logs de consultas SERPRO
     *
     * GET /api/multas-online/logs
     */
    public function logs(Request $request): void
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 15)));
            $filtroTipo = $request->query('tipo', '');
            $filtroPlaca = $request->query('placa', '') ?: null;
            $filtroStatus = $request->query('status', '') ?: null;

            $model = new SerproConsultaLog();
            $logs = $model->listarPaginado($page, $perPage, $filtroTipo, $filtroPlaca, $filtroStatus);
            $total = $model->contar($filtroTipo, $filtroPlaca, $filtroStatus);

            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $logs,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'totalPages' => $totalPages,
                    'hasPrev' => $page > 1,
                    'hasNext' => $page < $totalPages,
                ],
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao listar logs: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Detalhe de um log especifico (com payload completo)
     *
     * GET /api/multas-online/logs/{id}
     */
    public function logDetalhe(Request $request, int $id): void
    {
        try {
            $model = new SerproConsultaLog();
            $log = $model->buscarPorId($id);

            if (!$log) {
                Response::json(['success' => false, 'message' => 'Log nao encontrado'], 404);
                return;
            }

            Response::json(['success' => true, 'data' => $log]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar log: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // METODOS PRIVADOS
    // =========================================================================

    /**
     * Sincroniza infracoes da SERPRO com a tabela de multas local
     * Cria multas novas e atualiza existentes
     *
     * @return int Numero de multas novas criadas
     */
    private function sincronizarInfracoes(string $placa, array $infracoes): int
    {
        if (empty($infracoes)) {
            return 0;
        }

        $multaModel = new Multa();
        $veiculoModel = new Veiculo();
        $novas = 0;

        // Buscar veiculo pela placa
        $veiculo = $veiculoModel->buscarPorPlaca($_SESSION['chave'], $placa);

        foreach ($infracoes as $infracao) {
            $codigoOrgao = $infracao['codigoOrgao'] ?? '';
            $numeroAit = $infracao['numeroAit'] ?? '';
            $codigoInfracao = $infracao['codigoInfracao'] ?? '';

            if (empty($codigoOrgao) || empty($numeroAit)) {
                continue;
            }

            // Verificar se multa ja existe pelo trio de chaves SERPRO
            $existente = $multaModel->buscarPorChavesSerpro($codigoOrgao, $numeroAit, $codigoInfracao);

            if ($existente) {
                // Atualizar dados se necessario
                $multaModel->atualizarDadosSerpro($existente['id'], [
                    'serpro_sync_at' => date('Y-m-d H:i:s'),
                ]);
                continue;
            }

            // Criar nova multa
            $dadosMulta = [
                'id_veiculo' => $veiculo ? (int) $veiculo['id'] : null,
                'placa' => $placa,
                'codigo_orgao' => $codigoOrgao,
                'numero_ait' => $numeroAit,
                'codigo_infracao' => $codigoInfracao,
                'descricao' => $infracao['descricaoInfracao'] ?? $infracao['descricao'] ?? 'Infracao importada por consulta online',
                'valor' => (float) ($infracao['valorInfracao'] ?? $infracao['valor'] ?? 0),
                'valor_desconto_40' => isset($infracao['valorDesconto']) ? (float) $infracao['valorDesconto'] : null,
                'data_hora' => $infracao['dataHoraInfracao'] ?? $infracao['dataInfracao'] ?? null,
                'data_vencimento' => $infracao['dataVencimento'] ?? null,
                'local' => $infracao['localInfracao'] ?? $infracao['local'] ?? null,
                'origem' => 'serpro_consulta',
                'status_processamento' => 'novo',
                'serpro_sync_at' => date('Y-m-d H:i:s'),
            ];

            $multaModel->criarDeSerpro($dadosMulta);
            $novas++;
        }

        return $novas;
    }

    /**
     * Registra URL de webhook na SERPRO para receber eventos
     */
    private function registrarWebhookSerpro(SerproConfiguracao $configModel): void
    {
        try {
            $appUrl = env('APP_URL', '');
            if (empty($appUrl)) {
                return;
            }

            $webhookUrl = rtrim($appUrl, '/') . '/webhook/multas-online/eventos';
            $webhookSecret = env('SERPRO_WEBHOOK_SECRET', '');

            $headers = [];
            if (!empty($webhookSecret)) {
                $headers = [
                    ['chave' => 'X-Webhook-Secret', 'valor' => $webhookSecret],
                ];
            }

            $serpro = new SerproService();
            $resultado = $serpro->registrarUrlWebhook($webhookUrl, $headers);

            if ($resultado['success']) {
                $configModel->atualizarWebhookStatus(true);
            }
        } catch (\Exception $e) {
            error_log('SerproConsultaController::registrarWebhookSerpro - Erro: ' . $e->getMessage());
        }
    }
}
