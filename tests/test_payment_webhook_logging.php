#!/usr/bin/env php
<?php

/**
 * Regressao: apenas referencias locais link_* ausentes devem gerar alerta.
 *
 * Execute: php tests/test_payment_webhook_logging.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\PagamentoPublicoController;
use App\Core\Database;

$controller = new PagamentoPublicoController();
$method = new ReflectionMethod($controller, 'shouldAlertMissingTransaction');
$method->setAccessible(true);

$cases = [
    '' => false,
    'pay_externo' => false,
    'link_86623' => true,
    ' link_76777 ' => true,
];

foreach ($cases as $reference => $expected) {
    $actual = $method->invoke($controller, $reference);
    if ($actual !== $expected) {
        throw new RuntimeException("Classificacao incorreta para referencia '{$reference}'.");
    }
}

$config = new ReflectionProperty(Database::class, 'config');
$config->setAccessible(true);
$debugLog = new ReflectionMethod($controller, 'webhookDebugLog');
$debugLog->setAccessible(true);
$logPath = tempnam(sys_get_temp_dir(), 'webhook-debug-');
if ($logPath === false) {
    throw new RuntimeException('Nao foi possivel criar log temporario.');
}
file_put_contents($logPath, '');
ini_set('log_errors', '1');
ini_set('error_log', $logPath);

$config->setValue(null, ['PAYMENT_WEBHOOK_DEBUG' => 'false']);
$debugLog->invoke($controller, '[Webhook] DEBUG_DESATIVADO');
if (str_contains((string) file_get_contents($logPath), 'DEBUG_DESATIVADO')) {
    throw new RuntimeException('Webhook escreveu log detalhado com debug desativado.');
}

$config->setValue(null, ['PAYMENT_WEBHOOK_DEBUG' => 'true']);
$debugLog->invoke($controller, '[Webhook] DEBUG_ATIVADO');
if (!str_contains((string) file_get_contents($logPath), 'DEBUG_ATIVADO')) {
    throw new RuntimeException('Webhook nao escreveu log detalhado com debug ativado.');
}

echo "OK: webhook classifica referencias locais e respeita a configuracao de debug.\n";
