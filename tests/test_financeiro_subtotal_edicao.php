<?php

require dirname(__DIR__) . '/vendor/autoload.php';
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/Helpers/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

use App\Models\Financeiro;

$_SESSION['chave'] = '1111111111111';
$_SESSION['user_id'] = 7;
$_SESSION['user_name'] = 'Teste automatizado';

$model = new Financeiro();
$id = null;

function validarSubtotal(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

try {
    $id = $model->criar([
        'chave' => $_SESSION['chave'],
        'tipo' => 'R',
        'pago' => 'N',
        'descricao' => 'Teste preservacao subtotal ' . uniqid('', true),
        'data_criada' => today(),
        'data_venci' => today(),
        'valor_subtotal' => 1460,
        'juros' => 2.89,
        'multa' => 146,
        'desconto' => 0,
        'total_parcelas' => 1,
    ]);

    $model->atualizar($id, ['descricao' => 'Edicao sem subtotal']);
    $lancamento = $model->buscarPorId($id);
    validarSubtotal((float) $lancamento['valor_subtotal'] === 1460.0, 'Edicao sem subtotal deve preservar o valor');
    validarSubtotal((float) $lancamento['valor_total'] === 1608.89, 'Edicao comum deve preservar o total');

    $model->atualizar($id, ['juros' => 3.42]);
    $lancamento = $model->buscarPorId($id);
    validarSubtotal((float) $lancamento['valor_subtotal'] === 1460.0, 'Alteracao de juros nao deve zerar subtotal');
    validarSubtotal((float) $lancamento['valor_total'] === 1609.42, 'Total deve usar subtotal preservado');

    $model->atualizar($id, ['valor_subtotal' => 1200, 'juros' => 4]);
    $lancamento = $model->buscarPorId($id);
    validarSubtotal((float) $lancamento['valor_subtotal'] === 1200.0, 'Ajuste interno deve aceitar novo subtotal');
    validarSubtotal((float) $lancamento['valor_total'] === 1350.0, 'Total deve usar o novo subtotal na mesma atualizacao');

    $view = file_get_contents(APP_ROOT . '/app/Views/pages/financeiro/adicionar.php');
    validarSubtotal(
        str_contains($view, "hasOwnProperty.call(dados, 'valor_subtotal')"),
        'Frontend deve converter subtotal somente quando presente no FormData'
    );

    echo "OK: subtotal preservado e total recalculado na ordem correta\n";
} finally {
    if ($id !== null) {
        $model->deletar($id);
    }
}
