<?php

namespace App\Services\Gateways;

use SimpleSoftwareIO\QrCode\Generator as QrCodeGenerator;

/**
 * Gateway de pagamento Banco Inter
 *
 * Integração com a API do Banco Inter para PIX e Boleto.
 * Implementação própria via REST API.
 *
 * @see https://developers.inter.co/
 */
class InterGateway extends AbstractPaymentGateway
{
    private string $grantedScopes = '';
    private ?string $accessToken = null;
    private int $accessTokenExpiresAt = 0;

    /**
     * {@inheritdoc}
     */
    public function getCode(): string
    {
        return 'inter';
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'Banco Inter';
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
                'help' => 'ID do cliente disponível no Internet Banking',
            ],
            'client_secret' => [
                'type' => 'password',
                'required' => true,
                'label' => 'Client Secret',
                'placeholder' => 'Seu Client Secret',
                'help' => 'Chave secreta do cliente',
            ],
            'conta_corrente' => [
                'type' => 'string',
                'required' => true,
                'label' => 'Conta Corrente',
                'placeholder' => '12345678',
                'help' => 'Número da conta corrente Inter',
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
                    'message' => 'Não foi possível autenticar com o Banco Inter',
                ];
            }

            $billingType = strtoupper($data['billing_type']);

            if ($billingType === 'PIX') {
                return $this->createPixCharge($data, $token);
            }

            return $this->createBoletoCharge($data, $token);

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao criar cobrança: ' . $e->getMessage(),
                'raw' => [],
            ];
        }
    }

    /**
     * Cria cobrança PIX
     */
    private function createPixCharge(array $data, string $token): array
    {
        $vencimento = $this->resolveDueDate($data['due_date'] ?? null);

        $payload = [
            'seuNumero' => substr(preg_replace('/[^a-zA-Z0-9]/', '', uniqid('pix', true)), 0, 15),
            'valorNominal' => (float) $data['value'],
            'dataVencimento' => $vencimento,
            'numDiasAgenda' => 3,
        ];

        // Pagador é obrigatório na API Cobrança v3 (incluindo endereço)
        $doc = !empty($data['customer_document']) ? $this->sanitizeDocument($data['customer_document']) : '';
        $payload['pagador'] = [
            'cpfCnpj' => $doc ?: '00000000000',
            'nome' => $data['customer_name'] ?? 'Cliente',
            'tipoPessoa' => strlen($doc) === 14 ? 'JURIDICA' : 'FISICA',
            'endereco' => !empty($data['customer_address']) ? $data['customer_address'] : 'Nao informado',
            'cidade' => !empty($data['customer_city']) ? $data['customer_city'] : 'Nao informado',
            'uf' => !empty($data['customer_state']) ? $data['customer_state'] : 'SP',
            'cep' => !empty($data['customer_zip']) ? $data['customer_zip'] : '00000000',
        ];
        if (!empty($data['customer_email'])) {
            $payload['pagador']['email'] = $data['customer_email'];
        }

        $response = $this->makeApiRequest('POST', '/cobranca/v3/cobrancas', $payload, $token);

        if (empty($response['codigoSolicitacao'])) {
            $httpCode = $response['_http_code'] ?? 0;
            $rawBody = $response['_raw_body'] ?? '';
            $errorMsg = $response['detail']
                ?? $response['message']
                ?? $response['title']
                ?? null;

            if (!$errorMsg) {
                $errorMsg = "Erro ao criar PIX (HTTP {$httpCode})";
                if ($rawBody && $rawBody !== '{}') {
                    $errorMsg .= ': ' . substr($rawBody, 0, 300);
                }
            }

            if (!empty($response['violacoes'])) {
                $violations = array_map(function($v) {
                    $prop = $v['propriedade'] ?? '';
                    $razao = $v['razao'] ?? $v['motivo'] ?? '';
                    return $prop ? "[{$prop}] {$razao}" : $razao;
                }, $response['violacoes']);
                $errorMsg .= ' - ' . implode('; ', array_filter($violations));
            }

            return [
                'success' => false,
                'message' => $errorMsg,
                'http_code' => $httpCode,
                'raw' => $response,
            ];
        }

        $codigoSolicitacao = $response['codigoSolicitacao'];
        $detailResponse = $this->getCobrancaDetalhada($codigoSolicitacao, $token);
        $pixCode = $detailResponse['pix']['pixCopiaECola'] ?? null;
        $pixTxid = $detailResponse['pix']['txid'] ?? null;
        $pixQrcode = $pixCode ? $this->generatePixQrCodeDataUri($pixCode) : null;
        $barcode = $detailResponse['boleto']['linhaDigitavel'] ?? $response['linhaDigitavel'] ?? null;
        $boletoUrl = $detailResponse['boleto']['urlBoleto'] ?? $response['urlBoleto'] ?? null;

        if (empty($pixCode)) {
            return [
                'success' => false,
                'message' => 'PIX criado no Banco Inter, mas o código copia e cola não foi retornado na consulta da cobrança.',
                'http_code' => $detailResponse['_http_code'] ?? 500,
                'raw' => [
                    'create' => $response,
                    'detail' => $detailResponse,
                ],
            ];
        }

        $status = 'pending';
        $rawPayload = [
            'create' => $response,
            'detail' => $detailResponse,
        ];

        $transactionId = $this->logTransaction(
            $data['chave'] ?? '',
            $data['id_financeiro'] ?? null,
            'charge',
            $codigoSolicitacao,
            $status,
            (float) $data['value'],
            'pix',
            $rawPayload,
            null,
            $pixCode,
            $barcode,
            $vencimento
        );

        return [
            'success' => true,
            'external_id' => $codigoSolicitacao,
            'pix_txid' => $pixTxid,
            'status' => $status,
            'pix_code' => $pixCode,
            'pix_qrcode' => $pixQrcode,
            'barcode' => $barcode,
            'boleto_url' => $boletoUrl,
            'transaction_id' => $transactionId,
            'raw' => $rawPayload,
        ];
    }

    /**
     * Cria cobrança Boleto
     */
    private function createBoletoCharge(array $data, string $token): array
    {
        $vencimento = $this->resolveDueDate($data['due_date'] ?? null);

        $payload = [
            'seuNumero' => $data['external_reference'] ?? uniqid(),
            'valorNominal' => (float) $data['value'],
            'dataVencimento' => $vencimento,
            'numDiasAgenda' => 30,
        ];

        if (!empty($data['customer_document'])) {
            $doc = $this->sanitizeDocument($data['customer_document']);
            $payload['pagador'] = [
                'cpfCnpj' => $doc,
                'nome' => $data['customer_name'] ?? 'Cliente',
                'tipoPessoa' => strlen($doc) === 11 ? 'FISICA' : 'JURIDICA',
            ];

            if (!empty($data['customer_email'])) {
                $payload['pagador']['email'] = $data['customer_email'];
            }
        }

        if (!empty($data['description'])) {
            $payload['desconto'] = ['codigoDesconto' => 'NAOTEMDESCONTO'];
            $payload['multa'] = ['codigoMulta' => 'NAOTEMMULTA'];
            $payload['mora'] = ['codigoMora' => 'ISENTO'];
        }

        $response = $this->makeApiRequest('POST', '/cobranca/v3/cobrancas', $payload, $token);

        if (empty($response['codigoSolicitacao'])) {
            return [
                'success' => false,
                'message' => $response['message'] ?? 'Erro ao criar boleto',
            ];
        }

        $transactionId = $this->logTransaction(
            $data['chave'] ?? '',
            $data['id_financeiro'] ?? null,
            'charge',
            $response['codigoSolicitacao'],
            'pending',
            (float) $data['value'],
            'boleto',
            $response,
            null,
            null,
            $response['linhaDigitavel'] ?? null,
            $vencimento
        );

        return [
            'success' => true,
            'external_id' => $response['codigoSolicitacao'],
            'status' => 'pending',
            'barcode' => $response['linhaDigitavel'] ?? null,
            'boleto_url' => $response['urlBoleto'] ?? null,
            'transaction_id' => $transactionId,
            'raw' => $response,
        ];
    }

    /**
     * Recupera a cobranca detalhada para obter o pixCopiaECola.
     */
    private function getCobrancaDetalhada(string $codigoSolicitacao, string $token): array
    {
        $lastResponse = [];

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $lastResponse = $this->makeApiRequest(
                'GET',
                '/cobranca/v3/cobrancas/' . rawurlencode($codigoSolicitacao),
                [],
                $token
            );

            if (!empty($lastResponse['pix']['pixCopiaECola'])) {
                return $lastResponse;
            }

            if ($attempt < 3) {
                usleep(300000);
            }
        }

        return $lastResponse;
    }

    /**
     * Gera um QR Code renderizavel no modal a partir do copia e cola PIX.
     */
    private function generatePixQrCodeDataUri(string $pixCode): ?string
    {
        try {
            $svg = (string) (new QrCodeGenerator())
                ->format('svg')
                ->size(260)
                ->margin(1)
                ->generate($pixCode);

            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Throwable) {
            return null;
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
                return ['success' => false, 'message' => 'Não foi possível autenticar'];
            }

            // Verificar se é PIX ou Boleto pelo formato do ID
            if (strlen($externalId) <= 35 && !str_contains($externalId, '-')) {
                $response = $this->makeApiRequest('GET', "/pix/v2/cob/{$externalId}", [], $token);
            } else {
                return $this->getCobrancaV3ChargeStatus($externalId);
            }

            if (($response['_http_code'] ?? 0) >= 400) {
                return [
                    'success' => false,
                    'message' => $response['detail'] ?? $response['message'] ?? $response['title'] ?? 'Erro ao consultar cobrança no Banco Inter',
                    'raw' => $response,
                ];
            }

            return [
                'success' => true,
                'status' => $this->mapStatus($this->extractGatewayStatus($response)),
                'paid_at' => $this->extractPaidAt($response),
                'raw' => $response,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao consultar: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Consulta uma cobranca emitida pela API Cobranca v3 usando codigoSolicitacao.
     */
    public function getCobrancaV3ChargeStatus(string $codigoSolicitacao): array
    {
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                return ['success' => false, 'message' => 'Não foi possível autenticar'];
            }

            $endpoint = '/cobranca/v3/cobrancas/' . rawurlencode($codigoSolicitacao);
            $response = $this->makeApiRequest('GET', $endpoint, [], $token);

            if (in_array((int) ($response['_http_code'] ?? 0), [401, 403], true)) {
                $token = $this->getAccessToken(true);
                if (!$token) {
                    return ['success' => false, 'message' => 'Não foi possível autenticar'];
                }
                $response = $this->makeApiRequest('GET', $endpoint, [], $token);
            }

            if (($response['_http_code'] ?? 0) >= 400) {
                return [
                    'success' => false,
                    'message' => $response['detail'] ?? $response['message'] ?? $response['title'] ?? 'Erro ao consultar cobrança no Banco Inter',
                    'raw' => $response,
                ];
            }

            return [
                'success' => true,
                'status' => $this->mapStatus($this->extractGatewayStatus($response)),
                'gateway_status' => $this->extractGatewayStatus($response),
                'paid_at' => $this->extractPaidAt($response),
                'raw' => $response,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao consultar cobrança: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Recupera dados de pagamento PIX de uma cobranca ja emitida.
     */
    public function getPixPaymentData(string $codigoSolicitacao): array
    {
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                return ['success' => false, 'message' => 'Não foi possível autenticar'];
            }

            $detailResponse = $this->getCobrancaDetalhada($codigoSolicitacao, $token);
            $pixCode = $detailResponse['pix']['pixCopiaECola'] ?? null;

            if (empty($pixCode)) {
                return [
                    'success' => false,
                    'message' => 'Código PIX não disponível para esta cobrança.',
                    'http_code' => $detailResponse['_http_code'] ?? 500,
                    'raw' => $detailResponse,
                ];
            }

            return [
                'success' => true,
                'external_id' => $codigoSolicitacao,
                'pix_txid' => $detailResponse['pix']['txid'] ?? null,
                'status' => $this->mapStatus($detailResponse['cobranca']['situacao'] ?? ''),
                'pix_code' => $pixCode,
                'pix_qrcode' => $this->generatePixQrCodeDataUri($pixCode),
                'raw' => $detailResponse,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao recuperar PIX: ' . $e->getMessage(),
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
                return ['success' => false, 'message' => 'Não foi possível autenticar'];
            }

            $e2eid = $this->credentials['e2eid'] ?? '';
            $payload = [
                'valor' => $amount ? $this->formatAmount($amount) : null,
            ];

            $response = $this->makeApiRequest('PUT', "/pix/v2/pix/{$e2eid}/devolucao/{$externalId}", $payload, $token);

            return [
                'success' => true,
                'refund_id' => $response['id'] ?? $externalId,
                'raw' => $response,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao estornar: ' . $e->getMessage(),
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
                return ['success' => false, 'message' => 'Não foi possível autenticar'];
            }

            $response = $this->makeApiRequest('POST', "/cobranca/v3/cobrancas/{$externalId}/cancelar", ['motivoCancelamento' => 'APEDIDODOCLIENTE'], $token);

            return [
                'success' => true,
                'raw' => $response,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao cancelar: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateWebhookSignature(array $payload, array $headers): bool
    {
        return true; // mTLS valida
    }

    /**
     * {@inheritdoc}
     */
    public function parseWebhookPayload(array $payload): array
    {
        $pix = $payload['pix'][0] ?? $payload;
        return [
            'event' => 'payment',
            'external_id' => $pix['txid'] ?? $payload['codigoSolicitacao'] ?? '',
            'status' => $this->mapStatus($pix['status'] ?? $payload['situacao'] ?? ''),
            'paid_at' => $pix['horario'] ?? $payload['dataPagamento'] ?? null,
            'raw' => $payload,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getDocumentationUrl(): string
    {
        return 'https://developers.inter.co/';
    }

    /**
     * {@inheritdoc}
     */
    protected function mapStatus(string $gatewayStatus): string
    {
        return match (strtoupper($gatewayStatus)) {
            'CONCLUIDA', 'PAGO', 'RECEBIDO', 'LIQUIDADO' => 'paid',
            'ATIVA', 'EMABERTO', 'PENDENTE', 'AGUARDANDO' => 'pending',
            'REMOVIDA_PELO_USUARIO_RECEBEDOR', 'CANCELADO', 'BAIXADO', 'EXPIRADO' => 'cancelled',
            default => 'pending',
        };
    }

    private function extractGatewayStatus(array $response): string
    {
        return (string) (
            $response['cobranca']['situacao']
            ?? $response['status']
            ?? $response['situacao']
            ?? ''
        );
    }

    private function extractPaidAt(array $response): ?string
    {
        return $response['cobranca']['dataSituacao']
            ?? $response['dataPagamento']
            ?? $response['horario']
            ?? null;
    }

    /**
     * {@inheritdoc}
     */
    protected function getBaseUrl(): string
    {
        if ($this->sandbox) {
            return 'https://cdpj-sandbox.partners.bancointer.com.br';
        }
        return env('INTER_BASE_URL', '') ?: 'https://cdpj.partners.bancointer.com.br';
    }

    /**
     * Faz requisição HTTP com certificado mTLS para Banco Inter
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
            $postData = !empty($data) ? json_encode($data, JSON_PRESERVE_ZERO_FRACTION) : '';
        }

        if ($token) {
            $headers[] = "Authorization: Bearer {$token}";
        }

        // Adicionar header da conta corrente para requisições autenticadas
        if (!$isAuthRequest && !empty($this->credentials['conta_corrente'])) {
            $headers[] = "x-conta-corrente: {$this->credentials['conta_corrente']}";
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
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        } finally {
            curl_close($ch);
            $this->cleanupStoredCertificate($storedCertificate);
        }

        if ($error) {
            throw new \RuntimeException("Erro cURL: {$error}");
        }

        $decoded = json_decode($response, true) ?: [];
        $decoded['_http_code'] = $httpCode;
        $decoded['_raw_body'] = $response;

        return $decoded;
    }

    /**
     * Obtém token de acesso OAuth
     */
    private function getAccessToken(bool $forceRefresh = false): ?string
    {
        if (!$forceRefresh && $this->accessToken && time() < $this->accessTokenExpiresAt) {
            return $this->accessToken;
        }

        $clientId = $this->credentials['client_id'] ?? '';
        $clientSecret = $this->credentials['client_secret'] ?? '';

        $authUrl = $this->getBaseUrl() . '/oauth/v2/token';

        $response = $this->makeApiRequest('POST', $authUrl, [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => 'boleto-cobranca.write boleto-cobranca.read',
        ], null, true);

        $token = $response['access_token'] ?? null;

        if ($token) {
            $expiresIn = max(60, (int) ($response['expires_in'] ?? 3600));
            $this->accessToken = $token;
            $this->accessTokenExpiresAt = time() + $expiresIn - 30;
            $this->grantedScopes = $response['scope'] ?? '';
        } else {
            $this->accessToken = null;
            $this->accessTokenExpiresAt = 0;
        }

        return $token;
    }
}
