<?php

namespace App\Services\Gateways;

use SimpleSoftwareIO\QrCode\Generator as QrCodeGenerator;

/**
 * Banco Santander — Pix Cobrança e Cobrança (boletos).
 *
 * OAuth2 client credentials e todas as chamadas protegidas usam mTLS.
 *
 * @see https://developer.santander.com.br/api/documentacao
 */
class SantanderGateway extends AbstractPaymentGateway
{
    private const PIX_PRODUCTION_URL = 'https://trust-pix.santander.com.br/api/v1';
    private const PIX_SANDBOX_URL = 'https://trust-pix-h.santander.com.br/api/v1';
    private const BILLING_PRODUCTION_URL = 'https://trust-open.api.santander.com.br/collection_bill_management/v2';
    private const BILLING_SANDBOX_URL = 'https://trust-sandbox.api.santander.com.br/collection_bill_management/v2';
    private const BILLING_AUTH_PRODUCTION_URL = 'https://trust-open.api.santander.com.br/auth/oauth/v2/token';
    private const BILLING_AUTH_SANDBOX_URL = 'https://trust-sandbox.api.santander.com.br/auth/oauth/v2/token';

    /** @var array<string, array{token: string, expires_at: int}> */
    private array $tokens = [];

    public function getCode(): string { return 'santander'; }
    public function getName(): string { return 'Banco Santander'; }
    public function getCountry(): string { return 'BR'; }
    public function getSupportedMethods(): array { return ['pix', 'boleto']; }

    public function getCertificateConfig(): ?array
    {
        return ['required' => true, 'formats' => ['pfx', 'p12', 'pem', 'crt', 'cer']];
    }

    public function getConfigSchema(): array
    {
        return [
            'client_id' => [
                'type' => 'string', 'required' => true, 'label' => 'Client ID',
                'placeholder' => 'Client ID da aplicação Santander',
                'help' => 'Identificador da aplicação cadastrada no Santander Developer.',
            ],
            'client_secret' => [
                'type' => 'password', 'required' => true, 'label' => 'Client Secret',
                'placeholder' => 'Client Secret da aplicação Santander',
                'help' => 'Segredo OAuth2 da aplicação. Ele é armazenado criptografado junto às demais credenciais.',
            ],
            'pix_key_type' => [
                'type' => 'select', 'required' => true, 'label' => 'Tipo da chave Pix',
                'options' => [
                    'CPF' => 'CPF', 'CNPJ' => 'CNPJ', 'CELULAR' => 'Celular',
                    'EMAIL' => 'E-mail', 'EVP' => 'Chave aleatória',
                ],
                'help' => 'Tipo da chave DICT vinculada à conta recebedora.',
            ],
            'pix_key' => [
                'type' => 'string', 'required' => true, 'label' => 'Chave Pix',
                'placeholder' => 'Chave Pix recebedora',
                'help' => 'Chave Pix usada para gerar cobranças imediatas e com vencimento.',
            ],
            'workspace_id' => [
                'type' => 'string', 'required' => true, 'label' => 'Workspace ID',
                'placeholder' => 'Identificador do workspace',
                'help' => 'Workspace habilitado para a API de Cobrança do Santander.',
            ],
            'covenant_code' => [
                'type' => 'string', 'required' => true, 'label' => 'Código do convênio',
                'placeholder' => 'Código do convênio de cobrança',
                'help' => 'Convênio de cobrança bancária contratado com o Santander.',
            ],
        ];
    }

    public function validateCredentials(array $credentials): array
    {
        foreach (['client_id', 'client_secret', 'pix_key', 'workspace_id', 'covenant_code'] as $field) {
            if (empty($credentials[$field])) {
                return ['valid' => false, 'message' => 'Preencha todas as credenciais obrigatórias do Santander.'];
            }
        }
        if (empty($credentials['certificado_arquivo'])) {
            return ['valid' => false, 'message' => 'Envie o certificado digital da aplicação Santander.'];
        }

        try {
            $this->credentials = $credentials;
            $pixToken = $this->getToken('pix', true);
            $billingToken = $this->getToken('billing', true);
            return $pixToken !== '' && $billingToken !== ''
                ? ['valid' => true, 'message' => 'Credenciais Santander válidas.']
                : ['valid' => false, 'message' => 'Não foi possível autenticar nas APIs do Santander.'];
        } catch (\Throwable $e) {
            return ['valid' => false, 'message' => 'Erro ao validar: ' . $e->getMessage()];
        }
    }

