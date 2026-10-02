#!/usr/bin/env php
<?php

/** Regressao: o nome livre da forma nao pode ocultar metodos habilitados no gateway. */
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/vendor/autoload.php';
require APP_ROOT . '/app/Helpers/helpers.php';

use App\Controllers\PagamentoPublicoController;

function checkPagamentoPublicoMetodo(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$controller = new PagamentoPublicoController();
$permiteMetodo = new ReflectionMethod($controller, 'gatewayPermiteMetodoPorForma');

$cora = [
    'gateway_code' => 'cora',
    'nome' => 'Cora',
    'forma_pagamento_nome' => 'Boleto',
    'pix_enabled' => 1,
    'boleto_enabled' => 1,
    'credit_card_enabled' => 0,
    'debit_card_enabled' => 0,
];

checkPagamentoPublicoMetodo(
    $permiteMetodo->invoke($controller, $cora, 'pix'),
    'Forma chamada Boleto ocultou o Pix habilitado na Cora.'
);
checkPagamentoPublicoMetodo(
    $permiteMetodo->invoke($controller, $cora, 'boleto'),
    'Boleto habilitado na Cora deveria estar disponivel.'
);

$cora['pix_enabled'] = 0;
checkPagamentoPublicoMetodo(
    !$permiteMetodo->invoke($controller, $cora, 'pix'),
    'Pix desabilitado no gateway nao pode ser oferecido.'
);

$cora['pix_enabled'] = 1;
$cora['gateway_code'] = 'stripe';
checkPagamentoPublicoMetodo(
    !$permiteMetodo->invoke($controller, $cora, 'pix'),
    'Gateway sem suporte a Pix nao pode oferecer o metodo mesmo com a flag ativa.'
);

$modelSource = file_get_contents(APP_ROOT . '/app/Models/FormaPagamento.php');
checkPagamentoPublicoMetodo(
    $modelSource !== false && !str_contains($modelSource, 'inferirMetodosDaForma'),
    'A inferencia de metodo pelo nome da forma ainda existe.'
);
checkPagamentoPublicoMetodo(
    $modelSource !== false && !str_contains($modelSource, 'withoutChave()'),
    'FormaPagamento ainda desabilita o escopo de tenant em CRUD normal.'
);

echo "[OK] Metodos publicos seguem capacidades e flags do gateway, sem inferencia pelo nome da forma.\n";
