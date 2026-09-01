<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Helpers\PdfHelper;

if (!function_exists('t')) {
    function t(string $key, array $replace = []): string
    {
        $translations = [
            'modules.locacoes.print.invoice' => 'Fatura',
            'modules.locacoes.print.rental_label' => 'Locacao',
            'modules.locacoes.pdf.invoice_title' => 'Fatura',
            'modules.locacoes.pdf.rental_label' => 'Locacao',
            'modules.locacoes.pdf.additional_driver' => 'Condutor Adicional',
            'modules.locacoes.pdf.invoice_composition' => 'Composicao da Fatura',
            'modules.locacoes.pdf.description_header' => 'Descricao',
            'modules.locacoes.pdf.qty_header' => 'Qtd',
            'modules.locacoes.pdf.unit_value_header' => 'Valor Unit.',
            'modules.locacoes.pdf.total_header' => 'Total',
            'modules.locacoes.pdf.subtotal_label' => 'Subtotal:',
            'modules.locacoes.pdf.discount_label' => 'Desconto:',
            'modules.locacoes.pdf.total_rental_label' => 'Total da locacao',
            'modules.locacoes.pdf.total_to_pay' => 'TOTAL A PAGAR:',
            'modules.locacoes.plans.km_free' => 'Km Livre',
        ];

        $translation = $translations[$key] ?? $key;
        foreach ($replace as $name => $value) {
            $translation = str_replace(':' . $name, (string) $value, $translation);
        }

        return $translation;
    }
}

if (!function_exists('t_choice')) {
    function t_choice(string $key, int $count, array $replace = []): string
    {
        return $count . ($count === 1 ? ' dia' : ' dias');
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
        return $date ? '01/09/2026' : '';
    }
}

if (!function_exists('format_operational_datetime')) {
    function format_operational_datetime(?string $date): string
    {
        return $date ? '01/09/2026 12:05' : '';
    }
}

if (!function_exists('today')) {
    function today(): string
    {
        return '2026-09-01';
    }
}

if (!function_exists('locale_info')) {
    function locale_info(): array
    {
        return ['code' => 'pt-BR'];
    }
}

function assertLocacaoFaturaCondutor(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function renderizarFaturaLocacaoCondutor(array $locacao, array $taxas): string
{
    $empresa = ['nome_fantasia' => 'Locadora Teste'];
    $veiculo = ['placa' => 'STK4C43', 'marca' => 'VW', 'modelo' => 'POLO'];
    $assinatura = null;
    $logoPath = null;
    $qrPath = null;
    $multas = [];
    $totalMultas = 0;
    $parcelasFinanceiras = [];
    $resumoFinanceiro = [];
    $referenciasFatura = [];
    $historicoVeiculos = [];
    $totaisResumoFatura = [];
    $_pdfFooterFixo = true;

    ob_start();
    include dirname(__DIR__) . '/app/Views/pages/locacoes/imprimir/fatura.php';
    return (string) ob_get_clean();
}

$locacao = [
    'codigo' => 'LXWI8198',
    'sequencia' => 1,
    'status' => 'A',
    'created_at' => '2026-09-01 12:05:00',
    'data_saida' => '2026-09-01 12:05:00',
    'data_prevista' => '2026-09-08 04:00:00',
    'dias' => 7,
    'cliente_nome_completo' => 'Cliente Teste',
    'plano' => 'KL',
    'km_livre_valor' => 100,
    'seguro_carro' => 'N',
    'seguro_terceiros' => 'N',
    'condutor_adicional' => json_encode([['nome' => 'Condutor Teste']]),
    'valor_condutor_adicional' => 14.90,
    'valor_desconto' => 224.83,
    'total_fatura' => 966.83,
    'total_pagar' => 742,
    'bloqueio_status' => null,
];
$taxas = [
    [
        'nome' => 'CONDUTOR ADICIONAL',
        'quantidade' => 1,
        'valor_unitario' => 29.99,
        'valor_total' => 209.93,
    ],
    [
        'nome' => 'HIGIENIZACAO DO VEICULO',
        'quantidade' => 1,
        'valor_unitario' => 42,
        'valor_total' => 42,
    ],
];

$html = renderizarFaturaLocacaoCondutor($locacao, $taxas);

assertLocacaoFaturaCondutor(
    preg_match('/<tr>\s*<td>\s*Condutor Adicional.*?<td class="text-center">1<\/td>.*?R\$ 14,90.*?R\$ 14,90\s*<\/td>\s*<\/tr>/s', $html) === 1,
    'Cobranca nativa do condutor adicional nao foi detalhada na composicao.'
);
assertLocacaoFaturaCondutor(str_contains($html, 'R$ 209,93'), 'Taxa generica de condutor deve permanecer separada.');
assertLocacaoFaturaCondutor(str_contains($html, 'R$ 966,83'), 'Subtotal da locacao nao foi preservado.');

$locacaoSemCobranca = $locacao;
$locacaoSemCobranca['valor_condutor_adicional'] = 0;
$locacaoSemCobranca['total_fatura'] = 951.93;
$htmlSemCobranca = renderizarFaturaLocacaoCondutor($locacaoSemCobranca, $taxas);
assertLocacaoFaturaCondutor(
    preg_match('/<tr>\s*<td>\s*Condutor Adicional.*?R\$ 0,00.*?<\/tr>/s', $htmlSemCobranca) === 0,
    'Valor de condutor zerado nao deve criar linha monetaria.'
);

$locacaoDoisCondutores = $locacao;
$locacaoDoisCondutores['condutor_adicional'] = json_encode([
    ['nome' => 'Condutor Um'],
    ['nome' => 'Condutor Dois'],
]);
$locacaoDoisCondutores['total_fatura'] = 981.73;
$htmlDoisCondutores = renderizarFaturaLocacaoCondutor($locacaoDoisCondutores, $taxas);
assertLocacaoFaturaCondutor(
    preg_match('/<tr>\s*<td>\s*Condutor Adicional.*?<td class="text-center">2<\/td>.*?R\$ 14,90.*?R\$ 29,80\s*<\/td>\s*<\/tr>/s', $htmlDoisCondutores) === 1,
    'Quantidade e total de dois condutores nao foram renderizados corretamente.'
);

$pdf = PdfHelper::generateAsString($html, ['watermark' => false]);
assertLocacaoFaturaCondutor(str_starts_with($pdf, '%PDF-'), 'A fatura nao gerou um PDF valido.');

$qaOutput = getenv('PDF_QA_OUTPUT');
if (is_string($qaOutput) && $qaOutput !== '') {
    file_put_contents($qaOutput, $pdf);
}

echo "OK: cobranca de condutor adicional detalhada na fatura de locacao.\n";
