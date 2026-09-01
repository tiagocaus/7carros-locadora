#!/usr/bin/env php
<?php

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Services\Gateways\EfipayGateway;
use App\Services\Gateways\InterGateway;
use App\Services\Gateways\SantanderGateway;
use Efi\EfiPay;

$fail = static function (string $message): never {
    fwrite(STDERR, "[FAIL] {$message}\n");
    exit(1);
};

$efiRecorder = (object) ['calls' => [], 'options' => []];
$efi = new class([], false, null, $efiRecorder) extends EfipayGateway {
    public function __construct(array $credentials, bool $sandbox, ?int $gatewayId, private object $recorder)
    {
        parent::__construct($credentials, $sandbox, $gatewayId);
    }

    protected function createClient(array $options): EfiPay
    {
        $this->recorder->options[] = $options;
        return new class($this->recorder) extends EfiPay {
            public function __construct(private object $recorder) {}

            public function __call($name, $arguments)
            {
                $this->recorder->calls[] = ['method' => $name, 'arguments' => $arguments];
                return [];
            }
        };
    }
};

$efiResult = $efi->validateCredentials([
    'client_id' => 'client',
    'client_secret' => 'secret',
    'pix_key' => 'pix@example.com',
    'certificate_path' => __FILE__,
    '_pix_enabled' => true,
    '_boleto_enabled' => true,
]);
if (empty($efiResult['valid'])) {
    $fail('Efí deveria validar Pix e Boleto com o cliente simulado.');
}
$efiMethods = array_column($efiRecorder->calls, 'method');
if ($efiMethods !== ['pixListCharges', 'listCharges']) {
    $fail('Efí não validou separadamente a API Pix e a API Cobranças.');
}
if (empty($efiRecorder->options[0]['certificate'])) {
    $fail('Efí não encaminhou o certificado para a validação Pix.');
}

$efiPixWithoutCertificate = $efi->validateCredentials([
    'client_id' => 'client',
    'client_secret' => 'secret',
    'pix_key' => 'pix@example.com',
    '_pix_enabled' => true,
]);
if (!empty($efiPixWithoutCertificate['valid'])) {
    $fail('Efí Pix não pode validar sem certificado.');
}

$santander = new class([], false) extends SantanderGateway {
    /** @var array<int, string> */
    public array $authenticatedProducts = [];

    protected function request(string $product, string $method, string $endpoint, array $data = [], bool $authentication = false): array
    {
        if ($authentication) {
            $this->authenticatedProducts[] = $product;
            return ['access_token' => 'token-' . $product, 'expires_in' => 300, '_http_code' => 200];
        }
        return ['_http_code' => 200];
    }
};

$santanderPix = $santander->validateCredentials([
    'client_id' => 'client',
    'client_secret' => 'secret',
    'certificado_arquivo' => 'dummy.p12',
    'pix_key_type' => 'EMAIL',
    'pix_key' => 'pix@example.com',
    '_pix_enabled' => true,
]);
if (empty($santanderPix['valid']) || $santander->authenticatedProducts !== ['pix']) {
    $fail('Santander Pix-only tentou autenticar produto incorreto.');
}

$santander->authenticatedProducts = [];
$santanderBoleto = $santander->validateCredentials([
    'client_id' => 'client',
    'client_secret' => 'secret',
    'certificado_arquivo' => 'dummy.p12',
    'workspace_id' => 'workspace',
    'covenant_code' => '1234567',
    '_boleto_enabled' => true,
]);
if (empty($santanderBoleto['valid']) || $santander->authenticatedProducts !== ['billing']) {
    $fail('Santander boleto-only tentou autenticar produto incorreto.');
}
$santanderSchema = $santander->getConfigSchema();
foreach (['pix_key_type', 'pix_key', 'workspace_id', 'covenant_code'] as $conditionalField) {
    if (!empty($santanderSchema[$conditionalField]['required'])) {
        $fail("Campo Santander {$conditionalField} deve ser validado conforme o produto ativo.");
    }
}

$inter = new class([
    'client_id' => 'client',
    'client_secret' => 'secret',
    'certificate_path' => __FILE__,
    'conta_corrente' => '12345678',
    'e2eid' => 'E123',
], false) extends InterGateway {
    /** @var array<int, string> */
    public array $requestedScopes = [];

    protected function makeApiRequest(
        string $method,
        string $endpoint,
        array $data = [],
        ?string $token = null,
        bool $isAuthRequest = false
    ): array {
        if ($isAuthRequest) {
            $scope = (string) ($data['scope'] ?? '');
            $this->requestedScopes[] = $scope;
            return ['access_token' => 'token-' . str_replace([' ', '.'], '-', $scope), 'expires_in' => 3600, 'scope' => $scope];
        }
        if (str_contains($endpoint, '/devolucao/')) {
            return ['_http_code' => 200, 'id' => 'refund-1'];
        }
        return ['_http_code' => 200, 'status' => 'ATIVA'];
    }

    public function tokenFor(string $profile): ?string
    {
        return $this->getAccessToken($profile);
    }
};

$inter->tokenFor('cobranca');
$inter->tokenFor('cobranca');
if ($inter->requestedScopes !== ['boleto-cobranca.write boleto-cobranca.read']) {
    $fail('Inter não reutilizou o token da Cobrança V3.');
}

$inter->getChargeStatus('abc123');
$inter->refund('refund-id', 10.00);
if ($inter->requestedScopes !== [
    'boleto-cobranca.write boleto-cobranca.read',
    'cob.read',
    'pix.write',
]) {
    $fail('Inter misturou os escopos da Cobrança V3 com os escopos da API Pix.');
}

echo "[OK] Autenticação por produto validada para Efí, Santander e Inter.\n";
