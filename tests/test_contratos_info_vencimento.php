<?php

$root = dirname(__DIR__);

function assertContratoInfoVencimento(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FALHA: {$message}\n");
        exit(1);
    }
}

$view = file_get_contents($root . '/app/Views/pages/contratos/index.php');
assertContratoInfoVencimento($view !== false, 'A view da listagem de contratos deve existir.');

assertContratoInfoVencimento(
    str_contains($view, "c.status === 'A' && !c.auto_renovacao && c.data_fim"),
    'O vencimento deve ser exibido somente para contratos ativos sem renovacao.'
);

assertContratoInfoVencimento(
    str_contains($view, 'if (diffDays < 0)')
        && str_contains($view, 'else if (diffDays === 0)')
        && str_contains($view, "Math.abs(diffDays) + 'd'")
        && str_contains($view, "diffDays + 'd'"),
    'A listagem deve distinguir contratos vencidos, com vencimento hoje e a vencer.'
);

assertContratoInfoVencimento(
    str_contains($view, 'dataIsoValida(dataFim)')
        && str_contains($view, 'DateHelper.todayISO()')
        && str_contains($view, 'DateHelper.diffDays(inicio, fim)')
        && !str_contains($view, 'new Date('),
    'O calculo deve validar a data e usar apenas o DateHelper do tenant.'
);

assertContratoInfoVencimento(
    str_contains($view, 'bg-yellow-100 text-yellow-700')
        && str_contains($view, 'bg-orange-100 text-orange-700')
        && str_contains($view, 'bg-red-100 text-red-700'),
    'Os estados a vencer, hoje e vencido devem ter destaque visual proprio.'
);

foreach (['pt_BR', 'pt_PT', 'en_US', 'es_ES', 'it_IT'] as $locale) {
    $translations = require $root . "/app/Lang/{$locale}/modules/contratos.php";
    foreach (['due_in', 'due_today', 'overdue_by'] as $key) {
        assertContratoInfoVencimento(
            !empty($translations['status'][$key]),
            "A traducao status.{$key} deve existir em {$locale}."
        );
    }
}

echo "OK: coluna Info diferencia contratos a vencer, vencendo hoje e vencidos.\n";
