<?php

/**
 * Regressao: locacao gratuita pode ser fechada sem criar parcela de valor zero.
 *
 * Execute: php tests/test_locacao_fechamento_valor_zero.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\LocacoesController;

$metodoExigirParcelas = new ReflectionMethod(LocacoesController::class, 'deveExigirParcelasFinanceiras');
$metodoExigirParcelas->setAccessible(true);

$metodoDiferenca = new ReflectionMethod(LocacoesController::class, 'calcularDiferencaFinanceiraFechamento');
$metodoDiferenca->setAccessible(true);

$falhas = 0;
$sucessos = 0;

$check = static function (string $descricao, bool $condicao) use (&$falhas, &$sucessos): void {
    echo '   ' . ($condicao ? 'PASS' : 'FAIL') . " {$descricao}\n";
    $condicao ? $sucessos++ : $falhas++;
};

$exigeParcelas = static fn(float $totalEsperado, int $totalParcelas): bool =>
    (bool) $metodoExigirParcelas->invoke(null, $totalEsperado, $totalParcelas);

$calcularDiferenca = static fn(float $totalPagar, float $totalAvarias, float $totalLancado): float =>
    (float) $metodoDiferenca->invoke(null, $totalPagar, [
        'total_avarias' => $totalAvarias,
        'total_lancado' => $totalLancado,
    ]);

echo "=== Teste fechamento de locacao com valor zero ===\n";

$check(
    'locacao gratuita sem parcelas nao exige lancamento financeiro',
    !$exigeParcelas(0.00, 0)
);
$check(
    'taxa de limpeza sem parcelas exige lancamento financeiro',
    $exigeParcelas(50.00, 0)
);
$check(
    'taxa de limpeza com parcela registrada nao exige nova parcela',
    !$exigeParcelas(50.00, 1)
);
$check(
    'locacao gratuita conciliada permanece sem diferenca',
    abs($calcularDiferenca(0.00, 0.00, 0.00)) < 0.001
);
$check(
    'limpeza nao lancada produz diferenca positiva',
    abs($calcularDiferenca(0.00, 50.00, 0.00) - 50.00) < 0.001
);
$check(
    'limpeza lancada deixa a diferenca zerada',
    abs($calcularDiferenca(0.00, 50.00, 50.00)) < 0.001
);
$check(
    'credito sem receita em locacao gratuita permanece bloqueado por diferenca',
    $calcularDiferenca(0.00, 0.00, -10.00) > 0.009
);

echo "\nSucessos: {$sucessos}\n";
echo "Falhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
