<?php

$formPath = __DIR__ . '/../app/Views/pages/orcamentos/adicionar.php';
$listPath = __DIR__ . '/../app/Views/pages/orcamentos/index.php';
$form = file_get_contents($formPath);
$list = file_get_contents($listPath);

$failures = 0;
$check = static function (bool $condition, string $message) use (&$failures): void {
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) $failures++;
};

$check(str_contains($form, 'function escapeHtml(value)'), 'formulário define escapeHtml localmente');
$check(str_contains($list, 'function escapeHtml(value)'), 'listagem define escapeHtml localmente');
$check(!str_contains($form, 'chosen:updated'), 'formulário não usa evento do Chosen jQuery');
$check(str_contains($form, 'select.chosenSelect.clear()'), 'seletor de taxas usa a API clear do ChosenSelect');
$check(str_contains($form, 'select.chosenSelect.refresh()'), 'selects dinâmicos usam a API refresh do ChosenSelect');
$check(
    strpos($form, 'renderFees();clearChosen(select);') !== false,
    'taxa é renderizada antes de o seletor ser limpo'
);
$check(
    str_contains($form, "notify(result.message);navigate('/pages/orcamentos');"),
    'criação e edição retornam para a listagem após salvar'
);
$check(str_contains($list, 'table-pagination-controls'), 'listagem usa os controles de paginação do sistema');
$check(str_contains($list, 'pagination-button numbered'), 'listagem renderiza botões de página numerados');
$check(str_contains($list, 'Mostrando ${start}-${end} de ${total} registros'), 'listagem informa o intervalo de registros');
$check(str_contains($list, 'hidden lg:table-cell'), 'listagem aplica colunas responsivas');
$check(!str_contains($form, 'formatDateTimeForInput'), 'edição não usa data localizada em datetime-local');
$check(
    substr_count($form, 'DateHelper.toOperationalDateTimeInput(initialState.') === 2,
    'retirada e devolução usam o helper operacional documentado'
);
$check(str_contains($form, 'if(editingId)renderSummary(initialState);else calculate(false);'), 'edição exibe o snapshot sem recalcular ao abrir');
$check(str_contains($form, 'select.add(new Option(currentLabel,current))'), 'veículo preferencial salvo é preservado fora da disponibilidade atual');
$check(!str_contains($form, "$('plano').dispatchEvent(new Event('change'))"), 'inicialização do plano não dispara recálculo artificial');

exit($failures > 0 ? 1 : 0);
