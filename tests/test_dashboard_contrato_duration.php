<?php

/**
 * Teste: contratos ativos no dashboard exibem tempo de contrato, nao atraso.
 *
 * Execute: php tests/test_dashboard_contrato_duration.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/Helpers/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

use App\Models\Contrato;

$falhas = 0;
$sucessos = 0;

function checkDashboardContratoDuration(string $label, bool $ok): void
{
    global $falhas, $sucessos;

    echo '   ' . ($ok ? 'PASS' : 'FAIL') . " {$label}\n";
    if ($ok) {
        $sucessos++;
    } else {
        $falhas++;
    }
}

echo "=== Teste dashboard contrato tempo de contrato ===\n";

try {
    $contrato = new Contrato();
    $reflection = new ReflectionClass($contrato);
    $method = $reflection->getMethod('formatDashboardSimpleContratoRow');
    $method->setAccessible(true);

    $row = $method->invoke($contrato, [
        'id' => 123,
        'codigo' => 'C123',
        'status' => 'A',
        'data_ini' => date('Y-m-d H:i:s', strtotime('-1936 days')),
        'data_fim' => date('Y-m-d H:i:s', strtotime('-1900 days')),
        'cliente' => 'Cliente Teste',
        'placa' => 'ABC1D23',
        'marca' => 'Marca',
        'modelo' => 'Modelo',
        'filial_retirada' => 'Matriz',
    ]);

    checkDashboardContratoDuration('contrato retorna tipo contract_duration', ($row['prazo_tipo'] ?? '') === 'contract_duration');
    checkDashboardContratoDuration('contrato nao retorna tipo overdue', ($row['prazo_tipo'] ?? '') !== 'overdue');
    checkDashboardContratoDuration('label informa contrato', str_contains((string) ($row['prazo_label'] ?? ''), 'contrato'));
    checkDashboardContratoDuration('label nao informa atraso', !str_contains((string) ($row['prazo_label'] ?? ''), 'atraso'));
} catch (\Throwable $e) {
    echo 'ERRO: ' . $e->getMessage() . "\n";
    $falhas++;
}

echo "\nSucessos: {$sucessos}\n";
echo "Falhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
