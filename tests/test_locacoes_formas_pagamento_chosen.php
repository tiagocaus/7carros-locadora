<?php

require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

function checkLocacoesFormaPagamentoChosen(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$view = file_get_contents(APP_ROOT . '/app/Views/pages/locacoes/adicionar.php');
checkLocacoesFormaPagamentoChosen($view !== false, 'View de locacoes deve estar disponivel.');

$contasController = file_get_contents(APP_ROOT . '/app/Controllers/ContasBancariasController.php');
checkLocacoesFormaPagamentoChosen($contasController !== false, 'Controller de contas bancarias deve estar disponivel.');
checkLocacoesFormaPagamentoChosen(
    str_contains($contasController, '$contaModel->listarParaSelect($search)'),
    'Endpoint de contas bancarias para chosen deve limitar a busca aos registros ativos.'
);

$selectIds = [
    'id_forma_pagamento',
    'id_forma_pagamento_caucao',
    'gerar_id_forma_pagamento',
    'parcela_id_forma_pagamento',
    'avaria_id_forma_pagamento',
    'pagar_id_forma_pagamento',
];

$paymentSelectIds = [
    'gerar_id_forma_pagamento',
    'parcela_id_forma_pagamento',
    'avaria_id_forma_pagamento',
    'pagar_id_forma_pagamento',
];

foreach ($selectIds as $selectId) {
    $pattern = '/<select\b[^>]*\bid="' . preg_quote($selectId, '/') . '"[^>]*>/s';
    checkLocacoesFormaPagamentoChosen(
        preg_match($pattern, $view, $matches) === 1,
        "Select {$selectId} deve existir."
    );

    $tag = $matches[0];
    checkLocacoesFormaPagamentoChosen(
        preg_match('/\bclass="[^"]*\bchosen-select\b[^"]*"/', $tag) === 1,
        "Select {$selectId} deve usar chosen-select."
    );
    checkLocacoesFormaPagamentoChosen(
        str_contains($tag, 'data-chosen-type="server-side"'),
        "Select {$selectId} deve usar carregamento server-side."
    );
    checkLocacoesFormaPagamentoChosen(
        str_contains($tag, 'data-chosen-search-url="/api/formas-pagamento/select"'),
        "Select {$selectId} deve usar o endpoint de formas ativas."
    );
}

foreach ($paymentSelectIds as $selectId) {
    preg_match('/<select\b[^>]*\bid="' . preg_quote($selectId, '/') . '"[^>]*>/s', $view, $matches);
    checkLocacoesFormaPagamentoChosen(
        str_contains($matches[0], 'data-chosen-placement="bottom"'),
        "Select {$selectId} deve abrir imediatamente abaixo do campo."
    );
}

$accountSelectIds = [
    'gerar_id_conta',
    'parcela_id_conta',
    'avaria_id_conta',
    'pagar_id_conta',
];

foreach ($accountSelectIds as $selectId) {
    $pattern = '/<select\b[^>]*\bid="' . preg_quote($selectId, '/') . '"[^>]*>/s';
    checkLocacoesFormaPagamentoChosen(
        preg_match($pattern, $view, $matches) === 1,
        "Select {$selectId} deve existir."
    );

    $tag = $matches[0];
    checkLocacoesFormaPagamentoChosen(
        preg_match('/\bclass="[^"]*\bchosen-select\b[^"]*"/', $tag) === 1,
        "Select {$selectId} deve usar chosen-select."
    );
    checkLocacoesFormaPagamentoChosen(
        str_contains($tag, 'data-chosen-type="server-side"'),
        "Select {$selectId} deve usar carregamento server-side."
    );
    checkLocacoesFormaPagamentoChosen(
        str_contains($tag, 'data-chosen-search-url="/api/contas-bancarias/buscar"'),
        "Select {$selectId} deve usar o endpoint de contas bancarias."
    );
    checkLocacoesFormaPagamentoChosen(
        str_contains($tag, 'data-chosen-placement="bottom"'),
        "Select {$selectId} deve abrir imediatamente abaixo do campo."
    );
}

checkLocacoesFormaPagamentoChosen(
    !str_contains($view, "API.get('/api/formas-pagamento')"),
    'View nao deve usar a listagem paginada de 10 registros para popular formas de pagamento.'
);
checkLocacoesFormaPagamentoChosen(
    !str_contains($view, "API.get('/api/contas-bancarias/buscar')"),
    'View nao deve carregar manualmente as contas bancarias dos formularios de parcelas.'
);
checkLocacoesFormaPagamentoChosen(
    str_contains($view, "setChosen('id_forma_pagamento', locacaoData.id_forma_pagamento, locacaoData.forma_pagamento_descricao);"),
    'Edicao deve preservar a forma de pagamento principal, inclusive historica.'
);
checkLocacoesFormaPagamentoChosen(
    str_contains($view, "setChosen('pagar_id_forma_pagamento', idForma, formaPagamento);"),
    'Baixa de parcela deve preservar a forma de pagamento vinculada.'
);
checkLocacoesFormaPagamentoChosen(
    str_contains($view, "setChosen('pagar_id_conta', idConta, contaDescricao);"),
    'Baixa de parcela deve preservar a conta bancaria vinculada.'
);
checkLocacoesFormaPagamentoChosen(
    str_contains($view, "select.dispatchEvent(new Event('change', { bubbles: true }));"),
    'Selecoes injetadas devem manter o evento change para campos dependentes.'
);

echo "OK: formas de pagamento das locacoes usam chosen-select server-side.\n";
