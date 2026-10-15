<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\NFSe\Betha\NFSeXMLBetha;
use App\Services\NFSe\Nacional\NFSeXMLNacional;
use App\Services\NFSe\NFSeErros;
use App\Services\NFSe\NFSeService;

function assertEndereco(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

$service = (new ReflectionClass(NFSeService::class))->newInstanceWithoutConstructor();
$validar = new ReflectionMethod(NFSeService::class, 'validarDPS');
$normalizar = new ReflectionMethod(NFSeService::class, 'normalizarEnderecoTomador');
$codigoValidacao = new ReflectionMethod(NFSeService::class, 'codigoValidacaoDPS');
$preparar = new ReflectionMethod(NFSeService::class, 'prepararXMLAssinado');

$base = [
    'tipo_emissao' => 'nacional',
    'ambiente' => 2,
    'municipio_codigo' => '2611606',
    'serie' => '1',
    'numero' => 1,
    'data_emissao' => '2026-10-15T10:00:00-03:00',
    'data_competencia' => '2026-10-15',
    'prestador' => ['cnpj' => '11694532000192', 'regime_tributario' => 1],
    'tomador' => [
        'tipo' => 'PF',
        'pais' => 'BR',
        'nome' => 'Cliente de teste',
        'cpf_cnpj' => '52998224725',
        'endereco' => [
            'pais' => ' br ',
            'codigo_municipio' => ' 3522505 ',
            'cep' => ' 06663-095 ',
            'logradouro' => ' Rua de Teste ',
            'numero' => ' 130 ',
            'complemento' => ' Apto 13 ',
            'bairro' => ' Conjunto Habitacional - Setor ',
        ],
    ],
    'servico' => ['codigo' => '1.1101.11', 'descricao' => 'Locação'],
    'valores' => ['servicos' => 100, 'trib_issqn' => 4, 'preencher_ibscbs' => 'N'],
];

$normalizado = $base;
$normalizar->invokeArgs($service, [&$normalizado]);
$validar->invoke($service, $normalizado);
$endereco = $normalizado['tomador']['endereco'];
assertEndereco($endereco['pais'] === 'BR', 'Pais deve ser normalizado.');
assertEndereco($endereco['numero'] === '130', 'Numero deve perder espacos nas extremidades.');
assertEndereco($endereco['bairro'] === 'Conjunto Habitacional - Setor', 'Bairro deve perder espacos nas extremidades.');

foreach ([NFSeXMLNacional::class, NFSeXMLBetha::class] as $geradorClass) {
    $xml = (new $geradorClass())->gerarXML($normalizado);
    $trecho = '<xLgr>RUA DE TESTE</xLgr><nro>130</nro><xCpl>APTO 13</xCpl><xBairro>CONJUNTO HABITACIONAL - SETOR</xBairro>';
    assertEndereco(str_contains($xml, $trecho), $geradorClass . ' deve gerar o endereco completo, normalizado e na ordem do XSD.');
}

$semNumero = $base;
$semNumero['tomador']['endereco']['numero'] = '   ';
try {
    $normalizar->invokeArgs($service, [&$semNumero]);
    $validar->invoke($service, $semNumero);
    throw new RuntimeException('Endereco com CEP/IBGE e sem numero deveria ser bloqueado.');
} catch (InvalidArgumentException $e) {
    assertEndereco(str_contains($e->getMessage(), 'logradouro, número e bairro'), 'Erro deve orientar a correcao cadastral.');
    assertEndereco($codigoValidacao->invoke($service, $e->getMessage()) === 'TOMADOR_ENDERECO', 'Erro local deve usar TOMADOR_ENDERECO.');
}

$resultado = $preparar->invokeArgs($service, [
    'nacional',
    &$semNumero,
    ['id_matriz_filial' => 0],
    '1111111111111',
    [],
]);
assertEndereco(!$resultado['sucesso'] && $resultado['codigo'] === 'TOMADOR_ENDERECO', 'Validacao deve ocorrer antes da reserva da numeracao.');

$semBlocoFiscal = $base;
$semBlocoFiscal['tomador']['endereco']['codigo_municipio'] = '';
$normalizar->invokeArgs($service, [&$semBlocoFiscal]);
$validar->invoke($service, $semBlocoFiscal);
$xmlSemEndereco = (new NFSeXMLNacional())->gerarXML($semBlocoFiscal);
assertEndereco(!str_contains($xmlSemEndereco, '<end>'), 'Sem CEP/IBGE completos, o bloco de endereco deve ser omitido.');

$erroNumero = "Falha no esquema XML do DF-e. - The element 'end' has invalid child element 'xBairro'. List of possible elements expected: 'nro'.";
$erroBairro = "Falha no esquema XML do DF-e. - The 'xBairro' element is invalid according to its datatype 'TSBairro'.";
$erroDocumento = "The element 'toma' has invalid child element 'xNome'. Expected: 'CNPJ', 'CPF' or 'NIF'.";
assertEndereco(NFSeErros::mapearErroRetorno('E1235', $erroNumero) === 'TOMADOR_ENDERECO', 'E1235 sem nro deve mapear endereco.');
assertEndereco(NFSeErros::mapearErroRetorno('E1235', $erroBairro) === 'TOMADOR_ENDERECO', 'E1235 TSBairro deve mapear endereco.');
assertEndereco(NFSeErros::mapearErroRetorno('E1235', $erroDocumento) === 'E1235', 'E1235 de documento deve manter o mapeamento especifico.');

echo "Teste de endereco do tomador NFS-e passou.\n";
