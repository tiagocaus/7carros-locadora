<?php

/**
 * Integracao local do relatorio Contas a Receber por Contrato.
 *
 * Execute: php tests/test_relatorio_receber_por_contrato.php
 */
require __DIR__ . '/../vendor/autoload.php';
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/Helpers/helpers.php';

use App\Core\Database;
use App\Helpers\DateHelper;
use App\Helpers\PdfHelper;
use App\Models\Relatorios\FaturasReport;

if (Database::env('DB_HOST') !== 'localhost') {
    throw new RuntimeException('Este teste deve usar o banco local.');
}

function expectReceberContrato(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

function insertReceberContrato(mysqli $db, string $table, array $data): int
{
    $columns = array_keys($data);
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $columns) . '`) VALUES ('
        . implode(',', array_fill(0, count($columns), '?')) . ')';
    $stmt = $db->prepare($sql);
    $values = array_values($data);
    $stmt->bind_param(str_repeat('s', count($values)), ...$values);
    $stmt->execute();
    return $db->insert_id;
}

$db = FaturasReport::sharedMysqli();
$db->begin_transaction();
$oldSession = $_SESSION ?? [];
$tenant = 'TEST_REC_CONTRATO_' . strtoupper(bin2hex(random_bytes(5)));
$outroTenant = $tenant . '_OUTRO';
$_SESSION['chave'] = $tenant;

