<?php

namespace App\Services\Gateways;

use CodePhix\Asaas\Asaas;

/**
 * Gateway de pagamento Asaas
 *
 * Integração com a API do Asaas para PIX, Boleto e Cartão de Crédito.
 * Utiliza o SDK codephix/asaas-sdk já instalado no projeto.
 *
 * @see https://docs.asaas.com/
 */
class AsaasGateway extends AbstractPaymentGateway
{
    private ?Asaas $client = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $credentials, bool $sandbox = false, ?int $gatewayId = null)
    {
        parent::__construct($credentials, $sandbox, $gatewayId);
        $this->initClient();
    }

    /**
     * Inicializa o cliente Asaas
     */
    private function initClient(): void
    {
        if (!empty($this->credentials['api_key'])) {
            $environment = $this->sandbox ? 'homologacao' : 'producao';
            $this->client = new Asaas($this->credentials['api_key'], $environment);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCode(): string
    {
        return 'asaas';
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'Asaas';
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
        return ['pix', 'boleto', 'credit_card'];
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigSchema(): array
    {
        return [
            'api_key' => [
                'type' => 'string',
                'required' => true,
                'label' => 'API Key',
                'placeholder' => '$aact_...',
                'help' => 'Chave de API disponível no painel do Asaas',
            ],
            'webhook_token' => [
                'type' => 'string',
                'required' => false,
                'label' => 'Token de Webhook',
                'placeholder' => 'Token para validar webhooks',
                'help' => 'Configure nas configurações de webhook do Asaas',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(array $credentials): array
    {
        if (empty($credentials['api_key'])) {
            return [
                'valid' => false,
                'message' => 'API Key é obrigatória',
            ];
        }

        try {
            $environment = $this->sandbox ? 'homologacao' : 'producao';
            $client = new Asaas($credentials['api_key'], $environment);

            // Tenta listar clientes como teste de conexão
            $result = $client->Cliente()->getAll();

            return [
                'valid' => true,
                'message' => 'Credenciais válidas',
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => 'Credenciais inválidas: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createCharge(array $data): array
    {
        if ($this->client === null) {
            return [
                'success' => false,
                'message' => 'Cliente Asaas não inicializado. Verifique as credenciais.',
            ];
        }

        try {
            $this->validateRequiredFields($data, ['value', 'billing_type']);

            // Preparar dados do cliente se não tiver customer_id
            $customerId = $data['customer_id'] ?? null;
            if (empty($customerId) && !empty($data['customer_document'])) {
                $customerResult = $this->findOrCreateCustomer($data);
                if (!$customerResult['success']) {
                    return [
                        'success' => false,
                        'message' => $customerResult['message'] ?? 'Não foi possível criar/localizar o cliente no Asaas.',
                        'raw' => $customerResult['raw'] ?? null,
                    ];
                }

                $customerId = $customerResult['customer_id'] ?? null;
            }

            if (empty($customerId)) {
                return [
                    'success' => false,
                    'message' => 'ID do cliente ou dados do cliente são obrigatórios',
                ];
            }

            // Mapear billing_type para formato Asaas
            $billingType = $this->mapBillingType($data['billing_type']);

            $paymentData = [
                'customer' => $customerId,
                'billingType' => $billingType,
                'value' => (float) $data['value'],
                'dueDate' => $this->resolveDueDate($data['due_date'] ?? null),
            ];

            if (!empty($data['description'])) {
                $paymentData['description'] = $data['description'];
            }

            if (!empty($data['external_reference'])) {
                $paymentData['externalReference'] = $data['external_reference'];
            }

            // Parcelas para cartão de crédito
            if ($billingType === 'CREDIT_CARD' && !empty($data['installments'])) {
                $paymentData['installmentCount'] = (int) $data['installments'];
                $paymentData['installmentValue'] = round($data['value'] / $data['installments'], 2);
            }

            // Token do cartão se for cartão de crédito
            if ($billingType === 'CREDIT_CARD' && !empty($data['card_token'])) {
                $paymentData['creditCardToken'] = $data['card_token'];
            }

            $result = $this->client->Cobranca()->create($paymentData);

            // Verificar se a cobrança foi criada com sucesso
            if (empty($result->id)) {
                $errorMessage = 'Erro ao criar cobrança no Asaas';
                if (isset($result->errors) && is_array($result->errors)) {
                    $errorMessage = implode(', ', array_map(fn($e) => $e->description ?? $e->message ?? '', $result->errors));
                }
                return [
                    'success' => false,
                    'message' => $errorMessage,
                    'raw' => $result,
                ];
            }

            // Buscar dados adicionais para PIX
            $pixCode = null;
            $pixQrCode = null;
            if ($billingType === 'PIX' && !empty($result->id)) {
                try {
                    $pixData = $this->client->Pix()->create($result->id);
                    $pixCode = $pixData->payload ?? null;
                    $pixQrCode = $pixData->encodedImage ?? null;
                } catch (\Exception $e) {
                    // PIX pode não estar disponível imediatamente
                }
            }

            $boletoInfo = null;
            if ($billingType === 'BOLETO' && !empty($result->id)) {
                try {
                    $boletoInfo = $this->client->Cobranca()->getInfoBoleto($result->id);
                } catch (\Exception $e) {
                    // Linha digitavel pode nao estar disponivel imediatamente.
                }
            }
            $barcode = $this->resolveBoletoBarcode($result, $boletoInfo);

            // Log da transação
            $transactionId = $this->logTransaction(
                $data['chave'] ?? '',
                $data['id_financeiro'] ?? null,
                'charge',
                $result->id,
                $this->mapStatus($result->status ?? ''),
                (float) $data['value'],
                strtolower($billingType),
                (array) $result,
                $result->invoiceUrl ?? null,
                $pixCode,
                $barcode,
                $this->normalizeAsaasDateTime($result->dueDate ?? null)
            );

            return [
                'success' => true,
                'external_id' => $result->id,
                'status' => $this->mapStatus($result->status ?? ''),
                'payment_url' => $result->invoiceUrl ?? null,
                'pix_code' => $pixCode,
                'pix_qrcode' => $pixQrCode,
                'barcode' => $barcode,
                'boleto_url' => $result->bankSlipUrl ?? null,
                'expires_at' => $result->dueDate ?? null,
                'transaction_id' => $transactionId,
                'raw' => $result,
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
        if ($this->client === null) {
            return [
                'success' => false,
                'message' => 'Cliente Asaas não inicializado',
            ];
        }

        try {
            $result = $this->client->Cobranca()->getById($externalId);

            return [
                'success' => true,
                'status' => $this->mapStatus($result->status ?? ''),
                'paid_at' => $this->normalizeAsaasDateTime($result->paymentDate ?? null),
                'raw' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao consultar cobrança: ' . $e->getMessage(),
                'raw' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function refund(string $externalId, ?float $amount = null): array
    {
        if ($this->client === null) {
            return [
                'success' => false,
                'message' => 'Cliente Asaas não inicializado',
            ];
        }

        try {
            $result = $this->client->Cobranca()->estorno($externalId);

            return [
                'success' => true,
                'refund_id' => $result->id ?? $externalId,
                'raw' => $result,
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
        if ($this->client === null) {
            return [
                'success' => false,
                'message' => 'Cliente Asaas não inicializado',
            ];
        }

        try {
            $result = $this->client->Cobranca()->delete($externalId);

            return [
                'success' => true,
                'raw' => $result,
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
        $token = $this->credentials['webhook_token'] ?? '';

        // Se não tem token configurado, aceita qualquer webhook
        if (empty($token)) {
            return true;
        }

        // Asaas envia o token no header
        $receivedToken = $headers['asaas-access-token']
            ?? $headers['ASAAS-ACCESS-TOKEN']
            ?? $headers['Asaas-Access-Token']
            ?? $headers['HTTP_ASAAS_ACCESS_TOKEN']
            ?? '';

        return hash_equals($token, $receivedToken);
    }

    /**
     * {@inheritdoc}
     */
    public function parseWebhookPayload(array $payload): array
    {
        $payment = $payload['payment'] ?? [];
        $status = (string) ($payment['status'] ?? '');
        $paymentDate = $this->normalizeAsaasDateTime($payment['paymentDate'] ?? null);
        $confirmedDate = $this->normalizeAsaasDateTime($payment['confirmedDate'] ?? null);
        $clientPaymentDate = $this->normalizeAsaasDateTime($payment['clientPaymentDate'] ?? null);

        return [
            'event' => $payload['event'] ?? 'unknown',
            'event_id' => $payload['id'] ?? null,
            'external_id' => $payment['id'] ?? '',
            'payment_id' => $payment['id'] ?? null,
            'external_reference' => $payment['externalReference'] ?? null,
            'status' => $this->mapStatus($status),
            'gateway_status' => $status,
            'billing_type' => $payment['billingType'] ?? null,
            'amount' => isset($payment['value']) ? (float) $payment['value'] : null,
            'net_amount' => isset($payment['netValue']) ? (float) $payment['netValue'] : null,
            'due_date' => $this->normalizeAsaasDateTime($payment['dueDate'] ?? null),
            'payment_date' => $paymentDate,
            'confirmed_date' => $confirmedDate,
            'client_payment_date' => $clientPaymentDate,
            'paid_at' => $paymentDate ?? $clientPaymentDate ?? $confirmedDate,
            'payment_url' => $payment['invoiceUrl'] ?? null,
            'boleto_url' => $payment['bankSlipUrl'] ?? $payment['boletoUrl'] ?? null,
            'barcode' => $this->resolveBoletoBarcode((object) $payment),
            'raw' => $payload,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getDocumentationUrl(): string
    {
        return 'https://docs.asaas.com/';
    }

    /**
     * {@inheritdoc}
     */
    protected function mapStatus(string $gatewayStatus): string
    {
        return match (strtoupper($gatewayStatus)) {
            'PENDING' => 'pending',
            'RECEIVED' => 'paid',
            'CONFIRMED' => 'paid',
            'RECEIVED_IN_CASH' => 'paid',
            'OVERDUE' => 'pending',
            'REFUNDED' => 'refunded',
            'REFUND_REQUESTED' => 'refunded',
            'CHARGEBACK_REQUESTED' => 'refunded',
            'CHARGEBACK_DISPUTE' => 'refunded',
            'AWAITING_CHARGEBACK_REVERSAL' => 'refunded',
            'DUNNING_REQUESTED' => 'pending',
            'DUNNING_RECEIVED' => 'paid',
            'AWAITING_RISK_ANALYSIS' => 'processing',
            default => 'pending',
        };
    }

    /**
     * {@inheritdoc}
     */
    protected function getBaseUrl(): string
    {
        return $this->sandbox
            ? 'https://sandbox.asaas.com/api/v3'
            : 'https://api.asaas.com/api/v3';
    }

    private function resolveBoletoBarcode(mixed $payment, mixed $boletoInfo = null): ?string
    {
        foreach ([$boletoInfo, $payment] as $source) {
            $value = $this->readAsaasField($source, 'identificationField')
                ?? $this->readAsaasField($source, 'barCode')
                ?? $this->readAsaasField($source, 'barcode');

            if ($value !== null) {
                return $value;
            }
        }

        return $this->readAsaasField($payment, 'nossoNumero');
    }

    private function readAsaasField(mixed $source, string $field): ?string
    {
        if ($source === null) {
            return null;
        }

        $value = null;
        if (is_array($source) && array_key_exists($field, $source)) {
            $value = $source[$field];
        } elseif (is_object($source) && isset($source->{$field})) {
            $value = $source->{$field};
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /**
     * Converte datas do Asaas para formato aceito por colunas DATETIME.
     */
    private function normalizeAsaasDateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $formats = [
            '!d/m/Y H:i:s',
            '!d/m/Y H:i',
            '!d/m/Y',
            '!Y-m-d H:i:s',
            '!Y-m-d H:i',
            '!Y-m-d',
        ];

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            $errors = \DateTimeImmutable::getLastErrors();

            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? null : \App\Helpers\DateHelper::formatTimestamp($timestamp, 'Y-m-d H:i:s', false);
    }

    /**
     * Mapeia billing_type para formato Asaas
     */
    private function mapBillingType(string $type): string
    {
        return match (strtolower($type)) {
            'pix' => 'PIX',
            'boleto' => 'BOLETO',
            'credit_card', 'creditcard', 'cartao' => 'CREDIT_CARD',
            default => strtoupper($type),
        };
    }

    /**
     * Busca ou cria cliente no Asaas
     */
    private function findOrCreateCustomer(array $data): array
    {
        if ($this->client === null) {
            return [
                'success' => false,
                'message' => 'Cliente Asaas não inicializado. Verifique as credenciais.',
            ];
        }

        $document = $this->sanitizeDocument($data['customer_document'] ?? '');
        if ($document === '') {
            return [
                'success' => false,
                'message' => 'É necessário informar o CPF/CNPJ do titular para tokenizar o cartão',
            ];
        }

        try {
            // Tenta encontrar cliente existente pelo CPF/CNPJ
            $existing = $this->client->Cliente()->getAll(['cpfCnpj' => $document]);
            if (!empty($existing->data[0]->id)) {
                return [
                    'success' => true,
                    'customer_id' => $existing->data[0]->id,
                    'raw' => $existing,
                ];
            }

            $existingError = $this->extractAsaasMessage($existing);
            if ($existingError !== null) {
                return [
                    'success' => false,
                    'message' => $existingError,
                    'raw' => $existing,
                ];
            }

            // Cria novo cliente
            $customerData = [
                'name' => $data['customer_name'] ?? 'Cliente',
                'cpfCnpj' => $document,
            ];

            if (!empty($data['customer_email'])) {
                $customerData['email'] = $data['customer_email'];
            }

            if (!empty($data['customer_phone'])) {
                $customerData['phone'] = $this->sanitizePhone($data['customer_phone']);
            }

            $newCustomer = $this->client->Cliente()->create($customerData);
            if (!empty($newCustomer->id)) {
                return [
                    'success' => true,
                    'customer_id' => $newCustomer->id,
                    'raw' => $newCustomer,
                ];
            }

            return [
                'success' => false,
                'message' => $this->extractAsaasMessage($newCustomer) ?? 'Não foi possível criar/localizar o cliente no Asaas.',
                'raw' => $newCustomer,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao criar/localizar cliente no Asaas: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Extrai mensagem de erro de respostas do SDK Asaas.
     */
    private function extractAsaasMessage(mixed $response): ?string
    {
        if (is_array($response)) {
            if (!empty($response['error'])) {
                return is_string($response['error']) ? $response['error'] : json_encode($response['error']);
            }
            if (!empty($response['errors'][0]['description'])) {
                return $response['errors'][0]['description'];
            }
        }

        if (is_object($response)) {
            if (!empty($response->error)) {
                if (is_string($response->error)) {
                    return $response->error;
                }
                if (is_array($response->error) && !empty($response->error[0]->description)) {
                    return $response->error[0]->description;
                }
            }
            if (!empty($response->errors) && is_array($response->errors) && !empty($response->errors[0]->description)) {
                return $response->errors[0]->description;
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransparentCheckout(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsCardStorage(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function tokenizeCard(array $cardData): array
    {
        if ($this->client === null) {
            return [
                'success' => false,
                'message' => 'Cliente Asaas não inicializado. Verifique as credenciais.',
            ];
        }

        try {
            // Validar campos obrigatórios
            $this->validateRequiredFields($cardData, ['holder', 'number', 'expiry_month', 'expiry_year', 'cvv']);

            // Buscar ou criar cliente no Asaas
            $customerId = $cardData['customer_id'] ?? null;
            if (empty($customerId) && !empty($cardData['cpf'])) {
                $customerResult = $this->findOrCreateCustomer([
                    'customer_document' => $cardData['cpf'],
                    'customer_name' => $cardData['holder'],
                    'customer_email' => $cardData['email'] ?? '',
                    'customer_phone' => $cardData['phone'] ?? '',
                ]);

                if (!$customerResult['success']) {
                    return [
                        'success' => false,
                        'message' => $customerResult['message'] ?? 'Não foi possível criar/localizar o cliente no Asaas.',
                        'raw' => $customerResult['raw'] ?? null,
                    ];
                }

                $customerId = $customerResult['customer_id'] ?? null;
            }

            if (empty($customerId)) {
                return [
                    'success' => false,
                    'message' => 'É necessário informar o CPF/CNPJ do titular para tokenizar o cartão',
                ];
            }

            // Detectar bandeira pelo número do cartão
            $brand = $this->detectCardBrand($cardData['number']);

            // Tokenizar cartão via API Asaas
            $tokenData = [
                'customer' => $customerId,
                'creditCard' => [
                    'holderName' => $cardData['holder'],
                    'number' => preg_replace('/\D/', '', $cardData['number']),
                    'expiryMonth' => str_pad($cardData['expiry_month'], 2, '0', STR_PAD_LEFT),
                    'expiryYear' => $cardData['expiry_year'],
                    'ccv' => $cardData['cvv'],
                ],
                'creditCardHolderInfo' => [
                    'name' => $cardData['holder'],
                    'cpfCnpj' => $this->sanitizeDocument($cardData['cpf'] ?? ''),
                    'email' => $cardData['email'] ?? null,
                    'phone' => $this->sanitizePhone($cardData['phone'] ?? ''),
                    'postalCode' => $this->sanitizeDocument($cardData['postal_code'] ?? ''),
                    'addressNumber' => $cardData['address_number'] ?? null,
                ],
            ];

            $response = $this->httpRequest(
                'POST',
                $this->getBaseUrl() . '/creditCard/tokenize',
                $tokenData,
                ['access_token: ' . $this->credentials['api_key']]
            );

            if (!$response['success'] || empty($response['body']['creditCardToken'])) {
                $errorMessage = 'Erro ao tokenizar cartão';
                if (!empty($response['body']['errors'])) {
                    $errorMessage = $response['body']['errors'][0]['description'] ?? $errorMessage;
                }
                return [
                    'success' => false,
                    'message' => $errorMessage,
                    'raw' => $response['body'],
                ];
            }

            return [
                'success' => true,
                'token' => $response['body']['creditCardToken'],
                'brand' => $response['body']['creditCardBrand'] ?? $brand,
                'last_digits' => substr(preg_replace('/\D/', '', $cardData['number']), -4),
                'raw' => $response['body'],
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao tokenizar cartão: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Detecta a bandeira do cartão pelo número
     */
    private function detectCardBrand(string $number): string
    {
        $number = preg_replace('/\D/', '', $number);

        // Visa
        if (preg_match('/^4/', $number)) {
            return 'VISA';
        }

        // Mastercard
        if (preg_match('/^(5[1-5]|2[2-7])/', $number)) {
            return 'MASTERCARD';
        }

        // American Express
        if (preg_match('/^3[47]/', $number)) {
            return 'AMEX';
        }

        // Elo
        if (preg_match('/^(636368|438935|504175|451416|636297|5067|4576|4011)/', $number)) {
            return 'ELO';
        }

        // Hipercard
        if (preg_match('/^(606282|3841)/', $number)) {
            return 'HIPERCARD';
        }

        return 'UNKNOWN';
    }
}
