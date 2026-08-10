<?php

define('APP_ROOT', dirname(__DIR__));

$view = file_get_contents(APP_ROOT . '/app/Views/pages/financeiro/adicionar.php');

$trechosObrigatorios = [
    'id="btnCancelarParcelamento"',
    "addEventListener('click', cancelarParcelamento)",
    'function cancelarParcelamento()',
    'parcelasPreview = [];',
    'document.querySelectorAll(\'input[name^="parcelas["]\').forEach(el => el.remove());',
    "document.getElementById('previewParcelasContainer').classList.add('hidden');",
    "document.getElementById('btnCancelarParcelamento')?.classList.add('hidden');",
    "dados.parcelas = parcelasPreview;",
];

foreach ($trechosObrigatorios as $trecho) {
    if (!str_contains($view, $trecho)) {
        throw new RuntimeException("Fluxo de cancelamento do preview incompleto: {$trecho}");
    }
}

foreach (['pt_BR', 'en_US', 'es_ES', 'pt_PT', 'it_IT'] as $idioma) {
    $traducao = file_get_contents(APP_ROOT . "/app/Lang/{$idioma}/modules/financeiro.php");
    if (!str_contains($traducao, "'cancel_installments'")) {
        throw new RuntimeException("Traducao de cancelamento ausente para {$idioma}");
    }
}

echo "Teste do cancelamento do preview de parcelas passou.\n";
