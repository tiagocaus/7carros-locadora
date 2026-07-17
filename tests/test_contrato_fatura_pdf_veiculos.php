<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Helpers\PdfHelper;

if (!function_exists('t')) {
    function t(string $key, array $replace = []): string
    {
        $translations = [
            'modules.contratos.pdf.vehicles_section' => 'Veículos',
            'modules.contratos.pdf.vehicle_header' => 'Veículo',
            'modules.contratos.pdf.plan_header' => 'Plano',
            'modules.contratos.pdf.withdrawal_header' => 'Retirada',
            'modules.contratos.pdf.return_details_header' => 'Devolução',
            'modules.contratos.pdf.value_header' => 'Valor',
            'modules.contratos.pdf.total_header' => 'Total',
            'modules.contratos.pdf.group_header' => 'Grupo',
            'modules.contratos.pdf.fuel_short_label' => 'Comb.',
            'modules.contratos.pdf.insurances_label' => 'Seguros',
            'modules.contratos.pdf.vehicle_insurance_short' => 'Veículo',
            'modules.contratos.pdf.third_party_insurance_short' => 'Terceiros',
            'modules.contratos.pdf.insurance_contracted' => 'Contratado',
            'modules.contratos.pdf.insurance_not_contracted' => 'Não contratado',
            'modules.contratos.pdf.counting_labels.day' => 'Dia',
            'modules.contratos.pdf.counting_labels.week' => 'Semana',
            'modules.contratos.pdf.counting_labels.month' => 'Mês',
            'modules.contratos.pdf.counting_labels.year' => 'Ano',
            'modules.contratos.vehicles.plan_km_free' => 'Km Livre',
            'modules.contratos.vehicles.plan_km_controlled' => 'Km Controlado',
            'modules.contratos.vehicles.plan_km_paid' => 'Km Pago',
            'modules.contratos.fuel_levels.full' => 'Cheio',
            'modules.contratos.fuel_levels.reserve' => 'Reserva',
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
        return $date ? '17/07/2026' : '';
    }
}

if (!function_exists('format_operational_datetime')) {
    function format_operational_datetime(?string $date): string
    {
        return $date ? '17/07/2026 10:00' : '';
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

function assertContratoFatura(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$contrato = [
    'codigo' => 'CTESTE',
    'sequencia' => 1,
    'status' => 'A',
    'created_at' => '2026-07-17 10:00:00',
    'data_ini' => '2026-07-17 10:00:00',
    'data_fim' => '2026-07-24 10:00:00',
    'dias' => 1,
    'contagem' => 'semana',
    'auto_renovacao' => '',
    'cliente_nome' => 'Cliente Teste',
    'condutor_adicional' => null,
    'array_fiadores' => null,
    'array_avalistas' => null,
    'array_testemunhas' => null,
    'taxas' => [],
    'total_fatura' => 1015,
    'total_pagar' => 1015,
    'veiculos' => [
        [
            'veiculo_marca' => 'Fiat',
            'veiculo_modelo' => 'Novo Uno <Way>',
            'veiculo_placa' => 'NCN1219',
            'grupo_nome' => 'A',
            'plano' => 'KMC',
            'valor_plano_km_controlado' => 560,
            'km_franquia' => 840,
            'seguro_carro' => 1,
            'valor_seguro_carro' => 105,
            'seguro_terceiros' => 1,
            'valor_seguro_terceiros' => 0,
            'odometro_saida' => 111111,
            'odometro_entrada' => null,
            'combustivel_saida' => 8,
            'combustivel_entrada' => null,
            'veiculo_tipo_combustivel' => 'GE',
        ],
        [
            'veiculo_marca' => 'Ford',
            'veiculo_modelo' => 'Ranger',
            'veiculo_placa' => 'ODJ5076',
            'grupo_nome' => 'C',
            'plano' => 'KP',
            'valor_plano_km_pago' => 350,
            'seguro_carro' => 0,
            'valor_seguro_carro' => 91,
            'seguro_terceiros' => 0,
            'valor_seguro_terceiros' => 105,
            'odometro_saida' => 121212,
            'odometro_entrada' => 125000,
            'combustivel_saida' => 8,
            'combustivel_entrada' => 4,
            'veiculo_tipo_combustivel' => 'GE',
        ],
    ],
];
$empresa = ['nome_fantasia' => 'Locadora Teste'];
$assinatura = null;
$logoPath = null;
$qrPath = null;
$_pdfFooterFixo = true;

ob_start();
include dirname(__DIR__) . '/app/Views/pages/contratos/imprimir/fatura.php';
$html = (string) ob_get_clean();

assertContratoFatura(str_contains($html, '>Retirada</th>'), 'Cabecalho Retirada nao foi renderizado.');
assertContratoFatura(str_contains($html, '>Devolução</th>'), 'Cabecalho Devolucao nao foi renderizado.');
assertContratoFatura(str_contains($html, 'Novo Uno &lt;Way&gt;'), 'Nome do veiculo nao foi escapado.');
assertContratoFatura(str_contains($html, 'NCN1219 · Grupo A'), 'Placa e grupo nao foram agrupados.');
assertContratoFatura(str_contains($html, '840 km/Semana'), 'Franquia por contagem nao foi renderizada.');
assertContratoFatura(str_contains($html, '111.111 km'), 'Odometro de retirada nao foi formatado.');
assertContratoFatura(str_contains($html, '125.000 km'), 'Odometro de devolucao nao foi formatado.');
assertContratoFatura(str_contains($html, 'Comb.: Cheio'), 'Combustivel de retirada nao foi renderizado.');
assertContratoFatura(str_contains($html, 'Comb.: 1/2'), 'Combustivel de devolucao nao foi renderizado.');
assertContratoFatura(str_contains($html, 'Veículo - Contratado (R$ 105,00)'), 'Seguro contratado com valor nao foi detalhado.');
assertContratoFatura(str_contains($html, 'Terceiros - Contratado'), 'Seguro contratado sem valor nao foi informado.');
assertContratoFatura(!str_contains($html, 'Contratado (R$ 0,00)'), 'Seguro sem valor nao deve exibir zero.');
assertContratoFatura(str_contains($html, 'Veículo - Não contratado'), 'Seguro desabilitado nao foi informado.');
assertContratoFatura(!str_contains($html, 'R$ 91,00'), 'Valor residual de seguro desabilitado foi exibido.');
assertContratoFatura(substr_count($html, 'class="vehicle-insurance-row"') === 2, 'Cada veiculo deve ter uma linha de seguros.');
assertContratoFatura(substr_count($html, 'R$ 350,00') >= 2, 'Plano sem seguros deve manter Valor e Total iguais.');

foreach (['dia' => 'Dia', 'semana' => 'Semana', 'mes' => 'Mês', 'ano' => 'Ano'] as $contagem => $label) {
    $contrato['contagem'] = $contagem;
    ob_start();
    include dirname(__DIR__) . '/app/Views/pages/contratos/imprimir/fatura.php';
    $htmlContagem = (string) ob_get_clean();
    assertContratoFatura(
        str_contains($htmlContagem, '840 km/' . $label),
        "Contagem {$contagem} nao foi exibida corretamente no plano Km Controlado."
    );
}

$translationKeys = [
    'withdrawal_header', 'return_details_header', 'value_header', 'fuel_short_label',
    'insurances_label', 'vehicle_insurance_short', 'third_party_insurance_short',
    'insurance_contracted', 'insurance_not_contracted', 'counting_labels',
];
foreach (['pt_BR', 'pt_PT', 'en_US', 'es_ES', 'it_IT'] as $locale) {
    $translations = require dirname(__DIR__) . "/app/Lang/{$locale}/modules/contratos.php";
    foreach ($translationKeys as $key) {
        assertContratoFatura(!empty($translations['pdf'][$key]), "Traducao pdf.{$key} ausente em {$locale}.");
    }
    foreach (['day', 'week', 'month', 'year'] as $period) {
        assertContratoFatura(!empty($translations['pdf']['counting_labels'][$period]), "Contagem {$period} ausente em {$locale}.");
    }
}

$pdf = PdfHelper::generateAsString($html, ['watermark' => false]);
assertContratoFatura(str_starts_with($pdf, '%PDF-'), 'A fatura nao gerou um PDF valido.');

echo "OK: listagem de veiculos da fatura de contrato renderizada e validada.\n";
