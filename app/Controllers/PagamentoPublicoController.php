<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\PagamentoLink;
use App\Models\GatewayPagamento;
use App\Models\FormaPagamento;
use App\Models\FinanceiroTransacao;
use App\Models\Financeiro;
use App\Models\ClienteCartao;
use App\Services\Gateways\GatewayFactory;

/**
 * Controller para página pública de pagamento
 *
 * Gerencia a exibição da página de pagamento e processamento
 * de cobranças sem necessidade de autenticação.
 */
class PagamentoPublicoController
{
    /**
     * Exibe a página pública de pagamento
     *
     * GET /pagar/{codigo}
     */
    public function index(Request $request, string $codigo): void
    {
        $linkModel = new PagamentoLink();
        $link = $linkModel->buscarPorCodigo($codigo);

        if (!$link) {
            $this->renderErro('Link de pagamento não encontrado', 'O link que você está tentando acessar não existe ou foi removido.');
            return;
        }

        // Verificar se expirou
        if (!empty($link['expires_at']) && strtotime($link['expires_at']) < time()) {
            $linkModel->marcarComoExpirado($link['id']);
            $this->renderErro('Link expirado', 'Este link de pagamento expirou. Solicite um novo link.');
            return;
        }

        // Verificar se já foi pago
        if ($link['status'] === 'paid') {
            $this->renderSucesso('Pagamento já realizado', 'Este link de pagamento já foi utilizado. Obrigado!');
            return;
        }

        // Verificar se foi cancelado
        if ($link['status'] === 'cancelled') {
            $this->renderErro('Link cancelado', 'Este link de pagamento foi cancelado.');
            return;
        }

        // Buscar gateways vinculados à forma de pagamento do financeiro
        $gateways = [];

        if (!empty($link['id_forma_pagamento'])) {
            $formaPagamentoModel = new FormaPagamento();
            $gatewaysVinculados = $formaPagamentoModel->buscarGateways((int) $link['id_forma_pagamento']);
            $idsVinculados = array_column($gatewaysVinculados, 'id');

            if (!empty($idsVinculados)) {
                $gatewayModel = new GatewayPagamento();
                $gateways = $gatewayModel->listarParaPagamentoPublicoPorIds($link['chave'], $idsVinculados);
            }
        }
        // Se não há forma de pagamento definida ou nenhum gateway vinculado,
        // $gateways permanece vazio - não exibe opções de pagamento online

        // Obter moeda do tenant para filtrar gateways compatíveis
        // Nota: currency_config() depende de sessão, mas esta é página pública
        $tenantCurrency = $link['tenant_currency'] ?? 'BRL';

        // Filtrar gateways por moeda e métodos habilitados
        $gatewaysPix = [];
        $gatewaysBoleto = [];
        $gatewaysCartao = [];

        foreach ($gateways as $gw) {
            // Decodificar currencies (JSON array)
            $currencies = $gw['currencies'] ?? '["BRL"]';
            if (is_string($currencies)) {
                $currencies = json_decode($currencies, true) ?: ['BRL'];
            }

            // Ignorar gateways que não suportam a moeda do tenant
            if (!in_array($tenantCurrency, $currencies, true)) {
                continue;
            }

            if ($gw['pix_enabled']) {
                $gatewaysPix[] = $gw;
            }
            if ($gw['boleto_enabled']) {
                $gatewaysBoleto[] = $gw;
            }
            if ($gw['credit_card_enabled'] || $gw['debit_card_enabled']) {
                $gatewaysCartao[] = $gw;
            }
        }

        $html = Template::render('public.pagar.index', [
            'link' => $link,
            'gateways_pix' => $gatewaysPix,
            'gateways_boleto' => $gatewaysBoleto,
            'gateways_cartao' => $gatewaysCartao,
            'codigo' => $codigo,
            'valor_formatado' => currency_format((float)$link['valor']),
        ]);

        Response::html($html);
    }