    public function createCharge(array $data): array
    {
        try {
            $this->validateRequiredFields($data, ['value', 'billing_type']);
            return strtoupper((string) $data['billing_type']) === 'PIX'
                ? $this->createPixCharge($data)
                : $this->createBoletoCharge($data);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Erro ao criar cobrança: ' . $e->getMessage(), 'raw' => []];
        }
    }

    public function getChargeStatus(string $externalId): array
    {
        try {
            if (str_starts_with($externalId, 'pix_')) {
                $response = $this->request('pix', 'GET', '/cobv/' . rawurlencode(substr($externalId, 4)));
                return $this->statusResponse($response, 'pix');
            }

            $bankNumber = str_starts_with($externalId, 'bol_') ? substr($externalId, 4) : $externalId;
            $identifier = rawurlencode((string) $this->credentials['covenant_code'] . '.' . $bankNumber);
            $response = $this->request('billing', 'GET', '/bills/' . $identifier . '?tipoConsulta=settlement');
            return $this->statusResponse($response, 'boleto');
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Erro ao consultar: ' . $e->getMessage(), 'raw' => []];
        }
    }

    public function cancel(string $externalId): array
    {
        try {
            if (str_starts_with($externalId, 'pix_')) {
                $response = $this->request('pix', 'PATCH', '/cobv/' . rawurlencode(substr($externalId, 4)), [
                    'status' => 'REMOVIDA_PELO_USUARIO_RECEBEDOR',
                ]);
            } else {
                $bankNumber = str_starts_with($externalId, 'bol_') ? substr($externalId, 4) : $externalId;
                $response = $this->request('billing', 'PATCH', $this->workspacePath('/bank_slips'), [
                    'covenantCode' => (string) $this->credentials['covenant_code'],
                    'bankNumber' => $bankNumber,
                    'operation' => 'BAIXAR',
                ]);
            }

            return $this->isSuccessful($response)
                ? ['success' => true, 'raw' => $response]
                : ['success' => false, 'message' => $this->errorMessage($response, 'Não foi possível cancelar a cobrança.'), 'raw' => $response];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Erro ao cancelar: ' . $e->getMessage()];
        }
    }

    public function refund(string $externalId, ?float $amount = null): array
    {
        if (!str_starts_with($externalId, 'pix_')) {
            return ['success' => false, 'message' => 'A devolução automática está disponível apenas para cobranças Pix.'];
        }
        if ($amount === null || $amount <= 0) {
            return ['success' => false, 'message' => 'Informe o valor da devolução Pix.'];
        }

        try {
            $charge = $this->request('pix', 'GET', '/cobv/' . rawurlencode(substr($externalId, 4)));
            $endToEndId = (string) ($charge['pix'][0]['endToEndId'] ?? '');
            if ($endToEndId === '') {
                return ['success' => false, 'message' => 'A cobrança ainda não possui um Pix liquidado para devolver.', 'raw' => $charge];
            }

            $refundId = $this->generateTxId('dev');
            $response = $this->request('pix', 'PUT', '/pix/' . rawurlencode($endToEndId) . '/devolucao/' . $refundId, [
                'valor' => $this->formatAmount($amount),
            ]);
            return $this->isSuccessful($response)
                ? ['success' => true, 'refund_id' => $response['id'] ?? $refundId, 'raw' => $response]
                : ['success' => false, 'message' => $this->errorMessage($response, 'Não foi possível devolver o Pix.'), 'raw' => $response];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Erro ao estornar: ' . $e->getMessage()];
        }
    }

