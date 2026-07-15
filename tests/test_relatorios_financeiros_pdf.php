<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Helpers\PdfHelper;

if (!function_exists('t')) {
    function t(string $key): string
    {
        return $key;
    }
}

if (!function_exists('currency_format')) {
    function currency_format(float|int|string|null $value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $date): string
    {
        return $date ?? '';
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime(?string $datetime): string
    {
        return $datetime ?? '';
    }
}

if (!function_exists('now')) {
    function now(): string
    {
        return '2026-07-15 12:00:00';
    }
}

function assertReportPdf(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function renderFinanceiroPdfTemplate(string $template, array $totals, array $details): string
{
    $titulo = 'Relatorio de teste';
    $descricao = 'Teste automatizado';
    $dataInicio = '2026-07-01';
    $dataFim = '2026-07-31';
    $empresa = ['nome' => 'Locadora Teste', 'logo' => null];
    $usuario = 'Teste automatizado';
    $viewPath = dirname(__DIR__) . '/app/Views/pages/relatorios/imprimir/financeiro/' . $template;

    ob_start();
    include $viewPath;
    return (string) ob_get_clean();
}

$cases = [
    'faturamento.php' => [
        'totals' => [
            'faturamento_bruto' => 1000,
            'descontos' => 100,
            'faturamento_liquido' => 900,
            'total_lancamentos' => 3,
        ],
        'details' => [
            'por_origem' => [['nome' => 'locacao', 'qtd' => 3, 'valor' => 1000, 'percentual' => 100]],
            'por_forma_pagamento' => [['nome' => 'Pix', 'qtd' => 3, 'valor' => 1000, 'percentual' => 100]],
        ],
        'expected' => '3',
    ],
    'contas-bancarias.php' => [
        'totals' => ['total_entradas' => 500, 'total_saidas' => 100, 'saldo_geral' => 400, 'total_contas' => 2],
        'details' => [['conta' => 'Principal', 'banco' => 'Banco', 'entradas' => 500, 'saidas' => 100, 'saldo' => 400]],
        'expected' => '2',
    ],
    'plano-contas.php' => [
        'totals' => ['total_receitas' => 500, 'total_despesas' => 100, 'total_categorias' => 4],
        'details' => [['codigo' => '1.1', 'descricao' => 'Receitas', 'tipo' => 'R', 'valor' => 500, 'percentual' => 100]],
        'expected' => '4',
    ],
    'projecao-receitas.php' => [
        'totals' => ['receita_confirmada' => 600, 'receita_projetada' => 400, 'receita_total' => 1000, 'contratos_ativos' => 2],
        'details' => [['mes' => '07/2026', 'confirmada' => 600, 'projetada' => 400, 'total' => 1000]],
        'expected' => 'R$ 1.000,00',
    ],
    'inadimplencia.php' => [
        'totals' => ['total_a_receber' => 1000, 'total_vencido' => 300, 'taxa_inadimplencia' => 30, 'total_clientes' => 1],
        'details' => [
            'aging' => [
                'faixa_1_15' => 100,
                'faixa_16_30' => 0,
                'faixa_31_60' => 50,
                'faixa_61_90' => 0,
                'faixa_90_plus' => 150,
            ],
            'devedores' => [['cliente' => 'Cliente <Teste>', 'valor_vencido' => 300, 'faturas' => 2, 'maior_atraso' => 95]],
        ],
        'expected' => 'Cliente &lt;Teste&gt;',
    ],
];

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    foreach ($cases as $template => $case) {
        $html = renderFinanceiroPdfTemplate($template, $case['totals'], $case['details']);
        assertReportPdf(str_contains($html, $case['expected']), "Template {$template} nao exibiu os dados esperados.");

        $pdf = PdfHelper::generateAsString($html, ['watermark' => false]);
        assertReportPdf(str_starts_with($pdf, '%PDF-'), "Template {$template} nao gerou um PDF valido.");
    }
} finally {
    restore_error_handler();
}

echo "OK: PDFs dos relatorios financeiros renderizados sem chaves indefinidas.\n";