    /**
     * Processa o pagamento
     *
     * POST /pagar/{codigo}/processar
     */
    public function processar(Request $request, string $codigo): void
    {
        $linkModel = new PagamentoLink();
        $link = $linkModel->buscarPorCodigo($codigo);

        if (!$link) {
            Response::json([
                'success' => false,
                'message' => 'Link de pagamento não encontrado'
            ], 404);
            return;
        }

        // Validações
        if ($link['status'] === 'paid') {
            Response::json([
                'success' => false,
                'message' => 'Este link já foi pago'
            ], 400);
            return;
        }

        if ($link['status'] !== 'pending') {
            Response::json([
                'success' => false,
                'message' => 'Link de pagamento inválido'
            ], 400);
            return;
        }

        if (!empty($link['expires_at']) && strtotime($link['expires_at']) < time()) {
            $linkModel->marcarComoExpirado($link['id']);
            Response::json([
                'success' => false,
                'message' => 'Este link expirou'
            ], 400);
            return;
        }

        $dados = $request->all();
        $metodo = $dados['metodo'] ?? '';
        $gatewayId = (int) ($dados['gateway_id'] ?? 0);

        if (empty($metodo) || empty($gatewayId)) {
            Response::json([
                'success' => false,
                'message' => 'Método de pagamento e gateway são obrigatórios'
            ], 400);
            return;
        }

        // Buscar gateway
        $gatewayModel = new GatewayPagamento();
        $gatewayConfig = $gatewayModel->buscarPorIdComCredenciais($gatewayId);

        if (!$gatewayConfig || $gatewayConfig['chave'] !== $link['chave']) {
            Response::json([
                'success' => false,
                'message' => 'Gateway de pagamento inválido'
            ], 400);
            return;
        }

        if ($gatewayConfig['status'] !== 'A') {
            Response::json([
                'success' => false,
                'message' => 'Gateway de pagamento inativo'
            ], 400);
            return;
        }

        // Verificar se o método está habilitado no gateway
        $metodoHabilitado = match ($metodo) {
            'pix' => $gatewayConfig['pix_enabled'],
            'boleto' => $gatewayConfig['boleto_enabled'],
            'credit_card' => $gatewayConfig['credit_card_enabled'],
            'debit_card' => $gatewayConfig['debit_card_enabled'],
            default => false,
        };

        if (!$metodoHabilitado) {
            Response::json([
                'success' => false,
                'message' => 'Método de pagamento não disponível para este gateway'
            ], 400);
            return;
        }

        try {
            // Criar instância do gateway
            $gateway = GatewayFactory::create(
                $gatewayConfig['gateway_code'],
                $gatewayConfig['credentials'] ?? [],
                $gatewayConfig['ambiente'] === 'sandbox',
                $gatewayId
            );

            // Preparar dados da cobrança
            $chargeData = [
                'chave' => $link['chave'],
                'id_financeiro' => $link['id_financeiro'],
                'value' => (float) $link['valor'],
                'billing_type' => $metodo,
                'description' => $link['descricao'] ?? $link['financeiro_descricao'] ?? 'Pagamento',
                'external_reference' => "link_{$link['id']}",
                'due_date' => date('Y-m-d', strtotime('+3 days')),
            ];

            // Dados do cliente
            if (!empty($link['cliente_documento'])) {
                $chargeData['customer_document'] = $link['cliente_documento'];
                $chargeData['customer_name'] = $link['cliente_nome'] ?? 'Cliente';
                $chargeData['customer_email'] = $link['cliente_email'] ?? null;
                $chargeData['customer_phone'] = $link['cliente_telefone'] ?? null;
            }

            // Se for cartão, incluir dados do cartão
            if (in_array($metodo, ['credit_card', 'debit_card'], true)) {
                if (!empty($dados['card_token'])) {
                    $chargeData['card_token'] = $dados['card_token'];
                }
                if (!empty($dados['installments'])) {
                    $chargeData['installments'] = (int) $dados['installments'];
                }
            }

            // Criar cobrança
            $result = $gateway->createCharge($chargeData);

            if (!$result['success']) {
                Response::json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Erro ao criar cobrança'
                ], 400);
                return;
            }

