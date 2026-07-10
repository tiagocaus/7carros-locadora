<?php

/**
 * Regressao: multas vinculadas nao reduzem o saldo parcelavel da locacao.
 *
 * Execute: php tests/test_locacao_parcelas_ignoram_multas.php
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
use App\Models\Locacao;

$chave = '1111111111111';
$_SESSION['chave'] = $chave;
$_SESSION['user_id'] = 7;
$_SESSION['user_name'] = 'Teste';

$falhas = 0;
$sucessos = 0;
$locacoesCriadas = [];
$locacoesVeiculosCriadas = [];

function checkLocacaoMulta(string $label, bool $ok, mixed $atual = null): void
{
    global $falhas, $sucessos;

    echo '   ' . ($ok ? 'PASS' : 'FAIL') . " {$label}";
    if ($atual !== null) {
        echo " - atual={$atual}";
    }
    echo "\n";

    $ok ? $sucessos++ : $falhas++;
}

function criarLocacaoTesteMulta(string $chave, float $total): int
{
    global $locacoesCriadas;

    $codigo = 'LTM' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
    $id = Database::insertGetId('locacoes', [
        'codigo' => $codigo,
        'chave' => $chave,
        'status' => 'A',
        'data_saida' => date('Y-m-d H:i:s'),
        'data_prevista' => date('Y-m-d H:i:s', strtotime('+2 days')),
        'dias' => 2,
        'cliente_nome' => 'Cliente teste multa',
        'total_fatura' => $total,
        'total_pagar' => $total,
    ]);
    $locacoesCriadas[] = $id;

    return $id;
}

function criarFinanceiroTesteMulta(
    string $chave,
    int $locacaoId,
    float $valor,
    ?int $multaId,
    int $parcela = 0,
    int $totalParcelas = 0
): int {
    return Database::insertGetId('financeiro', [
        'chave' => $chave,
        'id_locacao' => $locacaoId,
        'id_multa' => $multaId,
        'tipo' => 'R',
        'pago' => 'N',
        'parcela' => $parcela,
        'total_parcelas' => $totalParcelas,
        'descricao' => $multaId ? 'Multa teste' : 'Parcela teste',
        'data_criada' => date('Y-m-d'),
        'data_venci' => date('Y-m-d'),
        'valor_subtotal' => $valor,
        'valor_total' => $valor,
    ]);
}

function criarVeiculoLocacaoTeste(string $chave, int $locacaoId): void
{
    global $locacoesVeiculosCriadas;

    $idVeiculo = (int) Database::fetchColumn(
        'SELECT id FROM veiculos WHERE chave = ? ORDER BY id LIMIT 1',
        [$chave]
    );
    if ($idVeiculo <= 0) {
        throw new RuntimeException('Veiculo de teste nao encontrado');
    }

    $id = Database::insertGetId('locacoes_veiculos', [
        'chave' => $chave,
        'id_locacao' => $locacaoId,
        'id_veiculo' => $idVeiculo,
        'plano' => 'KMC',
        'valor_plano_km_controlado' => 150.00,
        'km_franquia' => 100,
        'valor_km_excedente' => 0.10,
        'odometro_saida' => 100,
        'combustivel_saida' => 8,
        'data_saida' => date('Y-m-d H:i:s'),
    ]);
    $locacoesVeiculosCriadas[] = $id;
}

echo "=== Teste parcelas de locacao ignoram multas ===\n";

try {
    $model = new Locacao();

    $locacaoSemParcela = criarLocacaoTesteMulta($chave, 300.00);
    $multaFinanceiro = criarFinanceiroTesteMulta($chave, $locacaoSemParcela, 100.00, 999999, 7, 9);
    $idsGerados = $model->gerarParcelas($locacaoSemParcela, [
        'quantidade' => 1,
        'total_pagar_final' => 300.00,
        'data_primeiro_vencimento' => date('Y-m-d'),
    ], $chave);

    $parcelaGerada = Database::fetchOne(
        'SELECT valor_total, parcela, total_parcelas FROM financeiro WHERE id = ? AND chave = ?',
        [$idsGerados[0], $chave]
    );
    checkLocacaoMulta('multa nao reduz parcela de R$ 300,00', (float) $parcelaGerada['valor_total'] === 300.0, $parcelaGerada['valor_total']);
    checkLocacaoMulta('numeracao inicia em 1 ignorando multa', (int) $parcelaGerada['parcela'] === 1, $parcelaGerada['parcela']);

    $multaAposGeracao = Database::fetchOne(
        'SELECT parcela, total_parcelas FROM financeiro WHERE id = ? AND chave = ?',
        [$multaFinanceiro, $chave]
    );
    checkLocacaoMulta('metadados da multa permanecem inalterados', (int) $multaAposGeracao['parcela'] === 7 && (int) $multaAposGeracao['total_parcelas'] === 9);

    $resumo = $model->resumoFinanceiro($locacaoSemParcela);
    checkLocacaoMulta('diferenca fica zerada', abs((float) $resumo['diferenca']) < 0.001, $resumo['diferenca']);

    $semSaldo = false;
    try {
        $model->gerarParcelas($locacaoSemParcela, [
            'quantidade' => 1,
            'total_pagar_final' => 300.00,
        ], $chave);
    } catch (InvalidArgumentException $e) {
        $semSaldo = str_contains($e->getMessage(), 'saldo restante');
    }
    checkLocacaoMulta('nova geracao sem saldo e bloqueada', $semSaldo);

    $locacaoComParcela = criarLocacaoTesteMulta($chave, 300.00);
    criarFinanceiroTesteMulta($chave, $locacaoComParcela, 100.00, 999998);
    criarFinanceiroTesteMulta($chave, $locacaoComParcela, 100.00, null, 1, 1);
    $complementares = $model->gerarParcelas($locacaoComParcela, [
        'quantidade' => 1,
        'total_pagar_final' => 300.00,
    ], $chave);
    $valorComplementar = (float) Database::fetchColumn(
        'SELECT valor_total FROM financeiro WHERE id = ? AND chave = ?',
        [$complementares[0], $chave]
    );
    checkLocacaoMulta('gera apenas os R$ 200,00 restantes', $valorComplementar === 200.0, $valorComplementar);

    $locacaoComDevolucao = criarLocacaoTesteMulta($chave, 300.00);
    criarFinanceiroTesteMulta($chave, $locacaoComDevolucao, 300.00, null, 1, 1);
    $encargoDevolucao = $model->gerarParcelas($locacaoComDevolucao, [
        'quantidade' => 1,
        'total_pagar_final' => 370.00,
    ], $chave);
    $valorEncargo = (float) Database::fetchColumn(
        'SELECT valor_total FROM financeiro WHERE id = ? AND chave = ?',
        [$encargoDevolucao[0], $chave]
    );
    checkLocacaoMulta('gera os R$ 70,00 de encargos da devolucao', $valorEncargo === 70.0, $valorEncargo);

    $locacaoOdometroFormatado = criarLocacaoTesteMulta($chave, 300.00);
    criarVeiculoLocacaoTeste($chave, $locacaoOdometroFormatado);
    $totaisOdometroFormatado = $model->calcularTotaisResumo($locacaoOdometroFormatado, [
        'status' => 'F',
        'dias' => 2,
        'plano' => 'KMC',
        'odometro_ini' => '100',
        'odometro_fim' => '1.000',
        'km_controlado_franquia' => 100,
        'km_valor' => '0,10',
        'combustivel_fim' => 4,
    ]);
    checkLocacaoMulta('odometro formatado gera R$ 70,00 de km excedente', (float) $totaisOdometroFormatado['total_km_excedente'] === 70.0, $totaisOdometroFormatado['total_km_excedente']);
    $totalEsperadoOdometroFormatado = 300.0
        + (float) $totaisOdometroFormatado['total_km_excedente']
        + (float) $totaisOdometroFormatado['total_combustivel'];
    checkLocacaoMulta('total soma diaria, km excedente e combustivel', (float) $totaisOdometroFormatado['total_pagar'] === $totalEsperadoOdometroFormatado, $totaisOdometroFormatado['total_pagar']);
} catch (Throwable $e) {
    echo 'ERRO: ' . $e->getMessage() . "\n";
    $falhas++;
} finally {
    foreach ($locacoesVeiculosCriadas as $idLocacaoVeiculo) {
        Database::execute('DELETE FROM locacoes_veiculos WHERE id = ? AND chave = ?', [$idLocacaoVeiculo, $chave]);
    }
    foreach ($locacoesCriadas as $idLocacao) {
        Database::execute('DELETE FROM financeiro WHERE id_locacao = ? AND chave = ?', [$idLocacao, $chave]);
        Database::execute('DELETE FROM locacoes WHERE id = ? AND chave = ?', [$idLocacao, $chave]);
    }
}

echo "\nSucessos: {$sucessos}\n";
echo "Falhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
