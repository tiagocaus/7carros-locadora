<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\NFSe\NFSeService;
use App\Services\NFSe\NFSeErros;
use App\Services\NFSe\Nacional\NFSeXMLNacional;

$reflexao = new ReflectionClass(NFSeService::class);
$service = $reflexao->newInstanceWithoutConstructor();

$extrairId = $reflexao->getMethod('extrairIdDPS');
$idEsperado = 'DPS420455823570662300016900001000000000000212';
$idDoXml = $extrairId->invoke($service, [
    'xml_envio' => '<DPS><infDPS Id="' . $idEsperado . '"></infDPS></DPS>',
], []);
if ($idDoXml !== $idEsperado) {
    throw new RuntimeException('A reconciliacao deve priorizar o Id persistido no XML original da DPS.');
}

$idReconstruido = $extrairId->invoke($service, [
    'xml_envio' => null,
    'prestador_cnpj' => '35.706.623/0001-69',
    'serie' => '1',
    'numero' => 212,
], ['codigo_municipio' => '4204558']);
if ($idReconstruido !== $idEsperado) {
    throw new RuntimeException('A reconciliacao deve reconstruir corretamente o Id da DPS quando o XML nao estiver disponivel.');
}

$extrairChave = $reflexao->getMethod('extrairChaveAcessoResposta');
$chaveEsperada = str_repeat('9', 50);
$chave = $extrairChave->invoke($service, json_encode(['nfse' => ['chaveAcesso' => $chaveEsperada]]));
if ($chave !== $chaveEsperada) {
    throw new RuntimeException('A reconciliacao deve extrair a chave de acesso da resposta GET /dps/{id}.');
}

$comparar = $reflexao->getMethod('compararDpsReconciliacao');
$dpsLocal = '<DPS><infDPS><serie>1</serie><nDPS>212</nDPS><dCompet>2026-08-25</dCompet><cLocEmi>4204558</cLocEmi><prest><CNPJ>35706623000169</CNPJ></prest><toma><CPF>12345678901</CPF><xNome>Cliente Teste</xNome></toma><valores><vServPrest><vServ>195.00</vServ></vServPrest></valores></infDPS></DPS>';
$retornoCompativel = '<NFSe><infNFSe><DPS><infDPS><serie>00001</serie><nDPS>212</nDPS><dCompet>2026-08-25</dCompet><cLocEmi>4204558</cLocEmi><prest><CNPJ>35.706.623/0001-69</CNPJ></prest><toma><CPF>123.456.789-01</CPF><xNome>CLIENTE   TESTE</xNome></toma><valores><vServPrest><vServ>195,00</vServ></vServPrest></valores></infDPS></DPS></infNFSe></NFSe>';
$comparacao = $comparar->invoke($service, $dpsLocal, $retornoCompativel);
if (!$comparacao['compativel'] || $comparacao['divergencias'] !== []) {
    throw new RuntimeException('A reconciliacao deve aceitar a mesma DPS apos normalizar formatos equivalentes.');
}

$retornoExterno = str_replace(
    ['<CPF>123.456.789-01</CPF>', '<xNome>CLIENTE   TESTE</xNome>', '<vServ>195,00</vServ>'],
    ['<CNPJ>99999999000199</CNPJ>', '<xNome>OUTRO TOMADOR</xNome>', '<vServ>340.00</vServ>'],
    $retornoCompativel
);
$comparacao = $comparar->invoke($service, $dpsLocal, $retornoExterno);
foreach (['tomador_documento', 'tomador_nome', 'valor_servicos'] as $campo) {
    if (!in_array($campo, $comparacao['divergencias'], true)) {
        throw new RuntimeException("A reconciliacao deveria detectar divergencia em {$campo}.");
    }
}
if ($comparacao['compativel']) {
    throw new RuntimeException('Uma DPS externa incompatível nunca pode autorizar a tentativa local.');
}
if (NFSeErros::isRecuperavel('DPS_CONFLITO')) {
    throw new RuntimeException('DPS_CONFLITO nao pode permitir reenvio automatico.');
}

$dadosXml = [
    'ambiente' => 2,
    'serie' => '1',
    'numero' => 1,
    'data_emissao' => '2026-08-25T10:00:00-03:00',
    'data_competencia' => '2026-08-25',
    'municipio_codigo' => '4204558',
    'prestador' => ['cnpj' => '35706623000169', 'regime_tributario' => 1],
    'tomador' => ['cpf_cnpj' => '12345678901', 'nome' => 'Cliente Teste'],
    'servico' => [
        'codigo' => '1.1101.11',
        'codigo_tributacao_nacional' => '123456',
        'descricao' => 'Locacao',
    ],
    'valores' => ['servicos' => 100, 'trib_issqn' => 1],
];
$xmlConfigurado = (new NFSeXMLNacional())->gerarXML($dadosXml);
if (!str_contains($xmlConfigurado, '<cTribNac>123456</cTribNac>')) {
    throw new RuntimeException('O XML Nacional deve priorizar o cTribNac configurado.');
}
unset($dadosXml['servico']['codigo_tributacao_nacional']);
$xmlLegado = (new NFSeXMLNacional())->gerarXML($dadosXml);
if (!str_contains($xmlLegado, '<cTribNac>010101</cTribNac>')) {
    throw new RuntimeException('Sem configuracao, o mapeamento legado de cTribNac deve ser preservado.');
}

echo "Teste de reconciliacao NFS-e Nacional passou.\n";
