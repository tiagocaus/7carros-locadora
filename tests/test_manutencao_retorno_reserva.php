<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Controllers\ManutencoesController;

function checkManutencaoRetornoReserva(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$controller = new ManutencoesController();
$reflection = new ReflectionClass($controller);
$camposRetornoPreenchidos = $reflection->getMethod('camposRetornoPreenchidos');

$retornoValido = [
    'data_retorno' => '2026-07-14 14:06:00',
    'odo_retorno' => 413211,
    'tanque_retorno' => 8,
];

checkManutencaoRetornoReserva(
    $camposRetornoPreenchidos->invoke($controller, $retornoValido),
    'Retorno completo deve ser aceito.'
);

foreach ([0, '0'] as $reserva) {
    $dados = $retornoValido;
    $dados['tanque_retorno'] = $reserva;

    checkManutencaoRetornoReserva(
        $camposRetornoPreenchidos->invoke($controller, $dados),
        'Tanque na Reserva deve ser aceito como preenchido.'
    );
}

foreach ([1, '1', 8, '8'] as $nivel) {
    $dados = $retornoValido;
    $dados['tanque_retorno'] = $nivel;

    checkManutencaoRetornoReserva(
        $camposRetornoPreenchidos->invoke($controller, $dados),
        "Nivel de tanque {$nivel} deve continuar aceito."
    );
}

foreach ([null, ''] as $tanqueVazio) {
    $dados = $retornoValido;
    $dados['tanque_retorno'] = $tanqueVazio;

    checkManutencaoRetornoReserva(
        !$camposRetornoPreenchidos->invoke($controller, $dados),
        'Tanque ausente deve ser rejeitado.'
    );
}

$dadosSemTanque = $retornoValido;
unset($dadosSemTanque['tanque_retorno']);
checkManutencaoRetornoReserva(
    !$camposRetornoPreenchidos->invoke($controller, $dadosSemTanque),
    'Tanque nao enviado deve ser rejeitado.'
);

foreach ([null, '', '   '] as $dataVazia) {
    $dados = $retornoValido;
    $dados['data_retorno'] = $dataVazia;

    checkManutencaoRetornoReserva(
        !$camposRetornoPreenchidos->invoke($controller, $dados),
        'Data de retorno vazia deve ser rejeitada.'
    );
}

foreach ([null, '', 0, '0', -1, 'abc'] as $odometroInvalido) {
    $dados = $retornoValido;
    $dados['odo_retorno'] = $odometroInvalido;

    checkManutencaoRetornoReserva(
        !$camposRetornoPreenchidos->invoke($controller, $dados),
        'Odometro de retorno invalido deve ser rejeitado.'
    );
}

echo "OK: retorno de manutencao aceita tanque na Reserva.\n";
