<?php

namespace App\Services\Gateways;

/**
 * Gateway de pagamento Cora
 *
 * Integração com a API do Cora para PIX e Boleto.
 * Implementação própria via REST API.
 *
 * @see https://developers.cora.com.br/
 */
class CoraGateway extends AbstractPaymentGateway
{
    /**
     * {@inheritdoc}
     */
    public function getCode(): string
    {
        return 'cora';
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'Cora';
    }

    /**
     * {@inheritdoc}
     */
    public function getCountry(): string
    {
        return 'BR';
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedMethods(): array
    {
        return ['pix', 'boleto'];
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigSchema(): array
    {
        return [
            'client_id' => [
                'type' => 'string',
                'required' => true,
                'label' => 'Client ID',
                'placeholder' => 'Seu Client ID',
                'help' => 'ID do cliente disponível no painel Cora',
            ],
            'client_secret' => [
                'type' => 'password',
                'required' => true,
                'label' => 'Client Secret',
                'placeholder' => 'Seu Client Secret',
                'help' => 'Chave secreta do cliente',
            ],
        ];
    }

    public function getCertificateConfig(): ?array
    {
        return ['required' => true, 'formats' => ['pfx', 'p12', 'pem', 'crt', 'cer']];
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(array $credentials): array
    {
        if (empty($credentials['client_id']) || empty($credentials['client_secret'])) {
            return [
                'valid' => false,
                'message' => 'Client ID e Client Secret são obrigatórios',
            ];
        }

        if (empty($credentials['certificado_arquivo']) && empty($credentials['certificate_path'])) {
            return ['valid' => false, 'message' => 'Certificado digital é obrigatório'];
        }

        try {
            $token = $this->getAccessToken();

            if ($token) {
                return [
                    'valid' => true,
                    'message' => 'Credenciais válidas',
                ];
            }

            return [
                'valid' => false,
                'message' => 'Não foi possível obter token de acesso',
            ];

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => 'Erro ao validar: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createCharge(array $data): array
    {
        try {
            $this->validateRequiredFields($data, ['value', 'billing_type']);

            $token = $this->getAccessToken();
            if (!$token) {
                return [
                    'success' => false,
                    'message' => 'Não foi possível autenticar com o Cora',
                ];
            }

            $billingType = strtoupper($data['billing_type']);

            // Preparar dados do pagador
            $payer = [];
            if (!empty($data['customer_document'])) {
                $doc = $this->sanitizeDocument($data['customer_document']);
                $payer['cpf_cnpj'] = $doc;
                $payer['name'] = $data['customer_name'] ?? 'Cliente';
            }

            $payload = [
                'amount' => $this->toCents((float) $data['value']),
                'description' => $data['description'] ?? 'Pagamento',
                'due_date' => $this->resolveDueDate($data['due_date'] ?? null),
            ];

            if (!empty($payer)) {
                $payload['payer'] = $payer;
            }

            $endpoint = $billingType === 'PIX' ? '/invoices/pix' : '/invoices/boleto';
            $response = $this->makeApiRequest('POST', $endpoint, $payload, $token);

            if (empty($response['id'])) {
                return [
                    'success' => false,
                    'message' => $response['message'] ?? 'Erro ao criar cobrança no Cora',
                ];
            }

            $status = $this->mapStatus($response['status'] ?? 'PENDING');

            // Log da transação
            $transactionId = $this->logTransaction(
                $data['chave'] ?? '',
                $data['id_financeiro'] ?? null,
                'charge',
                $response['id'],
                $status,
                (float) $data['value'],
                strtolower($billingType),
                $response,
                $response['payment_url'] ?? null,
                $response['pix']['qr_code'] ?? null,
                $response['boleto']['barcode'] ?? null,
                $response['due_date'] ?? null
            );

            return [
                'success' => true,
                'external_id' => $response['id'],
                'status' => $status,
                'pix_code' => $response['pix']['qr_code'] ?? null,
                'pix_qrcode' => $response['pix']['qr_code_image'] ?? null,
                'barcode' => $response['boleto']['barcode'] ?? null,
                'boleto_url' => $response['boleto']['url'] ?? null,
                'payment_url' => $response['payment_url'] ?? null,
                'expires_at' => $response['due_date'] ?? null,
                'transaction_id' => $transactionId,
                'raw' => $response,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao criar cobrança: ' . $e->getMessage(),
                'raw' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getChargeStatus(string $externalId): array
    {
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                return [
                    'success' => false,
                    'message' => 'Não foi possível autenticar',
                ];
            }

            $response = $this->makeApiRequest('GET', "/invoices/{$externalId}", [], $token);

            return [
                'success' => true,
                'status' => $this->mapStatus($response['status'] ?? ''),
                'paid_at' => $response['paid_at'] ?? null,
                'raw' => $response,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao consultar: ' . $e->getMessage(),
                'raw' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function refund(string $externalId, ?float $amount = null): array
    {
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                return [
                    'success' => false,
                    'message' => 'Não foi possível autenticar',
                ];
            }

            $payload = [];
            if ($amount !== null) {
                $payload['amount'] = $this->toCents($amount);
            }

            $response = $this->makeApiRequest('POST', "/invoices/{$externalId}/refund", $payload, $token);

            return [
                'success' => true,
                'refund_id' => $response['id'] ?? $externalId,
                'raw' => $response,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao estornar: ' . $e->getMessage(),
                'raw' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(string $externalId): array
    {
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                return [
                    'success' => false,
                    'message' => 'Não foi possível autenticar',
                ];
            }

            $response = $this->makeApiRequest('DELETE', "/invoices/{$externalId}", [], $token);

            return [
                'success' => true,
                'raw' => $response,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao cancelar: ' . $e->getMessage(),
                'raw' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateWebhookSignature(array $payload, array $headers): bool
    {
        // Cora valida via certificado mTLS, aceitar se passou
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function parseWebhookPayload(array $payload): array
    {
        return [
            'event' => $payload['event'] ?? 'unknown',
            'external_id' => $payload['data']['id'] ?? '',
            'status' => $this->mapStatus($payload['data']['status'] ?? ''),
            'paid_at' => $payload['data']['paid_at'] ?? null,
            'raw' => $payload,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getDocumentationUrl(): string
    {
        return 'https://developers.cora.com.br/';
    }

    /**
     * {@inheritdoc}
     */
    protected function mapStatus(string $gatewayStatus): string
    {
        return match (strtoupper($gatewayStatus)) {
            'PAID', 'CONFIRMED' => 'paid',
            'PENDING', 'CREATED', 'AWAITING_PAYMENT' => 'pending',
            'REFUNDED' => 'refunded',
            'CANCELED', 'CANCELLED', 'EXPIRED' => 'cancelled',
            default => 'pending',
        };
    }

    /**
     * {@inheritdoc}
     */
    protected function getBaseUrl(): string
    {
        return $this->sandbox
            ? 'https://api.sandbox.cora.com.br/v1'
            : 'https://api.cora.com.br/v1';
    }

    /**
     * Faz requisição HTTP com certificado mTLS para Cora
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

    /**
     * Obtém token de acesso OAuth
     */
    private function getAccessToken(): ?string
    {
        $clientId = $this->credentials['client_id'] ?? '';
        $clientSecret = $this->credentials['client_secret'] ?? '';

        $authUrl = $this->sandbox
            ? 'https://auth.sandbox.cora.com.br/oauth/token'
            : 'https://auth.cora.com.br/oauth/token';

        $response = $this->makeApiRequest('POST', $authUrl, [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ], null, true);

        return $response['access_token'] ?? null;
    }
}
