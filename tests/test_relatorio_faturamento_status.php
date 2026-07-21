<?php

/**
 * Teste: o relatorio Faturamento filtra receitas pagas, nao pagas ou todas.
 *
 * Execute: php tests/test_relatorio_faturamento_status.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/Helpers/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

use App\Core\Database;
use App\Models\Relatorios\FinanceiroReport;

$chave = 'TEST_FAT_' . strtoupper(bin2hex(random_bytes(8)));
$_SESSION['chave'] = $chave;

$falhas = 0;

function assertFaturamentoStatus(string $label, mixed $atual, mixed $esperado): void
{
    global $falhas;

    if ($atual !== $esperado) {
        $falhas++;
        echo "FAIL: {$label} - esperado=" . var_export($esperado, true)
            . ', atual=' . var_export($atual, true) . "\n";
        return;
    }

    echo "PASS: {$label}\n";
}

try {
    $base = [
        'chave' => $chave,
        'parcela' => 1,
        'total_parcelas' => 1,
        'descricao' => 'Teste relatorio faturamento',
        'data_criada' => '2099-01-10',
        'data_venci' => '2099-01-10',
    ];

    Database::insertGetId('financeiro', array_merge($base, [
        'tipo' => 'R',
        'pago' => 'S',
        'desconto' => 10,
        'valor_total' => 100,
    ]));
    Database::insertGetId('financeiro', array_merge($base, [
        'tipo' => 'R',
        'pago' => 'N',
        'desconto' => 20,
        'valor_total' => 200,
    ]));
    Database::insertGetId('financeiro', array_merge($base, [
        'tipo' => 'D',
        'pago' => 'S',
        'desconto' => 0,
        'valor_total' => 999,
    ]));

    $model = new FinanceiroReport();
    $pagas = $model->faturamento('2099-01-01', '2099-01-31', '', [], '', '', 'S');
    $naoPagas = $model->faturamento('2099-01-01', '2099-01-31', '', [], '', '', 'N');
    $todas = $model->faturamento('2099-01-01', '2099-01-31', '', [], '', '', 'all');
    $padrao = $model->faturamento('2099-01-01', '2099-01-31', '', []);

    assertFaturamentoStatus('padrao retorna somente pagas', $padrao['totals']['faturamento_bruto'], 100.0);
    assertFaturamentoStatus('pagas: valor bruto', $pagas['totals']['faturamento_bruto'], 100.0);
    assertFaturamentoStatus('pagas: quantidade', $pagas['totals']['total_lancamentos'], 1);
    assertFaturamentoStatus('nao pagas: valor bruto', $naoPagas['totals']['faturamento_bruto'], 200.0);
    assertFaturamentoStatus('nao pagas: quantidade', $naoPagas['totals']['total_lancamentos'], 1);
    assertFaturamentoStatus('nao pagas: agrupamento por origem', $naoPagas['details']['por_origem'][0]['valor'], 200.0);
    assertFaturamentoStatus('nao pagas: agrupamento por pagamento', $naoPagas['details']['por_forma_pagamento'][0]['valor'], 200.0);
    assertFaturamentoStatus('todas: valor bruto', $todas['totals']['faturamento_bruto'], 300.0);
    assertFaturamentoStatus('todas: descontos', $todas['totals']['descontos'], 30.0);
    assertFaturamentoStatus('todas: quantidade', $todas['totals']['total_lancamentos'], 2);
    assertFaturamentoStatus('todas: grafico coerente', $todas['chart']['datasets'][0]['data'], [300.0]);
} finally {
    Database::execute('DELETE FROM financeiro WHERE chave = ?', [$chave]);
}

exit($falhas > 0 ? 1 : 0);
