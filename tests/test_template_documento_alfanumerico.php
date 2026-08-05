<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\I18n\TemplateRenderer;

function assertDocumentoTemplateSame(string $expected, string $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . ' Esperado: ' . var_export($expected, true) . ' Obtido: ' . var_export($actual, true)
        );
    }
}

$renderer = new TemplateRenderer('pt_BR');

$casos = [
    ['{{cliente.cpf_cnpj}}', ['cliente' => ['cpf_cnpj' => 'PAZ451648']], 'PAZ451648'],
    ['{{empresa.cnpj}}', ['empresa' => ['cnpj' => 'PT-ABC123']], 'PT-ABC123'],
    ['{{fornecedor.cpf_cnpj}}', ['fornecedor' => ['cpf_cnpj' => 'ES X1234567']], 'ES X1234567'],
    ['{{cliente.cpf_cnpj}}', ['cliente' => ['cpf_cnpj' => '12345678909']], '123.456.789-09'],
    ['{{cliente.cpf_cnpj}}', ['cliente' => ['cpf_cnpj' => '123.456.789-09']], '123.456.789-09'],
    ['{{empresa.cnpj}}', ['empresa' => ['cnpj' => '11222333000181']], '11.222.333/0001-81'],
    ['{{cliente.cpf_cnpj}}', ['cliente' => ['cpf_cnpj' => '451648']], '451648'],
];

foreach ($casos as [$template, $context, $expected]) {
    assertDocumentoTemplateSame(
        $expected,
        $renderer->render($template, $context),
        "Falha ao renderizar {$template}."
    );
}

echo "Teste de documentos alfanumericos em templates passou.\n";
