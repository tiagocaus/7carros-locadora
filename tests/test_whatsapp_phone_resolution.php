#!/usr/bin/env php
<?php

// Provedor e download simulados. Sem banco, RabbitMQ ou mensagens reais.
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Services\WhatsAppService;

(new ReflectionProperty(Database::class, 'config'))->setValue(null, [
    'WHATSAPP_API_URL' => 'https://provider.invalid', 'WHATSAPP_API_INSTANCE_TOKEN' => 'fake-token',
]);
$_SESSION = ['chave' => '1111111111111'];

class PhoneResolutionFake extends WhatsAppService
{
    public array $responses = [];
    public array $requests = [];
    public string $mime = 'application/pdf';
    protected function makeRequest(string $url, string $instanceToken, array $data): array
    {
        $this->requests[] = ['path' => parse_url($url, PHP_URL_PATH), 'token' => $instanceToken, 'data' => $data];
        $next = array_shift($this->responses);
        if ($next instanceof Throwable) { throw $next; }
        if (!is_array($next)) { throw new RuntimeException('Requisicao extra inesperada'); }
        return $next;
    }
    protected function downloadMedia(string $url): array
    {
        return ['success' => true, 'mime' => $this->mime, 'body' => 'fake-file', 'filename' => 'teste.pdf'];
    }
}
function lookup(string $query, ?string $phone): array
{
    return ['http_code' => 200, 'body' => ['code' => 200, 'success' => true, 'data' => ['Users' => [[
        'Query' => $query, 'IsInWhatsapp' => $phone !== null,
        'JID' => $phone === null ? '' : $phone . '@s.whatsapp.net', 'VerifiedName' => '',
    ]]]]];
}
$checks = 0;
function verifyPhone(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) { throw new RuntimeException($message); }
    $checks++;
}
function runPhone(array $responses, string $to = '+5511999999999', string $kind = 'text'): array
{
    $fake = new PhoneResolutionFake();
    $fake->responses = $responses;
    $fake->mime = $kind === 'image' ? 'image/png' : 'application/pdf';
    $payload = ['to' => $to, '_system_message' => true, 'id_matriz_filial' => 13];
    if ($kind === 'text') { $payload['message'] = 'SIMULACAO SEM ENVIO'; }
    else { $payload += ['media_url' => 'https://media.invalid/file', 'caption' => 'SIMULACAO']; }
    $result = $fake->send($payload);
    return [$result, $fake->requests];
}
$original = '5511999999999';
$canonical = '551199999999';
$success = ['http_code' => 200, 'body' => ['success' => true]];
$lid = '1234567890123456@lid';
$lidResponse = lookup($original, $original);
$lidResponse['body']['data']['Users'][0]['JID'] = $lid;
foreach (['text', 'image', 'document'] as $kind) {
    [$result, $requests] = runPhone([$lidResponse, $success], '+' . $original, $kind);
    verifyPhone($result['success'] && count($requests) === 2, "$kind: LID consulta e um envio");
    verifyPhone($requests[0]['path'] === '/user/check' && $requests[0]['data'] === ['Phone' => [$original]], 'LID consulta telefone original');
    verifyPhone($requests[1]['path'] === '/chat/send/' . $kind && $requests[1]['data']['Phone'] === $lid, 'LID completo, sem converter em telefone');
    verifyPhone($requests[0]['token'] === $requests[1]['token'], 'LID usa mesma instancia');
    foreach ([$original, $canonical] as $target) {
        [$result, $requests] = runPhone([lookup($original, $target), $success], '+' . $original, $kind);
        verifyPhone($result['success'] && count($requests) === 2, "$kind: consulta e um envio");
        verifyPhone($requests[0]['path'] === '/user/check' && $requests[0]['data'] === ['Phone' => [$original]], 'Consulta original');
        verifyPhone($requests[1]['path'] === '/chat/send/' . $kind && $requests[1]['data']['Phone'] === $target, 'Usa JID reconhecido');
        verifyPhone($requests[0]['token'] === $requests[1]['token'], 'Mesma instancia na consulta e envio');
    }
}
foreach (['Query' => '5521999999999', 'IsInWhatsapp' => false, 'JID' => '123@g.us'] as $field => $value) {
    $invalidLid = $lidResponse;
    $invalidLid['body']['data']['Users'][0][$field] = $value;
    [$result, $requests] = runPhone([$invalidLid]);
    verifyPhone(!$result['success'] && count($requests) === 1, "LID rejeita $field invalido sem envio");
}
foreach (['', '@lid', 'abc@lid', '123@lid.example', '123:1@lid', "123@lid\n", '0@lid', null, 123] as $invalidJid) {
    $invalidLid = $lidResponse;
    $invalidLid['body']['data']['Users'][0]['JID'] = $invalidJid;
    [$result, $requests] = runPhone([$invalidLid]);
    verifyPhone(!$result['success'] && count($requests) === 1, 'LID malformado nao envia');
}
$ambiguousLid = $lidResponse;
$ambiguousLid['body']['data']['Users'][] = $ambiguousLid['body']['data']['Users'][0];
[$result, $requests] = runPhone([$ambiguousLid]);
verifyPhone(!$result['success'] && count($requests) === 1, 'LID ambiguo nao envia');
$fallbackLid = $lidResponse;
$fallbackLid['body']['data']['Users'][0]['Query'] = $canonical;
[$result, $requests] = runPhone([lookup($original, null), $fallbackLid, $success]);
verifyPhone($result['success'] && count($requests) === 3 && $requests[2]['data']['Phone'] === $lid, 'Fallback de consulta reconhece LID');
[$result, $requests] = runPhone([$lidResponse, ['http_code' => 0, 'body' => null, 'curl_errno' => 28]]);
verifyPhone($result['uncertain'] && !$result['retryable'] && count($requests) === 2, 'Timeout enviando LID nao tenta outro destino');
[$result, $requests] = runPhone([lookup($original, null), lookup($canonical, $canonical), $success]);
verifyPhone($result['success'] && count($requests) === 3 && $requests[1]['data']['Phone'] === [$canonical], 'Fallback somente na consulta');
[$result, $requests] = runPhone([lookup($original, null), lookup($canonical, null)]);
verifyPhone(!$result['success'] && !$result['retryable'] && !$result['uncertain'] && count($requests) === 2, 'Inexistente nao envia');
foreach (['+351912345678', '+14155552671', '+447700900123'] as $international) {
    $digits = substr($international, 1);
    [$result, $requests] = runPhone([lookup($digits, $digits), $success], $international);
    verifyPhone($result['success'] && $requests[1]['data']['Phone'] === $digits, 'Internacional preservado');
}
verifyPhone(WhatsAppService::gerarTelefonesCandidatos('(11) 99999-9999') === [$original, $canonical], 'Formato nacional preservado');

