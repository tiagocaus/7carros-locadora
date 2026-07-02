<?php

namespace App\Services\Gateways;

use SimpleSoftwareIO\QrCode\Generator as QrCodeGenerator;

/**
 * Gateway de pagamento Sicoob
 *
 * Integração com APIs oficiais Sicoob para Pix Recebimentos e Cobrança
 * Bancária. Usa OAuth2 client credentials com mTLS e header client_id.
 *
 * @see https://developers.sicoob.com.br/portal/apis
 */
class SicoobGateway extends AbstractPaymentGateway
{
    private ?string $accessToken = null;
    private int $accessTokenExpiresAt = 0;

    public function getCode(): string
    {
        return 'sicoob';
    }

    public function getName(): string
    {
        return 'Sicoob';
    }

    public function getCountry(): string
    {
        return 'BR';
    }

    public function getSupportedMethods(): array
    {
        return ['pix', 'boleto'];
    }

    public function getConfigSchema(): array
    {
        return [
            'client_id' => [
                'type' => 'string',
                'required' => true,
                'label' => 'Client ID',
                'placeholder' => 'Client ID do aplicativo Sicoob',
                'help' => 'Identificador do aplicativo cadastrado no Portal Developers Sicoob',
            ],
            'client_secret' => [
                'type' => 'password',
                'required' => false,
                'label' => 'Client Secret',
                'placeholder' => 'Opcional',
                'help' => 'Informe apenas se o aplicativo Sicoob exigir secret no token OAuth',
            ],
            'certificate_path' => [
                'type' => 'string',
                'required' => true,
                'label' => 'Caminho do Certificado',
                'placeholder' => '/path/to/certificate.pem',
                'help' => 'Certificado mTLS em formato PEM/CRT',
            ],
            'private_key_path' => [
                'type' => 'string',
                'required' => true,
                'label' => 'Caminho da Chave Privada',
                'placeholder' => '/path/to/private_key.key',
                'help' => 'Chave privada do certificado mTLS',
            ],
            'private_key_password' => [
                'type' => 'password',
                'required' => false,
                'label' => 'Senha da Chave',
                'placeholder' => 'Opcional',
                'help' => 'Senha da chave privada, se houver',
            ],
            'pix_key' => [
                'type' => 'string',
                'required' => true,
                'label' => 'Chave PIX',
                'placeholder' => 'CPF/CNPJ, e-mail, telefone ou chave aleatória',
                'help' => 'Chave recebedora usada nas cobranças Pix',
            ],
            'numero_cliente' => [
                'type' => 'string',
                'required' => true,
                'label' => 'Número Cliente',
                'placeholder' => '25546454',
                'help' => 'Número do contrato de cobrança do beneficiário no Sisbr',
            ],
            'numero_conta_corrente' => [
                'type' => 'string',
                'required' => true,
                'label' => 'Conta Corrente',
                'placeholder' => '123456',
                'help' => 'Conta corrente onde será creditada a liquidação do boleto',
            ],
            'codigo_modalidade' => [
                'type' => 'string',
                'required' => true,
                'label' => 'Modalidade',
                'placeholder' => '1',
                'help' => 'Modalidade de cobrança. Normalmente 1 = simples com registro',
            ],
            'api_base_url' => [
                'type' => 'string',
                'required' => false,
                'label' => 'URL Base da API',
                'placeholder' => 'https://api.sicoob.com.br',
                'help' => 'Opcional. Use para homologação/sandbox quando a URL for fornecida pelo Sicoob',
            ],
            'auth_url' => [
                'type' => 'string',
                'required' => false,
                'label' => 'URL OAuth Token',
                'placeholder' => 'https://auth.sicoob.com.br/auth/realms/cooperado/protocol/openid-connect/token',
                'help' => 'Opcional. Use para homologação/sandbox quando a URL for fornecida pelo Sicoob',
            ],
            'x_client_certificate' => [
                'type' => 'textarea',
                'required' => false,
                'label' => 'X-Client-Certificate',
                'placeholder' => '-----BEGIN CERTIFICATE-----...',
                'help' => 'Opcional. Alguns ambientes exigem o certificado público também neste header',
            ],
        ];
    }

    public function validateCredentials(array $credentials): array
    {
        foreach (['client_id', 'certificate_path', 'private_key_path'] as $field) {
            if (empty($credentials[$field])) {
                return ['valid' => false, 'message' => 'Client ID, certificado e chave privada são obrigatórios'];
            }
        }

        try {
            $token = $this->getAccessToken(true);

            return $token
                ? ['valid' => true, 'message' => 'Credenciais válidas']
                : ['valid' => false, 'message' => 'Não foi possível obter token de acesso'];
        } catch (\Exception $e) {
            return ['valid' => false, 'message' => 'Erro ao validar: ' . $e->getMessage()];
        }
    }

