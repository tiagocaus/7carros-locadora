<?php

namespace App\Services\Gateways;

/**
 * Gateway de pagamento Itaú
 *
 * Integração com a API do Itaú para BoleCode (PIX + Boleto).
 *
 * @see https://devportal.itau.com.br
 */
class ItauGateway extends AbstractPaymentGateway
{
    public function getCode(): string { return 'itau'; }
    public function getName(): string { return 'Itaú'; }
    public function getCountry(): string { return 'BR'; }
    public function getSupportedMethods(): array { return ['pix', 'boleto']; }

    public function getConfigSchema(): array
    {
        return [
            'client_id' => ['type' => 'string', 'required' => true, 'label' => 'Client ID', 'help' => 'ID do cliente no Itaú Developers'],
            'client_secret' => ['type' => 'password', 'required' => true, 'label' => 'Client Secret', 'help' => 'Chave secreta'],
            'agencia' => ['type' => 'string', 'required' => true, 'label' => 'Agência', 'help' => 'Número da agência'],
            'conta' => ['type' => 'string', 'required' => true, 'label' => 'Conta', 'help' => 'Número da conta'],
        ];
    }

    public function getCertificateConfig(): ?array
    {
        return ['required' => true, 'formats' => ['pfx', 'p12', 'pem', 'crt', 'cer']];
    }