$badQuery = lookup('5521999999999', $canonical);
$badJid = lookup($original, '552199999999');
$badDomain = lookup($original, $canonical);
$badDomain['body']['data']['Users'][0]['JID'] = $canonical . '@g.us';
$ambiguous = lookup($original, $canonical);
$ambiguous['body']['data']['Users'][] = $ambiguous['body']['data']['Users'][0];
$contradiction = lookup($original, $canonical);
$contradiction['body']['data']['Users'][0]['IsInWhatsapp'] = false;
foreach ([
    ['http_code' => 500, 'body' => 'erro'],
    ['http_code' => 401, 'body' => ['success' => false]],
    ['http_code' => 0, 'body' => null, 'curl_errno' => 28],
    ['http_code' => 200, 'body' => '<html>invalid</html>'],
    ['http_code' => 200, 'body' => ['success' => true, 'data' => ['Users' => []]]],
    $badQuery, $badJid, $badDomain, $ambiguous, $contradiction, new RuntimeException('simulado'),
] as $response) {
    [$result, $requests] = runPhone([$response]);
    verifyPhone(!$result['success'] && $result['retryable'] && !$result['uncertain'] && count($requests) === 1, 'Falha na consulta nao envia nem troca candidato');
    verifyPhone($result['diagnostic']['stage'] === 'lookup', 'Etapa de consulta identificada');
}
foreach ([
    ['http_code' => 500, 'body' => 'token=segredo'],
    ['http_code' => 0, 'body' => null, 'curl_errno' => 28],
    ['http_code' => 200, 'body' => ['success' => false]],
    ['http_code' => 200, 'body' => '<html>invalid</html>'],
    ['http_code' => 200, 'body' => null, 'curl_errno' => 28],
    ['http_code' => 400, 'body' => 'invalid phone', 'curl_errno' => 28],
    new RuntimeException('segredo'),
] as $response) {
    [$result, $requests] = runPhone([lookup($original, $canonical), $response]);
    verifyPhone($result['uncertain'] && !$result['retryable'] && count($requests) === 2, 'Envio incerto nao tenta outro numero');
    verifyPhone(!str_contains(json_encode($result), 'segredo'), 'Falha nao expoe resposta bruta');
}
[$result, $requests] = runPhone([lookup($original, $canonical), ['http_code' => 400, 'body' => 'invalid phone']]);
verifyPhone(!$result['uncertain'] && !$result['retryable'] && count($requests) === 2, 'Rejeicao no envio nao tenta outra variante');
[$result, $requests] = runPhone([lookup($original, $canonical), ['http_code' => 0, 'body' => null, 'retryable' => true, 'curl_errno' => 7]]);
verifyPhone($result['retryable'] && !$result['uncertain'] && count($requests) === 2, 'Conexao nao estabelecida pode repetir depois');
echo "OK: {$checks} verificacoes de WhatsApp, sem mensagens reais.\n";
