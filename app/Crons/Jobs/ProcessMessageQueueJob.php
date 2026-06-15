<?php

namespace App\Crons\Jobs;

use App\Services\MessageQueueService;
use App\Services\EmailService;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use App\Classes\QueryBuilder;
use App\Core\Database;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use mysqli;

/**
 * Job para processar mensagens da fila RabbitMQ
 *
 * Consome mensagens da fila e processa usando os services apropriados
 */
class ProcessMessageQueueJob extends BaseJob
{
    protected string $name = 'Process Message Queue';
    protected string $description = 'Processa mensagens da fila RabbitMQ (email, SMS, WhatsApp)';

    private QueryBuilder $qb;
    private int $maxMessages;
    private int $maxAttempts;
    private int $timeout;

    public function __construct()
    {
        $this->maxMessages = (int) Database::env('QUEUE_MAX_MESSAGES_PER_RUN', 50);
        $this->maxAttempts = (int) Database::env('QUEUE_MAX_ATTEMPTS', 3);
        $this->timeout = (int) Database::env('QUEUE_CONSUME_TIMEOUT', 30);
    }

    /**
     * Implementa a lógica do job
     */
    protected function handle(): array
    {
        $this->log("Iniciando processamento da fila de mensagens...");
        $this->log("Limite de mensagens por execução: {$this->maxMessages}");
        $this->log("Tentativas máximas: {$this->maxAttempts}");

        // Cria QueryBuilder
        $mysqli = new mysqli(
            Database::env('DB_HOST'),
            Database::env('DB_USERNAME'),
            Database::env('DB_PASSWORD'),
            Database::env('DB_DATABASE'),
            (int) Database::env('DB_PORT', '3306')
        );
        $mysqli->set_charset('utf8mb4');
        $this->qb = new QueryBuilder($mysqli);
        $this->qb->withoutChave(); // Desabilita filtro de chave para processar todas as mensagens

        $processed = 0;
        $successful = 0;
        $failed = 0;
        $republished = 0;
        $recoveredFailed = 0;

        $recovery = $this->recoverFailedPublications();
        $republished = $recovery['republished'];
        $recoveredFailed = $recovery['recovered_failed'];

        if (!$this->hasRunnableMessages()) {
            $this->log('Nenhuma mensagem pendente/processando no banco; conexão RabbitMQ não será aberta.');

            return [
                'success' => true,
                'message' => 'Nenhuma mensagem pendente',
                'data' => [
                    'processed' => $processed,
                    'successful' => $successful,
                    'failed' => $failed,
                    'republished' => $republished,
                    'recovered_failed' => $recoveredFailed,
                ],
            ];
        }

        try {
            // Conecta ao RabbitMQ
            $this->amqpLog('consumer.connect.start');
            $connection = $this->getConnection();
            $this->amqpLog('consumer.connect.ok');
            $this->amqpLog('consumer.channel.create.start');
            $channel = $connection->channel();
            $this->amqpLog('consumer.channel.create.ok');

            $queueName = Database::env('RABBITMQ_QUEUE_NAME', 'messages_queue');
            
            // Declara a fila
            $this->amqpLog('consumer.queue.declare.start', ['queue' => $queueName]);
            $channel->queue_declare(
                $queueName,
                false, // passive
                true,  // durable
                false, // exclusive
                false  // auto_delete
            );
            $this->amqpLog('consumer.queue.declare.ok', ['queue' => $queueName]);

            $this->log("Conectado à fila: {$queueName}");

            // Consome mensagens
            $callback = function ($msg) use (&$processed, &$successful, &$failed, $channel) {
                $processed++;

                try {
                    $messageData = json_decode($msg->body, true);
                    
                    if (!$messageData) {
                        throw new \RuntimeException("Mensagem inválida: não é JSON válido");
                    }

                    $messageId = $messageData['id'] ?? null;
                    $type = $messageData['type'] ?? null;
                    $payload = $messageData['payload'] ?? [];
                    $chave = $messageData['chave'] ?? null;

                    if (!$messageId || !$type) {
                        throw new \RuntimeException("Mensagem inválida: campos obrigatórios faltando");
                    }

                    $this->log("Processando mensagem #{$messageId} (tipo: {$type})");

                    // Atualiza status para 'processing'
                    $this->updateMessageStatus($messageId, 'processing');

                    // Processa mensagem usando o service apropriado
                    $result = $this->processMessage($type, $payload, $chave);

                    if ($result['success']) {
                        // Sucesso: atualiza status para 'sent'
                        $this->updateMessageStatus($messageId, 'sent', null, true);
                        $successful++;
                        $this->log("Mensagem #{$messageId} processada com sucesso");
                    } else {
                        // Falha: incrementa tentativas
                        $attempts = $this->incrementAttempts($messageId);
                        
                        if ($attempts >= $this->maxAttempts) {
                            // Excedeu tentativas máximas: marca como failed
                            $this->updateMessageStatus($messageId, 'failed', $result['message']);
                            $this->log("Mensagem #{$messageId} falhou após {$attempts} tentativas");
                        } else {
                            // Ainda pode tentar novamente: volta para pending
                            $this->updateMessageStatus($messageId, 'pending', $result['message']);
                            $this->log("Mensagem #{$messageId} será reprocessada (tentativa {$attempts}/{$this->maxAttempts})");
                        }
                        
                        $failed++;
                    }

                    // Confirma processamento da mensagem
                    $msg->ack();

                } catch (\Exception $e) {
                    $this->log("Erro ao processar mensagem: " . $e->getMessage(), 'ERROR');
                    
                    // Incrementa tentativas
                    if (isset($messageId)) {
                        $attempts = $this->incrementAttempts($messageId);
                        
                        if ($attempts >= $this->maxAttempts) {
                            $this->updateMessageStatus($messageId, 'failed', $e->getMessage());
                        } else {
                            $this->updateMessageStatus($messageId, 'pending', $e->getMessage());
                        }
                    }
                    
                    $failed++;
                    $msg->ack(); // Remove da fila mesmo em caso de erro (para não travar)
                }

                // Para após processar o limite de mensagens
                if ($processed >= $this->maxMessages) {
                    $channel->basic_cancel($msg->getConsumerTag());
                }
            };

            // Configura consumo
            $this->amqpLog('consumer.qos.start');
            $channel->basic_qos(null, 1, false); // Processa uma mensagem por vez
            $this->amqpLog('consumer.qos.ok');
            $this->amqpLog('consumer.consume.start', ['queue' => $queueName]);
            $channel->basic_consume(
                $queueName,
                '',
                false,
                false,
                false,
                false,
                $callback
            );
            $this->amqpLog('consumer.consume.ok', ['queue' => $queueName]);

            $this->log("Aguardando mensagens (timeout: {$this->timeout}s)...");

            // Aguarda mensagens com timeout
            while ($channel->is_consuming() && $processed < $this->maxMessages) {
                try {
                    $this->amqpLog('consumer.wait.start', ['processed' => $processed]);
                    $channel->wait(null, false, $this->timeout);
                    $this->amqpLog('consumer.wait.message', ['processed' => $processed]);
                } catch (AMQPTimeoutException $e) {
                    // Timeout: não há mais mensagens
                    $this->amqpLog('consumer.wait.timeout', ['processed' => $processed]);
                    break;
                }
            }

            $this->amqpLog('consumer.channel.close.start');
            $channel->close();
            $this->amqpLog('consumer.channel.close.ok');
            $this->amqpLog('consumer.connection.close.start');
            $connection->close();
            $this->amqpLog('consumer.connection.close.ok');

        } catch (\Exception $e) {
            $this->amqpLog('consumer.error', ['error' => $e->getMessage()]);
            $this->log("Erro ao conectar ao RabbitMQ: " . $e->getMessage(), 'ERROR');

            $fallback = $this->processDatabaseFallbackMessages($this->maxMessages);
            $processed += $fallback['processed'];
            $successful += $fallback['successful'];
            $failed += $fallback['failed'];

            return [
                'success' => $fallback['processed'] > 0 && $fallback['failed'] === 0,
                'message' => $fallback['processed'] > 0
                    ? "RabbitMQ indisponivel; fallback processou {$fallback['processed']} mensagem(ns)"
                    : 'Erro ao processar fila: ' . $e->getMessage(),
                'data' => [
                    'processed' => $processed,
                    'successful' => $successful,
                    'failed' => $failed,
                    'republished' => $republished,
                    'recovered_failed' => $recoveredFailed,
                ],
            ];
        }

        $orphanRecovery = $this->recoverOrphanedPendingMessages();
        $republished += $orphanRecovery['republished'];

        $this->log("Processamento concluído: {$processed} mensagens processadas ({$successful} sucesso, {$failed} falhas, {$republished} re-publicadas)");

        return [
            'success' => true,
            'message' => "Processadas {$processed} mensagens ({$successful} sucesso, {$failed} falhas, {$republished} re-publicadas)",
            'data' => [
                'processed' => $processed,
                'successful' => $successful,
                'failed' => $failed,
                'republished' => $republished,
                'recovered_failed' => $recoveredFailed,
            ],
        ];
    }

