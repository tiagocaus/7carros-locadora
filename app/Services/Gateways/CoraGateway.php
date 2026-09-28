<?php

namespace App\Services\Gateways;

use SimpleSoftwareIO\QrCode\Generator as QrCodeGenerator;

/** Integração Direta Cora: mTLS + Client ID, cobranças v2. */
class CoraGateway extends AbstractPaymentGateway
{
    private ?string $accessToken = null;
    private int $tokenExpiresAt = 0;
    private array $lastApiError = [];

    public function getCode(): string { return 'cora'; }
    public function getName(): string { return 'Cora'; }
    public function getCountry(): string { return 'BR'; }
    public function getSupportedMethods(): array { return ['pix', 'boleto']; }
    public function getDocumentationUrl(): string { return 'https://developers.cora.com.br/docs/integracao-direta'; }

    public function getConfigSchema(): array
    {
        return ['client_id' => [
            'type' => 'string', 'required' => true, 'label' => 'Client ID',
            'placeholder' => 'Seu Client ID',
            'help' => 'Obtido em Conta > Integrações via APIs na Cora. A Integração Direta usa Client ID, certificado e chave privada, sem Client Secret.',
        ]];
    }

    public function getCertificateConfig(): ?array
    {
        return [
            'required' => true, 'formats' => ['pfx', 'p12', 'pem', 'crt', 'cer'],
            'guidance' => 'Use o certificado e a private key fornecidos pela Cora para o mesmo ambiente da integração. Não use um certificado A1 fiscal genérico.',
        ];
    }

    public function validateCredentials(array $credentials): array
    {
        $this->credentials = $credentials;
        $this->accessToken = null;
        try {
            $this->getAccessToken();
            return ['valid' => true, 'message' => 'Credenciais válidas'];
        } catch (\Throwable $e) {
            return ['valid' => false, 'message' => $e->getMessage()];
        }
    }

    protected function getBaseUrl(): string
    {
        return $this->sandbox ? 'https://matls-clients.api.stage.cora.com.br' : 'https://matls-clients.api.cora.com.br';
    }

    protected function getAccessToken(): string
    {
        if ($this->accessToken !== null && time() < $this->tokenExpiresAt) {
            return $this->accessToken;
        }
        if (empty($this->credentials['client_id'])) {
            throw new \InvalidArgumentException('Client ID é obrigatório.');
        }
        if (empty($this->credentials['certificado_arquivo']) && empty($this->credentials['certificate_path'])) {
            throw new \InvalidArgumentException('Certificado e chave privada da Cora são obrigatórios.');
        }
        $response = $this->request('POST', '/token', [
            'grant_type' => 'client_credentials', 'client_id' => $this->credentials['client_id'],
        ], null, true);
        $this->assertResponse($response);
        if (empty($response['access_token'])) {
            throw new \RuntimeException('A Cora não retornou um token de acesso.');
        }
        $this->accessToken = $response['access_token'];
        $this->tokenExpiresAt = time() + max(0, (int) ($response['expires_in'] ?? 0) - 30);
        return $this->accessToken;
    }

    private function api(string $method, string $path, array $data = [], ?string $key = null): array
    {
        $response = $this->request($method, $path, $data, $this->getAccessToken(), false, $key);
        if (($response['_http_code'] ?? 0) === 401) {
            $this->accessToken = null;
            $response = $this->request($method, $path, $data, $this->getAccessToken(), false, $key);
        }
        $this->assertResponse($response);
        return $response;
    }

