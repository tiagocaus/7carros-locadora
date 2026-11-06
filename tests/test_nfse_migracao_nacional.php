<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\NFSe\NFSeService;

// Reflection evita construtores de Models: este teste não acessa BD nem rede.
$reflection = new ReflectionClass(NFSeService::class);
$service = $reflection->newInstanceWithoutConstructor();
$conclusiva = $reflection->getMethod('rejeicaoMunicipalConclusiva');
$identidade = $reflection->getMethod('validarIdentidadeReenvio');
$falhas = [];
$verificar = static function (bool $condicao, string $cenario) use (&$falhas): void {
    if (!$condicao) {
        $falhas[] = $cenario;
    }
};

$nota = ['status' => 'rejeitada', 'tipo_emissao' => 'betha', 'chave_acesso' => null, 'protocolo' => null];
$betha = static function (string $status = '', string $codigo = 'E1235', string $protocolo = ''): string {
    return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
        . '<ns:ConsultarStatusDpsResposta xmlns:ns="http://www.betha.com.br/e-nota-dps">'
        . ($status !== '' ? '<ns:statusProcessamento>' . $status . '</ns:statusProcessamento>' : '')
        . ($protocolo !== '' ? '<ns:protocolo>' . $protocolo . '</ns:protocolo>' : '')
        . '<ns:mensagens><ns:mensagem><ns:codigo>' . $codigo . '</ns:codigo>'
        . '<ns:descricao>Erro de validação do documento</ns:descricao></ns:mensagem></ns:mensagens>'
        . '</ns:ConsultarStatusDpsResposta></soap:Body></soap:Envelope>';
};
$evento = static fn(string $xml, string $tipo = 'erro'): array => ['tipo_evento' => $tipo, 'xml_evento' => $xml];
$avaliar = static fn(array $n, array $e): bool => $conclusiva->invoke($service, $n, $e);

$verificar($avaliar($nota, $evento($betha())), 'Betha: rejeição externa sem protocolo deve ser conclusiva.');
$comProtocolo = array_replace($nota, ['protocolo' => 'protocolo-original']);
$verificar($avaliar($comProtocolo, $evento($betha('Processado com erro', 'E1235', 'protocolo-original'))), 'Betha: erro final consultado pelo protocolo original deve liberar avaliação da nova tentativa.');

foreach (['Não processado', 'Aguardando validação do ambiente nacional', 'Processado com sucesso'] as $status) {
    $verificar(!$avaliar($comProtocolo, $evento($betha($status, 'E1235', 'protocolo-original'))), 'Betha: status ' . $status . ' não comprova rejeição definitiva.');
}
$verificar(!$avaliar($nota, $evento($betha('Aguardando validação do ambiente nacional', 'E1235', 'protocolo-recebido'))), 'Betha: protocolo pendente no retorno também bloqueia quando ainda não foi persistido na nota.');
$verificar(!$avaliar($comProtocolo, $evento($betha('', 'E1235', 'protocolo-original'))), 'Betha: protocolo sem status final não comprova rejeição definitiva.');

foreach (['CONN_TIMEOUT', 'CONN_CURL', 'ERRO_DESCONHECIDO', 'DPS_JA_GERADA', ''] as $codigo) {
    $verificar(!$avaliar($nota, $evento($betha('', $codigo))), 'Betha: código inconclusivo ' . var_export($codigo, true) . ' deve bloquear.');
}
foreach (['', '<resposta>', '<!DOCTYPE resposta [<!ELEMENT resposta ANY>]><resposta/>'] as $xml) {
    $verificar(!$avaliar($nota, $evento($xml)), 'XML ausente, inválido ou com DTD deve bloquear: ' . $xml);
}
$verificar(!$avaliar($nota, $evento($betha(), 'consulta')), 'O último evento precisa ser erro, não apenas consulta.');
$verificar(!$avaliar(array_replace($nota, ['status' => 'processando']), $evento($betha())), 'Nota em processamento não pode originar migração.');
$verificar(!$avaliar(array_replace($nota, ['chave_acesso' => str_repeat('1', 50)]), $evento($betha())), 'Chave autorizada local bloqueia outra emissão.');