    /**
     * Processa uma mensagem usando o service apropriado
     *
     * Define $_SESSION['chave'] para que os Models (Smtp, Whatsapp, Sms)
     * consigam resolver credenciais do tenant via QueryBuilder.
     */
    private function processMessage(string $type, array $payload, ?string $chave = null): array
    {
        if ($chave) {
            $_SESSION['chave'] = $chave;
            $payload['chave'] = $payload['chave'] ?? $chave;
        }

        try {
            return match ($type) {
                'email' => (new EmailService())->send($payload),
                'sms' => (new SmsService())->send($payload),
                'whatsapp' => (new WhatsAppService())->send($payload),
                default => throw new \InvalidArgumentException("Tipo de mensagem desconhecido: {$type}"),
            };
        } finally {
            if ($chave) {
                unset($_SESSION['chave']);
            }
        }
    }

    /**
     * Atualiza status de uma mensagem no banco
     */
    private function updateMessageStatus(int $messageId, string $status, ?string $errorMessage = null, bool $setProcessedAt = false): void
    {
        $data = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($errorMessage !== null) {
            $data['error_message'] = mb_convert_encoding($errorMessage, 'UTF-8', 'UTF-8');
        }

        if ($status === 'sent') {
            $data['error_message'] = null;
        }

        if ($setProcessedAt) {
            $data['processed_at'] = date('Y-m-d H:i:s');
        }

        $this->qb->table('messages_queue')->where('id', '=', $messageId)->update($data);
    }

