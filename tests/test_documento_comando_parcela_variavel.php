<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\I18n\TemplateRenderer;
use App\I18n\TemplateVariables;

function assertDocumentoComandoParcelaSame(string $expected, string $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . ' Esperado: ' . var_export($expected, true) . ' Obtido: ' . var_export($actual, true)
        );
    }
}

function renderComandoParcela(string $comando, string $descricao = '', string $locale = 'pt_BR'): string
{
    return (new TemplateRenderer($locale))->render('{{contrato.comando_parcela}}', [
        'contrato' => [
            'comando_parcela_comando' => $comando,
            'comando_parcela_descricao' => $descricao,
        ],
    ]);
}

$casosPtBr = [
    '0' => 'Pagamento à vista',
    '7' => 'Pagamento único com vencimento em 7 dias',
    '1-12' => 'Parcelamento mensal de 1 a 12 parcelas',
    '7/14/21/28' => '4 parcelas com vencimentos em 7, 14, 21 e 28 dias',
    '0/7/14/21' => '4 parcelas: a primeira no ato e as demais em 7, 14 e 21 dias',
    'w4' => 'semanal',
    'w4-Seg' => 'segundas-feiras',
    'w24-Qua' => 'quartas-feiras',
    'd15' => 'vencimento no dia 15',
    'Ter' => 'terça-feira',
];

foreach ($casosPtBr as $comando => $esperado) {
    assertDocumentoComandoParcelaSame(
        $esperado,
        renderComandoParcela($comando),
        "O comando {$comando} deve ser exibido em formato amigável."
    );
}

assertDocumentoComandoParcelaSame(
    'Não informado',
    renderComandoParcela(''),
    'Contrato sem comando deve informar a ausência da configuração.'
);

assertDocumentoComandoParcelaSame(
    'Condição personalizada',
    renderComandoParcela('comando-customizado', 'Condição personalizada'),
    'Formato desconhecido deve usar a descrição cadastrada.'
);

assertDocumentoComandoParcelaSame(
    'Não informado',
    renderComandoParcela('comando-customizado'),
    'Formato desconhecido sem descrição não deve exibir o código técnico.'
);

assertDocumentoComandoParcelaSame(
    'Mondays',
    renderComandoParcela('w52-Seg', '', 'en_US'),
    'O dia semanal deve respeitar o idioma do documento.'
);

foreach (['pt_BR', 'pt_PT', 'en_US', 'es_ES', 'it_IT'] as $locale) {
    foreach (array_keys($casosPtBr) as $comando) {
        $resultado = renderComandoParcela($comando, '', $locale);
        if ($resultado === '' || str_contains($resultado, 'variables.installment_command.')) {
            throw new RuntimeException("O comando {$comando} não foi traduzido corretamente para {$locale}.");
        }
    }
}

$frontendVariables = TemplateVariables::getForFrontend('pt_BR');
$contratoVariables = array_column($frontendVariables['contrato']['variables'] ?? [], null, 'variable');

if (!isset($contratoVariables['{{contrato.comando_parcela}}'])) {
    throw new RuntimeException('A variável amigável deve aparecer no painel de variáveis do documento.');
}

assertDocumentoComandoParcelaSame(
    'Comando de Parcela (Texto Amigável)',
    (string) $contratoVariables['{{contrato.comando_parcela}}']['label'],
    'O painel deve exibir o rótulo amigável da variável.'
);

echo "Teste da variável amigável de comando de parcela passou.\n";
