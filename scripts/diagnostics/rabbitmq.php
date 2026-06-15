#!/usr/bin/env php
<?php

declare(strict_types=1);

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este diagnostico deve ser executado via CLI.\n");
    exit(1);
}

$root = dirname(__DIR__, 2);
$autoload = $root . '/vendor/autoload.php';

if (!file_exists($autoload)) {
    fwrite(STDERR, "vendor/autoload.php nao encontrado.\n");
    exit(1);
}

require_once $autoload;

$envFile = $root . '/.env.production';
if (!file_exists($envFile)) {
    fwrite(STDERR, ".env.production nao encontrado.\n");
    exit(1);
}

$env = loadEnv($envFile);

$host = (string) ($env['RABBITMQ_HOST'] ?? 'localhost');
$port = (int) ($env['RABBITMQ_PORT'] ?? 5672);
$user = (string) ($env['RABBITMQ_USER'] ?? 'guest');
$password = (string) ($env['RABBITMQ_PASSWORD'] ?? 'guest');
$vhost = (string) ($env['RABBITMQ_VHOST'] ?? '/');
$queue = (string) ($env['RABBITMQ_QUEUE_NAME'] ?? 'messages_queue');
$diagnosticQueue = $queue . '_diagnostic';

$failed = false;

line('RabbitMQ diagnostic');
line('Host: ' . $host);
line('Port: ' . $port);
line('User: ' . $user);
line('Password: ' . mask($password));
line('VHost: ' . $vhost);
line('Queue: ' . $queue);
line('');

$ip = gethostbyname($host);
if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
    fail('DNS', 'Nao foi possivel resolver o host.');
    $failed = true;
} else {
    ok('DNS', $ip);
}

$start = microtime(true);
$errno = 0;
$errstr = '';
$socket = @fsockopen($host, $port, $errno, $errstr, 10);
if (!$socket) {
    fail('TCP', trim($errstr) !== '' ? "{$errstr} ({$errno})" : "Conexao recusada ou timeout ({$errno})");
    $failed = true;
} else {
    $elapsed = round((microtime(true) - $start) * 1000);
    ok('TCP', "Conectou em {$elapsed}ms");
    fclose($socket);
}

try {
    $connection = new AMQPStreamConnection(
        $host,
        $port,
        $user,
        $password,
        $vhost,
        false,
        'AMQPLAIN',
        null,
        'en_US',
        10,
        10
    );
    ok('AMQP login', 'Conectado e autenticado');

    $channel = $connection->channel();
    ok('AMQP channel', 'Canal criado');

    $channel->queue_declare($queue, false, true, false, false);
    ok('Queue declare', "Fila principal declarada: {$queue}");

    $channel->queue_declare($diagnosticQueue, false, false, false, true);
    ok('Diagnostic queue', "Fila diagnostica criada: {$diagnosticQueue}");

    $body = json_encode([
        'diagnostic' => true,
        'host' => gethostname(),
        'time' => date('c'),
    ], JSON_UNESCAPED_SLASHES);

    $channel->basic_publish(new AMQPMessage((string) $body), '', $diagnosticQueue);
    ok('Diagnostic publish', 'Mensagem de teste publicada');

    $message = $channel->basic_get($diagnosticQueue);
    if ($message === null) {
        fail('Diagnostic consume', 'Mensagem de teste nao foi consumida.');
        $failed = true;
    } else {
        $message->ack();
        ok('Diagnostic consume', 'Mensagem de teste consumida e confirmada');
    }

    $channel->queue_delete($diagnosticQueue);
    ok('Cleanup', 'Fila diagnostica removida');

    $channel->close();
    $connection->close();
} catch (Throwable $e) {
    fail('AMQP', get_class($e) . ': ' . $e->getMessage());
    $failed = true;
}

line('');
if ($failed) {
    line('RESULTADO: FALHOU');
    exit(1);
}

line('RESULTADO: OK');
exit(0);

function loadEnv(string $path): array
{
    $values = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines ?: [] as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $trimmed, 2);
        $values[trim($key)] = trim(trim($value), "\"'");
    }

    return $values;
}

function mask(string $value): string
{
    if ($value === '') {
        return '(vazio)';
    }

    return substr($value, 0, 2) . str_repeat('*', max(4, strlen($value) - 4)) . substr($value, -2);
}

function ok(string $step, string $message): void
{
    line("[OK] {$step}: {$message}");
}

function fail(string $step, string $message): void
{
    line("[FAIL] {$step}: {$message}");
}

function line(string $message): void
{
    echo $message . PHP_EOL;
}