    private function assertResponse(array $response): void
    {
        $code = (int) ($response['_http_code'] ?? 0);
        if ($code >= 200 && $code < 300) return;
        if (($response['_http_code'] ?? 0) >= 400) {
            $this->lastApiError = [
                'http' => (int) $response['_http_code'],
                'code' => preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($response['code'] ?? '')),
                'message' => $this->sanitizeDiagnostic((string) ($response['message'] ?? '')),
            ];
        }
        $message = match ($code) {
            401, 403 => 'A Cora recusou a autenticação. Confira Client ID, certificado e ambiente.',
            404 => 'Cobrança ou recurso não encontrado na conta Cora configurada.',
            400, 422 => 'A Cora recusou os dados da operação. Confira os dados do cliente, valor, vencimento e situação da cobrança.',
            429 => 'Limite de requisições da Cora atingido. Tente novamente em instantes.',
            default => 'A Cora está indisponível ou retornou uma resposta inesperada. Tente novamente.',
        };
        throw new \RuntimeException($message . ' (HTTP ' . $code . ')');
    }

    /** UUID estável por operação; repetições usam o mesmo identificador remoto. */
    protected function operationKey(string $value): string
    {
        $hex = sha1('7carros:cora:' . $value);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-5' . substr($hex, 13, 3)
            . '-' . dechex((hexdec($hex[16]) & 3) | 8) . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
    }

    protected function chargePayload(array $data): array
    {
        $this->validateRequiredFields($data, ['value', 'billing_type', 'customer_name', 'customer_document', 'external_reference']);
        $method = strtolower($data['billing_type']);
        if (!in_array($method, $this->getSupportedMethods(), true)) throw new \InvalidArgumentException('Método não suportado pela Cora.');
        $document = $this->sanitizeDocument($data['customer_document']);
        if (!$this->validateCPF($document) && !$this->validateCNPJ($document)) throw new \InvalidArgumentException('CPF/CNPJ do cliente inválido.');
        $amount = $this->toCents((float) $data['value']);
        if ($amount < 500) throw new \InvalidArgumentException('A cobrança Cora deve ser de pelo menos R$ 5,00.');
        $dueDate = $this->resolveDueDate($data['due_date'] ?? null);
        $customer = ['name' => mb_substr(trim($data['customer_name']), 0, 60),
            'document' => ['identity' => $document, 'type' => strlen($document) === 11 ? 'CPF' : 'CNPJ']];
        if (!empty($data['customer_email'])) {
            if (!filter_var($data['customer_email'], FILTER_VALIDATE_EMAIL) || strlen($data['customer_email']) > 60) {
                throw new \InvalidArgumentException('E-mail do cliente inválido para a Cora.');
            }
            $customer['email'] = $data['customer_email'];
        }
        $address = [];
        foreach (['street' => 'address', 'number' => 'address_number', 'district' => 'neighborhood', 'city' => 'city', 'state' => 'state', 'zip_code' => 'postal_code'] as $key => $source) {
            $address[$key] = trim((string) ($data['customer_' . $source] ?? ''));
        }
        if (!in_array('', $address, true)) {
            $address['zip_code'] = preg_replace('/\D/', '', $address['zip_code']);
            $address['complement'] = $data['customer_complement'] ?? 'N/A';
            $customer['address'] = $address;
        }
        return [
            'code' => $data['external_reference'], 'customer' => $customer,
            'services' => [['name' => 'Locação', 'description' => mb_substr($data['description'] ?? 'Pagamento', 0, 100), 'amount' => $amount]],
            'payment_terms' => ['due_date' => $dueDate],
            'payment_forms' => $method === 'pix' ? ['PIX'] : ['BANK_SLIP', 'PIX'],
        ];
    }

    public function createCharge(array $data): array
    {
        try {
            $payload = $this->chargePayload($data);
            $chave = (string) ($data['chave'] ?? '');
            if ($chave === '' || !$this->gatewayId || empty($data['id_financeiro'])) {
                throw new \InvalidArgumentException('Vínculo financeiro da cobrança Cora é obrigatório.');
            }
            $model = $this->getTransacaoModel();
            return $model->comBloqueioCora($chave, 'charge:' . $data['id_financeiro'], function () use ($model, $payload, $data, $chave) {
                if (!$model->financeiroDisponivelParaCora($chave, (int) $data['id_financeiro'], (float) $data['value'])) {
                    throw new \RuntimeException('A situação ou o valor da parcela mudou. Atualize a página antes de continuar.');
                }
                $scope = [$chave, $this->gatewayId, $this->sandbox, $this->credentials['client_id'], $payload];
                $fingerprint = hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR));
                $rows = $model->listarTentativasParaTrocaCora($chave, (int) $data['id_financeiro']);
                if (count($rows) > 1) throw new \RuntimeException('Há mais de uma cobrança anterior. Confira a situação antes de trocar o pagamento.');
                $intent = null;
                foreach ($rows as $previous) {
                    if ($previous['gateway'] !== 'cora' || (int) $previous['id_gateway'] !== $this->gatewayId) {
                        throw new \RuntimeException('Existe uma cobrança em outro gateway. Cancele-a antes de trocar para a Cora.');
                    }
                    if (($previous['status'] ?? '') === 'paid') return $this->reconciliationRequired($previous);
                    $previousMeta = json_decode($previous['payload'] ?? '{}', true) ?: [];
                    $compatible = ($previousMeta['_cora_fingerprint'] ?? '') === $fingerprint;
                    // Uma nova data de acesso não altera o vencimento de uma cobrança já emitida.
                    if (!$compatible && ($previous['payment_method'] ?? '') === strtolower($data['billing_type'])
                        && isset($previousMeta['_cora_source_due_date'], $data['source_due_date'], $previousMeta['payment_terms']['due_date'])
                        && $previousMeta['_cora_source_due_date'] === $data['source_due_date']) {
                        $originalPayload = $payload;
                        $originalPayload['payment_terms']['due_date'] = $previousMeta['payment_terms']['due_date'];
                        $compatible = ($previousMeta['_cora_fingerprint'] ?? '') === hash('sha256', json_encode(
                            [$chave, $this->gatewayId, $this->sandbox, $this->credentials['client_id'], $originalPayload], JSON_THROW_ON_ERROR));
                    }
                    if ($compatible) {
                        $intent = $previous;
                        break;
                    }
                    if (empty($previous['external_id'])) {
                        throw new \RuntimeException('A emissão anterior ainda não foi confirmada. Consulte novamente o método anterior antes de trocar.');
                    }
                    $before = $this->getChargeStatus($previous['external_id']);
                    if (empty($before['success'])) throw new \RuntimeException('Não foi possível consultar a cobrança anterior. A troca não foi concluída.');
                    if ($before['status'] === 'paid') return $this->reconciliationRequired($previous);
                    if (in_array($before['raw']['status'] ?? '', ['IN_PAYMENT', 'INITIATED'], true)) {
                        throw new \RuntimeException('A cobrança anterior está em processamento. Aguarde a confirmação antes de trocar.');
                    }
                    $cancelled = $before['status'] === 'cancelled' ? ['success' => true] : $this->cancel($previous['external_id']);
                    if (!empty($cancelled['paid'])) return $this->reconciliationRequired($previous);
                    if (empty($cancelled['success'])) {
                        throw new \RuntimeException('Não foi possível cancelar a cobrança anterior. A troca para '
                            . (strtolower($data['billing_type']) === 'pix' ? 'Pix' : 'boleto') . ' não foi concluída. '
                            . ($cancelled['message'] ?? 'Tente consultar novamente em instantes.'));
                    }
                    $before['status'] = 'cancelled';
                    $before['raw']['status'] = 'CANCELLED';
                    $model->salvarRetornoCora($chave, (int) $previous['id'], $before,
                        array_merge($previousMeta, $before['raw']));
                }
                $meta = $intent ? json_decode($intent['payload'], true) : [];
                if (!$intent) {
                    $key = $this->operationKey($fingerprint . ':' . bin2hex(random_bytes(16)));
                    $meta = ['_cora_fingerprint' => $fingerprint, '_cora_key' => $key, '_cora_source_due_date' => $data['source_due_date'] ?? null];
                    $intent = ['id' => $model->criar([
                        'chave' => $chave, 'id_financeiro' => $data['id_financeiro'], 'id_gateway' => $this->gatewayId,
                        'gateway' => 'cora', 'type' => 'charge', 'status' => 'pending',
                        'payment_method' => strtolower($data['billing_type']), 'amount' => $data['value'],
                        'payload' => json_encode($meta),
                    ])];
                }
                if (!empty($intent['external_id'])) {
                    $invoice = $this->api('GET', '/v2/invoices/' . rawurlencode($intent['external_id']));
                } else {
                    $invoice = $this->api('POST', '/v2/invoices', $payload, $meta['_cora_key']);
                }
                if (empty($invoice['id'])) throw new \RuntimeException('A Cora não retornou o identificador da cobrança. Tente novamente para consultar a mesma tentativa.');
                if (!empty($intent['external_id']) && $invoice['id'] !== $intent['external_id']) {
                    throw new \RuntimeException('A Cora retornou uma cobrança diferente da solicitada.');
                }
                $result = $this->normalizeInvoice($invoice);
                if (($intent['status'] ?? '') === 'paid' && $result['status'] !== 'paid') {
                    throw new \RuntimeException('Esta fatura já consta como paga. Aguarde a confirmação da Cora.');
                }
                if ($result['status'] === 'paid') {
                    if (empty($intent['external_id'])) {
                        $pending = $result;
                        $pending['status'] = 'pending';
                        $model->salvarRetornoCora($chave, (int) $intent['id'], $pending, array_merge($invoice, $meta));
                    }
                    return $this->reconciliationRequired(array_replace($intent, ['chave' => $chave, 'external_id' => $invoice['id'], 'amount' => $data['value'], 'id_financeiro' => $data['id_financeiro']]));
                }
                $model->salvarRetornoCora($chave, (int) $intent['id'], $result, array_merge($invoice, array_intersect_key($meta, array_flip(['_cora_fingerprint', '_cora_key', '_cora_source_due_date']))));
                if ($result['status'] === 'cancelled') return ['success' => false, 'message' => 'Cobrança cancelada. Tente novamente para gerar uma nova cobrança.'];
                $ready = strtolower($data['billing_type']) === 'pix' ? !empty($result['pix_code']) : !empty($result['barcode']) && !empty($result['boleto_url']);
                if (!$ready) return ['success' => false, 'message' => 'A Cora ainda não disponibilizou os dados de pagamento. Tente novamente para consultar a mesma cobrança. Para Pix, confira se há uma chave Pix cadastrada na Cora.'];
                return $result + ['transaction_id' => (int) $intent['id']];
            });
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function normalizeInvoice(array $invoice): array
    {
        $bankSlip = $invoice['payment_options']['bank_slip'] ?? [];
        $paidAt = $invoice['occurrence_date'] ?? null;
        foreach ($invoice['payments'] ?? [] as $payment) {
            if (!$paidAt && ($payment['status'] ?? '') === 'SUCCESS') {
                $paidAt = $payment['finalized_at'] ?? $payment['created_at'] ?? null;
            }
        }
        if ($paidAt) {
            try { $paidAt = (new \DateTimeImmutable($paidAt))->format('Y-m-d H:i:s'); }
            catch (\Throwable) { $paidAt = null; }
        }
        return [
            'success' => true, 'external_id' => $invoice['id'] ?? '',
            'status' => $this->mapStatus($invoice['status'] ?? ''), 'paid_at' => $paidAt,
            'pix_code' => $invoice['pix']['emv'] ?? null,
            'pix_qrcode' => !empty($invoice['pix']['emv']) ? $this->generatePixQrCodeDataUri($invoice['pix']['emv']) : null,
            'barcode' => $bankSlip['digitable'] ?? $bankSlip['barcode'] ?? null,
            'boleto_url' => $bankSlip['url'] ?? null, 'payment_url' => $bankSlip['url'] ?? null,
            'expires_at' => $invoice['payment_terms']['due_date'] ?? null, 'raw' => $invoice,
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

    public function getChargeStatus(string $externalId): array
    {
        try {
            $invoice = $this->api('GET', '/v2/invoices/' . rawurlencode($externalId));
            if (($invoice['id'] ?? '') !== $externalId || empty($invoice['status'])) throw new \RuntimeException('Resposta de cobrança inválida da Cora.');
            return $this->normalizeInvoice($invoice);
        } catch (\Throwable $e) { return ['success' => false, 'message' => $e->getMessage()]; }
    }

    public function refund(string $externalId, ?float $amount = null): array
    {
        return ['success' => false, 'message' => 'Estorno automático não suportado pela integração Cora. Realize a devolução na Cora.'];
    }

    private function reconciliationRequired(array $transaction): array
    {
        return ['success' => false, 'message' => 'Esta fatura já consta como paga na Cora. Aguarde a atualização do financeiro.',
            'reconcile_transaction' => $transaction];
    }

    private function sanitizeDiagnostic(string $message): string
    {
        $message = strip_tags($message);
        $message = preg_replace('/[\r\n\t]+/', ' ', $message);
        $message = preg_replace('/https?:\/\/\S+|[\w.+-]+@[\w.-]+|Bearer\s+\S+|[a-zA-Z0-9_-]{24,}/i', '[oculto]', $message);
        $message = preg_replace('/[0-9][0-9.\/() +\-]{5,}[0-9]/', '[oculto]', $message);
        $message = preg_replace('/["\'][^"\']*["\']/', '[oculto]', $message);
        return mb_substr($message, 0, 500);
    }

    protected function auditCancellation(string $externalId, array $diagnostic): void
    {
        \App\Services\AuditLogService::registrarComCampos('Cora: resultado do cancelamento de cobrança', [
            \App\Services\AuditLogService::campo('Gateway', null, $this->gatewayId, 'Cora'),
            \App\Services\AuditLogService::campo('Cobrança', null, $externalId, 'Cora'),
            \App\Services\AuditLogService::campo('Diagnóstico', null, json_encode($diagnostic, JSON_UNESCAPED_UNICODE), 'Cora'),
        ]);
    }

    public function cancel(string $externalId): array
    {
        $this->lastApiError = [];
        try {
            $response = $this->api('DELETE', '/v2/invoices/' . rawurlencode($externalId));
            if (($response['_http_code'] ?? 0) !== 204) throw new \RuntimeException('A Cora não confirmou o cancelamento.');
            $this->auditCancellation($externalId, ['http' => 204, 'status' => 'CANCELLED']);
            return ['success' => true];
        } catch (\Throwable $e) {
            $diagnostic = $this->lastApiError ?: ['message' => $this->sanitizeDiagnostic($e->getMessage())];
            // O resultado de DELETE pode ser incerto mesmo após timeout ou recusa.
            $after = $this->getChargeStatus($externalId);
            $diagnostic['status_after'] = !empty($after['success']) ? $after['status'] : 'unknown';
            $this->auditCancellation($externalId, $diagnostic);
            if (!empty($after['success']) && $after['status'] === 'cancelled') return ['success' => true];
            return ['success' => false, 'paid' => !empty($after['success']) && $after['status'] === 'paid',
                'message' => 'A Cora não confirmou o cancelamento.'
                    . (!empty($diagnostic['code']) ? ' Referência: ' . $diagnostic['code'] . '.' : '')];
        }
    }

    public function activateWebhook(string $url): array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new \InvalidArgumentException('O webhook exige uma URL pública HTTPS configurada na aplicação.');
        }
        $endpoints = $this->api('GET', '/endpoints/');
        if ($this->hasWebhook($endpoints, $url)) return ['success' => true, 'message' => 'Webhook já está ativo'];
        $data = ['url' => $url, 'resource' => 'invoice', 'trigger' => '*'];
        $key = $this->operationKey(json_encode([$this->sandbox, $this->credentials['client_id'], $data, $endpoints], JSON_THROW_ON_ERROR));
        try {
            $created = $this->api('POST', '/endpoints/', $data, $key);
            if (empty($created['id']) || empty($created['active'])) throw new \RuntimeException('A Cora não confirmou a ativação do webhook.');
        } catch (\Throwable $e) {
            if (!$this->hasWebhook($this->api('GET', '/endpoints/'), $url)) throw $e;
        }
        return ['success' => true, 'message' => 'Webhook ativado'];
    }

    private function hasWebhook(array $response, string $url): bool
    {
        $rows = $response['data'] ?? $response['content'] ?? $response['items'] ?? $response;
        $triggers = [];
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['active']) || ($row['url'] ?? '') !== $url) continue;
            if (!in_array($row['resource'] ?? '', ['invoice', '*'], true)) continue;
            if (($row['trigger'] ?? '') === '*') return true;
            $triggers[] = $row['trigger'] ?? '';
        }
        return !array_diff(['drafted', 'created', 'paid', 'canceled', 'overdue'], $triggers);
    }

    public static function webhookPayload(array $headers): array
    {
        $headers = array_change_key_case($headers, CASE_LOWER);
        return ['event' => (string) ($headers['webhook-event-type'] ?? ''),
            'event_id' => (string) ($headers['webhook-event-id'] ?? ''),
            'resource_id' => (string) ($headers['webhook-resource-id'] ?? '')];
    }

    public function parseWebhookPayload(array $payload): array
    {
        return ['event' => $payload['event'] ?? '', 'external_id' => $payload['resource_id'] ?? '', 'status' => '', 'raw' => $payload];
    }

    public function validateWebhookSignature(array $payload, array $headers): bool
    {
        // A Cora não documenta assinatura neste webhook. A baixa exige consulta autenticada no handler.
        return false;
    }

    protected function mapStatus(string $gatewayStatus): string
    {
        return match (strtoupper($gatewayStatus)) {
            'PAID' => 'paid', 'CANCELLED', 'CANCELED' => 'cancelled',
            'DRAFT', 'OPEN', 'LATE', 'IN_PAYMENT', 'INITIATED', 'RECURRENCE_DRAFT' => 'pending',
            default => throw new \RuntimeException('Estado de cobrança não reconhecido na Cora.'),
        };
    }

    protected function request(
        string $method,
        string $endpoint,
        array $data = [],
        ?string $token = null,
        bool $isAuthRequest = false,
        ?string $idempotencyKey = null
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

        if ($idempotencyKey !== null) {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
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

        if (!is_file($certPath) || !is_file($keyPath)) {
            curl_close($ch);
            $this->cleanupStoredCertificate($storedCertificate);
            throw new \RuntimeException('Certificado e chave privada da Cora são obrigatórios.');
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
            $error = curl_errno($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        } finally {
            curl_close($ch);
            $this->cleanupStoredCertificate($storedCertificate);
        }

        if ($error) {
            throw new \RuntimeException("Falha de comunicação mTLS com a Cora (cURL {$error}). Verifique certificado, chave privada e ambiente.");
        }

        $body = json_decode((string) $response, true);
        if ($httpCode !== 204 && !is_array($body)) {
            throw new \RuntimeException('Resposta inválida da Cora.');
        }
        return array_merge(is_array($body) ? $body : [], ['_http_code' => $httpCode]);
    }

}
