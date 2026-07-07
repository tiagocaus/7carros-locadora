<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\FinanceiroController;

function assertFinanceiroDescricaoSame(string $expected, string $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . " Esperado: {$expected} Obtido: {$actual}");
    }
}

$controller = (new ReflectionClass(FinanceiroController::class))->newInstanceWithoutConstructor();
$method = new ReflectionMethod(FinanceiroController::class, 'montarDescricaoFaturaComVeiculos');
$method->setAccessible(true);

$descricao = $method->invoke($controller, [
    'descricao' => 'Parcela mensal',
]);
assertFinanceiroDescricaoSame('Parcela mensal', $descricao, 'Fatura sem veiculo deve manter descricao original.');

$descricao = $method->invoke($controller, [
    'descricao' => 'Parcela mensal',
    'veiculo_placa' => 'ABC1D23',
    'veiculo_marca' => 'Fiat',
    'veiculo_modelo' => 'Mobi',
]);
assertFinanceiroDescricaoSame(
    'Parcela mensal - Veículo: ABC1D23 - Fiat Mobi',
    $descricao,
    'Fatura com veiculo direto deve incluir placa, marca e modelo.'
);

$descricao = $method->invoke($controller, [
    'descricao' => 'Parcela mensal',
    'itens' => [
        ['descricao' => 'Diaria', 'veiculo_placa' => 'XYZ9A99', 'veiculo_marca' => 'Volkswagen', 'veiculo_modelo' => 'Polo'],
    ],
]);
assertFinanceiroDescricaoSame(
    'Parcela mensal - Veículo: XYZ9A99 - Volkswagen Polo',
    $descricao,
    'Fatura com veiculo em item deve incluir dados do veiculo.'
);

$descricao = $method->invoke($controller, [
    'descricao' => 'Parcela mensal',
    'itens' => [
        ['veiculo_placa' => 'ABC1D23', 'veiculo_marca' => 'Fiat', 'veiculo_modelo' => 'Mobi'],
        ['veiculo_placa' => 'ABC-1D23', 'veiculo_marca' => 'Fiat', 'veiculo_modelo' => 'Mobi'],
        ['veiculo_placa' => 'XYZ9A99', 'veiculo_marca' => 'Volkswagen', 'veiculo_modelo' => 'Polo'],
    ],
]);
assertFinanceiroDescricaoSame(
    'Parcela mensal - Veículos: ABC1D23 - Fiat Mobi; XYZ9A99 - Volkswagen Polo',
    $descricao,
    'Fatura com multiplos veiculos deve listar todos sem duplicar placas equivalentes.'
);

$descricao = $method->invoke($controller, [
    'descricao' => 'Parcela mensal ABC1D23',
    'veiculo_placa' => 'ABC-1D23',
    'veiculo_marca' => 'Fiat',
    'veiculo_modelo' => 'Mobi',
]);
assertFinanceiroDescricaoSame(
    'Parcela mensal ABC1D23',
    $descricao,
    'Descricao que ja possui a placa nao deve duplicar o veiculo.'
);

echo "Teste de descricao de veiculo no PDF financeiro passou.\n";
