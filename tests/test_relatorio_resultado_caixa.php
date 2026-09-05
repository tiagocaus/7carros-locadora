<?php

/**
 * Teste: resultado gerencial considera exclusivamente a data efetiva de caixa.
 *
 * Execute: php tests/test_relatorio_resultado_caixa.php
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

$chave = 'TEST_CAIXA_' . strtoupper(bin2hex(random_bytes(8)));
$outraChave = $chave . '_OUTRO';
$hierarquiaCusto = '3.1.' . random_int(100000, 999999);
$_SESSION['chave'] = $chave;
$falhas = 0;

function assertResultadoCaixa(string $label, mixed $atual, mixed $esperado): void
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
        'hierarquia' => $hierarquiaCusto,
        'descricao_i18n' => json_encode(['pt_BR' => 'Custo de teste'], JSON_UNESCAPED_UNICODE),
        'tipo' => 'D',
    ]);

    $base = [
        'chave' => $chave,
        'parcela' => 1,
        'total_parcelas' => 12,
        'descricao' => 'Teste resultado por caixa',
        'data_venci' => '2098-08-10',
    ];

    Database::insertGetId('financeiro', array_merge($base, [
        'tipo' => 'R', 'pago' => 'S', 'data_criada' => '2098-07-01',
        'data_pago' => '2098-08-10', 'desconto' => 10, 'valor_total' => 100,
    ]));
    Database::insertGetId('financeiro', array_merge($base, [
        'tipo' => 'R', 'pago' => 'S', 'data_criada' => '2098-08-01',
        'data_pago' => '2098-09-10', 'desconto' => 0, 'valor_total' => 200,
    ]));
    Database::insertGetId('financeiro', array_merge($base, [
        'tipo' => 'R', 'pago' => 'N', 'data_criada' => '2098-08-01',
        'data_pago' => null, 'desconto' => 0, 'valor_total' => 300,
    ]));
    Database::insertGetId('financeiro', array_merge($base, [
        'tipo' => 'D', 'pago' => 'S', 'data_criada' => '2098-07-01',
        'data_pago' => '2098-08-15', 'desconto' => 0, 'valor_total' => 30,
        'id_plano_de_conta' => $planoCusto,
    ]));
    Database::insertGetId('financeiro', array_merge($base, [
        'tipo' => 'D', 'pago' => 'S', 'data_criada' => '2098-08-01',
        'data_pago' => '2098-08-20', 'desconto' => 0, 'valor_total' => 20,
    ]));
    Database::insertGetId('financeiro', array_merge($base, [
        'tipo' => 'R', 'pago' => 'S', 'data_criada' => '2098-08-01',
        'data_pago' => null, 'desconto' => 0, 'valor_total' => 40,
    ]));
    Database::insertGetId('financeiro', array_merge($base, [
        'chave' => $outraChave, 'tipo' => 'R', 'pago' => 'S',
        'data_criada' => '2098-08-01', 'data_pago' => '2098-08-10',
        'desconto' => 0, 'valor_total' => 999,
    ]));

    $resultado = (new FinanceiroReport())->resultadoCaixa(
        '2098-08-01',
        '2098-08-31',
        '',
        []
    );

    assertResultadoCaixa('receita bruta recompõe desconto', $resultado['totals']['receita_bruta'], 110.0);
    assertResultadoCaixa('deduções', $resultado['totals']['deducoes'], 10.0);
    assertResultadoCaixa('receita líquida recebida', $resultado['totals']['receita_liquida'], 100.0);
    assertResultadoCaixa('custos pagos', $resultado['totals']['custos_operacionais'], 30.0);
    assertResultadoCaixa('despesas pagas', $resultado['totals']['despesas_operacionais'], 20.0);
    assertResultadoCaixa('resultado líquido', $resultado['totals']['lucro_liquido'], 50.0);
    assertResultadoCaixa('pago sem data é sinalizado', $resultado['totals']['sem_data_quantidade'], 1);
    assertResultadoCaixa('valor sem data é sinalizado', $resultado['totals']['sem_data_receitas'], 40.0);
} finally {
    Database::execute('DELETE FROM financeiro WHERE chave IN (?, ?)', [$chave, $outraChave]);
    Database::execute('DELETE FROM planos_de_contas WHERE chave = ?', [$chave]);
}

exit($falhas > 0 ? 1 : 0);
