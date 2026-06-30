<?php

namespace App\Services\Gateways;

/**
 * Gateway de pagamento Pagopar (Paraguay)
 *
 * Integração com a API do Pagopar.
 * Utiliza o SDK oficial pagopar/sdk-php.
 *
 * @see https://developers.pagopar.com/
 */
class PagoparGateway extends AbstractPaymentGateway
{
    public function getCode(): string { return 'pagopar'; }
    public function getName(): string { return 'Pagopar'; }
    public function getCountry(): string { return 'PY'; }
    public function getSupportedMethods(): array { return ['credit_card', 'debit_card']; }
    public function getSupportedCurrencies(): array { return ['PYG']; }

    public function getConfigSchema(): array
    {
        return [
            'public_key' => ['type' => 'string', 'required' => true, 'label' => 'Public Key', 'placeholder' => 'pk_...', 'help' => 'Chave pública disponível no painel Pagopar'],
            'private_key' => ['type' => 'password', 'required' => true, 'label' => 'Private Key', 'placeholder' => 'sk_...', 'help' => 'Chave privada'],
            'comercio_id' => ['type' => 'string', 'required' => true, 'label' => 'ID do Comércio', 'help' => 'ID do comercio no Pagopar'],
        ];
    }

    public function validateCredentials(array $credentials): array
    {
        if (empty($credentials['public_key']) || empty($credentials['private_key'])) {
            return ['valid' => false, 'message' => 'Public Key e Private Key são obrigatórios'];
        }
        try {
            // Testar conexão consultando o comercio
            $response = $this->makeApiRequest('GET', '/api/v1/comercio', [], $credentials['private_key']);
            return isset($response['id']) ? ['valid' => true, 'message' => 'Credenciais válidas'] : ['valid' => false, 'message' => 'Credenciais inválidas'];
        } catch (\Exception $e) {
            return ['valid' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }

    public function createCharge(array $data): array
    {
        try {
            $this->validateRequiredFields($data, ['value']);

            $payload = [
                'monto' => (int) round((float) $data['value']),
                'moneda' => $data['currency'] ?? 'PYG',
                'descripcion' => $data['description'] ?? 'Pagamento',
                'referencia' => $data['external_reference'] ?? uniqid('ref_'),
                'url_retorno' => $data['return_url'] ?? url('/pagar/callback'),
            ];

            if (!empty($data['customer_document'])) {
                $payload['comprador'] = [
                    'documento' => $data['customer_document'],
                    'nombre' => $data['customer_name'] ?? 'Cliente',
                    'email' => $data['customer_email'] ?? null,
                    'telefono' => $data['customer_phone'] ?? null,
                ];
            }

            $response = $this->makeApiRequest('POST', '/api/v1/pedidos', $payload, $this->credentials['private_key']);

            if (empty($response['id'])) {
                return ['success' => false, 'message' => $response['mensaje'] ?? 'Erro ao criar cobrança'];
            }

            $transactionId = $this->logTransaction($data['chave'] ?? '', $data['id_financeiro'] ?? null, 'charge', $response['id'], 'pending', (float) $data['value'], 'credit_card', $response, $response['url_pago'] ?? null);

            return [
                'success' => true,
                'external_id' => $response['id'],
                'status' => 'pending',
                'payment_url' => $response['url_pago'] ?? null,
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
            $response = $this->makeApiRequest('GET', "/api/v1/pedidos/{$externalId}", [], $this->credentials['private_key']);
            return [
                'success' => true,
                'status' => $this->mapStatus($response['estado'] ?? ''),
                'paid_at' => ($response['estado'] ?? '') === 'pagado' ? ($response['fecha_pago'] ?? now()) : null,
                'raw' => $response,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }

    public function refund(string $externalId, ?float $amount = null): array
    {
        try {
            $payload = $amount ? ['monto' => (int) round($amount)] : [];
            $response = $this->makeApiRequest('POST', "/api/v1/pedidos/{$externalId}/devolucion", $payload, $this->credentials['private_key']);
            return ['success' => true, 'refund_id' => $response['id'] ?? $externalId, 'raw' => $response];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }

    public function cancel(string $externalId): array
    {
        try {
            $response = $this->makeApiRequest('POST', "/api/v1/pedidos/{$externalId}/cancelar", [], $this->credentials['private_key']);
            return ['success' => true, 'raw' => $response];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }

    public function validateWebhookSignature(array $payload, array $headers): bool
    {
        $signature = $headers['X-PAGOPAR-SIGNATURE'] ?? $headers['x-pagopar-signature'] ?? '';
        if (empty($signature)) return true;
        $computed = hash_hmac('sha256', json_encode($payload), $this->credentials['private_key']);
        return hash_equals($computed, $signature);
    }

    public function parseWebhookPayload(array $payload): array
    {
        return [
            'event' => $payload['evento'] ?? 'payment',
            'external_id' => $payload['pedido']['id'] ?? '',
            'status' => $this->mapStatus($payload['pedido']['estado'] ?? ''),
            'paid_at' => $payload['pedido']['fecha_pago'] ?? null,
            'raw' => $payload,
        ];
    }

    public function getDocumentationUrl(): string { return 'https://developers.pagopar.com/'; }

    protected function mapStatus(string $gatewayStatus): string
    {
        return match (strtolower($gatewayStatus)) {
            'pagado', 'aprobado', 'completado' => 'paid',
            'pendiente', 'procesando' => 'pending',
            'rechazado', 'cancelado', 'expirado' => 'cancelled',
            'devuelto' => 'refunded',
            default => 'pending',
        };
    }

    protected function getBaseUrl(): string
    {
        return $this->sandbox ? 'https://sandbox.pagopar.com' : 'https://api.pagopar.com';
    }

    private function makeApiRequest(string $method, string $endpoint, array $data = [], ?string $token = null, bool $isAuthRequest = false): array
    {
        $url = str_starts_with($endpoint, 'http') ? $endpoint : $this->getBaseUrl() . $endpoint;

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($token) {
            $headers[] = "Authorization: Bearer {$token}";
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?: [];
    }
}
