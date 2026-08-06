<?php

namespace App\Services\Gateways;

use Efi\Exception\EfiException;
use Efi\EfiPay;

/**
 * Gateway de pagamento EFI Pay (antigo Gerencianet)
 *
 * Integração com a API do EFI Pay para PIX e Boleto.
 * Utiliza o SDK efipay/sdk-php-apis-efi já instalado no projeto.
 *
 * @see https://dev.efipay.com.br/
 */
class EfipayGateway extends AbstractPaymentGateway
{
    private ?EfiPay $client = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $credentials, bool $sandbox = false, ?int $gatewayId = null)
    {
        parent::__construct($credentials, $sandbox, $gatewayId);
        $this->initClient();
    }

    /**
     * Inicializa o cliente EFI Pay
     */
    private function initClient(): void
    {
        if (!empty($this->credentials['client_id']) && !empty($this->credentials['client_secret'])) {
            try {
                $options = [
                    'client_id' => $this->credentials['client_id'],
                    'client_secret' => $this->credentials['client_secret'],
                    'sandbox' => $this->sandbox,
                    'timeout' => 30,
                ];

                // Upload gerenciado; caminho legado permanece somente para transição.
                if (!empty($this->credentials['certificado_arquivo'])) {
                    $options['certificate'] = (new GatewayCertificateService())->storedPath(
                        (string) $this->credentials['certificado_arquivo']
                    );
                    $options['pwdCertificate'] = decrypt((string) ($this->credentials['certificado_senha'] ?? '')) ?? '';
                } elseif (!empty($this->credentials['certificate_path']) && file_exists($this->credentials['certificate_path'])) {
                    $options['certificate'] = $this->credentials['certificate_path'];
                }

                $this->client = new EfiPay($options);
            } catch (\Exception $e) {
                $this->client = null;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCode(): string
    {
        return 'efipay';
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'EFI Pay';
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
                'placeholder' => 'Client_Id_...',
                'help' => 'ID do cliente disponível no painel EFI Pay',
            ],
            'client_secret' => [
                'type' => 'password',
                'required' => true,
                'label' => 'Client Secret',
                'placeholder' => 'Client_Secret_...',
                'help' => 'Chave secreta do cliente',
            ],
            'pix_key' => [
                'type' => 'string',
                'required' => false,
                'label' => 'Chave PIX',
                'placeholder' => 'email@exemplo.com ou CPF',
                'help' => 'Chave PIX cadastrada na conta EFI Pay',
            ],
        ];
    }

    public function getCertificateConfig(): ?array
    {
        return ['required' => false, 'formats' => ['pfx', 'p12', 'pem', 'crt', 'cer']];
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

        try {
            $options = [
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'sandbox' => $this->sandbox,
                'timeout' => 30,
            ];

            $client = new EfiPay($options);

            // Tenta listar cobranças como teste
            $params = ['begin_date' => today(), 'end_date' => today()];
            $client->listCharges($params);

            return [
                'valid' => true,
                'message' => 'Credenciais válidas',
            ];
        } catch (EfiException $e) {
            return [
                'valid' => false,
                'message' => 'Credenciais inválidas: ' . $e->getMessage(),
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
                'message' => 'Cliente EFI Pay não inicializado. Verifique as credenciais.',
            ];
        }

        try {
            $this->validateRequiredFields($data, ['value', 'billing_type']);

            $billingType = strtoupper($data['billing_type']);

            if ($billingType === 'PIX') {
                return $this->createPixCharge($data);
            }

            return $this->createBoletoCharge($data);
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
    private function createPixCharge(array $data): array
    {
        $txid = $this->generateTxId();

        $body = [
            'calendario' => [
                'expiracao' => 3600 * 24, // 24 horas
            ],
            'valor' => [
                'original' => $this->formatAmount((float) $data['value']),
            ],
            'chave' => $this->credentials['pix_key'] ?? '',
        ];

        if (!empty($data['description'])) {
            $body['infoAdicionais'] = [
                ['nome' => 'Descricao', 'valor' => substr($data['description'], 0, 200)],
            ];
        }

        if (!empty($data['customer_document'])) {
            $doc = $this->sanitizeDocument($data['customer_document']);
            $body['devedor'] = [
                strlen($doc) === 11 ? 'cpf' : 'cnpj' => $doc,
                'nome' => $data['customer_name'] ?? 'Cliente',
            ];
        }

        $params = ['txid' => $txid];

        try {
            $result = $this->client->pixCreateImmediateCharge($params, $body);

            // Gerar QR Code
            $qrParams = ['id' => $result['loc']['id']];
            $qrCode = $this->client->pixGenerateQRCode($qrParams);

            $status = $this->mapStatus($result['status'] ?? 'ATIVA');

            $transactionId = $this->logTransaction(
                $data['chave'] ?? '',
                $data['id_financeiro'] ?? null,
                'charge',
                $result['txid'],
                $status,
                (float) $data['value'],
                'pix',
                $result,
                null,
                $qrCode['qrcode'] ?? null,
                null,
                \App\Helpers\DateHelper::addDaysForDatabase(1, null, 'Y-m-d H:i:s')
            );

            return [
                'success' => true,
                'external_id' => $result['txid'],
                'status' => $status,
                'pix_code' => $qrCode['qrcode'] ?? null,
                'pix_qrcode' => $qrCode['imagemQrcode'] ?? null,
                'transaction_id' => $transactionId,
                'raw' => $result,
            ];
        } catch (EfiException $e) {
            return [
                'success' => false,
                'message' => 'Erro EFI Pay: ' . $e->getMessage(),
                'raw' => [],
            ];
        }
    }

    /**
     * Cria cobrança Boleto
     */
    private function createBoletoCharge(array $data): array
    {
        // Criar cobrança
        $chargeBody = [
            'items' => [
                [
                    'name' => $data['description'] ?? 'Pagamento',
                    'value' => $this->toCents((float) $data['value']),
                    'amount' => 1,
                ],
            ],
        ];

        try {
            $chargeResult = $this->client->createCharge([], $chargeBody);

            if (empty($chargeResult['data']['charge_id'])) {
                return [
                    'success' => false,
                    'message' => 'Erro ao criar cobrança: resposta inválida',
                ];
            }

            $chargeId = $chargeResult['data']['charge_id'];

            // Definir forma de pagamento (Boleto)
            $paymentBody = [
                'payment' => [
                    'banking_billet' => [
                        'expire_at' => $this->resolveDueDate($data['due_date'] ?? null),
                        'customer' => $this->buildCustomerData($data),
                    ],
                ],
            ];

            // Adicionar multa e juros se configurados
            if (!empty($this->credentials['multa'])) {
                $paymentBody['payment']['banking_billet']['configurations'] = [
                    'fine' => (int) ($this->credentials['multa'] * 100),
                    'interest' => (int) (($this->credentials['juros_por_dia'] ?? 0) * 100),
                ];
            }

            $params = ['id' => $chargeId];
            $paymentResult = $this->client->payCharge($params, $paymentBody);

            $status = $this->mapStatus($paymentResult['data']['status'] ?? 'waiting');

            $transactionId = $this->logTransaction(
                $data['chave'] ?? '',
                $data['id_financeiro'] ?? null,
                'charge',
                (string) $chargeId,
                $status,
                (float) $data['value'],
                'boleto',
                $paymentResult,
                $paymentResult['data']['link'] ?? null,
                null,
                $paymentResult['data']['barcode'] ?? null,
                $this->resolveDueDate($data['due_date'] ?? null)
            );

            return [
                'success' => true,
                'external_id' => (string) $chargeId,
                'status' => $status,
                'barcode' => $paymentResult['data']['barcode'] ?? null,
                'boleto_url' => $paymentResult['data']['link'] ?? null,
                'payment_url' => $paymentResult['data']['link'] ?? null,
                'expires_at' => $paymentResult['data']['expire_at'] ?? null,
                'transaction_id' => $transactionId,
                'raw' => $paymentResult,
            ];
        } catch (EfiException $e) {
            return [
                'success' => false,
                'message' => 'Erro EFI Pay: ' . $e->getMessage(),
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
                'message' => 'Cliente EFI Pay não inicializado',
            ];
        }

        try {
            // Verificar se é PIX (txid) ou Boleto (charge_id)
            if (strlen($externalId) > 20) {
                // PIX
                $params = ['txid' => $externalId];
                $result = $this->client->pixDetailCharge($params);

                return [
                    'success' => true,
                    'status' => $this->mapStatus($result['status'] ?? ''),
                    'paid_at' => $result['pix'][0]['horario'] ?? null,
                    'raw' => $result,
                ];
            } else {
                // Boleto
                $params = ['id' => $externalId];
                $result = $this->client->detailCharge($params);

                return [
                    'success' => true,
                    'status' => $this->mapStatus($result['data']['status'] ?? ''),
                    'paid_at' => $result['data']['paid_at'] ?? null,
                    'raw' => $result,
                ];
            }
        } catch (EfiException $e) {
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
        if ($this->client === null) {
            return [
                'success' => false,
                'message' => 'Cliente EFI Pay não inicializado',
            ];
        }

        try {
            // PIX refund
            if (strlen($externalId) > 20) {
                $params = [
                    'e2eId' => $externalId,
                    'id' => uniqid(),
                ];
                $body = [
                    'valor' => $amount ? $this->formatAmount($amount) : null,
                ];

                $result = $this->client->pixDevolution($params, $body);

                return [
                    'success' => true,
                    'refund_id' => $result['id'] ?? $externalId,
                    'raw' => $result,
                ];
            }

            // Boleto não suporta refund direto
            return [
                'success' => false,
                'message' => 'Estorno de boleto deve ser feito manualmente no painel EFI Pay',
            ];
        } catch (EfiException $e) {
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
                'message' => 'Cliente EFI Pay não inicializado',
            ];
        }

        try {
            $params = ['id' => $externalId];
            $result = $this->client->cancelCharge($params);

            return [
                'success' => true,
                'raw' => $result,
            ];
        } catch (EfiException $e) {
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
        // EFI Pay valida via mTLS, não há assinatura adicional
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function parseWebhookPayload(array $payload): array
    {
        // PIX webhook
        if (isset($payload['pix'])) {
            $pix = $payload['pix'][0] ?? [];
            return [
                'event' => 'pix_received',
                'external_id' => $pix['txid'] ?? '',
                'status' => 'paid',
                'paid_at' => $pix['horario'] ?? null,
                'raw' => $payload,
            ];
        }

        // Boleto webhook
        return [
            'event' => $payload['event'] ?? 'unknown',
            'external_id' => (string) ($payload['data']['charge_id'] ?? ''),
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
        return 'https://dev.efipay.com.br/';
    }

    /**
     * {@inheritdoc}
     */
    protected function mapStatus(string $gatewayStatus): string
    {
        return match (strtoupper($gatewayStatus)) {
            'CONCLUIDA', 'PAID', 'SETTLED' => 'paid',
            'ATIVA', 'NEW', 'WAITING', 'IDENTIFIED' => 'pending',
            'CANCELED', 'UNPAID', 'EXPIRED', 'REMOVIDA_PELO_USUARIO_RECEBEDOR' => 'cancelled',
            'REFUNDED' => 'refunded',
            default => 'pending',
        };
    }

    /**
     * {@inheritdoc}
     */
    protected function getBaseUrl(): string
    {
        return $this->sandbox
            ? 'https://pix-h.api.efipay.com.br'
            : 'https://pix.api.efipay.com.br';
    }

    /**
     * Constrói dados do cliente para boleto
     */
    private function buildCustomerData(array $data): array
    {
        $customer = [
            'name' => $data['customer_name'] ?? 'Cliente',
        ];

        if (!empty($data['customer_document'])) {
            $doc = $this->sanitizeDocument($data['customer_document']);
            $customer[strlen($doc) === 11 ? 'cpf' : 'cnpj'] = $doc;
        }

        if (!empty($data['customer_email'])) {
            $customer['email'] = $data['customer_email'];
        }

        if (!empty($data['customer_phone'])) {
            $customer['phone_number'] = $this->sanitizePhone($data['customer_phone']);
        }

        return $customer;
    }
}