            Response::json([
                'success' => true,
                'message' => 'Cobrança criada com sucesso',
                'data' => [
                    'external_id' => $result['external_id'] ?? null,
                    'status' => $result['status'] ?? 'pending',
                    'payment_url' => $result['payment_url'] ?? null,
                    'pix_code' => $result['pix_code'] ?? null,
                    'pix_qrcode' => $result['pix_qrcode'] ?? null,
                    'barcode' => $result['barcode'] ?? null,
                    'boleto_url' => $result['boleto_url'] ?? null,
                    'expires_at' => $result['expires_at'] ?? null,
                    'metodo' => $metodo,
                ]
            ]);

        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao processar pagamento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Consulta status do pagamento
     *
     * GET /pagar/{codigo}/status
     */
    public function status(Request $request, string $codigo): void
    {
        $linkModel = new PagamentoLink();
        $link = $linkModel->buscarPorCodigo($codigo);

        if (!$link) {
            Response::json([
                'success' => false,
                'message' => 'Link não encontrado'
            ], 404);
            return;
        }

        // Se já está pago, retornar imediatamente
        if ($link['status'] === 'paid') {
            Response::json([
                'success' => true,
                'data' => [
                    'status' => 'paid',
                    'message' => 'Pagamento confirmado'
                ]
            ]);
            return;
        }

        // Buscar última transação
        $transacaoModel = new FinanceiroTransacao();
        $transacao = $transacaoModel->buscarUltimaCobranca($link['id_financeiro']);

        if (!$transacao) {
            Response::json([
                'success' => true,
                'data' => [
                    'status' => 'pending',
                    'message' => 'Aguardando pagamento'
                ]
            ]);
            return;
        }

        // Se tem external_id, consultar no gateway
        if (!empty($transacao['external_id']) && !empty($transacao['id_gateway'])) {
            $gatewayModel = new GatewayPagamento();
            $gatewayConfig = $gatewayModel->buscarPorIdComCredenciais($transacao['id_gateway']);

            if ($gatewayConfig) {
                try {
                    $gateway = GatewayFactory::create(
                        $gatewayConfig['gateway_code'],
                        $gatewayConfig['credentials'] ?? [],
                        $gatewayConfig['ambiente'] === 'sandbox',
                        $transacao['id_gateway']
                    );

                    $statusResult = $gateway->getChargeStatus($transacao['external_id']);

                    if ($statusResult['success']) {
                        $status = $statusResult['status'];

                        // Atualizar transação se status mudou
                        if ($status !== $transacao['status']) {
                            $transacaoModel->atualizarPorExternalId(
                                $transacao['external_id'],
                                $status,
                                $status === 'paid' ? date('Y-m-d H:i:s') : null
                            );

                            // Se pagou, atualizar link
                            if ($status === 'paid') {
                                $linkModel->marcarComoPago(
                                    $link['id'],
                                    $transacao['id'],
                                    $_SERVER['REMOTE_ADDR'] ?? null,
                                    $_SERVER['HTTP_USER_AGENT'] ?? null
                                );

                                // Atualizar financeiro como pago
                                $this->marcarFinanceiroPago($link['id_financeiro'], $transacao['id']);

                                // Se o link esta vinculado a uma locacao (reserva com pagamento antecipado),
                                // efetiva a reserva e dispara confirmacao ao cliente
                                if (!empty($link['id_locacao'])) {
                                    $this->efetivarReservaAposPagamento((int) $link['id_locacao']);
                                }
                            }
                        }

                        Response::json([
                            'success' => true,
                            'data' => [
                                'status' => $status,
                                'message' => $this->getStatusMessage($status)
                            ]
                        ]);
                        return;
                    }
                } catch (\Exception $e) {
                    // Log error, continue with stored status
                }
            }
        }

        Response::json([
            'success' => true,
            'data' => [
                'status' => $transacao['status'] ?? 'pending',
                'message' => $this->getStatusMessage($transacao['status'] ?? 'pending')
            ]
        ]);
    }

