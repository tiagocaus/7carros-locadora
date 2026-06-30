<?php

namespace App\Services\Gateways;

/**
 * Gateway de pagamento Bancard (Paraguay)
 *
 * Integração com a API vPOS 2.0 do Bancard.
 *
 * @see https://vpos.infonet.com.py/
 */
class BancardGateway extends AbstractPaymentGateway
{
    public function getCode(): string { return 'bancard'; }
    public function getName(): string { return 'Bancard'; }
    public function getCountry(): string { return 'PY'; }
    public function getSupportedMethods(): array { return ['credit_card', 'debit_card']; }
    public function getSupportedCurrencies(): array { return ['PYG', 'USD']; }

    public function getConfigSchema(): array
    {
        return [
            'public_key' => ['type' => 'string', 'required' => true, 'label' => 'Public Key', 'help' => 'Chave pública do comerciante'],
            'private_key' => ['type' => 'password', 'required' => true, 'label' => 'Private Key', 'help' => 'Chave privada do comerciante'],
        ];
    }

    public function validateCredentials(array $credentials): array
    {
        if (empty($credentials['public_key']) || empty($credentials['private_key'])) {
            return ['valid' => false, 'message' => 'Public Key e Private Key são obrigatórios'];
        }
        return ['valid' => true, 'message' => 'Credenciais configuradas'];
    }

    public function createCharge(array $data): array
    {
        try {
            $this->validateRequiredFields($data, ['value']);

            $shopProcessId = $data['external_reference'] ?? uniqid('shop_');
            $amount = $this->formatAmount((float) $data['value']);

            // Gerar token para o request
            $token = $this->generateToken($shopProcessId, 'single_buy', $amount);

            $payload = [
                'public_key' => $this->credentials['public_key'],
                'operation' => [
                    'token' => $token,
                    'shop_process_id' => $shopProcessId,
                    'amount' => $amount,
                    'currency' => $data['currency'] ?? 'PYG',
                    'additional_data' => $data['description'] ?? '',
                    'description' => $data['description'] ?? 'Pagamento',
                    'return_url' => $data['return_url'] ?? url('/pagar/callback'),
                    'cancel_url' => $data['cancel_url'] ?? url('/pagar/cancel'),
                ],
            ];

            $response = $this->httpRequest('POST', '/vpos/api/0.3/single_buy', $payload);

            if (($response['status'] ?? '') !== 'success') {
                return ['success' => false, 'message' => $response['messages'][0]['desc'] ?? 'Erro ao criar cobrança'];
            }

            $processId = $response['process_id'] ?? $shopProcessId;
            $transactionId = $this->logTransaction($data['chave'] ?? '', $data['id_financeiro'] ?? null, 'charge', $processId, 'pending', (float) $data['value'], 'credit_card', $response, $this->getPaymentUrl($processId));

            return [
                'success' => true,
                'external_id' => $processId,
                'status' => 'pending',
                'payment_url' => $this->getPaymentUrl($processId),
                'transaction_id' => $transactionId,
                'raw' => $response,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }

    public function getChargeStatus(string $externalId): array
    {
        try {
            $token = $this->generateToken($externalId, 'get_confirmation', '');
            $payload = [
                'public_key' => $this->credentials['public_key'],
                'operation' => ['token' => $token, 'shop_process_id' => $externalId],
            ];
            $response = $this->httpRequest('POST', '/vpos/api/0.3/single_buy/confirmations', $payload);
            $confirmation = $response['confirmation'] ?? [];
            return [
                'success' => true,
                'status' => $this->mapStatus($confirmation['response_code'] ?? ''),
                'paid_at' => $confirmation['response_code'] === '00' ? now() : null,
                'raw' => $response,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }

    public function refund(string $externalId, ?float $amount = null): array
    {
        try {
            $token = $this->generateToken($externalId, 'rollback', '');
            $payload = [
                'public_key' => $this->credentials['public_key'],
                'operation' => ['token' => $token, 'shop_process_id' => $externalId],
            ];
            $response = $this->httpRequest('POST', '/vpos/api/0.3/single_buy/rollback', $payload);
            return ['success' => ($response['status'] ?? '') === 'success', 'refund_id' => $externalId, 'raw' => $response];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }

    public function cancel(string $externalId): array
    {
        return $this->refund($externalId);
    }

    public function validateWebhookSignature(array $payload, array $headers): bool
    {
        $receivedToken = $payload['operation']['token'] ?? '';
        $expectedToken = $this->generateToken($payload['operation']['shop_process_id'] ?? '', 'confirm', '');
        return hash_equals($expectedToken, $receivedToken);
    }

    public function parseWebhookPayload(array $payload): array
    {
        $operation = $payload['operation'] ?? [];
        return [
            'event' => 'payment_confirmed',
            'external_id' => $operation['shop_process_id'] ?? '',
            'status' => $this->mapStatus($operation['response_code'] ?? ''),
            'paid_at' => ($operation['response_code'] ?? '') === '00' ? now() : null,
            'raw' => $payload,
        ];
    }

    public function getDocumentationUrl(): string { return 'https://vpos.infonet.com.py/'; }

    protected function mapStatus(string $gatewayStatus): string
    {
        return match ($gatewayStatus) {
            '00' => 'paid',
            '12', '14', '51', '54', '55', '61', '91' => 'cancelled',
            default => 'pending',
        };
    }

    protected function getBaseUrl(): string
    {
        return $this->sandbox ? 'https://vpos.infonet.com.py:8888' : 'https://vpos.infonet.com.py';
    }

    private function generateToken(string $shopProcessId, string $operation, string $amount): string
    {
        $privateKey = $this->credentials['private_key'] ?? '';
        $data = $privateKey . $shopProcessId . $operation . $amount;
        return md5($data);
    }

    private function getPaymentUrl(string $processId): string
    {
        $baseUrl = $this->sandbox ? 'https://vpos.infonet.com.py:8888' : 'https://vpos.infonet.com.py';
        return "{$baseUrl}/checkout/single_buy?process_id={$processId}";
    }
}
