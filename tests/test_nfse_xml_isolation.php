<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\NFSe\Betha\NFSeXMLBetha;
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
assertContainsText('<infDPS Id="DPS420455823570662300016900001000000000000212">', $nacional, 'Nacional deve usar atributo Id maiusculo.');
assertContainsText('<end><endNac><cMun>3530706</cMun><CEP>13849252</CEP></endNac>', $nacional, 'Nacional deve enviar endereco nacional quando IBGE e CEP existem.');
assertNotContainsText('http://www.betha.com.br/e-nota-dps', $nacional, 'Nacional nao pode usar namespace Betha.');
assertNotContainsText('<IBSCBS>', $nacional, 'Nacional nao pode gerar bloco IBSCBS por padrao.');
assertNotContainsText('<cLocalidadeIncid>', $nacional, 'Nacional nao pode gerar cLocalidadeIncid por padrao.');
assertNotContainsText('<vTotTrib>', $nacional, 'Nacional nao pode calcular IBS/CBS por padrao em vTotTrib.');
assertContainsText('<totTrib><pTotTrib><pTotTribFed>0.00</pTotTribFed><pTotTribEst>0.00</pTotTribEst><pTotTribMun>0.00</pTotTribMun></pTotTrib></totTrib>', $nacional, 'Nacional deve enviar percentuais de tributos zerados quando IBS/CBS estiver desativado.');
assertNotContainsText('<infDPS id=', $nacional, 'Nacional nao pode usar atributo id minusculo.');

echo "Teste de isolamento XML NFS-e passou.\n";
