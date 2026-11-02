#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Helpers/helpers.php';

use App\Controllers\WhatsappController;

function assertWhatsappProxy(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

class FakeWhatsappProxyController extends WhatsappController
{
    public array $requests = [];
    public array $responses = [];

    public function __construct(bool $enabled, array $config)
    {
        parent::__construct(null, $config, $enabled);
    }

    public function provision(string $instanceName): array
    {
        return $this->provisionInstance($instanceName);
    }

    public function applyProxy(string $instanceName): array
    {
        return $this->setProxy($instanceName);
    }

    public function connectSessionForTest(string $instanceName): array
    {
        return $this->connectSession($instanceName);
    }

    public function restartForTest(string $instanceName): array
    {
        return $this->restartInstance($instanceName);
    }

    protected function makeRequest(
        string $url,
        string $method = 'GET',
        array $data = [],
        string $auth = 'admin',
        string $instanceToken = ''
    ): array {
        $this->requests[] = compact('url', 'method', 'data', 'auth', 'instanceToken');
        $response = array_shift($this->responses);

        if (!is_array($response)) {
            throw new RuntimeException('Requisicao simulada inesperada');
        }

        return $response;
    }
}

$config = [
    'protocol' => 'http',
    'host' => 'proxy.test',
    'port' => '8080',
    'username' => 'user@x',
    'password' => 'p:ss',
];

$controller = new FakeWhatsappProxyController(false, $config);
$controller->responses = [
    ['success' => true, 'data' => ['data' => ['id' => 'disabled-id']]],
];
$result = $controller->provision('locadora_disabled');
assertWhatsappProxy($result['success'], 'Proxy desabilitado nao deveria bloquear o provisionamento');
assertWhatsappProxy(count($controller->requests) === 1, 'Proxy desabilitado deveria executar somente a criacao');
assertWhatsappProxy(str_ends_with($controller->requests[0]['url'], '/admin/users'), 'Criacao remota nao executada');

$controller = new FakeWhatsappProxyController(true, array_replace($config, ['host' => '']));
$result = $controller->provision('locadora_invalid');
assertWhatsappProxy(!$result['success'], 'Proxy habilitado sem host deveria falhar');
assertWhatsappProxy($controller->requests === [], 'Configuracao invalida nao deveria criar instancia remota');

$controller = new FakeWhatsappProxyController(true, $config);
$controller->responses = [
    ['success' => true, 'data' => []],
];
$result = $controller->applyProxy('locadora_proxy');
assertWhatsappProxy($result['success'], 'Proxy valido deveria ser aplicado');
assertWhatsappProxy(
    $controller->requests[0]['data']['proxy_url'] === 'http://user%40x:p%3Ass@proxy.test:8080',
    'URL do proxy nao foi montada ou codificada corretamente'
);

$controller = new FakeWhatsappProxyController(true, $config);
$controller->responses = [
    ['success' => true, 'data' => ['data' => ['id' => 'new-id']]],
    ['success' => true, 'data' => ['data' => ['id' => 'new-id']]],
    ['success' => false, 'message' => 'provider failure', 'data' => null, 'http_code' => 502],
    ['success' => true, 'data' => []],
];
$result = $controller->provision('locadora_rollback');
assertWhatsappProxy(!$result['success'], 'Falha ao aplicar proxy deveria bloquear o provisionamento');
assertWhatsappProxy(count($controller->requests) === 4, 'Falha de proxy deveria criar, aguardar, aplicar e desfazer');
assertWhatsappProxy(str_ends_with($controller->requests[1]['url'], '/admin/users/new-id'), 'Espera deveria usar o novo instanceId');
assertWhatsappProxy(str_ends_with($controller->requests[2]['url'], '/session/proxy'), 'Proxy nao foi aplicado apos a espera');
assertWhatsappProxy(str_ends_with($controller->requests[3]['url'], '/admin/users/new-id/full'), 'Rollback remoto nao foi executado');
assertWhatsappProxy(!str_contains($result['message'], 'provider failure'), 'Erro bruto do provedor nao deveria ser exposto');

$controller = new FakeWhatsappProxyController(true, $config);
$controller->responses = [
    ['success' => false, 'message' => 'provider failure', 'data' => null, 'http_code' => 502],
];
$result = $controller->connectSessionForTest('locadora_connect');
assertWhatsappProxy(!$result['success'], 'Conexao deveria falhar quando o proxy falha');
assertWhatsappProxy(count($controller->requests) === 1, 'Session connect nao deveria ocorrer apos falha do proxy');
assertWhatsappProxy(str_ends_with($controller->requests[0]['url'], '/session/proxy'), 'Proxy deveria preceder session connect');

$controller = new FakeWhatsappProxyController(true, $config);
$controller->responses = [
    ['success' => true, 'data' => []],
    ['success' => false, 'message' => 'provider failure', 'data' => null, 'http_code' => 502],
];
$result = $controller->restartForTest('locadora_restart');
assertWhatsappProxy(!$result['success'], 'Reinicio deveria falhar quando o proxy falha');
assertWhatsappProxy(count($controller->requests) === 2, 'Reinicio nao deveria reconectar apos falha do proxy');
assertWhatsappProxy(str_ends_with($controller->requests[0]['url'], '/session/disconnect'), 'Reinicio deveria desconectar primeiro');
assertWhatsappProxy(str_ends_with($controller->requests[1]['url'], '/session/proxy'), 'Reinicio deveria aplicar proxy antes de reconectar');

echo "OK: proxy obrigatorio nos fluxos WhatsApp\n";
