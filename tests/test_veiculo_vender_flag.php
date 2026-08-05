<?php

/**
 * Regressao: a string "N" nao pode ser tratada como true pelo PHP e voltar a "S".
 *
 * Execute: php tests/test_veiculo_vender_flag.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\VeiculosController;

$controller = new VeiculosController();
$normalizar = new ReflectionMethod(VeiculosController::class, 'normalizarFlagSimNao');
$normalizar->setAccessible(true);

$entradas = [
    ['valor' => 'S', 'esperado' => 'S'],
    ['valor' => 'N', 'esperado' => 'N'],
    ['valor' => '', 'esperado' => 'N'],
    ['valor' => null, 'esperado' => 'N'],
    ['valor' => true, 'esperado' => 'N'],
    ['valor' => 1, 'esperado' => 'N'],
    ['valor' => 's', 'esperado' => 'N'],
    ['valor' => 'V', 'esperado' => 'N'],
];

foreach ($entradas as $caso) {
    $resultado = $normalizar->invoke($controller, $caso['valor']);
    if ($resultado !== $caso['esperado']) {
        fwrite(
            STDERR,
            sprintf(
                "FALHA: entrada %s retornou %s; esperado %s.\n",
                var_export($caso['valor'], true),
                var_export($resultado, true),
                var_export($caso['esperado'], true)
            )
        );
        exit(1);
    }
}

$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/VeiculosController.php');
if ($controllerSource === false
    || substr_count($controllerSource, '$this->normalizarFlagSimNao(') !== 2
    || str_contains($controllerSource, '$dados[\'vender\'] ? \'S\' : \'N\'')) {
    fwrite(STDERR, "FALHA: criacao e edicao devem usar a normalizacao estrita de S/N.\n");
    exit(1);
}

echo "OK: flag vender preserva N ao criar e editar veiculos.\n";
