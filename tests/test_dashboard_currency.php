#!/usr/bin/env php
<?php

/**
 * Regressao: dashboard e configuracao monetaria respeitam moeda e tenant.
 *
 * Execute: php tests/test_dashboard_currency.php
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

use App\Controllers\DashboardController;
use App\Core\Database;
use App\Helpers\CurrencyHelper;
use App\Models\MatrizFilial;
use App\Models\Model;

function assertDashboardCurrency(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$dashboardSource = file_get_contents(APP_ROOT . '/app/Views/dashboard/index2.php');
$controllerSource = file_get_contents(APP_ROOT . '/app/Controllers/DashboardController.php');
$modelSource = file_get_contents(APP_ROOT . '/app/Models/MatrizFilial.php');
$currencyMethodStart = strpos($modelSource, 'public function buscarConfigMoeda');
$currencyMethodEnd = strpos($modelSource, 'public function buscarConfigData', $currencyMethodStart ?: 0);
$currencyMethodSource = $currencyMethodStart !== false && $currencyMethodEnd !== false
    ? substr($modelSource, $currencyMethodStart, $currencyMethodEnd - $currencyMethodStart)
    : '';

assertDashboardCurrency(
    !str_contains($dashboardSource, 'R$'),
    'O dashboard completo voltou a conter simbolo monetario BRL fixo.'
);
assertDashboardCurrency(
    str_contains($dashboardSource, 'Currency.format(Number(value) || 0, true)'),
    'O auto-refresh do dashboard nao usa o helper monetario multi-tenant.'
);
assertDashboardCurrency(
    !str_contains($controllerSource, "'message' => 'R$ '"),
    'O alerta financeiro voltou a concatenar R$ diretamente.'
);
assertDashboardCurrency(
    $currencyMethodSource !== '' && !str_contains($currencyMethodSource, 'withoutChave()'),
    'A configuracao monetaria voltou a ignorar a chave ao buscar a filial da sessao.'
);

$suffix = bin2hex(random_bytes(8));
$tenant = 'currency-own-' . $suffix;
$foreignTenant = 'currency-foreign-' . $suffix;
$createdIds = [];

try {
    $createdIds[] = $ownId = Database::insertGetId('matrizes_filiais', [
        'chave' => $tenant,
        'tipo' => 'M',
        'status' => 'A',
        'razao_social' => 'Teste dashboard USD',
        'nome_fantasia' => 'Teste dashboard USD',
        'locale' => 'pt_BR',
        'currency_code' => 'USD',
    ]);
    $createdIds[] = $foreignId = Database::insertGetId('matrizes_filiais', [
        'chave' => $foreignTenant,
        'tipo' => 'M',
        'status' => 'A',
        'razao_social' => 'Teste dashboard EUR externo',
        'nome_fantasia' => 'Teste dashboard EUR externo',
        'locale' => 'pt_PT',
        'currency_code' => 'EUR',
    ]);

    $_SESSION['chave'] = $tenant;
    $_SESSION['id_matriz_filial'] = $ownId;

    $matrizFilial = new MatrizFilial();
    $config = $matrizFilial->buscarConfigMoeda();
    assertDashboardCurrency(($config['currency_code'] ?? null) === 'USD', 'A filial do tenant nao retornou USD.');

    CurrencyHelper::clearCache();
    assertDashboardCurrency(currency_format(1234.56) === '$ 1.234,56', 'A formatacao PHP nao respeitou USD/pt_BR.');

    $buildAlerts = new ReflectionMethod(DashboardController::class, 'buildAlerts');
    $alerts = $buildAlerts->invoke(new DashboardController(), [], [
        'overdue_count' => 2,
        'overdue_total' => 1234.56,
    ], null);
    assertDashboardCurrency(
        str_contains($alerts[0]['message'] ?? '', '$ 1.234,56'),
        'O alerta financeiro nao respeitou a moeda USD da sessao.'
    );

    $_SESSION['id_matriz_filial'] = $foreignId;
    $foreignConfig = $matrizFilial->buscarConfigMoeda();
    assertDashboardCurrency($foreignConfig === null, 'A configuracao monetaria vazou de outro tenant pelo ID da filial.');

    echo "OK: dashboard USD, alerta monetario e isolamento por tenant validados.\n";
} finally {
    foreach ($createdIds as $id) {
        Database::execute('DELETE FROM matrizes_filiais WHERE id = ?', [$id]);
    }

    CurrencyHelper::clearCache();
    Model::closeConnection();
    Database::disconnect();
}
