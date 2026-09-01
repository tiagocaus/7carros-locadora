#!/usr/bin/env php
<?php

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/vendor/autoload.php';

use App\Services\Gateways\GatewayFactory;
use App\Controllers\GatewaysPagamentoController;

$fail = static function (string $message): never {
    fwrite(STDERR, "[FAIL] {$message}\n");
    exit(1);
};

$expectedGateways = ['cora', 'efipay', 'inter', 'sicoob', 'bradesco', 'itau', 'santander'];
foreach ($expectedGateways as $gatewayCode) {
    $config = GatewayFactory::getGatewayInfo($gatewayCode)['certificate_config'] ?? null;
    if (!is_array($config) || empty($config['formats']) || empty($config['guidance'])) {
        $fail("Configuração de certificado incompleta para {$gatewayCode}.");
    }
}

$efipayConfig = GatewayFactory::getGatewayInfo('efipay')['certificate_config'] ?? [];
if (($efipayConfig['required_methods'] ?? []) !== ['pix'] || !empty($efipayConfig['required'])) {
    $fail('Efí deve exigir certificado somente para Pix.');
}

$itauConfig = GatewayFactory::getGatewayInfo('itau')['certificate_config'] ?? [];
if (($itauConfig['required_environments'] ?? []) !== ['production']) {
    $fail('Itaú deve exigir certificado somente em produção.');
}

$controller = new GatewaysPagamentoController();
$requiresCertificate = new ReflectionMethod($controller, 'requiresCertificate');
$cases = [
    ['efipay', ['ambiente' => 'production', 'pix_enabled' => 1, 'boleto_enabled' => 0], true],
    ['efipay', ['ambiente' => 'production', 'pix_enabled' => 0, 'boleto_enabled' => 1], false],
    ['itau', ['ambiente' => 'production', 'pix_enabled' => 1], true],
    ['itau', ['ambiente' => 'sandbox', 'pix_enabled' => 1], false],
    ['sicoob', ['ambiente' => 'production', 'pix_enabled' => 0, 'boleto_enabled' => 0], true],
];
foreach ($cases as [$gatewayCode, $configuration, $expected]) {
    $actual = $requiresCertificate->invoke($controller, $gatewayCode, $configuration);
    if ($actual !== $expected) {
        $fail("Obrigatoriedade contextual incorreta para {$gatewayCode}.");
    }
}

$certificateInfo = new ReflectionMethod($controller, 'getGatewayCertificateInfo');
$pemInfo = $certificateInfo->invoke($controller, [
    'certificado_arquivo' => 'certificado.pem',
    'certificado_formato' => 'pem',
]);
if (($pemInfo['modo'] ?? null) !== 'pem_pair') {
    $fail('Configuração antiga PEM não foi mapeada para o modo de arquivos separados.');
}

$pkcs12Info = $certificateInfo->invoke($controller, [
    'certificado_arquivo' => 'certificado.p12',
    'certificado_formato' => 'pkcs12',
    'certificado_modo' => 'pkcs12',
]);
if (($pkcs12Info['modo'] ?? null) !== 'pkcs12') {
    $fail('Modo explícito PKCS#12 não foi preservado.');
}

echo "[OK] Regras de certificado por gateway validadas.\n";
