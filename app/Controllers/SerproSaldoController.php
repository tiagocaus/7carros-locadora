<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\SerproSaldo;
use App\Models\SerproTransacao;
use App\Models\SerproConfiguracao;
use App\Services\SerproSaldoService;

/**
 * Controller de Saldo SERPRO eFrotas
 *
 * Gerencia saldo prepago, recargas (PIX e Stripe) e historico de transacoes.
 * O saldo e usado para pagar consultas e eventos da API SERPRO.
 */
class SerproSaldoController
{
    /**
     * Renderiza a pagina de saldo e recargas
     *
     * GET /pages/multas-online/saldo
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.multas.saldo');
        Response::html($html);
    }

    /**
     * Retorna dados de saldo, precos e resumo
     *
     * GET /api/multas-online/saldo
     */
    public function index(Request $request): void
    {
        try {
            $saldoService = new SerproSaldoService();
            $saldoModel = new SerproSaldo();
            $transacaoModel = new SerproTransacao();

            // Garante que registro de saldo existe
            $saldoService->inicializarSaldo();

            $saldoInfo = $saldoModel->buscarPorChave();
            $resumoGastos = $transacaoModel->resumoGastos();

            Response::json([
                'success' => true,
                'data' => [
                    'saldo' => (float) ($saldoInfo['saldo'] ?? 0),
                    'auto_recarga_ativo' => (int) ($saldoInfo['auto_recarga_ativo'] ?? 0),
                    'auto_recarga_valor' => (float) ($saldoInfo['auto_recarga_valor'] ?? 100),
                    'auto_recarga_limite' => (float) ($saldoInfo['auto_recarga_limite'] ?? 10),
                    'stripe_payment_method_id' => $saldoInfo['stripe_payment_method_id'] ?? null,
                    'precos' => [
                        'consulta' => $saldoService->getPrecoConsulta(),
                        'evento' => $saldoService->getPrecoEvento(),
                        'markup' => $saldoService->getMarkupPercent(),
                    ],
                    'resumo' => [
                        'total_consultas' => (int) ($resumoGastos['total_consultas'] ?? 0),
                        'total_eventos' => (int) ($resumoGastos['total_eventos'] ?? 0),
                        'total_gasto' => (float) ($resumoGastos['total_gasto'] ?? 0),
                        'total_recarregado' => (float) ($resumoGastos['total_recarregado'] ?? 0),
                    ],
                    'recarga_minima' => $saldoService->getRecargaMinima(),
                ],
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao carregar saldo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lista transacoes com paginacao e filtros
     *
     * GET /api/multas-online/transacoes
     * Query params: page, perPage, tipo, data_inicio, data_fim
     */
    public function transacoes(Request $request): void
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 15)));
            $filtroTipo = $request->query('tipo', '');
            $dataInicio = $request->query('data_inicio', '');
            $dataFim = $request->query('data_fim', '');

            $model = new SerproTransacao();
            $transacoes = $model->listarPaginado($page, $perPage, $filtroTipo, $dataInicio ?: null, $dataFim ?: null);
            $total = $model->contar($filtroTipo, $dataInicio ?: null, $dataFim ?: null);

            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $transacoes,
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
                'message' => 'Erro ao listar transacoes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cria recarga via PIX (Banco Inter)
     *
     * POST /multas-online/saldo/recarregar-pix
     * Body: valor
     */
    public function recarregarPix(Request $request): void
    {
        try {
            $valor = (float) $request->input('valor', 0);
            $saldoService = new SerproSaldoService();

            // Validacao
            $recargaMinima = $saldoService->getRecargaMinima();
            if ($valor < $recargaMinima) {
                Response::json([
                    'success' => false,
                    'message' => 'Valor minimo de recarga: R$ ' . number_format($recargaMinima, 2, ',', '.'),
                ], 422);
                return;
            }

            // Buscar credenciais Inter do ENV (conta da 7Carros)
            $clientId = env('INTER_CLIENT_ID', '');
            $clientSecret = env('INTER_CLIENT_SECRET', '');
            $certPath = env('INTER_CERT_PATH', '');
            $keyPath = env('INTER_KEY_PATH', '');
            $pixKey = env('INTER_PIX_KEY', '');
            $contaCorrente = env('INTER_CONTA_CORRENTE', '');

            // Converter caminhos relativos para absolutos
            if ($certPath && !str_starts_with($certPath, '/')) {
                $certPath = APP_ROOT . '/' . $certPath;
            }
            if ($keyPath && !str_starts_with($keyPath, '/')) {
                $keyPath = APP_ROOT . '/' . $keyPath;
            }

            if (empty($clientId) || empty($clientSecret) || empty($certPath)) {
                Response::json([
                    'success' => false,
                    'message' => 'Recarga PIX nao configurada. Contate o suporte.',
                ], 503);
                return;
            }

            // Gerar cobranca PIX via Inter
            $isSandbox = env('INTER_AMBIENTE', 'sandbox') === 'sandbox';
            $interGateway = new \App\Services\Gateways\InterGateway(
                [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'certificate_path' => $certPath,
                    'private_key_path' => $keyPath,
                    'pix_key' => $pixKey,
                    'conta_corrente' => $contaCorrente,
                ],
                $isSandbox
            );

            // Buscar dados da empresa (pagador) para a cobrança
            $matrizFilial = new \App\Models\MatrizFilial();
            $empresa = $matrizFilial->buscarDadosEmpresa();

            $resultado = $interGateway->createCharge([
                'value' => $valor,
                'billing_type' => 'PIX',
                'description' => 'Recarga de saldo - 7Carros',
                'customer_name' => $empresa['razao_social'] ?? Auth::user()['nome'] ?? 'Cliente',
                'customer_document' => $empresa['cpf_cnpj'] ?? '',
                'customer_email' => $empresa['email'] ?? '',
                'customer_address' => $empresa['rua'] ?? '',
                'customer_city' => $empresa['cidade'] ?? '',
                'customer_state' => $empresa['estado'] ?? '',
                'customer_zip' => isset($empresa['cep']) ? preg_replace('/\D/', '', $empresa['cep']) : '',
                'chave' => $_SESSION['chave'],
            ]);

            if (empty($resultado['success']) || !$resultado['success']) {
                $httpCode = $resultado['http_code'] ?? 500;
                Response::json([
                    'success' => false,
                    'message' => $resultado['message'] ?? 'Erro ao gerar PIX',
                ], $httpCode ?: 500);
                return;
            }

            // Registrar transacao de recarga pendente
            $transacaoModel = new SerproTransacao();
            $transacaoId = $transacaoModel->criarRecarga(
                'recarga_pix',
                $valor,
                'Recarga via PIX',
                $resultado['external_id'] ?? null,
                'pix',
                null,
                $resultado['pix_code'] ?? null,
                $resultado['pix_qrcode'] ?? null
            );

            Response::json([
                'success' => true,
                'data' => [
                    'transacao_id' => $transacaoId,
                    'pix_code' => $resultado['pix_code'] ?? null,
                    'pix_qrcode' => $resultado['pix_qrcode'] ?? null,
                    'external_id' => $resultado['external_id'] ?? null,
                    'valor' => $valor,
                ],
                'message' => 'PIX gerado com sucesso. Escaneie o QR Code para pagar.',
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao gerar recarga PIX: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cria recarga via Stripe (Cartao de credito)
     *
     * POST /multas-online/saldo/recarregar-stripe
     * Body: valor, payment_method_id (opcional, usa salvo se omitido)
     */
    public function recarregarStripe(Request $request): void
    {
        try {
            $valor = (float) $request->input('valor', 0);
            $paymentMethodId = $request->input('payment_method_id', '');
            $salvarCartao = (bool) $request->input('salvar_cartao', false);
            $saldoService = new SerproSaldoService();

            // Validacao
            $recargaMinima = $saldoService->getRecargaMinima();
            if ($valor < $recargaMinima) {
                Response::json([
                    'success' => false,
                    'message' => 'Valor minimo de recarga: R$ ' . number_format($recargaMinima, 2, ',', '.'),
                ], 422);
                return;
            }

            $stripeSecretKey = env('STRIPE_SECRET_KEY', '');
            if (empty($stripeSecretKey)) {
                Response::json([
                    'success' => false,
                    'message' => 'Recarga via cartao nao configurada. Contate o suporte.',
                ], 503);
                return;
            }

            $stripe = new \Stripe\StripeClient($stripeSecretKey);
            $saldoModel = new SerproSaldo();
            $saldoInfo = $saldoModel->buscarPorChave();

            // Obter ou criar Stripe Customer
            $customerId = $saldoInfo['stripe_customer_id'] ?? null;

            if (empty($customerId)) {
                $customer = $stripe->customers->create([
                    'metadata' => [
                        'chave' => $_SESSION['chave'],
                        'sistema' => '7carros_serpro',
                    ],
                ]);
                $customerId = $customer->id;
                $saldoModel->atualizarAutoRecarga(['stripe_customer_id' => $customerId]);
            }

            // Se nao tem payment_method_id, precisa do client_secret para coleta no frontend
            if (empty($paymentMethodId)) {
                $paymentIntent = $stripe->paymentIntents->create([
                    'amount' => (int) ($valor * 100),
                    'currency' => 'brl',
                    'customer' => $customerId,
                    'setup_future_usage' => $salvarCartao ? 'off_session' : null,
                    'description' => 'Recarga de saldo - 7Carros',
                    'metadata' => [
                        'tipo' => 'recarga_serpro',
                        'chave' => $_SESSION['chave'],
                    ],
                ]);

                // Registrar transacao pendente
                $transacaoModel = new SerproTransacao();
                $transacaoId = $transacaoModel->criarRecarga(
                    'recarga_cartao',
                    $valor,
                    'Recarga via cartao de credito',
                    $paymentIntent->id
                );

                Response::json([
                    'success' => true,
                    'data' => [
                        'transacao_id' => $transacaoId,
                        'client_secret' => $paymentIntent->client_secret,
                        'payment_intent_id' => $paymentIntent->id,
                        'requires_action' => true,
                    ],
                    'message' => 'Complete o pagamento no formulario.',
                ]);
                return;
            }

            // Cobranca direta com payment_method_id (off_session ou on_session)
            $paymentIntent = $stripe->paymentIntents->create([
                'amount' => (int) ($valor * 100),
                'currency' => 'brl',
                'customer' => $customerId,
                'payment_method' => $paymentMethodId,
                'confirm' => true,
                'off_session' => true,
                'description' => 'Recarga de saldo - 7Carros',
                'metadata' => [
                    'tipo' => 'recarga_serpro',
                    'chave' => $_SESSION['chave'],
                ],
            ]);

            // Registrar transacao
            $transacaoModel = new SerproTransacao();
            $transacaoId = $transacaoModel->criarRecarga(
                'recarga_cartao',
                $valor,
                'Recarga via cartao de credito',
                $paymentIntent->id,
                'credit_card'
            );

            // Se pagamento foi confirmado imediatamente
            if ($paymentIntent->status === 'succeeded') {
                $saldoService->confirmarRecarga($transacaoId);

                // Salvar cartao se solicitado
                if ($salvarCartao) {
                    $saldoModel->atualizarAutoRecarga([
                        'stripe_payment_method_id' => $paymentMethodId,
                    ]);
                }

                Response::json([
                    'success' => true,
                    'data' => [
                        'transacao_id' => $transacaoId,
                        'requires_action' => false,
                        'saldo_novo' => $saldoService->getSaldo(),
                    ],
                    'message' => 'Recarga de R$ ' . number_format($valor, 2, ',', '.') . ' confirmada!',
                ]);
                return;
            }

            Response::json([
                'success' => true,
                'data' => [
                    'transacao_id' => $transacaoId,
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                    'requires_action' => true,
                ],
                'message' => 'Acao adicional necessaria para completar o pagamento.',
            ]);
        } catch (\Stripe\Exception\CardException $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro no cartao: ' . $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao processar recarga: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirma pagamento Stripe no frontend (apos 3D Secure)
     *
     * POST /multas-online/saldo/confirmar-stripe
     * Body: payment_intent_id
     */
    public function confirmarStripe(Request $request): void
    {
        try {
            $paymentIntentId = $request->input('payment_intent_id', '');

            if (empty($paymentIntentId)) {
                Response::json([
                    'success' => false,
                    'message' => 'Payment intent ID obrigatorio',
                ], 422);
                return;
            }

            $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET_KEY'));
            $paymentIntent = $stripe->paymentIntents->retrieve($paymentIntentId);

            if ($paymentIntent->status !== 'succeeded') {
                Response::json([
                    'success' => false,
                    'message' => 'Pagamento ainda nao confirmado. Status: ' . $paymentIntent->status,
                ], 400);
                return;
            }

            $saldoService = new SerproSaldoService();
            $resultado = $saldoService->confirmarRecargaPorExternalId($paymentIntentId);

            if ($resultado === null) {
                Response::json([
                    'success' => true,
                    'message' => 'Recarga ja processada anteriormente.',
                    'data' => ['saldo_novo' => $saldoService->getSaldo()],
                ]);
                return;
            }

            Response::json([
                'success' => true,
                'message' => 'Recarga confirmada com sucesso!',
                'data' => [
                    'saldo_anterior' => $resultado['saldo_anterior'],
                    'saldo_novo' => $resultado['saldo_posterior'],
                ],
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao confirmar pagamento: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Atualiza configuracoes de auto-recarga
     *
     * POST /multas-online/saldo/auto-recarga
     * Body: auto_recarga_ativo, auto_recarga_valor, auto_recarga_limite, stripe_payment_method_id
     */
    public function atualizarAutoRecarga(Request $request): void
    {
        try {
            $saldoModel = new SerproSaldo();
            $saldoService = new SerproSaldoService();
            $saldoService->inicializarSaldo();

            $dados = [];

            if ($request->has('auto_recarga_ativo')) {
                $dados['auto_recarga_ativo'] = (int) $request->input('auto_recarga_ativo');
            }
            if ($request->has('auto_recarga_valor')) {
                $valor = (float) $request->input('auto_recarga_valor');
                if ($valor < $saldoService->getRecargaMinima()) {
                    Response::json([
                        'success' => false,
                        'message' => 'Valor minimo de auto-recarga: R$ ' . number_format($saldoService->getRecargaMinima(), 2, ',', '.'),
                    ], 422);
                    return;
                }
                $dados['auto_recarga_valor'] = $valor;
            }
            if ($request->has('auto_recarga_limite')) {
                $dados['auto_recarga_limite'] = max(1.00, (float) $request->input('auto_recarga_limite'));
            }
            if ($request->has('stripe_payment_method_id')) {
                $dados['stripe_payment_method_id'] = $request->input('stripe_payment_method_id');
            }

            $saldoModel->atualizarAutoRecarga($dados);

            Response::json([
                'success' => true,
                'message' => 'Configuracoes de auto-recarga atualizadas.',
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar auto-recarga: ' . $e->getMessage(),
            ], 500);
        }
    }
}