    /**
     * Incrementa contador de tentativas
     */
    private function incrementAttempts(int $messageId): int
    {
        // Busca tentativas atuais
        $message = $this->qb->table('messages_queue')->select(['attempts'])->where('id', '=', $messageId)->first();
        $attempts = ($message['attempts'] ?? 0) + 1;

        // Atualiza
        $this->qb->table('messages_queue')->where('id', '=', $messageId)->update([
            'attempts' => $attempts,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $attempts;
    }

    /**
     * Verifica se existe algo que justifique abrir conexão com RabbitMQ.
     */
    private function hasRunnableMessages(): bool
    {
        return $this->qb
            ->withoutChave()
            ->table('messages_queue')
            ->whereRaw("(
                status IN ('pending', 'processing')
                OR (
                    status = 'failed'
                    AND error_message LIKE 'Erro ao publicar na fila:%'
                    AND attempts < ?
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                )
            )", [$this->maxAttempts])
            ->count() > 0;
    }

    /**
     * Recupera falhas de publicacao causadas por indisponibilidade temporaria do RabbitMQ.
     */
    private function recoverFailedPublications(): array
    {
        $candidates = $this->loadRecoverableFailedMessages();

        if (empty($candidates)) {
            return [
                'republished' => 0,
                'recovered_failed' => 0,
            ];
        }

        $republished = 0;
        $recoveredFailed = 0;

        try {
            $this->amqpLog('recover.connect.start');
            $connection = $this->getConnection();
            $this->amqpLog('recover.connect.ok');
            $this->amqpLog('recover.channel.create.start');
            $channel = $connection->channel();
            $this->amqpLog('recover.channel.create.ok');
            $queueName = Database::env('RABBITMQ_QUEUE_NAME', 'messages_queue');
            $this->amqpLog('recover.queue.declare.start', ['queue' => $queueName]);
            $channel->queue_declare($queueName, false, true, false, false);
            $this->amqpLog('recover.queue.declare.ok', ['queue' => $queueName]);

            foreach ($candidates as $msg) {
                $payload = json_decode($msg['payload'], true);
                if (!is_array($payload)) {
                    $this->markMessageFailed((int) $msg['id'], 'Payload invalido para re-publicacao');
                    $this->log("Mensagem #{$msg['id']} nao foi re-publicada: payload invalido", 'WARNING');
                    continue;
                }

                $messageData = [
                    'id'       => (int) $msg['id'],
                    'type'     => $msg['type'],
                    'payload'  => $payload,
                    'chave'    => $msg['chave'],
                    'batch_id' => $msg['batch_id'],
                ];

                $amqpMsg = new AMQPMessage(
                    json_encode($messageData, JSON_UNESCAPED_UNICODE),
                    ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
                );
                $this->amqpLog('recover.publish.start', ['message_id' => (int) $msg['id']]);
                $channel->basic_publish($amqpMsg, '', $queueName);
                $this->amqpLog('recover.publish.ok', ['message_id' => (int) $msg['id']]);

                $wasFailed = ($msg['status'] ?? '') === 'failed';
                $this->markMessageRepublished((int) $msg['id'], $wasFailed);

                $this->log("Mensagem #{$msg['id']} re-publicada no RabbitMQ");
                $republished++;
                if ($wasFailed) {
                    $recoveredFailed++;
                }
            }

            $this->amqpLog('recover.channel.close.start');
            $channel->close();
            $this->amqpLog('recover.channel.close.ok');
            $this->amqpLog('recover.connection.close.start');
            $connection->close();
            $this->amqpLog('recover.connection.close.ok');
        } catch (\Exception $e) {
            $this->amqpLog('recover.error', ['error' => $e->getMessage()]);
            $this->log("Erro ao recuperar mensagens no RabbitMQ: " . $e->getMessage(), 'ERROR');
        }

        return [
            'republished' => $republished,
            'recovered_failed' => $recoveredFailed,
        ];
    }

    /**
     * Recupera mensagens pending que ficaram no banco e nao foram consumidas.
     *
     * Executa depois do consumo normal para reduzir risco de duplicar uma
     * mensagem que ainda esteja presente no RabbitMQ.
     */
    private function recoverOrphanedPendingMessages(): array
    {
        $candidates = $this->loadOrphanedPendingMessages();

        if (empty($candidates)) {
            return ['republished' => 0];
        }

        $republished = 0;

        try {
            $this->amqpLog('orphan.connect.start');
            $connection = $this->getConnection();
            $this->amqpLog('orphan.connect.ok');
            $this->amqpLog('orphan.channel.create.start');
            $channel = $connection->channel();
            $this->amqpLog('orphan.channel.create.ok');
            $queueName = Database::env('RABBITMQ_QUEUE_NAME', 'messages_queue');
            $this->amqpLog('orphan.queue.declare.start', ['queue' => $queueName]);
            $channel->queue_declare($queueName, false, true, false, false);
            $this->amqpLog('orphan.queue.declare.ok', ['queue' => $queueName]);

            foreach ($candidates as $msg) {
                $payload = json_decode($msg['payload'], true);
                if (!is_array($payload)) {
                    $this->markMessageFailed((int) $msg['id'], 'Payload invalido para re-publicacao');
                    $this->log("Mensagem #{$msg['id']} nao foi re-publicada: payload invalido", 'WARNING');
                    continue;
                }

                $messageData = [
                    'id'       => (int) $msg['id'],
                    'type'     => $msg['type'],
                    'payload'  => $payload,
                    'chave'    => $msg['chave'],
                    'batch_id' => $msg['batch_id'],
                ];

                $amqpMsg = new AMQPMessage(
                    json_encode($messageData, JSON_UNESCAPED_UNICODE),
                    ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
                );
                $this->amqpLog('orphan.publish.start', ['message_id' => (int) $msg['id']]);
                $channel->basic_publish($amqpMsg, '', $queueName);
                $this->amqpLog('orphan.publish.ok', ['message_id' => (int) $msg['id']]);

                $this->markMessageRepublished((int) $msg['id'], false);
                $this->log("Mensagem pendente #{$msg['id']} re-publicada no RabbitMQ");
                $republished++;
            }

            $this->amqpLog('orphan.channel.close.start');
            $channel->close();
            $this->amqpLog('orphan.channel.close.ok');
            $this->amqpLog('orphan.connection.close.start');
            $connection->close();
            $this->amqpLog('orphan.connection.close.ok');
        } catch (\Exception $e) {
            $this->amqpLog('orphan.error', ['error' => $e->getMessage()]);
            $this->log("Erro ao re-publicar pendentes: " . $e->getMessage(), 'ERROR');
        }

        return ['republished' => $republished];
    }

    private function loadRecoverableFailedMessages(): array
    {
        return $this->qb
            ->withoutChave()
            ->table('messages_queue')
            ->select(['id', 'type', 'status', 'payload', 'chave', 'batch_id'])
            ->where('status', '=', 'failed')
            ->whereRaw("error_message LIKE 'Erro ao publicar na fila:%'")
            ->whereRaw('attempts < ?', [$this->maxAttempts])
            ->whereRaw('created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')
            ->limit(20)
            ->get();
    }

    private function loadOrphanedPendingMessages(): array
    {
        return $this->qb
            ->withoutChave()
            ->table('messages_queue')
            ->select(['id', 'type', 'status', 'payload', 'chave', 'batch_id'])
            ->where('status', '=', 'pending')
            ->whereRaw('updated_at < DATE_SUB(NOW(), INTERVAL 2 MINUTE)')
            ->limit(20)
            ->get();
    }

    /**
     * Fallback quando o RabbitMQ esta indisponivel.
     */
    private function processDatabaseFallbackMessages(int $limit): array
    {
        $messages = $this->loadDatabaseFallbackMessages($limit);

        if (empty($messages)) {
            $this->log('Fallback pelo banco: nenhuma mensagem elegivel para processamento.');
            return [
                'processed' => 0,
                'successful' => 0,
                'failed' => 0,
            ];
        }

        $processed = 0;
        $successful = 0;
        $failed = 0;

        foreach ($messages as $message) {
            $messageId = (int) $message['id'];
            $type = (string) $message['type'];
            $payload = json_decode((string) $message['payload'], true);
            $chave = $message['chave'] ?? null;

            if (!is_array($payload)) {
                $this->markMessageFailed($messageId, 'Payload invalido para fallback pelo banco');
                $failed++;
                continue;
            }

            $processed++;
            $this->log("Fallback pelo banco: processando mensagem #{$messageId} ({$type})");
            $this->updateMessageStatus($messageId, 'processing');

            try {
                $result = $this->processMessage($type, $payload, $chave);

                if ($result['success']) {
                    $this->updateMessageStatus($messageId, 'sent', null, true);
                    $successful++;
                    $this->log("Fallback pelo banco: mensagem #{$messageId} enviada com sucesso");
                    continue;
                }

                $attempts = $this->incrementAttempts($messageId);
                if ($attempts >= $this->maxAttempts) {
                    $this->updateMessageStatus($messageId, 'failed', $result['message'] ?? 'Falha no envio');
                } else {
                    $this->updateMessageStatus($messageId, 'pending', $result['message'] ?? 'Falha no envio');
                }
                $failed++;
            } catch (\Exception $ex) {
                $attempts = $this->incrementAttempts($messageId);
                if ($attempts >= $this->maxAttempts) {
                    $this->updateMessageStatus($messageId, 'failed', $ex->getMessage());
                } else {
                    $this->updateMessageStatus($messageId, 'pending', $ex->getMessage());
                }
                $failed++;
                $this->log("Fallback pelo banco: erro na mensagem #{$messageId}: {$ex->getMessage()}", 'ERROR');
            }
        }

        return [
            'processed' => $processed,
            'successful' => $successful,
            'failed' => $failed,
        ];
    }

    private function loadDatabaseFallbackMessages(int $limit): array
    {
        return $this->qb
            ->withoutChave()
            ->table('messages_queue')
            ->select(['id', 'type', 'status', 'payload', 'chave', 'batch_id'])
            ->whereRaw("(
                status = 'pending'
                OR (
                    status = 'processing'
                    AND updated_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                )
                OR (
                    status = 'failed'
                    AND error_message LIKE 'Erro ao publicar na fila:%'
                    AND attempts < ?
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                )
            )", [$this->maxAttempts])
            ->orderBy('id', 'ASC')
            ->limit($limit)
            ->get();
    }

    private function markMessageRepublished(int $messageId, bool $wasFailed): void
    {
        $this->qb
            ->withoutChave()
            ->table('messages_queue')
            ->where('id', '=', $messageId)
            ->update([
                'status' => 'pending',
                'attempts' => $wasFailed ? 1 : 0,
                'error_message' => $wasFailed
                    ? 'Re-publicada apos falha de conexao com RabbitMQ'
                    : 'Re-publicada (mensagem pendente no banco)',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    private function markMessageFailed(int $messageId, string $errorMessage): void
    {
        $this->qb
            ->withoutChave()
            ->table('messages_queue')
            ->where('id', '=', $messageId)
            ->update([
                'status' => 'failed',
                'error_message' => $errorMessage,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Obtém conexão com RabbitMQ
     */
    private function getConnection(): AMQPStreamConnection
    {
        $host = Database::env('RABBITMQ_HOST', 'localhost');
        $port = (int) Database::env('RABBITMQ_PORT', '5672');
        $user = Database::env('RABBITMQ_USER', 'guest');
        $password = Database::env('RABBITMQ_PASSWORD', 'guest');
        $vhost = Database::env('RABBITMQ_VHOST', '/');

        return new AMQPStreamConnection($host, $port, $user, $password, $vhost);
    }

    /**
     * Log diagnostico opcional do ciclo AMQP.
     */
    private function amqpLog(string $event, array $context = []): void
    {
        if (Database::env('RABBITMQ_DEBUG', 'false') !== 'true') {
            return;
        }

        error_log('[RabbitMQ] ' . json_encode(array_merge([
            'event' => $event,
            'pid' => getmypid(),
            'host' => Database::env('RABBITMQ_HOST', 'localhost'),
            'port' => (int) Database::env('RABBITMQ_PORT', '5672'),
            'vhost' => Database::env('RABBITMQ_VHOST', '/'),
            'queue' => Database::env('RABBITMQ_QUEUE_NAME', 'messages_queue'),
            'time' => date('c'),
        ], $context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