    public function validateWebhookSignature(array $payload, array $headers): bool
    {
        $parsed = $this->parseWebhookPayload($payload);
        if (empty($parsed['external_id'])) {
            return false;
        }

        $live = $this->getChargeStatus((string) $parsed['external_id']);
        if (empty($live['success'])) {
            return false;
        }

        return ($parsed['status'] ?? 'pending') !== 'paid' || ($live['status'] ?? null) === 'paid';
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

        $bankNumber = (string) ($payload['bankNumber'] ?? $payload['bankSlip']['bankNumber'] ?? $payload['nossoNumero'] ?? '');
        $event = (string) ($payload['function'] ?? $payload['event'] ?? $payload['status'] ?? 'boleto.notification');
        return [
            'event' => $event,
            'external_id' => $bankNumber !== '' ? 'bol_' . $bankNumber : '',
            'status' => $this->mapStatus($event),
            'paid_at' => $payload['paymentDate'] ?? $payload['settlementDate'] ?? null,
            'raw' => $payload,
        ];
    }

    public function getDocumentationUrl(): string
    {
        return 'https://developer.santander.com.br/api/documentacao';
    }

    protected function mapStatus(string $gatewayStatus): string
    {
        return match (strtoupper(trim($gatewayStatus))) {
            'CONCLUIDA', 'PAGO', 'PAGA', 'LIQUIDADO', 'LIQUIDADA', 'SETTLED', 'PAGAMENTO' => 'paid',
            'REMOVIDA_PELO_USUARIO_RECEBEDOR', 'REMOVIDA_PELO_PSP', 'BAIXADO', 'BAIXADA', 'CANCELADO', 'CANCELADA', 'CANCELLED' => 'cancelled',
            'DEVOLVIDO', 'DEVOLVIDA', 'REFUNDED', 'ESTORNO' => 'refunded',
            default => 'pending',
        };
    }

    protected function getBaseUrl(): string
    {
        return $this->baseUrl('pix');
    }

    /** @param array<string, mixed> $data */
    private function createPixCharge(array $data): array
    {
        $document = preg_replace('/\D+/', '', (string) ($data['customer_document'] ?? ''));
        $debtor = ['nome' => mb_substr((string) ($data['customer_name'] ?? 'Pagador'), 0, 200)];
        if ($document !== '') {
            $debtor[strlen($document) === 11 ? 'cpf' : 'cnpj'] = $document;
        }

        $txid = $this->generateTxId('sc');
        $payload = [
            'calendario' => ['dataDeVencimento' => $this->resolveDueDate($data['due_date'] ?? null), 'validadeAposVencimento' => 0],
            'devedor' => $debtor,
            'valor' => ['original' => $this->formatAmount((float) $data['value'])],
            'chave' => (string) $this->credentials['pix_key'],
            'solicitacaoPagador' => mb_substr((string) ($data['description'] ?? 'Cobrança'), 0, 140),
        ];
        $response = $this->request('pix', 'PUT', '/cobv/' . rawurlencode($txid), $payload);
        if (!$this->isSuccessful($response)) {
            return ['success' => false, 'message' => $this->errorMessage($response, 'Erro ao criar Pix no Santander.'), 'raw' => $response];
        }

        $pixCode = (string) ($response['pixCopiaECola'] ?? $response['qrcode'] ?? '');
        if ($pixCode === '' && !empty($response['loc']['id'])) {
            $qrResponse = $this->request('pix', 'GET', '/loc/' . rawurlencode((string) $response['loc']['id']) . '/qrcode');
            $pixCode = (string) ($qrResponse['qrcode'] ?? $qrResponse['pixCopiaECola'] ?? '');
        }
        $externalId = 'pix_' . ($response['txid'] ?? $txid);
        $qrCode = $pixCode !== '' ? (string) (new QrCodeGenerator())->size(300)->generate($pixCode) : null;

        $this->logTransaction((string) ($data['chave'] ?? ''), $data['id_financeiro'] ?? null, 'charge', $externalId, 'pending', (float) $data['value'], 'pix', $response, null, $pixCode ?: null, null, $data['due_date'] ?? null);

        return [
            'success' => true, 'external_id' => $externalId, 'status' => $this->mapStatus((string) ($response['status'] ?? 'ATIVA')),
            'pix_code' => $pixCode ?: null, 'pix_qrcode' => $qrCode, 'expires_at' => $data['due_date'] ?? null, 'raw' => $response,
        ];
    }

