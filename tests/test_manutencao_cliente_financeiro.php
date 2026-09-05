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
use App\Models\Financeiro;
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
$idConta = (int) Database::fetchColumn(
    'SELECT id FROM contas_bancarias WHERE chave = ? AND status = ? ORDER BY id LIMIT 1',
    [$chave, 'A']
);
$idFormaPagamento = (int) Database::fetchColumn(
    'SELECT id FROM formas_pagamento WHERE chave = ? AND status = ? ORDER BY id LIMIT 1',
    [$chave, 'A']
);
$planosReceita = array_column(
    Database::fetchAll('SELECT id FROM planos_de_contas WHERE chave = ? AND tipo = ? ORDER BY id LIMIT 2', ['0', 'R']),
    'id'
);
$idPlanoDespesa = (int) Database::fetchColumn(
    'SELECT id FROM planos_de_contas WHERE chave = ? AND tipo = ? ORDER BY id LIMIT 1',
    ['0', 'D']
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

function parcelasTesteManutencao(array $planos): array
{
    global $idConta, $idFormaPagamento;

    $quantidade = count($planos);
    $valorBase = round(120.50 / $quantidade, 2);
    $parcelas = [];
    $acumulado = 0.0;

    foreach ($planos as $index => $idPlano) {
        $valor = $index === $quantidade - 1 ? round(120.50 - $acumulado, 2) : $valorBase;
        $acumulado = round($acumulado + $valor, 2);
        $parcelas[] = [
            'numero' => $index + 1,
            'id_conta' => $idConta,
            'id_forma_pagamento' => $idFormaPagamento,
            'id_plano_de_conta' => (int) $idPlano,
            'data_vencimento' => \App\Helpers\DateHelper::addDaysForDatabase(30 * $index),
            'valor' => $valor,
            'pago' => 'N',
        ];
    }

    return $parcelas;
}

echo "=== Teste manutencao cliente pagador no financeiro ===\n";

try {
    if ($idConta <= 0 || $idFormaPagamento <= 0 || count($planosReceita) < 2 || $idPlanoDespesa <= 0) {
        throw new RuntimeException('Fixtures financeiras obrigatorias nao encontradas');
    }

    $planosSelect = (new Financeiro())->listarPlanosDeContasSelect($chave);
    $hierarquiasSelect = array_column($planosSelect, 'hierarquia');
    checkManutencaoClienteFinanceiro(
        'select financeiro retorna hierarquia dos planos padrao',
        in_array('3.1.1', $hierarquiasSelect, true) && in_array('4.1.1.04', $hierarquiasSelect, true)
    );

    $model = new Manutencao();

    $clienteId = criarClienteTesteManutencao($chave);
    $clientesCriados[] = $clienteId;

    $veiculoComCliente = criarVeiculoTesteManutencao($chave);
    $veiculosCriados[] = $veiculoComCliente;
    $manutencaoComCliente = criarManutencaoComItem($chave, $veiculoComCliente, $clienteId);
    $manutencoesCriadas[] = $manutencaoComCliente;

    $parcelaSemPlano = parcelasTesteManutencao([(int) $planosReceita[0]]);
    unset($parcelaSemPlano[0]['id_plano_de_conta']);
    $rejeitouSemPlano = false;
    try {
        $model->criarLancamentoFinanceiro($manutencaoComCliente, ['parcelas_geradas' => $parcelaSemPlano]);
    } catch (InvalidArgumentException) {
        $rejeitouSemPlano = true;
    }
    checkManutencaoClienteFinanceiro('plano e obrigatorio em cada parcela', $rejeitouSemPlano);

    $rejeitouTipoIncompativel = false;
    try {
        $model->criarLancamentoFinanceiro(
            $manutencaoComCliente,
            ['parcelas_geradas' => parcelasTesteManutencao([$idPlanoDespesa])]
        );
    } catch (InvalidArgumentException) {
        $rejeitouTipoIncompativel = true;
    }
    checkManutencaoClienteFinanceiro('plano deve ser compativel com receita ou despesa', $rejeitouTipoIncompativel);

    $financeiroComCliente = $model->criarLancamentoFinanceiro($manutencaoComCliente, [
        'parcelas_geradas' => parcelasTesteManutencao($planosReceita),
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

    $planosGravados = array_map(
        'intval',
        array_column(Database::fetchAll(
            'SELECT id_plano_de_conta FROM financeiro WHERE (id = ? OR id_financeiro_origem = ?) AND chave = ? ORDER BY parcela',
            [$financeiroComCliente, $financeiroComCliente, $chave]
        ), 'id_plano_de_conta')
    );
    checkManutencaoClienteFinanceiro('cada parcela mantem seu plano de contas', $planosGravados === array_map('intval', $planosReceita), implode(',', $planosGravados));

    $planoItemPrincipal = (int) Database::fetchColumn(
        'SELECT id_plano_de_conta FROM financeiro_itens WHERE id_financeiro = ? AND chave = ? LIMIT 1',
        [$financeiroComCliente, $chave]
    );
    checkManutencaoClienteFinanceiro('item financeiro usa plano da primeira parcela', $planoItemPrincipal === (int) $planosReceita[0], $planoItemPrincipal);

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
        'parcelas_geradas' => parcelasTesteManutencao([$idPlanoDespesa]),
    ]);
    $financeirosCriados[] = $financeiroSemCliente;

    $cabecalhoSemCliente = Database::fetchOne(
        'SELECT tipo, id_cliente FROM financeiro WHERE id = ? AND chave = ?',
        [$financeiroSemCliente, $chave]
    );
    checkManutencaoClienteFinanceiro('financeiro sem cliente continua despesa', ($cabecalhoSemCliente['tipo'] ?? null) === 'D', $cabecalhoSemCliente['tipo'] ?? null);
    checkManutencaoClienteFinanceiro('financeiro sem cliente nao grava id_cliente', empty($cabecalhoSemCliente['id_cliente']), $cabecalhoSemCliente['id_cliente'] ?? 'NULL');
    $planoDespesaGravado = (int) Database::fetchColumn(
        'SELECT id_plano_de_conta FROM financeiro WHERE id = ? AND chave = ?',
        [$financeiroSemCliente, $chave]
    );
    checkManutencaoClienteFinanceiro('despesa grava plano selecionado', $planoDespesaGravado === $idPlanoDespesa, $planoDespesaGravado);

    $veiculoParcial = criarVeiculoTesteManutencao($chave);
    $veiculosCriados[] = $veiculoParcial;
    $manutencaoParcial = criarManutencaoComItem($chave, $veiculoParcial, $clienteId);
    $manutencoesCriadas[] = $manutencaoParcial;
    $itemParcial = (int) Database::fetchColumn(
        'SELECT id FROM manutencoes_itens WHERE id_manutencao = ? AND chave = ? LIMIT 1',
        [$manutencaoParcial, $chave]
    );

    $financeiroParcial = $model->criarLancamentoParcial($manutencaoParcial, [$itemParcial], [
        'parcelas_geradas' => parcelasTesteManutencao([(int) $planosReceita[0]]),
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
