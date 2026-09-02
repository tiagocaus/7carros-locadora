<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\NFSe as NFSeConfig;
use App\Services\NFSe\NFSeService;
use App\Models\NFSeEvento;

class FakeNFSeEventoTentativaExtra extends NFSeEvento
{
    public function __construct(private array $eventos = [])
    {
    }

    public function listarPorNfse(int $idNfse): array
    {
        return $this->eventos;
    }
}

function assertBoolSame(bool $expected, bool $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}

if (NFSeConfig::MAX_ENVIOS !== 5 || NFSeConfig::MAX_ENVIOS_EXTRAS_MANUAIS !== 1) {
    throw new RuntimeException('A politica deve permitir cinco envios regulares e uma unica excecao manual.');
}

$service = (new ReflectionClass(NFSeService::class))->newInstanceWithoutConstructor();
$eventoProperty = new ReflectionProperty(NFSeService::class, 'eventoModel');
$eventoProperty->setAccessible(true);
$eventoProperty->setValue($service, new FakeNFSeEventoTentativaExtra());

$method = new ReflectionMethod(NFSeService::class, 'permiteTentativaExtraManual');
$method->setAccessible(true);

$base = [
    'id' => 456,
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

$eventoProperty->setValue($service, new FakeNFSeEventoTentativaExtra([
    [
        'tipo_evento' => 'reenvio_manual',
        'codigo_retorno' => 'LIMITE_TECNICO',
        'mensagem' => 'Tentativa manual extra liberada após correção técnica do XML/data fiscal.',
    ],
]));
assertBoolSame(false, $method->invoke($service, $base), 'A tentativa extra manual deve ser liberada uma unica vez.');

$eventoProperty->setValue($service, new FakeNFSeEventoTentativaExtra([
    [
        'tipo_evento' => 'erro',
        'codigo_retorno' => 'E1235',
        'mensagem' => "Falha no esquema XML. List of possible elements expected: 'cLocalidadeIncid'.",
    ],
]));
$erroTecnicoHistorico = $erroNaoTecnico;
assertBoolSame(true, $method->invoke($service, $erroTecnicoHistorico), 'Erro tecnico historico deve liberar a tentativa extra quando ela ainda nao foi usada.');

$eventoProperty->setValue($service, new FakeNFSeEventoTentativaExtra());
$erroCIndOp = $base;
$erroCIndOp['codigo_rejeicao'] = 'IBSCBS_CONFIGURACAO';
$erroCIndOp['motivo_rejeicao'] = 'E082 Código indicador de operação inválido';
assertBoolSame(true, $method->invoke($service, $erroCIndOp), 'E082 corrigido deve liberar a tentativa manual extra.');

echo "Teste de tentativa extra manual NFS-e passou.\n";
