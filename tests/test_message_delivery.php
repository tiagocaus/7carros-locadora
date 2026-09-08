#!/usr/bin/env php
<?php

// Apenas MySQL local e provedores simulados. Nunca publica no RabbitMQ.
require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Model;
use App\Models\MessageQueueDelivery;
use App\Services\MessageDeliveryService;
use App\Services\WhatsAppService;
use App\Services\EmailService;
use App\Crons\Jobs\ProcessMessageQueueJob;
use App\Classes\QueryBuilder;

foreach (file(__DIR__ . '/../.env.development', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (preg_match('/^(DB_[A-Z_]+)=(.*)$/', $line, $match)) {
        $_ENV[$match[1]] = trim($match[2], " \"'");
        putenv($match[1] . '=' . $_ENV[$match[1]]);
    }
}
if (($_ENV['DB_HOST'] ?? '') !== 'localhost') {
    throw new RuntimeException('Este teste exige DB_HOST=localhost');
}
$_ENV['APP_ENV'] = 'development';
date_default_timezone_set('America/Sao_Paulo');
$_SESSION = [];
$tenant = '1111111111111';
$model = new MessageQueueDelivery();
if (($argv[1] ?? '') === '--claim') {
    echo $model->claim((int) $argv[2], $tenant, 3) ? '1' : '0';
    exit;
}
$qb = new QueryBuilder(Model::sharedMysqli());
$batch = 'test_delivery_' . bin2hex(random_bytes(8));
$checks = 0;
function checkDelivery(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) { throw new RuntimeException($message); }
    $checks++;
}
function fixtureDelivery(string $status = 'PENDING', int $attempts = 0, ?string $error = null): int
{
    global $qb, $tenant, $batch;
    return $qb->table('messages_queue')->withChave($tenant)->insert([
        'chave' => $tenant, 'batch_id' => $batch, 'type' => 'whatsapp', 'status' => $status,
        'payload' => json_encode(['message' => 'SIMULACAO SEM ENVIO', 'to' => 'invalid']),
        'attempts' => $attempts, 'error_message' => $error,
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s', time() - 1200),
    ]);
}
$service = new MessageDeliveryService($model, 3);
$calls = 0;
$sender = function ($type, $payload, $chave) use (&$calls, $tenant) {
    checkDelivery($type === 'whatsapp' && $payload['message'] === 'SIMULACAO SEM ENVIO' && $chave === $tenant,
        'Tipo, conteudo e tenant devem vir do banco');
    $calls++;
    return ['success' => true];
};
try {
    $id = fixtureDelivery();
    checkDelivery($service->deliver($id, $tenant, $sender)['outcome'] === 'sent', 'Primeiro envio');
    $sent = $model->findMessage($id, $tenant);
    checkDelivery($service->deliver($id, $tenant, $sender)['outcome'] === 'ignored', 'Copia deve ser ignorada');
    checkDelivery($calls === 1 && $model->findMessage($id, $tenant) === $sent, 'Copia nao altera historico');
    checkDelivery(!$model->preparePublication($sent, 3), 'Recuperacao nao reativa SENT');

    $id = fixtureDelivery();
    $stale = $model->findMessage($id, $tenant);
    checkDelivery($service->deliver($id, 'outro-tenant', $sender)['outcome'] === 'ignored', 'Isolamento tenant');
    $service->deliver($id, $tenant, function () use ($service, $id, $tenant, $sender) {
        checkDelivery($service->deliver($id, $tenant, $sender)['outcome'] === 'ignored', 'Reserva bloqueia consumidor concorrente');
        return ['success' => true];
    });
    checkDelivery(!$model->preparePublication($stale, 3), 'Snapshot antigo nao pode reativar envio concluido');

    $id = fixtureDelivery();
    $failure = fn() => ['success' => false, 'retryable' => true];
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $result = $service->deliver($id, $tenant, $failure);
        checkDelivery($result['attempt'] === $attempt, 'Tentativas nao devem reiniciar');
        checkDelivery($result['outcome'] === ($attempt < 3 ? 'pending' : 'failed'), 'Limite de tentativas');
    }
    checkDelivery($service->deliver($id, $tenant, $sender)['outcome'] === 'ignored', 'FAILED nao envia');

    $id = fixtureDelivery();
    checkDelivery($service->deliver($id, $tenant, fn() => ['success' => false])['outcome'] === 'uncertain', 'Falha sem classificacao e incerta');
    checkDelivery($model->findMessage($id, $tenant)['error_message'] === MessageQueueDelivery::UNCERTAIN, 'Erro identificavel para revisao');
    checkDelivery($service->deliver($id, $tenant, $sender)['outcome'] === 'ignored', 'Incerto nao reenvia');

    $id = fixtureDelivery();
    checkDelivery($service->deliver($id, $tenant, function () { throw new RuntimeException('simulado'); })['outcome'] === 'uncertain', 'Excecao nao autoriza repeticao');
    $id = fixtureDelivery();
    checkDelivery($service->deliver($id, $tenant, fn() => ['success' => true, 'skipped' => true])['outcome'] === 'skipped', 'Preferencia bloqueada');
    checkDelivery($service->deliver($id, $tenant, $sender)['outcome'] === 'ignored', 'SKIPPED nao reenvia');

    $id = fixtureDelivery('PROCESSING', 1);
    Model::sharedMysqli()->begin_transaction();
    try {
        checkDelivery(in_array($id, $model->expireProcessing(date('Y-m-d H:i:s', time() - 600)), true), 'Processamento antigo exige revisao');
        checkDelivery($service->deliver($id, $tenant, $sender)['outcome'] === 'ignored', 'Expirado nao reenvia');
    } finally {
        // A rotina e global do CRON; desfaz inclusive qualquer linha preexistente.
        Model::sharedMysqli()->rollback();
    }
    $id = fixtureDelivery('FAILED', 2, 'Erro ao publicar na fila: teste');
    checkDelivery($model->preparePublication($model->findMessage($id, $tenant), 3), 'Falha de publicacao pode recuperar');
    checkDelivery((int) $model->findMessage($id, $tenant)['attempts'] === 2, 'Republicacao preserva contador');
    $pendingSnapshot = $model->findMessage($id, $tenant);
    $service->deliver($id, $tenant, $sender); // Fallback enviou antes de a copia chegar.
    checkDelivery(!$model->preparePublication($pendingSnapshot, 3), 'Fallback seguido de republicacao nao reativa envio');

    checkDelivery(!ProcessMessageQueueJob::shouldRecoverPending(false, 2, 50), 'Fila nao vazia nao recupera');
    checkDelivery(!ProcessMessageQueueJob::shouldRecoverPending(true, 50, 50), 'Limite atingido nao recupera');
    checkDelivery(ProcessMessageQueueJob::shouldRecoverPending(true, 2, 50), 'Fila vazia permite recuperar');

    // Concorrencia real: duas conexoes independentes disputam o mesmo UPDATE.
    $id = fixtureDelivery();
    $processes = [];
    for ($i = 0; $i < 2; $i++) {
        $pipes = [];
        $proc = proc_open([PHP_BINARY, __FILE__, '--claim', (string) $id],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) { throw new RuntimeException('Falha ao iniciar teste concorrente'); }
        fclose($pipes[0]);
        $processes[] = [$proc, $pipes];
    }
    $winners = 0;
    foreach ($processes as [$proc, $pipes]) {
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        checkDelivery(proc_close($proc) === 0 && $err === '', 'Processo concorrente deve concluir');
        $winners += (int) $out;
    }
    checkDelivery($winners === 1 && (int) $model->findMessage($id, $tenant)['attempts'] === 1, 'Somente uma reserva atomica vence');

    $whatsapp = new class extends WhatsAppService {
        public array $responses = [];
        public int $calls = 0;
        protected function makeRequest(string $url, string $instanceToken, array $data): array
        {
            $this->calls++;
            return array_shift($this->responses);
        }
    };
    $method = new ReflectionMethod($whatsapp, 'sendWithPhoneFallback');
    foreach ([
        [['http_code' => 500, 'body' => 'erro'], 'uncertain'],
        [['http_code' => 0, 'body' => null], 'uncertain'],
        [['http_code' => 0, 'body' => null, 'retryable' => true], 'retryable'],
    ] as [$response, $flag]) {
        $whatsapp->calls = 0; $whatsapp->responses = [$response];
        $result = $method->invoke($whatsapp, 'https://invalid', 'fake', '5511999999999', ['Body' => 'teste'], 'ok');
        checkDelivery(!empty($result[$flag]) && $whatsapp->calls === 1, 'Erro nao deve tentar outro numero');
    }
    $whatsapp->calls = 0;
    $whatsapp->responses = [['http_code' => 400, 'body' => 'invalid phone'], ['http_code' => 200, 'body' => []]];
    $result = $method->invoke($whatsapp, 'https://invalid', 'fake', '5511999999999', ['Body' => 'teste'], 'ok');
    checkDelivery($result['success'] && $whatsapp->calls === 2, 'Rejeicao explicita permite variante');

    $_ENV['MAIL_FROM_ADDRESS'] = 'test@example.invalid';
    $email = new EmailService();
    $mailer = new class(true) extends PHPMailer\PHPMailer\PHPMailer {
        public bool $connectFails = true;
        public function smtpConnect($options = null) { return !$this->connectFails; }
        public function send() { throw new PHPMailer\PHPMailer\Exception('simulado'); }
    };
    (new ReflectionProperty($email, 'mailer'))->setValue($email, $mailer);
    $payload = ['to' => 'test@example.invalid', 'subject' => 'teste', 'body' => 'teste', '_system_message' => true];
    checkDelivery($email->send($payload)['retryable'] === true, 'Falha de conexao SMTP pode repetir');
    $mailer->connectFails = false;
    checkDelivery($email->send($payload)['uncertain'] === true, 'Falha durante SMTP exige revisao');

    echo "OK: {$checks} verificacoes, sem mensagens reais.\n";
} finally {
    $qb->table('messages_queue')->withChave($tenant)->where('batch_id', '=', $batch)->delete();
}
