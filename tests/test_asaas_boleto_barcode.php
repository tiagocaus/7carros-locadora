#!/usr/bin/env php
<?php

/**
 * Regressao: boleto Asaas deve expor linha digitavel, nao apenas nossoNumero.
 *
 * Uso: php tests/test_asaas_boleto_barcode.php
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Services\Gateways\AsaasGateway;

$gateway = new AsaasGateway([]);
$method = new ReflectionMethod(AsaasGateway::class, 'resolveBoletoBarcode');
$method->setAccessible(true);

$cases = [
    [
        'payment' => (object) ['nossoNumero' => '123456789'],
        'boletoInfo' => (object) ['identificationField' => '03399.77779 29900.000000 04751.101017 1 81510000002990'],
        'expected' => '03399.77779 29900.000000 04751.101017 1 81510000002990',
        'label' => 'identificationField tem prioridade',
    ],
    [
        'payment' => (object) ['nossoNumero' => '123456789'],
        'boletoInfo' => ['barCode' => '00195809300000750000000000000000000000000000'],
        'expected' => '00195809300000750000000000000000000000000000',
        'label' => 'barCode e usado quando nao ha linha digitavel',
    ],
    [
        'payment' => (object) ['identificationField' => '10490.12345 67890.123456 78901.234567 8 12340000075000'],
        'boletoInfo' => null,
        'expected' => '10490.12345 67890.123456 78901.234567 8 12340000075000',
        'label' => 'payload de pagamento tambem pode trazer linha digitavel',
    ],
    [
        'payment' => (object) ['nossoNumero' => '123456789'],
        'boletoInfo' => null,
        'expected' => '123456789',
        'label' => 'fallback preserva nossoNumero',
    ],
];

foreach ($cases as $case) {
    $actual = $method->invoke($gateway, $case['payment'], $case['boletoInfo']);
    if ($actual !== $case['expected']) {
        fwrite(STDERR, "[FAIL] {$case['label']}: esperado {$case['expected']}, obtido {$actual}\n");
        exit(1);
    }
}

echo "[OK] AsaasGateway prioriza linha digitavel do boleto.\n";
