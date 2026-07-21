<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Controllers\FinanceiroController;
use App\Models\Financeiro;

function assertLimiteParcelasSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . ' Esperado: ' . var_export($expected, true) . ' Obtido: ' . var_export($actual, true)
        );
    }
}

assertLimiteParcelasSame(2, Financeiro::MIN_PARCELAS, 'O limite minimo deve permanecer em duas parcelas.');
assertLimiteParcelasSame(120, Financeiro::MAX_PARCELAS, 'O limite maximo deve permitir 120 parcelas.');

$controller = new FinanceiroController();
$validar = new ReflectionMethod($controller, 'validarCamposObrigatorios');
$dadosValidos = [
    'id_conta' => 1,
    'id_forma_pagamento' => 1,
    'id_plano_de_conta' => 1,
    'descricao' => 'Teste de parcelamento',
    'data_criada' => '2026-07-21',
    'id_matriz_filial' => 1,
    'id_cliente' => 1,
    'valor_subtotal' => 1200,
];

assertLimiteParcelasSame(
    null,
    $validar->invoke($controller, $dadosValidos),
    'Lancamento sem parcelamento deve continuar valido.'
);

foreach ([2, 60, 120] as $quantidade) {
    $dados = $dadosValidos;
    $dados['parcelas'] = array_fill(0, $quantidade, ['valor' => 10, 'dataVenci' => '2026-08-01']);

    assertLimiteParcelasSame(
        null,
        $validar->invoke($controller, $dados),
        "Parcelamento com {$quantidade} parcelas deve ser aceito."
    );
}

foreach ([1, 121] as $quantidade) {
    $dados = $dadosValidos;
    $dados['parcelas'] = array_fill(0, $quantidade, ['valor' => 10, 'dataVenci' => '2026-08-01']);
    $erro = $validar->invoke($controller, $dados);

    if (!is_string($erro) || !str_contains($erro, '2') || !str_contains($erro, '120')) {
        throw new RuntimeException("Parcelamento com {$quantidade} parcelas deve ser rejeitado com os limites permitidos.");
    }
}

$dadosInvalidos = $dadosValidos;
$dadosInvalidos['parcelas'] = '120';
assertLimiteParcelasSame(
    'Parcelamento invalido',
    $validar->invoke($controller, $dadosInvalidos),
    'Payload de parcelas que nao seja array deve ser rejeitado.'
);

$view = file_get_contents(APP_ROOT . '/app/Views/pages/financeiro/adicionar.php');
if (
    !str_contains($view, 'Financeiro::MIN_PARCELAS')
    || !str_contains($view, 'Financeiro::MAX_PARCELAS')
    || !str_contains($view, 'Number.isInteger(numParcelas)')
) {
    throw new RuntimeException('A tela deve reutilizar os limites do Model e validar numeros inteiros.');
}

echo "Teste do limite de parcelas do Financeiro passou.\n";
