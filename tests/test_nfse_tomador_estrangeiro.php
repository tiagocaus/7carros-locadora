<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\NFSe\Betha\NFSeXMLBetha;
use App\Services\NFSe\ISSNet\NFSeXMLISSNet;
use App\Services\NFSe\Nacional\NFSeXMLNacional;
use App\Services\NFSe\NFSeService;

function exigirContem(string $trecho, string $xml, string $mensagem): void
{
    if (!str_contains($xml, $trecho)) {
        throw new RuntimeException($mensagem);
    }
}

function exigirNaoContem(string $trecho, string $xml, string $mensagem): void
{
    if (str_contains($xml, $trecho)) {
        throw new RuntimeException($mensagem);
    }
}

$dados = [
    'ambiente' => 2,
    'serie' => 'DPS',
    'numero' => 321,
    'data_emissao' => '2026-08-24T12:00:00-03:00',
    'data_competencia' => '2026-08-24',
    'municipio_codigo' => '3550308',
    'prestador' => [
        'cnpj' => '35706623000169',
        'inscricao_municipal' => '7138',
        'regime_tributario' => 1,
        'reg_apuracao_sn' => 1,
        'enviar_im' => 'S',
    ],
    'tomador' => [
        'tipo' => 'ES',
        'pais' => 'PT',
        'cpf_cnpj' => 'P<PT123456789',
        'nome' => 'Cliente Estrangeiro',
        'email' => 'cliente@example.com',
        'endereco' => [
            'pais' => 'PT',
            'codigo_pais_bacen' => '6076',
            'cep' => '1000-001',
            'logradouro' => 'Avenida da Liberdade',
            'numero' => '100',
            'complemento' => '2º andar',
            'bairro' => 'Santo António',
            'cidade' => 'Lisboa',
            'uf' => 'Lisboa',
        ],
    ],
    'servico' => [
        'codigo' => '111011100',
        'item_lista_servico' => '01.01',
        'descricao' => 'Locação de veículo automotor sem condutor.',
    ],
    'valores' => [
        'servicos' => 100,
        'deducoes' => 0,
        'base_calculo' => 100,
        'aliquota_iss' => 0,
        'valor_iss' => 0,
        'trib_issqn' => 4,
        'exigibilidade_iss' => 1,
        'iss_retido' => 'N',
    ],
    'comercio_exterior' => [
        'mdPrestacao' => 2,
        'vincPrest' => 0,
        'tpMoeda' => '790',
        'vServMoeda' => 100,
        'mecAFComexP' => 1,
        'mecAFComexT' => 1,
        'movTempBens' => 1,
        'mdic' => 0,
    ],
];

foreach ([new NFSeXMLNacional(), new NFSeXMLBetha()] as $gerador) {
    $xml = $gerador->gerarXML($dados);
    exigirContem('<cNaoNIF>0</cNaoNIF>', $xml, 'Tomador estrangeiro deve ser marcado sem NIF informado.');
    exigirContem('<endExt><cPais>PT</cPais><cEndPost>1000-001</cEndPost>', $xml, 'Endereco exterior deve preservar pais e codigo postal.');
    exigirNaoContem('<CPF>', $xml, 'Passaporte nao pode ser enviado como CPF.');
    exigirNaoContem('<CNPJ>P&lt;PT123456789</CNPJ>', $xml, 'Passaporte nao pode ser enviado como CNPJ.');
    exigirNaoContem('P&lt;PT123456789', $xml, 'Passaporte nao pode sair no XML fiscal.');
}

$betha = (new NFSeXMLBetha())->gerarXML($dados);
$comercioExteriorEsperado = '</cServ><comExt><mdPrestacao>2</mdPrestacao><vincPrest>0</vincPrest>'
    . '<tpMoeda>790</tpMoeda><vServMoeda>100.00</vServMoeda><mecAFComexP>1</mecAFComexP>'
    . '<mecAFComexT>1</mecAFComexT><movTempBens>1</movTempBens><mdic>0</mdic></comExt></serv>';
exigirContem($comercioExteriorEsperado, $betha, 'Betha deve gerar o comercio exterior completo e na ordem do XSD.');

