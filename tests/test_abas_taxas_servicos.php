<?php

/**
 * Teste das traducoes da aba Taxas e servicos em contratos e locacoes.
 *
 * Execute: php tests/test_abas_taxas_servicos.php
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

function checkAbasTaxasServicos(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$rotulos = [
    'pt_BR' => 'Taxas e serviços',
    'pt_PT' => 'Taxas e serviços',
    'en_US' => 'Fees and services',
    'es_ES' => 'Tasas y servicios',
    'it_IT' => 'Tariffe e servizi',
];

foreach ($rotulos as $locale => $rotuloEsperado) {
    foreach (['contratos', 'locacoes'] as $modulo) {
        $translations = require APP_ROOT . "/app/Lang/{$locale}/modules/{$modulo}.php";
        checkAbasTaxasServicos(
            ($translations['tabs']['fees'] ?? null) === $rotuloEsperado,
            "A aba de {$modulo} deve usar '{$rotuloEsperado}' em {$locale}."
        );
    }
}

$views = [
    'novo contrato' => APP_ROOT . '/app/Views/pages/contratos/adicionar.php',
    'edicao de contrato' => APP_ROOT . '/app/Views/pages/contratos/editar.php',
    'locacao' => APP_ROOT . '/app/Views/pages/locacoes/adicionar.php',
];

foreach ($views as $contexto => $path) {
    $view = file_get_contents($path);
    $modulo = str_contains($path, '/contratos/') ? 'contratos' : 'locacoes';
    checkAbasTaxasServicos(
        $view !== false && str_contains($view, "t('modules.{$modulo}.tabs.fees')"),
        "A tela de {$contexto} deve renderizar o rotulo traduzido da aba."
    );
}

echo "OK: aba Taxas e servicos traduzida em contratos e locacoes.\n";
