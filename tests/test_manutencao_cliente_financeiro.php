<?php

/**
 * Teste: manutencao com cliente pagador deve gerar financeiro como receita.
 *
 * Execute: php tests/test_manutencao_cliente_financeiro.php
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
use App\Models\Manutencao;
use App\Models\ManutencaoItem;

$chave = '1111111111111';
$_SESSION['chave'] = $chave;
$_SESSION['user_id'] = 7;
$_SESSION['user_name'] = 'Teste';

$falhas = 0;
$sucessos = 0;
$idMatrizFilial = (int) Database::fetchColumn(
    'SELECT id FROM matrizes_filiais WHERE chave = ? ORDER BY id LIMIT 1',
    [$chave]
);
$clientesCriados = [];
$veiculosCriados = [];
$manutencoesCriadas = [];
$financeirosCriados = [];

function checkManutencaoClienteFinanceiro(string $label, bool $ok, mixed $atual = null): void
{
    global $falhas, $sucessos;

    echo '   ' . ($ok ? 'PASS' : 'FAIL') . " {$label}";
    if ($atual !== null) {
        echo " - atual={$atual}";
    }
    echo "\n";

    if ($ok) {
        $sucessos++;
    } else {
        $falhas++;
    }
}

function codigoTesteManutencaoCliente(string $prefixo): string
{
    return $prefixo . substr(strtoupper(bin2hex(random_bytes(4))), 0, 6);
}

function criarClienteTesteManutencao(string $chave): int
{
    global $idMatrizFilial;

    return Database::insertGetId('clientes', [
        'chave' => $chave,
        'id_matriz_filial' => $idMatrizFilial ?: null,
        'nome_rsocial' => 'Cliente Teste Manutencao ' . codigoTesteManutencaoCliente(''),
        'cpf_cnpj' => (string) random_int(10000000000, 99999999999),
        'foto' => '',
        'data_cadastro' => date('Y-m-d'),
        'situacao' => 'A',
    ]);
}

function criarVeiculoTesteManutencao(string $chave): int
{
    global $idMatrizFilial;

    return Database::insertGetId('veiculos', [
        'chave' => $chave,
        'id_matriz_filial' => $idMatrizFilial ?: null,
        'placa' => codigoTesteManutencaoCliente('TF'),
        'marca' => 'Teste',
        'modelo' => 'Financeiro',
        'disponibilidade' => 'D',
        'odometro' => '1000',
    ]);
}

function criarManutencaoComItem(string $chave, int $veiculoId, ?int $clienteId): int
{
    global $idMatrizFilial;

    $manutencaoModel = new Manutencao();
    $itemModel = new ManutencaoItem();

    $manutencaoId = $manutencaoModel->criar([
        'chave' => $chave,
        'os' => codigoTesteManutencaoCliente('OS'),
        'id_matriz_filial' => $idMatrizFilial ?: null,
        'id_veiculo' => $veiculoId,
        'id_cliente' => $clienteId,
        'status' => 'C',
    ]);

    $itemModel->criar([
        'chave' => $chave,
        'id_manutencao' => $manutencaoId,
        'descricao' => 'Servico teste cliente pagador',
        'quantidade' => 1,
        'valor_unitario' => 120.50,
        'desconto' => 0,
        'pago' => 'N',
    ]);

    return $manutencaoId;
}

echo "=== Teste manutencao cliente pagador no financeiro ===\n";

try {
    $model = new Manutencao();

    $clienteId = criarClienteTesteManutencao($chave);
    $clientesCriados[] = $clienteId;

    $veiculoComCliente = criarVeiculoTesteManutencao($chave);
    $veiculosCriados[] = $veiculoComCliente;
    $manutencaoComCliente = criarManutencaoComItem($chave, $veiculoComCliente, $clienteId);
    $manutencoesCriadas[] = $manutencaoComCliente;

    $financeiroComCliente = $model->criarLancamentoFinanceiro($manutencaoComCliente, [
        'parcelas' => 2,
        'data_vencimento' => date('Y-m-d'),
    ]);
    $financeirosCriados[] = $financeiroComCliente;

    $cabecalhoCliente = Database::fetchOne(
        'SELECT tipo, id_cliente FROM financeiro WHERE id = ? AND chave = ?',
        [$financeiroComCliente, $chave]
    );
    checkManutencaoClienteFinanceiro('financeiro com cliente vira receita', ($cabecalhoCliente['tipo'] ?? null) === 'R', $cabecalhoCliente['tipo'] ?? null);
    checkManutencaoClienteFinanceiro('financeiro com cliente grava id_cliente', (int) ($cabecalhoCliente['id_cliente'] ?? 0) === $clienteId, $cabecalhoCliente['id_cliente'] ?? null);

    $parcelasSemCliente = (int) Database::fetchColumn(
        'SELECT COUNT(*) FROM financeiro WHERE (id = ? OR id_financeiro_origem = ?) AND chave = ? AND id_cliente IS NULL',
        [$financeiroComCliente, $financeiroComCliente, $chave]
    );
    checkManutencaoClienteFinanceiro('parcelas mantem cliente pagador', $parcelasSemCliente === 0, $parcelasSemCliente);

    $idsParcelas = Database::fetchAll(
        'SELECT id FROM financeiro WHERE id_financeiro_origem = ? AND chave = ?',
        [$financeiroComCliente, $chave]
    );
    foreach ($idsParcelas as $parcela) {
        $financeirosCriados[] = (int) $parcela['id'];
    }

    $veiculoSemCliente = criarVeiculoTesteManutencao($chave);
    $veiculosCriados[] = $veiculoSemCliente;
    $manutencaoSemCliente = criarManutencaoComItem($chave, $veiculoSemCliente, null);
    $manutencoesCriadas[] = $manutencaoSemCliente;

    $financeiroSemCliente = $model->criarLancamentoFinanceiro($manutencaoSemCliente, [
        'data_vencimento' => date('Y-m-d'),
    ]);
    $financeirosCriados[] = $financeiroSemCliente;

    $cabecalhoSemCliente = Database::fetchOne(
        'SELECT tipo, id_cliente FROM financeiro WHERE id = ? AND chave = ?',
        [$financeiroSemCliente, $chave]
    );
    checkManutencaoClienteFinanceiro('financeiro sem cliente continua despesa', ($cabecalhoSemCliente['tipo'] ?? null) === 'D', $cabecalhoSemCliente['tipo'] ?? null);
    checkManutencaoClienteFinanceiro('financeiro sem cliente nao grava id_cliente', empty($cabecalhoSemCliente['id_cliente']), $cabecalhoSemCliente['id_cliente'] ?? 'NULL');

    $veiculoParcial = criarVeiculoTesteManutencao($chave);
    $veiculosCriados[] = $veiculoParcial;
    $manutencaoParcial = criarManutencaoComItem($chave, $veiculoParcial, $clienteId);
    $manutencoesCriadas[] = $manutencaoParcial;
    $itemParcial = (int) Database::fetchColumn(
        'SELECT id FROM manutencoes_itens WHERE id_manutencao = ? AND chave = ? LIMIT 1',
        [$manutencaoParcial, $chave]
    );

    $financeiroParcial = $model->criarLancamentoParcial($manutencaoParcial, [$itemParcial], [
        'data_vencimento' => date('Y-m-d'),
    ]);
    $financeirosCriados[] = $financeiroParcial;

    $cabecalhoParcial = Database::fetchOne(
        'SELECT tipo, id_cliente FROM financeiro WHERE id = ? AND chave = ?',
        [$financeiroParcial, $chave]
    );
    checkManutencaoClienteFinanceiro('financeiro parcial com cliente vira receita', ($cabecalhoParcial['tipo'] ?? null) === 'R', $cabecalhoParcial['tipo'] ?? null);
    checkManutencaoClienteFinanceiro('financeiro parcial grava id_cliente', (int) ($cabecalhoParcial['id_cliente'] ?? 0) === $clienteId, $cabecalhoParcial['id_cliente'] ?? null);
} catch (Throwable $e) {
    echo 'ERRO: ' . $e->getMessage() . "\n";
    $falhas++;
} finally {
    foreach (array_unique($financeirosCriados) as $idFinanceiro) {
        Database::execute('DELETE FROM financeiro_itens WHERE id_financeiro = ? AND chave = ?', [$idFinanceiro, $chave]);
        Database::execute('DELETE FROM financeiro WHERE id = ? AND chave = ?', [$idFinanceiro, $chave]);
    }
    foreach ($manutencoesCriadas as $idManutencao) {
        Database::execute('DELETE FROM manutencoes_itens WHERE id_manutencao = ? AND chave = ?', [$idManutencao, $chave]);
        Database::execute('DELETE FROM manutencoes WHERE id = ? AND chave = ?', [$idManutencao, $chave]);
    }
    foreach ($veiculosCriados as $idVeiculo) {
        Database::execute('DELETE FROM veiculos WHERE id = ? AND chave = ?', [$idVeiculo, $chave]);
    }
    foreach ($clientesCriados as $idCliente) {
        Database::execute('DELETE FROM clientes WHERE id = ? AND chave = ?', [$idCliente, $chave]);
    }
}

echo "\nSucessos: {$sucessos}\n";
echo "Falhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
