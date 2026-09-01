<?php

/**
 * Teste do contexto de veiculo no PDF de Contas a Pagar e Receber.
 *
 * Execute: php tests/test_relatorio_pagar_receber_pdf_veiculo.php
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

use App\Controllers\Relatorios\FaturasController;
use App\Core\Database;
use App\Helpers\PdfHelper;
use App\Models\Veiculo;

$chave = 'TEST_REL_PAGAR_RECEBER_' . strtoupper(bin2hex(random_bytes(6)));
$outraChave = $chave . '_OUTRO';
$_SESSION['chave'] = $chave;
$falhas = 0;

function assertPagarReceberPdf(string $label, mixed $atual, mixed $esperado): void
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

function renderPagarReceberPdf(array $contextoPdf): string
{
    $titulo = 'Contas a Pagar e Receber';
    $descricao = 'Teste automatizado';
    $dataInicio = '2026-08-01';
    $dataFim = '2026-08-31';
    $empresa = ['nome' => 'Locadora Teste', 'logo' => null];
    $usuario = 'Teste automatizado';
    $totals = [
        'total_receber' => 100,
        'total_pagar' => 40,
        'saldo' => 60,
        'qtd_receber' => 1,
        'qtd_pagar' => 1,
    ];
    $details = ['receber' => [], 'pagar' => []];

    ob_start();
    include APP_ROOT . '/app/Views/pages/relatorios/imprimir/faturas/pagar-receber.php';
    return (string) ob_get_clean();
}

try {
    $veiculoId = Database::insertGetId('veiculos', [
        'chave' => $chave,
        'placa' => 'ABC&1D23',
        'marca' => 'Fiat',
        'modelo' => 'Fiorino',
    ]);
    $veiculoOutroTenantId = Database::insertGetId('veiculos', [
        'chave' => $outraChave,
        'placa' => 'XYZ9A99',
        'marca' => 'Ford',
        'modelo' => 'Ranger',
    ]);

    $model = new Veiculo();
    assertPagarReceberPdf(
        'model encontra veiculo do tenant atual',
        $model->buscarIdentificacaoPorId($veiculoId)['placa'] ?? null,
        'ABC&1D23'
    );
    assertPagarReceberPdf(
        'model nao expoe veiculo de outro tenant',
        $model->buscarIdentificacaoPorId($veiculoOutroTenantId),
        null
    );

    $controller = (new ReflectionClass(FaturasController::class))->newInstanceWithoutConstructor();
    $resolver = new ReflectionMethod(FaturasController::class, 'resolverVeiculoFiltro');
    $resolver->setAccessible(true);

    $identificacao = $resolver->invoke($controller, (string) $veiculoId);
    assertPagarReceberPdf(
        'controller formata placa, marca e modelo',
        $identificacao,
        'ABC&1D23 - Fiat Fiorino'
    );
    assertPagarReceberPdf(
        'controller ignora filtro vazio',
        $resolver->invoke($controller, ''),
        ''
    );
    assertPagarReceberPdf(
        'controller ignora veiculo de outro tenant',
        $resolver->invoke($controller, (string) $veiculoOutroTenantId),
        ''
    );

    $htmlSemFiltro = renderPagarReceberPdf([]);
    assertPagarReceberPdf(
        'template omite bloco sem veiculo',
        str_contains($htmlSemFiltro, t('modules.relatorios.common.applied_filters')),
        false
    );

    $htmlComFiltro = renderPagarReceberPdf(['veiculo_filtro' => $identificacao]);
    assertPagarReceberPdf(
        'template exibe rotulo de filtros aplicados',
        str_contains($htmlComFiltro, t('modules.relatorios.common.applied_filters')),
        true
    );
    assertPagarReceberPdf(
        'template escapa identificacao do veiculo',
        str_contains($htmlComFiltro, 'ABC&amp;1D23 - Fiat Fiorino'),
        true
    );

    $pdf = PdfHelper::generateAsString($htmlComFiltro, ['watermark' => false]);
    assertPagarReceberPdf('template gera PDF valido', str_starts_with($pdf, '%PDF-'), true);
} finally {
    Database::execute('DELETE FROM veiculos WHERE chave IN (?, ?)', [$chave, $outraChave]);
}

exit($falhas > 0 ? 1 : 0);
