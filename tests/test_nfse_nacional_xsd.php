<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\NFSe\Nacional\NFSeXMLNacional;
use App\Services\NFSe\Nacional\NFSeXSDNacional;
use App\Services\NFSe\NFSeAssinatura;

function assertNacionalXSD(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

function rejeitaNacionalXSD(callable $executar, string $trecho): void
{
    try {
        $executar();
    } catch (InvalidArgumentException $erro) {
        assertNacionalXSD(str_contains($erro->getMessage(), $trecho), $erro->getMessage());
        return;
    }
    throw new RuntimeException('Deveria rejeitar XML/configuração: ' . $trecho);
}

$dados = [
    'ambiente' => 2, 'serie' => '1', 'numero' => 1, 'municipio_codigo' => '2611606',
    'data_emissao' => '2026-11-06T10:00:00-03:00', 'data_competencia' => '2026-11-01',
    'prestador' => ['cnpj' => '11694532000192', 'regime_tributario' => 1, 'reg_apuracao_sn' => 1],
    'tomador' => ['tipo' => 'PF', 'cpf_cnpj' => '52998224725', 'nome' => 'Cliente Teste'],
    'servico' => ['codigo' => '1.1101.11', 'codigo_tributacao_nacional' => '990101', 'descricao' => 'Locação'],
    'valores' => ['servicos' => 100, 'trib_issqn' => 4, 'preencher_ibscbs' => 'N'],
];
$gerador = new NFSeXMLNacional();
$validador = new NFSeXSDNacional();

foreach (['0', '1', '00001', '99999'] as $serie) {
    $dados['serie'] = $serie;
    $xml = $gerador->gerarXML($dados);
    $validador->validarDPS($xml);
    assertNacionalXSD((bool) preg_match('/Id="DPS[0-9]{42}"/', $xml), 'ID deve ter 45 posições e somente dígitos após DPS.');
}
foreach ([null, '', 'DPS', 'A1', '123456', '-1', '1.0'] as $serie) {
    $dados['serie'] = $serie;
    rejeitaNacionalXSD(fn () => $gerador->gerarXML($dados), 'série');
}
$dados['serie'] = '1';
$dados['valores']['trib_issqn'] = 1;
$dados['valores']['aliquota_iss'] = 2;
$dados['servico']['codigo_tributacao_nacional'] = '010101';
$tributavel = $gerador->gerarXML($dados);
assertNacionalXSD(str_contains($tributavel, '<pAliq>2.00</pAliq>'), 'DPS tributável preserva alíquota declarada.');
assertNacionalXSD(!str_contains($tributavel, '<vISSQN>'), 'DPS não pode incluir vISSQN calculado no tribMun.');
$validador->validarDPS($tributavel);
$invalido = str_replace('</tribMun>', '<vISSQN>2.00</vISSQN></tribMun>', $tributavel);
rejeitaNacionalXSD(fn () => $validador->validarDPS($invalido), 'vISSQN');
rejeitaNacionalXSD(fn () => $validador->validarDPS(str_replace('<serie>1</serie>', '<serie>DPS</serie>', $tributavel)), 'serie');
rejeitaNacionalXSD(fn () => $validador->validarDPS(str_replace('<nDPS>1</nDPS>', '<nDPS>0</nDPS>', $tributavel)), 'nDPS');
rejeitaNacionalXSD(fn () => $validador->validarDPS('<DPS>'), 'malformado');
rejeitaNacionalXSD(fn () => $validador->validarDPS('<!DOCTYPE DPS SYSTEM "file:///etc/passwd"><DPS/>'), 'DTD');

foreach ([1, 2, 3] as $apuracao) {
    $dados['prestador']['reg_apuracao_sn'] = $apuracao;
    $xml = $gerador->gerarXML($dados);
    assertNacionalXSD(str_contains($xml, '<regApTribSN>' . $apuracao . '</regApTribSN>'), 'ME/EPP deve enviar apuração do Simples.');
}
$dados['prestador']['regime_tributario'] = 4;
$xmlMei = $gerador->gerarXML($dados);
assertNacionalXSD(str_contains($xmlMei, '<opSimpNac>2</opSimpNac>') && !str_contains($xmlMei, '<regApTribSN>'), 'MEI não envia regApTribSN.');

$dados['valores'] = ['servicos' => 100, 'trib_issqn' => 4, 'preencher_ibscbs' => 'S',
    'c_ind_op_ibscbs' => '100301', 'cst_ibscbs' => '000', 'c_class_trib_ibscbs' => '000001'];
$dados['servico']['codigo_tributacao_nacional'] = '990101';
$gerador->gerarXML($dados);

$chave = str_repeat('1', 50);
$evento = $gerador->gerarXMLCancelamento($chave, 'Erro na emissão para teste', ['prestador_cnpj' => '11694532000192', 'ambiente' => 2]);
$validador->validarEvento($evento);
rejeitaNacionalXSD(fn () => $validador->validarEvento(str_replace('<cMotivo>9</cMotivo>', '<cMotivo>8</cMotivo>', $evento)), 'cMotivo');

// Certificado efêmero e local: nenhuma consulta fiscal ou envio externo.
$estadoAnterior = getenv('RANDFILE');
$arquivoEstado = tempnam(sys_get_temp_dir(), 'nfse-xsd-rand-');
putenv('RANDFILE=' . $arquivoEstado);
try {
    $chavePrivada = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr = openssl_csr_new(['commonName' => 'Teste local NFS-e'], $chavePrivada);
    $certificado = openssl_csr_sign($csr, null, $chavePrivada, 1);
    openssl_pkey_export($chavePrivada, $pemChave);
    openssl_x509_export($certificado, $pemCertificado);
} finally {
    putenv($estadoAnterior === false ? 'RANDFILE' : 'RANDFILE=' . $estadoAnterior);
    unlink($arquivoEstado);
}
$arquivoChave = tempnam(sys_get_temp_dir(), 'nfse-xsd-key-');
$arquivoCertificado = tempnam(sys_get_temp_dir(), 'nfse-xsd-cert-');
try {
    chmod($arquivoChave, 0600);
    file_put_contents($arquivoChave, $pemChave);
    file_put_contents($arquivoCertificado, $pemCertificado);
    $assinador = new NFSeAssinatura();
    foreach ([[$tributavel, 'infDPS'], [$evento, 'infPedReg']] as [$documento, $tag]) {
        $resultado = $assinador->assinar($documento, $arquivoCertificado, $arquivoChave, $tag, 'sha256', 'Id');
        assertNacionalXSD($resultado['sucesso'], $resultado['mensagem'] ?? 'Falha na assinatura.');
        if ($tag === 'infDPS') {
            $validador->validarDPS($resultado['xml']);
        } else {
            $validador->validarEvento($resultado['xml']);
        }
    }
} finally {
    unlink($arquivoChave);
    unlink($arquivoCertificado);
}

echo "Teste de XSD Nacional passou: séries, ISSQN, regimes, IBS/CBS e eventos assinados.\n";
