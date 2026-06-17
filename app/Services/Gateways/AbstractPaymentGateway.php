<?php

namespace App\Services\Gateways;

use App\Models\FinanceiroTransacao;

/**
 * Classe abstrata base para gateways de pagamento
 *
 * Fornece métodos auxiliares comuns para todos os gateways,
 * incluindo logging de transações, formatação de valores e requisições HTTP.
 */
abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    /** @var array<string, mixed> Credenciais do gateway */
    protected array $credentials;

    /** @var bool Se está em modo sandbox */
    protected bool $sandbox;

    /** @var int|null ID do gateway no banco de dados */
    protected ?int $gatewayId;

    /** @var FinanceiroTransacao|null Model de transações (lazy loaded) */
    protected ?FinanceiroTransacao $transacaoModel = null;

    /**
     * @param array<string, mixed> $credentials Credenciais do gateway
     * @param bool $sandbox Se está em modo sandbox
     * @param int|null $gatewayId ID do gateway no banco
     */
    public function __construct(array $credentials, bool $sandbox = false, ?int $gatewayId = null)
    {
        $this->credentials = $credentials;
        $this->sandbox = $sandbox;
        $this->gatewayId = $gatewayId;
    }

    /**
     * {@inheritdoc}
     */
    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    /**
     * Retorna o model de transações (lazy loading)
     */
    protected function getTransacaoModel(): FinanceiroTransacao
    {
        if ($this->transacaoModel === null) {
            $this->transacaoModel = new FinanceiroTransacao();
        }
        return $this->transacaoModel;
    }

    /**
     * Registra transação no banco de dados
     *
     * @param string $chave Chave do tenant
     * @param int|null $idFinanceiro ID do lançamento financeiro
     * @param string $type Tipo: charge, refund, webhook, callback
     * @param string $externalId ID externo no gateway
     * @param string $status Status da transação
     * @param float $amount Valor da transação
     * @param string|null $paymentMethod Método de pagamento
     * @param array<string, mixed> $payload Dados completos
     * @param string|null $paymentUrl URL de pagamento
     * @param string|null $pixCode Código PIX
     * @param string|null $barcode Código de barras
     * @param string|null $expiresAt Data de expiração
     * @return int ID da transação criada
     */
    protected function logTransaction(
        string $chave,
        ?int $idFinanceiro,
        string $type,
        string $externalId,
        string $status,
        float $amount,
        ?string $paymentMethod = null,
        array $payload = [],
        ?string $paymentUrl = null,
        ?string $pixCode = null,
        ?string $barcode = null,
        ?string $expiresAt = null
    ): int {
        return $this->getTransacaoModel()->criar([
            'chave' => $chave,
            'id_financeiro' => $idFinanceiro,
            'id_gateway' => $this->gatewayId,
            'gateway' => $this->getCode(),
            'external_id' => $externalId,
            'type' => $type,
            'payment_method' => $paymentMethod,
            'status' => $status,
            'amount' => $amount,
            'payment_url' => $paymentUrl,
            'pix_code' => $pixCode,
            'barcode' => $barcode,
            'expires_at' => $expiresAt,
            'payload' => json_encode($payload),
        ]);
    }

    /**
     * Atualiza status de uma transação existente
     *
     * @param string $externalId ID externo no gateway
     * @param string $status Novo status
     * @param string|null $paidAt Data de pagamento
     * @param string|null $refundedAt Data de reembolso
     * @return int Número de registros atualizados
     */
    protected function updateTransactionStatus(
        string $externalId,
        string $status,
        ?string $paidAt = null,
        ?string $refundedAt = null
    ): int {
        return $this->getTransacaoModel()->atualizarPorExternalId(
            $externalId,
            $status,
            $paidAt,
            $refundedAt
        );
    }

    /**
     * Formata valor para centavos (inteiro)
     *
     * @param float $amount Valor em reais/dólares
     * @return int Valor em centavos
     */
    protected function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Normaliza a data de vencimento enviada ao gateway.
     *
     * A fatura mantém seu vencimento real quando ainda está em aberto; se já
     * venceu ou a data veio inválida, a cobrança externa vence hoje.
     */
    protected function resolveDueDate(?string $dueDate = null): string
    {
        $today = date('Y-m-d');

        if (empty($dueDate)) {
            return $today;
        }

        $timestamp = strtotime($dueDate);
        if ($timestamp === false) {
            return $today;
        }

        $normalized = date('Y-m-d', $timestamp);
        return $normalized < $today ? $today : $normalized;
    }

    /**
     * Converte centavos para valor decimal
     *
     * @param int $cents Valor em centavos
     * @return float Valor decimal
     */
    protected function fromCents(int $cents): float
    {
        return $cents / 100;
    }

    /**
     * Formata valor para string com 2 casas decimais
     *
     * @param float $amount Valor
     * @return string Ex: '199.99'
     */
    protected function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * Gera TxId único para PIX (26-35 caracteres alfanuméricos)
     *
     * @param string $prefix Prefixo opcional
     * @return string
     */
    protected function generateTxId(string $prefix = ''): string
    {
        $random = bin2hex(random_bytes(16)); // 32 chars
        $txId = $prefix . $random;
        return substr($txId, 0, 35); // Max 35 chars
    }

    /**
     * Gera código único para referência externa
     *
     * @return string 32 caracteres hexadecimais
     */
    protected function generateExternalReference(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Valida se todos os campos obrigatórios estão presentes
     *
     * @param array<string, mixed> $data Dados a validar
     * @param array<string> $required Lista de campos obrigatórios
     * @throws \InvalidArgumentException Se campo obrigatório estiver ausente
     */
    protected function validateRequiredFields(array $data, array $required): void
    {
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                throw new \InvalidArgumentException("Campo obrigatório ausente: {$field}");
            }
        }
    }

    /**
     * Faz requisição HTTP genérica
     *
     * @param string $method Método HTTP (GET, POST, PUT, DELETE, PATCH)
     * @param string $url URL da requisição
     * @param array<string, mixed> $data Dados para enviar (body)
     * @param array<string, string> $headers Headers adicionais
     * @param int $timeout Timeout em segundos
     * @return array{success: bool, http_code: int, body: array, error: string}
     */
    protected function httpRequest(
        string $method,
        string $url,
        array $data = [],
        array $headers = [],
        int $timeout = 30
    ): array {
        $ch = curl_init();

        $defaultHeaders = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $allHeaders = array_merge($defaultHeaders, $headers);

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        switch (strtoupper($method)) {
            case 'POST':
                $curlOptions[CURLOPT_POST] = true;
                if (!empty($data)) {
                    $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;

            case 'PUT':
                $curlOptions[CURLOPT_CUSTOMREQUEST] = 'PUT';
                if (!empty($data)) {
                    $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;

            case 'PATCH':
                $curlOptions[CURLOPT_CUSTOMREQUEST] = 'PATCH';
                if (!empty($data)) {
                    $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;

            case 'DELETE':
                $curlOptions[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                if (!empty($data)) {
                    $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;

            case 'GET':
            default:
                if (!empty($data)) {
                    $url .= '?' . http_build_query($data);
                    $curlOptions[CURLOPT_URL] = $url;
                }
                break;
        }

        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $body = [];
        if ($response !== false && $response !== '') {
            $decoded = json_decode($response, true);
            $body = is_array($decoded) ? $decoded : ['raw_response' => $response];
        }

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'body' => $body,
            'error' => $error,
        ];
    }

    /**
     * Faz requisição HTTP com certificado mTLS
     *
     * @param string $method Método HTTP
     * @param string $url URL da requisição
     * @param array<string, mixed> $data Dados para enviar
     * @param array<string, string> $headers Headers adicionais
     * @param string $certPath Caminho do certificado
     * @param string $keyPath Caminho da chave privada
     * @param string|null $keyPassword Senha da chave (opcional)
     * @param int $timeout Timeout em segundos
     * @return array{success: bool, http_code: int, body: array, error: string}
     */
    protected function httpRequestWithCert(
        string $method,
        string $url,
        array $data = [],
        array $headers = [],
        string $certPath = '',
        string $keyPath = '',
        ?string $keyPassword = null,
        int $timeout = 30
    ): array {
        $ch = curl_init();

        $defaultHeaders = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $allHeaders = array_merge($defaultHeaders, $headers);

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        // Configurar certificados
        if (!empty($certPath) && file_exists($certPath)) {
            $curlOptions[CURLOPT_SSLCERT] = $certPath;
        }

        if (!empty($keyPath) && file_exists($keyPath)) {
            $curlOptions[CURLOPT_SSLKEY] = $keyPath;
            if ($keyPassword !== null) {
                $curlOptions[CURLOPT_SSLKEYPASSWD] = $keyPassword;
            }
        }

        switch (strtoupper($method)) {
            case 'POST':
                $curlOptions[CURLOPT_POST] = true;
                if (!empty($data)) {
                    $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;

            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                $curlOptions[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
                if (!empty($data)) {
                    $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;

            default:
                if (!empty($data)) {
                    $url .= '?' . http_build_query($data);
                    $curlOptions[CURLOPT_URL] = $url;
                }
                break;
        }

        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $body = [];
        if ($response !== false && $response !== '') {
            $decoded = json_decode($response, true);
            $body = is_array($decoded) ? $decoded : ['raw_response' => $response];
        }

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'body' => $body,
            'error' => $error,
        ];
    }

    /**
     * Valida CPF brasileiro
     *
     * @param string $cpf CPF com ou sem formatação
     * @return bool
     */
    protected function validateCPF(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf);

        if (strlen($cpf) !== 11 || preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += (int) $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ((int) $cpf[$c] !== $d) {
                return false;
            }
        }

        return true;
    }

    /**
     * Valida CNPJ brasileiro
     *
     * @param string $cnpj CNPJ com ou sem formatação
     * @return bool
     */
    protected function validateCNPJ(string $cnpj): bool
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        if (strlen($cnpj) !== 14 || preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }

        $t = 12;
        $multipliers1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $multipliers2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        for ($i = 0; $i < 2; $i++) {
            $multipliers = $i === 0 ? $multipliers1 : $multipliers2;
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += (int) $cnpj[$c] * $multipliers[$c];
            }
            $d = $d % 11;
            $d = $d < 2 ? 0 : 11 - $d;
            if ((int) $cnpj[$t] !== $d) {
                return false;
            }
            $t++;
        }

        return true;
    }

    /**
     * Remove formatação de documento (CPF/CNPJ/RUC)
     *
     * @param string $document Documento formatado
     * @return string Apenas números
     */
    protected function sanitizeDocument(string $document): string
    {
        return preg_replace('/\D/', '', $document);
    }

    /**
     * Remove formatação de telefone
     *
     * @param string $phone Telefone formatado
     * @return string Apenas números
     */
    protected function sanitizePhone(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }

    /**
     * Mapeia status do gateway para status interno padronizado
     *
     * @param string $gatewayStatus Status retornado pelo gateway
     * @return string Status interno: pending, processing, paid, failed, cancelled, refunded
     */
    abstract protected function mapStatus(string $gatewayStatus): string;

    /**
     * Retorna URL base da API (sandbox ou produção)
     *
     * @return string
     */
    abstract protected function getBaseUrl(): string;

    /**
     * {@inheritdoc}
     *
     * Por padrão, gateways brasileiros suportam apenas BRL.
     * Sobrescreva este método nos gateways internacionais.
     */
    public function getSupportedCurrencies(): array
    {
        return ['BRL'];
    }

    /**
     * {@inheritdoc}
     *
     * Por padrão, gateways não suportam checkout transparente.
     * Sobrescreva este método nos gateways que suportam.
     */
    public function supportsTransparentCheckout(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * Por padrão, gateways não suportam armazenamento de cartão.
     * Sobrescreva este método nos gateways que suportam.
     */
    public function supportsCardStorage(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * Implementação padrão retorna erro para gateways que não suportam tokenização.
     * Sobrescreva este método nos gateways que suportam.
     */
    public function tokenizeCard(array $cardData): array
    {
        return [
            'success' => false,
            'message' => 'Este gateway não suporta tokenização de cartão',
        ];
    }

    /**
     * Verifica se o gateway suporta authorization holds (pre-autorizacao)
     *
     * Por padrão, gateways não suportam. Sobrescreva nos que suportam
     * (Stripe, Square) e implemente AuthorizationHoldInterface.
     */
    public function supportsAuthorizationHold(): bool
    {
        return false;
    }
}
