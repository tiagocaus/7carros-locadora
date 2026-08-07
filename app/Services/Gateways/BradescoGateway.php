<?php

namespace App\Services\Gateways;

use SimpleSoftwareIO\QrCode\Generator as QrCodeGenerator;

/**
 * Banco Bradesco — APIs Pix Recebimentos e Cobranca.
 *
 * @see https://developers.bradesco.com.br/
 */
class BradescoGateway extends AbstractPaymentGateway
{
    private const PIX_PRODUCTION_URL = 'https://qrpix.bradesco.com.br/v2';
    private const PIX_SANDBOX_URL = 'https://qrpix-h.bradesco.com.br/v2';
    private const AUTH_PRODUCTION_URL = 'https://qrpix.bradesco.com.br/oauth/token';
    private const AUTH_SANDBOX_URL = 'https://qrpix-h.bradesco.com.br/oauth/token';
    private const BOLETO_PRODUCTION_URL = 'https://openapi.bradesco.com.br';
    private const BOLETO_SANDBOX_URL = 'https://openapisandbox.prebanco.com.br';
    private const BOLETO_AUTH_PRODUCTION_URL = 'https://openapi.bradesco.com.br/auth/server-mtls/v2/token';
    private const BOLETO_AUTH_SANDBOX_URL = 'https://openapisandbox.prebanco.com.br/auth/server-mtls/v2/token';
    private const BOLETO_CREATE_PATH = '/boleto/cobranca-registro/v1/cobranca';
    private const BOLETO_STATUS_PATH = '/boleto/cobranca-registro/v1/titulo-consultar';
    private const BOLETO_CANCEL_PATH = '/boleto/cobranca-registro/v1/titulo-baixar';

    /** @var array{token: string, expires_at: int}|null */
    private ?array $tokenCache = null;

    /** @var array{token: string, expires_at: int}|null */
    private ?array $boletoTokenCache = null;

    public function getCode(): string { return 'bradesco'; }
    public function getName(): string { return 'Banco Bradesco'; }
    public function getCountry(): string { return 'BR'; }

    public function getSupportedMethods(): array { return ['pix', 'boleto']; }

    public function getConfigSchema(): array
    {
        return [
            'client_id' => [
                'type' => 'string',
                'required' => true,
                'label' => 'Client ID do Pix',
                'help' => 'Identificador de produção ou homologação fornecido para a aplicação da API Pix Bradesco.',
            ],
            'client_secret' => [
                'type' => 'password',
                'required' => true,
                'label' => 'Client Secret do Pix',
                'help' => 'Segredo da aplicação usado na autenticação OAuth2 da API Pix.',
            ],
            'pix_key' => [
                'type' => 'string',
                'required' => true,
                'label' => 'Chave Pix recebedora',
                'help' => 'Chave Pix Bradesco vinculada à conta que receberá as cobranças.',
            ],
            'boleto_client_id' => [
                'type' => 'string',
                'required' => false,
                'label' => 'Client ID do Boleto',
                'help' => 'Obrigatório ao ativar Boleto. Use a credencial da API Cobrança, que pode ser diferente da credencial Pix.',
            ],
            'boleto_client_secret' => [
                'type' => 'password',
                'required' => false,
                'label' => 'Client Secret do Boleto',
                'help' => 'Obrigatório ao ativar Boleto. Segredo da aplicação da API Cobrança fornecido no Portal Developers.',
            ],
            'boleto_beneficiary_document' => [
                'type' => 'string',
                'required' => false,
                'label' => 'CNPJ do Beneficiário',
                'help' => 'Obrigatório ao ativar Boleto. Informe o CNPJ titular do contrato de cobrança, com 14 dígitos.',
            ],
            'boleto_product' => [
                'type' => 'string',
                'required' => false,
                'label' => 'Carteira / ID do Produto',
                'help' => 'Obrigatório ao ativar Boleto. Código da carteira informado pelo Bradesco, por exemplo 09 para cobrança escritural.',
            ],
            'boleto_negotiation' => [
                'type' => 'string',
                'required' => false,
                'label' => 'Número da Negociação',
                'help' => 'Obrigatório ao ativar Boleto. Informe os 18 dígitos da negociação/convênio exatamente como fornecidos pelo Bradesco.',
            ],
        ];
    }

