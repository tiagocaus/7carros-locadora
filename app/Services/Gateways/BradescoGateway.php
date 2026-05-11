<?php

namespace App\Services\Gateways;

/**
 * Gateway de pagamento Bradesco
 *
 * Integração com a API do Bradesco para Boleto e PIX.
 *
 * @see https://developers.bradesco.com.br/
 */
class BradescoGateway extends AbstractPaymentGateway
{
    public function getCode(): string { return 'bradesco'; }
    public function getName(): string { return 'Bradesco'; }
    public function getCountry(): string { return 'BR'; }
    public function getSupportedMethods(): array { return ['pix', 'boleto']; }

    public function getConfigSchema(): array
    {
        return [
            'client_id' => ['type' => 'string', 'required' => true, 'label' => 'Client ID', 'help' => 'ID do cliente no Bradesco Developers'],
            'client_secret' => ['type' => 'password', 'required' => true, 'label' => 'Client Secret', 'help' => 'Chave secreta'],
            'certificate_path' => ['type' => 'string', 'required' => true, 'label' => 'Caminho do Certificado', 'help' => 'Caminho para o certificado .pfx'],
            'certificate_password' => ['type' => 'password', 'required' => true, 'label' => 'Senha do Certificado', 'help' => 'Senha do certificado digital'],
            'merchant_id' => ['type' => 'string', 'required' => true, 'label' => 'Merchant ID', 'help' => 'ID do estabelecimento'],
        ];
    }

    public function validateCredentials(array $credentials): array
    {
        if (empty($credentials['client_id']) || empty($credentials['client_secret'])) {
            return ['valid' => false, 'message' => 'Client ID e Client Secret são obrigatórios'];
        }
        try {
            $token = $this->getAccessToken();
            return $token ? ['valid' => true, 'message' => 'Credenciais válidas'] : ['valid' => false, 'message' => 'Não foi possível obter token'];
        } catch (\Exception $e) {
            return ['valid' => false, 'message' => 'Erro ao validar: ' . $e->getMessage()];
        }
    }