    public function createCharge(array $data): array
    {
        try {
            $this->validateRequiredFields($data, ['value', 'billing_type']);

            $token = $this->getAccessToken();
            if (!$token) {
                return ['success' => false, 'message' => 'Não foi possível autenticar com o Sicoob'];
            }

            return strtoupper((string) $data['billing_type']) === 'PIX'
                ? $this->createPixCharge($data, $token)
                : $this->createBoletoCharge($data, $token);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao criar cobrança: ' . $e->getMessage(),
                'raw' => [],
            ];
        }
    }

    public function getChargeStatus(string $externalId): array
    {
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                return ['success' => false, 'message' => 'Não foi possível autenticar'];
            }

            if (str_starts_with($externalId, 'pix_')) {
                return $this->getPixChargeStatus(substr($externalId, 4), $token);
            }

            if (str_starts_with($externalId, 'bol_')) {
                return $this->getBoletoChargeStatus(substr($externalId, 4), $token);
            }

            return $this->getBoletoChargeStatus($externalId, $token);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao consultar: ' . $e->getMessage(),
                'raw' => [],
            ];
        }
    }

    public function refund(string $externalId, ?float $amount = null): array
    {
        try {
            if ($amount === null || $amount <= 0) {
                return ['success' => false, 'message' => 'Informe o valor da devolução PIX'];
            }

            $token = $this->getAccessToken();
            if (!$token) {
                return ['success' => false, 'message' => 'Não foi possível autenticar'];
            }

            $e2eid = str_starts_with($externalId, 'pix_') ? substr($externalId, 4) : $externalId;
            $refundId = $this->generateTxId('dev');
            $response = $this->makeApiRequest(
                'PUT',
                '/pix/api/v2/pix/' . rawurlencode($e2eid) . '/devolucao/' . rawurlencode($refundId),
                ['valor' => $this->formatAmount($amount)],
                $token
            );

            if (($response['_http_code'] ?? 0) >= 400) {
                return [
                    'success' => false,
                    'message' => $this->extractErrorMessage($response, 'Erro ao solicitar devolução PIX no Sicoob'),
                    'raw' => $response,
                ];
            }

            return [
                'success' => true,
                'refund_id' => $response['id'] ?? $refundId,
                'raw' => $response,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro ao estornar: ' . $e->getMessage()];
        }
    }

    public function cancel(string $externalId): array
    {
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                return ['success' => false, 'message' => 'Não foi possível autenticar'];
            }

            if (str_starts_with($externalId, 'pix_')) {
                $txid = substr($externalId, 4);
                $response = $this->makeApiRequest(
                    'PATCH',
                    '/pix/api/v2/cob/' . rawurlencode($txid),
                    ['status' => 'REMOVIDA_PELO_USUARIO_RECEBEDOR'],
                    $token
                );
            } else {
                $nossoNumero = str_starts_with($externalId, 'bol_') ? substr($externalId, 4) : $externalId;
                $response = $this->makeApiRequest(
                    'POST',
                    '/cobranca-bancaria/v3/boletos/' . rawurlencode($nossoNumero) . '/baixar',
                    [
                        'numeroCliente' => (int) ($this->credentials['numero_cliente'] ?? 0),
                        'codigoModalidade' => (int) ($this->credentials['codigo_modalidade'] ?? 1),
                    ],
                    $token
                );
            }

            if (($response['_http_code'] ?? 0) >= 400) {
                return [
                    'success' => false,
                    'message' => $this->extractErrorMessage($response, 'Erro ao cancelar cobrança no Sicoob'),
                    'raw' => $response,
                ];
            }

            return ['success' => true, 'raw' => $response];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro ao cancelar: ' . $e->getMessage()];
        }
    }

    public function validateWebhookSignature(array $payload, array $headers): bool
    {
        return true;
    }

    public function parseWebhookPayload(array $payload): array
    {
        $pix = $payload['pix'][0] ?? null;
        if (is_array($pix)) {
            return [
                'event' => 'pix.received',
                'external_id' => !empty($pix['txid']) ? 'pix_' . $pix['txid'] : '',
                'status' => 'paid',
                'paid_at' => $pix['horario'] ?? null,
                'raw' => $payload,
            ];
        }

        $nossoNumero = $payload['nossoNumero']
            ?? $payload['boleto']['nossoNumero']
            ?? $payload['dados']['nossoNumero']
            ?? $payload['numeroTitulo']
            ?? '';

        return [
            'event' => (string) ($payload['tipoMovimento'] ?? $payload['evento'] ?? 'boleto.notification'),
            'external_id' => $nossoNumero !== '' ? 'bol_' . $nossoNumero : '',
            'status' => $this->mapStatus((string) ($payload['situacao'] ?? $payload['status'] ?? $payload['tipoMovimento'] ?? '')),
            'paid_at' => $payload['dataPagamento'] ?? $payload['dataLiquidacao'] ?? null,
            'raw' => $payload,
        ];
    }

    public function getDocumentationUrl(): string
    {
        return 'https://developers.sicoob.com.br/portal/apis';
    }

    protected function mapStatus(string $gatewayStatus): string
    {
        return match (strtoupper($gatewayStatus)) {
            'CONCLUIDA', 'LIQUIDADO', 'LIQUIDADA', 'PAGO', 'PAGA', 'BAIXADO POR PAGAMENTO' => 'paid',
            'ATIVA', 'EM_ABERTO', 'EMABERTO', 'REGISTRADO', 'PENDENTE' => 'pending',
            'REMOVIDA_PELO_USUARIO_RECEBEDOR', 'REMOVIDA_PELO_PSP', 'BAIXADO', 'BAIXADA', 'CANCELADO', 'CANCELADA' => 'cancelled',
            'DEVOLVIDO', 'DEVOLVIDA' => 'refunded',
            default => 'pending',
        };
    }

    protected function getBaseUrl(): string
    {
        $configured = trim((string) ($this->credentials['api_base_url'] ?? ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return 'https://api.sicoob.com.br';
    }

    private function createPixCharge(array $data, string $token): array
    {
        $txid = $this->generateTxId('7c');
        $payload = [
            'calendario' => ['expiracao' => 86400 * 30],
            'valor' => ['original' => $this->formatAmount((float) $data['value'])],
            'chave' => (string) ($this->credentials['pix_key'] ?? ''),
            'solicitacaoPagador' => substr((string) ($data['description'] ?? 'Pagamento'), 0, 140),
        ];

        $doc = !empty($data['customer_document']) ? $this->sanitizeDocument((string) $data['customer_document']) : '';
        if ($doc !== '') {
            $payload['devedor'] = strlen($doc) === 14
                ? ['cnpj' => $doc, 'nome' => substr((string) ($data['customer_name'] ?? 'Cliente'), 0, 200)]
                : ['cpf' => $doc, 'nome' => substr((string) ($data['customer_name'] ?? 'Cliente'), 0, 200)];
        }

        $response = $this->makeApiRequest('PUT', '/pix/api/v2/cob/' . rawurlencode($txid), $payload, $token);

        if (($response['_http_code'] ?? 0) >= 400 || empty($response['txid'])) {
            return [
                'success' => false,
                'message' => $this->extractErrorMessage($response, 'Erro ao criar PIX no Sicoob'),
                'raw' => $response,
            ];
        }

        $pixCode = $response['brcode'] ?? $response['pixCopiaECola'] ?? null;
        $externalId = 'pix_' . $response['txid'];
        $transactionId = $this->logTransaction(
            $data['chave'] ?? '',
            $data['id_financeiro'] ?? null,
            'charge',
            $externalId,
            $this->mapStatus((string) ($response['status'] ?? 'ATIVA')),
            (float) $data['value'],
            'pix',
            $response,
            null,
            $pixCode,
            null,
            $data['due_date'] ?? null
        );

        return [
            'success' => true,
            'external_id' => $externalId,
            'status' => $this->mapStatus((string) ($response['status'] ?? 'ATIVA')),
            'pix_code' => $pixCode,
            'pix_qrcode' => $pixCode ? $this->generatePixQrCodeDataUri($pixCode) : null,
            'transaction_id' => $transactionId,
            'raw' => $response,
        ];
    }

    private function createBoletoCharge(array $data, string $token): array
    {
        $vencimento = $this->resolveDueDate($data['due_date'] ?? null);
        $doc = !empty($data['customer_document']) ? $this->sanitizeDocument((string) $data['customer_document']) : '';
        if ($doc === '') {
            return [
                'success' => false,
                'message' => 'Documento do pagador é obrigatório para emitir boleto Sicoob',
                'raw' => [],
            ];
        }

        $payload = [
            'numeroCliente' => (int) ($this->credentials['numero_cliente'] ?? 0),
            'codigoModalidade' => (int) ($this->credentials['codigo_modalidade'] ?? 1),
            'numeroContaCorrente' => (int) ($this->credentials['numero_conta_corrente'] ?? 0),
            'codigoEspecieDocumento' => 'DS',
            'dataEmissao' => \App\Helpers\DateHelper::todayForDatabase(),
            'seuNumero' => substr(preg_replace('/[^A-Za-z0-9]/', '', (string) ($data['external_reference'] ?? uniqid('fat'))), 0, 18),
            'identificacaoEmissaoBoleto' => 1,
            'identificacaoDistribuicaoBoleto' => 1,
            'valor' => (float) $data['value'],
            'dataVencimento' => $vencimento,
            'tipoDesconto' => 0,
            'tipoMulta' => 0,
            'tipoJurosMora' => 3,
            'numeroParcela' => 1,
            'pagador' => [
                'numeroCpfCnpj' => $doc,
                'nome' => substr((string) ($data['customer_name'] ?? 'Cliente'), 0, 50),
                'endereco' => substr((string) ($data['customer_address'] ?? 'Nao informado'), 0, 40),
                'bairro' => substr((string) ($data['customer_neighborhood'] ?? 'Nao informado'), 0, 30),
                'cidade' => substr((string) ($data['customer_city'] ?? 'Nao informado'), 0, 40),
                'cep' => substr($this->sanitizeDocument((string) ($data['customer_zip'] ?? '00000000')), 0, 8),
                'uf' => substr((string) ($data['customer_state'] ?? 'SP'), 0, 2),
            ],
            'mensagensInstrucao' => [substr((string) ($data['description'] ?? 'Pagamento'), 0, 40)],
            'gerarPdf' => false,
            'codigoCadastrarPIX' => 0,
        ];

        if (!empty($data['customer_email'])) {
            $payload['pagador']['email'] = (string) $data['customer_email'];
        }

        $response = $this->makeApiRequest('POST', '/cobranca-bancaria/v3/boletos', $payload, $token);

        if (($response['_http_code'] ?? 0) >= 400) {
            return [
                'success' => false,
                'message' => $this->extractErrorMessage($response, 'Erro ao criar boleto no Sicoob'),
                'raw' => $response,
            ];
        }

        $nossoNumero = (string) ($response['nossoNumero'] ?? $response['resultado']['nossoNumero'] ?? $response['boleto']['nossoNumero'] ?? '');
        if ($nossoNumero === '') {
            return [
                'success' => false,
                'message' => 'Boleto criado no Sicoob, mas o nosso número não foi retornado.',
                'raw' => $response,
            ];
        }

        $externalId = 'bol_' . $nossoNumero;
        $barcode = $response['linhaDigitavel'] ?? $response['resultado']['linhaDigitavel'] ?? null;
        $boletoUrl = $response['pdfBoleto'] ?? $response['resultado']['pdfBoleto'] ?? $response['urlBoleto'] ?? null;
        $transactionId = $this->logTransaction(
            $data['chave'] ?? '',
            $data['id_financeiro'] ?? null,
            'charge',
            $externalId,
            'pending',
            (float) $data['value'],
            'boleto',
            $response,
            $boletoUrl,
            null,
            $barcode,
            $vencimento
        );

        return [
            'success' => true,
            'external_id' => $externalId,
            'status' => 'pending',
            'barcode' => $barcode,
            'boleto_url' => $boletoUrl,
            'payment_url' => $boletoUrl,
            'transaction_id' => $transactionId,
            'raw' => $response,
        ];
    }

    private function getPixChargeStatus(string $txid, string $token): array
    {
        $response = $this->makeApiRequest('GET', '/pix/api/v2/cob/' . rawurlencode($txid), [], $token);

        if (($response['_http_code'] ?? 0) >= 400) {
            return [
                'success' => false,
                'message' => $this->extractErrorMessage($response, 'Erro ao consultar PIX no Sicoob'),
                'raw' => $response,
            ];
        }

        return [
            'success' => true,
            'status' => $this->mapStatus((string) ($response['status'] ?? '')),
            'paid_at' => $response['pix'][0]['horario'] ?? null,
            'raw' => $response,
        ];
    }

    private function getBoletoChargeStatus(string $nossoNumero, string $token): array
    {
        $response = $this->makeApiRequest('GET', '/cobranca-bancaria/v3/boletos', [
            'numeroCliente' => (int) ($this->credentials['numero_cliente'] ?? 0),
            'codigoModalidade' => (int) ($this->credentials['codigo_modalidade'] ?? 1),
            'nossoNumero' => $nossoNumero,
        ], $token);

        if (($response['_http_code'] ?? 0) >= 400) {
            return [
                'success' => false,
                'message' => $this->extractErrorMessage($response, 'Erro ao consultar boleto no Sicoob'),
                'raw' => $response,
            ];
        }

        return [
            'success' => true,
            'status' => $this->mapStatus((string) ($response['situacao'] ?? $response['status'] ?? '')),
            'paid_at' => $response['dataPagamento'] ?? $response['dataLiquidacao'] ?? null,
            'raw' => $response,
        ];
    }

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

    private function getAccessToken(bool $forceRefresh = false): ?string
    {
        if (!$forceRefresh && $this->accessToken && time() < $this->accessTokenExpiresAt) {
            return $this->accessToken;
        }

        $scope = implode(' ', [
            'cob.read',
            'cob.write',
            'pix.read',
            'pix.write',
            'webhook.read',
            'webhook.write',
            'boletos_inclusao',
            'boletos_consulta',
            'boletos_alteracao',
            'webhooks_inclusao',
            'webhooks_consulta',
            'webhooks_alteracao',
        ]);

        $data = [
            'grant_type' => 'client_credentials',
            'client_id' => (string) ($this->credentials['client_id'] ?? ''),
            'scope' => $scope,
        ];

        if (!empty($this->credentials['client_secret'])) {
            $data['client_secret'] = (string) $this->credentials['client_secret'];
        }

        $response = $this->makeApiRequest('POST', $this->getAuthUrl(), $data, null, true);
        $token = $response['access_token'] ?? null;

        if ($token) {
            $expiresIn = max(60, (int) ($response['expires_in'] ?? 300));
            $this->accessToken = $token;
            $this->accessTokenExpiresAt = time() + $expiresIn - 30;
            return $token;
        }

        $this->accessToken = null;
        $this->accessTokenExpiresAt = 0;
        return null;
    }

    private function getAuthUrl(): string
    {
        $configured = trim((string) ($this->credentials['auth_url'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        return 'https://auth.sicoob.com.br/auth/realms/cooperado/protocol/openid-connect/token';
    }

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
            $headers[] = 'client_id: ' . (string) ($this->credentials['client_id'] ?? '');
            $postData = !empty($data) ? json_encode($data, JSON_PRESERVE_ZERO_FRACTION) : '';
        }

        if ($token) {
            $headers[] = "Authorization: Bearer {$token}";
        }

        if (!empty($this->credentials['x_client_certificate'])) {
            $certHeader = preg_replace('/\s+/', ' ', trim((string) $this->credentials['x_client_certificate']));
            $headers[] = 'X-Client-Certificate: ' . $certHeader;
        }

        $ch = curl_init();
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        $certPath = (string) ($this->credentials['certificate_path'] ?? '');
        $keyPath = (string) ($this->credentials['private_key_path'] ?? '');
        $keyPassword = $this->credentials['private_key_password'] ?? null;

        if ($certPath !== '' && file_exists($certPath)) {
            $curlOptions[CURLOPT_SSLCERT] = $certPath;
        }

        if ($keyPath !== '' && file_exists($keyPath)) {
            $curlOptions[CURLOPT_SSLKEY] = $keyPath;
            if ($keyPassword !== null && $keyPassword !== '') {
                $curlOptions[CURLOPT_SSLKEYPASSWD] = (string) $keyPassword;
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
                if ($postData !== '') {
                    $curlOptions[CURLOPT_POSTFIELDS] = $postData;
                }
                break;
            case 'GET':
            default:
                if (!empty($data)) {
                    $curlOptions[CURLOPT_URL] = $url . '?' . http_build_query($data);
                }
                break;
        }

        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("Erro cURL: {$error}");
        }

        $decoded = json_decode((string) $response, true);
        $body = is_array($decoded) ? $decoded : [];
        $body['_http_code'] = $httpCode;
        $body['_raw_body'] = $response;

        return $body;
    }

    private function extractErrorMessage(array $response, string $fallback): string
    {
        if (!empty($response['mensagens']) && is_array($response['mensagens'])) {
            return implode('; ', array_map('strval', $response['mensagens']));
        }

        if (!empty($response['violacoes']) && is_array($response['violacoes'])) {
            return implode('; ', array_map(static function ($item) {
                if (!is_array($item)) {
                    return (string) $item;
                }
                return trim((string) ($item['propriedade'] ?? '') . ' ' . (string) ($item['razao'] ?? $item['mensagem'] ?? ''));
            }, $response['violacoes']));
        }

        return (string) (
            $response['detail']
            ?? $response['message']
            ?? $response['mensagem']
            ?? $response['title']
            ?? $fallback
        );
    }
}