    /** @param array<string, mixed> $data */
    private function createBoletoCharge(array $data): array
    {
        $this->validateRequiredFields($data, ['customer_name', 'customer_document', 'customer_address', 'customer_neighborhood', 'customer_city', 'customer_state', 'customer_postal_code']);
        $document = preg_replace('/\D+/', '', (string) $data['customer_document']);
        $clientNumber = preg_replace('/\D+/', '', (string) ($data['external_reference'] ?? $this->generateExternalReference()));
        $clientNumber = substr($clientNumber !== '' ? $clientNumber : (string) random_int(100000000, 999999999), 0, 15);
        $bankNumber = substr((string) ((int) floor(microtime(true) * 1000)), -9)
            . sprintf('%04d', random_int(0, 9999));
        $today = date('Y-m-d');
        $postalCode = preg_replace('/\D+/', '', (string) $data['customer_postal_code']);
        if (strlen($postalCode) === 8) {
            $postalCode = substr($postalCode, 0, 5) . '-' . substr($postalCode, 5);
        }
        $payload = [
            'environment' => $this->sandbox ? 'TESTE' : 'PRODUCAO',
            'nsuCode' => substr($this->generateExternalReference(), 0, 20),
            'nsuDate' => $today,
            'covenantCode' => (string) $this->credentials['covenant_code'],
            'bankNumber' => $bankNumber,
            'clientNumber' => $clientNumber,
            'dueDate' => $this->resolveDueDate($data['due_date'] ?? null),
            'issueDate' => $today,
            'nominalValue' => $this->formatAmount((float) $data['value']),
            'payer' => [
                'name' => mb_substr((string) $data['customer_name'], 0, 40),
                'documentType' => strlen($document) === 11 ? 'CPF' : 'CNPJ',
                'documentNumber' => $document,
                'address' => mb_substr(trim((string) $data['customer_address'] . ' ' . (string) ($data['customer_address_number'] ?? '')), 0, 40),
                'neighborhood' => mb_substr((string) $data['customer_neighborhood'], 0, 30),
                'city' => mb_substr((string) $data['customer_city'], 0, 20),
                'state' => strtoupper(substr((string) $data['customer_state'], 0, 2)),
                'zipCode' => $postalCode,
            ],
            'documentKind' => 'DUPLICATA_MERCANTIL',
            'paymentType' => 'REGISTRO',
            'key' => ['type' => (string) ($this->credentials['pix_key_type'] ?? 'EVP'), 'dictKey' => (string) $this->credentials['pix_key']],
            'writeOffQuantityDays' => 30,
            'messages' => [mb_substr((string) ($data['description'] ?? 'Cobrança'), 0, 100)],
        ];
        $response = $this->request('billing', 'POST', $this->workspacePath('/bank_slips'), $payload);
        if (!$this->isSuccessful($response)) {
            return ['success' => false, 'message' => $this->errorMessage($response, 'Erro ao registrar boleto no Santander.'), 'raw' => $response];
        }

        $registeredBankNumber = (string) ($response['bankNumber'] ?? $response['nossoNumero'] ?? $bankNumber);
        $externalId = 'bol_' . $registeredBankNumber;
        $barcode = (string) ($response['digitableLine'] ?? $response['barCode'] ?? $response['barcode'] ?? '');
        $pixCode = (string) ($response['qrCodePix'] ?? $response['pixCopyPaste'] ?? '');
        $paymentUrl = (string) ($response['pdfUrl'] ?? $response['paymentUrl'] ?? '');
        $this->logTransaction((string) ($data['chave'] ?? ''), $data['id_financeiro'] ?? null, 'charge', $externalId, 'pending', (float) $data['value'], 'boleto', $response, $paymentUrl ?: null, $pixCode ?: null, $barcode ?: null, $data['due_date'] ?? null);

        return [
            'success' => true, 'external_id' => $externalId, 'status' => 'pending', 'payment_url' => $paymentUrl ?: null,
            'barcode' => $barcode ?: null, 'pix_code' => $pixCode ?: null, 'expires_at' => $data['due_date'] ?? null, 'raw' => $response,
        ];
    }

