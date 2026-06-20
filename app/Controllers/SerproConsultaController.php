<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Multa;
use App\Models\MatrizFilial;
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
     * Tipos de evento SERPRO suportados pela Central de Multas.
     *
     * A ativacao por cliente continua vindo de serpro_configuracoes.auto_eventos_ativo.
     */
    private const TIPOS_EVENTOS_MULTAS_SERPRO = [1];

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
                $estorno = $this->estornarDebitoConsulta($saldoService, $debito, $resultado);

                Response::json([
                    'success' => false,
                    'message' => $resultado['error'] ?? 'Erro ao consultar infracoes online',
                    'saldo_posterior' => $estorno['saldo_posterior'] ?? $saldoService->getSaldo(),
                    'debito_estornado' => $estorno['success'],
                ], $this->statusHttpConsultaOnline($resultado));
                return;
            }

            // Sincronizar multas no sistema
            $infracoes = $resultado['data'] ?? [];
            $multasSincronizadas = $this->sincronizarInfracoes($placa, $infracoes);

            Response::json([
                'success' => true,
                'data' => [
                    'infracoes' => $infracoes,
                    'multas_sincronizadas' => $multasSincronizadas,
                    'total_multas' => count($infracoes),
                    'novas' => $multasSincronizadas,
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
                    $debito = $saldoService->debitarConsulta("Consulta lote placa {$placa}", $placa);

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
                        $estorno = $this->estornarDebitoConsulta($saldoService, $debito, $resultado);
                        $erros++;
                        $resultados[] = [
                            'placa' => $placa,
                            'success' => false,
                            'error' => $resultado['error'],
                            'debito_estornado' => $estorno['success'],
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
                    'total_consultadas' => $totalVeiculos,
                    'total_novas_multas' => $totalInfracoes,
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
     * Consulta dados cadastrais de um veiculo por placa.
     *
     * GET /api/multas-online/veiculo/{placa}
     */
    public function dadosVeiculo(Request $request, string $placa): void
    {
        try {
            if (!Auth::can('veiculos.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para visualizar veiculos',
                ], 403);
                return;
            }

            $placa = $this->normalizarPlaca($placa);
            if ($placa === '') {
                Response::json(['success' => false, 'message' => 'Placa obrigatoria'], 422);
                return;
            }

            $saldoService = new SerproSaldoService();
            $preco = $saldoService->getPrecoConsultaDadosVeiculo();
            if (!$saldoService->temSaldoParaConsultaDadosVeiculo()) {
                Response::json([
                    'success' => false,
                    'message' => 'Saldo insuficiente para consultar dados do veiculo. Necessario: R$ ' . number_format($preco, 2, ',', '.') . '. Saldo atual: R$ ' . number_format($saldoService->getSaldo(), 2, ',', '.'),
                    'saldo_insuficiente' => true,
                ], 402);
                return;
            }

            $serpro = new SerproService();
            $resultado = $serpro->consultarVeiculoPorPlaca($placa);

            if (!$resultado['success']) {
                Response::json([
                    'success' => false,
                    'message' => 'Nao foi possivel consultar os dados do veiculo agora. Tente novamente em alguns instantes.',
                ]);
                return;
            }

            $debito = $saldoService->debitarConsultaDadosVeiculo("Consulta dados veiculo placa {$placa}", $placa);

            Response::json([
                'success' => true,
                'data' => [
                    'veiculo' => $resultado['data'],
                    'saldo_posterior' => $debito['saldo_posterior'],
                ],
                'message' => 'Dados do veiculo consultados com sucesso.',
            ]);
        } catch (\RuntimeException $e) {
            $statusCode = str_contains($e->getMessage(), 'Saldo insuficiente') ? 402 : 500;
            $message = $statusCode === 402
                ? $e->getMessage()
                : 'Nao foi possivel consultar os dados do veiculo agora. Tente novamente em alguns instantes.';

            Response::json([
                'success' => false,
                'message' => $message,
                'saldo_insuficiente' => $statusCode === 402,
            ], $statusCode);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Nao foi possivel consultar os dados do veiculo agora. Tente novamente em alguns instantes.',
            ], 500);
        }
    }

    /**
     * Consulta CRLV de um veiculo por placa.
     *
     * GET /api/multas-online/crlv/{placa}
     */
    public function crlv(Request $request, string $placa): void
    {
        try {
            if (!Auth::can('veiculos.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para visualizar veiculos',
                ], 403);
                return;
            }

            $placa = $this->normalizarPlaca($placa);
            if ($placa === '') {
                Response::json(['success' => false, 'message' => 'Placa obrigatoria'], 422);
                return;
            }

            $saldoService = new SerproSaldoService();
            $preco = $saldoService->getPrecoConsultaCrlv();
            if (!$saldoService->temSaldoParaConsultaCrlv()) {
                Response::json([
                    'success' => false,
                    'message' => 'Saldo insuficiente para consultar CRLV. Necessario: R$ ' . number_format($preco, 2, ',', '.') . '. Saldo atual: R$ ' . number_format($saldoService->getSaldo(), 2, ',', '.'),
                    'saldo_insuficiente' => true,
                ], 402);
                return;
            }

            $serpro = new SerproService();
            $resultado = $serpro->consultarCRLV($placa);

            if (!$resultado['success']) {
                $mensagem = (int) ($resultado['status'] ?? 0) === 404
                    ? 'CRLV nao encontrado para esta placa. Confira a placa e tente novamente.'
                    : 'Nao foi possivel consultar o CRLV agora. Tente novamente em alguns instantes.';

                Response::json([
                    'success' => false,
                    'message' => $mensagem,
                ]);
                return;
            }

            $pdfBase64 = $this->extrairPdfBase64($resultado['data']);
            if (!$pdfBase64) {
                Response::json([
                    'success' => false,
                    'message' => 'A Consulta Online retornou a consulta, mas nao enviou o PDF do CRLV.',
                ]);
                return;
            }

            $debito = $saldoService->debitarConsultaCrlv("Consulta CRLV placa {$placa}", $placa);

            Response::json([
                'success' => true,
                'data' => [
                    'placa' => $placa,
                    'pdf_base64' => $pdfBase64,
                    'saldo_posterior' => $debito['saldo_posterior'],
                ],
                'message' => 'CRLV consultado com sucesso.',
            ]);
        } catch (\RuntimeException $e) {
            $statusCode = str_contains($e->getMessage(), 'Saldo insuficiente') ? 402 : 500;
            $message = $statusCode === 402
                ? $e->getMessage()
                : 'Nao foi possivel consultar o CRLV agora. Tente novamente em alguns instantes.';

            Response::json([
                'success' => false,
                'message' => $message,
                'saldo_insuficiente' => $statusCode === 402,
            ], $statusCode);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Nao foi possivel consultar o CRLV agora. Tente novamente em alguns instantes.',
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

            if ($dados['auto_eventos_ativo']) {
                $resultadoEventos = $this->configurarEventosSerpro($configModel);
                if (!$resultadoEventos['success']) {
                    $configModel->salvar([
                        'auto_eventos_ativo' => 0,
                    ]);

                    Response::json([
                        'success' => false,
                        'message' => $resultadoEventos['message'],
                    ], 502);
                    return;
                }
            }

            Response::json([
                'success' => true,
                'message' => 'Configuracao salva com sucesso.',
            ]);
        } catch (\Throwable $e) {
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
                if ($valor === 1) {
                    $resultadoConfig = $this->criarConfiguracaoInicialConsultaOnline($configModel);
                    if (!$resultadoConfig['success']) {
                        Response::json([
                            'success' => false,
                            'message' => $resultadoConfig['message'],
                            'requires_setup' => true,
                        ], 422);
                        return;
                    }
                } elseif ($valor === 0) {
                    Response::json([
                        'success' => true,
                        'message' => 'Automacao ja estava desativada.',
                    ]);
                    return;
                } else {
                    Response::json([
                        'success' => false,
                        'message' => 'Configuracao de consulta online nao encontrada. Configure o CNPJ da empresa primeiro.',
                        'requires_setup' => true,
                    ], 422);
                    return;
                }
            } elseif ($valor === 1 && !$this->cnpjSerproValido((string) ($config['cnpj_empresa'] ?? ''))) {
                $resultadoConfig = $this->preencherCnpjConsultaOnline($configModel);
                if (!$resultadoConfig['success']) {
                    Response::json([
                        'success' => false,
                        'message' => $resultadoConfig['message'],
                        'requires_setup' => true,
                    ], 422);
                    return;
                }
            }

            $dados = [$campo => $valor];

            if ($campo === 'auto_consulta_ativo' && $valor === 1) {
                $intervalo = (int) $request->input('intervalo_dias_consulta', 0);
                if ($intervalo > 0) {
                    $dados['intervalo_dias_consulta'] = $intervalo;
                }
            }

            $configModel->salvar($dados);

            if ($campo === 'auto_eventos_ativo' && $valor === 1) {
                $resultadoEventos = $this->configurarEventosSerpro($configModel);
                if (!$resultadoEventos['success']) {
                    $configModel->salvar([
                        'auto_eventos_ativo' => 0,
                    ]);

                    Response::json([
                        'success' => false,
                        'message' => $resultadoEventos['message'],
                    ], 502);
                    return;
                }
            }

            if ($campo === 'auto_eventos_ativo' && $valor === 0) {
                $resultadoEventos = $this->desativarEventosSerpro();
                if (!$resultadoEventos['success']) {
                    $configModel->salvar(['auto_eventos_ativo' => 1]);

                    Response::json([
                        'success' => false,
                        'message' => $resultadoEventos['message'],
                    ], 502);
                    return;
                }
            }

            Response::json([
                'success' => true,
                'message' => 'Configuracao atualizada com sucesso.',
            ]);
        } catch (\Throwable $e) {
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

    private function normalizarPlaca(string $placa): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($placa)) ?? '');
    }

    private function extrairPdfBase64(mixed $data): ?string
    {
        if (!is_array($data)) {
            return null;
        }

        foreach (['pdf_base64', 'pdfBase64', 'pdf', 'documento', 'raw'] as $key) {
            if (!empty($data[$key]) && is_string($data[$key])) {
                return $this->limparDataUriBase64($data[$key]);
            }
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $pdf = $this->extrairPdfBase64($value);
                if ($pdf !== null) {
                    return $pdf;
                }
            }
        }

        return null;
    }

    private function limparDataUriBase64(string $valor): string
    {
        if (str_contains($valor, ',')) {
            [$prefixo, $conteudo] = explode(',', $valor, 2);
            if (str_contains($prefixo, 'base64')) {
                return $conteudo;
            }
        }

        return $valor;
    }

    private function statusHttpConsultaOnline(array $resultado): int
    {
        $status = (int) ($resultado['status'] ?? 0);

        return match ($status) {
            401, 403 => 403,
            404 => 404,
            429 => 429,
            default => 502,
        };
    }

    private function estornarDebitoConsulta(SerproSaldoService $saldoService, array $debito, array $resultado): array
    {
        $transacaoId = (int) ($debito['transacao_id'] ?? 0);

        if ($transacaoId <= 0) {
            return [
                'success' => false,
                'saldo_posterior' => $saldoService->getSaldo(),
            ];
        }

        try {
            $estorno = $saldoService->estornarDebito($transacaoId);

            return [
                'success' => true,
                'saldo_posterior' => $estorno['saldo_posterior'] ?? $saldoService->getSaldo(),
            ];
        } catch (\Throwable $e) {
            error_log(sprintf(
                'SerproConsultaController::estornarDebitoConsulta - transacao=%d status_serpro=%s erro="%s" falha_estorno="%s"',
                $transacaoId,
                (string) ($resultado['status'] ?? ''),
                (string) ($resultado['error'] ?? ''),
                $e->getMessage()
            ));

            return [
                'success' => false,
                'saldo_posterior' => $saldoService->getSaldo(),
            ];
        }
    }

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
        $veiculo = $veiculoModel->buscarPorPlaca($placa);

        foreach ($infracoes as $infracao) {
            $dadosMulta = $multaModel->normalizarInfracaoSerpro(array_merge($infracao, [
                'id_veiculo' => $veiculo ? (int) $veiculo['id'] : null,
                'placa' => $placa,
                'origem' => 'serpro_consulta',
                'status_processamento' => 'novo',
                'serpro_sync_at' => date('Y-m-d H:i:s'),
            ]));

            $codigoOrgao = $dadosMulta['codigo_orgao'] ?? '';
            $numeroAit = $dadosMulta['numero_ait'] ?? '';
            $codigoInfracao = $dadosMulta['codigo_infracao'] ?? '';

            if (empty($codigoOrgao) || empty($numeroAit)) {
                continue;
            }

            // Verificar se multa ja existe pelo trio de chaves SERPRO
            $existente = $multaModel->buscarPorChavesSerpro($codigoOrgao, $numeroAit, $codigoInfracao);

            if ($existente) {
                // Atualizar dados se necessario
                $multaModel->atualizarDadosSerpro($existente['id'], $dadosMulta);
                continue;
            }

            // Criar nova multa
            $multaModel->criarDeSerpro($dadosMulta);
            $novas++;
        }

        return $novas;
    }

    /**
     * Configura URL de webhook e ativa os tipos de evento na SERPRO.
     */
    private function configurarEventosSerpro(SerproConfiguracao $configModel): array
    {
        try {
            $appUrl = env('APP_URL', '');
            if (empty($appUrl)) {
                return ['success' => false, 'message' => 'URL da aplicacao nao configurada para registrar eventos da Consulta Online.'];
            }

            $webhookSecret = env('SERPRO_WEBHOOK_SECRET', '');
            if (empty($webhookSecret)) {
                return ['success' => false, 'message' => 'Chave de validacao dos eventos da Consulta Online nao configurada.'];
            }

            $webhookUrl = rtrim($appUrl, '/') . '/webhook/multas-online/eventos';
            $headers = [
                'X-Webhook-Secret' => $webhookSecret,
            ];

            $serpro = new SerproService();
            $resultado = $serpro->registrarUrlWebhook($webhookUrl, $headers);

            if (!$resultado['success']) {
                $webhookJaConfigurado = $this->sincronizarWebhookJaRegistrado($serpro, $webhookUrl, $headers, $resultado);
                if (!$webhookJaConfigurado['success']) {
                    $configModel->atualizarWebhookStatus(false);
                    return [
                        'success' => false,
                        'message' => 'Erro ao registrar eventos da Consulta Online: ' . ($webhookJaConfigurado['message'] ?? ($resultado['error'] ?? 'erro desconhecido')),
                    ];
                }
            }

            $configModel->atualizarWebhookStatus(true);

            foreach ($this->tiposEventosSerpro() as $tipoEvento) {
                $resultadoEvento = $serpro->ativarEvento($tipoEvento, true);
                if (!$resultadoEvento['success']) {
                    return [
                        'success' => false,
                        'message' => "Webhook registrado, mas erro ao ativar evento da Consulta Online {$tipoEvento}: " . ($resultadoEvento['error'] ?? 'erro desconhecido'),
                    ];
                }
            }

            return ['success' => true, 'message' => 'Eventos da Consulta Online configurados com sucesso.'];
        } catch (\Throwable $e) {
            error_log('SerproConsultaController::registrarWebhookSerpro - Erro: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao configurar eventos da Consulta Online: ' . $e->getMessage()];
        }
    }

    /**
     * Trata o cadastro de webhook como idempotente quando a Consulta Online
     * responde que o endpoint ja existe. Se a URL for a mesma mas o header
     * cadastrado estiver desatualizado, remove e recadastra.
     */
    private function sincronizarWebhookJaRegistrado(SerproService $serpro, string $webhookUrl, array $headers, array $resultadoRegistro): array
    {
        $status = (int) ($resultadoRegistro['status'] ?? 0);
        $erro = strtolower((string) ($resultadoRegistro['error'] ?? ''));
        if ($status !== 409 && !str_contains($erro, 'endpoint ja existe') && !str_contains($erro, 'endpoint já existe')) {
            return [
                'success' => false,
                'message' => $resultadoRegistro['error'] ?? 'erro desconhecido',
            ];
        }

        $endpointAtual = $serpro->consultarUrlWebhook();
        if (!$endpointAtual['success']) {
            return [
                'success' => false,
                'message' => 'endpoint ja existe, mas nao foi possivel consultar a URL cadastrada: ' . ($endpointAtual['error'] ?? 'erro desconhecido'),
            ];
        }

        $endpoints = $endpointAtual['data'] ?? [];
        if (!is_array($endpoints)) {
            return [
                'success' => false,
                'message' => 'endpoint ja existe, mas a consulta da URL cadastrada retornou formato invalido.',
            ];
        }

        if (isset($endpoints['url'])) {
            $endpoints = [$endpoints];
        }

        foreach ($endpoints as $endpoint) {
            if (!is_array($endpoint)) {
                continue;
            }

            $urlAtual = rtrim((string) ($endpoint['url'] ?? ''), '/');
            if ($urlAtual !== rtrim($webhookUrl, '/')) {
                continue;
            }

            $headerEsperado = (string) array_key_first($headers);
            $valorEsperado = (string) ($headers[$headerEsperado] ?? '');
            $headerAtual = (string) ($endpoint['header'] ?? '');
            $valorAtual = (string) ($endpoint['valor'] ?? '');

            if (strcasecmp($headerAtual, $headerEsperado) === 0 && hash_equals($valorEsperado, $valorAtual)) {
                return ['success' => true];
            }

            $remocao = $serpro->removerUrlWebhook();
            if (!$remocao['success']) {
                return [
                    'success' => false,
                    'message' => 'endpoint ja existe com header desatualizado, mas nao foi possivel remove-lo: ' . ($remocao['error'] ?? 'erro desconhecido'),
                ];
            }

            $novoRegistro = $serpro->registrarUrlWebhook($webhookUrl, $headers);
            if (!$novoRegistro['success']) {
                return [
                    'success' => false,
                    'message' => 'endpoint ja existe com header desatualizado, mas nao foi possivel recadastra-lo: ' . ($novoRegistro['error'] ?? 'erro desconhecido'),
                ];
            }

            return ['success' => true];
        }

        return [
            'success' => false,
            'message' => 'ja existe outro endpoint cadastrado na Consulta Online. Remova o endpoint atual antes de ativar os eventos.',
        ];
    }

    /**
     * Desativa os tipos de evento configurados na SERPRO.
     */
    private function desativarEventosSerpro(): array
    {
        try {
            $serpro = new SerproService();

            foreach ($this->tiposEventosSerpro() as $tipoEvento) {
                $resultadoEvento = $serpro->ativarEvento($tipoEvento, false);
                if (!$resultadoEvento['success']) {
                    return [
                        'success' => false,
                        'message' => "Erro ao desativar evento da Consulta Online {$tipoEvento}: " . ($resultadoEvento['error'] ?? 'erro desconhecido'),
                    ];
                }
            }

            return ['success' => true, 'message' => 'Eventos da Consulta Online desativados com sucesso.'];
        } catch (\Throwable $e) {
            error_log('SerproConsultaController::desativarEventosSerpro - Erro: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao desativar eventos da Consulta Online: ' . $e->getMessage()];
        }
    }

    /**
     * Cria a configuracao inicial usando um CNPJ valido do tenant.
     */
    private function criarConfiguracaoInicialConsultaOnline(SerproConfiguracao $configModel): array
    {
        $resultado = $this->resolverCnpjConsultaOnline();
        if (!$resultado['success']) {
            return $resultado;
        }

        $configModel->salvar([
            'cnpj_empresa' => $resultado['cnpj'],
            'auto_consulta_ativo' => 0,
            'intervalo_dias_consulta' => 7,
            'auto_eventos_ativo' => 0,
        ]);

        return ['success' => true];
    }

    /**
     * Preenche CNPJ ausente em configuracao existente usando um CNPJ valido do tenant.
     */
    private function preencherCnpjConsultaOnline(SerproConfiguracao $configModel): array
    {
        $resultado = $this->resolverCnpjConsultaOnline();
        if (!$resultado['success']) {
            return $resultado;
        }

        $configModel->salvar(['cnpj_empresa' => $resultado['cnpj']]);

        return ['success' => true];
    }

    /**
     * Retorna o CNPJ que deve ser usado pela Consulta Online.
     */
    private function resolverCnpjConsultaOnline(): array
    {
        $model = new MatrizFilial();
        $matriz = $model->buscarMatriz();
        $cnpjMatriz = preg_replace('/\D/', '', (string) ($matriz['cpf_cnpj'] ?? ''));

        if ($this->cnpjSerproValido($cnpjMatriz)) {
            return ['success' => true, 'cnpj' => $cnpjMatriz];
        }

        $empresas = $model->listar(null, [], 'tipo DESC, razao_social ASC');
        $cnpjsValidos = [];

        foreach ($empresas as $empresa) {
            $cnpj = preg_replace('/\D/', '', (string) ($empresa['cpf_cnpj'] ?? ''));
            if ($this->cnpjSerproValido($cnpj)) {
                $cnpjsValidos[$cnpj] = $empresa;
            }
        }

        if (count($cnpjsValidos) === 1) {
            return ['success' => true, 'cnpj' => array_key_first($cnpjsValidos)];
        }

        if (count($cnpjsValidos) > 1) {
            return [
                'success' => false,
                'message' => t('modules.multas.central.automation.online_query_multiple_cnpjs'),
            ];
        }

        return [
            'success' => false,
            'message' => t('modules.multas.central.automation.online_query_requires_cnpj'),
        ];
    }

    /**
     * Valida formato minimo de CNPJ para envio a SERPRO.
     */
    private function cnpjSerproValido(string $cnpj): bool
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        return strlen($cnpj) === 14 && !preg_match('/^(\d)\1{13}$/', $cnpj);
    }

    /**
     * Tipos de evento SERPRO que a Central de Multas deve receber.
     */
    private function tiposEventosSerpro(): array
    {
        return self::TIPOS_EVENTOS_MULTAS_SERPRO;
    }
}
