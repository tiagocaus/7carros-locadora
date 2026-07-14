<?php

require dirname(__DIR__) . '/vendor/autoload.php';
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/Helpers/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

use App\Models\Financeiro;
use App\Models\FinanceiroTaxa;
use App\Models\FormaPagamento;
use App\Services\FinanceiroTaxaService;

$_SESSION['chave'] = '1111111111111';
$_SESSION['user_id'] = 7;
$_SESSION['user_name'] = 'Teste automatizado';

$formaModel = new FormaPagamento();
$financeiroModel = new Financeiro();
$taxaModel = new FinanceiroTaxa();
$idForma = null;
$idReceita = null;

function verificar(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

verificar(function_exists('currency_parse'), 'Bootstrap CLI deve carregar os helpers financeiros');

try {
    $idForma = $formaModel->criar([
        'chave' => $_SESSION['chave'],
        'nome' => 'Teste taxa automatica ' . uniqid('', true),
        'lancar_pago' => 'N',
        'onde_exibir' => '2',
        'status' => 'A',
        'taxa_fixa' => 10,
        'taxa_fixa_parcela' => 0,
        'taxa_percentual_parcela' => 0,
    ]);

    $idReceita = $financeiroModel->criar([
        'chave' => $_SESSION['chave'],
        'id_forma_pagamento' => $idForma,
        'tipo' => 'R',
        'pago' => 'N',
        'descricao' => 'Receita de teste da taxa',
        'data_criada' => date('Y-m-d'),
        'data_venci' => date('Y-m-d'),
        'valor_subtotal' => 100,
        'valor_total' => 100,
        'total_parcelas' => 1,
    ]);

    verificar($taxaModel->buscarDespesaVinculada($idReceita) === null, 'Receita pendente nao deve gerar despesa');

    $financeiroModel->atualizar($idReceita, ['pago' => 'S', 'data_pago' => date('Y-m-d')]);
    $despesa = $taxaModel->buscarDespesaVinculada($idReceita);
    verificar($despesa !== null, 'Baixa deve gerar despesa vinculada');
    verificar((float) $despesa['valor_total'] === 10.0, 'Despesa deve ter o valor da taxa');
    verificar($despesa['tipo'] === 'D' && $despesa['pago'] === 'S', 'Taxa deve ser uma despesa paga');

    $idDespesa = (int) $despesa['id'];
    $idsRetroativo = array_map('intval', array_column($taxaModel->listarReceitasParaRetroativo(), 'id'));
    verificar(!in_array($idReceita, $idsRetroativo, true), 'Receita contabilizada nao deve aparecer no retroativo');

    $taxaModel->excluirDespesaPorReceita($idReceita);
    $idsRetroativo = array_map('intval', array_column($taxaModel->listarReceitasParaRetroativo(), 'id'));
    verificar(in_array($idReceita, $idsRetroativo, true), 'Receita paga sem despesa deve aparecer no retroativo');

    (new FinanceiroTaxaService())->sincronizar($idReceita);
    $despesaRecriada = $taxaModel->buscarDespesaVinculada($idReceita);
    verificar($despesaRecriada !== null, 'Retroativo deve recriar a despesa ausente');

    $idDespesa = (int) $despesaRecriada['id'];
    (new FinanceiroTaxaService())->sincronizar($idReceita);
    verificar((int) $taxaModel->buscarDespesaVinculada($idReceita)['id'] === $idDespesa, 'Sincronizacao deve ser idempotente');

    $financeiroModel->atualizar($idReceita, ['pago' => 'N']);
    verificar($taxaModel->buscarDespesaVinculada($idReceita) === null, 'Estorno deve remover a despesa vinculada');

    echo "OK: contabilizacao, idempotencia e estorno da taxa\n";
} finally {
    if ($idReceita !== null) {
        $financeiroModel->deletar($idReceita);
    }
    if ($idForma !== null) {
        $formaModel->excluir($idForma);
    }
}
