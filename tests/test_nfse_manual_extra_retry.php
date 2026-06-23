<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\NFSe\NFSeService;

function assertBoolSame(bool $expected, bool $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}

$service = new NFSeService();
$method = new ReflectionMethod(NFSeService::class, 'permiteTentativaExtraManual');
$method->setAccessible(true);

$base = [
    'codigo_rejeicao' => 'XML_INVALIDO',
    'id_financeiro' => 123,
    'motivo_rejeicao' => "Falha no esquema XML do DF-e. List of possible elements expected: 'cLocalidadeIncid'. Código: E1235",
];

assertBoolSame(true, $method->invoke($service, $base), 'Erro Betha cLocalidadeIncid deve liberar tentativa extra manual.');

$semFinanceiro = $base;
$semFinanceiro['id_financeiro'] = null;
assertBoolSame(false, $method->invoke($service, $semFinanceiro), 'Sem financeiro vinculado nao deve liberar tentativa extra.');

$codigoDiferente = $base;
$codigoDiferente['codigo_rejeicao'] = 'ERRO_DESCONHECIDO';
assertBoolSame(true, $method->invoke($service, $codigoDiferente), 'Erro tecnico de XML deve liberar tentativa extra mesmo se o ultimo codigo foi sobrescrito.');

$erroNaoTecnico = $base;
$erroNaoTecnico['codigo_rejeicao'] = 'ERRO_DESCONHECIDO';
$erroNaoTecnico['motivo_rejeicao'] = 'Cliente sem email cadastrado.';
assertBoolSame(false, $method->invoke($service, $erroNaoTecnico), 'Erro nao tecnico nao deve liberar tentativa extra.');

echo "Teste de tentativa extra manual NFS-e passou.\n";
