<?php

/**
 * Teste: seguros opcionais/obrigatorios no site e protecao server-side.
 *
 * Execute: php tests/test_website_seguros_obrigatorios.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Core\Database;
use App\Services\WebsiteReservaCalcService;

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

$falhas = 0;
$sucessos = 0;

function checkSeguroWebsite(string $label, mixed $atual, mixed $esperado): void
{
    global $falhas, $sucessos;
    $ok = $atual === $esperado;
    echo '   ' . ($ok ? 'PASS' : 'FAIL') . " {$label} - esperado="
        . var_export($esperado, true) . ', atual=' . var_export($atual, true) . PHP_EOL;
    $ok ? $sucessos++ : $falhas++;
}

echo "=== Teste seguros obrigatorios do website ===\n";

$colunas = Database::fetchAll(
    "SELECT column_name, column_default
     FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'site_config'
       AND column_name IN ('seguro_carro_obrigatorio', 'seguro_terceiros_obrigatorio')"
);
checkSeguroWebsite('migration criou as duas colunas', count($colunas), 2);
foreach ($colunas as $coluna) {
    checkSeguroWebsite("default {$coluna['column_name']}", (string) $coluna['column_default'], '0');
}

$preco = Database::fetchOne(
    "SELECT sc.chave, sc.seguro_carro_obrigatorio, sc.seguro_terceiros_obrigatorio,
            gpf.id_grupo, gpf.id_matriz_filial, gpf.valor_seguro_carro, gpf.valor_seguro_terceiros
     FROM site_config sc
     INNER JOIN grupos_precos_filiais gpf ON gpf.chave = sc.chave
     WHERE gpf.valor_plano_km_livre > 0
       AND (gpf.valor_seguro_carro > 0 OR gpf.valor_seguro_terceiros > 0)
     LIMIT 1"
);

if (!$preco) {
    echo "Nao ha tenant com site e preco de seguro para executar o teste.\n";
    exit(1);
}

$chave = (string) $preco['chave'];
$_SESSION['chave'] = $chave;

try {
    Database::execute(
        'UPDATE site_config SET seguro_carro_obrigatorio = 0, seguro_terceiros_obrigatorio = 0 WHERE chave = ?',
        [$chave]
    );
    $opcional = (new WebsiteReservaCalcService())->calcular([
        'filial_id' => (int) $preco['id_matriz_filial'],
        'grupo_id' => (int) $preco['id_grupo'],
        'plano' => 'KML',
        'dias' => 2,
        'servicos' => [],
        'seguro_carro' => false,
        'seguro_terceiros' => false,
    ]);
    checkSeguroWebsite('seguro do veiculo opcional permanece desmarcado', $opcional['breakdown']['seguros_detalhe']['carro']['selecionado'], false);
    checkSeguroWebsite('seguro de terceiros opcional permanece desmarcado', $opcional['breakdown']['seguros_detalhe']['terceiros']['selecionado'], false);
    checkSeguroWebsite('seguros opcionais nao entram no total', (float) $opcional['breakdown']['seguros'], 0.0);

    Database::execute(
        'UPDATE site_config SET seguro_carro_obrigatorio = 1, seguro_terceiros_obrigatorio = 1 WHERE chave = ?',
        [$chave]
    );
    $obrigatorio = (new WebsiteReservaCalcService())->calcular([
        'filial_id' => (int) $preco['id_matriz_filial'],
        'grupo_id' => (int) $preco['id_grupo'],
        'plano' => 'KML',
        'dias' => 2,
        'servicos' => [],
        // Payload adulterado: o backend deve ignorar ambos os false.
        'seguro_carro' => false,
        'seguro_terceiros' => false,
    ]);
    $esperado = round(((float) $preco['valor_seguro_carro'] + (float) $preco['valor_seguro_terceiros']) * 2, 2);
    checkSeguroWebsite('backend forca seguro do veiculo', $obrigatorio['breakdown']['seguros_detalhe']['carro']['selecionado'], true);
    checkSeguroWebsite('backend forca seguro de terceiros', $obrigatorio['breakdown']['seguros_detalhe']['terceiros']['selecionado'], true);
    checkSeguroWebsite('backend recalcula subtotal dos seguros', (float) $obrigatorio['breakdown']['seguros'], $esperado);
} finally {
    Database::execute(
        'UPDATE site_config SET seguro_carro_obrigatorio = ?, seguro_terceiros_obrigatorio = ? WHERE chave = ?',
        [(int) $preco['seguro_carro_obrigatorio'], (int) $preco['seguro_terceiros_obrigatorio'], $chave]
    );
}

$precoZero = Database::fetchOne(
    "SELECT sc.chave, sc.seguro_carro_obrigatorio, sc.seguro_terceiros_obrigatorio,
            gpf.id_grupo, gpf.id_matriz_filial
     FROM site_config sc
     INNER JOIN grupos_precos_filiais gpf ON gpf.chave = sc.chave
     WHERE gpf.valor_plano_km_livre > 0
       AND gpf.valor_seguro_carro = 0
       AND gpf.valor_seguro_terceiros = 0
     LIMIT 1"
);
if ($precoZero) {
    $chaveZero = (string) $precoZero['chave'];
    $_SESSION['chave'] = $chaveZero;
    try {
        Database::execute(
            'UPDATE site_config SET seguro_carro_obrigatorio = 1, seguro_terceiros_obrigatorio = 1 WHERE chave = ?',
            [$chaveZero]
        );
        $gratuito = (new WebsiteReservaCalcService())->calcular([
            'filial_id' => (int) $precoZero['id_matriz_filial'],
            'grupo_id' => (int) $precoZero['id_grupo'],
            'plano' => 'KML',
            'dias' => 2,
            'servicos' => [],
        ]);
        checkSeguroWebsite('seguro gratuito obrigatorio permanece selecionado', $gratuito['breakdown']['seguros_detalhe']['carro']['selecionado'], true);
        checkSeguroWebsite('seguros gratuitos nao alteram o total', (float) $gratuito['breakdown']['seguros'], 0.0);
    } finally {
        Database::execute(
            'UPDATE site_config SET seguro_carro_obrigatorio = ?, seguro_terceiros_obrigatorio = ? WHERE chave = ?',
            [(int) $precoZero['seguro_carro_obrigatorio'], (int) $precoZero['seguro_terceiros_obrigatorio'], $chaveZero]
        );
    }
}

$template = file_get_contents(APP_ROOT . '/storage/templates/website/reserva.php');
checkSeguroWebsite('template renderiza seguro do veiculo', str_contains($template, 'id="seguro-veiculo"'), true);
checkSeguroWebsite('template renderiza seguro de terceiros', str_contains($template, 'id="seguro-terceiros"'), true);
checkSeguroWebsite('controles obrigatorios nao usam disabled', str_contains($template, 'id="seguro-veiculo" disabled'), false);

echo "\nSucessos: {$sucessos}\nFalhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
