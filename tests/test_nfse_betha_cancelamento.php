<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\NFSe\Betha\NFSeXMLBetha;
use App\Services\NFSe\NFSeAssinatura;

function exigir(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

$chaveAcesso = '42045581235706623000169000000000027626081507487246';
$gerador = new NFSeXMLBetha();
$xml = $gerador->gerarXMLCancelamento($chaveAcesso, 'reajuste de valores', [
    'ambiente' => 1,
    'prestador_cnpj' => '35706623000169',
    'data_evento' => '2026-08-26T12:00:00-03:00',
]);

exigir(str_contains($xml, '<evento versao="1.0">'), 'Evento Betha deve informar a versao exigida pelo XSD.');
exigir(str_contains($xml, '<infEvento id="EVT' . $chaveAcesso . '101101001">'), 'infEvento deve ter id Betha minusculo.');
exigir(str_contains($xml, '<pedRegEvento versao="1.0">'), 'Pedido de evento deve informar versao.');
exigir(str_contains($xml, '<infPedReg id="PRE' . $chaveAcesso . '101101">'), 'Pedido deve ter identificador PRE.');
exigir(str_contains($xml, '<chNFSe>' . $chaveAcesso . '</chNFSe>'), 'Pedido deve informar a chave da NFS-e.');
exigir(str_contains($xml, '<CNPJAutor>35706623000169</CNPJAutor>'), 'Pedido deve informar o CNPJ autor.');
exigir(str_contains($xml, '<cMotivo>9</cMotivo><xMotivo>REAJUSTE DE VALORES</xMotivo>'), 'Motivo livre deve usar codigo 9 e xMotivo.');
exigir(strpos($xml, '<chNFSe>') < strpos($xml, '<CNPJAutor>'), 'Elementos do pedido devem seguir a ordem do XSD Betha.');
exigir(!str_contains($xml, '<chaveAcesso>'), 'Formato simplificado antigo nao pode ser gerado.');

$randomPath = tempnam(sys_get_temp_dir(), 'nfse_betha_rand_');
exigir($randomPath !== false, 'Nao foi possivel criar estado aleatorio temporario.');
putenv('RANDFILE=' . $randomPath);
$privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
exigir($privateKey !== false, 'Nao foi possivel gerar chave privada para o teste.');
$csr = openssl_csr_new(['commonName' => 'Teste NFS-e Betha'], $privateKey, ['digest_alg' => 'sha256']);
exigir($csr !== false, 'Nao foi possivel gerar CSR para o teste.');
$certificado = openssl_csr_sign($csr, null, $privateKey, 1, ['digest_alg' => 'sha256']);
exigir($certificado !== false, 'Nao foi possivel gerar certificado para o teste.');

$certPath = tempnam(sys_get_temp_dir(), 'nfse_betha_cert_');
$keyPath = tempnam(sys_get_temp_dir(), 'nfse_betha_key_');
exigir($certPath !== false && $keyPath !== false, 'Nao foi possivel criar arquivos temporarios.');

try {
    openssl_x509_export($certificado, $certPem);
    openssl_pkey_export($privateKey, $keyPem);
    file_put_contents($certPath, $certPem);
    file_put_contents($keyPath, $keyPem);

    $assinado = (new NFSeAssinatura())->assinar($xml, $certPath, $keyPath, 'infEvento', 'sha256', 'id');
    exigir($assinado['sucesso'], 'Evento Betha deve ser assinado pelo id de infEvento.');

    $doc = new DOMDocument();
    exigir($doc->loadXML($assinado['xml']), 'XML assinado deve continuar bem formado.');
    $signatures = $doc->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature');
    exigir($signatures->length === 1, 'Evento deve conter uma assinatura XMLDSIG.');
    exigir($signatures->item(0)?->parentNode?->localName === 'evento', 'Assinatura de infEvento deve ser filha de evento.');
    $references = $doc->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Reference');
    exigir(
        $references->item(0)?->getAttribute('URI') === '#EVT' . $chaveAcesso . '101101001',
        'Assinatura deve referenciar o id de infEvento.'
    );
} finally {
    @unlink($certPath);
    @unlink($keyPath);
    @unlink($randomPath);
}

$recepcao = '<RecepcionarEventoCancelamentoResposta xmlns="http://www.betha.com.br/e-nota-evento">'
    . '<protocolo>abc-123</protocolo><status>Aguardando validação do ambiente nacional</status>'
    . '</RecepcionarEventoCancelamentoResposta>';
$retorno = $gerador->parseRetornoCancelamento($recepcao);
exigir($retorno['processando'] && !$retorno['sucesso'], 'Recepcao com protocolo deve ficar em processamento.');
exigir($retorno['protocolo'] === 'abc-123', 'Parser deve preservar protocolo de cancelamento.');

$recepcaoReal = '<env:Envelope xmlns:env="http://schemas.xmlsoap.org/soap/envelope/">'
    . '<env:Body><ns2:RecepcionarEventoCancelamentoResposta xmlns:ns2="http://www.betha.com.br/e-nota-dps">'
    . '<ns2:protocolo>608635427881452</ns2:protocolo>'
    . '<ns2:dhRecebimento>2026-08-26T12:22:09.076-03:00</ns2:dhRecebimento>'
    . '<ns2:status>Não processado</ns2:status>'
    . '</ns2:RecepcionarEventoCancelamentoResposta></env:Body></env:Envelope>';
$retorno = $gerador->parseRetornoCancelamento($recepcaoReal);
exigir($retorno['processando'] && !$retorno['sucesso'], 'Retorno real "Não processado" deve manter o cancelamento em processamento.');
exigir($retorno['protocolo'] === '608635427881452', 'Parser deve preservar o protocolo do retorno real Betha.');
exigir(empty($retorno['erros']), 'Retorno real aceito pela Betha nao deve gerar erro sintetico.');

$sucesso = '<ConsultarStatusDpsCancelamentoResposta xmlns="http://www.betha.com.br/e-nota-dps">'
    . '<statusProcessamento>Processado com sucesso</statusProcessamento><protocolo>abc-123</protocolo>'
    . '<idCancelamento>cancel-1</idCancelamento></ConsultarStatusDpsCancelamentoResposta>';
$retorno = $gerador->parseRetornoCancelamento($sucesso);
exigir($retorno['sucesso'] && !$retorno['processando'], 'Somente confirmacao final deve concluir o cancelamento.');

$erro = '<ConsultarStatusDpsCancelamentoResposta xmlns="http://www.betha.com.br/e-nota-dps">'
    . '<statusProcessamento>Processado com erro</statusProcessamento><protocolo>abc-123</protocolo>'
    . '<mensagemErro>Prazo expirado</mensagemErro></ConsultarStatusDpsCancelamentoResposta>';
$retorno = $gerador->parseRetornoCancelamento($erro);
exigir(!$retorno['sucesso'] && !$retorno['processando'], 'Erro final nao pode concluir nem manter polling.');
exigir(($retorno['erros'][0]['mensagem'] ?? '') === 'Prazo expirado', 'Parser deve expor mensagemErro da Betha.');

$erroSemMensagem = '<ConsultarStatusDpsCancelamentoResposta xmlns="http://www.betha.com.br/e-nota-dps">'
    . '<statusProcessamento>Processado com erro</statusProcessamento><protocolo>abc-123</protocolo>'
    . '</ConsultarStatusDpsCancelamentoResposta>';
$retorno = $gerador->parseRetornoCancelamento($erroSemMensagem);
exigir(!$retorno['sucesso'] && !$retorno['processando'], 'Status final de erro sem mensagem nao pode permanecer em polling.');
exigir(($retorno['erros'][0]['codigo'] ?? '') === 'ERRO_CANCELAMENTO_BETHA', 'Parser deve sintetizar codigo para erro final sem detalhe.');

echo "Teste de cancelamento Betha passou.\n";