    /** @return array<string, mixed> */
    protected function request(string $product, string $method, string $endpoint, array $data = [], bool $authentication = false): array
    {
        $certificate = $this->prepareStoredCertificate();
        if ($certificate === null) {
            throw new \RuntimeException('Certificado digital Santander não configurado.');
        }

        try {
            $url = $authentication && $product === 'billing'
                ? ($this->sandbox ? self::BILLING_AUTH_SANDBOX_URL : self::BILLING_AUTH_PRODUCTION_URL)
                : $this->baseUrl($product) . $endpoint;
            $headers = ['Accept: application/json'];
            $body = null;
            if ($authentication) {
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                $body = http_build_query([
                    'grant_type' => 'client_credentials',
                    'client_id' => (string) $this->credentials['client_id'],
                    'client_secret' => (string) $this->credentials['client_secret'],
                ]);
            } else {
                $headers[] = 'Content-Type: application/json';
                $headers[] = 'Authorization: Bearer ' . $this->getToken($product);
                $headers[] = 'X-Application-Key: ' . (string) $this->credentials['client_id'];
                if ($data !== []) {
                    $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSLCERT => $certificate['certPath'],
                CURLOPT_SSLKEY => $certificate['keyPath'],
                CURLOPT_TIMEOUT => 40,
            ]);
            if ($body !== null && strtoupper($method) !== 'GET') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $raw = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($raw === false) {
                throw new \RuntimeException('Falha de comunicação com o Santander: ' . $error);
            }
            $decoded = json_decode($raw, true);
            $response = is_array($decoded) ? $decoded : ['response' => $raw];
            $response['_http_code'] = $httpCode;
            return $response;
        } finally {
            $this->cleanupStoredCertificate($certificate);
        }
    }

    private function getToken(string $product, bool $force = false): string
    {
        if (!$force && isset($this->tokens[$product]) && $this->tokens[$product]['expires_at'] > time() + 30) {
            return $this->tokens[$product]['token'];
        }
        $endpoint = $product === 'pix' ? '/oauth/token?grant_type=client_credentials' : '';
        $response = $this->request($product, 'POST', $endpoint, [], true);
        $token = (string) ($response['access_token'] ?? '');
        if ($token === '') {
            throw new \RuntimeException($this->errorMessage($response, 'Token OAuth2 não retornado.'));
        }
        $this->tokens[$product] = ['token' => $token, 'expires_at' => time() + max(60, (int) ($response['expires_in'] ?? 300))];
        return $token;
    }

    private function baseUrl(string $product): string
    {
        if ($product === 'pix') {
            return $this->sandbox ? self::PIX_SANDBOX_URL : self::PIX_PRODUCTION_URL;
        }
        if ($product === 'billing') {
            return $this->sandbox ? self::BILLING_SANDBOX_URL : self::BILLING_PRODUCTION_URL;
        }
        throw new \InvalidArgumentException('Produto Santander inválido.');
    }

    private function workspacePath(string $suffix): string
    {
        return '/workspaces/' . rawurlencode((string) $this->credentials['workspace_id']) . $suffix;
    }

    /** @param array<string, mixed> $response */
    private function statusResponse(array $response, string $type): array
    {
        if (!$this->isSuccessful($response)) {
            return ['success' => false, 'message' => $this->errorMessage($response, 'Consulta rejeitada pelo Santander.'), 'raw' => $response];
        }
        $status = (string) ($response['status'] ?? $response['situation'] ?? $response['function'] ?? '');
        return [
            'success' => true,
            'status' => $this->mapStatus($status),
            'paid_at' => $type === 'pix' ? ($response['pix'][0]['horario'] ?? null) : ($response['paymentDate'] ?? $response['settlementDate'] ?? null),
            'raw' => $response,
        ];
    }

    /** @param array<string, mixed> $response */
    private function isSuccessful(array $response): bool
    {
        $code = (int) ($response['_http_code'] ?? 0);
        return $code >= 200 && $code < 300;
    }

    /** @param array<string, mixed> $response */
    private function errorMessage(array $response, string $fallback): string
    {
        return (string) ($response['message'] ?? $response['error_description'] ?? $response['error'] ?? $response['detail'] ?? $fallback);
    }
}
