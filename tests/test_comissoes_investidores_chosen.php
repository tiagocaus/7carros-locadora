<?php

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

function checkComissoesInvestidoresChosen(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$view = file_get_contents(APP_ROOT . '/app/Views/pages/comissoes-investidores/index.php');
checkComissoesInvestidoresChosen($view !== false, 'View de comissoes de investidores deve estar disponivel.');

checkComissoesInvestidoresChosen(
    preg_match('/<select\b[^>]*\bid="filtroInvestidor"[^>]*>/s', $view, $matches) === 1,
    'Select de investidor deve existir.'
);

$tag = $matches[0];
checkComissoesInvestidoresChosen(
    preg_match('/\bclass="[^"]*\bchosen-select\b[^"]*"/', $tag) === 1,
    'Select de investidor deve usar chosen-select.'
);
checkComissoesInvestidoresChosen(
    str_contains($tag, 'data-chosen-type="server-side"'),
    'Select de investidor deve usar carregamento server-side.'
);
checkComissoesInvestidoresChosen(
    str_contains($tag, 'data-chosen-search-url="/api/fornecedores/investidores/select"'),
    'Select de investidor deve usar o endpoint dedicado aos investidores.'
);
checkComissoesInvestidoresChosen(
    !str_contains($tag, 'data-api-url=') && !str_contains($tag, 'data-chosen-type="server"'),
    'Select de investidor nao deve manter atributos legados.'
);
checkComissoesInvestidoresChosen(
    str_contains($view, 'filtroInvestidor.chosenSelect.clear();'),
    'Limpeza dos filtros deve sincronizar o componente chosen-select.'
);
checkComissoesInvestidoresChosen(
    !str_contains($view, "trigger('chosen:updated')"),
    'View nao deve depender do plugin jQuery Chosen legado.'
);

echo "OK: filtro de investidor usa chosen-select server-side.\n";
