<?php

/**
 * Regressao estrutural da persistencia e do saneamento de valor_total das taxas.
 *
 * Execute: php tests/test_locacao_taxas_valor_total.php
 */

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/LocacoesController.php');
$migration = file_get_contents($root . '/app/Database/migrations/00436_repair_locacoes_taxas_valor_total.php');

function assertTaxaValorTotal(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

assertTaxaValorTotal(
    substr_count($controller, 'sincronizarTotaisResumo($id, $dadosTotais, false)') === 2,
    'Criacao e reaplicacao de promocao devem recalcular as taxas persistidas.'
);
assertTaxaValorTotal(
    str_contains($migration, "->whereIn('l.status', ['P', 'R', 'A'])")
        && str_contains($migration, "private const FLUXO_AFETADO_DESDE = '2026-06-19 00:00:00'"),
    'Migration deve limitar o reparo a locacoes nao finalizadas do fluxo afetado.'
);
assertTaxaValorTotal(
    str_contains($migration, 'ABS(lts.valor_total - (lts.valor_unitario * lts.quantidade)) < 0.009'),
    'Migration deve exigir a assinatura do total persistido incorretamente.'
);
assertTaxaValorTotal(
    str_contains($migration, '$baseCalculo === \'PER\'')
        && str_contains($migration, '$valorTotalVeiculo / $dias')
        && str_contains($migration, '$valorTotalVeiculo'),
    'Migration deve contemplar formulas por periodo e sobre o valor total.'
);
assertTaxaValorTotal(
    str_contains($migration, 'Correcao de dados intencionalmente irreversivel'),
    'Rollback deve documentar por que os valores incorretos nao sao restaurados.'
);

echo "OK: persistencia e migration de valor_total das taxas validadas.\n";
