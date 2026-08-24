<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\NFSe\NFSeService;

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

echo "Teste de reconciliacao NFS-e Nacional passou.\n";
