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

echo "\n=== Teste ajuste de temporada no site ===\n";

$chaveTemporada = 'CE758408F6EF98D7C7A7B786ECA7B3A8';
$_SESSION['chave'] = $chaveTemporada;
$serviceTemporada = new GrupoPrecoPeriodoService();

$foraTemporada = $serviceTemporada->calcularValorPeriodo(1738, 650, 'KMC', 5, '2026-11-01', $chaveTemporada);
checkMoneyGrupoPreco('Fora da temporada mantem subtotal base', (float) $foraTemporada['subtotal'], 700.00);
checkGrupoPreco('Fora da temporada nao marca ajuste', $foraTemporada['tem_ajuste'], false);

$temporadaCompleta = $serviceTemporada->calcularValorPeriodo(1738, 650, 'KMC', 5, '2026-12-15', $chaveTemporada);
checkMoneyGrupoPreco('Final de ano aplica +50% nas cinco diarias', (float) $temporadaCompleta['subtotal'], 1050.00);
checkMoneyGrupoPreco('Diaria media integral da temporada', (float) $temporadaCompleta['valor_dia'], 210.00);
checkGrupoPreco('Temporada informa cinco dias aplicados', $temporadaCompleta['temporadas'][0]['dias_aplicados'] ?? null, 5);

$periodoMisto = $serviceTemporada->calcularValorPeriodo(1738, 650, 'KMC', 5, '2026-12-13', $chaveTemporada);
checkMoneyGrupoPreco('Periodo misto reajusta somente tres diarias', (float) $periodoMisto['subtotal'], 910.00);
checkMoneyGrupoPreco('Periodo misto retorna diaria media', (float) $periodoMisto['valor_dia'], 182.00);
checkGrupoPreco('Periodo misto informa tres dias aplicados', $periodoMisto['temporadas'][0]['dias_aplicados'] ?? null, 3);

$viradaAno = $serviceTemporada->calcularValorPeriodo(1738, 650, 'KMC', 5, '2027-01-02', $chaveTemporada);
checkMoneyGrupoPreco('Temporada recorrente funciona apos a virada do ano', (float) $viradaAno['subtotal'], 1050.00);

$siteTemporada = (new WebsiteReservaCalcService())->calcular([
    'filial_id' => 650,
    'grupo_id' => 1738,
    'plano' => 'KMC',
    'dias' => 5,
    'data_inicio' => '2026-12-15',
    'servicos' => [],
    'seguro_carro' => false,
    'seguro_terceiros' => false,
]);
checkMoneyGrupoPreco('Site publico usa subtotal com temporada', (float) ($siteTemporada['breakdown']['plano']['subtotal'] ?? 0), 1050.00);
checkGrupoPreco('Site publico sinaliza ajuste de temporada', $siteTemporada['breakdown']['plano']['tem_ajuste_temporada'] ?? null, true);

$dataInvalidaRejeitada = false;
try {
    (new WebsiteReservaCalcService())->calcular([
        'filial_id' => 650,
        'grupo_id' => 1738,
        'plano' => 'KMC',
        'dias' => 5,
        'data_inicio' => '2026-13-40',
    ]);
} catch (InvalidArgumentException) {
    $dataInvalidaRejeitada = true;
}
checkGrupoPreco('Site rejeita data manipulada ou invalida', $dataInvalidaRejeitada, true);

echo "\nSucessos: {$sucessos}\n";
echo "Falhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
