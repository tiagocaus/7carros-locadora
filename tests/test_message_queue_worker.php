#!/usr/bin/env php
<?php

// Broker, banco e provedores inteiramente simulados; nenhuma conexao externa.
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Crons\Jobs\ProcessMessageQueueJob;
use App\Models\MessageQueueDelivery;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;

(new ReflectionProperty(Database::class, 'config'))->setValue(null, ['QUEUE_MAX_MESSAGES_PER_RUN' => 2]);
$_SESSION = [];

class WorkerMemoryStore extends MessageQueueDelivery
{
    public array $rows = [];
    public function __construct(int $count)
    {
        for ($i = 1; $i <= $count; $i++) {
            $this->rows[$i] = ['id' => $i, 'chave' => '1111111111111', 'type' => 'whatsapp',
                'payload' => '{"message":"conteudo do banco"}', 'status' => 'PENDING',
                'attempts' => 0, 'batch_id' => null, 'updated_at' => null];
        }
    }
    public function findMessage(int $id, string $chave): ?array { return $this->rows[$id] ?? null; }
    public function claim(int $id, string $chave, int $maxAttempts): bool
    {
        if (($this->rows[$id]['chave'] ?? '') !== $chave || $this->rows[$id]['status'] !== 'PENDING') { return false; }
        $this->rows[$id]['status'] = 'PROCESSING';
        $this->rows[$id]['attempts']++;
        return true;
    }
    public function finish(int $id, string $chave, string $status, ?string $error = null): bool
    {
        $this->rows[$id]['status'] = $status;
        return true;
    }
    public function pending(int $limit, int $maxAttempts, ?string $before = null): array
    {
        return array_slice(array_values(array_filter($this->rows, fn($r) => $r['status'] === 'PENDING')), 0, $limit);
    }
    public function publicationFailures(int $maxAttempts, string $since): array { return []; }
    public function preparePublication(array $message, int $maxAttempts): bool { return $this->rows[$message['id']]['status'] === 'PENDING'; }
    public function expireProcessing(string $before): array { return []; }
    public function failExhausted(int $maxAttempts): void {}
}

class WorkerFakeChannel
{
    public array $queue = [];
    public array $published = [];
    public bool $active = false;
    public bool $failAck = false;
    public bool $failWait = false;
    public int $otherConsumers = 0;
    private $callback;
    public function queue_declare(...$args) { return ['fake', count($this->queue), $this->otherConsumers]; }
    public function basic_qos(...$args) {}
    public function basic_consume(...$args) { $this->active = true; $this->callback = $args[6]; return 'fake'; }
    public function basic_cancel(...$args) { $this->active = false; }
    public function is_consuming() { return $this->active; }
    public function is_open() { return true; }
    public function close() { $this->active = false; }
    public function basic_publish($message, ...$args) { $this->published[] = json_decode($message->body, true); }
    public function wait(...$args)
    {
        if ($this->failWait) { throw new RuntimeException('Broker indisponivel simulado'); }
        if ($this->queue === []) { throw new AMQPTimeoutException('Fila vazia simulada'); }
        $id = array_shift($this->queue);
        $message = new class($id, $this->failAck) {
            public string $body;
            public function __construct(int $id, private bool $failAck)
            {
                $this->body = json_encode(['id' => $id, 'chave' => '1111111111111', 'type' => 'sms', 'payload' => ['message' => 'conteudo adulterado']]);
            }
            public function ack() { if ($this->failAck) { throw new RuntimeException('ACK falhou'); } }
            public function getConsumerTag() { return 'fake'; }
        };
        ($this->callback)($message);
    }
}

class WorkerFakeConnection extends AMQPStreamConnection
{
    public function __construct(private WorkerFakeChannel $fakeChannel) {}
    public function channel($channel_id = null) { return $this->fakeChannel; }
    public function close($reply_code = 0, $reply_text = '', $method_sig = [0, 0]) {}
    public function __destruct() {}
}

class WorkerUnderTest extends ProcessMessageQueueJob
{
    public int $sends = 0;
    public array $logsForTest = [];
    public function __construct(private WorkerMemoryStore $store, private WorkerFakeChannel $broker)
    {
        $policy = (new ReflectionClass(App\Services\NotificationChannelPolicyService::class))->newInstanceWithoutConstructor();
        parent::__construct($policy);
    }
    protected function createMessageStore(): MessageQueueDelivery { return $this->store; }
    protected function getConnection(): AMQPStreamConnection { return new WorkerFakeConnection($this->broker); }
    protected function processMessage(string $type, array $payload, ?string $chave = null): array
    {
        if ($type !== 'whatsapp' || $payload['message'] !== 'conteudo do banco') { throw new RuntimeException('Envelope do broker usado no envio'); }
        $this->sends++;
        return ['success' => true];
    }
    protected function log(string $message, string $level = 'INFO'): void { $this->logsForTest[] = $message; }
    public function runTest(): array { return $this->handle(); }
}

function checkWorker(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }

$store = new WorkerMemoryStore(1); $broker = new WorkerFakeChannel(); $broker->queue = [1, 1];
$worker = new WorkerUnderTest($store, $broker); $result = $worker->runTest();
checkWorker($worker->sends === 1 && $result['data']['ignored'] === 1, 'Copias devem causar somente um envio com payload do banco');

$store = new WorkerMemoryStore(2); $broker = new WorkerFakeChannel(); $broker->queue = [1]; $broker->failAck = true;
$worker = new WorkerUnderTest($store, $broker); $result = $worker->runTest();
checkWorker($worker->sends === 2 && $store->rows[1]['attempts'] === 1 && $store->rows[1]['status'] === 'SENT', 'Falha no ACK nao pode reabrir mensagem enviada');
checkWorker($broker->published === [], 'Erro no broker nao deve republicar pendentes');

$store = new WorkerMemoryStore(2); $broker = new WorkerFakeChannel(); $broker->queue = [1, 1];
$worker = new WorkerUnderTest($store, $broker); $worker->runTest();
checkWorker($broker->published === [] && $store->rows[2]['status'] === 'PENDING', 'Limite atingido nao deve gerar copias');

$store = new WorkerMemoryStore(2); $broker = new WorkerFakeChannel(); $broker->queue = [1];
$worker = new WorkerUnderTest($store, $broker); $worker->runTest();
checkWorker(count($broker->published) === 1 && $broker->published[0]['id'] === 2, 'Fila vazia deve recuperar pendente perdido');

$store = new WorkerMemoryStore(2); $broker = new WorkerFakeChannel(); $broker->queue = [1]; $broker->otherConsumers = 1;
$worker = new WorkerUnderTest($store, $broker); $worker->runTest();
checkWorker($broker->published === [], 'Outro consumidor pode estar processando a fila');

$store = new WorkerMemoryStore(1); $broker = new WorkerFakeChannel(); $broker->failWait = true;
$worker = new WorkerUnderTest($store, $broker); $worker->runTest();
checkWorker($worker->sends === 1 && $store->rows[1]['status'] === 'SENT', 'Fallback deve enviar pelo mesmo controle');

echo "OK: consumidor validado com broker e provedores simulados.\n";
