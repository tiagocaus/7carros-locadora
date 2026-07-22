<?php

/**
 * Teste: o DRE filtra receitas e despesas pagas, nao pagas ou todas.
 *
 * Execute: php tests/test_relatorio_dre_status.php
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

$chave = 'TEST_DRE_' . strtoupper(bin2hex(random_bytes(8)));
$_SESSION['chave'] = $chave;

$falhas = 0;

function assertDreStatus(string $label, mixed $atual, mixed $esperado): void
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
        'descricao' => 'Teste relatorio DRE',
        'data_criada' => '2099-02-10',
        'data_venci' => '2099-02-10',
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
        'valor_total' => 30,
    ]));
    Database::insertGetId('financeiro', array_merge($base, [
        'tipo' => 'D',
        'pago' => 'N',
        'desconto' => 0,
        'valor_total' => 70,
    ]));

    $model = new FinanceiroReport();
    $pagas = $model->dre('2099-02-01', '2099-02-28', '', [], '', 'S');
    $naoPagas = $model->dre('2099-02-01', '2099-02-28', '', [], '', 'N');
    $todas = $model->dre('2099-02-01', '2099-02-28', '', [], '', 'all');
    $padrao = $model->dre('2099-02-01', '2099-02-28', '', []);

    assertDreStatus('padrao retorna somente pagas', $padrao['totals']['receita_bruta'], 100.0);
    assertDreStatus('pagas: receita bruta', $pagas['totals']['receita_bruta'], 100.0);
    assertDreStatus('pagas: deducoes', $pagas['totals']['deducoes'], 10.0);
    assertDreStatus('pagas: despesas', $pagas['totals']['despesas_operacionais'], 30.0);
    assertDreStatus('pagas: lucro liquido', $pagas['totals']['lucro_liquido'], 60.0);
    assertDreStatus('nao pagas: receita bruta', $naoPagas['totals']['receita_bruta'], 200.0);
    assertDreStatus('nao pagas: despesas', $naoPagas['totals']['despesas_operacionais'], 70.0);
    assertDreStatus('nao pagas: lucro liquido', $naoPagas['totals']['lucro_liquido'], 110.0);
    assertDreStatus('todas: receita bruta', $todas['totals']['receita_bruta'], 300.0);
    assertDreStatus('todas: deducoes', $todas['totals']['deducoes'], 30.0);
    assertDreStatus('todas: despesas', $todas['totals']['despesas_operacionais'], 100.0);
    assertDreStatus('todas: lucro liquido', $todas['totals']['lucro_liquido'], 170.0);
} finally {
    Database::execute('DELETE FROM financeiro WHERE chave = ?', [$chave]);
}

exit($falhas > 0 ? 1 : 0);
