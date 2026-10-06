#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Helpers/helpers.php';

use App\Crons\Jobs\BaseJob;
use App\Crons\Jobs\SyncWhatsappStatusJob;
use App\Models\Whatsapp;
use App\Services\WhatsAppSessionStatusService;

function assertWhatsappStatus(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function statusResponse(bool $connected, bool $loggedIn, bool $camelCase = false, ?string $jid = null): array
{
    $data = $camelCase
        ? ['connected' => $connected, 'loggedIn' => $loggedIn]
        : ['Connected' => $connected, 'LoggedIn' => $loggedIn];

    if ($jid !== null) {
        $data[$camelCase ? 'jid' : 'Jid'] = $jid;
    }

    return [
        'http_code' => 200,
        'curl_errno' => 0,
        'body' => json_encode(['code' => 200, 'data' => $data, 'success' => true]),
    ];
}

class FakeWhatsAppSessionStatusService extends WhatsAppSessionStatusService
{
    public array $responses = [];
    public int $requests = 0;

    public function __construct(array $responses = [])
    {
        parent::__construct('https://provider.invalid');
        $this->responses = $responses;
    }

    protected function requestStatus(string $instanceToken): array
    {
        $this->requests++;
        $response = array_shift($this->responses);
        if (!is_array($response)) {
            throw new RuntimeException('Requisicao simulada inesperada');
        }

        return $response;
    }

    protected function pauseBeforeConfirmation(): void
    {
        // Teste sem espera real.
    }
}

class FakeWhatsappStatusModel extends Whatsapp
{
    public array $connections = [];
    public array $updates = [];

    public function __construct()
    {
        // Sem banco: o teste exercita apenas a orquestracao do job.
    }

    public function listarParaSincronizacaoStatus(): array
    {
        return $this->connections;
    }

    public function atualizarStatus(int $id, string $status, ?string $remoteJid = null): int
    {
        $this->updates[] = [
            'id' => $id,
            'status' => $status,
            'remoteJid' => $remoteJid,
            'chave' => $_SESSION['chave'] ?? null,
        ];

        return 1;
    }
}

$service = new FakeWhatsAppSessionStatusService([statusResponse(true, true)]);
$result = $service->consultar('token-falso');
assertWhatsappStatus($result['conclusive'] && $result['status'] === 'connected', 'PascalCase conectado nao reconhecido');

$service = new FakeWhatsAppSessionStatusService([statusResponse(true, false, true)]);
$result = $service->consultar('token-falso');
assertWhatsappStatus($result['conclusive'] && $result['status'] === 'connecting', 'camelCase conectando nao reconhecido');

$service = new FakeWhatsAppSessionStatusService([
    statusResponse(false, false),
    statusResponse(false, false),
]);
$result = $service->consultarComConfirmacao('token-falso');
assertWhatsappStatus($result['conclusive'] && $result['status'] === 'disconnected', 'Duas negativas deveriam confirmar desconexao');
assertWhatsappStatus($result['attempts'] === 2 && $service->requests === 2, 'Desconexao deveria usar duas consultas');

$service = new FakeWhatsAppSessionStatusService([
    statusResponse(false, false),
    statusResponse(true, true, true, '5511999999999@s.whatsapp.net'),
]);
$result = $service->consultarComConfirmacao('token-falso');
assertWhatsappStatus($result['conclusive'] && $result['status'] === 'connected', 'Resposta positiva deve vencer oscilacao negativa');
assertWhatsappStatus($result['owner'] === '5511999999999@s.whatsapp.net', 'JID valido deveria ser preservado');

$service = new FakeWhatsAppSessionStatusService([
    statusResponse(false, false),
    ['http_code' => 0, 'curl_errno' => 28, 'body' => false],
]);
$result = $service->consultarComConfirmacao('token-falso');
assertWhatsappStatus(!$result['conclusive'], 'Timeout na confirmacao deve ser inconclusivo');
assertWhatsappStatus(str_starts_with($result['reason'], 'disconnect_confirmation_'), 'Motivo da confirmacao inconclusiva ausente');

$invalidResponses = [
    ['http_code' => 0, 'curl_errno' => 6, 'body' => false],
    ['http_code' => 401, 'curl_errno' => 0, 'body' => '{}'],
    ['http_code' => 404, 'curl_errno' => 0, 'body' => '{}'],
    ['http_code' => 500, 'curl_errno' => 0, 'body' => '{}'],
    ['http_code' => 200, 'curl_errno' => 0, 'body' => ''],
    ['http_code' => 200, 'curl_errno' => 0, 'body' => '<html>erro</html>'],
    ['http_code' => 200, 'curl_errno' => 0, 'body' => json_encode(['code' => 200, 'data' => [], 'success' => true])],
    ['http_code' => 200, 'curl_errno' => 0, 'body' => json_encode(['code' => 200, 'data' => ['Connected' => 'false', 'LoggedIn' => 'false'], 'success' => true])],
    ['http_code' => 200, 'curl_errno' => 0, 'body' => json_encode(['code' => 200, 'data' => ['Connected' => false, 'LoggedIn' => false], 'success' => false])],
];

foreach ($invalidResponses as $index => $invalidResponse) {
    $service = new FakeWhatsAppSessionStatusService([$invalidResponse]);
    $result = $service->consultar('token-falso');
    assertWhatsappStatus(!$result['conclusive'] && $result['status'] === null, "Resposta invalida #{$index} nao deveria mudar status");
}

$model = new FakeWhatsappStatusModel();
$model->connections = [
    ['id' => 1, 'instanceName' => 'token-1', 'status' => 'CONNECTED', 'chave' => '1111111111111'],
    ['id' => 2, 'instanceName' => 'token-2', 'status' => 'CONNECTED', 'chave' => '1111111111111'],
    ['id' => 3, 'instanceName' => 'token-3', 'status' => 'DISCONNECTED', 'chave' => '1111111111111'],
    ['id' => 4, 'instanceName' => 'token-4', 'status' => 'CONNECTING', 'chave' => '1111111111111'],
];
$service = new FakeWhatsAppSessionStatusService([
    ['http_code' => 0, 'curl_errno' => 28, 'body' => false],
    statusResponse(false, false),
    statusResponse(false, false),
    statusResponse(true, true, true, '5511999999999@s.whatsapp.net'),
    statusResponse(false, false),
    statusResponse(true, true),
]);

$_SESSION = ['chave' => 'sessao-original', 'user_id' => 99];
$jobResult = (new SyncWhatsappStatusJob($service, $model))->run();

assertWhatsappStatus($jobResult['status'] === BaseJob::STATUS_PARTIAL, 'Job com uma consulta inconclusiva deveria ser parcial');
assertWhatsappStatus($jobResult['data']['checked'] === 4, 'Quantidade verificada incorreta');
assertWhatsappStatus($jobResult['data']['inconclusive'] === 1, 'Quantidade inconclusiva incorreta');
assertWhatsappStatus($jobResult['data']['disconnected_confirmed'] === 1, 'Desconexao confirmada nao contabilizada');
assertWhatsappStatus($jobResult['data']['recovered'] === 1, 'Recuperacao automatica nao contabilizada');
assertWhatsappStatus(count($model->updates) === 3, 'Job deveria fazer exatamente tres atualizacoes');
assertWhatsappStatus($model->updates[0]['status'] === 'disconnected', 'Segunda conexao deveria desconectar');
assertWhatsappStatus($model->updates[1]['status'] === 'connected', 'Conexao desconectada deveria se recuperar');
assertWhatsappStatus($model->updates[2]['status'] === 'connected', 'Oscilacao deveria terminar conectada');
assertWhatsappStatus($_SESSION === ['chave' => 'sessao-original', 'user_id' => 99], 'Job deveria restaurar a sessao original');

echo "OK: sincronizacao resiliente de status WhatsApp\n";
