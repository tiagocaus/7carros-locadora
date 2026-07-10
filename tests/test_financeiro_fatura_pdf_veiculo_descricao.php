<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\FinanceiroController;

function assertFinanceiroVeiculosSame(array $expected, array $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message
            . ' Esperado: ' . json_encode($expected, JSON_UNESCAPED_UNICODE)
            . ' Obtido: ' . json_encode($actual, JSON_UNESCAPED_UNICODE)
        );
    }
}

$controller = (new ReflectionClass(FinanceiroController::class))->newInstanceWithoutConstructor();
$method = new ReflectionMethod(FinanceiroController::class, 'veiculosDescricaoFatura');
$method->setAccessible(true);

$veiculos = $method->invoke($controller, [
    'descricao' => 'Parcela mensal',
]);
assertFinanceiroVeiculosSame([], $veiculos, 'Fatura sem veiculo nao deve criar o bloco de veiculos.');

$veiculos = $method->invoke($controller, [
    'descricao' => 'Parcela mensal',
    'veiculo_placa' => 'ABC1D23',
    'veiculo_marca' => 'Fiat',
    'veiculo_modelo' => 'Mobi',
]);
assertFinanceiroVeiculosSame(
    [['placa' => 'ABC1D23', 'texto' => 'ABC1D23 - Fiat Mobi']],
    $veiculos,
    'Fatura com veiculo direto deve listar placa, marca e modelo.'
);

$veiculos = $method->invoke($controller, [
    'descricao' => 'Parcela mensal',
    'itens' => [
        ['descricao' => 'Diaria', 'veiculo_placa' => 'XYZ9A99', 'veiculo_marca' => 'Volkswagen', 'veiculo_modelo' => 'Polo'],
    ],
]);
assertFinanceiroVeiculosSame(
    [['placa' => 'XYZ9A99', 'texto' => 'XYZ9A99 - Volkswagen Polo']],
    $veiculos,
    'Fatura com veiculo em item deve listar os dados do veiculo.'
);

$veiculos = $method->invoke($controller, [
    'descricao' => 'Parcela mensal',
    'veiculo_placa' => 'ABC1D23',
    'veiculo_marca' => 'Fiat',
    'veiculo_modelo' => 'Mobi',
    'itens' => [
        ['veiculo_placa' => 'ABC-1D23', 'veiculo_marca' => 'Fiat', 'veiculo_modelo' => 'Mobi'],
        ['veiculo_placa' => 'XYZ9A99', 'veiculo_marca' => 'Volkswagen', 'veiculo_modelo' => 'Polo'],
    ],
]);
assertFinanceiroVeiculosSame(
    [
        ['placa' => 'ABC1D23', 'texto' => 'ABC1D23 - Fiat Mobi'],
        ['placa' => 'XYZ9A99', 'texto' => 'XYZ9A99 - Volkswagen Polo'],
    ],
    $veiculos,
    'Fatura com multiplos veiculos deve listar um por linha sem duplicar placas equivalentes.'
);

$veiculos = $method->invoke($controller, [
    'descricao' => 'NF DEVOLUÇÃO checklist-CK095A8CE11834 - Orcamento_3982_11439 PLACA STZ7H39',
    'veiculo_placa' => 'STZ7H39',
    'veiculo_marca' => 'VOLKSWAGEN',
    'veiculo_modelo' => 'SAVEIRO CS RB MPI',
    'itens' => [
        ['veiculo_placa' => 'STZ7H39', 'veiculo_marca' => 'VOLKSWAGEN', 'veiculo_modelo' => 'SAVEIRO CS RB MPI'],
    ],
]);
assertFinanceiroVeiculosSame(
    [['placa' => 'STZ7H39', 'texto' => 'STZ7H39 - VOLKSWAGEN SAVEIRO CS RB MPI']],
    $veiculos,
    'A placa no texto livre nao deve ocultar o bloco estruturado de veiculos da fatura 3958.'
);

echo "Teste de veiculos no PDF financeiro passou.\n";
