#!/usr/bin/env php
<?php

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Services\Gateways\GatewayFactory;
use App\Services\Gateways\SantanderGateway;

$fail = static function (string $message): never {
    fwrite(STDERR, "[FAIL] {$message}\n");
    exit(1);
};

$info = GatewayFactory::getGatewayInfo('santander');
if (($info['name'] ?? null) !== 'Banco Santander') {
    $fail('Santander não foi registrado na GatewayFactory.');
}
if (($info['methods'] ?? []) !== ['pix', 'boleto'] || empty($info['certificate_config']['required'])) {
    $fail('Capacidades do Santander estão incorretas.');
}

$gateway = new class(['workspace_id' => 'workspace', 'covenant_code' => '1234567', 'pix_key' => 'pix@example.com', 'pix_key_type' => 'EMAIL'], false) extends SantanderGateway {
    /** @var array<string, mixed> */
    public array $response = [];
    /** @var array<string, mixed> */
    public array $lastRequest = [];

    protected function request(string $product, string $method, string $endpoint, array $data = [], bool $authentication = false): array
    {
        $this->lastRequest = compact('product', 'method', 'endpoint', 'data', 'authentication');
        return $this->response;
    }

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
        return 1;
    }
};

$pix = $gateway->parseWebhookPayload([
    'pix' => [['txid' => 'abc123', 'horario' => '2026-08-06T10:00:00Z']],
]);
if (($pix['external_id'] ?? null) !== 'pix_abc123' || ($pix['status'] ?? null) !== 'paid') {
    $fail('Webhook Pix não foi normalizado corretamente.');
}

$boleto = $gateway->parseWebhookPayload(['bankNumber' => '123456789', 'function' => 'PAGAMENTO']);
if (($boleto['external_id'] ?? null) !== 'bol_123456789' || ($boleto['status'] ?? null) !== 'paid') {
    $fail('Webhook de boleto não foi normalizado corretamente.');
}

$gateway->response = ['_http_code' => 200, 'status' => 'CONCLUIDA', 'pix' => [['horario' => '2026-08-06T10:00:00Z']]];
$status = $gateway->getChargeStatus('pix_abc123');
if (empty($status['success']) || ($status['status'] ?? null) !== 'paid') {
    $fail('Consulta autenticada de status Pix não foi mapeada como paga.');
}
if (!$gateway->validateWebhookSignature(['pix' => [['txid' => 'abc123']]], [])) {
    $fail('Webhook confirmado pela consulta bancária deveria ser aceito.');
}

$gateway->response = ['_http_code' => 200, 'status' => 'ATIVA'];
if ($gateway->validateWebhookSignature(['pix' => [['txid' => 'abc123']]], [])) {
    $fail('Payload pago sem confirmação bancária não pode ser aceito.');
}

$gateway->response = ['_http_code' => 201, 'bankNumber' => '123456789', 'digitableLine' => '03399'];
$charge = $gateway->createCharge([
    'chave' => '1111111111111', 'value' => 99.90, 'billing_type' => 'boleto', 'due_date' => '2026-08-10',
    'customer_name' => 'Cliente Teste', 'customer_document' => '12345678901', 'customer_address' => 'Rua Teste',
    'customer_address_number' => '10', 'customer_neighborhood' => 'Centro', 'customer_city' => 'São Paulo',
    'customer_state' => 'SP', 'customer_postal_code' => '01001000',
]);
$payload = $gateway->lastRequest['data'] ?? [];
if (empty($charge['success']) || empty($payload['bankNumber']) || ($payload['payer']['zipCode'] ?? null) !== '01001-000') {
    $fail('Payload do boleto não contém Nosso Número e endereço no formato oficial.');
}

echo "[OK] Santander registrado, webhooks normalizados e pagamento confirmado por consulta.\n";
