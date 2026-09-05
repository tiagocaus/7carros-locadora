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
    $planoCusto = Database::insertGetId('planos_de_contas', [
        'chave' => $chave,
        'hierarquia' => '3.3.' . random_int(100000, 999999),
        'descricao_i18n' => json_encode(['pt_BR' => 'Custo operacional de teste'], JSON_UNESCAPED_UNICODE),
        'tipo' => 'D',
    ]);

    $planoPassivo = Database::insertGetId('planos_de_contas', [
        'chave' => $chave,
        'hierarquia' => '2.99.' . random_int(100000, 999999),
        'descricao_i18n' => json_encode(['pt_BR' => 'Passivo de teste'], JSON_UNESCAPED_UNICODE),
        'tipo' => 'D',
    ]);

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
        'id_plano_de_conta' => $planoCusto,
    ]));
    Database::insertGetId('financeiro', array_merge($base, [
        'tipo' => 'D',
        'pago' => 'N',
        'desconto' => 0,
        'valor_total' => 70,
        'id_plano_de_conta' => $planoCusto,
    ]));
    Database::insertGetId('financeiro', array_merge($base, [
        'tipo' => 'D',
        'pago' => 'S',
        'desconto' => 0,
        'valor_total' => 5,
        'id_plano_de_conta' => $planoPassivo,
    ]));

    $model = new FinanceiroReport();
    $pagas = $model->dre('2099-02-01', '2099-02-28', '', [], '', 'S');
    $naoPagas = $model->dre('2099-02-01', '2099-02-28', '', [], '', 'N');
    $todas = $model->dre('2099-02-01', '2099-02-28', '', [], '', 'all');
    $padrao = $model->dre('2099-02-01', '2099-02-28', '', []);

    assertDreStatus('padrao retorna somente pagas', $padrao['totals']['receita_bruta'], 100.0);
    assertDreStatus('pagas: receita bruta', $pagas['totals']['receita_bruta'], 100.0);
    assertDreStatus('pagas: deducoes', $pagas['totals']['deducoes'], 10.0);
    assertDreStatus('pagas: custos', $pagas['totals']['custos_operacionais'], 30.0);
    assertDreStatus('passivo nao e tratado como custo', $pagas['totals']['despesas_operacionais'], 5.0);
    assertDreStatus('pagas: lucro liquido', $pagas['totals']['lucro_liquido'], 55.0);
    assertDreStatus('nao pagas: receita bruta', $naoPagas['totals']['receita_bruta'], 200.0);
    assertDreStatus('nao pagas: custos', $naoPagas['totals']['custos_operacionais'], 70.0);
    assertDreStatus('nao pagas: despesas', $naoPagas['totals']['despesas_operacionais'], 0);
    assertDreStatus('nao pagas: lucro liquido', $naoPagas['totals']['lucro_liquido'], 110.0);
    assertDreStatus('todas: receita bruta', $todas['totals']['receita_bruta'], 300.0);
    assertDreStatus('todas: deducoes', $todas['totals']['deducoes'], 30.0);
    assertDreStatus('todas: custos', $todas['totals']['custos_operacionais'], 100.0);
    assertDreStatus('todas: despesas', $todas['totals']['despesas_operacionais'], 5.0);
    assertDreStatus('todas: lucro liquido', $todas['totals']['lucro_liquido'], 165.0);
} finally {
    Database::execute('DELETE FROM financeiro WHERE chave = ?', [$chave]);
    Database::execute('DELETE FROM planos_de_contas WHERE chave = ?', [$chave]);
}

exit($falhas > 0 ? 1 : 0);