    public function validateCredentials(array $credentials): array
    {
        if (empty($credentials['client_id']) || empty($credentials['client_secret'])) {
            return ['valid' => false, 'message' => 'Client ID e Client Secret são obrigatórios'];
        }
        if (empty($credentials['certificado_arquivo']) && empty($credentials['certificate_path'])) {
            return ['valid' => false, 'message' => 'Certificado digital é obrigatório'];
        }
        try {
            $token = $this->getAccessToken();
            return $token ? ['valid' => true, 'message' => 'Credenciais válidas'] : ['valid' => false, 'message' => 'Não foi possível obter token'];
        } catch (\Exception $e) {
            return ['valid' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }

    public function createCharge(array $data): array
    {
        try {
            $this->validateRequiredFields($data, ['value', 'billing_type']);
            $token = $this->getAccessToken();
            if (!$token) return ['success' => false, 'message' => 'Não foi possível autenticar'];

            $payload = [
                'etapa_processo_boleto' => 'efetivacao',
                'beneficiario' => [
                    'id_beneficiario' => $this->credentials['id_beneficiario'] ?? '',
                ],
                'dado_boleto' => [
                    'tipo_boleto' => 'a vista',
                    'codigo_carteira' => '109',
                    'valor_total_titulo' => number_format((float) $data['value'], 2, '.', ''),
                    'data_vencimento' => $this->resolveDueDate($data['due_date'] ?? null),
                ],
            ];

            if (!empty($data['customer_document'])) {
                $doc = $this->sanitizeDocument($data['customer_document']);
                $payload['pagador'] = [
                    'pessoa' => [
                        'nome_pessoa' => $data['customer_name'] ?? 'Cliente',
                        'tipo_pessoa' => ['codigo_tipo_pessoa' => strlen($doc) === 11 ? 'F' : 'J'],
                        'numero_cadastro_pessoa_fisica' => strlen($doc) === 11 ? $doc : null,
                        'numero_cadastro_nacional_pessoa_juridica' => strlen($doc) === 14 ? $doc : null,
                    ],
                ];
            }

            $response = $this->makeApiRequest('POST', '/boletos', $payload, $token);

            if (empty($response['dado_boleto']['numero_nosso_numero'])) {
                return ['success' => false, 'message' => $response['mensagem'] ?? 'Erro ao criar boleto'];
            }

            $externalId = $response['dado_boleto']['numero_nosso_numero'];
            $transactionId = $this->logTransaction($data['chave'] ?? '', $data['id_financeiro'] ?? null, 'charge', $externalId, 'pending', (float) $data['value'], strtolower($data['billing_type']), $response);

            return [
                'success' => true,
                'external_id' => $externalId,
                'status' => 'pending',
                'barcode' => $response['dado_boleto']['texto_codigo_barras'] ?? null,
                'pix_code' => $response['dado_qrcode']['emv'] ?? null,
                'pix_qrcode' => $response['dado_qrcode']['imagem_qrcode'] ?? null,
                'boleto_url' => $response['dado_boleto']['url_boleto'] ?? null,
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
            $response = $this->makeApiRequest('GET', "/boletos/{$externalId}", [], $token);
            return ['success' => true, 'status' => $this->mapStatus($response['dado_boleto']['codigo_situacao'] ?? ''), 'raw' => $response];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }

    public function refund(string $externalId, ?float $amount = null): array
    {
        return ['success' => false, 'message' => 'Estorno não suportado para boletos Itaú'];
    }

    public function cancel(string $externalId): array
    {
        try {
            $token = $this->getAccessToken();
            if (!$token) return ['success' => false, 'message' => 'Não foi possível autenticar'];
            $response = $this->makeApiRequest('PATCH', "/boletos/{$externalId}/baixa", [], $token);
            return ['success' => true, 'raw' => $response];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }

    public function validateWebhookSignature(array $payload, array $headers): bool { return true; }

    public function parseWebhookPayload(array $payload): array
    {
        return [
            'event' => $payload['tipo_evento'] ?? 'unknown',
            'external_id' => $payload['dado_boleto']['numero_nosso_numero'] ?? '',
            'status' => $this->mapStatus($payload['dado_boleto']['codigo_situacao'] ?? ''),
            'paid_at' => $payload['data_pagamento'] ?? null,
            'raw' => $payload,
        ];
    }

    public function getDocumentationUrl(): string { return 'https://devportal.itau.com.br'; }

    /**
     * Faz requisição HTTP com certificado mTLS para Itaú
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

        // Upload gerenciado; caminhos legados permanecem somente para transição.
        $storedCertificate = $this->prepareStoredCertificate();
        $certPath = $storedCertificate['certPath'] ?? ($this->credentials['certificate_path'] ?? '');
        $keyPath = $storedCertificate['keyPath'] ?? ($this->credentials['private_key_path'] ?? '');

        if (!empty($certPath) && file_exists($certPath)) {
            $curlOptions[CURLOPT_SSLCERT] = $certPath;
        }

        if (!empty($keyPath) && file_exists($keyPath)) {
            $curlOptions[CURLOPT_SSLKEY] = $keyPath;
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

        try {
            $response = curl_exec($ch);
            $error = curl_error($ch);
        } finally {
            curl_close($ch);
            $this->cleanupStoredCertificate($storedCertificate);
        }

        if ($error) {
            throw new \RuntimeException("Erro cURL: {$error}");
        }

        return json_decode($response, true) ?: [];
    }

    protected function mapStatus(string $gatewayStatus): string
    {
        return match (strtoupper($gatewayStatus)) {
            'PAGO', 'LIQUIDADO', '06', '17' => 'paid',
            'EMABERTO', 'REGISTRADO', '01', '02' => 'pending',
            'BAIXADO', 'CANCELADO', '09', '10' => 'cancelled',
            default => 'pending',
        };
    }

    protected function getBaseUrl(): string
    {
        return $this->sandbox ? 'https://devportal.itau.com.br/sandboxapi/cash_management_ext_v2' : 'https://secure.api.itau/cash_management_ext/v2';
    }

    private function getAccessToken(): ?string
    {
        $authUrl = $this->sandbox ? 'https://devportal.itau.com.br/api/jwt' : 'https://sts.itau.com.br/api/oauth/token';
        $response = $this->makeApiRequest('POST', $authUrl, [
            'grant_type' => 'client_credentials',
            'client_id' => $this->credentials['client_id'] ?? '',
            'client_secret' => $this->credentials['client_secret'] ?? '',
        ], null, true);
        return $response['access_token'] ?? null;
    }
}
