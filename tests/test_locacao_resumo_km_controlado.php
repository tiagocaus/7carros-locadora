<?php

/**
 * Teste estrutural do informativo de franquia KMC no resumo da locacao.
 *
 * Execute: php tests/test_locacao_resumo_km_controlado.php
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

$view = file_get_contents(APP_ROOT . '/app/Views/pages/locacoes/adicionar.php');
$docs = file_get_contents(APP_ROOT . '/docs/locacoes.md');
$checks = 0;
$failures = 0;

$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$condition) {
        $failures++;
    }
    echo '   ' . ($condition ? 'PASS' : 'FAIL') . " {$message}\n";
};

echo "=== Teste resumo KMC da locacao ===\n";

$check(
    str_contains($view, "'kmAllowanceInfo' => t('modules.locacoes.pdf.km_allowance_info')")
        && str_contains($view, "'kmAllowanceUnitDay' => t('modules.locacoes.pdf.km_allowance_unit_day')"),
    'resumo reutiliza as traducoes da fatura'
);

$check(
    str_contains($view, "plano === 'KMC' && franquiaDiaria > 0")
        && str_contains($view, 'const franquiaTotal = franquiaDiaria * dias;'),
    'informativo exige KMC e calcula a franquia total pelo periodo'
);

$check(
    str_contains($view, ".replace(':franquia', `\${Km.format(franquiaDiaria)}km`)")
        && str_contains($view, ".replace(':total', `\${Km.format(franquiaTotal)}Km`)")
        && str_contains($view, '<tr class="bg-white text-xs text-slate-500">')
        && str_contains($view, '<td colspan="5" class="px-4 py-2">'),
    'mensagem apresenta franquia diaria e total em linha completa com estilo neutro'
);

$check(
    str_contains($docs, 'franquia diaria * dias da locacao'),
    'documentacao descreve o informativo dinamico'
);

echo "\nResultado: " . ($checks - $failures) . "/{$checks} verificacoes passaram.\n";
exit($failures === 0 ? 0 : 1);
