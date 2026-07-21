<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Controllers\ContratosController;
use App\Controllers\LocacoesController;
use App\I18n\TemplateRenderer;
use App\I18n\TemplateVariables;

function assertCaucaoObservacoesSame(string $expected, string $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . ' Esperado: ' . var_export($expected, true) . ' Obtido: ' . var_export($actual, true)
        );
    }
}

$observacoesContrato = 'Caução recebida via transferência bancária.';
$contratosController = new ContratosController();
$buildContratoContext = new ReflectionMethod($contratosController, 'buildDocumentoContext');
$contratoContext = $buildContratoContext->invoke($contratosController, [
    'caucao_observacoes' => $observacoesContrato,
], [], null);

assertCaucaoObservacoesSame(
    $observacoesContrato,
    (string) ($contratoContext['contrato']['caucao_observacoes'] ?? ''),
    'O contexto de documentos do contrato deve receber as observações da caução.'
);
assertCaucaoObservacoesSame(
    $observacoesContrato,
    (new TemplateRenderer('pt_BR'))->render('{{contrato.caucao_observacoes}}', $contratoContext),
    'O TemplateRenderer deve substituir as observações da caução do contrato.'
);

$observacoesLocacao = 'Devolver a caução na mesma conta de origem.';
$locacoesController = new LocacoesController();
$buildLocacaoContext = new ReflectionMethod($locacoesController, 'buildDocumentoContext');
$locacaoContext = $buildLocacaoContext->invoke($locacoesController, [
    'caucao_observacoes' => $observacoesLocacao,
], [], null);

assertCaucaoObservacoesSame(
    $observacoesLocacao,
    (string) ($locacaoContext['locacao']['caucao_observacoes'] ?? ''),
    'O contexto de documentos da locação deve receber as observações da caução.'
);
assertCaucaoObservacoesSame(
    $observacoesLocacao,
    (new TemplateRenderer('pt_BR'))->render('{{locacao.caucao_observacoes}}', $locacaoContext),
    'O TemplateRenderer deve substituir as observações da caução da locação.'
);

$renderer = new TemplateRenderer('pt_BR');
assertCaucaoObservacoesSame(
    '',
    $renderer->render('{{contrato.caucao_observacoes}}', ['contrato' => ['caucao_observacoes' => '']]),
    'Observações vazias da caução do contrato devem renderizar texto vazio.'
);
assertCaucaoObservacoesSame(
    '',
    $renderer->render('{{locacao.caucao_observacoes}}', ['locacao' => ['caucao_observacoes' => '']]),
    'Observações vazias da caução da locação devem renderizar texto vazio.'
);

foreach (['pt_BR', 'pt_PT', 'en_US', 'es_ES', 'it_IT'] as $locale) {
    $frontendVariables = TemplateVariables::getForFrontend($locale);

    foreach (['contrato', 'locacao'] as $entity) {
        $variables = array_column($frontendVariables[$entity]['variables'] ?? [], null, 'variable');
        $placeholder = "{{{$entity}.caucao_observacoes}}";
        $variable = $variables[$placeholder] ?? null;

        if ($variable === null) {
            throw new RuntimeException("A variável {$placeholder} deve aparecer no painel de documentos em {$locale}.");
        }

        if (($variable['label'] ?? '') === "variables.{$entity}.caucao_observacoes") {
            throw new RuntimeException("O rótulo de {$placeholder} não foi traduzido em {$locale}.");
        }
    }
}

echo "Teste das variáveis de observações da caução passou.\n";
