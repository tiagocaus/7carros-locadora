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
use App\Services\PagamentoLinkSyncService;

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

        if ($this->financeiroEstaPago($link)) {
            $this->renderSucesso('Pagamento já realizado', 'Esta fatura já foi paga. Obrigado!');
            return;
        }

        $link = $this->sincronizarLinkPublico($linkModel, $link);
        if (!$link) {
            $this->renderErro('Pagamento indisponível', 'Esta fatura não está disponível para pagamento. Entre em contato com a empresa.');
            return;
        }

        // Verificar se expirou
        if (!empty($link['expires_at']) && strtotime($link['expires_at']) < \App\Helpers\DateHelper::timestamp()) {
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
            $this->renderErro('Pagamento indisponível', 'Esta fatura não está disponível para pagamento. Entre em contato com a empresa.');
            return;
        }

        // Buscar gateways vinculados à forma de pagamento do financeiro
        $gateways = [];

        if (!empty($link['id_forma_pagamento'])) {
            $formaPagamentoModel = new FormaPagamento();
            $formaSite = $formaPagamentoModel->buscarFormaPagamentoSite(
                (int) $link['id_forma_pagamento'],
                (string) $link['chave'],
                !empty($link['id_matriz_filial']) ? (int) $link['id_matriz_filial'] : null
            );

            if ($formaSite) {
                $gateways = $this->gatewaysDasFormasPagamentoSite([$formaSite]);
            } else {
                $gatewaysVinculados = $formaPagamentoModel->buscarGateways((int) $link['id_forma_pagamento']);
                $idsVinculados = array_column($gatewaysVinculados, 'id');

                if (!empty($idsVinculados)) {
                    $gatewayModel = new GatewayPagamento();
                    $gateways = $gatewayModel->listarParaPagamentoPublicoPorIds($link['chave'], $idsVinculados);
                }
            }
        } elseif (!empty($link['id_financeiro'])) {
            $formaPagamentoModel = new FormaPagamento();
            $formasSite = $formaPagamentoModel->listarParaPagamentoSite(
                (string) $link['chave'],
                !empty($link['id_matriz_filial']) ? (int) $link['id_matriz_filial'] : null
            );
            $gateways = $this->gatewaysDasFormasPagamentoSite($formasSite);
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

            if ($this->gatewayPermiteMetodoPorForma($gw, 'pix')) {
                $gatewaysPix[] = $gw;
            }
            if ($this->gatewayPermiteMetodoPorForma($gw, 'boleto')) {
                $gatewaysBoleto[] = $gw;
            }
            if ($this->gatewayPermiteMetodoPorForma($gw, 'credit_card') || $this->gatewayPermiteMetodoPorForma($gw, 'debit_card')) {
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
        if ($link['status'] === 'paid' || $this->financeiroEstaPago($link)) {
            Response::json([
                'success' => false,
                'message' => 'Esta fatura já foi paga'
            ], 400);
            return;
        }

        $link = $this->sincronizarLinkPublico($linkModel, $link);
        if (!$link) {
            Response::json([
                'success' => false,
                'message' => 'Esta fatura não está disponível para pagamento'
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

        if (!empty($link['expires_at']) && strtotime($link['expires_at']) < \App\Helpers\DateHelper::timestamp()) {
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
        $formaPagamentoId = !empty($link['id_forma_pagamento'])
            ? (int) $link['id_forma_pagamento']
            : (int) ($dados['id_forma_pagamento'] ?? 0);

        if (empty($metodo) || empty($gatewayId)) {
            Response::json([
                'success' => false,
                'message' => 'Método de pagamento e gateway são obrigatórios'
            ], 400);
            return;
        }

        if (empty($formaPagamentoId)) {
            Response::json([
                'success' => false,
                'message' => 'Forma de pagamento obrigatória'
            ], 400);
            return;
        }

        $formaPagamentoModel = new FormaPagamento();
        $formaSite = $formaPagamentoModel->buscarFormaPagamentoSite(
            $formaPagamentoId,
            (string) $link['chave'],
            !empty($link['id_matriz_filial']) ? (int) $link['id_matriz_filial'] : null
        );

        if ($formaSite) {
            if (!$formaPagamentoModel->formaPermiteGatewayMetodo($formaSite, $gatewayId, $metodo)) {
                Response::json([
                    'success' => false,
                    'message' => 'Forma de pagamento indisponível para este link'
                ], 400);
                return;
            }
        } elseif (!empty($link['id_forma_pagamento'])) {
            $idsVinculados = array_column($formaPagamentoModel->buscarGateways((int) $link['id_forma_pagamento']), 'id');
            if (!in_array($gatewayId, array_map('intval', $idsVinculados), true)) {
                Response::json([
                    'success' => false,
                    'message' => 'Forma de pagamento indisponível para este link'
                ], 400);
                return;
            }
        } else {
            Response::json([
                'success' => false,
                'message' => 'Forma de pagamento indisponível para este link'
            ], 400);
            return;
        }

        // Buscar gateway
        $gatewayModel = new GatewayPagamento();
        $gatewayConfig = $gatewayModel->buscarPorIdComCredenciaisParaTenant($gatewayId, (string) $link['chave']);

        if (!$gatewayConfig) {
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

        $gatewayInfo = GatewayFactory::getGatewayInfo((string) $gatewayConfig['gateway_code']);
        if (!$gatewayInfo || !in_array($metodo, $gatewayInfo['methods'] ?? [], true)) {
            Response::json([
                'success' => false,
                'message' => 'Método de pagamento não suportado por este gateway'
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
            if (empty($link['id_forma_pagamento']) && !empty($link['id_financeiro'])) {
                $this->vincularFormaPagamentoAoFinanceiro($link, $formaSite);
                $link = $linkModel->buscarPorCodigo($codigo) ?? $link;
            }

            if (!empty($link['id_financeiro'])) {
                (new PagamentoLinkSyncService())->invalidarLinksPendentes((int) $link['id_financeiro'], (string) $link['chave']);
                $link = $linkModel->buscarPorCodigo($codigo) ?? $link;
            }

            // Criar instância do gateway
            $gateway = GatewayFactory::create(
                $gatewayConfig['gateway_code'],
                $gatewayConfig['credentials'] ?? [],
                $gatewayConfig['ambiente'] === 'sandbox',
                $gatewayId
            );

            $dueDate = $this->resolveGatewayDueDate($link['financeiro_vencimento'] ?? null);

            // Preparar dados da cobrança
            $chargeData = [
                'chave' => $link['chave'],
                'id_financeiro' => $link['id_financeiro'],
                'value' => (float) $link['valor'],
                'billing_type' => $metodo,
                'description' => $link['descricao'] ?? $link['financeiro_descricao'] ?? 'Pagamento',
                'external_reference' => "link_{$link['id']}",
                'due_date' => $dueDate,
            ];

            // Dados do cliente
            if (!empty($link['cliente_documento'])) {
                $chargeData['customer_document'] = $link['cliente_documento'];
                $chargeData['customer_name'] = $link['cliente_nome'] ?? 'Cliente';
                $chargeData['customer_email'] = $link['cliente_email'] ?? null;
                $chargeData['customer_phone'] = $link['cliente_telefone'] ?? null;
                $chargeData['customer_address'] = $link['cliente_endereco'] ?? null;
                $chargeData['customer_address_number'] = $link['cliente_numero'] ?? null;
                $chargeData['customer_neighborhood'] = $link['cliente_bairro'] ?? null;
                $chargeData['customer_city'] = $link['cliente_cidade'] ?? null;
                $chargeData['customer_state'] = $link['cliente_estado'] ?? null;
                $chargeData['customer_postal_code'] = $link['cliente_cep'] ?? null;
            }

            $chargeData['beneficiary_name'] = $link['empresa_razao_social'] ?? $link['empresa_nome'] ?? null;
            $chargeData['beneficiary_document'] = $link['empresa_cnpj'] ?? null;
            $chargeData['beneficiary_address'] = $link['empresa_endereco'] ?? null;
            $chargeData['beneficiary_address_number'] = $link['empresa_numero'] ?? null;
            $chargeData['beneficiary_neighborhood'] = $link['empresa_bairro'] ?? null;
            $chargeData['beneficiary_city'] = $link['empresa_cidade'] ?? null;
            $chargeData['beneficiary_state'] = $link['empresa_uf'] ?? null;
            $chargeData['beneficiary_postal_code'] = $link['empresa_cep'] ?? null;

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
                    'due_date' => $dueDate,
                    'metodo' => $metodo,
                ]
            ]);

        } catch (\Exception $e) {
            $mensagem = $e->getMessage();
            if (str_contains($mensagem, 'ja consta como paga') || str_contains($mensagem, 'já consta como paga')) {
                Response::json([
                    'success' => false,
                    'message' => 'Esta fatura já possui pagamento confirmado no gateway. Aguarde a atualização do financeiro.'
                ], 409);
                return;
            }

            Response::json([
                'success' => false,
                'message' => 'Erro ao processar pagamento: ' . $mensagem
            ], 500);
        }
    }

    private function sincronizarLinkPublico(PagamentoLink $linkModel, array $link): ?array
    {
        if (empty($link['id_financeiro'])) {
            return $link;
        }

        if (($link['financeiro_tipo'] ?? null) !== 'R') {
            return null;
        }

        if (($link['financeiro_pago'] ?? 'N') === 'S') {
            return $link;
        }

        if (!isset($link['financeiro_valor_total'])) {
            return null;
        }

        $expiresAt = $link['expires_at'] ?? null;
        if (empty($expiresAt) || strtotime((string) $expiresAt) < \App\Helpers\DateHelper::timestamp() || ($link['status'] ?? '') !== 'pending') {
            $expiresAt = \App\Helpers\DateHelper::addDaysForDatabase(30, null, 'Y-m-d H:i:s');
        }

        $linkModel->atualizarDadosCobranca((int) $link['id'], [
            'id_cliente' => $link['financeiro_id_cliente'] ?? $link['id_cliente'] ?? null,
            'valor' => $link['financeiro_valor_total'],
            'descricao' => $link['financeiro_descricao'] ?? $link['descricao'] ?? 'Pagamento',
            'expires_at' => $expiresAt,
        ], (string) $link['chave']);

        return $linkModel->buscarPorCodigo((string) $link['codigo']);
    }

    private function financeiroEstaPago(array $link): bool
    {
        return ($link['status'] ?? '') === 'paid' || ($link['financeiro_pago'] ?? 'N') === 'S';
    }

    private function resolveGatewayDueDate(?string $financeiroVencimento): string
    {
        return \App\Helpers\DateHelper::normalizeDueDateForGateway($financeiroVencimento);
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
            $gatewayConfig = $gatewayModel->buscarPorIdComCredenciaisParaTenant(
                (int) $transacao['id_gateway'],
                (string) $link['chave']
            );

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
                                $status === 'paid' ? now() : null
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
        $payload = $this->getWebhookPayload($request);
        $headers = $this->getHeaders();

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
            $event = $parsedPayload['event'] ?? 'unknown';

            $this->webhookDebugLog(sprintf(
                '[Webhook] Recebido gateway=%s event=%s external_id=%s reference=%s keys=%s',
                $gatewayCode,
                $event,
                $externalId ?: '-',
                $parsedPayload['external_reference'] ?? '-',
                implode(',', array_keys($payload))
            ));

            if (empty($externalId)) {
                if ($this->isAsaasWebhook($gatewayCode, $payload)) {
                    $this->webhookDebugLog("[Webhook] external_id ausente gateway={$gatewayCode} event={$event}");
                    Response::json(['success' => true, 'ignored' => true, 'message' => 'Webhook sem cobrança processável']);
                    return;
                }

                error_log("[Webhook] external_id ausente gateway={$gatewayCode} event={$event}");
                Response::json(['error' => 'external_id não encontrado no payload'], 400);
                return;
            }

            // Buscar transação original
            $transacao = $transacaoModel->buscarPorExternalId($externalId);
            $gatewayConfig = null;
            $gateway = null;

            if (!$transacao && $this->isAsaasWebhook($gatewayCode, $payload)) {
                $link = $this->buscarLinkPorExternalReference($parsedPayload['external_reference'] ?? null);

                if ($link) {
                    $gatewayConfig = $gatewayModel->buscarPorChaveECodigo($link['chave'], $gatewayCode);

                    if (!$gatewayConfig) {
                        error_log("[Webhook] Gateway Asaas nao configurado para chave={$link['chave']} external_id={$externalId}");
                        Response::json(['success' => true, 'pending' => true, 'message' => 'Gateway não configurado']);
                        return;
                    }

                    $gateway = GatewayFactory::create(
                        $gatewayConfig['gateway_code'],
                        $gatewayConfig['credentials'] ?? [],
                        $gatewayConfig['ambiente'] === 'sandbox',
                        $gatewayConfig['id']
                    );

                    if (!$gateway->validateWebhookSignature($payload, $headers)) {
                        Response::json(['error' => 'Assinatura inválida'], 401);
                        return;
                    }

                    $transacao = $this->criarTransacaoAsaasPeloWebhook(
                        $transacaoModel,
                        $gatewayConfig,
                        $link,
                        $externalId,
                        $parsedPayload,
                        $payload
                    );
                }
            }

            if (!$transacao) {
                $externalReference = trim((string) ($parsedPayload['external_reference'] ?? ''));
                $message = "[Webhook] Transacao nao encontrada gateway={$gatewayCode} external_id={$externalId}"
                    . ' reference=' . ($externalReference !== '' ? $externalReference : '-');

                if ($this->shouldAlertMissingTransaction($externalReference)) {
                    error_log($message);
                } else {
                    $this->webhookDebugLog($message);
                }

                // Transação não encontrada, mas retornar OK para não reenviar
                Response::json(['success' => true, 'ignored' => true, 'message' => 'Transação não encontrada']);
                return;
            }

            // Buscar gateway com credenciais
            if (!empty($transacao['id_gateway'])) {
                $gatewayConfig = $gatewayModel->buscarPorIdComCredenciaisParaTenant(
                    (int) $transacao['id_gateway'],
                    (string) $transacao['chave']
                );
            }

            // Se não encontrou pelo id_gateway, buscar pelo chave e código
            if (!$gatewayConfig) {
                $gatewayConfig = $gatewayModel->buscarPorChaveECodigo($transacao['chave'], $gatewayCode);
            }

            if (!$gatewayConfig) {
                if ($this->isAsaasWebhook($gatewayCode, $payload)) {
                    error_log("[Webhook] Gateway nao configurado gateway={$gatewayCode} chave=" . ($transacao['chave'] ?? '-'));
                    Response::json(['success' => true, 'pending' => true, 'message' => 'Gateway não configurado']);
                    return;
                }

                Response::json(['error' => 'Gateway não configurado'], 400);
                return;
            }

            // Criar instância com credenciais
            if (!$gateway) {
                $gateway = GatewayFactory::create(
                    $gatewayConfig['gateway_code'],
                    $gatewayConfig['credentials'] ?? [],
                    $gatewayConfig['ambiente'] === 'sandbox',
                    $gatewayConfig['id']
                );
            }

            // Validar assinatura
            if (!$gateway->validateWebhookSignature($payload, $headers)) {
                Response::json(['error' => 'Assinatura inválida'], 401);
                return;
            }

            // Verificar idempotência
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
                $paidAt = $parsedPayload['paid_at'] ?? now();
            }

            $transacaoModel->atualizarPorExternalId($externalId, $newStatus, $paidAt);

            // Se foi pago, atualizar link e financeiro
            if ($newStatus === 'paid' && !empty($transacao['id_financeiro'])) {
                // Buscar link associado ao financeiro
                $linkModel = new PagamentoLink();
                $link = $linkModel->buscarReutilizavelPorFinanceiro($transacao['id_financeiro']);

                if ($link) {
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

    private function webhookDebugLog(string $message): void
    {
        if (\App\Core\Database::env('PAYMENT_WEBHOOK_DEBUG', 'false') !== 'true') {
            return;
        }

        error_log($message);
    }

    private function shouldAlertMissingTransaction(string $externalReference): bool
    {
        return str_starts_with(trim($externalReference), 'link_');
    }

    /**
     * Obtém payload do webhook mesmo quando o Content-Type não foi detectado como JSON.
     */
    private function getWebhookPayload(Request $request): array
    {
        $payload = $request->all();

        if (!empty($payload) && (isset($payload['event']) || isset($payload['payment']))) {
            return $payload;
        }

        $rawBody = file_get_contents('php://input') ?: '';
        if ($rawBody === '') {
            return $payload;
        }

        $decoded = json_decode($rawBody, true);
        return is_array($decoded) ? $decoded : $payload;
    }

    private function isAsaasWebhook(string $gatewayCode, array $payload): bool
    {
        return $gatewayCode === 'asaas' && isset($payload['event']);
    }

    private function buscarLinkPorExternalReference(?string $externalReference): ?array
    {
        if (empty($externalReference) || !preg_match('/^link_(\d+)$/', $externalReference, $matches)) {
            return null;
        }

        $linkModel = new PagamentoLink();
        return $linkModel->buscarPublicoPorId((int) $matches[1]);
    }

    /**
     * Recria a transação local quando o Asaas envia externalReference antes de
     * encontrarmos o registro original por payment.id.
     *
     * @param array<string, mixed> $gatewayConfig
     * @param array<string, mixed> $link
     * @param array<string, mixed> $parsedPayload
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function criarTransacaoAsaasPeloWebhook(
        FinanceiroTransacao $transacaoModel,
        array $gatewayConfig,
        array $link,
        string $externalId,
        array $parsedPayload,
        array $payload
    ): array {
        $idTransacao = $transacaoModel->criar([
            'chave' => $link['chave'],
            'id_financeiro' => $link['id_financeiro'] ?? null,
            'id_gateway' => $gatewayConfig['id'] ?? null,
            'gateway' => 'asaas',
            'external_id' => $externalId,
            'type' => 'charge',
            'payment_method' => strtolower((string) ($parsedPayload['billing_type'] ?? '')),
            'status' => $parsedPayload['status'] ?? 'pending',
            'amount' => $parsedPayload['amount'] ?? ($link['valor'] ?? null),
            'net_amount' => $parsedPayload['net_amount'] ?? null,
            'payment_url' => $parsedPayload['payment_url'] ?? null,
            'barcode' => $parsedPayload['barcode'] ?? null,
            'expires_at' => $parsedPayload['due_date'] ?? null,
            'payload' => json_encode($payload),
        ]);

        return $transacaoModel->buscarPorId($idTransacao) ?? [
            'id' => $idTransacao,
            'chave' => $link['chave'],
            'id_financeiro' => $link['id_financeiro'] ?? null,
            'id_gateway' => $gatewayConfig['id'] ?? null,
            'external_id' => $externalId,
        ];
    }

    public function webhookAsaas(Request $request): void
    {
        $this->webhook($request, 'asaas');
    }

    public function webhookAsaasInfo(Request $request): void
    {
        $this->renderWebhookInfo('asaas');
    }

    public function webhookStripe(Request $request): void
    {
        $this->webhook($request, 'stripe');
    }

    public function webhookStripeInfo(Request $request): void
    {
        $this->renderWebhookInfo('stripe');
    }

    public function webhookSquare(Request $request): void
    {
        $this->webhook($request, 'square');
    }

    public function webhookSquareInfo(Request $request): void
    {
        $this->renderWebhookInfo('square');
    }

    public function webhookCora(Request $request): void
    {
        $this->webhook($request, 'cora');
    }

    public function webhookCoraInfo(Request $request): void
    {
        $this->renderWebhookInfo('cora');
    }

    public function webhookEfipay(Request $request): void
    {
        $this->webhook($request, 'efipay');
    }

    public function webhookEfipayInfo(Request $request): void
    {
        $this->renderWebhookInfo('efipay');
    }

    public function webhookInter(Request $request): void
    {
        $this->webhook($request, 'inter');
    }

    public function webhookInterInfo(Request $request): void
    {
        $this->renderWebhookInfo('inter');
    }

    public function webhookSicoob(Request $request): void
    {
        $this->webhook($request, 'sicoob');
    }

    public function webhookSicoobInfo(Request $request): void
    {
        $this->renderWebhookInfo('sicoob');
    }

    public function webhookBradesco(Request $request): void
    {
        $this->webhook($request, 'bradesco');
    }

    public function webhookBradescoInfo(Request $request): void
    {
        $this->renderWebhookInfo('bradesco');
    }

    public function webhookItau(Request $request): void
    {
        $this->webhook($request, 'itau');
    }

    public function webhookItauInfo(Request $request): void
    {
        $this->renderWebhookInfo('itau');
    }

    public function webhookSantander(Request $request): void
    {
        $this->webhook($request, 'santander');
    }

    public function webhookSantanderInfo(Request $request): void
    {
        $this->renderWebhookInfo('santander');
    }

    public function webhookBancard(Request $request): void
    {
        $this->webhook($request, 'bancard');
    }

    public function webhookBancardInfo(Request $request): void
    {
        $this->renderWebhookInfo('bancard');
    }

    public function webhookPagopar(Request $request): void
    {
        $this->webhook($request, 'pagopar');
    }

    public function webhookPagoparInfo(Request $request): void
    {
        $this->renderWebhookInfo('pagopar');
    }

    /**
     * Exibe diagnóstico seguro quando a URL do webhook é aberta no navegador.
     */
    private function renderWebhookInfo(string $gatewayCode): void
    {
        $gatewayCode = strtolower($gatewayCode);

        if (!GatewayFactory::exists($gatewayCode)) {
            Response::html('Gateway desconhecido', 404);
            return;
        }

        $gatewayName = htmlspecialchars($gatewayCode, ENT_QUOTES, 'UTF-8');
        $endpoint = htmlspecialchars('/webhook/' . $gatewayCode, ENT_QUOTES, 'UTF-8');

        Response::html(
            '<!doctype html>'
            . '<html lang="pt-BR">'
            . '<head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Webhook ativo</title>'
            . '<style>'
            . 'body{font-family:Arial,sans-serif;margin:0;background:#f8fafc;color:#111827;}'
            . 'main{max-width:720px;margin:64px auto;padding:32px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;}'
            . 'h1{font-size:24px;margin:0 0 16px;}'
            . 'p{font-size:16px;line-height:1.5;margin:0 0 12px;}'
            . 'code{background:#f3f4f6;border-radius:4px;padding:2px 6px;}'
            . '</style>'
            . '</head>'
            . '<body>'
            . '<main>'
            . '<h1>Webhook ' . $gatewayName . ' ativo</h1>'
            . '<p>Este endpoint esta disponivel para receber notificacoes do gateway.</p>'
            . '<p>Eventos reais devem ser enviados por <strong>POST</strong> para <code>' . $endpoint . '</code>.</p>'
            . '<p>Esta pagina aparece apenas como diagnostico ao abrir a URL pelo navegador.</p>'
            . '</main>'
            . '</body>'
            . '</html>'
        );
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
            (new \App\Services\FinanceiroTaxaService())->sincronizar($idFinanceiro, $idTransacao);

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
                    'id'            => (int) ($locacao['id_cliente'] ?? 0),
                    'nome'          => $nomeCliente,
                    'primeiro_nome' => $primeiroNome,
                    'email'         => $emailCliente,
                    'telefone'      => $telCliente,
                    'celular'       => $telCliente,
                ],
                'empresa' => [
                    'id' => (int) ($locacao['id_matriz_filial_retirada'] ?? 0),
                ],
                'id_matriz_filial' => (int) ($locacao['id_matriz_filial_retirada'] ?? 0),
                'locacao' => [
                    'numero'          => $locacao['codigo'] ?? '',
                    'data_retirada'   => !empty($locacao['data_saida']) ? format_date($locacao['data_saida']) : '',
                    'hora_retirada'   => !empty($locacao['data_saida']) ? \App\Helpers\DateHelper::formatOperationalDateTime($locacao['data_saida'], true, 'H:i') : '',
                    'data_devolucao'  => !empty($locacao['data_prevista']) ? format_date($locacao['data_prevista']) : '',
                    'hora_devolucao'  => !empty($locacao['data_prevista']) ? \App\Helpers\DateHelper::formatOperationalDateTime($locacao['data_prevista'], true, 'H:i') : '',
                    'quantidade_dias' => (int) ($locacao['dias'] ?? 0),
                    'valor_total'     => (float) ($locacao['total_pagar'] ?? $locacao['total_fatura'] ?? 0),
                ],
                'outros' => [
                    'data_atual' => format_date(today()),
                ],
            ];

            if (function_exists('queue_template_message')) {
                foreach (['email', 'whatsapp', 'sms'] as $canal) {
                    try {
                        queue_template_message('confirmacao_reserva', $canal, $context, $chave);
                    } catch (\App\Exceptions\NotificationChannelUnavailableException|\App\Exceptions\NotificationRecipientUnavailableException) {
                        // Canal ou destinatario indisponivel: notificacao opcional ignorada.
                    } catch (\Throwable $e) {
                        error_log("[Webhook/Reserva] Erro ao enfileirar confirmacao_reserva/{$canal}: " . $e->getMessage());
                    }
                }
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
        $gatewayConfig = $gatewayModel->buscarPorIdComCredenciaisParaTenant($gatewayId, (string) $link['chave']);

        if (!$gatewayConfig) {
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
            $gatewayConfig = $gatewayModel->buscarPorIdComCredenciaisParaTenant($gatewayId, (string) $link['chave']);
            if ($gatewayConfig) {
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
        $gatewayConfig = $gatewayModel->buscarPorIdComCredenciaisParaTenant($gatewayId, (string) $link['chave']);

        if (!$gatewayConfig) {
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

    /**
     * @param array<int, array<string, mixed>> $formas
     * @return array<int, array<string, mixed>>
     */
    private function gatewaysDasFormasPagamentoSite(array $formas): array
    {
        $gateways = [];
        foreach ($formas as $forma) {
            $metodos = $forma['metodos'] ?? [];
            foreach ($forma['gateways'] ?? [] as $gateway) {
                $gateway['id_forma_pagamento'] = (int) $forma['id'];
                $gateway['forma_pagamento_nome'] = (string) $forma['nome'];
                $gateway['metodos_forma_pagamento'] = $metodos;
                $gateways[] = $gateway;
            }
        }

        return $gateways;
    }

    private function gatewayPermiteMetodoPorForma(array $gateway, string $metodo): bool
    {
        $metodosForma = $gateway['metodos_forma_pagamento'] ?? [];
        if (!empty($metodosForma) && !in_array($metodo, $metodosForma, true)) {
            return false;
        }

        $gatewayInfo = GatewayFactory::getGatewayInfo((string) ($gateway['gateway_code'] ?? ''));
        if (!$gatewayInfo || !in_array($metodo, $gatewayInfo['methods'] ?? [], true)) {
            return false;
        }

        return match ($metodo) {
            'pix' => !empty($gateway['pix_enabled']),
            'boleto' => !empty($gateway['boleto_enabled']),
            'credit_card' => !empty($gateway['credit_card_enabled']),
            'debit_card' => !empty($gateway['debit_card_enabled']),
            default => false,
        };
    }

    private function vincularFormaPagamentoAoFinanceiro(array $link, array $forma): void
    {
        if (empty($link['id_financeiro'])) {
            return;
        }

        $financeiroModel = new Financeiro();
        $valorParcela = (float) ($link['financeiro_valor_subtotal'] ?? $link['financeiro_valor_total'] ?? $link['valor'] ?? 0);
        $valorTaxa = $financeiroModel->calcularTaxaParcela($forma, $valorParcela, 1);

        $dadosUpdate = [
            'id_forma_pagamento' => (int) $forma['id'],
            'valor_taxa' => $valorTaxa,
            'taxa_percentual_snapshot' => $forma['taxa_percentual_parcela'] ?? 0,
            'taxa_fixa_snapshot' => $forma['taxa_fixa'] ?? 0,
            'taxa_fixa_parcela_snapshot' => $forma['taxa_fixa_parcela'] ?? 0,
        ];

        if (!empty($link['id_locacao'])) {
            $dadosUpdate['id_locacao'] = (int) $link['id_locacao'];
        }

        $financeiroModel->atualizar((int) $link['id_financeiro'], $dadosUpdate);
    }
}
