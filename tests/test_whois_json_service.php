<?php

/**
 * Testa a integracao WhoisJSON sem consumir a API externa.
 *
 * Execute: php tests/test_whois_json_service.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\WhoisJsonService;

function assertWhoisJson(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertWhoisJsonThrows(callable $callback, string $expectedMessage): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        assertWhoisJson(
            $exception->getMessage() === $expectedMessage,
            'Erro inesperado: ' . $exception->getMessage()
        );
        return;
    }

    throw new RuntimeException('Era esperada uma excecao: ' . $expectedMessage);
}

$requests = [];
$responses = [
    ['status' => 200, 'body' => '{"domain":"exemplo.com.br","available":true}', 'curl_errno' => 0],
    ['status' => 200, 'body' => '{"domain":"exemplo.com.br","available":false}', 'curl_errno' => 0],
    ['status' => 200, 'body' => '{"domain":"exemplo.com.br","available":"unknown"}', 'curl_errno' => 0],
];

$httpClient = static function (
    string $url,
    array $headers,
    int $connectTimeout,
    int $requestTimeout
) use (&$requests, &$responses): array {
    $requests[] = compact('url', 'headers', 'connectTimeout', 'requestTimeout');
    return array_shift($responses);
};

$service = new WhoisJsonService($httpClient, 'token-teste');
$disponivel = $service->verificarDisponibilidade(' HTTPS://WWW.Exemplo.com.br/caminho ');
$registrado = $service->verificarDisponibilidade('exemplo.com.br');
$inconclusivo = $service->verificarDisponibilidade('exemplo.com.br');

assertWhoisJson($disponivel === [
    'dominio' => 'exemplo.com.br',
    'disponivel' => true,
], 'Dominio disponivel ou normalizacao incorretos.');
assertWhoisJson($registrado['disponivel'] === false, 'Dominio registrado deve retornar false.');
assertWhoisJson($inconclusivo['disponivel'] === null, 'Status unknown deve retornar null.');
assertWhoisJson(
    $requests[0]['url'] === 'https://whoisjson.com/api/v1/domain-availability?domain=exemplo.com.br',
    'Endpoint ou query string incorretos.'
);
assertWhoisJson(
    in_array('Authorization: TOKEN=token-teste', $requests[0]['headers'], true),
    'Header de autenticacao incorreto.'
);
assertWhoisJson(
    $requests[0]['connectTimeout'] === 2 && $requests[0]['requestTimeout'] === 5,
    'Timeouts incorretos.'
);

assertWhoisJsonThrows(
    fn() => (new WhoisJsonService($httpClient, ''))->verificarDisponibilidade('exemplo.com.br'),
    'APIWHOISJSON_API_KEY nao configurada'
);
assertWhoisJsonThrows(
    fn() => $service->verificarDisponibilidade('dominio-invalido'),
    'Dominio invalido'
);

foreach ([401, 429, 500] as $httpStatus) {
    $httpError = static fn(): array => [
        'status' => $httpStatus,
        'body' => '{}',
        'curl_errno' => 0,
    ];
    assertWhoisJsonThrows(
        fn() => (new WhoisJsonService($httpError, 'token'))->verificarDisponibilidade('exemplo.com'),
        'WhoisJSON retornou HTTP ' . $httpStatus
    );
}

$timeout = static fn(): array => ['status' => 0, 'body' => '', 'curl_errno' => 28];
assertWhoisJsonThrows(
    fn() => (new WhoisJsonService($timeout, 'token'))->verificarDisponibilidade('exemplo.com'),
    'Falha de conexao com WhoisJSON (cURL 28)'
);

$invalidJson = static fn(): array => ['status' => 200, 'body' => 'invalid', 'curl_errno' => 0];
assertWhoisJsonThrows(
    fn() => (new WhoisJsonService($invalidJson, 'token'))->verificarDisponibilidade('exemplo.com'),
    'Resposta invalida do WhoisJSON'
);

echo "OK: WhoisJSON trata disponibilidade, normalizacao, unknown e falhas sem chamada externa.\n";
