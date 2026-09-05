<?php

/**
 * Teste: classificacao global de financeiros historicos de manutencao.
 *
 * O teste usa planos globais existentes e nao altera planos_de_contas.
 * Execute: php tests/test_classificar_financeiros_manutencoes.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Core\Database;

$sufixo = strtoupper(bin2hex(random_bytes(6)));
$prefixoTeste = "TEST_CLASS_{$sufixo}_";
$chaveDespesa = "{$prefixoTeste}D";
$chaveReceita = "{$prefixoTeste}R";
$chaves = [$chaveDespesa, $chaveReceita];
$falhas = 0;

function assertClassificacaoManutencao(string $label, bool $condicao): void
{
    global $falhas;
    echo ($condicao ? 'PASS' : 'FAIL') . ": {$label}\n";
    if (!$condicao) {
        $falhas++;
    }
}

$planos = Database::fetchAll(
    "SELECT id, hierarquia, tipo FROM planos_de_contas
     WHERE chave = '0' AND hierarquia IN ('3.1.1', '4.1.1.04')"
);
$planosPorHierarquia = array_column($planos, null, 'hierarquia');
$idPlanoDespesa = (int) ($planosPorHierarquia['3.1.1']['id'] ?? 0);
$idPlanoReceita = (int) ($planosPorHierarquia['4.1.1.04']['id'] ?? 0);

if ($idPlanoDespesa <= 0 || $idPlanoReceita <= 0) {
    fwrite(STDERR, "Planos globais 3.1.1 e 4.1.1.04 sao obrigatorios para o teste.\n");
    exit(1);
}

try {
    $idDespesaPrincipal = Database::insertGetId('financeiro', [
        'chave' => $chaveDespesa,
        'tipo' => 'D',
        'pago' => 'S',
        'parcela' => 1,
        'total_parcelas' => 2,
        'descricao' => 'Manutencao historica despesa teste',
        'data_criada' => today(),
        'data_venci' => today(),
        'data_pago' => today(),
        'valor_subtotal' => 50,
        'valor_total' => 50,
    ]);
    $idDespesaFilha = Database::insertGetId('financeiro', [
        'chave' => $chaveDespesa,
        'tipo' => 'D',
        'pago' => 'N',
        'parcela' => 2,
        'total_parcelas' => 2,
        'id_financeiro_origem' => $idDespesaPrincipal,
        'descricao' => 'Manutencao historica despesa teste',
        'data_criada' => today(),
        'data_venci' => today(),
        'valor_subtotal' => 50,
        'valor_total' => 50,
    ]);
    Database::insertGetId('financeiro_itens', [
        'chave' => $chaveDespesa,
        'id_financeiro' => $idDespesaPrincipal,
        'descricao' => 'Item despesa teste',
        'valor' => 50,
        'ordem' => 1,
    ]);
    Database::insertGetId('manutencoes', [
        'chave' => $chaveDespesa,
        'os' => "TD{$sufixo}",
        'id_financeiro_principal' => $idDespesaPrincipal,
        'status' => 'F',
    ]);

    $idReceita = Database::insertGetId('financeiro', [
        'chave' => $chaveReceita,
        'tipo' => 'R',
        'pago' => 'N',
        'parcela' => 1,
        'total_parcelas' => 1,
        'descricao' => 'Manutencao historica receita teste',
        'data_criada' => today(),
        'data_venci' => today(),
        'valor_subtotal' => 75,
        'valor_total' => 75,
    ]);
    Database::insertGetId('financeiro_itens', [
        'chave' => $chaveReceita,
        'id_financeiro' => $idReceita,
        'descricao' => 'Item receita teste',
        'valor' => 75,
        'ordem' => 1,
    ]);
    Database::insertGetId('manutencoes', [
        'chave' => $chaveReceita,
        'os' => "TR{$sufixo}",
        'id_financeiro_principal' => $idReceita,
        'status' => 'F',
    ]);

    $idJaClassificado = Database::insertGetId('financeiro', [
        'chave' => $chaveReceita,
        'tipo' => 'D',
        'pago' => 'N',
        'parcela' => 1,
        'total_parcelas' => 1,
        'id_plano_de_conta' => $idPlanoDespesa,
        'descricao' => 'Manutencao ja classificada teste',
        'data_criada' => today(),
        'data_venci' => today(),
        'valor_subtotal' => 20,
        'valor_total' => 20,
    ]);
    Database::insertGetId('financeiro_itens', [
        'chave' => $chaveReceita,
        'id_financeiro' => $idJaClassificado,
        'descricao' => 'Item de financeiro classificado teste',
        'valor' => 20,
        'ordem' => 1,
    ]);
    Database::insertGetId('manutencoes', [
        'chave' => $chaveReceita,
        'os' => "TC{$sufixo}",
        'id_financeiro_principal' => $idJaClassificado,
        'status' => 'F',
    ]);

    $comandoBase = escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg(APP_ROOT . '/scripts/classificar-financeiros-manutencoes.php')
        . ' --env=development --all-tenants'
        . ' --tenant-prefix=' . escapeshellarg($prefixoTeste)
        . ' --plano-despesa=3.1.1 --plano-receita=4.1.1.04';

    exec($comandoBase, $saidaPrevia, $codigoPrevia);
    $textoPrevia = implode("\n", $saidaPrevia);
    assertClassificacaoManutencao('previa global conclui sem gravacao', $codigoPrevia === 0);
    assertClassificacaoManutencao(
        'previa separa despesas do primeiro tenant',
        str_contains($textoPrevia, "TENANT | {$chaveDespesa} | despesas_registros=2 | despesas_valor=100.00")
    );
    assertClassificacaoManutencao(
        'previa separa receitas do segundo tenant',
        str_contains($textoPrevia, "TENANT | {$chaveReceita} | despesas_registros=0 | despesas_valor=0.00 | receitas_registros=1 | receitas_valor=75.00")
    );

    $nulosAntes = (int) Database::fetchColumn(
        'SELECT COUNT(*) FROM financeiro WHERE chave IN (?, ?) AND id_plano_de_conta IS NULL',
        $chaves
    );
    assertClassificacaoManutencao('previa global preserva os tres financeiros nulos', $nulosAntes === 3);

    exec(
        $comandoBase . ' --apply --confirm=CONFIRMACAO_INCORRETA 2>&1',
        $saidaConfirmacaoIncorreta,
        $codigoConfirmacaoIncorreta
    );
    assertClassificacaoManutencao('confirmacao global incorreta e recusada', $codigoConfirmacaoIncorreta !== 0);

    exec(
        escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg(APP_ROOT . '/scripts/classificar-financeiros-manutencoes.php')
            . ' --env=development --all-tenants --tenant-prefix=' . escapeshellarg($prefixoTeste)
            . ' --plano-despesa=3.1.1 --plano-receita=9.9.9'
            . ' --apply --confirm=CLASSIFICAR_FINANCEIROS_MANUTENCOES_TODOS 2>&1',
        $saidaPlanoInvalido,
        $codigoPlanoInvalido
    );
    assertClassificacaoManutencao('plano global invalido aborta antes de gravar', $codigoPlanoInvalido !== 0);

    $nulosAposFalhas = (int) Database::fetchColumn(
        'SELECT COUNT(*) FROM financeiro WHERE chave IN (?, ?) AND id_plano_de_conta IS NULL',
        $chaves
    );
    assertClassificacaoManutencao('falhas de pre-validacao nao alteram financeiros', $nulosAposFalhas === 3);

    $comandoAplicacao = $comandoBase . ' --apply --confirm=CLASSIFICAR_FINANCEIROS_MANUTENCOES_TODOS';
    exec($comandoAplicacao, $saidaAplicacao, $codigoAplicacao);
    assertClassificacaoManutencao('aplicacao global conclui', $codigoAplicacao === 0);

    $planosDespesa = Database::fetchAll(
        'SELECT id_plano_de_conta FROM financeiro WHERE id IN (?, ?) ORDER BY id',
        [$idDespesaPrincipal, $idDespesaFilha]
    );
    $todasDespesasClassificadas = count($planosDespesa) === 2;
    foreach ($planosDespesa as $registro) {
        $todasDespesasClassificadas = $todasDespesasClassificadas
            && (int) $registro['id_plano_de_conta'] === $idPlanoDespesa;
    }
    assertClassificacaoManutencao(
        'despesa principal e parcela filha recebem 3.1.1',
        $todasDespesasClassificadas
    );

    $planoReceitaGravado = (int) Database::fetchColumn(
        'SELECT id_plano_de_conta FROM financeiro WHERE id = ?',
        [$idReceita]
    );
    assertClassificacaoManutencao('receita recebe 4.1.1.04', $planoReceitaGravado === $idPlanoReceita);

    $planoPreservado = (int) Database::fetchColumn(
        'SELECT id_plano_de_conta FROM financeiro WHERE id = ?',
        [$idJaClassificado]
    );
    assertClassificacaoManutencao('financeiro ja classificado e preservado', $planoPreservado === $idPlanoDespesa);

    $itensNulos = (int) Database::fetchColumn(
        'SELECT COUNT(*) FROM financeiro_itens WHERE chave IN (?, ?) AND id_plano_de_conta IS NULL',
        $chaves
    );
    assertClassificacaoManutencao('itens financeiros recebem o plano do lancamento', $itensNulos === 0);

    exec($comandoAplicacao, $saidaReexecucao, $codigoReexecucao);
    assertClassificacaoManutencao(
        'reexecucao global e idempotente',
        $codigoReexecucao === 0
            && str_contains(implode("\n", $saidaReexecucao), 'APLICADO_TOTAL | tenants=0 | financeiros=0 | itens=0')
    );
} finally {
    Database::execute('DELETE FROM manutencoes WHERE chave IN (?, ?)', $chaves);
    Database::execute('DELETE FROM financeiro_itens WHERE chave IN (?, ?)', $chaves);
    Database::execute('DELETE FROM financeiro WHERE chave IN (?, ?)', $chaves);
}

$residuos = 0;
foreach (['manutencoes', 'financeiro_itens', 'financeiro'] as $tabela) {
    $residuos += (int) Database::fetchColumn(
        "SELECT COUNT(*) FROM {$tabela} WHERE chave IN (?, ?)",
        $chaves
    );
}
assertClassificacaoManutencao('teste remove todos os dados temporarios', $residuos === 0);

exit($falhas > 0 ? 1 : 0);
