<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\SerproSaldoService;
use App\Models\SerproTransacao;

/**
 * Controller de Webhooks SERPRO
 *
 * Recebe notificacoes de pagamento (PIX e Stripe) para recargas de saldo SERPRO.
 * Rotas publicas sem CSRF, com validacao por token/assinatura.
 *
 * IMPORTANTE: Este controller opera cross-tenant (sem sessao).
 * Usa withoutChave() e chaveOverride para creditar saldo do tenant correto.
 */
class SerproWebhookController
{
    /**
     * Webhook PIX (Banco Inter) para confirmar recarga
     *
     * POST /webhook/multas-online/pix
     */
    public function webhookPix(Request $request): void
    {
        try {
            $payload = $request->all();

            // Log minimo: so metadata nao-sensivel (evita PII/valores nos logs)
            error_log('[SerproWebhook] PIX recebido: ' . count($payload['pix'] ?? []) . ' transacao(oes)');

            // Inter envia array 'pix' com transacoes
            $pixArray = $payload['pix'] ?? [];

            if (empty($pixArray)) {
                Response::json(['success' => true, 'message' => 'Nenhum PIX no payload']);
                return;
            }

            $saldoService = new SerproSaldoService();
            $processados = 0;

            foreach ($pixArray as $pix) {
                $txid = $pix['txid'] ?? '';

                if (empty($txid)) {
                    continue;
                }

                $resultado = $saldoService->confirmarRecargaPorExternalId($txid);

                if ($resultado !== null) {
                    $processados++;
                    error_log("[SerproWebhook] PIX confirmado - txid: {$txid}, saldo: {$resultado['saldo_posterior']}");
                }
            }

            Response::json([
                'success' => true,
                'processados' => $processados,
            ]);
        } catch (\Exception $e) {
            error_log('[SerproWebhook] Erro PIX: ' . $e->getMessage());
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Webhook Stripe para confirmar recarga via cartao
     *
     * POST /webhook/multas-online/stripe
     */
    public function webhookStripe(Request $request): void
    {
        try {
            $payload = file_get_contents('php://input');
            $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
            $webhookSecret = env('STRIPE_WEBHOOK_SECRET', '');

            // Validar assinatura Stripe (obrigatorio)
            if (empty($webhookSecret)) {
                error_log('[SerproWebhook] STRIPE_WEBHOOK_SECRET nao configurado - webhook recusado');
                Response::json(['error' => 'Webhook nao configurado'], 500);
                return;
            }

            try {
                $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
            } catch (\Stripe\Exception\SignatureVerificationException $e) {
                error_log('[SerproWebhook] Stripe assinatura invalida: ' . $e->getMessage());
                Response::json(['error' => 'Assinatura invalida'], 401);
                return;
            }

            error_log('[SerproWebhook] Stripe evento: ' . ($event->type ?? 'unknown'));

            // Processar apenas eventos de pagamento confirmado
            $eventType = is_object($event) ? $event->type : ($event['type'] ?? '');

            if (!in_array($eventType, ['payment_intent.succeeded', 'charge.succeeded'], true)) {
                Response::json(['success' => true, 'message' => 'Evento ignorado: ' . $eventType]);
                return;
            }

            // Extrair dados do evento
            $dataObject = is_object($event) && isset($event->data->object)
                ? $event->data->object
                : ($event['data']['object'] ?? []);

            $paymentIntentId = '';
            $metadata = [];

            if (is_object($dataObject)) {
                $paymentIntentId = $dataObject->id ?? ($dataObject->payment_intent ?? '');
                $metadata = (array) ($dataObject->metadata ?? []);
            } else {
                $paymentIntentId = $dataObject['id'] ?? ($dataObject['payment_intent'] ?? '');
                $metadata = $dataObject['metadata'] ?? [];
            }

            // Verificar se e recarga SERPRO
            $tipo = $metadata['tipo'] ?? '';
            if (!in_array($tipo, ['recarga_serpro', 'auto_recarga_serpro'], true)) {
                Response::json(['success' => true, 'message' => 'Nao e recarga de consultas online']);
                return;
            }

            if (empty($paymentIntentId)) {
                Response::json(['success' => false, 'message' => 'Payment intent ID ausente'], 400);
                return;
            }

            // Confirmar recarga
            $saldoService = new SerproSaldoService();
            $resultado = $saldoService->confirmarRecargaPorExternalId($paymentIntentId);

            if ($resultado === null) {
                Response::json(['success' => true, 'message' => 'Ja processado (idempotente)']);
                return;
            }

            error_log("[SerproWebhook] Stripe confirmado - PI: {$paymentIntentId}, saldo: {$resultado['saldo_posterior']}");

            // Salvar payment method para auto-recarga futura
            if ($tipo === 'recarga_serpro' && !empty($metadata['chave'])) {
                $this->salvarPaymentMethodSeNecessario($dataObject, $metadata['chave']);
            }

            Response::json([
                'success' => true,
                'message' => 'Recarga confirmada',
            ]);
        } catch (\Exception $e) {
            error_log('[SerproWebhook] Erro Stripe: ' . $e->getMessage());
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
 * Webhook de eventos de consultas online
     *
     * Recebe notificacoes de novos eventos (multas, recalls, etc.)
     * POST /webhook/multas-online/eventos
     */
    public function webhookEventos(Request $request): void
    {
        try {
            $payload = $request->all();

            // Validar secret se configurado
            $secret = env('SERPRO_WEBHOOK_SECRET', '');
            if (!empty($secret)) {
                $headerSecret = trim((string) ($_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? ''));
                $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
                $expectedAuthorization = 'Bearer ' . $secret;

                if (!hash_equals($secret, $headerSecret) && !hash_equals($expectedAuthorization, $authorization)) {
                    error_log('[SerproWebhook] Eventos - secret invalido');
                    Response::json(['error' => 'Unauthorized'], 401);
                    return;
                }
            }

            // Log minimo: so contadores (evita PII/valores sensiveis no log)
            error_log('[SerproWebhook] Evento SERPRO recebido: ' . count($payload['eventos'] ?? [$payload]) . ' evento(s)');

            $eventos = $payload['eventos'] ?? [$payload];
            $processados = 0;

            foreach ($eventos as $evento) {
                $tipoEvento = $evento['tipoEvento'] ?? $evento['tipo'] ?? '';
                $placa = $evento['placa'] ?? ($evento['infracao']['placa'] ?? '');

                if (empty($placa)) {
                    continue;
                }

                $veiculo = $this->buscarVeiculoPorPlacaCrossTenant($placa);

                if (!$veiculo) {
                    error_log("[SerproWebhook] Veiculo nao encontrado: {$placa}");
                    continue;
                }

                $chave = $veiculo['chave'];
                $_SESSION['chave'] = $chave;

                try {
                    // Debitar evento do saldo
                    $saldoService = new \App\Services\SerproSaldoService();
                    $saldoService->inicializarSaldo();

                    if ($saldoService->temSaldoParaEventos(1)) {
                        $saldoService->debitarEvento("Evento de consulta online: {$tipoEvento} placa {$placa}", $placa);
                    }

                    // Processar evento conforme tipo
                    $this->processarEvento($evento, $veiculo);
                    $processados++;
                } finally {
                    unset($_SESSION['chave']);
                }
            }

            Response::json([
                'success' => true,
                'processados' => $processados,
            ]);
        } catch (\Throwable $e) {
            error_log('[SerproWebhook] Erro eventos: ' . $e->getMessage());
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Busca veiculo por placa em todos os tenants (cross-tenant)
     */
    private function buscarVeiculoPorPlacaCrossTenant(string $placa): ?array
    {
        $veiculoModel = new \App\Models\Veiculo();
        return $veiculoModel->buscarPorPlacaCrossTenant($placa);
    }

    /**
     * Processa um evento SERPRO e cria/atualiza multa se aplicavel
     */
    private function processarEvento(array $evento, array $veiculo): void
    {
        $multaModel = new \App\Models\Multa();
        $dadosEvento = $multaModel->normalizarInfracaoSerpro(array_merge($evento, [
            'id_veiculo' => (int) $veiculo['id'],
            'placa' => $veiculo['placa'],
            'origem' => 'serpro_evento',
            'status_processamento' => 'novo',
        ]));

        $codigoOrgao = $dadosEvento['codigo_orgao'] ?? '';
        $numeroAit = $dadosEvento['numero_ait'] ?? '';
        $codigoInfracao = $dadosEvento['codigo_infracao'] ?? '';

        if (empty($codigoOrgao) || empty($numeroAit)) {
            return;
        }

        // Verificar se multa ja existe
        $existente = $multaModel->buscarPorChavesSerpro($codigoOrgao, $numeroAit, $codigoInfracao);

        if ($existente) {
            $dadosAtualizacao = $dadosEvento;

            if (empty($existente['id_contrato']) && empty($existente['id_locacao'])) {
                $dadosAtualizacao = array_merge($dadosAtualizacao, $multaModel->resolverResponsavelSerpro([
                    'id_veiculo' => (int) $veiculo['id'],
                    'data_hora' => $dadosEvento['data_hora'] ?? null,
                ]));
            }

            $multaModel->atualizarDadosSerpro($existente['id'], $dadosAtualizacao);
            return;
        }

        // Criar nova multa a partir do evento
        $multaModel->criarDeSerpro($dadosEvento);
    }

    /**
     * Salva payment method no saldo do tenant (para auto-recarga futura)
     */
    private function salvarPaymentMethodSeNecessario($dataObject, string $chave): void
    {
        try {
            $paymentMethodId = '';

            if (is_object($dataObject)) {
                $paymentMethodId = $dataObject->payment_method ?? '';
            } else {
                $paymentMethodId = $dataObject['payment_method'] ?? '';
            }

            if (empty($paymentMethodId)) {
                return;
            }

            $saldoModel = new \App\Models\SerproSaldo();
            $saldoInfo = $saldoModel->buscarPorChaveEspecifica($chave);

            // Salvar apenas se nao tem payment method ainda
            if ($saldoInfo && empty($saldoInfo['stripe_payment_method_id'])) {
                $saldoModel->qb
                    ->table('serpro_saldo')
                    ->withoutChave()
                    ->where('chave', '=', $chave)
                    ->update([
                        'stripe_payment_method_id' => $paymentMethodId,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            }
        } catch (\Exception $e) {
            error_log('[SerproWebhook] Erro ao salvar payment method: ' . $e->getMessage());
        }
    }
}
