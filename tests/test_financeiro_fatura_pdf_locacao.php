<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (!function_exists('t')) {
    function t(string $key, array $replace = []): string
    {
        $translations = [
            'modules.financeiro.print_pdf.title' => 'Fatura :number',
            'modules.financeiro.print_pdf.invoice' => 'FATURA',
            'modules.financeiro.print_pdf.default_company' => 'Locadora',
            'modules.financeiro.print_pdf.company_tax_id' => 'CNPJ',
            'modules.financeiro.print_pdf.zip' => 'CEP',
            'modules.financeiro.print_pdf.phone_short' => 'Tel',
            'modules.financeiro.print_pdf.number' => 'Número',
            'modules.financeiro.print_pdf.issue_date' => 'Emissão',
            'modules.financeiro.print_pdf.due_date' => 'Vencimento',
            'modules.financeiro.print_pdf.paid_at' => 'Pago em',
            'modules.financeiro.print_pdf.customer' => 'Cliente',
            'modules.financeiro.print_pdf.supplier' => 'Fornecedor',
            'modules.financeiro.print_pdf.name' => 'Nome',
            'modules.financeiro.print_pdf.tax_id' => 'CPF/CNPJ',
            'modules.financeiro.print_pdf.address' => 'Endereço',
            'modules.financeiro.print_pdf.city_state' => 'Cidade/UF',
            'modules.financeiro.print_pdf.email' => 'E-mail',
            'modules.financeiro.print_pdf.phone' => 'Telefone',
            'modules.financeiro.print_pdf.description' => 'Descrição',
            'modules.financeiro.print_pdf.vehicles' => 'Veículo(s)',
            'modules.financeiro.print_pdf.items' => 'Itens',
            'modules.financeiro.print_pdf.value' => 'Valor',
            'modules.financeiro.print_pdf.subtotal' => 'Subtotal',
            'modules.financeiro.print_pdf.interest' => 'Juros',
            'modules.financeiro.print_pdf.penalty' => 'Multa',
            'modules.financeiro.print_pdf.discount' => 'Desconto',
            'modules.financeiro.print_pdf.total' => 'TOTAL',
            'modules.financeiro.print_pdf.observations' => 'Observações',
            'modules.financeiro.print_pdf.online_payment_link' => 'Link para pagamento online',
            'modules.financeiro.print_pdf.generated_at' => 'Gerado em :date',
            'modules.financeiro.print_pdf.status_paid' => 'PAGO',
            'modules.financeiro.print_pdf.status_overdue' => 'VENCIDO',
            'modules.financeiro.print_pdf.status_open' => 'EM ABERTO',
            'modules.financeiro.print_pdf.rental_service_invoice_title' => 'Fatura de serviço locação do veículo sem condutor',
            'modules.financeiro.print_pdf.rental_service_cnae' => 'Código do serviço / Atividade / CNAE: 7711-0/00',
            'modules.financeiro.print_pdf.rental_service_tax_notice' => 'NÃO RETENÇÃO DE IMPOSTOS SOBRE SERVIÇOS, Lei complementar nº 116/2003, alterou a legislação sobre ISS, vetou a cobrança do imposto municipal sobre locação de bens móveis.',
        ];

        $translation = $translations[$key] ?? $key;
        foreach ($replace as $name => $value) {
            $translation = str_replace(':' . $name, (string) $value, $translation);
        }

        return $translation;
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
        return $date ? '17/07/2026' : '-';
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime(?string $date): string
    {
        return $date ? '17/07/2026 10:00' : '-';
    }
}

if (!function_exists('now')) {
    function now(): string
    {
        return '2026-07-17 10:00:00';
    }
}

if (!function_exists('today')) {
    function today(): string
    {
        return '2026-07-17';
    }
}

if (!function_exists('locale_info')) {
    function locale_info(): array
    {
        return ['code' => 'pt-BR'];
    }
}

function assertFinanceiroFaturaLocacao(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function renderFinanceiroFatura(array $lancamento): string
{
    $empresa = [
        'nome_fantasia' => 'Locadora Teste',
        'cpf_cnpj' => '00.000.000/0001-00',
        'telefone' => '+55 11 98888-7777',
    ];
    $cliente = ['nome_rsocial' => 'Cliente Teste', 'cpf_cnpj' => '000.000.000-00'];
    $fornecedor = [];
    $contraparte = $cliente;
    $tipoReceita = true;
    $logoPath = null;
    $linkPagamento = null;
    $qrPath = null;
    $descricaoLancamentoPdf = 'Parcela mensal';
    $veiculosLancamentoPdf = [];

    ob_start();
    include dirname(__DIR__) . '/app/Views/pages/financeiro/imprimir/fatura.php';

    return (string) ob_get_clean();
}

$baseLancamento = [
    'id' => 1,
    'codigo' => 'FAT001',
    'tipo' => 'R',
    'pago' => 'N',
    'data_criada' => '2026-07-17',
    'data_venci' => '2026-07-24',
    'valor_total' => 1000,
    'descricao' => 'Parcela mensal',
    'itens' => [
        ['descricao' => 'Diária', 'valor' => 1000],
    ],
];

$htmlSemVinculo = renderFinanceiroFatura($baseLancamento);
assertFinanceiroFaturaLocacao(str_contains($htmlSemVinculo, '+55 11 98888-7777'), 'Fatura deve exibir o telefone normalizado da empresa.');
assertFinanceiroFaturaLocacao(!str_contains($htmlSemVinculo, 'Fatura de serviço locação do veículo sem condutor'), 'Fatura sem vinculo nao deve exibir titulo de locacao.');
assertFinanceiroFaturaLocacao(!str_contains($htmlSemVinculo, '7711-0/00'), 'Fatura sem vinculo nao deve exibir CNAE.');
assertFinanceiroFaturaLocacao(!str_contains($htmlSemVinculo, 'NÃO RETENÇÃO DE IMPOSTOS'), 'Fatura sem vinculo nao deve exibir aviso de ISS.');

$htmlContrato = renderFinanceiroFatura($baseLancamento + ['id_contrato' => 25]);
assertFinanceiroFaturaLocacao(str_contains($htmlContrato, '<div class="fatura-locacao-titulo">'), 'Fatura de contrato deve exibir titulo de locacao.');
assertFinanceiroFaturaLocacao(str_contains($htmlContrato, 'Fatura de serviço locação do veículo sem condutor'), 'Titulo de locacao deve ser renderizado.');
assertFinanceiroFaturaLocacao(str_contains($htmlContrato, '7711-0/00'), 'Fatura de contrato deve exibir CNAE acima dos itens.');
assertFinanceiroFaturaLocacao(str_contains($htmlContrato, 'NÃO RETENÇÃO DE IMPOSTOS SOBRE SERVIÇOS'), 'Fatura de contrato deve exibir aviso de ISS no final.');

$htmlLocacao = renderFinanceiroFatura($baseLancamento + ['id_locacao' => 10]);
assertFinanceiroFaturaLocacao(str_contains($htmlLocacao, '<div class="fatura-locacao-titulo">'), 'Fatura de locacao deve exibir titulo de locacao.');
assertFinanceiroFaturaLocacao(str_contains($htmlLocacao, '7711-0/00'), 'Fatura de locacao deve exibir CNAE.');
assertFinanceiroFaturaLocacao(str_contains($htmlLocacao, 'Lei complementar nº 116/2003'), 'Fatura de locacao deve exibir aviso de ISS.');

$cnaePos = strpos($htmlContrato, '7711-0/00');
$itensPos = strpos($htmlContrato, '>Itens</div>');
assertFinanceiroFaturaLocacao($cnaePos !== false && $itensPos !== false && $cnaePos < $itensPos, 'CNAE deve aparecer acima da secao de itens.');

$issPos = strpos($htmlContrato, 'NÃO RETENÇÃO DE IMPOSTOS');
$footerPos = strpos($htmlContrato, 'class="footer"');
assertFinanceiroFaturaLocacao($issPos !== false && $footerPos !== false && $issPos < $footerPos, 'Aviso de ISS deve aparecer antes do rodape.');

echo "OK: textos legais da fatura financeira vinculada a contrato/locacao renderizados corretamente.\n";