    /**
     * Recebe webhook do gateway
     *
     * POST /webhook/{gateway_code}
     */
    public function webhook(Request $request, string $gatewayCode): void
    {
        $gatewayCode = strtolower($gatewayCode);
        $payload = $request->all();
        $headers = $this->getHeaders();

        error_log("[Webhook] Recebido gateway={$gatewayCode} keys=" . implode(',', array_keys($payload)));

        try {
            if (!GatewayFactory::exists($gatewayCode)) {
                Response::json(['error' => 'Gateway desconhecido'], 400);
                return;
            }

            // Buscar o gateway ativo pelo código (pode haver múltiplos, processar para cada um)
            $transacaoModel = new FinanceiroTransacao();
            $gatewayModel = new GatewayPagamento();

            // Primeiro, tentar identificar a transação pelo external_id
            $gatewayInstance = GatewayFactory::create($gatewayCode, [], false);
            $parsedPayload = $gatewayInstance->parseWebhookPayload($payload);

            $externalId = $parsedPayload['external_id'] ?? '';

            if (empty($externalId)) {
                error_log("[Webhook] external_id ausente gateway={$gatewayCode} event=" . ($parsedPayload['event'] ?? 'unknown'));
                Response::json(['error' => 'external_id não encontrado no payload'], 400);
                return;
            }

            // Buscar transação original
            $transacao = $transacaoModel->buscarPorExternalId($externalId);

            if (!$transacao) {
                error_log("[Webhook] Transacao nao encontrada gateway={$gatewayCode} external_id={$externalId}");
                // Transação não encontrada, mas retornar OK para não reenviar
                Response::json(['success' => true, 'message' => 'Transação não encontrada']);
                return;
            }

            // Buscar gateway com credenciais
            $gatewayConfig = null;
            if (!empty($transacao['id_gateway'])) {
                $gatewayConfig = $gatewayModel->buscarPorIdComCredenciais($transacao['id_gateway']);
            }

            // Se não encontrou pelo id_gateway, buscar pelo chave e código
            if (!$gatewayConfig) {
                $gatewayConfig = $gatewayModel->buscarPorChaveECodigo($transacao['chave'], $gatewayCode);
            }

            if (!$gatewayConfig) {
                Response::json(['error' => 'Gateway não configurado'], 400);
                return;
            }

            // Criar instância com credenciais
            $gateway = GatewayFactory::create(
                $gatewayConfig['gateway_code'],
                $gatewayConfig['credentials'] ?? [],
                $gatewayConfig['ambiente'] === 'sandbox',
                $gatewayConfig['id']
            );

            // Validar assinatura
            if (!$gateway->validateWebhookSignature($payload, $headers)) {
                Response::json(['error' => 'Assinatura inválida'], 401);
                return;
            }

            // Verificar idempotência
            $event = $parsedPayload['event'] ?? 'unknown';
            if ($transacaoModel->webhookJaProcessado($externalId, $event)) {
                Response::json(['success' => true, 'message' => 'Webhook já processado']);
                return;
            }

            // Registrar webhook
            $transacaoModel->registrarWebhook(
                $transacao['chave'],
                $gatewayCode,
                $externalId,
                $event,
                $parsedPayload['status'] ?? '',
                $payload
            );

            // Atualizar status da transação
            $newStatus = $parsedPayload['status'] ?? '';
            $paidAt = null;

            if ($newStatus === 'paid') {
                $paidAt = $parsedPayload['paid_at'] ?? date('Y-m-d H:i:s');
            }

            $transacaoModel->atualizarPorExternalId($externalId, $newStatus, $paidAt);

            // Se foi pago, atualizar link e financeiro
            if ($newStatus === 'paid' && !empty($transacao['id_financeiro'])) {
                // Buscar link associado ao financeiro
                $linkModel = new PagamentoLink();
                $link = $linkModel->buscarPorFinanceiro($transacao['id_financeiro']);

                if ($link && $link['status'] === 'pending') {
                    $linkModel->marcarComoPago(
                        $link['id'],
                        $transacao['id'],
                        null,
                        null
                    );
                }

                // Marcar financeiro como pago
                $this->marcarFinanceiroPago($transacao['id_financeiro'], $transacao['id']);

                // Se o link esta vinculado a uma locacao (reserva com pagamento antecipado),
                // efetiva a reserva e dispara confirmacao ao cliente
                if ($link && !empty($link['id_locacao'])) {
                    $this->efetivarReservaAposPagamento((int) $link['id_locacao']);
                }
            }

            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            error_log("[Webhook] Erro gateway={$gatewayCode}: " . $e->getMessage());
            Response::json(['success' => false, 'message' => 'Erro ao processar webhook'], 500);
        }
    }

    public function webhookAsaas(Request $request): void
    {
        $this->webhook($request, 'asaas');
    }

    public function webhookStripe(Request $request): void
    {
        $this->webhook($request, 'stripe');
    }

    public function webhookSquare(Request $request): void
    {
        $this->webhook($request, 'square');
    }

    public function webhookCora(Request $request): void
    {
        $this->webhook($request, 'cora');
    }

    public function webhookEfipay(Request $request): void
    {
        $this->webhook($request, 'efipay');
    }

    public function webhookInter(Request $request): void
    {
        $this->webhook($request, 'inter');
    }

    public function webhookBradesco(Request $request): void
    {
        $this->webhook($request, 'bradesco');
    }

    public function webhookItau(Request $request): void
    {
        $this->webhook($request, 'itau');
    }

    public function webhookBancard(Request $request): void
    {
        $this->webhook($request, 'bancard');
    }

