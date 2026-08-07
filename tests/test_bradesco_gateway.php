#!/usr/bin/env php
<?php

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Services\Gateways\BradescoGateway;
use App\Services\Gateways\GatewayFactory;

$fail = static function (string $message): never {
    fwrite(STDERR, "[FAIL] {$message}\n");
    exit(1);
};

$info = GatewayFactory::getGatewayInfo('bradesco');
if (($info['methods'] ?? []) !== ['pix', 'boleto']) {
    $fail('Bradesco deve anunciar Pix e Boleto.');
}
if (empty($info['certificate_config']['required']) || !isset($info['config_schema']['pix_key'], $info['config_schema']['boleto_negotiation'])) {
    $fail('Configuração Pix/Boleto/certificado do Bradesco está incompleta.');
}
if (isset($info['config_schema']['merchant_id'])) {
    $fail('Campo legado merchant_id não deve ser solicitado pela API Pix Bradesco.');
}

$gateway = new class([
    'client_id' => 'client',
    'client_secret' => 'secret',
    'pix_key' => 'pix@example.com',
    'boleto_client_id' => 'boleto-client',
    'boleto_client_secret' => 'boleto-secret',
    'boleto_beneficiary_document' => '36590712000155',
    'boleto_product' => '09',
    'boleto_negotiation' => '123400000001234567',
], false) extends BradescoGateway {
    /** @var array<int, array<string, mixed>> */
    public array $responses = [];
    /** @var array<int, array<string, mixed>> */
    public array $requests = [];
    /** @var array<int, array<string, mixed>> */
    public array $boletoResponses = [];
    /** @var array<int, array<string, mixed>> */
    public array $boletoRequests = [];

    protected function request(string $method, string $endpoint, array $data = [], bool $authentication = false): array
    {
        $this->requests[] = compact('method', 'endpoint', 'data', 'authentication');
        return array_shift($this->responses) ?? ['_http_code' => 500];
    }

    protected function requestBoleto(string $method, string $endpoint, array $data = [], bool $authentication = false): array
    {
        $this->boletoRequests[] = compact('method', 'endpoint', 'data', 'authentication');
        return array_shift($this->boletoResponses) ?? ['_http_code' => 500];
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
        return 99;
    }
};

$gateway->responses[] = [
    '_http_code' => 201,
    'txid' => 'tx123',
    'status' => 'ATIVA',
    'pixCopiaECola' => '00020101021226880014br.gov.bcb.pix',
];
$charge = $gateway->createCharge([
    'chave' => '1111111111111',
    'id_financeiro' => 10,
    'value' => 99.90,
    'billing_type' => 'pix',
    'due_date' => '2099-08-10',
    'description' => 'Locação 123',
    'customer_name' => 'Cliente Teste',
    'customer_document' => '12.345.678/0001-99',
]);
$request = $gateway->requests[0] ?? [];
$payload = $request['data'] ?? [];
if (empty($charge['success']) || ($charge['external_id'] ?? null) !== 'pix_tx123') {
    $fail('Cobrança Pix não foi criada ou normalizada corretamente.');
}
if (($request['method'] ?? null) !== 'PUT' || !str_starts_with((string) ($request['endpoint'] ?? ''), '/cobv/')) {
    $fail('Cobrança Pix deve usar PUT /cobv/{txid}.');
}
if (($payload['valor']['original'] ?? null) !== '99.90' || ($payload['chave'] ?? null) !== 'pix@example.com') {
    $fail('Payload Pix não contém valor e chave recebedora no formato esperado.');
}
if (($payload['devedor']['cnpj'] ?? null) !== '12345678000199') {
    $fail('CNPJ do devedor não foi normalizado no payload Pix.');
}

$gateway->responses[] = ['_http_code' => 201, 'txid' => 'semqr', 'status' => 'ATIVA'];
$withoutQr = $gateway->createCharge(['value' => 10, 'billing_type' => 'pix']);
if (!empty($withoutQr['success']) || !str_contains((string) ($withoutQr['message'] ?? ''), 'Copia e Cola')) {
    $fail('Cobrança sem Pix Copia e Cola não pode ser persistida como utilizável.');
}

