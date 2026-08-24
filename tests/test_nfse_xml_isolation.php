<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\NFSe\Betha\NFSeXMLBetha;
use App\Services\NFSe\NFSeErros;
use App\Services\NFSe\Nacional\NFSeXMLNacional;

function assertContainsText(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}

function assertNotContainsText(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}

function assertTextOrder(string $first, string $second, string $haystack, string $message): void
{
    $firstPos = strpos($haystack, $first);
    $secondPos = strpos($haystack, $second);
    if ($firstPos === false || $secondPos === false || $firstPos >= $secondPos) {
        throw new RuntimeException($message);
    }
}

$dados = [
    'ambiente' => 1,
    'serie' => '1',
    'numero' => 212,
    'data_emissao' => '2026-06-19T15:25:59-03:00',
    'data_competencia' => '2026-06-19',
    'municipio_codigo' => '4204558',
    'prestador' => [
        'cnpj' => '35706623000169',
        'inscricao_municipal' => '7138',
        'telefone' => '5549991185001',
        'email' => 'ednoteles@hotmail.com',
        'regime_tributario' => 1,
        'reg_apuracao_sn' => 1,
        'enviar_im' => 'S',
    ],
    'tomador' => [
        'cpf_cnpj' => '02656748000172',
        'nome' => 'Clyde Industries Brasil Ltda',
        'email' => 'financeiro@example.com',
        'endereco' => [
            'codigo_municipio' => '3530706',
            'cep' => '13849252',
            'logradouro' => 'Vereador Jose Maria Rangel',
            'numero' => '485',
            'bairro' => 'Distrito Industrial IV',
        ],
    ],
    'servico' => [
        'codigo' => '999999999',
        'descricao' => 'Locacao de veiculo automotor sem condutor, conforme contrato de locacao.',
    ],
    'valores' => [
        'servicos' => 2490.00,
        'deducoes' => 0,
        'base_calculo' => 2490.00,
        'aliquota_iss' => 0,
        'valor_iss' => 0,
        'trib_issqn' => 4,
        'iss_retido' => 'N',
    ],
];

$betha = (new NFSeXMLBetha())->gerarXML($dados);
assertContainsText('xmlns="http://www.betha.com.br/e-nota-dps"', $betha, 'Betha deve usar namespace Betha no DPS enviado ao SOAP Betha.');
assertContainsText('<DPS xmlns="http://www.betha.com.br/e-nota-dps" versao="1.01">', $betha, 'Betha deve gerar DPS na versao 1.01 do layout Betha NT004.');
assertContainsText('<infDPS id="DPS420455823570662300016900001000000000000212">', $betha, 'Betha deve usar atributo id minusculo.');
assertContainsText('<end><endNac><cMun>3530706</cMun><CEP>13849252</CEP></endNac>', $betha, 'Betha deve enviar endereco nacional quando IBGE e CEP existem.');
assertContainsText('<cTribNac>990101</cTribNac>', $betha, 'Betha deve mapear nao incidencia para cTribNac 990101.');
assertContainsText('<cNBS>111011100</cNBS>', $betha, 'Betha deve trocar placeholder de locacao de veiculo pelo NBS real usado pela calculadora nacional.');
assertTextOrder('</serv>', '<valores>', $betha, 'Betha deve posicionar valores logo apos serv.');
assertNotContainsText('http://www.sped.fazenda.gov.br/nfse', $betha, 'Betha nao pode usar namespace SPED diretamente no SOAP Betha.');
assertNotContainsText('<IBSCBS>', $betha, 'Betha nao deve gerar grupo IBSCBS quando preencher_ibscbs estiver desativado.');
assertNotContainsText('<cLocalidadeIncid>', $betha, 'Betha nao pode gerar cLocalidadeIncid.');
assertNotContainsText('<infoCompl/>', $betha, 'Betha nao deve enviar infoCompl no perfil aceito pelo SOAP Betha.');
assertNotContainsText('<vTotTrib>', $betha, 'Betha nao deve enviar vTotTrib zerado quando IBS/CBS estiver desativado.');
assertNotContainsText('<pTotTrib>', $betha, 'Betha nao deve enviar percentuais estimados quando seguir o perfil minimo oficial NT004.');
assertContainsText('<totTrib><indTotTrib>0</indTotTrib></totTrib>', $betha, 'Betha deve enviar indTotTrib=0 no perfil minimo oficial NT004.');
assertNotContainsText('<infDPS Id=', $betha, 'Betha nao pode usar atributo Id maiusculo.');

