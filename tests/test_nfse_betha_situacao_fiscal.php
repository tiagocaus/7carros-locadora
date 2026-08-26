<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\NFSe\Nacional\NFSeEventosNacional;

function exigirSituacaoFiscal(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

function eventoCompactado(string $xml): string
{
    $compactado = gzencode($xml);
    if ($compactado === false) {
        throw new RuntimeException('Falha ao compactar fixture XML.');
    }
    return base64_encode($compactado);
}

$chave = '42045581235706623000169000000000027626081507487246';
$parser = new NFSeEventosNacional();

$xmlCancelamento = '<evento xmlns="http://www.sped.fazenda.gov.br/nfse" versao="1.00">'
    . '<infEvento><chNFSe>' . $chave . '</chNFSe><tpEvento>101101</tpEvento>'
    . '<dhEvento>2026-08-26T12:22:09-03:00</dhEvento><e101101><xMotivo>Reajuste de valores</xMotivo></e101101>'
    . '</infEvento></evento>';
$jsonCancelamento = json_encode([
    'eventos' => [['eventoXmlGZipB64' => eventoCompactado($xmlCancelamento)]],
], JSON_THROW_ON_ERROR);
$resultado = $parser->parseSituacao($jsonCancelamento, $chave);
exigirSituacaoFiscal($resultado['sucesso'] && $resultado['situacao'] === 'C', 'Evento 101101 deve cancelar a NFS-e.');
exigirSituacaoFiscal(($resultado['evento']['motivo'] ?? '') === 'Reajuste de valores', 'Motivo do cancelamento deve ser preservado.');

$chaveSubstituta = '42045581235706623000169000000000027726081507487247';
$xmlSubstituicao = '<evento xmlns="http://www.sped.fazenda.gov.br/nfse" versao="1.00">'
    . '<infEvento><chNFSe>' . $chave . '</chNFSe><tpEvento>105102</tpEvento>'
    . '<dhEvento>2026-08-26T13:00:00-03:00</dhEvento><e105102><chSubstituta>' . $chaveSubstituta . '</chSubstituta></e105102>'
    . '</infEvento></evento>';
$resultado = $parser->parseSituacao(json_encode([
    'loteDFe' => ['documentos' => [['documentoXmlGZipB64' => eventoCompactado($xmlSubstituicao)]]],
], JSON_THROW_ON_ERROR), $chave);
exigirSituacaoFiscal($resultado['sucesso'] && $resultado['situacao'] === 'S', 'Evento 105102 deve marcar a NFS-e como substituída.');
exigirSituacaoFiscal(($resultado['evento']['chave_substituta'] ?? '') === $chaveSubstituta, 'Chave substituta deve ser preservada.');

$resultado = $parser->parseSituacao('[]', $chave);
exigirSituacaoFiscal($resultado['sucesso'] && $resultado['situacao'] === 'N', 'Lista sem eventos fiscais deve manter situação normal.');

$resultado = $parser->parseSituacao(json_encode([
    ['tipoEvento' => 101101, 'chaveAcesso' => $chave, 'dataHoraEvento' => '2026-08-26T12:22:09-03:00'],
], JSON_THROW_ON_ERROR), $chave);
exigirSituacaoFiscal($resultado['sucesso'] && $resultado['situacao'] === 'C', 'Metadados oficiais do evento também devem ser reconhecidos.');

$xmlOutraNota = str_replace($chave, $chaveSubstituta, $xmlCancelamento);
$resultado = $parser->parseSituacao($xmlOutraNota, $chave);
exigirSituacaoFiscal(!$resultado['sucesso'], 'Evento de outra chave não pode alterar a NFS-e local.');

$resultado = $parser->parseSituacao(str_replace('<chNFSe>' . $chave . '</chNFSe>', '', $xmlCancelamento), $chave);
exigirSituacaoFiscal(!$resultado['sucesso'], 'Evento fiscal sem chave não pode alterar a NFS-e local.');

$resultado = $parser->parseSituacao('{json-invalido', $chave);
exigirSituacaoFiscal(!$resultado['sucesso'], 'Resposta JSON malformada deve falhar de forma segura.');

echo "Teste de sincronização fiscal Betha/ADN passou.\n";
