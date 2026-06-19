<?php

/**
 * Teste: preco progressivo por dias em grupos de veiculos.
 *
 * Execute: php tests/test_grupo_preco_periodo.php
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

use App\Services\GrupoPrecoPeriodoService;
use App\Services\WebsiteReservaCalcService;

$chave = '1B36EA1C9B7A1C3AD668B8BB5DF7963F';
$_SESSION['chave'] = $chave;

$falhas = 0;
$sucessos = 0;

function checkGrupoPreco(string $label, $atual, $esperado): void
{
    global $falhas, $sucessos;
    $ok = $atual === $esperado;
    echo '   ' . ($ok ? 'PASS' : 'FAIL') . " {$label} - esperado=" . var_export($esperado, true) . ', atual=' . var_export($atual, true) . PHP_EOL;
    if ($ok) {
        $sucessos++;
    } else {
        $falhas++;
    }
}

function checkMoneyGrupoPreco(string $label, float $atual, float $esperado): void
{
    checkGrupoPreco($label, round($atual, 2), round($esperado, 2));
}

echo "=== Teste preco progressivo por dias (tenant {$chave}) ===\n";

$service = new GrupoPrecoPeriodoService();

$grupoF20 = $service->calcularValorDiaria(1377, 120, 'KMC', 20);
checkMoneyGrupoPreco('Grupo F KMC 20 dias usa faixa 16-20', (float) $grupoF20['valor'], 153.00);
checkGrupoPreco('Grupo F KMC 20 dias origem', $grupoF20['origem'], 'preco_dias');

$grupoH33 = $service->calcularValorDiaria(3625, 120, 'KMC', 33);
checkMoneyGrupoPreco('Grupo H KMC 33 dias usa faixa 21-365', (float) $grupoH33['valor'], 168.00);
checkGrupoPreco('Grupo H KMC tipo plano', $grupoH33['tipo_plano'], 'km_controlado');

$grupoB7 = $service->calcularValorDiaria(246, 120, 'KMC', 7);
checkMoneyGrupoPreco('Grupo B KMC 7 dias usa faixa 6-10', (float) $grupoB7['valor'], 139.50);

$grupoC7 = $service->calcularValorDiaria(247, 120, 'KMC', 7);
checkMoneyGrupoPreco('Grupo C KMC 7 dias sem faixa usa base', (float) $grupoC7['valor'], 160.00);
checkGrupoPreco('Grupo C KMC origem fallback', $grupoC7['origem'], 'preco_base');

$siteCalc = (new WebsiteReservaCalcService())->calcular([
    'filial_id' => 120,
    'grupo_id' => 1377,
    'plano' => 'KMC',
    'dias' => 20,
    'servicos' => [],
    'seguro_carro' => false,
    'seguro_terceiros' => false,
]);
checkMoneyGrupoPreco('Site publico usa diaria progressiva no subtotal', (float) ($siteCalc['breakdown']['plano']['subtotal'] ?? 0), 3060.00);
checkGrupoPreco('Site publico origem do plano', $siteCalc['breakdown']['plano']['origem'] ?? null, 'preco_dias');

echo "\nSucessos: {$sucessos}\n";
echo "Falhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
