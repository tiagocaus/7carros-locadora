<?php
/** CLI SAPI fixtures; no database, network, tokens or application state. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || function_exists('getallheaders')) {
    throw new RuntimeException('Execute com PHP CLI sem getallheaders nativo.');
}
if (!function_exists('getallheaders')) {
    function getallheaders(): array { return $GLOBALS['fixtureSapiHeaders']; }
}
require dirname(__DIR__) . '/vendor/autoload.php';

$cases = [
    [[], ['HTTP_AUTHORIZATION' => 'Bearer cgi-fixture'], 'Bearer cgi-fixture'],
    [[], ['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer redirect-fixture'], 'Bearer redirect-fixture'],
    [[], ['HTTP_AUTHORIZATION' => 'Bearer canonical-fixture', 'REDIRECT_HTTP_AUTHORIZATION' => 'Bearer old-fixture'], 'Bearer canonical-fixture'],
    [['aUtHoRiZaTiOn' => 'Bearer original-fixture'], ['HTTP_AUTHORIZATION' => 'Bearer other-fixture'], 'Bearer original-fixture'],
    [['Authorization' => ''], ['HTTP_AUTHORIZATION' => 'Bearer other-fixture'], ''],
    [[], [], ''],
    [['X-Forwarded-Authorization' => 'Bearer untrusted-fixture'], [], ''],
];
$count = 0;
foreach ($cases as [$sapi, $cgi, $expected]) {
    $GLOBALS['fixtureSapiHeaders'] = $sapi + ['Host' => 'mcp.example.test', 'Content-Type' => 'application/json', 'X-Fixture' => 'preserved'];
    $_SERVER = $cgi + ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/mcp', 'HTTP_HOST' => 'mcp.example.test',
        'SERVER_PORT' => '443', 'HTTPS' => 'on'];
    $_GET = ['access_token' => 'query-must-not-authenticate']; $_POST = []; $_COOKIE = []; $_FILES = [];
    $request = \App\Controllers\Mcp\Gateway::request();
    if ($request->getHeaderLine('Authorization') !== $expected || $request->getHeaderLine('X-Fixture') !== 'preserved'
        || $request->getUri()->getPath() !== '/mcp' || $request->getHeader('Host') !== ['mcp.example.test']) {
        throw new RuntimeException('Falha na normalização CGI: caso ' . $count);
    }
    $count++;
}
$guard = new \Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware(['mcp.example.test']);
$next = new class implements \Psr\Http\Server\RequestHandlerInterface {
    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
        return new \Nyholm\Psr7\Response(200);
    }
};
foreach ([['mcp.example.test', 'mcp.example.test', 200], ['mcp.example.test:8443', 'mcp.example.test:8443', 200],
    ['mcp.example.test:443', 'mcp.example.test:443', 200], ['mcp.example.test', 'invalid.example', 403],
    ['mcp.example.test', ['mcp.example.test', 'invalid.example'], 403],
    ['invalid.example', 'invalid.example', 403], ['mcp.example.test', null, 200]] as [$source, $sapi, $expected]) {
    $_SERVER['HTTP_HOST'] = $source;
    $GLOBALS['fixtureSapiHeaders'] = $sapi === null ? [] : ['Host' => $sapi];
    $request = \App\Controllers\Mcp\Gateway::request();
    if ($guard->process($request, $next)->getStatusCode() !== $expected
        || ($expected === 200 && count($request->getHeader('Host')) !== 1)) {
        throw new RuntimeException('Falha na validação Host: caso ' . $count);
    }
    $count++;
}
echo "OK: $count cenários de headers CGI/SAPI, sem banco ou rede.\n";