    public function getCertificateConfig(): ?array
    {
        return ['required' => true, 'formats' => ['pfx', 'p12', 'pem', 'crt', 'cer']];
    }

    public function validateCredentials(array $credentials): array
    {
        if (empty($credentials['certificado_arquivo']) && empty($credentials['certificate_path'])) {
            return ['valid' => false, 'message' => 'Envie o certificado A1 correspondente à aplicação Bradesco.'];
        }

        try {
            $this->credentials = $credentials;
            $tested = [];

            if (!empty($credentials['_pix_enabled']) || !$this->hasCompleteBoletoCredentials($credentials)) {
                foreach (['client_id', 'client_secret', 'pix_key'] as $field) {
                    if (empty($credentials[$field])) {
                        return ['valid' => false, 'message' => 'Preencha Client ID, Client Secret e chave Pix do Bradesco.'];
                    }
                }
                $this->getAccessToken(true);
                $tested[] = 'Pix';
            }

            if (!empty($credentials['_boleto_enabled']) || $this->hasAnyBoletoCredential($credentials)) {
                $missing = $this->missingBoletoCredentials($credentials);
                if ($missing !== []) {
                    return ['valid' => false, 'message' => 'Complete a configuração do Boleto Bradesco: ' . implode(', ', $missing) . '.'];
                }
                $this->getBoletoAccessToken(true);
                $tested[] = 'Boleto';
            }

            return ['valid' => true, 'message' => 'Credenciais Bradesco válidas para: ' . implode(' e ', $tested) . '.'];
        } catch (\Throwable $e) {
            return ['valid' => false, 'message' => 'Falha na autenticação Bradesco: ' . $e->getMessage()];
        }
    }

    public function createCharge(array $data): array
    {
        try {
            $this->validateRequiredFields($data, ['value', 'billing_type']);
            return match (strtolower((string) $data['billing_type'])) {
                'pix' => $this->createPixCharge($data),
                'boleto' => $this->createBoletoCharge($data),
                default => ['success' => false, 'message' => 'Método não suportado pelo Bradesco.'],
            };
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Erro ao criar cobrança Bradesco: ' . $e->getMessage(), 'raw' => []];
        }
    }

