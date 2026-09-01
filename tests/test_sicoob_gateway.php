#!/usr/bin/env php
<?php

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Services\Gateways\GatewayFactory;
use App\Services\Gateways\SicoobGateway;

$fail = static function (string $message): never {
    fwrite(STDERR, "[FAIL] {$message}\n");
    exit(1);
};

$credentials = [
    'client_id' => '00000000-0000-4000-8000-000000000000',
    'certificado_arquivo' => 'certificate.p12',
];

$newGateway = static function (array $gatewayCredentials) {
    return new class($gatewayCredentials, false) extends SicoobGateway {
        /** @var array<int, array<string, mixed>> */
        public array $responses = [];

        /** @var array<int, array<string, mixed>> */
        public array $requests = [];

        protected function request(
            string $method,
            string $endpoint,
            array $data = [],
            ?string $token = null,
            bool $isAuthRequest = false
        ): array {
            $this->requests[] = compact('method', 'endpoint', 'data', 'token', 'isAuthRequest');
            return array_shift($this->responses) ?? ['_http_code' => 500];
        }

        public function tokenFor(string $product, bool $forceRefresh = false): string
        {
            return $this->getAccessToken($product, $forceRefresh);
        }
    };
};

$info = GatewayFactory::getGatewayInfo('sicoob');
if (($info['methods'] ?? []) !== ['pix', 'boleto'] || empty($info['certificate_config']['required'])) {
    $fail('Sicoob deve anunciar Pix, Boleto e certificado obrigatório.');
}

$pixGateway = $newGateway($credentials);
$pixGateway->responses[] = ['_http_code' => 200, 'access_token' => 'pix-token', 'expires_in' => 300];
$pixResult = $pixGateway->validateCredentials($credentials + ['_pix_enabled' => true, '_boleto_enabled' => false]);
$pixScope = $pixGateway->requests[0]['data']['scope'] ?? '';
if (empty($pixResult['valid']) || $pixScope !== 'cob.read cob.write pix.read pix.write') {
    $fail('Validação Pix não solicitou exclusivamente os escopos Pix.');
}
if (str_contains($pixScope, 'boleto') || str_contains($pixScope, 'webhook')) {
    $fail('Validação Pix enviou escopos de Boleto ou Webhook.');
}

$boletoGateway = $newGateway($credentials);
$boletoGateway->responses[] = ['_http_code' => 200, 'access_token' => 'boleto-token', 'expires_in' => 300];
$boletoResult = $boletoGateway->validateCredentials($credentials + ['_pix_enabled' => false, '_boleto_enabled' => true]);
$boletoScope = $boletoGateway->requests[0]['data']['scope'] ?? '';
if (empty($boletoResult['valid']) || $boletoScope !== 'boletos_inclusao boletos_consulta boletos_alteracao') {
    $fail('Validação de Boleto não solicitou exclusivamente os escopos de cobrança bancária.');
}
if (str_contains($boletoScope, 'pix.') || str_contains($boletoScope, 'webhook')) {
    $fail('Validação de Boleto enviou escopos Pix ou Webhook.');
}

$bothGateway = $newGateway($credentials);
$bothGateway->responses = [
    ['_http_code' => 200, 'access_token' => 'pix-token', 'expires_in' => 300],
    ['_http_code' => 200, 'access_token' => 'boleto-token', 'expires_in' => 300],
];
$bothResult = $bothGateway->validateCredentials($credentials + ['_pix_enabled' => true, '_boleto_enabled' => true]);
if (empty($bothResult['valid']) || count($bothGateway->requests) !== 2) {
    $fail('Validação conjunta deve autenticar Pix e Boleto separadamente.');
}

$cacheGateway = $newGateway($credentials);
$cacheGateway->responses = [
    ['_http_code' => 200, 'access_token' => 'pix-token', 'expires_in' => 300],
    ['_http_code' => 200, 'access_token' => 'boleto-token', 'expires_in' => 300],
];
if (
    $cacheGateway->tokenFor('pix') !== 'pix-token'
    || $cacheGateway->tokenFor('pix') !== 'pix-token'
    || $cacheGateway->tokenFor('boleto') !== 'boleto-token'
    || count($cacheGateway->requests) !== 2
) {
    $fail('Cache de token deve ser reutilizado apenas dentro do mesmo produto.');
}

$scopeErrorGateway = $newGateway($credentials);
$scopeErrorGateway->responses[] = [
    '_http_code' => 400,
    'error' => 'invalid_scope',
    'error_description' => 'internal provider detail containing secret-value',
];
$scopeError = $scopeErrorGateway->validateCredentials($credentials + ['_pix_enabled' => true]);
if (
    !empty($scopeError['valid'])
    || !str_contains((string) ($scopeError['message'] ?? ''), 'não possui autorização')
    || str_contains((string) ($scopeError['message'] ?? ''), 'secret-value')
) {
    $fail('Erro invalid_scope deve ser específico e não pode expor a resposta bruta.');
}

$clientErrorGateway = $newGateway($credentials);
$clientErrorGateway->responses[] = ['_http_code' => 401, 'error' => 'invalid_client'];
$clientError = $clientErrorGateway->validateCredentials($credentials + ['_pix_enabled' => true]);
if (!str_contains((string) ($clientError['message'] ?? ''), 'Client ID, certificado ou situação')) {
    $fail('Erro invalid_client deve orientar a conferência do aplicativo e certificado.');
}

$missingCertificateGateway = $newGateway($credentials);
$missingCertificate = $missingCertificateGateway->validateCredentials(['client_id' => $credentials['client_id']]);
if (!empty($missingCertificate['valid']) || !str_contains((string) ($missingCertificate['message'] ?? ''), 'certificado')) {
    $fail('Certificado ausente deve impedir a validação.');
}

echo "[OK] Sicoob usa escopos e cache separados por produto e diagnostica OAuth sem expor credenciais.\n";
