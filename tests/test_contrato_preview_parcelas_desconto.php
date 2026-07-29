<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Models\Contrato;

final class ContratoPreviewParcelasFake extends Contrato
{
    public function __construct()
    {
    }

    public function buscarPorId(int $id): ?array
    {
        return [
            'id' => $id,
            'total_pagar' => 2000.00,
            'data_fim' => '2026-08-28',
        ];
    }
}

function assertContratoPreviewSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . ' Esperado: ' . var_export($expected, true) . ' Obtido: ' . var_export($actual, true)
        );
    }
}

function assertContratoPreviewMoney(float $expected, mixed $actual, string $message): void
{
    if (abs($expected - (float) $actual) >= 0.005) {
        throw new RuntimeException(
            $message . ' Esperado: ' . number_format($expected, 2, '.', '')
            . ' Obtido: ' . number_format((float) $actual, 2, '.', '')
        );
    }
}

$contrato = new ContratoPreviewParcelasFake();
$configBase = [
    'id_conta' => 0,
    'id_forma_pagamento' => 0,
    'id_comando_parcela' => 0,
    'primeiro_vencimento' => '2026-07-29',
    'valor_desconto' => 0.10,
];

$previewExistente = $contrato->gerarPreviewParcelas(33649, $configBase);
assertContratoPreviewSame(1, count($previewExistente['parcelas']), 'Contrato existente deve gerar uma parcela.');
assertContratoPreviewMoney(
    2000.00,
    $previewExistente['parcelas'][0]['valor_total'],
    'Contrato existente nao deve reaplicar o desconto.'
);
assertContratoPreviewMoney(
    2000.00,
    $previewExistente['resumo']['valor_base'],
    'Base do contrato existente deve ser o total a pagar liquido.'
);
assertContratoPreviewMoney(
    0.10,
    $previewExistente['resumo']['desconto'],
    'Desconto deve permanecer disponivel no resumo.'
);

$previewStateless = $contrato->gerarPreviewParcelas(0, $configBase + [
    'total_pagar' => 2000.00,
    'data_fim' => '2026-08-28',
]);
assertContratoPreviewMoney(
    2000.00,
    $previewStateless['parcelas'][0]['valor_total'],
    'Preview stateless nao deve reaplicar o desconto.'
);

$previewParcelado = $contrato->gerarPreviewParcelas(0, $configBase + [
    'total_pagar' => 2000.00,
    'data_fim' => '2026-10-28',
    'num_parcelas' => 3,
]);
$somaParcelas = array_sum(array_column($previewParcelado['parcelas'], 'valor_total'));
assertContratoPreviewSame(3, count($previewParcelado['parcelas']), 'Preview deve respeitar a quantidade de parcelas.');
assertContratoPreviewMoney(2000.00, $somaParcelas, 'Soma das parcelas deve fechar o total a pagar.');
assertContratoPreviewMoney(
    666.66,
    $previewParcelado['parcelas'][2]['valor_total'],
    'Ultima parcela deve absorver a diferenca de arredondamento.'
);

$previewSemDesconto = $contrato->gerarPreviewParcelas(0, [
    'total_pagar' => 2000.00,
    'data_fim' => '2026-08-28',
    'id_conta' => 0,
    'id_forma_pagamento' => 0,
    'id_comando_parcela' => 0,
    'primeiro_vencimento' => '2026-07-29',
    'valor_desconto' => 0,
]);
assertContratoPreviewMoney(
    2000.00,
    $previewSemDesconto['parcelas'][0]['valor_total'],
    'Contrato sem desconto deve manter o comportamento anterior.'
);

echo "Teste de desconto no preview de parcelas do contrato passou.\n";
