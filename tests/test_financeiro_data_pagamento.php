<?php

require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Models\Financeiro;

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

$_SESSION['chave'] = '1111111111111';
$_SESSION['user_id'] = 7;
$_SESSION['user_name'] = 'Teste automatizado';

function validarDataPagamento(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

$model = new Financeiro();
$ids = [];

try {
    $id = $model->criar([
        'chave' => $_SESSION['chave'],
        'tipo' => 'D',
        'pago' => 'N',
        'data_pago' => '2026-07-23',
        'descricao' => 'Teste consistencia data pagamento ' . uniqid('', true),
        'data_criada' => '2026-07-01',
        'data_venci' => '2026-07-31',
        'valor_subtotal' => 10,
    ]);
    $ids[] = $id;

    $lancamento = $model->buscarPorId($id);
    validarDataPagamento(
        $lancamento['data_pago'] === null,
        'Criacao pendente deve ignorar data de pagamento residual'
    );

    $model->atualizar($id, [
        'descricao' => 'Edicao de lancamento pendente',
        'pago' => 'N',
        'data_pago' => '2026-08-10',
    ]);
    $lancamento = $model->buscarPorId($id);
    validarDataPagamento(
        $lancamento['pago'] === 'N' && $lancamento['data_pago'] === null,
        'Edicao pendente deve manter data de pagamento nula'
    );

    $model->atualizar($id, ['data_pago' => '2026-08-09']);
    $lancamento = $model->buscarPorId($id);
    validarDataPagamento(
        $lancamento['data_pago'] === null,
        'Data isolada nao pode ser gravada em lancamento pendente'
    );

    $model->atualizar($id, ['pago' => 'S', 'data_pago' => '2026-08-08']);
    $lancamento = $model->buscarPorId($id);
    validarDataPagamento(
        $lancamento['pago'] === 'S' && $lancamento['data_pago'] === '2026-08-08',
        'Pagamento deve preservar a data informada'
    );

    $model->atualizar($id, ['descricao' => 'Edicao de lancamento pago']);
    $lancamento = $model->buscarPorId($id);
    validarDataPagamento(
        $lancamento['data_pago'] === '2026-08-08',
        'Edicao sem campos de pagamento deve preservar a data existente'
    );

    $model->atualizar($id, ['pago' => 'N', 'data_pago' => '2026-08-10']);
    $lancamento = $model->buscarPorId($id);
    validarDataPagamento(
        $lancamento['pago'] === 'N' && $lancamento['data_pago'] === null,
        'Estorno deve limpar a data mesmo quando o payload envia uma data'
    );

    $idLote = $model->criar([
        'chave' => $_SESSION['chave'],
        'tipo' => 'D',
        'pago' => 'N',
        'descricao' => 'Teste lote data pagamento ' . uniqid('', true),
        'data_criada' => '2026-07-01',
        'data_venci' => '2026-07-31',
        'valor_subtotal' => 20,
    ]);
    $ids[] = $idLote;

    $model->atualizarParcelasLote([$idLote], [
        'pago' => 'S',
        'data_pago' => '2026-08-07',
    ], $_SESSION['chave']);
    $lancamentoLote = $model->buscarPorId($idLote);
    validarDataPagamento(
        $lancamentoLote['pago'] === 'S' && $lancamentoLote['data_pago'] === '2026-08-07',
        'Baixa em lote deve usar a data informada'
    );

    $model->atualizarParcelasLote([$idLote], [
        'pago' => 'N',
        'data_pago' => '2026-08-10',
    ], $_SESSION['chave']);
    $lancamentoLote = $model->buscarPorId($idLote);
    validarDataPagamento(
        $lancamentoLote['pago'] === 'N' && $lancamentoLote['data_pago'] === null,
        'Estorno em lote deve limpar a data residual'
    );

    $view = file_get_contents(APP_ROOT . '/app/Views/pages/financeiro/adicionar.php');
    validarDataPagamento(
        str_contains($view, "document.getElementById('dataPago').value = ''")
        && str_contains($view, 'delete dados.data_pago;'),
        'Frontend deve limpar e omitir data de pagamento de lancamentos pendentes'
    );

    echo "OK: consistencia entre situacao e data de pagamento validada\n";
} finally {
    foreach (array_reverse($ids) as $idExcluir) {
        $model->deletar($idExcluir);
    }
}
