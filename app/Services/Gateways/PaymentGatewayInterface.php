<?php

namespace App\Services\Gateways;

/**
 * Interface para gateways de pagamento
 *
 * Define o contrato que todos os gateways de pagamento devem implementar.
 * Suporta múltiplos métodos de pagamento (PIX, Boleto, Cartão) e
 * diferentes países (BR, PY, INTL).
 */
interface PaymentGatewayInterface
{
    /**
     * Retorna o código único do gateway
     *
     * @return string Ex: 'asaas', 'stripe', 'square', 'cora', 'inter', 'bancard', 'pagopar'
     */
    public function getCode(): string;

    /**
     * Retorna o nome de exibição do gateway
     *
     * @return string Ex: 'Asaas', 'Stripe', 'Banco Inter'
     */
    public function getName(): string;

    /**
     * Retorna o país/região do gateway
     *
     * @return string 'BR' = Brasil, 'PY' = Paraguai, 'INTL' = Internacional
     */
    public function getCountry(): string;

    /**
     * Retorna os métodos de pagamento suportados
     *
     * @return array<string> Ex: ['pix', 'boleto', 'credit_card', 'debit_card']
     */
    public function getSupportedMethods(): array;

    /**
     * Retorna as moedas suportadas pelo gateway
     *
     * @return array<string> Códigos ISO 4217. Ex: ['BRL', 'USD', 'EUR']
     */
    public function getSupportedCurrencies(): array;

    /**
     * Retorna a estrutura de configuração necessária para o gateway
     *
     * @return array<string, array{type: string, required: bool, label: string, placeholder?: string, help?: string}>
     */
    public function getConfigSchema(): array;

    /**
     * Retorna a configuracao de certificado digital do gateway.
     *
     * @return array{required: bool, formats: array<string>}|null
     */
    public function getCertificateConfig(): ?array;

    /**
     * Valida se as credenciais estão corretas
     *
     * @param array<string, mixed> $credentials Credenciais do gateway
     * @return array{valid: bool, message: string}
     */
    public function validateCredentials(array $credentials): array;

    /**
     * Cria uma cobrança no gateway
     *
     * @param array{
     *     chave: string,
     *     id_financeiro?: int,
     *     id_gateway: int,
     *     customer_id?: string,
     *     customer_name?: string,
     *     customer_document?: string,
     *     customer_email?: string,
     *     customer_phone?: string,
     *     customer_address?: string,
     *     customer_address_number?: string,
     *     customer_neighborhood?: string,
     *     customer_city?: string,
     *     customer_state?: string,
     *     customer_postal_code?: string,
     *     beneficiary_name?: string,
     *     beneficiary_document?: string,
     *     value: float,
     *     billing_type: string,
     *     due_date: string,
     *     description?: string,
     *     external_reference?: string,
     *     installments?: int,
     *     card_token?: string
     * } $data Dados da cobrança
     * @return array{
     *     success: bool,
     *     message?: string,
     *     external_id?: string,
     *     status?: string,
     *     payment_url?: string,
     *     pix_code?: string,
     *     pix_qrcode?: string,
     *     barcode?: string,
     *     expires_at?: string,
     *     raw?: array
     * }
     */
    public function createCharge(array $data): array;

    /**
     * Consulta status de uma cobrança
     *
     * @param string $externalId ID externo da cobrança no gateway
     * @return array{
     *     success: bool,
     *     message?: string,
     *     status?: string,
     *     paid_at?: string,
     *     raw?: array
     * }
     */
    public function getChargeStatus(string $externalId): array;

    /**
     * Processa reembolso de uma cobrança
     *
     * @param string $externalId ID externo da cobrança no gateway
     * @param float|null $amount Valor do reembolso (null = total)
     * @return array{
     *     success: bool,
     *     message?: string,
     *     refund_id?: string,
     *     raw?: array
     * }
     */
    public function refund(string $externalId, ?float $amount = null): array;

    /**
     * Cancela uma cobrança pendente
     *
     * @param string $externalId ID externo da cobrança no gateway
     * @return array{
     *     success: bool,
     *     message?: string,
     *     raw?: array
     * }
     */
    public function cancel(string $externalId): array;

    /**
     * Valida assinatura de webhook
     *
     * @param array<string, mixed> $payload Payload do webhook
     * @param array<string, string> $headers Headers da requisição
     * @return bool
     */
    public function validateWebhookSignature(array $payload, array $headers): bool;

    /**
     * Processa dados do webhook e retorna em formato padronizado
     *
     * @param array<string, mixed> $payload Payload do webhook
     * @return array{
     *     event: string,
     *     external_id: string,
     *     status: string,
     *     paid_at?: string,
     *     raw: array
     * }
     */
    public function parseWebhookPayload(array $payload): array;

    /**
     * Retorna URL da documentação oficial do gateway
     *
     * @return string
     */
    public function getDocumentationUrl(): string;

    /**
     * Verifica se o gateway está em modo sandbox
     *
     * @return bool
     */
    public function isSandbox(): bool;

    /**
     * Verifica se o gateway suporta checkout transparente (formulário na própria tela)
     *
     * @return bool
     */
    public function supportsTransparentCheckout(): bool;

    /**
     * Verifica se o gateway suporta armazenamento de cartão (tokenização persistente)
     *
     * @return bool
     */
    public function supportsCardStorage(): bool;

    /**
     * Tokeniza um cartão de crédito para uso futuro
     *
     * @param array{
     *     holder: string,
     *     number: string,
     *     expiry_month: string,
     *     expiry_year: string,
     *     cvv: string,
     *     cpf?: string,
     *     customer_id?: string
     * } $cardData Dados do cartão
     * @return array{
     *     success: bool,
     *     message?: string,
     *     token?: string,
     *     brand?: string,
     *     last_digits?: string,
     *     raw?: array
     * }
     */
    public function tokenizeCard(array $cardData): array;
}
