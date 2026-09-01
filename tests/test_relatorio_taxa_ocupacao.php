<?php

/**
 * Teste de integracao da composicao historica da frota nos KPIs.
 *
 * Execute: php tests/test_relatorio_taxa_ocupacao.php
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
use App\Models\Relatorios\KpiReport;

$chave = 'TEST_OCUP_' . strtoupper(bin2hex(random_bytes(5)));
$outraChave = $chave . '_OUTRO';
$_SESSION['chave'] = $chave;
$_SESSION['user_id'] = 1;
$_SESSION['authenticated'] = true;
$falhas = 0;
$sequencia = 0;

function assertOcupacao(string $label, mixed $atual, mixed $esperado): void
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

function criarFilialOcupacao(string $chave, string $nome): int
{
    return Database::insertGetId('matrizes_filiais', [
        'chave' => $chave,
        'tipo' => 'F',
        'nome_fantasia' => $nome,
    ]);
}

function criarGrupoOcupacao(string $chave, string $nome): int
{
    return Database::insertGetId('grupos', [
        'chave' => $chave,
        'nome' => $nome,
    ]);
}

function criarVeiculoOcupacao(
    string $chave,
    int $filial,
    int $grupo,
    string $placa,
    string $disponibilidade,
    ?string $dataCompra = '2025-01-01',
    ?string $dataVenda = null
): int {
    return Database::insertGetId('veiculos', [
        'chave' => $chave,
        'id_matriz_filial' => $filial,
        'id_grupo' => $grupo,
        'placa' => $placa,
        'marca' => 'Teste',
        'modelo' => $placa,
        'data_compra' => $dataCompra,
        'data_venda' => $dataVenda,
        'disponibilidade' => $disponibilidade,
    ]);
}

function criarLocacaoOcupacao(
    string $chave,
    int $veiculo,
    int $grupo,
    int $filialOperacao,
    string $saida,
    ?string $entrada,
    string $status = 'F',
    float $total = 0.0
): void {
    global $sequencia;
    $sequencia++;
    $locacao = Database::insertGetId('locacoes', [
        'codigo' => 'TOCL' . substr(md5($chave . $sequencia), 0, 10),
        'chave' => $chave,
        'id_matriz_filial_retirada' => $filialOperacao,
        'id_matriz_filial_devolucao' => $filialOperacao,
        'status' => $status,
        'data_saida' => $saida,
        'data_prevista' => $entrada ?? '2026-09-30 18:00:00',
        'data_chegada' => $entrada,
        'dias' => 1,
        'cliente_nome' => 'Cliente Teste',
        'total_fatura' => $total,
        'total_pagar' => $total,
    ]);

    Database::insertGetId('locacoes_veiculos', [
        'id_locacao' => $locacao,
        'id_veiculo' => $veiculo,
        'id_grupo' => $grupo,
        'data_saida' => $saida,
        'data_entrada' => $entrada,
        'plano' => 'KL',
        'chave' => $chave,
    ]);
}

function criarContratoOcupacao(
    string $chave,
    int $veiculo,
    int $grupo,
    int $filialOperacao,
    string $saida,
    ?string $entrada,
    string $status = 'A',
    float $total = 0.0
): void {
    global $sequencia;
    $sequencia++;
    $contrato = Database::insertGetId('contratos', [
        'codigo' => 'TOCC' . substr(md5($chave . $sequencia), 0, 10),
        'chave' => $chave,
        'id_matriz_filial_retirada' => $filialOperacao,
        'data_ini' => $saida,
        'data_fim' => $entrada ?? '2026-12-31 18:00:00',
        'contagem' => 'Mensal',
        'dias' => 30,
        'status' => $status,
        'total_fatura' => $total,
        'total_pagar' => $total,
    ]);

    Database::insertGetId('contratos_veiculos', [
        'id_contrato' => $contrato,
        'id_veiculo' => $veiculo,
        'id_grupo' => $grupo,
        'data_saida' => $saida,
        'data_entrada' => $entrada,
        'plano' => 'KL',
        'chave' => $chave,
    ]);
}

function limparOcupacao(string $chave): void
{
    foreach ([
        'contratos_veiculos',
        'contratos',
        'locacoes_veiculos',
        'locacoes',
        'veiculos',
        'grupos',
        'matrizes_filiais',
    ] as $tabela) {
        Database::execute("DELETE FROM {$tabela} WHERE chave = ?", [$chave]);
    }
}

try {
    $filialA = criarFilialOcupacao($chave, 'Filial A');
    $filialB = criarFilialOcupacao($chave, 'Filial B');
    $grupoA = criarGrupoOcupacao($chave, 'Grupo A');
    $grupoB = criarGrupoOcupacao($chave, 'Grupo B');

    $ativo = criarVeiculoOcupacao($chave, $filialA, $grupoA, 'OCPATIVO', 'D');
    criarLocacaoOcupacao($chave, $ativo, $grupoA, $filialB, '2026-08-01 10:00:00', '2026-08-10 10:00:00', 'F', 1000);
    criarContratoOcupacao($chave, $ativo, $grupoA, $filialB, '2026-08-05 08:00:00', '2026-08-15 18:00:00', 'F', 2000);

    criarVeiculoOcupacao($chave, $filialA, $grupoA, 'OCPANTES', 'V', '2025-01-01', '2026-07-15');

    $vendidoDurante = criarVeiculoOcupacao($chave, $filialA, $grupoA, 'OCPDURAN', 'V', '2025-01-01', '2026-08-15');
    criarLocacaoOcupacao($chave, $vendidoDurante, $grupoA, $filialB, '2026-08-10 09:00:00', '2026-08-20 18:00:00');

    criarVeiculoOcupacao($chave, $filialA, $grupoA, 'OCPSEMDT', 'V');
    $compradoDurante = criarVeiculoOcupacao($chave, $filialA, $grupoB, 'OCPCOMPR', 'D', '2026-08-10');
    criarLocacaoOcupacao($chave, $compradoDurante, $grupoB, $filialB, '2026-08-10 08:00:00', '2026-08-20 18:00:00', 'C');
    criarVeiculoOcupacao($chave, $filialA, $grupoB, 'OCPDEPOI', 'V', '2025-01-01', '2026-09-10');
    criarVeiculoOcupacao($chave, $filialA, $grupoB, 'OCPROUBO', 'RO');
    criarVeiculoOcupacao($chave, $filialA, $grupoB, 'OCPEXCLU', 'E');
    criarVeiculoOcupacao($chave, $filialB, $grupoA, 'OCPFILB', 'D');
    criarVeiculoOcupacao($outraChave, $filialA, $grupoA, 'OCPOUTRO', 'D');

    $model = new KpiReport();
    $filtroFilialA = 'id_matriz_filial IN (?)';
    $resultado = $model->taxaOcupacao('2026-08-01', '2026-08-31', $filtroFilialA, [$filialA]);

    assertOcupacao('total de veiculos historicos', $resultado['totals']['total_veiculos'], 4);
    assertOcupacao('periodo inclusivo e janelas parciais', $resultado['totals']['dias_disponiveis'], 98);
    assertOcupacao('intervalos sobrepostos contam dias unicos', $resultado['totals']['dias_locados'], 20);
    assertOcupacao('dias parados derivados da mesma base', $resultado['totals']['dias_parados'], 78);
    assertOcupacao('taxa geral limitada e coerente', $resultado['totals']['taxa_ocupacao'], 20.41);

    $porPlaca = [];
    foreach ($resultado['details'] as $linha) {
        $porPlaca[$linha['placa']] = $linha;
        assertOcupacao('taxa individual ate 100% ' . $linha['placa'], $linha['taxa_ocupacao'] <= 100.0, true);
    }

    assertOcupacao('vendido antes nao aparece', isset($porPlaca['OCPANTES']), false);
    assertOcupacao('vendido sem data nao aparece', isset($porPlaca['OCPSEMDT']), false);
    assertOcupacao('roubado nao aparece', isset($porPlaca['OCPROUBO']), false);
    assertOcupacao('excluido nao aparece', isset($porPlaca['OCPEXCLU']), false);
    assertOcupacao('outra filial nao aparece', isset($porPlaca['OCPFILB']), false);
    assertOcupacao('outro tenant nao aparece', isset($porPlaca['OCPOUTRO']), false);
    assertOcupacao('sobreposicao locacao e contrato', $porPlaca['OCPATIVO']['dias_locados'], 15);
    assertOcupacao('venda exclui o proprio dia', $porPlaca['OCPDURAN']['dias_parados'], 9);
    assertOcupacao('ocupacao recortada na venda', $porPlaca['OCPDURAN']['dias_locados'], 5);
    assertOcupacao('compra durante o periodo', $porPlaca['OCPCOMPR']['dias_parados'], 22);

    $umDia = $model->taxaOcupacao('2026-08-10', '2026-08-10', $filtroFilialA, [$filialA]);
    assertOcupacao('periodo de um dia tem denominador correto', $umDia['totals']['dias_disponiveis'], 4);
    assertOcupacao('periodo de um dia deduplica ocupacao', $umDia['totals']['dias_locados'], 2);

    $grupo = $model->taxaOcupacao('2026-08-01', '2026-08-31', $filtroFilialA, [$filialA], '', (string) $grupoA);
    assertOcupacao('filtro de grupo usa frota historica', $grupo['totals']['total_veiculos'], 2);
    assertOcupacao('filtro de grupo soma disponibilidade', $grupo['totals']['dias_disponiveis'], 45);

    $revpar = $model->revpar('2026-08-01', '2026-08-31', $filtroFilialA, [$filialA]);
    assertOcupacao('RevPAR usa a mesma frota', $revpar['totals']['total_veiculos'], 4);
    assertOcupacao('RevPAR usa dias disponiveis reais', $revpar['totals']['dias_disponiveis'], 98);

    $adr = $model->adr('2026-08-01', '2026-08-31', $filtroFilialA, [$filialA]);
    assertOcupacao('ADR usa dias ocupados unicos', $adr['totals']['dias_locados'], 20);

    $margem = $model->margemBruta('2026-08-01', '2026-08-31', $filtroFilialA, [$filialA]);
    assertOcupacao('Margem por dia usa dias ocupados unicos', $margem['totals']['dias_locados'], 20);

    $receita = $model->receitaPorVeiculo('2026-08-01', '2026-08-31', $filtroFilialA, [$filialA], '', '', 1, 20);
    assertOcupacao('Receita por Veiculo usa a mesma frota', $receita['total'], 4);
    assertOcupacao('Receita por Veiculo pagina somente a frota', count($receita['details']), 4);

    $roi = $model->roiPorVeiculo('2026-08-01', '2026-08-31', $filtroFilialA, [$filialA], '', '', 1, 20);
    assertOcupacao('ROI herda a mesma frota', $roi['total'], 4);
} catch (Throwable $e) {
    $falhas++;
    echo 'ERRO: ' . $e->getMessage() . "\n";
} finally {
    limparOcupacao($chave);
    limparOcupacao($outraChave);
}

echo "\nFalhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