$gateway->boletoResponses[] = [
    '_http_code' => 201,
    'ctitloCobrCdent' => '12345678901',
    'linhaDig10' => '23790123456789012345678901234567890120000001000',
    'codStatus10' => 'EM ABERTO',
];
$boleto = $gateway->createCharge([
    'chave' => '1111111111111',
    'id_financeiro' => 11,
    'value' => 10,
    'billing_type' => 'boleto',
    'external_reference' => 'link_123',
    'due_date' => '2099-08-10',
    'customer_name' => 'Cliente Teste',
    'customer_document' => '123.456.789-09',
    'customer_address' => 'Rua Teste',
    'customer_address_number' => '100',
    'customer_neighborhood' => 'Centro',
    'customer_city' => 'Sao Paulo',
    'customer_state' => 'SP',
    'customer_postal_code' => '01001000',
]);
$boletoRequest = $gateway->boletoRequests[0] ?? [];
if (empty($boleto['success']) || ($boleto['external_id'] ?? null) !== 'bol_12345678901') {
    $fail('Boleto Bradesco não foi registrado ou normalizado corretamente.');
}
if (($boletoRequest['endpoint'] ?? null) !== '/boleto/cobranca-registro/v1/cobranca') {
    $fail('Registro de boleto não usou o endpoint da API Cobrança.');
}
if (($boletoRequest['data']['vlNominalTitulo'] ?? null) !== 1000 || ($boletoRequest['data']['nuCpfcnpjPagador'] ?? null) !== '00012345678909') {
    $fail('Payload do boleto não normalizou valor e CPF conforme o layout Bradesco.');
}

$gateway->boletoResponses[] = [
    '_http_code' => 200,
    'content' => [[
        'nossoNumero' => '12345678901',
        'situacao' => 'Liquidado',
        'dataHoraSituacao' => '2026-08-07T12:00:00Z',
    ]],
];
$boletoStatus = $gateway->getChargeStatus('bol_12345678901');
if (empty($boletoStatus['success']) || ($boletoStatus['status'] ?? null) !== 'paid') {
    $fail('Consulta do boleto liquidado não foi mapeada como paga.');
}

$gateway->boletoResponses[] = ['_http_code' => 200, 'status' => 'BAIXADO'];
$boletoCancel = $gateway->cancel('bol_12345678901');
$lastBoletoRequest = $gateway->boletoRequests[array_key_last($gateway->boletoRequests)] ?? [];
if (empty($boletoCancel['success']) || ($lastBoletoRequest['endpoint'] ?? null) !== '/boleto/cobranca-registro/v1/titulo-baixar') {
    $fail('Baixa do boleto não usou o endpoint oficial da API Cobrança.');
}

$gateway->responses[] = [
    '_http_code' => 200,
    'status' => 'CONCLUIDA',
    'pix' => [['horario' => '2026-08-07T10:00:00Z']],
];
$status = $gateway->getChargeStatus('pix_tx123');
if (empty($status['success']) || ($status['status'] ?? null) !== 'paid') {
    $fail('Status CONCLUIDA do Pix não foi mapeado como pago.');
}

$gateway->responses[] = ['_http_code' => 200, 'status' => 'CONCLUIDA'];
if (!$gateway->validateWebhookSignature(['pix' => [['txid' => 'tx123']]], [])) {
    $fail('Webhook Pix confirmado pela consulta bancária deveria ser aceito.');
}
$gateway->responses[] = ['_http_code' => 200, 'status' => 'ATIVA'];
if ($gateway->validateWebhookSignature(['pix' => [['txid' => 'tx123']]], [])) {
    $fail('Webhook Pix não confirmado pela consulta bancária deveria ser rejeitado.');
}

$gateway->responses[] = ['_http_code' => 200, 'status' => 'REMOVIDA_PELO_USUARIO_RECEBEDOR'];
$cancel = $gateway->cancel('pix_tx123');
$lastRequest = $gateway->requests[array_key_last($gateway->requests)] ?? [];
if (empty($cancel['success']) || ($lastRequest['method'] ?? null) !== 'PATCH') {
    $fail('Cancelamento Pix não usou PATCH na cobrança com vencimento.');
}

$gateway->responses[] = [
    '_http_code' => 200,
    'pix' => [['endToEndId' => 'E123', 'valor' => '99.90']],
];
$gateway->responses[] = ['_http_code' => 201, 'id' => 'dev123'];
$refund = $gateway->refund('pix_tx123');
$lastRequest = $gateway->requests[array_key_last($gateway->requests)] ?? [];
if (empty($refund['success']) || !str_contains((string) ($lastRequest['endpoint'] ?? ''), '/pix/E123/devolucao/')) {
    $fail('Devolução Pix não usou o endToEndId liquidado.');
}

echo "[OK] Bradesco Pix e Boleto, capacidades e confirmação de webhook validados.\n";
