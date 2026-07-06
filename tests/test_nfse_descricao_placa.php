<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\NFSe\NFSeService;

function assertDescricaoPlacaSame(string $expected, string $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . " Esperado: {$expected} Obtido: {$actual}");
    }
}

$service = (new ReflectionClass(NFSeService::class))->newInstanceWithoutConstructor();
$method = new ReflectionMethod(NFSeService::class, 'montarDescricaoServicoComPlacas');
$method->setAccessible(true);

$descricao = $method->invoke($service, 'Locação de veículo automotor sem condutor.', ['ABC1D23']);
assertDescricaoPlacaSame(
    'Locação de veículo automotor sem condutor. Placa: ABC1D23',
    $descricao,
    'Descricao deve receber placa unica.'
);

$descricao = $method->invoke($service, 'Locação de veículo ABC-1234', ['ABC1234']);
assertDescricaoPlacaSame(
    'Locação de veículo ABC-1234',
    $descricao,
    'Descricao nao deve duplicar placa ja informada com pontuacao diferente.'
);

$descricao = $method->invoke($service, 'Locação mensal', ['ABC1D23', 'XYZ9A99', 'abc1d23']);
assertDescricaoPlacaSame(
    'Locação mensal Placas: ABC1D23, XYZ9A99',
    $descricao,
    'Descricao deve receber placas unicas em contrato com multiplos veiculos.'
);

$descricao = $method->invoke($service, 'Locação mensal', ['', null]);
assertDescricaoPlacaSame(
    'Locação mensal',
    $descricao,
    'Descricao sem placa valida deve permanecer inalterada.'
);

echo "Teste de descricao com placa na NFS-e passou.\n";