$issnet = '<GerarNfseResposta xmlns="http://www.abrasf.org.br/nfse.xsd"><ListaMensagemRetorno>'
    . '<MensagemRetorno><Codigo>E123</Codigo><Mensagem>RPS rejeitado por validação</Mensagem></MensagemRetorno>'
    . '</ListaMensagemRetorno></GerarNfseResposta>';
$notaIssnet = array_replace($nota, ['tipo_emissao' => 'issnet']);
$verificar($avaliar($notaIssnet, $evento($issnet)), 'ISSNet: rejeição ABRASF conclusiva sem protocolo deve ser aceita.');
$verificar(!$avaliar(array_replace($notaIssnet, ['protocolo' => 'incerto']), $evento($issnet)), 'ISSNet: protocolo incerto deve bloquear.');
$autorizadaIssnet = '<GerarNfseResposta><CompNfse><Nfse><InfNfse><Numero>123</Numero>'
    . '<CodigoVerificacao>ABC123</CodigoVerificacao></InfNfse></Nfse></CompNfse></GerarNfseResposta>';
$verificar(!$avaliar($notaIssnet, $evento($autorizadaIssnet)), 'ISSNet: retorno autorizado deve bloquear nova emissão.');

$original = ['prestador_cnpj' => '12.345.678/0001-95', 'tomador_cpf_cnpj' => '123.456.789-09',
    'tomador_tipo' => 'PF', 'tomador_pais' => 'BR', 'valor_servicos' => '100.00'];
$dados = ['prestador' => ['cnpj' => '12345678000195'],
    'tomador' => ['cpf_cnpj' => '12345678909', 'tipo' => 'PF', 'pais' => 'BR'], 'valores' => ['servicos' => 100.0]];
$bloqueiaIdentidade = static function (array $n, array $d) use ($identidade, $service): bool {
    try {
        $identidade->invoke($service, $n, $d);
        return false;
    } catch (InvalidArgumentException) {
        return true;
    }
};
$verificar(!$bloqueiaIdentidade($original, $dados), 'Formatação equivalente de CPF/CNPJ deve preservar a identidade.');
$alterado = $dados;
$alterado['prestador']['cnpj'] = '99999999000199';
$verificar($bloqueiaIdentidade($original, $alterado), 'Mudança de prestador deve bloquear antes de reservar numeração.');
$alterado = $dados;
$alterado['tomador']['cpf_cnpj'] = '98765432100';
$verificar($bloqueiaIdentidade($original, $alterado), 'Mudança de documento do tomador deve bloquear.');
foreach ([99.99, 100.01] as $valor) {
    $alterado = $dados;
    $alterado['valores']['servicos'] = $valor;
    $verificar($bloqueiaIdentidade($original, $alterado), 'Mudança de um centavo no valor deve bloquear: ' . $valor);
}
$estrangeiro = array_replace($original, ['tomador_tipo' => 'ES', 'tomador_pais' => 'US', 'tomador_cpf_cnpj' => 'AB123']);
$dadosEstrangeiro = $dados;
$dadosEstrangeiro['tomador'] = ['tipo' => 'ES', 'pais' => 'US', 'cpf_cnpj' => 'AB123'];
$verificar(!$bloqueiaIdentidade($estrangeiro, $dadosEstrangeiro), 'Passaporte cadastral estrangeiro preservado deve permitir reenvio.');
$dadosEstrangeiro['tomador']['cpf_cnpj'] = 'XY123';
$verificar($bloqueiaIdentidade($estrangeiro, $dadosEstrangeiro), 'Mudança nas letras do passaporte também deve bloquear, mesmo com os mesmos dígitos.');

if ($falhas) {
    throw new RuntimeException("Falhas na migração NFS-e Nacional:\n- " . implode("\n- ", $falhas));
}
echo "Teste de migração NFS-e Nacional passou, sem BD ou rede.\n";