    public function webhookPagopar(Request $request): void
    {
        $this->webhook($request, 'pagopar');
    }

    /**
     * Retorna headers da requisição
     */
    private function getHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headers[$headerName] = $value;
            }
        }

        // Adicionar headers comuns
        foreach (['CONTENT_TYPE', 'CONTENT_LENGTH'] as $key) {
            if (isset($_SERVER[$key])) {
                $headers[$key] = $_SERVER[$key];
            }
        }

        return $headers;
    }

    /**
     * Marca financeiro como pago
     */
    private function marcarFinanceiroPago(int $idFinanceiro, int $idTransacao): void
    {
        try {
            $financeiroModel = new Financeiro();
            $financeiro = $financeiroModel->buscarPorId($idFinanceiro);

            if (!$financeiro) {
                error_log("[Webhook] Financeiro {$idFinanceiro} nao encontrado");
                return;
            }

            // Garantir contexto de sessao para o QueryBuilder (webhook nao tem sessao)
            if (empty($_SESSION['chave'])) {
                $_SESSION['chave'] = $financeiro['chave'];
            }

            $financeiroModel->atualizar($idFinanceiro, ['pago' => 'S']);

            // Hook: Gerar comissao de investidor
            try {
                $comissaoService = new \App\Services\ComissaoInvestidorService();
                $comissaoService->processarComissaoPorFinanceiro($idFinanceiro);
            } catch (\Exception $e) {
                error_log("[Webhook/Comissao] Erro: " . $e->getMessage());
            }
        } catch (\Exception $e) {
            error_log("[Webhook] Erro ao atualizar financeiro {$idFinanceiro}: " . $e->getMessage());
        }
    }

    /**
     * Efetiva a reserva apos pagamento confirmado: muda status de 'P' para 'R'
     * e dispara o template `confirmacao_reserva` em email/whatsapp/sms ao cliente.
     *
     * Idempotente: se a locacao ja estiver em 'R' nao dispara emails novamente
     * (evita double-send em caso de multiplos webhooks).
     */
    private function efetivarReservaAposPagamento(int $idLocacao): void
    {
        try {
            $locacaoModel = new \App\Models\Locacao();

            // Busca locacao com sessao ja configurada (marcarFinanceiroPago definiu $_SESSION['chave'])
            $locacao = $locacaoModel->buscarPorId($idLocacao);
            if (!$locacao) {
                error_log("[Webhook/Reserva] Locacao {$idLocacao} nao encontrada");
                return;
            }

            // So efetiva se ainda estava pendente (P). Evita reenviar email se ja estava R.
            if ($locacao['status'] === 'R') {
                return;
            }

            $locacaoModel->atualizar($idLocacao, ['status' => 'R']);

            $chave = $locacao['chave'];

            // Monta contexto do template (apenas nome principal; MessageTemplateService enriquece empresa.*)
            $nomeCliente = $locacao['cliente_nome'] ?? '';
            $primeiroNome = explode(' ', trim($nomeCliente))[0] ?? '';

            $emailCliente = '';
            $telCliente = '';
            if (!empty($locacao['id_cliente'])) {
                try {
                    $em = (new \App\Models\ContatoEmail())->getPrincipal('cliente', (int) $locacao['id_cliente']);
                    $emailCliente = $em['email'] ?? '';
                    $tel = (new \App\Models\ContatoTelefone())->getPrincipal('cliente', (int) $locacao['id_cliente']);
                    $telCliente = $tel['telefone'] ?? '';
                } catch (\Throwable $e) { /* ignore */ }
            }

            $context = [
                'cliente' => [
                    'nome'          => $nomeCliente,
                    'primeiro_nome' => $primeiroNome,
                    'email'         => $emailCliente,
                    'telefone'      => $telCliente,
                    'celular'       => $telCliente,
                ],
                'locacao' => [
                    'numero'          => $locacao['codigo'] ?? '',
                    'data_retirada'   => !empty($locacao['data_saida']) ? date('d/m/Y', strtotime($locacao['data_saida'])) : '',
                    'hora_retirada'   => !empty($locacao['data_saida']) ? date('H:i', strtotime($locacao['data_saida'])) : '',
                    'data_devolucao'  => !empty($locacao['data_prevista']) ? date('d/m/Y', strtotime($locacao['data_prevista'])) : '',
                    'hora_devolucao'  => !empty($locacao['data_prevista']) ? date('H:i', strtotime($locacao['data_prevista'])) : '',
                    'quantidade_dias' => (int) ($locacao['dias'] ?? 0),
                    'valor_total'     => (float) ($locacao['total_pagar'] ?? $locacao['total_fatura'] ?? 0),
                ],
                'outros' => [
                    'data_atual' => date('d/m/Y'),
                ],
            ];

            if (function_exists('queue_template_message')) {
                queue_template_message('confirmacao_reserva', 'email', $context, $chave);
                queue_template_message('confirmacao_reserva', 'whatsapp', $context, $chave);
                queue_template_message('confirmacao_reserva', 'sms', $context, $chave);
            }
        } catch (\Throwable $e) {
            error_log("[Webhook/Reserva] Erro ao efetivar locacao {$idLocacao}: " . $e->getMessage());
        }
    }

    /**
     * Retorna mensagem para status
     */
    private function getStatusMessage(string $status): string
    {
        return match ($status) {
            'paid' => 'Pagamento confirmado',
            'pending' => 'Aguardando pagamento',
            'processing' => 'Processando pagamento',
            'refunded' => 'Pagamento estornado',
            'cancelled' => 'Pagamento cancelado',
            'failed' => 'Pagamento falhou',
            default => 'Status desconhecido',
        };
    }

    /**
     * Renderiza página de erro
     */
    private function renderErro(string $titulo, string $mensagem): void
    {
        $html = Template::render('public.pagar.erro', [
            'titulo' => $titulo,
            'mensagem' => $mensagem,
        ]);
        Response::html($html);
    }

    /**
     * Renderiza página de sucesso
     */
    private function renderSucesso(string $titulo, string $mensagem): void
    {
        $html = Template::render('public.pagar.sucesso', [
            'titulo' => $titulo,
            'mensagem' => $mensagem,
        ]);
        Response::html($html);
    }

    /**
     * Retorna capacidades do gateway
     *
     * GET /pagar/{codigo}/gateway/{gatewayId}/capabilities
     */
    public function gatewayCapabilities(Request $request, string $codigo, int $gatewayId): void
    {
        $linkModel = new PagamentoLink();
        $link = $linkModel->buscarPorCodigo($codigo);

        if (!$link) {
            Response::json(['success' => false, 'message' => 'Link não encontrado'], 404);
            return;
        }

        $gatewayModel = new GatewayPagamento();
        $gatewayConfig = $gatewayModel->buscarPorIdComCredenciais($gatewayId);

        if (!$gatewayConfig || $gatewayConfig['chave'] !== $link['chave']) {
            Response::json(['success' => false, 'message' => 'Gateway inválido'], 400);
            return;
        }

        try {
            $gateway = GatewayFactory::create(
                $gatewayConfig['gateway_code'],
                $gatewayConfig['credentials'] ?? [],
                $gatewayConfig['ambiente'] === 'sandbox',
                $gatewayId
            );

            $data = [
                'supports_transparent' => $gateway->supportsTransparentCheckout(),
                'supports_storage' => $gateway->supportsCardStorage(),
                'gateway_code' => $gatewayConfig['gateway_code'],
            ];

            // Para Stripe, incluir publishable_key para tokenização no frontend
            if ($gatewayConfig['gateway_code'] === 'stripe' && method_exists($gateway, 'getPublishableKey')) {
                $data['publishable_key'] = $gateway->getPublishableKey();
            }

            Response::json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao consultar gateway'], 500);
        }
    }

    /**
     * Lista cartões salvos do cliente
     *
     * GET /pagar/{codigo}/cartoes
     */
    public function listarCartoes(Request $request, string $codigo): void
    {
        $linkModel = new PagamentoLink();
        $link = $linkModel->buscarPorCodigo($codigo);

        if (!$link) {
            Response::json(['success' => false, 'message' => 'Link não encontrado'], 404);
            return;
        }

        // Verificar se o link tem cliente associado
        if (empty($link['id_cliente'])) {
            Response::json(['success' => true, 'data' => []]);
            return;
        }

        $gatewayId = (int) ($request->query('gateway_id') ?? 0);
        $gatewayCode = null;

        if ($gatewayId > 0) {
            $gatewayModel = new GatewayPagamento();
            $gatewayConfig = $gatewayModel->buscarPorIdComCredenciais($gatewayId);
            if ($gatewayConfig && $gatewayConfig['chave'] === $link['chave']) {
                $gatewayCode = $gatewayConfig['gateway_code'];
            }
        }

        $cartaoModel = new ClienteCartao();
        $cartoes = $cartaoModel->listarPorCliente($link['id_cliente'], $gatewayCode);

        Response::json([
            'success' => true,
            'data' => $cartoes
        ]);
    }

    /**
     * Tokeniza um cartão de crédito
     *
     * POST /pagar/{codigo}/tokenizar
     */
    public function tokenizar(Request $request, string $codigo): void
    {
        $linkModel = new PagamentoLink();
        $link = $linkModel->buscarPorCodigo($codigo);

        if (!$link) {
            Response::json(['success' => false, 'message' => 'Link não encontrado'], 404);
            return;
        }

        $dados = $request->all();
        $gatewayId = (int) ($dados['gateway_id'] ?? 0);

        if (empty($gatewayId)) {
            Response::json(['success' => false, 'message' => 'Gateway é obrigatório'], 400);
            return;
        }

        $gatewayModel = new GatewayPagamento();
        $gatewayConfig = $gatewayModel->buscarPorIdComCredenciais($gatewayId);

        if (!$gatewayConfig || $gatewayConfig['chave'] !== $link['chave']) {
            Response::json(['success' => false, 'message' => 'Gateway inválido'], 400);
            return;
        }

        try {
            $gateway = GatewayFactory::create(
                $gatewayConfig['gateway_code'],
                $gatewayConfig['credentials'] ?? [],
                $gatewayConfig['ambiente'] === 'sandbox',
                $gatewayId
            );

            if (!$gateway->supportsTransparentCheckout()) {
                Response::json([
                    'success' => false,
                    'message' => 'Este gateway não suporta checkout transparente'
                ], 400);
                return;
            }

            // Preparar dados do cartão
            $cardData = [
                'holder' => $dados['holder'] ?? '',
                'number' => $dados['number'] ?? '',
                'expiry_month' => $dados['expiry_month'] ?? '',
                'expiry_year' => $dados['expiry_year'] ?? '',
                'cvv' => $dados['cvv'] ?? '',
                'cpf' => $dados['cpf'] ?? $link['cliente_documento'] ?? '',
                'email' => $dados['email'] ?? $link['cliente_email'] ?? '',
                'phone' => $dados['phone'] ?? $link['cliente_telefone'] ?? '',
                // Para Stripe/Square que já recebem token do frontend
                'payment_method_id' => $dados['payment_method_id'] ?? null,
                'source_id' => $dados['source_id'] ?? null,
                'brand' => $dados['brand'] ?? null,
                'last_digits' => $dados['last_digits'] ?? null,
            ];

            $result = $gateway->tokenizeCard($cardData);

            if (!$result['success']) {
                Response::json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Erro ao tokenizar cartão'
                ], 400);
                return;
            }

            Response::json([
                'success' => true,
                'data' => [
                    'token' => $result['token'],
                    'brand' => $result['brand'] ?? 'unknown',
                    'last_digits' => $result['last_digits'] ?? '****',
                ]
            ]);

        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao tokenizar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Salva um cartão para o cliente
     *
     * POST /pagar/{codigo}/salvar-cartao
     */
    public function salvarCartao(Request $request, string $codigo): void
    {
        $linkModel = new PagamentoLink();
        $link = $linkModel->buscarPorCodigo($codigo);

        if (!$link) {
            Response::json(['success' => false, 'message' => 'Link não encontrado'], 404);
            return;
        }

        if (empty($link['id_cliente'])) {
            Response::json(['success' => false, 'message' => 'Link não tem cliente associado'], 400);
            return;
        }

        $dados = $request->all();

        // Validar campos obrigatórios
        if (empty($dados['token']) || empty($dados['gateway']) || empty($dados['brand'])) {
            Response::json(['success' => false, 'message' => 'Dados incompletos'], 400);
            return;
        }

        try {
            $cartaoModel = new ClienteCartao();
            $id = $cartaoModel->criar([
                'id_cliente' => $link['id_cliente'],
                'bandeira' => strtoupper($dados['brand']),
                'ultimos_digitos' => $dados['last_digits'] ?? '****',
                'token' => $dados['token'],
                'gateway' => $dados['gateway'],
            ]);

            Response::json([
                'success' => true,
                'data' => ['id' => $id]
            ]);

        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao salvar cartão: ' . $e->getMessage()
            ], 500);
        }
    }
}