$tomadorBrasileiro = $dados;
$tomadorBrasileiro['tomador']['tipo'] = 'PF';
$tomadorBrasileiro['tomador']['pais'] = 'BR';
$tomadorBrasileiro['tomador']['cpf_cnpj'] = '52998224725';
$tomadorBrasileiro['tomador']['endereco']['pais'] = 'BR';
$tomadorBrasileiro['comercio_exterior'] = null;
$bethaBrasileira = (new NFSeXMLBetha())->gerarXML($tomadorBrasileiro);
exigirNaoContem('<comExt>', $bethaBrasileira, 'Betha nao deve gerar comercio exterior para tomador brasileiro.');

$semComercioExterior = $dados;
$semComercioExterior['comercio_exterior'] = null;
try {
    (new NFSeXMLBetha())->gerarXML($semComercioExterior);
    throw new RuntimeException('Betha deveria bloquear tomador estrangeiro sem dados de comercio exterior.');
} catch (InvalidArgumentException $e) {
    if (!str_contains($e->getMessage(), 'comércio exterior')) {
        throw $e;
    }
}

$issnet = (new NFSeXMLISSNet())->gerarXML($dados);
exigirNaoContem('<IdentificacaoTomador>', $issnet, 'ISSNet deve omitir identificacao fiscal do tomador estrangeiro.');
exigirNaoContem('<NifTomador>', $issnet, 'ISSNet nao deve transformar passaporte em NIF.');
exigirNaoContem('P&lt;PT123456789', $issnet, 'ISSNet nao pode enviar o passaporte no XML.');
exigirContem('<EnderecoExterior><CodigoPais>6076</CodigoPais>', $issnet, 'ISSNet deve usar o codigo BACEN do pais.');
exigirContem('<EnderecoCompletoExterior>', $issnet, 'ISSNet deve enviar endereco exterior completo.');

$service = new NFSeService();
$montarComercioExterior = new ReflectionMethod(NFSeService::class, 'montarComercioExteriorBetha');
$comercioExteriorBRL = $montarComercioExterior->invoke($service, 'betha', 'ES', 'JP', 'BRL', 410.14);
if (($comercioExteriorBRL['tpMoeda'] ?? null) !== '790'
    || ($comercioExteriorBRL['vServMoeda'] ?? null) !== 410.14
    || ($comercioExteriorBRL['vincPrest'] ?? null) !== 0
    || ($comercioExteriorBRL['mecAFComexP'] ?? null) !== 1
    || ($comercioExteriorBRL['mecAFComexT'] ?? null) !== 1
    || ($comercioExteriorBRL['movTempBens'] ?? null) !== 1) {
    throw new RuntimeException('Comercio exterior Betha deve usar os dados fiscais padrao e os valores reais da filial.');
}
foreach (['USD' => '220', 'EUR' => '978', 'GBP' => '540'] as $moeda => $codigoBacen) {
    $comercioExterior = $montarComercioExterior->invoke($service, 'betha', 'ES', 'JP', $moeda, 100);
    if (($comercioExterior['tpMoeda'] ?? null) !== $codigoBacen) {
        throw new RuntimeException("Moeda {$moeda} deve usar o codigo BACEN {$codigoBacen}.");
    }
}
try {
    $montarComercioExterior->invoke($service, 'betha', 'ES', 'JP', 'JPY', 410.14);
    throw new RuntimeException('Moeda sem mapeamento BACEN deveria bloquear a emissao Betha.');
} catch (InvalidArgumentException $e) {
    if (!str_contains($e->getMessage(), 'código BACEN')) {
        throw $e;
    }
}
$validarDps = new ReflectionMethod(NFSeService::class, 'validarDPS');
$validarDps->invoke($service, $dados);
$validarIssnet = new ReflectionMethod(NFSeService::class, 'validarISSNet');
$validarIssnet->invoke($service, $dados);

$semCodigoBacen = $dados;
$semCodigoBacen['tomador']['endereco']['codigo_pais_bacen'] = '';
try {
    $validarIssnet->invoke($service, $semCodigoBacen);
    throw new RuntimeException('ISSNet deveria bloquear pais sem codigo BACEN.');
} catch (InvalidArgumentException $e) {
    if (!str_contains($e->getMessage(), 'código BACEN')) {
        throw $e;
    }
}

echo "Teste de tomador estrangeiro na NFS-e passou.\n";
