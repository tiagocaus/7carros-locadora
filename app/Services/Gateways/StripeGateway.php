<?php

namespace App\Services\Gateways;

/**
 * Gateway de pagamento Stripe
 *
 * Integração com a API do Stripe para pagamentos internacionais.
 * Utiliza o SDK oficial stripe/stripe-php.
 *
 * @see https://stripe.com/docs/api
 */
class StripeGateway extends AbstractPaymentGateway implements AuthorizationHoldInterface
{
    private ?\Stripe\StripeClient $client = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $credentials, bool $sandbox = false, ?int $gatewayId = null)
    {
        parent::__construct($credentials, $sandbox, $gatewayId);
        $this->initClient();
    }

    /**
     * Inicializa o cliente Stripe
     */
    private function initClient(): void
    {
        if (!empty($this->credentials['secret_key'])) {
            \Stripe\Stripe::setApiKey($this->credentials['secret_key']);
            $this->client = new \Stripe\StripeClient($this->credentials['secret_key']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCode(): string
    {
        return 'stripe';
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'Stripe';
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
        return ['BRL', 'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'MXN', 'CHF'];
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigSchema(): array
    {
        return [
            'secret_key' => [
                'type' => 'password',
                'required' => true,
                'label' => 'Secret Key',
                'placeholder' => 'sk_live_... ou sk_test_...',
                'help' => 'Chave secreta disponível no Dashboard do Stripe',
            ],
            'publishable_key' => [
                'type' => 'string',
                'required' => true,
                'label' => 'Publishable Key',
                'placeholder' => 'pk_live_... ou pk_test_...',
                'help' => 'Chave pública para uso no frontend',
            ],
            'webhook_secret' => [
                'type' => 'password',
                'required' => false,
                'label' => 'Webhook Secret',
                'placeholder' => 'whsec_...',
                'help' => 'Segredo do webhook para validação de assinatura',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(array $credentials): array
    {
        if (empty($credentials['secret_key'])) {
            return [
                'valid' => false,
                'message' => 'Secret Key é obrigatória',
            ];
        }

        try {
            $client = new \Stripe\StripeClient($credentials['secret_key']);
            $client->customers->all(['limit' => 1]);

            return [
                'valid' => true,
                'message' => 'Credenciais válidas',
            ];
        } catch (\Stripe\Exception\AuthenticationException $e) {
            return [
                'valid' => false,
                'message' => 'Chave de API inválida',
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
                'message' => 'Cliente Stripe não inicializado. Verifique as credenciais.',
            ];
        }

        try {
            $this->validateRequiredFields($data, ['value']);

            // Converter valor para centavos (Stripe usa menor unidade da moeda)
            $amountInCents = $this->toCents((float) $data['value']);

            // Criar PaymentIntent ou Checkout Session
            $currency = $data['currency'] ?? 'brl';

            // Se tem token de pagamento, criar PaymentIntent direto
            if (!empty($data['payment_method_id'])) {
                $paymentIntent = $this->client->paymentIntents->create([
                    'amount' => $amountInCents,
                    'currency' => $currency,
                    'payment_method' => $data['payment_method_id'],
                    'confirmation_method' => 'manual',
                    'confirm' => true,
                    'automatic_payment_methods' => [
                        'enabled' => true,
                        'allow_redirects' => 'never',
                    ],
                    'description' => $data['description'] ?? 'Pagamento',
                    'metadata' => [
                        'external_reference' => $data['external_reference'] ?? '',
                        'id_financeiro' => $data['id_financeiro'] ?? '',
                    ],
                ]);

                $status = $this->mapStatus($paymentIntent->status);

                // Log da transação
                $transactionId = $this->logTransaction(
                    $data['chave'] ?? '',
                    $data['id_financeiro'] ?? null,
                    'charge',
                    $paymentIntent->id,
                    $status,
                    (float) $data['value'],
                    'credit_card',
                    $paymentIntent->toArray()
                );

                return [
                    'success' => true,
                    'external_id' => $paymentIntent->id,
                    'status' => $status,
                    'transaction_id' => $transactionId,
                    'client_secret' => $paymentIntent->client_secret,
                    'raw' => $paymentIntent->toArray(),
                ];
            }

            // Criar Checkout Session para pagamento via URL
            $successUrl = $data['success_url'] ?? ($data['return_url'] ?? url('/pagar/sucesso'));
            $cancelUrl = $data['cancel_url'] ?? ($data['return_url'] ?? url('/pagar/erro'));

            $sessionParams = [
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => [
                            'name' => $data['description'] ?? 'Pagamento',
                        ],
                        'unit_amount' => $amountInCents,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelUrl,
                'metadata' => [
                    'external_reference' => $data['external_reference'] ?? '',
                    'id_financeiro' => $data['id_financeiro'] ?? '',
                ],
            ];

            // Adicionar email do cliente se disponível
            if (!empty($data['customer_email'])) {
                $sessionParams['customer_email'] = $data['customer_email'];
            }

            $session = $this->client->checkout->sessions->create($sessionParams);

            // Log da transação
            $transactionId = $this->logTransaction(
                $data['chave'] ?? '',
                $data['id_financeiro'] ?? null,
                'charge',
                $session->id,
                'pending',
                (float) $data['value'],
                'credit_card',
                $session->toArray(),
                $session->url
            );

            return [
                'success' => true,
                'external_id' => $session->id,
                'status' => 'pending',
                'payment_url' => $session->url,
                'transaction_id' => $transactionId,
                'raw' => $session->toArray(),
            ];

        } catch (\Stripe\Exception\CardException $e) {
            return [
                'success' => false,
                'message' => 'Erro no cartão: ' . $e->getMessage(),
                'raw' => [],
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
                'message' => 'Cliente Stripe não inicializado',
            ];
        }

        try {
            // Verificar se é PaymentIntent ou Checkout Session
            if (str_starts_with($externalId, 'pi_')) {
                $paymentIntent = $this->client->paymentIntents->retrieve($externalId);

                return [
                    'success' => true,
                    'status' => $this->mapStatus($paymentIntent->status),
                    'paid_at' => $paymentIntent->status === 'succeeded' ? now() : null,
                    'raw' => $paymentIntent->toArray(),
                ];
            }

            if (str_starts_with($externalId, 'cs_')) {
                $session = $this->client->checkout->sessions->retrieve($externalId);

                return [
                    'success' => true,
                    'status' => $this->mapStatus($session->payment_status),
                    'paid_at' => $session->payment_status === 'paid' ? now() : null,
                    'raw' => $session->toArray(),
                ];
            }

            return [
                'success' => false,
                'message' => 'ID de pagamento inválido',
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
                'message' => 'Cliente Stripe não inicializado',
            ];
        }

        try {
            $params = [];

            // Se for Checkout Session, pegar o PaymentIntent
            if (str_starts_with($externalId, 'cs_')) {
                $session = $this->client->checkout->sessions->retrieve($externalId);
                $params['payment_intent'] = $session->payment_intent;
            } else {
                $params['payment_intent'] = $externalId;
            }

            if ($amount !== null) {
                $params['amount'] = $this->toCents($amount);
            }

            $refund = $this->client->refunds->create($params);

            return [
                'success' => true,
                'refund_id' => $refund->id,
                'raw' => $refund->toArray(),
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
                'message' => 'Cliente Stripe não inicializado',
            ];
        }

        try {
            // Stripe não tem conceito de cancelamento como outros gateways
            // Para PaymentIntent não capturado, podemos cancelar
            if (str_starts_with($externalId, 'pi_')) {
                $paymentIntent = $this->client->paymentIntents->cancel($externalId);

                return [
                    'success' => true,
                    'raw' => $paymentIntent->toArray(),
                ];
            }

            // Checkout Sessions expiram automaticamente
            if (str_starts_with($externalId, 'cs_')) {
                $session = $this->client->checkout->sessions->expire($externalId);

                return [
                    'success' => true,
                    'raw' => $session->toArray(),
                ];
            }

            return [
                'success' => false,
                'message' => 'Não é possível cancelar este pagamento',
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
        $webhookSecret = $this->credentials['webhook_secret'] ?? '';

        if (empty($webhookSecret)) {
            return true; // Se não configurado, aceita
        }

        $signature = $headers['STRIPE-SIGNATURE']
            ?? $headers['Stripe-Signature']
            ?? $headers['HTTP_STRIPE_SIGNATURE']
            ?? '';

        if (empty($signature)) {
            return false;
        }

        try {
            $payloadRaw = file_get_contents('php://input');
            \Stripe\Webhook::constructEvent($payloadRaw, $signature, $webhookSecret);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function parseWebhookPayload(array $payload): array
    {
        $event = $payload['type'] ?? 'unknown';
        $object = $payload['data']['object'] ?? [];

        $externalId = $object['id'] ?? '';
        $status = '';

        // Mapear status baseado no tipo de evento
        if (str_contains($event, 'payment_intent')) {
            $status = $this->mapStatus($object['status'] ?? '');
        } elseif (str_contains($event, 'checkout.session')) {
            $status = $this->mapStatus($object['payment_status'] ?? '');
            // Para checkout session, usar session id
            $externalId = $object['id'] ?? '';
        }

        return [
            'event' => $event,
            'external_id' => $externalId,
            'status' => $status,
            'paid_at' => in_array($status, ['paid'], true) ? now() : null,
            'raw' => $payload,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getDocumentationUrl(): string
    {
        return 'https://stripe.com/docs/api';
    }

    /**
     * {@inheritdoc}
     */
    protected function mapStatus(string $gatewayStatus): string
    {
        return match (strtolower($gatewayStatus)) {
            'succeeded', 'paid', 'complete' => 'paid',
            'requires_capture' => 'authorized',
            'requires_payment_method', 'requires_confirmation', 'requires_action', 'processing', 'unpaid' => 'pending',
            'canceled', 'expired', 'no_payment_required' => 'cancelled',
            default => 'pending',
        };
    }

    /**
     * {@inheritdoc}
     */
    protected function getBaseUrl(): string
    {
        return 'https://api.stripe.com/v1';
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
     *
     * Stripe usa Stripe.js no frontend para tokenização segura.
     * Este método recebe o payment_method_id já criado pelo Stripe.js
     * e retorna as informações do cartão para armazenamento.
     */
    public function tokenizeCard(array $cardData): array
    {
        if ($this->client === null) {
            return [
                'success' => false,
                'message' => 'Cliente Stripe não inicializado. Verifique as credenciais.',
            ];
        }

        try {
            // Stripe EXIGE tokenização via Stripe.js no frontend
            // O payment_method_id deve ser criado pelo Stripe.js antes de chegar aqui
            if (empty($cardData['payment_method_id'])) {
                return [
                    'success' => false,
                    'message' => 'Stripe requer tokenização via Stripe.js no frontend. Use stripe.createPaymentMethod() primeiro.',
                ];
            }

            // Validar formato do payment_method_id
            $paymentMethodId = $cardData['payment_method_id'];
            if (!str_starts_with($paymentMethodId, 'pm_')) {
                return [
                    'success' => false,
                    'message' => 'Payment method ID inválido. Deve começar com "pm_".',
                ];
            }

            // Buscar dados do payment_method para retornar informações do cartão
            $paymentMethod = $this->client->paymentMethods->retrieve($paymentMethodId);

            // Criar Customer no Stripe e vincular PM para permitir reutilizacao
            $customerParams = ['metadata' => ['source' => 'tokenizeCard']];
            if (!empty($cardData['customer_name'])) {
                $customerParams['name'] = $cardData['customer_name'];
            }
            if (!empty($cardData['email'])) {
                $customerParams['email'] = $cardData['email'];
            }
            if (!empty($cardData['cpf'])) {
                $customerParams['metadata']['cpf'] = $cardData['cpf'];
            }

            $customer = $this->client->customers->create($customerParams);
            $this->client->paymentMethods->attach($paymentMethodId, ['customer' => $customer->id]);

            return [
                'success' => true,
                'token' => $paymentMethod->id,
                'brand' => strtoupper($paymentMethod->card->brand ?? 'unknown'),
                'last_digits' => $paymentMethod->card->last4 ?? '****',
                'gateway_customer_id' => $customer->id,
                'raw' => $paymentMethod->toArray(),
            ];

        } catch (\Stripe\Exception\CardException $e) {
            return [
                'success' => false,
                'message' => 'Erro no cartão: ' . $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao validar cartão: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Retorna a chave pública do Stripe para uso no frontend
     */
    public function getPublishableKey(): ?string
    {
        return $this->credentials['publishable_key'] ?? null;
    }

    // ========== Stripe Customer Management ==========

    /**
     * Garante que existe um Customer no Stripe para o cartao/cliente
     * e que o PaymentMethod esta vinculado a ele.
     *
     * Busca gateway_customer_id em clientes_cartoes. Se nao existe,
     * cria Customer no Stripe, vincula o PM e salva o ID.
     *
     * @param array $data Deve conter payment_method_id e opcionalmente id_cartao_registro (ID em clientes_cartoes)
     * @return string Stripe Customer ID (cus_xxx)
     */
    private function ensureCustomer(array $data): string
    {
        $idCartaoRegistro = $data['id_cartao_registro'] ?? null;

        // Buscar customer_id ja salvo no registro do cartao
        if ($idCartaoRegistro) {
            $cartaoModel = new \App\Models\ClienteCartao();
            $cartao = $cartaoModel->buscarPorId((int) $idCartaoRegistro);
            if ($cartao && !empty($cartao['gateway_customer_id'])) {
                return $cartao['gateway_customer_id'];
            }
        }

        // Fallback: criar Customer e attach (nao deveria chegar aqui se tokenizeCard funcionou)
        $customerParams = [
            'metadata' => [
                'id_cliente' => $data['metadata']['id_cliente'] ?? '',
                'chave' => $data['chave'] ?? '',
            ],
        ];
        if (!empty($data['customer_name'])) {
            $customerParams['name'] = $data['customer_name'];
        }

        $customer = $this->client->customers->create($customerParams);
        $this->client->paymentMethods->attach($data['payment_method_id'], ['customer' => $customer->id]);

        if ($idCartaoRegistro) {
            $cartaoModel = $cartaoModel ?? new \App\Models\ClienteCartao();
            $cartaoModel->atualizarCustomerId((int) $idCartaoRegistro, $customer->id);
        }

        return $customer->id;
    }

    // ========== AuthorizationHoldInterface ==========

    /**
     * {@inheritdoc}
     */
    public function supportsAuthorizationHold(): bool
    {
        return true;
    }

    /**
     * Cria um authorization hold (pre-autorizacao) no cartao
     *
     * Usa PaymentIntent com capture_method='manual' para reservar
     * o valor no limite do cartao sem efetuar a cobranca.
     * Hold padrao: 7 dias. Extended: ate 31 dias.
     */
    public function createHold(array $data): array
    {
        if ($this->client === null) {
            return [
                'success' => false,
                'message' => 'Cliente Stripe não inicializado',
            ];
        }

        try {
            $this->validateRequiredFields($data, ['payment_method_id', 'amount']);

            $amountInCents = $this->toCents((float) $data['amount']);
            $currency = $data['currency'] ?? 'brl';

            // Garantir Customer no Stripe e vincular PaymentMethod
            $customerId = $this->ensureCustomer($data);

            $params = [
                'amount' => $amountInCents,
                'currency' => $currency,
                'customer' => $customerId,
                'payment_method' => $data['payment_method_id'],
                'capture_method' => 'manual',
                'confirm' => true,
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never',
                ],
                'description' => $data['description'] ?? 'Bloqueio/Pre-autorização',
                'metadata' => array_merge(
                    $data['metadata'] ?? [],
                    [
                        'type' => 'authorization_hold',
                        'id_financeiro' => $data['id_financeiro'] ?? '',
                    ]
                ),
            ];

            // Extended authorization: ate 31 dias (requer conta Stripe com recurso habilitado)
            if (!empty($data['extended_authorization'])) {
                $params['payment_method_options'] = [
                    'card' => [
                        'request_extended_authorization' => 'if_available',
                    ],
                ];
            }

            $paymentIntent = $this->client->paymentIntents->create($params);

            $status = $this->mapStatus($paymentIntent->status);

            // Calcular data de expiracao (7 dias padrao, 31 se extended)
            $expiresInDays = (!empty($data['extended_authorization'])) ? 31 : 7;
            $expiresAt = \App\Helpers\DateHelper::addDaysForDatabase($expiresInDays, null, 'Y-m-d H:i:s');

            // Log da transacao
            $transactionId = $this->logTransaction(
                $data['chave'] ?? '',
                $data['id_financeiro'] ?? null,
                'authorization_hold',
                $paymentIntent->id,
                $status,
                (float) $data['amount'],
                'credit_card',
                $paymentIntent->toArray()
            );

            return [
                'success' => true,
                'external_id' => $paymentIntent->id,
                'status' => $status,
                'transaction_id' => $transactionId,
                'client_secret' => $paymentIntent->client_secret,
                'expires_at' => $expiresAt,
                'raw' => $paymentIntent->toArray(),
            ];

        } catch (\Stripe\Exception\CardException $e) {
            return [
                'success' => false,
                'message' => 'Erro no cartão: ' . $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao criar bloqueio: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Captura (total ou parcial) um hold autorizado
     */
    public function captureHold(string $externalId, ?float $amount = null): array
    {
        if ($this->client === null) {
            return [
                'success' => false,
                'message' => 'Cliente Stripe não inicializado',
            ];
        }

        try {
            $params = [];
            if ($amount !== null) {
                $params['amount_to_capture'] = $this->toCents($amount);
            }

            $paymentIntent = $this->client->paymentIntents->capture($externalId, $params);

            $this->updateTransactionStatus($externalId, $this->mapStatus($paymentIntent->status));

            return [
                'success' => true,
                'status' => $this->mapStatus($paymentIntent->status),
                'raw' => $paymentIntent->toArray(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao capturar bloqueio: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Libera (cancela) um hold autorizado sem cobrar
     */
    public function releaseHold(string $externalId): array
    {
        if ($this->client === null) {
            return [
                'success' => false,
                'message' => 'Cliente Stripe não inicializado',
            ];
        }

        try {
            $paymentIntent = $this->client->paymentIntents->cancel($externalId);

            $this->updateTransactionStatus($externalId, 'cancelled');

            return [
                'success' => true,
                'status' => 'released',
                'raw' => $paymentIntent->toArray(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao liberar bloqueio: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Consulta o status de um hold
     */
    public function getHoldStatus(string $externalId): array
    {
        if ($this->client === null) {
            return [
                'success' => false,
                'message' => 'Cliente Stripe não inicializado',
            ];
        }

        try {
            $paymentIntent = $this->client->paymentIntents->retrieve($externalId);

            $capturedAmount = null;
            if ($paymentIntent->status === 'succeeded') {
                $capturedAmount = $this->fromCents($paymentIntent->amount_received);
            }

            return [
                'success' => true,
                'status' => $this->mapStatus($paymentIntent->status),
                'amount' => $this->fromCents($paymentIntent->amount),
                'captured_amount' => $capturedAmount,
                'raw' => $paymentIntent->toArray(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao consultar bloqueio: ' . $e->getMessage(),
            ];
        }
    }
}
