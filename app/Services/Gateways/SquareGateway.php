<?php

namespace App\Services\Gateways;

use Square\SquareClient;
use Square\Environment;
use Square\Models\CreatePaymentRequest;
use Square\Models\Money;

/**
 * Gateway de pagamento Square
 *
 * Integração com a API do Square para pagamentos internacionais.
 * Utiliza o SDK oficial square/square.
 *
 * @see https://developer.squareup.com/docs
 */
class SquareGateway extends AbstractPaymentGateway
{
    private ?SquareClient $client = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $credentials, bool $sandbox = false, ?int $gatewayId = null)
    {
        parent::__construct($credentials, $sandbox, $gatewayId);
        $this->initClient();
    }

    /**
     * Inicializa o cliente Square
     */
    private function initClient(): void
    {
        if (!empty($this->credentials['access_token'])) {
            $environment = $this->sandbox ? Environment::SANDBOX : Environment::PRODUCTION;

            $this->client = new SquareClient([
                'accessToken' => $this->credentials['access_token'],
                'environment' => $environment,
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCode(): string
    {
        return 'square';
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'Square';
    }

    /**
     * {@inheritdoc}
     */
    public function getCountry(): string
    {
        return 'INTL';
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedMethods(): array
    {
        return ['credit_card', 'debit_card'];
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedCurrencies(): array
    {
        return ['USD', 'CAD', 'GBP', 'EUR', 'AUD', 'JPY'];
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigSchema(): array
    {
        return [
            'access_token' => [
                'type' => 'password',
                'required' => true,
                'label' => 'Access Token',
                'placeholder' => 'EAAAl...',
                'help' => 'Token de acesso disponível no Developer Dashboard',
            ],
            'application_id' => [
                'type' => 'string',
                'required' => true,
                'label' => 'Application ID',
                'placeholder' => 'sq0idp-...',
                'help' => 'ID da aplicação no Square',
            ],
            'location_id' => [
                'type' => 'string',
                'required' => true,
                'label' => 'Location ID',
                'placeholder' => 'L...',
                'help' => 'ID da localização (loja) no Square',
            ],
            'webhook_signature_key' => [
                'type' => 'password',
                'required' => false,
                'label' => 'Webhook Signature Key',
                'placeholder' => 'Chave para validar webhooks',
                'help' => 'Configure nas configurações de webhook do Square',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(array $credentials): array
    {
        if (empty($credentials['access_token'])) {
            return [
                'valid' => false,
                'message' => 'Access Token é obrigatório',
            ];
        }

        if (empty($credentials['location_id'])) {
            return [
                'valid' => false,
                'message' => 'Location ID é obrigatório',
            ];
        }

        try {
            $environment = $this->sandbox ? Environment::SANDBOX : Environment::PRODUCTION;

            $client = new SquareClient([
                'accessToken' => $credentials['access_token'],
                'environment' => $environment,
            ]);

            // Tentar listar locations para validar
            $result = $client->getLocationsApi()->listLocations();

            if ($result->isSuccess()) {
                return [
                    'valid' => true,
                    'message' => 'Credenciais válidas',
                ];
            }

            $errors = $result->getErrors();
            $errorMsg = !empty($errors) ? $errors[0]->getDetail() : 'Erro desconhecido';

            return [
                'valid' => false,
                'message' => 'Credenciais inválidas: ' . $errorMsg,
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
        if ($this->client === null) {
            return [
                'success' => false,
                'message' => 'Cliente Square não inicializado. Verifique as credenciais.',
            ];
        }

        try {
            $this->validateRequiredFields($data, ['value']);

            $locationId = $this->credentials['location_id'] ?? '';
            if (empty($locationId)) {
                return [
                    'success' => false,
                    'message' => 'Location ID não configurado',
                ];
            }

            // Converter valor para centavos
            $amountInCents = $this->toCents((float) $data['value']);
            $currency = strtoupper($data['currency'] ?? 'BRL');

            // Se tem source_id (nonce do cartão), criar pagamento direto
            if (!empty($data['source_id'])) {
                $money = new Money();
                $money->setAmount($amountInCents);
                $money->setCurrency($currency);

                $paymentRequest = new CreatePaymentRequest($data['source_id'], uniqid('pay_'));
                $paymentRequest->setAmountMoney($money);
                $paymentRequest->setLocationId($locationId);

                if (!empty($data['description'])) {
                    $paymentRequest->setNote($data['description']);
                }

                if (!empty($data['external_reference'])) {
                    $paymentRequest->setReferenceId($data['external_reference']);
                }

                $result = $this->client->getPaymentsApi()->createPayment($paymentRequest);

                if (!$result->isSuccess()) {
                    $errors = $result->getErrors();
                    $errorMsg = !empty($errors) ? $errors[0]->getDetail() : 'Erro ao criar pagamento';
                    return [
                        'success' => false,
                        'message' => $errorMsg,
                    ];
                }

                $payment = $result->getResult()->getPayment();
                $status = $this->mapStatus($payment->getStatus());

                // Log da transação
                $transactionId = $this->logTransaction(
                    $data['chave'] ?? '',
                    $data['id_financeiro'] ?? null,
                    'charge',
                    $payment->getId(),
                    $status,
                    (float) $data['value'],
                    'credit_card',
                    json_decode(json_encode($payment), true)
                );

                return [
                    'success' => true,
                    'external_id' => $payment->getId(),
                    'status' => $status,
                    'transaction_id' => $transactionId,
                    'raw' => json_decode(json_encode($payment), true),
                ];
            }

            // Criar Checkout Link para pagamento via URL
            $checkoutApi = $this->client->getCheckoutApi();

            // Usar Quick Pay para link simples
            $quickPayRequest = new \Square\Models\CreatePaymentLinkRequest();

            $quickPay = new \Square\Models\QuickPay(
                $data['description'] ?? 'Pagamento',
                new Money()
            );
            $quickPay->getPriceMoney()->setAmount($amountInCents);
            $quickPay->getPriceMoney()->setCurrency($currency);
            $quickPay->setLocationId($locationId);

            $quickPayRequest->setQuickPay($quickPay);
            $quickPayRequest->setIdempotencyKey(uniqid('checkout_'));

            $result = $checkoutApi->createPaymentLink($quickPayRequest);

            if (!$result->isSuccess()) {
                $errors = $result->getErrors();
                $errorMsg = !empty($errors) ? $errors[0]->getDetail() : 'Erro ao criar link de pagamento';
                return [
                    'success' => false,
                    'message' => $errorMsg,
                ];
            }

            $paymentLink = $result->getResult()->getPaymentLink();

            // Log da transação
            $transactionId = $this->logTransaction(
                $data['chave'] ?? '',
                $data['id_financeiro'] ?? null,
                'charge',
                $paymentLink->getId(),
                'pending',
                (float) $data['value'],
                'credit_card',
                json_decode(json_encode($paymentLink), true),
                $paymentLink->getUrl()
            );

            return [
                'success' => true,
                'external_id' => $paymentLink->getId(),
                'status' => 'pending',
                'payment_url' => $paymentLink->getUrl(),
                'transaction_id' => $transactionId,
                'raw' => json_decode(json_encode($paymentLink), true),
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
                'message' => 'Cliente Square não inicializado',
            ];
        }

        try {
            $result = $this->client->getPaymentsApi()->getPayment($externalId);

            if (!$result->isSuccess()) {
                $errors = $result->getErrors();
                $errorMsg = !empty($errors) ? $errors[0]->getDetail() : 'Erro ao consultar pagamento';
                return [
                    'success' => false,
                    'message' => $errorMsg,
                ];
            }

            $payment = $result->getResult()->getPayment();
            $status = $this->mapStatus($payment->getStatus());

            return [
                'success' => true,
                'status' => $status,
                'paid_at' => $status === 'paid' ? now() : null,
                'raw' => json_decode(json_encode($payment), true),
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
                'message' => 'Cliente Square não inicializado',
            ];
        }

        try {
            $refundRequest = new \Square\Models\RefundPaymentRequest(
                uniqid('refund_'),
                new Money()
            );

            $refundRequest->setPaymentId($externalId);

            if ($amount !== null) {
                $refundRequest->getAmountMoney()->setAmount($this->toCents($amount));
                $refundRequest->getAmountMoney()->setCurrency('BRL');
            }

            $result = $this->client->getRefundsApi()->refundPayment($refundRequest);

            if (!$result->isSuccess()) {
                $errors = $result->getErrors();
                $errorMsg = !empty($errors) ? $errors[0]->getDetail() : 'Erro ao estornar';
                return [
                    'success' => false,
                    'message' => $errorMsg,
                ];
            }

            $refund = $result->getResult()->getRefund();

            return [
                'success' => true,
                'refund_id' => $refund->getId(),
                'raw' => json_decode(json_encode($refund), true),
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
                'message' => 'Cliente Square não inicializado',
            ];
        }

        try {
            $result = $this->client->getPaymentsApi()->cancelPayment($externalId);

            if (!$result->isSuccess()) {
                $errors = $result->getErrors();
                $errorMsg = !empty($errors) ? $errors[0]->getDetail() : 'Erro ao cancelar';
                return [
                    'success' => false,
                    'message' => $errorMsg,
                ];
            }

            return [
                'success' => true,
                'raw' => json_decode(json_encode($result->getResult()), true),
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
        $signatureKey = $this->credentials['webhook_signature_key'] ?? '';

        if (empty($signatureKey)) {
            return true;
        }

        $signature = $headers['X-SQUARE-SIGNATURE']
            ?? $headers['x-square-signature']
            ?? $headers['HTTP_X_SQUARE_SIGNATURE']
            ?? '';

        if (empty($signature)) {
            return false;
        }

        $body = file_get_contents('php://input');
        $notificationUrl = $this->credentials['webhook_url'] ?? '';

        $computedSignature = base64_encode(hash_hmac('sha256', $notificationUrl . $body, $signatureKey, true));

        return hash_equals($computedSignature, $signature);
    }

    /**
     * {@inheritdoc}
     */
    public function parseWebhookPayload(array $payload): array
    {
        $eventType = $payload['type'] ?? 'unknown';
        $object = $payload['data']['object'] ?? [];
        $payment = $object['payment'] ?? $object;

        $externalId = $payment['id'] ?? '';
        $status = '';

        if (!empty($payment['status'])) {
            $status = $this->mapStatus($payment['status']);
        }

        return [
            'event' => $eventType,
            'external_id' => $externalId,
            'status' => $status,
            'paid_at' => $status === 'paid' ? now() : null,
            'raw' => $payload,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getDocumentationUrl(): string
    {
        return 'https://developer.squareup.com/docs';
    }

    /**
     * {@inheritdoc}
     */
    protected function mapStatus(string $gatewayStatus): string
    {
        return match (strtoupper($gatewayStatus)) {
            'COMPLETED', 'APPROVED' => 'paid',
            'PENDING', 'AUTHORIZED' => 'pending',
            'CANCELED', 'CANCELLED', 'FAILED' => 'cancelled',
            default => 'pending',
        };
    }

    /**
     * {@inheritdoc}
     */
    protected function getBaseUrl(): string
    {
        return $this->sandbox
            ? 'https://connect.squareupsandbox.com/v2'
            : 'https://connect.squareup.com/v2';
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
     *
     * Square não suporta armazenamento persistente de cartão.
     * Os nonces/tokens expiram em 24 horas.
     */
    public function supportsCardStorage(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * Square usa Web Payments SDK no frontend para tokenização.
     * Este método recebe o source_id (nonce) já criado pelo SDK
     * e retorna as informações do cartão.
     */
    public function tokenizeCard(array $cardData): array
    {
        // Square não permite criar tokens via API server-side
        // O nonce deve ser criado pelo Web Payments SDK no frontend

        if (empty($cardData['source_id'])) {
            return [
                'success' => false,
                'message' => 'Square requer tokenização via Web Payments SDK no frontend',
            ];
        }

        // Apenas retorna as informações recebidas do frontend
        return [
            'success' => true,
            'token' => $cardData['source_id'],
            'brand' => strtoupper($cardData['brand'] ?? 'unknown'),
            'last_digits' => $cardData['last_digits'] ?? '****',
            'raw' => $cardData,
        ];
    }

    /**
     * Retorna o Application ID do Square para uso no frontend
     */
    public function getApplicationId(): ?string
    {
        return $this->credentials['application_id'] ?? null;
    }

    /**
     * Retorna o Location ID do Square para uso no frontend
     */
    public function getLocationId(): ?string
    {
        return $this->credentials['location_id'] ?? null;
    }
}
