<?php

/** Regressao: salvar/baixar a parcela 1 nao pode restaurar o total da OS. */
require dirname(__DIR__) . '/vendor/autoload.php';
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/Helpers/helpers.php';

use App\Core\Database;
use App\Models\Financeiro;
use App\Models\FinanceiroItem;
use App\Models\Manutencao;
use App\Models\ManutencaoItem;

$_ENV['APP_ENV'] = 'development';
if (Database::env('DB_HOST') !== 'localhost') {
    throw new RuntimeException('Este teste exige o banco localhost de development');
}
if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}
$_SESSION['chave'] = $chave = '1111111111111';
$_SESSION['user_id'] = 7;
$_SESSION['user_name'] = 'Teste parcelamento manutencao';

$filial = (int) Database::fetchColumn('SELECT id FROM matrizes_filiais WHERE chave = ? ORDER BY id LIMIT 1', [$chave]);
$veiculo = (int) Database::fetchColumn('SELECT id FROM veiculos WHERE chave = ? ORDER BY id LIMIT 1', [$chave]);
$cliente = (int) Database::fetchColumn('SELECT id FROM clientes WHERE chave = ? ORDER BY id LIMIT 1', [$chave]);
$conta = (int) Database::fetchColumn('SELECT id FROM contas_bancarias WHERE chave = ? AND status = ? LIMIT 1', [$chave, 'A']);
$forma = (int) Database::fetchColumn('SELECT id FROM formas_pagamento WHERE chave = ? AND status = ? LIMIT 1', [$chave, 'A']);
$planoD = (int) Database::fetchColumn('SELECT id FROM planos_de_contas WHERE chave = ? AND tipo = ? LIMIT 1', ['0', 'D']);
$planoR = (int) Database::fetchColumn('SELECT id FROM planos_de_contas WHERE chave = ? AND tipo = ? LIMIT 1', ['0', 'R']);
$manutencoes = [];
$extras = [];
$formasCriadas = [];
$checks = 0;

function conferir(bool $ok, string $mensagem): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($mensagem);
    }
    $checks++;
}

function centavos(mixed $valor): int
{
    return (int) round((float) $valor * 100);
}

function novaOs(array $valores, bool $receita = false): array
{
    global $chave, $filial, $veiculo, $cliente, $manutencoes;
    $id = (new Manutencao())->criar([
        'chave' => $chave, 'os' => 'TST' . strtoupper(bin2hex(random_bytes(5))),
        'id_matriz_filial' => $filial, 'id_veiculo' => $veiculo,
        'id_cliente' => $receita ? $cliente : null, 'status' => 'C',
    ]);
    $manutencoes[] = $id;
    $ids = [];
    foreach ($valores as $i => $valor) {
        $ids[] = (new ManutencaoItem())->criar([
            'chave' => $chave, 'id_manutencao' => $id, 'descricao' => 'Regressao servico ' . $i,
            'quantidade' => 1, 'valor_unitario' => $valor, 'pago' => 'N',
        ]);
    }
    return [$id, $ids];
}

function parcelas(array $valores, bool $receita = false, bool $pagas = false): array
{
    global $conta, $forma, $planoD, $planoR;
    return array_map(static fn ($valor, $i) => [
        'numero' => $i + 1, 'id_conta' => $conta, 'id_forma_pagamento' => $forma,
        'id_plano_de_conta' => $receita ? $planoR : $planoD,
        'data_vencimento' => App\Helpers\DateHelper::addDaysForDatabase($i * 30),
        'valor' => $valor, 'pago' => $pagas ? 'S' : 'N',
    ], $valores, array_keys($valores));
}

