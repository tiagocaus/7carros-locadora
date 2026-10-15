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

if (NFSeConfig::MAX_ENVIOS !== 5
    || NFSeConfig::MAX_ENVIOS_EXTRAS_MANUAIS !== 1
    || NFSeConfig::MAX_RECUPERACOES_ENDERECO_XSD !== 1) {
    throw new RuntimeException('A politica deve permitir cinco envios regulares e uma unica excecao manual.');
}

$service = (new ReflectionClass(NFSeService::class))->newInstanceWithoutConstructor();
$eventoProperty = new ReflectionProperty(NFSeService::class, 'eventoModel');
$eventoProperty->setAccessible(true);
$eventoProperty->setValue($service, new FakeNFSeEventoTentativaExtra());

$method = new ReflectionMethod(NFSeService::class, 'permiteTentativaExtraManual');
$method->setAccessible(true);
$recuperacaoEndereco = new ReflectionMethod(NFSeService::class, 'permiteRecuperacaoEnderecoXsd');
$recuperacaoEndereco->setAccessible(true);

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

$rejeicaoEndereco = [
    'id' => 789,
    'chave' => '1111111111111',
    'codigo_rejeicao' => 'E1235',
    'motivo_rejeicao' => "Falha no esquema XML. The element 'end' has invalid child element 'xBairro'. List of possible elements expected: 'nro'.",
    'id_financeiro' => 321,
    'tentativas_envio' => 6,
    'chave_acesso' => null,
    'protocolo' => null,
];
$eventoProperty->setValue($service, new FakeNFSeEventoTentativaExtra([
    [
        'tipo_evento' => 'reenvio_manual',
        'codigo_retorno' => 'LIMITE_TECNICO',
        'mensagem' => 'Tentativa extra historica.',
    ],
]));
assertBoolSame(true, $recuperacaoEndereco->invoke($service, $rejeicaoEndereco), 'E1235 de endereco deve liberar uma recuperacao auditada apos a tentativa extra antiga.');

$eventoProperty->setValue($service, new FakeNFSeEventoTentativaExtra([
    ['tipo_evento' => 'reenvio_manual', 'codigo_retorno' => 'LIMITE_TECNICO'],
    ['tipo_evento' => 'reenvio_manual', 'codigo_retorno' => 'CORRECAO_ENDERECO_XSD'],
]));
assertBoolSame(false, $recuperacaoEndereco->invoke($service, $rejeicaoEndereco), 'Recuperacao do endereco XSD deve ser utilizada uma unica vez.');

$semVinculo = $rejeicaoEndereco;
$semVinculo['id_financeiro'] = null;
assertBoolSame(false, $recuperacaoEndereco->invoke($service, $semVinculo), 'DPS sem financeiro nao pode regenerar o endereco para recuperacao.');

$comProtocolo = $rejeicaoEndereco;
$comProtocolo['protocolo'] = '123';
assertBoolSame(false, $recuperacaoEndereco->invoke($service, $comProtocolo), 'DPS com protocolo nao pode usar recuperacao de rejeicao local.');

echo "Teste de tentativa extra manual NFS-e passou.\n";
