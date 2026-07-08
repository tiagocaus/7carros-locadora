<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Controllers\MultasController;
use App\I18n\TemplateRenderer;

function assertMultaDocumentoContains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message . " Trecho nao encontrado: {$needle}. Render: {$haystack}");
    }
}

$controller = (new ReflectionClass(MultasController::class))->newInstanceWithoutConstructor();
$method = new ReflectionMethod(MultasController::class, 'buildDocumentoContextMulta');
$method->setAccessible(true);

$context = $method->invoke($controller, [
    'local' => 'Av. Brasil',
    'cidade' => 'Sao Paulo',
    'estado' => 'SP',
    'data_hora' => '2026-06-14 10:10:00',
    'data_vencimento' => '2026-06-30',
    'valor' => '100.00',
    'pago' => 'N',
    'descri' => 'Excesso de velocidade',
    'orgao_autuador' => 'DETRAN',
    'n_infracao' => 'TESTE',
    'cliente_nome' => 'Cliente Teste',
    'cliente_cpf_cnpj' => '12345678900',
    'veiculo_placa' => 'SAV0741',
    'veiculo_modelo' => '208 GRIFFE A',
    'veiculo_marca' => 'PEUGEOT',
    'veiculo_ano' => '2022/2022',
    'veiculo_cor' => 'BRANCA',
    'veiculo_renavam' => '01315130197',
    'veiculo_chassi' => '936SAV1SNNL770741',
    'veiculo_categoria' => 'A',
    'veiculo_tipo_combustivel' => 'FLEX',
    'veiculo_valor_compra' => '65000.00',
    'veiculo_valor_venda' => '58000.00',
], null, []);

$template = implode(' | ', [
    '{{veiculo.placa}}',
    '{{veiculo.renavam}}',
    '{{veiculo.cor}}',
    '{{veiculo.marca}}',
    '{{veiculo.modelo}}',
    '{{veiculo.ano}}',
    '{{veiculo.chassi}}',
    '{{veiculo.categoria}}',
    '{{veiculo.combustivel_tipo}}',
    '{{veiculo.valor_compra}}',
    '{{veiculo.valor_venda}}',
    '{{veiculo.descricao_completa}}',
]);

$rendered = (new TemplateRenderer('pt_BR'))->render($template, $context);

foreach ([
    'SAV0741',
    '01315130197',
    'BRANCA',
    'PEUGEOT',
    '208 GRIFFE A',
    '2022/2022',
    '936SAV1SNNL770741',
    'A',
    'FLEX',
    'R$ 65.000,00',
    'R$ 58.000,00',
    'PEUGEOT 208 GRIFFE A 2022/2022 - BRANCA - SAV0741',
] as $expected) {
    assertMultaDocumentoContains($expected, $rendered, 'Variavel de veiculo deve ser preenchida no documento de multa.');
}

echo "Teste de variaveis de veiculo em documento de multa passou.\n";