function conferirSerie(int $origem, array $valores): void
{
    global $chave, $veiculo;
    $rows = Database::fetchAll('SELECT * FROM financeiro WHERE chave = ? AND (id = ? OR id_financeiro_origem = ?) ORDER BY parcela', [$chave, $origem, $origem]);
    conferir(count($rows) === count($valores), 'Quantidade de parcelas incorreta');
    foreach ($rows as $i => $row) {
        conferir(centavos($row['valor_subtotal']) === centavos($valores[$i]), 'Subtotal alterado na parcela ' . ($i + 1));
        conferir(centavos($row['valor_total']) === centavos($valores[$i]), 'Total alterado na parcela ' . ($i + 1));
        conferir((int) $row['id_veiculo'] === $veiculo, 'Veiculo da parcela perdido');
    }
    $itens = (new FinanceiroItem())->listarPorFinanceiro($origem);
    conferir(array_sum(array_map(static fn ($item) => centavos($item['valor']), $itens)) === centavos($valores[0]), 'Itens da primeira parcela possuem o total integral da OS');
}

try {
    conferir(min($filial, $veiculo, $cliente, $conta, $forma, $planoD, $planoR) > 0, 'Fixtures obrigatorias ausentes');
    $model = new Manutencao();
    $financeiro = new Financeiro();
    foreach ([[390.35, 390.35, 390.35, 390.35, 390.36], [487.94, 487.94, 487.94, 487.94], [200, 500, 1251.76], [1951.76]] as $valores) {
        [$os] = novaOs([1780, 171.76, 0]);
        $origem = $model->criarLancamentoFinanceiro($os, ['parcelas_geradas' => parcelas($valores)]);
        conferirSerie($origem, $valores);
        // Mesmo caminho de persistencia dos itens usado pelo Controller ao salvar.
        $financeiro->atualizar($origem, ['descricao' => 'Edicao sem alterar valores']);
        (new FinanceiroItem())->salvarTodos($origem, $chave, (new FinanceiroItem())->listarPorFinanceiro($origem));
        $financeiro->recalcularTotal($origem);
        conferirSerie($origem, $valores);
        $financeiro->atualizar($origem, ['pago' => 'S', 'data_pago' => today()]);
        conferirSerie($origem, $valores);
        conferir(centavos($model->buscarPorId($os)['total_servicos']) === 195176, 'Valor integral da manutencao alterado');
        $rejeitou = false;
        try {
            $model->criarLancamentoFinanceiro($os, ['parcelas_geradas' => parcelas($valores)]);
        } catch (InvalidArgumentException) {
            $rejeitou = true;
        }
        conferir($rejeitou, 'Lancamento duplicado permitido');
    }

    [$os, $ids] = novaOs([1780, 171.76, 250]);
    $origem = $model->criarLancamentoParcial($os, array_slice($ids, 0, 2), ['parcelas_geradas' => parcelas([390.35, 390.35, 390.35, 390.35, 390.36])]);
    conferirSerie($origem, [390.35, 390.35, 390.35, 390.35, 390.36]);
    conferir((new ManutencaoItem())->buscarPorId($ids[2])['pago'] === 'N', 'Item nao selecionado foi fechado');
    $baixa = $financeiro->baixarParcial($origem, 100, today(), today(), $chave);
    $extras[] = $baixa['id_diferenca'];
    conferir(centavos($baixa['valor_diferenca']) === 29035, 'Saldo da baixa parcial incorreto');
    conferir(centavos($financeiro->buscarPorId($origem)['valor_total']) === 10000, 'Baixa parcial restaurou total da OS');

    // Muitos valores pequenos: o rateio nao pode deixar sobra negativa.
    [$os] = novaOs(array_fill(0, 30, 0.01));
    $origem = $model->criarLancamentoFinanceiro($os, ['parcelas_geradas' => parcelas([0.16, 0.14])]);
    conferirSerie($origem, [0.16, 0.14]);
    foreach ((new FinanceiroItem())->listarPorFinanceiro($origem) as $item) {
        conferir(centavos($item['valor']) >= 0, 'Rateio gerou item negativo');
    }

    // Falha na segunda parcela deve reverter tambem a primeira e seus itens.
    [$os, $ids] = novaOs([1780, 171.76]);
    $invalidas = parcelas([390.35, 1561.41]);
    $invalidas[1]['id_conta'] = 4294967295;
    $falhou = false;
    try {
        $model->criarLancamentoFinanceiro($os, ['parcelas_geradas' => $invalidas]);
    } catch (mysqli_sql_exception) {
        $falhou = true;
    }
    conferir($falhou, 'Falha de FK esperada nao ocorreu');
    $registro = $model->buscarPorId($os);
    conferir(empty($registro['id_financeiro_principal']), 'Rollback deixou financeiro principal');
    conferir((int) Database::fetchColumn('SELECT COUNT(*) FROM financeiro WHERE chave = ? AND descricao = ?', [$chave, 'Manutencao OS #' . $registro['os']]) === 0, 'Rollback deixou parcelas orfas');
    conferir((new ManutencaoItem())->buscarPorId($ids[0])['pago'] === 'N', 'Rollback deixou item fechado');

    // Receita ja paga com taxa exercita o indicador de transacao externa.
    $formaOriginal = $forma;
    $forma = Database::insertGetId('formas_pagamento', ['chave' => $chave, 'nome' => 'Teste taxa parcelamento', 'lancar_pago' => 'N', 'status' => 'A', 'taxa_percentual_parcela' => 2]);
    $formasCriadas[] = $forma;
    [$os] = novaOs([1780, 171.76], true);
    $origem = $model->criarLancamentoFinanceiro($os, ['parcelas_geradas' => parcelas([390.35, 1561.41], true, true)]);
    conferirSerie($origem, [390.35, 1561.41]);
    conferir($financeiro->buscarPorId($origem)['pago'] === 'S', 'Receita nao recebeu baixa');
    conferir(centavos(Database::fetchColumn('SELECT valor_total FROM financeiro WHERE chave = ? AND id_financeiro_taxa_origem = ?', [$chave, $origem])) === 781, 'Taxa nao corresponde a parcela');
    [$osFalha] = novaOs([1780, 171.76], true);
    $invalidas = parcelas([390.35, 1561.41], true, true);
    $invalidas[1]['id_conta'] = 4294967295;
    try {
        $model->criarLancamentoFinanceiro($osFalha, ['parcelas_geradas' => $invalidas]);
        throw new RuntimeException('Falha esperada na receita paga nao ocorreu');
    } catch (mysqli_sql_exception) {
        $registro = $model->buscarPorId($osFalha);
        conferir((int) Database::fetchColumn('SELECT COUNT(*) FROM financeiro WHERE chave = ? AND descricao = ?', [$chave, 'Manutencao OS #' . $registro['os']]) === 0, 'Receita paga confirmou transacao antes da hora');
    }
    $forma = $formaOriginal;

    $_SESSION['chave'] = 'TENANT_INEXISTENTE_TESTE';
    conferir($model->buscarPorId($os) === null, 'Manutencao vazou entre tenants');
    conferir($financeiro->buscarPorId($origem) === null, 'Financeiro vazou entre tenants');
    $_SESSION['chave'] = $chave;
    echo "OK: {$checks} verificacoes de parcelamento, edicao, baixa, centavos e rollback\n";
} finally {
    $_SESSION['chave'] = $chave;
    foreach ($manutencoes as $os) {
        $registro = $model->buscarPorId($os);
        $financeiros = Database::fetchAll('SELECT id FROM financeiro WHERE chave = ? AND descricao LIKE ?', [$chave, 'Manutencao OS #' . $registro['os'] . '%']);
        foreach ($financeiros as $row) {
            Database::execute('DELETE FROM financeiro WHERE chave = ? AND id_financeiro_taxa_origem = ?', [$chave, $row['id']]);
            Database::execute('DELETE FROM financeiro WHERE chave = ? AND id = ?', [$chave, $row['id']]);
        }
        Database::execute('DELETE FROM manutencoes_itens WHERE chave = ? AND id_manutencao = ?', [$chave, $os]);
        Database::execute('DELETE FROM manutencoes WHERE chave = ? AND id = ?', [$chave, $os]);
    }
    foreach ($extras as $id) {
        Database::execute('DELETE FROM financeiro WHERE chave = ? AND id = ?', [$chave, $id]);
    }
    foreach ($formasCriadas as $id) {
        Database::execute('DELETE FROM formas_pagamento WHERE chave = ? AND id = ?', [$chave, $id]);
    }
}