$nacional = (new NFSeXMLNacional())->gerarXML($dados);
assertContainsText('xmlns="http://www.sped.fazenda.gov.br/nfse"', $nacional, 'Nacional deve usar namespace SPED.');
assertContainsText('<DPS xmlns="http://www.sped.fazenda.gov.br/nfse" versao="1.01">', $nacional, 'Nacional deve gerar o layout DPS 1.01.');
assertContainsText('<infDPS Id="DPS420455823570662300016900001000000000000212">', $nacional, 'Nacional deve usar atributo Id maiusculo.');
assertContainsText('<end><endNac><cMun>3530706</cMun><CEP>13849252</CEP></endNac>', $nacional, 'Nacional deve enviar endereco nacional quando IBGE e CEP existem.');
assertNotContainsText('http://www.betha.com.br/e-nota-dps', $nacional, 'Nacional nao pode usar namespace Betha.');
assertNotContainsText('<IBSCBS>', $nacional, 'Nacional nao pode gerar bloco IBSCBS por padrao.');
assertNotContainsText('<cLocalidadeIncid>', $nacional, 'Nacional nao pode gerar cLocalidadeIncid por padrao.');
assertNotContainsText('<vTotTrib>', $nacional, 'Nacional nao pode calcular IBS/CBS por padrao em vTotTrib.');
assertContainsText('<totTrib><pTotTrib><pTotTribFed>0.00</pTotTribFed><pTotTribEst>0.00</pTotTribEst><pTotTribMun>0.00</pTotTribMun></pTotTrib></totTrib>', $nacional, 'Nacional deve enviar percentuais de tributos zerados quando IBS/CBS estiver desativado.');
assertNotContainsText('<infDPS id=', $nacional, 'Nacional nao pode usar atributo id minusculo.');

$dadosIBSCBS = $dados;
$dadosIBSCBS['valores']['preencher_ibscbs'] = 'S';
$dadosIBSCBS['valores']['c_ind_op_ibscbs'] = '010101';
$dadosIBSCBS['valores']['cst_ibscbs'] = '000';
$dadosIBSCBS['valores']['c_class_trib_ibscbs'] = '000001';
$nacionalIBSCBS = (new NFSeXMLNacional())->gerarXML($dadosIBSCBS);
assertContainsText(
    '<IBSCBS><finNFSe>0</finNFSe><cIndOp>010101</cIndOp><indDest>0</indDest><valores><trib><gIBSCBS><CST>000</CST><cClassTrib>000001</cClassTrib></gIBSCBS></trib></valores></IBSCBS>',
    $nacionalIBSCBS,
    'Nacional deve gerar o grupo declaratorio IBS/CBS na estrutura oficial do DPS 1.01.'
);
assertTextOrder('</valores>', '<IBSCBS>', $nacionalIBSCBS, 'O grupo IBSCBS deve ficar depois do grupo valores da DPS.');

$xmlAutorizado = '<?xml version="1.0"?><NFSe xmlns="http://www.sped.fazenda.gov.br/nfse"><infNFSe>'
    . '<nNFSe>987</nNFSe><chNFSe>' . str_repeat('1', 50) . '</chNFSe><cVerif>ABC123</cVerif>'
    . '<IBSCBS><valores><uf><pIBSUF>0.10</pIBSUF></uf><mun><pIBSMun>0.20</pIBSMun></mun>'
    . '<fed><pCBS>0.90</pCBS></fed></valores><totCIBS><gIBS><vIBSTot>7.47</vIBSTot></gIBS>'
    . '<gCBS><vCBS>22.41</vCBS></gCBS></totCIBS></IBSCBS></infNFSe></NFSe>';
$resposta = json_encode(['nfseXmlGZipB64' => base64_encode(gzencode($xmlAutorizado))]);
$retorno = (new NFSeXMLNacional())->parseRetorno($resposta);
if (!$retorno['sucesso'] || abs($retorno['aliquota_ibs'] - 0.3) > 0.00001 || $retorno['valor_ibs'] !== 7.47
    || $retorno['aliquota_cbs'] !== 0.9 || $retorno['valor_cbs'] !== 22.41) {
    throw new RuntimeException('Parser Nacional deve persistir IBS/CBS calculado no XML autorizado.');
}

if (NFSeErros::mapearErroRetorno('E0014', 'DPS ja gerada') !== 'DPS_JA_GERADA'
    || !NFSeErros::isRecuperavel('DPS_JA_GERADA')
    || NFSeErros::isRecuperavel('IBSCBS_CONFIGURACAO')) {
    throw new RuntimeException('Mapeamento de E0014/configuracao IBS/CBS esta incorreto para o reprocessamento.');
}

echo "Teste de isolamento XML NFS-e passou.\n";
