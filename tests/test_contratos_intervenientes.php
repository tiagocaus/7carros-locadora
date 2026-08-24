<?php

/**
 * Teste estrutural da aba Intervenientes no formulario de contratos.
 *
 * Execute: php tests/test_contratos_intervenientes.php
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

function checkContratosIntervenientes(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$views = [
    'novo contrato' => APP_ROOT . '/app/Views/pages/contratos/adicionar.php',
    'edicao de contrato' => APP_ROOT . '/app/Views/pages/contratos/editar.php',
];

foreach ($views as $contexto => $path) {
    $view = file_get_contents($path);
    checkContratosIntervenientes($view !== false, "View de {$contexto} deve estar disponivel.");

    checkContratosIntervenientes(
        substr_count($view, 'data-form-tab-target="#tabIntervenientes"') === 1,
        "{$contexto} deve possuir uma unica aba Intervenientes."
    );
    checkContratosIntervenientes(
        substr_count($view, 'id="tabIntervenientes" class="form-tab-content"') === 1,
        "{$contexto} deve possuir um unico conteudo para Intervenientes."
    );

    foreach (['tabCondutor', 'tabFiador', 'tabAvalista', 'tabTestemunhas'] as $abaLegada) {
        checkContratosIntervenientes(
            !str_contains($view, $abaLegada),
            "{$contexto} nao deve manter a aba separada {$abaLegada}."
        );
    }

    $inicio = strpos($view, '<div id="tabIntervenientes" class="form-tab-content">');
    $fim = strpos($view, '<div id="tabTaxas" class="form-tab-content">', $inicio);
    checkContratosIntervenientes($inicio !== false && $fim !== false, "{$contexto} deve manter Intervenientes antes de Taxas.");

    $conteudo = substr($view, $inicio, $fim - $inicio);
    foreach (['listaCondutores', 'listaFiadores', 'listaAvalistas', 'listaTestemunhas'] as $lista) {
        checkContratosIntervenientes(
            substr_count($conteudo, 'id="' . $lista . '"') === 1,
            "{$contexto} deve agrupar {$lista} dentro de Intervenientes."
        );
    }
}

foreach (glob(APP_ROOT . '/app/Lang/*/modules/contratos.php') as $translationFile) {
    $translations = require $translationFile;
    checkContratosIntervenientes(
        !empty($translations['tabs']['stakeholders']),
        basename(dirname(dirname($translationFile))) . ' deve traduzir a aba Intervenientes.'
    );
}

$docs = file_get_contents(APP_ROOT . '/docs/contratos.md');
checkContratosIntervenientes(
    $docs !== false
        && str_contains($docs, 'possui 8 abas')
        && str_contains($docs, '### Aba 3: Intervenientes'),
    'Documentacao de contratos deve descrever a aba Intervenientes.'
);

echo "OK: intervenientes agrupados nos formularios de contratos.\n";
