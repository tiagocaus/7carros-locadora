<?php

/**
 * Regressao: baixas financeiras sincronizam o status da multa vinculada.
 *
 * Execute: php tests/test_multas_sincronizacao_financeiro.php
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
use App\Models\Financeiro;

$chave = '1111111111111';
$_SESSION['chave'] = $chave;
$_SESSION['user_id'] = 7;
$_SESSION['user_name'] = 'Teste';

$falhas = 0;
$sucessos = 0;
$multasCriadas = [];
$financeirosAvulsos = [];

function checkSincronizacaoMulta(string $label, bool $ok, mixed $atual = null): void
{
    global $falhas, $sucessos;

    echo '   ' . ($ok ? 'PASS' : 'FAIL') . " {$label}";
    if ($atual !== null) {
        echo " - atual={$atual}";
    }
    echo "\n";

    $ok ? $sucessos++ : $falhas++;
}

function criarMultaSincronizacao(string $chave): int
{
    global $multasCriadas;

    $id = Database::insertGetId('multas', [
        'chave' => $chave,
        'tipo' => 'L',
        'local' => 'Teste automatizado',
        'cidade' => 'Teste',
        'estado' => 'SP',
        'data_hora' => date('Y-m-d H:i:s'),
        'data_vencimento' => date('Y-m-d'),
        'valor' => 100.00,
        'pago' => 'N',
        'pagador' => 'cliente',
        'descri' => 'Teste de sincronizacao financeira',
        'orgao_autuador' => 'Teste',
        'n_infracao' => substr(strtoupper(bin2hex(random_bytes(5))), 0, 10),
    ]);
    $multasCriadas[] = $id;

    return $id;
}

function criarFinanceiroSincronizacao(string $chave, ?int $idMulta, float $valor = 100.00): int
{
    $id = Database::insertGetId('financeiro', [
        'chave' => $chave,
        'id_multa' => $idMulta,
        'tipo' => 'R',
        'pago' => 'N',
        'parcela' => 0,
        'total_parcelas' => 0,
        'descricao' => 'Teste de sincronizacao de multa',
        'data_criada' => date('Y-m-d'),
        'data_venci' => date('Y-m-d'),
        'valor_subtotal' => $valor,
        'valor_total' => $valor,
    ]);

    if ($idMulta !== null) {
        Database::execute(
            'UPDATE multas SET id_financeiro = COALESCE(id_financeiro, ?) WHERE id = ? AND chave = ?',
            [$id, $idMulta, $chave]
        );
    }

    return $id;
}

function statusMultaSincronizacao(int $idMulta, string $chave): string
{
    return (string) Database::fetchColumn(
        'SELECT pago FROM multas WHERE id = ? AND chave = ?',
        [$idMulta, $chave]
    );
}

echo "=== Teste sincronizacao multa x financeiro ===\n";

try {
    $financeiroModel = new Financeiro();

    $multaIndividual = criarMultaSincronizacao($chave);
    $financeiroIndividual = criarFinanceiroSincronizacao($chave, $multaIndividual);
    $financeiroModel->atualizar($financeiroIndividual, ['pago' => 'S']);
    checkSincronizacaoMulta(
        'baixa individual marca multa como paga',
        statusMultaSincronizacao($multaIndividual, $chave) === 'S',
        statusMultaSincronizacao($multaIndividual, $chave)
    );

    $financeiroModel->atualizar($financeiroIndividual, ['pago' => 'N']);
    checkSincronizacaoMulta(
        'estorno individual marca multa como nao paga',
        statusMultaSincronizacao($multaIndividual, $chave) === 'N',
        statusMultaSincronizacao($multaIndividual, $chave)
    );

    $multaLoteA = criarMultaSincronizacao($chave);
    $multaLoteB = criarMultaSincronizacao($chave);
    $financeiroLoteA = criarFinanceiroSincronizacao($chave, $multaLoteA);
    $financeiroLoteB = criarFinanceiroSincronizacao($chave, $multaLoteB);
    $financeiroModel->atualizarParcelasLote([$financeiroLoteA, $financeiroLoteB], ['pago' => 'S'], $chave);
    checkSincronizacaoMulta(
        'baixa em lote sincroniza todas as multas',
        statusMultaSincronizacao($multaLoteA, $chave) === 'S'
            && statusMultaSincronizacao($multaLoteB, $chave) === 'S'
    );

    $financeiroModel->atualizarParcelasLote([$financeiroLoteA], ['pago' => 'N'], $chave);
    checkSincronizacaoMulta(
        'estorno em lote afeta somente a multa vinculada',
        statusMultaSincronizacao($multaLoteA, $chave) === 'N'
            && statusMultaSincronizacao($multaLoteB, $chave) === 'S'
    );

    $multaParcial = criarMultaSincronizacao($chave);
    $financeiroParcial = criarFinanceiroSincronizacao($chave, $multaParcial);
    $resultadoParcial = $financeiroModel->baixarParcial(
        $financeiroParcial,
        40.00,
        date('Y-m-d'),
        date('Y-m-d', strtotime('+7 days')),
        $chave
    );
    checkSincronizacaoMulta(
        'baixa parcial mantem multa pendente enquanto houver diferenca',
        statusMultaSincronizacao($multaParcial, $chave) === 'N',
        statusMultaSincronizacao($multaParcial, $chave)
    );

    $financeiroModel->atualizar((int) $resultadoParcial['id_diferenca'], ['pago' => 'S']);
    checkSincronizacaoMulta(
        'pagamento da diferenca quita a multa',
        statusMultaSincronizacao($multaParcial, $chave) === 'S',
        statusMultaSincronizacao($multaParcial, $chave)
    );

    $financeiroSemMulta = criarFinanceiroSincronizacao($chave, null);
    $financeirosAvulsos[] = $financeiroSemMulta;
    $financeiroModel->atualizar($financeiroSemMulta, ['pago' => 'S']);
    $pagoSemMulta = Database::fetchColumn(
        'SELECT pago FROM financeiro WHERE id = ? AND chave = ?',
        [$financeiroSemMulta, $chave]
    );
    checkSincronizacaoMulta('financeiro sem multa continua sendo atualizado', $pagoSemMulta === 'S', $pagoSemMulta);
} catch (Throwable $e) {
    echo 'ERRO: ' . $e->getMessage() . "\n";
    $falhas++;
} finally {
    foreach ($multasCriadas as $idMulta) {
        Database::execute('DELETE FROM financeiro WHERE id_multa = ? AND chave = ?', [$idMulta, $chave]);
        Database::execute('DELETE FROM multas WHERE id = ? AND chave = ?', [$idMulta, $chave]);
    }
    foreach ($financeirosAvulsos as $idFinanceiro) {
        Database::execute('DELETE FROM financeiro WHERE id = ? AND chave = ?', [$idFinanceiro, $chave]);
    }
}

echo "\nSucessos: {$sucessos}\n";
echo "Falhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