    public function getChargeStatus(string $externalId): array
    {
        if (str_starts_with($externalId, 'bol_')) {
            return $this->getBoletoChargeStatus(substr($externalId, 4));
        }
        if (!str_starts_with($externalId, 'pix_')) {
            return ['success' => false, 'message' => 'Identificador Bradesco não suportado.', 'raw' => []];
        }

        try {
            $txid = rawurlencode(substr($externalId, 4));
            $response = $this->request('GET', '/cobv/' . $txid);
            if (!$this->isSuccessful($response)) {
                return $this->failureResponse($response, 'Não foi possível consultar o Pix Bradesco.');
            }

            return [
                'success' => true,
                'status' => $this->mapStatus((string) ($response['status'] ?? '')),
                'paid_at' => $response['pix'][0]['horario'] ?? null,
                'raw' => $response,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Erro ao consultar Pix Bradesco: ' . $e->getMessage(), 'raw' => []];
        }
    }

    public function cancel(string $externalId): array
    {
        if (str_starts_with($externalId, 'bol_')) {
            return $this->cancelBoletoCharge(substr($externalId, 4));
        }
        if (!str_starts_with($externalId, 'pix_')) {
            return ['success' => false, 'message' => 'Identificador Bradesco não suportado.'];
        }

        try {
            $response = $this->request('PATCH', '/cobv/' . rawurlencode(substr($externalId, 4)), [
                'status' => 'REMOVIDA_PELO_USUARIO_RECEBEDOR',
            ]);
            if (!$this->isSuccessful($response)) {
                return $this->failureResponse($response, 'Não foi possível cancelar o Pix Bradesco.');
            }

            return ['success' => true, 'raw' => $response];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Erro ao cancelar Pix Bradesco: ' . $e->getMessage()];
        }
    }

    public function refund(string $externalId, ?float $amount = null): array
    {
        if (!str_starts_with($externalId, 'pix_')) {
            return ['success' => false, 'message' => 'A devolução automática está disponível apenas para Pix.'];
        }

        try {
            $charge = $this->request('GET', '/cobv/' . rawurlencode(substr($externalId, 4)));
            if (!$this->isSuccessful($charge)) {
                return $this->failureResponse($charge, 'Não foi possível localizar o Pix para devolução.');
            }

            $receivedPix = $charge['pix'][0] ?? [];
            $endToEndId = (string) ($receivedPix['endToEndId'] ?? $receivedPix['e2eid'] ?? '');
            $refundAmount = $amount ?? (isset($receivedPix['valor']) ? (float) $receivedPix['valor'] : 0.0);
            if ($endToEndId === '' || $refundAmount <= 0) {
                return ['success' => false, 'message' => 'O Pix ainda não possui liquidação válida para devolução.'];
            }

            $refundId = $this->generateTxId('dev');
            $response = $this->request(
                'PUT',
                '/pix/' . rawurlencode($endToEndId) . '/devolucao/' . rawurlencode($refundId),
                ['valor' => $this->formatAmount($refundAmount)]
            );
            if (!$this->isSuccessful($response)) {
                return $this->failureResponse($response, 'Não foi possível devolver o Pix Bradesco.');
            }

            return [
                'success' => true,
                'refund_id' => (string) ($response['id'] ?? $refundId),
                'raw' => $response,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Erro ao devolver Pix Bradesco: ' . $e->getMessage()];
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
        if (!is_array($pix)) {
            $nossoNumero = (string) ($payload['nossoNumero'] ?? $payload['ctitloCobrCdent'] ?? '');
            if ($nossoNumero !== '') {
                return [
                    'event' => 'boleto.status_changed',
                    'external_id' => 'bol_' . preg_replace('/\D+/', '', $nossoNumero),
                    'status' => $this->mapStatus((string) ($payload['situacao'] ?? $payload['status'] ?? '')),
                    'paid_at' => $payload['dataHoraSituacao'] ?? $payload['dataPagamento'] ?? null,
                    'barcode' => $payload['linhaDigitavel'] ?? $payload['linhaDig10'] ?? null,
                    'raw' => $payload,
                ];
            }
            return [
                'event' => (string) ($payload['evento'] ?? 'pix.unknown'),
                'external_id' => '',
                'status' => 'pending',
                'paid_at' => null,
                'raw' => $payload,
            ];
        }

        $txid = (string) ($pix['txid'] ?? '');
        return [
            'event' => 'pix.received',
            'external_id' => $txid !== '' ? 'pix_' . $txid : '',
            'status' => 'paid',
            'paid_at' => $pix['horario'] ?? null,
            'raw' => $payload,
        ];
    }

    public function getDocumentationUrl(): string
    {
        return 'https://developers.bradesco.com.br/';
    }

    protected function mapStatus(string $gatewayStatus): string
    {
        return match (strtoupper(trim($gatewayStatus))) {
            'CONCLUIDA', 'PAGO', 'LIQUIDADO', 'LIQUIDADO PARCIALMENTE', 'CONFIRMADO' => 'paid',
            'REMOVIDA_PELO_USUARIO_RECEBEDOR', 'REMOVIDA_PELO_PSP', 'CANCELADO', 'BAIXADO', 'EXPIRADO' => 'cancelled',
            'DEVOLVIDO', 'DEVOLVIDA' => 'refunded',
            default => 'pending',
        };
    }

    protected function getBaseUrl(): string
    {
        return $this->sandbox ? self::PIX_SANDBOX_URL : self::PIX_PRODUCTION_URL;
    }

    /** @param array<string, mixed> $data */
    private function createPixCharge(array $data): array
    {
        if (empty($this->credentials['pix_key'])) {
            return ['success' => false, 'message' => 'Configure a chave Pix recebedora do Bradesco.'];
        }

        $txid = $this->generateTxId('sc');
        $payload = [
            'calendario' => [
                'dataDeVencimento' => $this->resolveDueDate($data['due_date'] ?? null),
                'validadeAposVencimento' => 0,
            ],
            'valor' => ['original' => $this->formatAmount((float) $data['value'])],
            'chave' => (string) $this->credentials['pix_key'],
            'solicitacaoPagador' => mb_substr((string) ($data['description'] ?? 'Cobrança'), 0, 140),
        ];

        $document = preg_replace('/\D+/', '', (string) ($data['customer_document'] ?? ''));
        if (in_array(strlen($document), [11, 14], true)) {
            $payload['devedor'] = [
                strlen($document) === 11 ? 'cpf' : 'cnpj' => $document,
                'nome' => mb_substr((string) ($data['customer_name'] ?? 'Pagador'), 0, 200),
            ];
        }

        $response = $this->request('PUT', '/cobv/' . rawurlencode($txid), $payload);
        if (!$this->isSuccessful($response)) {
            return $this->failureResponse($response, 'Não foi possível criar o Pix Bradesco.');
        }

        $pixCode = (string) ($response['pixCopiaECola'] ?? $response['qrcode'] ?? '');
        if ($pixCode === '') {
            return [
                'success' => false,
                'message' => 'O Bradesco criou a cobrança, mas não retornou o Pix Copia e Cola.',
                'raw' => $response,
            ];
        }

        $externalId = 'pix_' . (string) ($response['txid'] ?? $txid);
        $qrCode = (string) (new QrCodeGenerator())->size(300)->generate($pixCode);
        $expiresAt = $data['due_date'] ?? null;
        $transactionId = $this->logTransaction(
            (string) ($data['chave'] ?? ''),
            $data['id_financeiro'] ?? null,
            'charge',
            $externalId,
            'pending',
            (float) $data['value'],
            'pix',
            $response,
            null,
            $pixCode,
            null,
            $expiresAt
        );

        return [
            'success' => true,
            'external_id' => $externalId,
            'status' => $this->mapStatus((string) ($response['status'] ?? 'ATIVA')),
            'pix_code' => $pixCode,
            'pix_qrcode' => $qrCode,
            'expires_at' => $expiresAt,
            'transaction_id' => $transactionId,
            'raw' => $response,
        ];
    }

    /** @param array<string, mixed> $data */
    private function createBoletoCharge(array $data): array
    {
        $missing = $this->missingBoletoCredentials($this->credentials);
        if ($missing !== []) {
            return ['success' => false, 'message' => 'Complete a configuração do Boleto Bradesco: ' . implode(', ', $missing) . '.', 'raw' => []];
        }

        $this->validateRequiredFields($data, [
            'customer_name', 'customer_document', 'customer_address', 'customer_address_number',
            'customer_neighborhood', 'customer_city', 'customer_state', 'customer_postal_code',
        ]);

        $beneficiary = preg_replace('/\D+/', '', (string) $this->credentials['boleto_beneficiary_document']);
        $payer = preg_replace('/\D+/', '', (string) $data['customer_document']);
        $postalCode = str_pad(substr(preg_replace('/\D+/', '', (string) $data['customer_postal_code']), 0, 8), 8, '0');
        if (strlen($beneficiary) !== 14 || !in_array(strlen($payer), [11, 14], true)) {
            return ['success' => false, 'message' => 'CNPJ do beneficiário ou documento do pagador inválido.', 'raw' => []];
        }

        $clientNumber = preg_replace('/[^A-Za-z0-9]/', '', (string) ($data['external_reference'] ?? ''));
        $clientNumber = substr($clientNumber !== '' ? $clientNumber : $this->generateTxId('bl'), 0, 10);
        $payload = [
            'nuCPFCNPJ' => substr($beneficiary, 0, 8),
            'filialCPFCNPJ' => substr($beneficiary, 8, 4),
            'ctrlCPFCNPJ' => substr($beneficiary, 12, 2),
            'idProduto' => str_pad(substr(preg_replace('/\D+/', '', (string) $this->credentials['boleto_product']), 0, 2), 2, '0', STR_PAD_LEFT),
            'nuNegociacao' => preg_replace('/\D+/', '', (string) $this->credentials['boleto_negotiation']),
            'nuCliente' => $clientNumber,
            'dtEmissaoTitulo' => date('d.m.Y'),
            'dtVencimentoTitulo' => date('d.m.Y', strtotime($this->resolveDueDate($data['due_date'] ?? null))),
            'tpVencimento' => 0,
            'vlNominalTitulo' => (int) round((float) $data['value'] * 100),
            'cdEspecieTitulo' => 16,
            'controleParticipante' => substr($clientNumber, 0, 25),
            'vlAbatimento' => 0,
            'vlIOF' => 0,
            'nomePagador' => $this->normalizeBoletoText((string) $data['customer_name'], 70),
            'logradouroPagador' => $this->normalizeBoletoText((string) $data['customer_address'], 40),
            'nuLogradouroPagador' => $this->normalizeBoletoText((string) $data['customer_address_number'], 10),
            'cepPagador' => substr($postalCode, 0, 5),
            'complementoCepPagador' => substr($postalCode, 5, 3),
            'bairroPagador' => $this->normalizeBoletoText((string) $data['customer_neighborhood'], 40),
            'municipioPagador' => $this->normalizeBoletoText((string) $data['customer_city'], 30),
            'ufPagador' => strtoupper(substr((string) $data['customer_state'], 0, 2)),
            'cdIndCpfcnpjPagador' => strlen($payer) === 14 ? 2 : 1,
            'nuCpfcnpjPagador' => strlen($payer) === 11 ? '000' . $payer : $payer,
        ];

        $response = $this->requestBoleto('POST', self::BOLETO_CREATE_PATH, $payload);
        if (!$this->isSuccessful($response)) {
            return $this->failureResponse($response, 'Não foi possível registrar o boleto Bradesco.');
        }

        $ourNumber = preg_replace('/\D+/', '', (string) ($response['ctitloCobrCdent'] ?? $response['nuTituloGerado'] ?? $response['nossoNumero'] ?? ''));
        if ($ourNumber === '') {
            return ['success' => false, 'message' => 'O Bradesco registrou a solicitação, mas não retornou o nosso número.', 'raw' => $response];
        }

        $externalId = 'bol_' . $ourNumber;
        $barcode = (string) ($response['linhaDig10'] ?? $response['linhaDigitavel'] ?? $response['cdBarras'] ?? '');
        $status = $this->mapStatus((string) ($response['codStatus10'] ?? $response['statusTitulo'] ?? 'EM ABERTO'));
        $transactionId = $this->logTransaction(
            (string) ($data['chave'] ?? ''),
            $data['id_financeiro'] ?? null,
            'charge',
            $externalId,
            $status,
            (float) $data['value'],
            'boleto',
            $response,
            null,
            null,
            $barcode !== '' ? $barcode : null,
            $data['due_date'] ?? null
        );

        return [
            'success' => true,
            'external_id' => $externalId,
            'status' => $status,
            'barcode' => $barcode !== '' ? $barcode : null,
            'expires_at' => $data['due_date'] ?? null,
            'transaction_id' => $transactionId,
            'raw' => $response,
        ];
    }

    private function getBoletoChargeStatus(string $ourNumber): array
    {
        try {
            $response = $this->requestBoleto('POST', self::BOLETO_STATUS_PATH, $this->boletoIdentificationPayload($ourNumber));
            if (!$this->isSuccessful($response)) {
                return $this->failureResponse($response, 'Não foi possível consultar o boleto Bradesco.');
            }
            $detail = is_array($response['content'][0] ?? null) ? $response['content'][0] : $response;
            return [
                'success' => true,
                'status' => $this->mapStatus((string) ($detail['situacao'] ?? $detail['codStatus10'] ?? $detail['statusTitulo'] ?? '')),
                'paid_at' => $detail['dataHoraSituacao'] ?? $detail['dataPagamento'] ?? null,
                'raw' => $response,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Erro ao consultar boleto Bradesco: ' . $e->getMessage(), 'raw' => []];
        }
    }

    private function cancelBoletoCharge(string $ourNumber): array
    {
        try {
            $payload = $this->boletoIdentificationPayload($ourNumber);
            unset($payload['status']);
            $payload['codigoBaixa'] = '57';
            $response = $this->requestBoleto('POST', self::BOLETO_CANCEL_PATH, $payload);
            if (!$this->isSuccessful($response)) {
                return $this->failureResponse($response, 'Não foi possível baixar o boleto Bradesco.');
            }
            return ['success' => true, 'raw' => $response];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Erro ao baixar boleto Bradesco: ' . $e->getMessage()];
        }
    }

    /** @return array<string, mixed> */
    private function boletoIdentificationPayload(string $ourNumber): array
    {
        $beneficiary = preg_replace('/\D+/', '', (string) ($this->credentials['boleto_beneficiary_document'] ?? ''));
        return [
            'cpfCnpj' => [
                'cpfCnpj' => substr($beneficiary, 0, 8),
                'filial' => substr($beneficiary, 8, 4),
                'controle' => substr($beneficiary, 12, 2),
            ],
            'produto' => ltrim((string) ($this->credentials['boleto_product'] ?? ''), '0') ?: '0',
            'negociacao' => preg_replace('/\D+/', '', (string) ($this->credentials['boleto_negotiation'] ?? '')),
            'nossoNumero' => preg_replace('/\D+/', '', $ourNumber),
            'sequencia' => '0',
            'status' => 0,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function request(string $method, string $endpoint, array $data = [], bool $authentication = false): array
    {
        $certificate = $this->prepareStoredCertificate();
        $legacyCertificate = $certificate === null ? (string) ($this->credentials['certificate_path'] ?? '') : '';
        if ($certificate === null && ($legacyCertificate === '' || !is_file($legacyCertificate))) {
            throw new \RuntimeException('Certificado A1 Bradesco não configurado ou não encontrado.');
        }

        try {
            $url = $authentication
                ? ($this->sandbox ? self::AUTH_SANDBOX_URL : self::AUTH_PRODUCTION_URL)
                : $this->getBaseUrl() . $endpoint;
            $headers = ['Accept: application/json'];
            $body = null;

            if ($authentication) {
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                $headers[] = 'Authorization: Basic ' . base64_encode(
                    (string) ($this->credentials['client_id'] ?? '') . ':' . (string) ($this->credentials['client_secret'] ?? '')
                );
                $body = http_build_query(['grant_type' => 'client_credentials']);
            } else {
                $headers[] = 'Content-Type: application/json';
                $headers[] = 'Authorization: Bearer ' . $this->getAccessToken();
                if ($data !== []) {
                    $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }

            $ch = curl_init($url);
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 40,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ];

            if ($certificate !== null) {
                $options[CURLOPT_SSLCERT] = $certificate['certPath'];
                $options[CURLOPT_SSLKEY] = $certificate['keyPath'];
            } else {
                $options[CURLOPT_SSLCERT] = $legacyCertificate;
                $options[CURLOPT_SSLCERTTYPE] = 'P12';
                if (!empty($this->credentials['certificate_password'])) {
                    $options[CURLOPT_SSLCERTPASSWD] = (string) $this->credentials['certificate_password'];
                }
            }

            if ($body !== null && strtoupper($method) !== 'GET') {
                $options[CURLOPT_POSTFIELDS] = $body;
            }

            curl_setopt_array($ch, $options);
            $raw = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($raw === false) {
                throw new \RuntimeException('Falha de comunicação com o Bradesco: ' . ($error !== '' ? $error : 'resposta vazia'));
            }

            $decoded = json_decode($raw, true);
            $response = is_array($decoded) ? $decoded : [];
            $response['_http_code'] = $httpCode;
            if ($error !== '') {
                $response['_transport_error'] = $error;
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                $this->logSafeFailure($authentication ? 'oauth' : $endpoint, $response);
            }

            return $response;
        } finally {
            $this->cleanupStoredCertificate($certificate);
        }
    }

    private function getAccessToken(bool $force = false): string
    {
        $now = \App\Helpers\DateHelper::timestamp();
        if (!$force && $this->tokenCache !== null && $this->tokenCache['expires_at'] > $now + 30) {
            return $this->tokenCache['token'];
        }

        $response = $this->request('POST', '', [], true);
        $token = (string) ($response['access_token'] ?? '');
        if ($token === '') {
            throw new \RuntimeException($this->errorMessage($response, 'Token OAuth2 não retornado.'));
        }

        $this->tokenCache = [
            'token' => $token,
            'expires_at' => $now + max(60, (int) ($response['expires_in'] ?? 300)),
        ];
        return $token;
    }

    /** @param array<string, mixed> $data */
    protected function requestBoleto(string $method, string $endpoint, array $data = [], bool $authentication = false): array
    {
        $certificate = $this->prepareStoredCertificate();
        $legacyCertificate = $certificate === null ? (string) ($this->credentials['certificate_path'] ?? '') : '';
        if ($certificate === null && ($legacyCertificate === '' || !is_file($legacyCertificate))) {
            throw new \RuntimeException('Certificado A1 Bradesco não configurado ou não encontrado.');
        }

        try {
            $url = $authentication
                ? ($this->sandbox ? self::BOLETO_AUTH_SANDBOX_URL : self::BOLETO_AUTH_PRODUCTION_URL)
                : ($this->sandbox ? self::BOLETO_SANDBOX_URL : self::BOLETO_PRODUCTION_URL) . $endpoint;
            $headers = ['Accept: application/json', 'Accept-Encoding: gzip, deflate'];
            $body = null;

            if ($authentication) {
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                $body = http_build_query([
                    'grant_type' => 'client_credentials',
                    'client_id' => (string) ($this->credentials['boleto_client_id'] ?? ''),
                    'client_secret' => (string) ($this->credentials['boleto_client_secret'] ?? ''),
                ]);
            } else {
                $headers[] = 'Content-Type: application/json';
                $headers[] = 'Authorization: Bearer ' . $this->getBoletoAccessToken();
                if ($data !== []) {
                    $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }

            $ch = curl_init($url);
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 40,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_ENCODING => '',
            ];

            if ($certificate !== null) {
                $options[CURLOPT_SSLCERT] = $certificate['certPath'];
                $options[CURLOPT_SSLKEY] = $certificate['keyPath'];
            } else {
                $options[CURLOPT_SSLCERT] = $legacyCertificate;
                $options[CURLOPT_SSLCERTTYPE] = 'P12';
                if (!empty($this->credentials['certificate_password'])) {
                    $options[CURLOPT_SSLCERTPASSWD] = (string) $this->credentials['certificate_password'];
                }
            }

            if ($body !== null && strtoupper($method) !== 'GET') {
                $options[CURLOPT_POSTFIELDS] = $body;
            }

            curl_setopt_array($ch, $options);
            $raw = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($raw === false) {
                throw new \RuntimeException('Falha de comunicação com a Cobrança Bradesco: ' . ($error !== '' ? $error : 'resposta vazia'));
            }

            $decoded = json_decode($raw, true);
            $response = is_array($decoded) ? $decoded : [];
            $response['_http_code'] = $httpCode;
            if ($error !== '') {
                $response['_transport_error'] = $error;
            }
            if ($httpCode < 200 || $httpCode >= 300) {
                $this->logSafeFailure($authentication ? 'boleto_oauth' : $endpoint, $response);
            }
            return $response;
        } finally {
            $this->cleanupStoredCertificate($certificate);
        }
    }

    private function getBoletoAccessToken(bool $force = false): string
    {
        $now = \App\Helpers\DateHelper::timestamp();
        if (!$force && $this->boletoTokenCache !== null && $this->boletoTokenCache['expires_at'] > $now + 30) {
            return $this->boletoTokenCache['token'];
        }

        $response = $this->requestBoleto('POST', '', [], true);
        $token = (string) ($response['access_token'] ?? '');
        if ($token === '') {
            throw new \RuntimeException($this->errorMessage($response, 'Token OAuth2 da Cobrança não retornado.'));
        }
        $this->boletoTokenCache = [
            'token' => $token,
            'expires_at' => $now + max(60, (int) ($response['expires_in'] ?? 300)),
        ];
        return $token;
    }

    /** @param array<string, mixed> $credentials */
    private function hasAnyBoletoCredential(array $credentials): bool
    {
        foreach (['boleto_client_id', 'boleto_client_secret', 'boleto_beneficiary_document', 'boleto_product', 'boleto_negotiation'] as $field) {
            if (!empty($credentials[$field])) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string, mixed> $credentials */
    private function hasCompleteBoletoCredentials(array $credentials): bool
    {
        return $this->missingBoletoCredentials($credentials) === [];
    }

    /** @param array<string, mixed> $credentials @return array<int, string> */
    private function missingBoletoCredentials(array $credentials): array
    {
        $labels = [
            'boleto_client_id' => 'Client ID do Boleto',
            'boleto_client_secret' => 'Client Secret do Boleto',
            'boleto_beneficiary_document' => 'CNPJ do Beneficiário',
            'boleto_product' => 'Carteira / ID do Produto',
            'boleto_negotiation' => 'Número da Negociação',
        ];
        $missing = [];
        foreach ($labels as $field => $label) {
            if (empty($credentials[$field])) {
                $missing[] = $label;
            }
        }
        return $missing;
    }

    private function normalizeBoletoText(string $value, int $length): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return mb_substr(trim($ascii !== false ? $ascii : $value), 0, $length);
    }

    /** @param array<string, mixed> $response */
    private function isSuccessful(array $response): bool
    {
        $code = (int) ($response['_http_code'] ?? 0);
        if ($code < 200 || $code >= 300 || $code === 207) {
            return false;
        }

        $businessError = $response['cdErro'] ?? $response['codigoErro'] ?? null;
        return $businessError === null || in_array((string) $businessError, ['', '0', '5'], true);
    }

    /** @param array<string, mixed> $response */
    private function failureResponse(array $response, string $fallback): array
    {
        return ['success' => false, 'message' => $this->errorMessage($response, $fallback), 'raw' => $response];
    }

    /** @param array<string, mixed> $response */
    private function errorMessage(array $response, string $fallback): string
    {
        $message = $response['message']
            ?? $response['error_description']
            ?? $response['detail']
            ?? $response['title']
            ?? $response['error']
            ?? $response['msgErro']
            ?? $response['mensagemRetorno']
            ?? null;

        if (!empty($response['details'][0]) && is_array($response['details'][0])) {
            $message = $response['details'][0]['value'] ?? $response['details'][0]['message'] ?? $message;
        }

        if (is_scalar($message) && trim((string) $message) !== '') {
            return trim((string) $message);
        }

        $code = $response['code'] ?? $response['status'] ?? $response['_http_code'] ?? null;
        return $code !== null ? $fallback . ' Código: ' . (string) $code : $fallback;
    }

    /** @param array<string, mixed> $response */
    private function logSafeFailure(string $operation, array $response): void
    {
        error_log('[Bradesco] ' . json_encode([
            'operation' => $operation,
            'http_code' => (int) ($response['_http_code'] ?? 0),
            'code' => $response['code'] ?? $response['status'] ?? null,
            'message' => $this->errorMessage($response, 'Resposta rejeitada pelo Bradesco.'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
