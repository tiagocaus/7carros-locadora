<?php

/**
 * Regressao: avarias cobradas compoem o total esperado no fechamento da locacao.
 *
 * Execute: php tests/test_locacao_fechamento_avarias.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\LocacoesController;

$metodo = new ReflectionMethod(LocacoesController::class, 'calcularDiferencaFinanceiraFechamento');
$metodo->setAccessible(true);

$falhas = 0;
$sucessos = 0;

$check = static function (string $descricao, float $esperado, float $atual) use (&$falhas, &$sucessos): void {
    $ok = abs($esperado - $atual) < 0.001;
    echo '   ' . ($ok ? 'PASS' : 'FAIL') . " {$descricao} - esperado={$esperado}, atual={$atual}\n";
    $ok ? $sucessos++ : $falhas++;
};

$calcular = static fn(float $totalPagar, float $totalAvarias, float $totalLancado): float =>
    (float) $metodo->invoke(null, $totalPagar, [
        'total_avarias' => $totalAvarias,
        'total_lancado' => $totalLancado,
    ]);

echo "=== Teste fechamento de locacao com avarias ===\n";

$check(
    'avaria ja lancada nao gera credito de devolucao',
    0.00,
    $calcular(9644.68, 183.00, 9827.68)
);
$check(
    'excesso financeiro real permanece negativo para gerar credito',
    -72.32,
    $calcular(9644.68, 183.00, 9900.00)
);
$check(
    'valor ainda nao lancado permanece positivo para bloquear fechamento',
    27.68,
    $calcular(9644.68, 183.00, 9800.00)
);
$check(
    'locacao sem avaria preserva conciliacao anterior',
    0.00,
    $calcular(9644.68, 0.00, 9644.68)
);

echo "\nSucessos: {$sucessos}\n";
echo "Falhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