    public function createCharge(array $data): array
    {
        try {
            $this->validateRequiredFields($data, ['value', 'billing_type']);
            $token = $this->getAccessToken();
            if (!$token) return ['success' => false, 'message' => 'Não foi possível autenticar'];

            $billingType = strtoupper($data['billing_type']);
            $payload = [
                'valor' => (float) $data['value'],
                'vencimento' => $data['due_date'] ?? date('Y-m-d', strtotime('+3 days')),
            ];

            if (!empty($data['customer_document'])) {
                $doc = $this->sanitizeDocument($data['customer_document']);
                $payload['pagador'] = [
                    'documento' => $doc,
                    'nome' => $data['customer_name'] ?? 'Cliente',
                ];
            }

            $endpoint = $billingType === 'PIX' ? '/pix/v1/cobranca' : '/boleto/v1/registrar';
            $response = $this->makeApiRequest('POST', $endpoint, $payload, $token);

            if (empty($response['id'])) {
                return ['success' => false, 'message' => $response['message'] ?? 'Erro ao criar cobrança'];
            }

            $transactionId = $this->logTransaction($data['chave'] ?? '', $data['id_financeiro'] ?? null, 'charge', $response['id'], 'pending', (float) $data['value'], strtolower($billingType), $response);

            return [
                'success' => true,
                'external_id' => $response['id'],
                'status' => 'pending',
                'pix_code' => $response['qrcode'] ?? null,
                'pix_qrcode' => $response['qrcodeImagem'] ?? null,
                'barcode' => $response['linhaDigitavel'] ?? null,
                'boleto_url' => $response['urlBoleto'] ?? null,
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
            $token = $this->getAccessToken();
            if (!$token) return ['success' => false, 'message' => 'Não foi possível autenticar'];
            $response = $this->makeApiRequest('GET', "/cobranca/v1/{$externalId}", [], $token);
            return ['success' => true, 'status' => $this->mapStatus($response['status'] ?? ''), 'raw' => $response];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }

    public function refund(string $externalId, ?float $amount = null): array
    {
        try {
            $token = $this->getAccessToken();
            if (!$token) return ['success' => false, 'message' => 'Não foi possível autenticar'];
            $response = $this->makeApiRequest('POST', "/cobranca/v1/{$externalId}/estorno", $amount ? ['valor' => $amount] : [], $token);
            return ['success' => true, 'refund_id' => $response['id'] ?? $externalId, 'raw' => $response];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }

    public function cancel(string $externalId): array
    {
        try {
            $token = $this->getAccessToken();
            if (!$token) return ['success' => false, 'message' => 'Não foi possível autenticar'];
            $response = $this->makeApiRequest('POST', "/cobranca/v1/{$externalId}/cancelar", [], $token);
            return ['success' => true, 'raw' => $response];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }

    public function validateWebhookSignature(array $payload, array $headers): bool { return true; }

    public function parseWebhookPayload(array $payload): array
    {
        return [
            'event' => $payload['evento'] ?? 'unknown',
            'external_id' => $payload['dados']['id'] ?? '',
            'status' => $this->mapStatus($payload['dados']['status'] ?? ''),
            'paid_at' => $payload['dados']['dataPagamento'] ?? null,
            'raw' => $payload,
        ];
    }

    public function getDocumentationUrl(): string { return 'https://developers.bradesco.com.br/'; }

    /**
     * Faz requisição HTTP com certificado mTLS para Bradesco
     *
     * @param string $method Método HTTP
     * @param string $endpoint Endpoint da API
     * @param array $data Dados para enviar
     * @param string|null $token Token de autenticação
     * @param bool $isAuthRequest Se é requisição de autenticação
     * @return array
     */
    private function makeApiRequest(
        string $method,
        string $endpoint,
        array $data = [],
        ?string $token = null,
        bool $isAuthRequest = false
    ): array {
        $url = str_starts_with($endpoint, 'http') ? $endpoint : $this->getBaseUrl() . $endpoint;

        $headers = ['Accept: application/json'];

        if ($isAuthRequest) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $postData = http_build_query($data);
        } else {
            $headers[] = 'Content-Type: application/json';
            $postData = !empty($data) ? json_encode($data) : '';
        }

        if ($token) {
            $headers[] = "Authorization: Bearer {$token}";
        }

        $ch = curl_init();

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        // Configurar certificado
        $certPath = $this->credentials['certificate_path'] ?? '';
        $certPassword = $this->credentials['certificate_password'] ?? '';

        if (!empty($certPath) && file_exists($certPath)) {
            $curlOptions[CURLOPT_SSLCERT] = $certPath;
            $curlOptions[CURLOPT_SSLCERTTYPE] = 'P12';
            if (!empty($certPassword)) {
                $curlOptions[CURLOPT_SSLCERTPASSWD] = $certPassword;
            }
        }

        switch (strtoupper($method)) {
            case 'POST':
                $curlOptions[CURLOPT_POST] = true;
                $curlOptions[CURLOPT_POSTFIELDS] = $postData;
                break;
            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                $curlOptions[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
                if (!empty($postData)) {
                    $curlOptions[CURLOPT_POSTFIELDS] = $postData;
                }
                break;
        }

        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("Erro cURL: {$error}");
        }

        return json_decode($response, true) ?: [];
    }

    protected function mapStatus(string $gatewayStatus): string
    {
        return match (strtoupper($gatewayStatus)) {
            'PAGO', 'LIQUIDADO', 'CONFIRMADO' => 'paid',
            'PENDENTE', 'AGUARDANDO', 'EMITIDO' => 'pending',
            'CANCELADO', 'BAIXADO', 'EXPIRADO' => 'cancelled',
            default => 'pending',
        };
    }

    protected function getBaseUrl(): string
    {
        return $this->sandbox ? 'https://proxy.api.prebanco.com.br' : 'https://openapi.bradesco.com.br';
    }

    private function getAccessToken(): ?string
    {
        $response = $this->makeApiRequest('POST', $this->getBaseUrl() . '/auth/server/v1.1/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $this->credentials['client_id'] ?? '',
            'client_secret' => $this->credentials['client_secret'] ?? '',
        ], null, true);
        return $response['access_token'] ?? null;
    }
}
