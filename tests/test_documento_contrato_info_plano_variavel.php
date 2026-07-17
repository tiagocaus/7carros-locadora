<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\ContratosController;
use App\I18n\TemplateRenderer;
use App\I18n\TemplateVariables;

function assertContratoInfoPlanoSame(string $expected, string $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . ' Esperado: ' . var_export($expected, true) . ' Obtido: ' . var_export($actual, true)
        );
    }
}

$controller = new ContratosController();
$formatar = new ReflectionMethod($controller, 'formatarInfoPlanoContratoDocumento');

$casos = [
    'sem veículos' => [[], ''],
    'um veículo Km Livre' => [[['plano' => 'KL', 'data_entrada' => null]], 'Km Livre'],
    'um veículo Km Controlado' => [[[
        'plano' => 'KMC',
        'km_franquia' => 300,
        'valor_km_excedente' => 2.5,
        'data_entrada' => null,
    ]], 'Km Controlado'],
    'um veículo Km Pago' => [[['plano' => 'KP', 'data_entrada' => null]], 'Km Pago'],
    'código legado DI' => [[['plano' => 'DI', 'data_entrada' => null]], 'Km Pago'],
    'vários veículos ativos com o mesmo plano' => [[
        ['plano' => 'KMC', 'data_entrada' => null],
        ['plano' => 'KMC', 'data_entrada' => null],
    ], 'Km Controlado'],
    'vários veículos ativos com planos diferentes' => [[
        ['plano' => 'KL', 'data_entrada' => null],
        ['plano' => 'KMC', 'data_entrada' => null],
    ], 'Conforme relação de veículos'],
    'veículo ativo tem prioridade sobre o histórico' => [[
        ['plano' => 'KMC', 'data_entrada' => '2026-07-01 10:00:00'],
        ['plano' => 'KL', 'data_entrada' => null],
    ], 'Km Livre'],
    'contrato finalizado usa o histórico' => [[
        ['plano' => 'KMC', 'data_entrada' => '2026-07-01 10:00:00'],
        ['plano' => 'KMC', 'data_entrada' => '2026-07-10 10:00:00'],
    ], 'Km Controlado'],
    'histórico finalizado com planos diferentes' => [[
        ['plano' => 'KL', 'data_entrada' => '2026-07-01 10:00:00'],
        ['plano' => 'KMC', 'data_entrada' => '2026-07-10 10:00:00'],
    ], 'Conforme relação de veículos'],
];

foreach ($casos as $descricao => [$veiculos, $esperado]) {
    assertContratoInfoPlanoSame(
        $esperado,
        (string) $formatar->invoke($controller, $veiculos),
        "Falha no caso: {$descricao}."
    );
}

$buildContext = new ReflectionMethod($controller, 'buildDocumentoContext');
$context = $buildContext->invoke($controller, [
    'veiculos' => [[
        'plano' => 'KMC',
        'km_franquia' => 300,
        'data_entrada' => null,
    ]],
], [], null);

assertContratoInfoPlanoSame(
    'Km Controlado',
    (string) ($context['contrato']['info_plano'] ?? ''),
    'O contexto de documentos do contrato deve receber o nome simples do plano.'
);

assertContratoInfoPlanoSame(
    'Km Controlado',
    (new TemplateRenderer('pt_BR'))->render('{{contrato.info_plano}}', $context),
    'O TemplateRenderer deve substituir a variável do plano do contrato.'
);

foreach (['pt_BR', 'pt_PT', 'en_US', 'es_ES', 'it_IT'] as $locale) {
    $frontendVariables = TemplateVariables::getForFrontend($locale);
    $contratoVariables = array_column($frontendVariables['contrato']['variables'] ?? [], null, 'variable');
    $variavel = $contratoVariables['{{contrato.info_plano}}'] ?? null;

    if ($variavel === null) {
        throw new RuntimeException("A variável deve aparecer no painel de documentos em {$locale}.");
    }

    if (($variavel['label'] ?? '') === 'variables.contrato.info_plano') {
        throw new RuntimeException("O rótulo da variável não foi traduzido em {$locale}.");
    }
}

echo "Teste da variável de plano do contrato passou.\n";