try {
    $hoje = DateHelper::todayForDatabase();
    $inicio = DateHelper::addDaysForDatabase(-30, $hoje);
    $fim = DateHelper::addDaysForDatabase(30, $hoje);
    $vencida = DateHelper::addDaysForDatabase(-10, $hoje);
    $aVencer = DateHelper::addDaysForDatabase(10, $hoje);

    $filialA = insertReceberContrato($db, 'matrizes_filiais', ['chave' => $tenant, 'nome_fantasia' => 'Filial A']);
    $filialB = insertReceberContrato($db, 'matrizes_filiais', ['chave' => $tenant, 'nome_fantasia' => 'Filial B']);
    $cliente = insertReceberContrato($db, 'clientes', ['chave' => $tenant, 'foto' => '', 'nome_rsocial' => 'Cliente & Teste', 'data_cadastro' => $hoje]);
    $grupo = insertReceberContrato($db, 'grupos', ['chave' => $tenant, 'nome' => 'Grupo Teste']);
    $veiculo = insertReceberContrato($db, 'veiculos', ['chave' => $tenant, 'id_matriz_filial' => $filialA, 'id_grupo' => $grupo, 'placa' => 'RCT1A23', 'marca' => 'Marca', 'modelo' => 'Modelo', 'diagrama' => 'Sedan.jpg']);

    $baseContrato = [
        'chave' => $tenant,
        'id_matriz_filial_retirada' => $filialA,
        'id_cliente' => $cliente,
        'data_ini' => $inicio . ' 08:00:00',
        'data_fim' => $fim . ' 18:00:00',
        'contagem' => 'Mensal',
        'dias' => 30,
    ];
    $finalizado = insertReceberContrato($db, 'contratos', $baseContrato + ['codigo' => 'RCTFINAL', 'status' => 'F']);
    $ativo = insertReceberContrato($db, 'contratos', $baseContrato + ['codigo' => 'RCTATIVO', 'status' => 'A']);

    foreach ([$finalizado, $ativo] as $contratoId) {
        insertReceberContrato($db, 'contratos_veiculos', [
            'chave' => $tenant,
            'id_contrato' => $contratoId,
            'id_veiculo' => $veiculo,
            'id_grupo' => $grupo,
            'data_saida' => $inicio . ' 08:00:00',
            'plano' => 'KL',
        ]);
    }

    $criarFinanceiro = static function (int $contratoId, string $codigo, string $data, float $valor, string $pago = 'N', string $tipo = 'R') use ($db, $tenant, $filialA, $cliente, $hoje, $veiculo): int {
        return insertReceberContrato($db, 'financeiro', [
            'chave' => $tenant,
            'codigo' => $codigo,
            'id_matriz_filial' => $filialA,
            'id_cliente' => $cliente,
            'id_contrato' => $contratoId ?: null,
            'id_veiculo' => $veiculo,
            'descricao' => 'Parcela ' . $codigo,
            'tipo' => $tipo,
            'pago' => $pago,
            'data_criada' => $hoje,
            'data_venci' => $data,
            'parcela' => 1,
            'total_parcelas' => 1,
            'valor_subtotal' => $valor,
            'valor_total' => $valor,
        ]);
    };

    $criarFinanceiro($finalizado, 'FAT-RCF-1', $vencida, 100);
    $criarFinanceiro($finalizado, 'FAT-RCF-2', $aVencer, 200);
    $criarFinanceiro($ativo, 'FAT-RCA-1', $vencida, 50);
    $criarFinanceiro($ativo, 'FAT-PAGA', $vencida, 900, 'S');
    $criarFinanceiro($ativo, 'FAT-DESP', $vencida, 800, 'N', 'D');
    $criarFinanceiro(0, 'FAT-AVULSA', $vencida, 700);

    $filialOutro = insertReceberContrato($db, 'matrizes_filiais', ['chave' => $outroTenant, 'nome_fantasia' => 'Filial Outro']);
    $clienteOutro = insertReceberContrato($db, 'clientes', ['chave' => $outroTenant, 'foto' => '', 'nome_rsocial' => 'Outro Tenant', 'data_cadastro' => $hoje]);
    $contratoOutro = insertReceberContrato($db, 'contratos', [
        'chave' => $outroTenant,
        'codigo' => 'RCTOUTRO',
        'id_matriz_filial_retirada' => $filialOutro,
        'id_cliente' => $clienteOutro,
        'data_ini' => $inicio . ' 08:00:00',
        'data_fim' => $fim . ' 18:00:00',
        'contagem' => 'Mensal',
        'dias' => 30,
        'status' => 'A',
    ]);
    insertReceberContrato($db, 'financeiro', [
        'chave' => $outroTenant,
        'codigo' => 'FAT-OUTRO',
        'id_matriz_filial' => $filialOutro,
        'id_cliente' => $clienteOutro,
        'id_contrato' => $contratoOutro,
        'tipo' => 'R',
        'pago' => 'N',
        'data_criada' => $hoje,
        'data_venci' => $vencida,
        'valor_subtotal' => 999,
        'valor_total' => 999,
    ]);

    $model = new FaturasReport();
    $result = $model->contasReceberPorContrato($inicio, $fim, 'id_matriz_filial IN (?)', [$filialA]);
    expectReceberContrato($result['totals']['total_aberto'] === 350.0, 'soma somente receitas pendentes vinculadas');
    expectReceberContrato($result['totals']['total_vencido'] === 150.0, 'total vencido');
    expectReceberContrato($result['totals']['total_a_vencer'] === 200.0, 'total a vencer');
    expectReceberContrato($result['totals']['total_contratos'] === 2, 'agrupa por contrato');
    expectReceberContrato($result['totals']['total_clientes'] === 1, 'conta clientes distintos');
    expectReceberContrato($result['details'][0]['contrato'] === 'RCTFINAL', 'ordena pelo maior vencido');
    expectReceberContrato($result['details'][0]['contrato_status'] === 'finalizado', 'mantem contrato finalizado com saldo');
    expectReceberContrato(count($result['details'][0]['parcelas']) === 2, 'expande parcelas do contrato');
    expectReceberContrato(str_contains($result['details'][0]['veiculo_resumo'], 'RCT1A23'), 'exibe veiculo vinculado');

    $somenteVencidas = $model->contasReceberPorContrato($inicio, $fim, '1=1', [], '', '', '', '', 'vencidas');
    expectReceberContrato($somenteVencidas['totals']['total_aberto'] === 150.0, 'filtro de vencidas');
    $somenteAVencer = $model->contasReceberPorContrato($inicio, $fim, '1=1', [], '', '', '', '', 'a_vencer');
    expectReceberContrato($somenteAVencer['totals']['total_aberto'] === 200.0, 'filtro a vencer');
    $porContrato = $model->contasReceberPorContrato($inicio, $fim, '1=1', [], '', '', 'RCTATIVO');
    expectReceberContrato($porContrato['totals']['total_aberto'] === 50.0, 'filtro por contrato');
    $porVeiculo = $model->contasReceberPorContrato($inicio, $fim, '1=1', [], '', '', '', (string) $veiculo);
    expectReceberContrato($porVeiculo['totals']['total_aberto'] === 350.0, 'filtro por veiculo');
    $semAcessoFilial = $model->contasReceberPorContrato($inicio, $fim, 'id_matriz_filial IN (?)', [$filialB]);
    expectReceberContrato($semAcessoFilial['totals']['total_aberto'] === 0.0, 'respeita restricao de filial');

    $i18nKeys = [
        'title', 'total_open', 'total_overdue', 'total_upcoming', 'total_contracts', 'total_clients',
        'contract_finalized_open', 'contract_active', 'days_overdue', 'next_due', 'open_installments',
        'view_installments', 'view_contract', 'overdue', 'upcoming', 'payment_link', 'payment_link_error',
        'col_invoice', 'col_description', 'col_due_date', 'col_original_value', 'col_fees',
        'col_total_value', 'col_status', 'col_action', 'contract_label',
    ];
    foreach ($i18nKeys as $i18nKey) {
        $translationKey = 'modules.relatorios.faturas.receber_por_contrato.' . $i18nKey;
        expectReceberContrato(t($translationKey) !== $translationKey, "traducao JavaScript resolvida: {$i18nKey}");
    }

    $viewSource = file_get_contents(APP_ROOT . '/app/Views/pages/relatorios/faturas/receber-por-contrato.php');
    expectReceberContrato(
        !str_contains($viewSource, "json_encode(t('modules.relatorios.faturas.receber_por_contrato'),"),
        'view nao usa chave intermediaria como pacote de traducoes'
    );
    expectReceberContrato(str_contains($viewSource, 'const i18n = {'), 'view monta objeto JavaScript de traducoes');

    $titulo = t('modules.relatorios.faturas.receber_por_contrato.title');
    $descricao = t('modules.relatorios.faturas.receber_por_contrato.description');
    $dataInicio = $inicio;
    $dataFim = $fim;
    $empresa = ['nome' => 'Empresa Teste', 'logo' => ''];
    $usuario = 'Teste';
    $totals = $result['totals'];
    $details = $result['details'];
    ob_start();
    include APP_ROOT . '/app/Views/pages/relatorios/imprimir/faturas/receber-por-contrato.php';
    $html = (string) ob_get_clean();
    expectReceberContrato(str_contains($html, 'Cliente &amp; Teste'), 'PDF escapa dados do cliente');
    $pdf = PdfHelper::generateAsString($html, ['orientation' => 'L', 'watermark' => false]);
    expectReceberContrato(str_starts_with($pdf, '%PDF-'), 'gera PDF valido');
} finally {
    $db->rollback();
    $_SESSION = $oldSession;
}
