<?php

/**
 * Regressao: website calcula diarias por data/hora e tolerancia do grupo/filial.
 *
 * Execute: php tests/test_website_reserva_periodo.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Core\Database;
use App\Services\WebsiteReservaCalcService;
use App\Services\WebsiteReservaPeriodoService;

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

$falhas = 0;
$sucessos = 0;

function checkPeriodoWebsite(string $label, mixed $atual, mixed $esperado): void
{
    global $falhas, $sucessos;
    $ok = $atual === $esperado;
    echo '   ' . ($ok ? 'PASS' : 'FAIL') . " {$label} - esperado="
        . var_export($esperado, true) . ', atual=' . var_export($atual, true) . PHP_EOL;
    $ok ? $sucessos++ : $falhas++;
}

$periodo = new WebsiteReservaPeriodoService();
checkPeriodoWebsite('24 horas exatas', $periodo->calcularDias('2035-01-06 08:00:00', '2035-01-07 08:00:00'), 1);
checkPeriodoWebsite('um minuto apos 24 horas', $periodo->calcularDias('2035-01-06 08:00:00', '2035-01-07 08:01:00'), 2);
checkPeriodoWebsite('33 horas', $periodo->calcularDias('2035-01-06 08:00:00', '2035-01-07 17:00:00'), 2);
checkPeriodoWebsite('21 horas', $periodo->calcularDias('2035-01-06 20:00:00', '2035-01-07 17:00:00'), 1);
checkPeriodoWebsite('limite de 180 minutos', $periodo->calcularDias('2035-01-06 08:00:00', '2035-01-07 11:00:00', 180), 1);
checkPeriodoWebsite('um minuto apos tolerancia', $periodo->calcularDias('2035-01-06 08:00:00', '2035-01-07 11:01:00', 180), 2);

$periodoInvalido = false;
try {
    $periodo->calcularDias('2035-01-06 08:00:00', '2035-01-06 08:00:00');
} catch (InvalidArgumentException) {
    $periodoInvalido = true;
}
checkPeriodoWebsite('rejeita devolucao sem duracao', $periodoInvalido, true);

$cenario = Database::fetchOne(
    "SELECT chave, id_matriz_filial, id_grupo, minutos_tolerancia
     FROM grupos_precos_filiais
     WHERE valor_plano_km_livre > 0
     ORDER BY id
     LIMIT 1"
);
if (!$cenario) {
    fwrite(STDERR, "Nao ha preco de grupo local para validar o calculo autoritativo.\n");
    exit(1);
}

$_SESSION['chave'] = (string) $cenario['chave'];
$tolerancia = max(0, (int) $cenario['minutos_tolerancia']);
$fimNoLimite = (new DateTimeImmutable('2035-01-06 08:00:00', new DateTimeZone('UTC')))
    ->modify('+' . (1440 + $tolerancia) . ' minutes')
    ->format('Y-m-d H:i:s');
$fimAposLimite = (new DateTimeImmutable($fimNoLimite, new DateTimeZone('UTC')))
    ->modify('+1 minute')
    ->format('Y-m-d H:i:s');

$inputBase = [
    'filial_id' => (int) $cenario['id_matriz_filial'],
    'grupo_id' => (int) $cenario['id_grupo'],
    'plano' => 'KML',
    'dias' => 99,
    'data_inicio' => '2035-01-06',
    'data_saida' => '2035-01-06 08:00:00',
    'servicos' => [],
];
$calcLimite = (new WebsiteReservaCalcService())->calcular($inputBase + ['data_prevista' => $fimNoLimite]);
$calcApos = (new WebsiteReservaCalcService())->calcular($inputBase + ['data_prevista' => $fimAposLimite]);

checkPeriodoWebsite('backend ignora dias enviados no limite', (int) ($calcLimite['breakdown']['plano']['dias'] ?? 0), 1);
checkPeriodoWebsite('backend ignora dias enviados apos limite', (int) ($calcApos['breakdown']['plano']['dias'] ?? 0), 2);
checkPeriodoWebsite('backend devolve tolerancia do grupo', (int) ($calcApos['breakdown']['tolerancia']['minutos'] ?? -1), $tolerancia);

$controller = file_get_contents(APP_ROOT . '/app/Controllers/PublicWebsiteController.php');
$javascript = file_get_contents(APP_ROOT . '/storage/templates/website/assets/js/custom.js');
checkPeriodoWebsite('reserva salva snapshot da tolerancia', str_contains($controller, "'minutos_tolerancia' => (int) (\$toleranciaCalculada['minutos']"), true);
checkPeriodoWebsite('site usa dias por grupo da API', str_contains($javascript, 'resp.dias_por_grupo') && str_contains($javascript, 'diasReservaGrupo(idGrupo)'), true);
checkPeriodoWebsite('site nao calcula dias apenas pelas datas', str_contains($javascript, "new Date(dat + 'T12:00:00')"), false);

echo "\nSucessos: {$sucessos}\nFalhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
