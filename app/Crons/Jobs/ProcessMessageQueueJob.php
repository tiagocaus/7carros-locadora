<?php

namespace App\Crons\Jobs;

use App\Services\EmailService;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use App\Services\MessageDeliveryService;
use App\Services\NotificationChannelPolicyService;
use App\Models\MessageQueueDelivery;
use App\Models\ContatoEmail;
use App\Models\ContatoTelefone;
use App\Core\Database;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;

class ProcessMessageQueueJob extends BaseJob
{
    protected string $name = 'Process Message Queue';
    protected string $description = 'Processa mensagens da fila RabbitMQ (email, SMS, WhatsApp)';

    private MessageQueueDelivery $messages;
    private MessageDeliveryService $delivery;
    private int $maxMessages;
    private int $maxAttempts;
    private int $timeout;
    private NotificationChannelPolicyService $channelPolicy;

    public function __construct(?NotificationChannelPolicyService $channelPolicy = null)
    {
        $this->maxMessages = max(1, (int) Database::env('QUEUE_MAX_MESSAGES_PER_RUN', 50));
        $this->maxAttempts = max(1, (int) Database::env('QUEUE_MAX_ATTEMPTS', 3));
        $this->timeout = max(1, (int) Database::env('QUEUE_CONSUME_TIMEOUT', 30));
        $this->channelPolicy = $channelPolicy ?? new NotificationChannelPolicyService();
    }

    protected function handle(): array
    {
        $this->messages = $this->createMessageStore();
        $this->delivery = new MessageDeliveryService($this->messages, $this->maxAttempts);
        $stats = ['processed' => 0, 'successful' => 0, 'failed' => 0,
            'ignored' => 0, 'uncertain' => 0, 'republished' => 0, 'recovered_failed' => 0];
        foreach ($this->messages->expireProcessing($this->technicalMinutesAgo(10)) as $id) {
            $stats['uncertain']++;
            $this->log("Mensagem #{$id}: envio incerto; conferir antes de reenviar", 'WARNING');
        }
        $this->messages->failExhausted($this->maxAttempts);
        if ($this->messages->pending(1, $this->maxAttempts) === []
            && $this->messages->publicationFailures($this->maxAttempts, $this->technicalDaysAgo(7)) === []) {
            return $this->result($stats, 'Nenhuma mensagem pendente');
        }

        $connection = null;
        $channel = null;
        $empty = false;
        $consumerFailed = false;
        try {
            $connection = $this->getConnection();
            $channel = $connection->channel();
            $queue = Database::env('RABBITMQ_QUEUE_NAME', 'messages_queue');
            $channel->queue_declare($queue, false, true, false, false);
            $this->republish($channel, $queue,
                $this->messages->publicationFailures($this->maxAttempts, $this->technicalDaysAgo(7)), $stats);
            $channel->basic_qos(null, 1, false);
            $callback = function ($msg) use (&$stats, $channel) {
                $data = json_decode($msg->body, true);
                if (!is_array($data) || !is_numeric($data['id'] ?? null)
                    || !is_string($data['chave'] ?? null) || $data['chave'] === '') {
                    $stats['processed']++;
                    $stats['ignored']++;
                    $this->log('Envelope invalido ignorado (sem ID/empresa)', 'WARNING');
                } else {
                    // Nunca usar tipo/payload do broker: o banco e a fonte de verdade.
                    $this->deliver((int) $data['id'], $data['chave'], 'rabbitmq', $stats);
                }
                // Falha no ACK nao altera o estado persistido. Redelivery sera ignorada.
                $msg->ack();
                if ($stats['processed'] >= $this->maxMessages) {
                    $channel->basic_cancel($msg->getConsumerTag());
                }
            };
            $tag = $channel->basic_consume($queue, '', false, false, false, false, $callback);
            while ($channel->is_consuming() && $stats['processed'] < $this->maxMessages) {
                try {
                    $channel->wait(null, false, $this->timeout);
                } catch (AMQPTimeoutException $e) {
                    // Timeout e apenas indicio. Confirma fila vazia depois de cancelar o consumo.
                    $channel->basic_cancel($tag);
                    $state = $channel->queue_declare($queue, true);
                    $empty = (int) $state[1] === 0 && (int) $state[2] === 0;
                    break;
                }
            }
            if (self::shouldRecoverPending($empty, $stats['processed'], $this->maxMessages)) {
                $this->republish($channel, $queue,
                    $this->messages->pending(20, $this->maxAttempts, $this->technicalMinutesAgo(2)), $stats);
            }
        } catch (\Throwable $e) {
            $consumerFailed = true;
            // Nao incluir mensagens de excecao que podem carregar payloads/credenciais.
            $this->log('Falha no consumo RabbitMQ (' . get_class($e) . '); usando fallback pelo banco', 'ERROR');
        } finally {
            try { if ($channel !== null && $channel->is_open()) { $channel->close(); } } catch (\Throwable $e) {}
            try { if ($connection !== null) { $connection->close(); } } catch (\Throwable $e) {}
        }
        if ($consumerFailed) {
            // Tambem recupera falhas de publicacao quando o broker esta indisponivel.
            foreach ($this->messages->publicationFailures($this->maxAttempts, $this->technicalDaysAgo(7)) as $row) {
                $this->messages->preparePublication($row, $this->maxAttempts);
            }
            $remaining = max(0, $this->maxMessages - $stats['processed']);
            if ($remaining > 0) {
                foreach ($this->messages->pending($remaining, $this->maxAttempts) as $row) {
                    $this->deliver((int) $row['id'], $row['chave'], 'database', $stats);
                }
            }
        }
        return $this->result($stats, $consumerFailed ? 'Processamento com fallback pelo banco' : 'Processamento concluido');
    }

    public static function shouldRecoverPending(bool $empty, int $processed, int $limit): bool
    {
        return $empty && $processed < $limit;
    }

    protected function createMessageStore(): MessageQueueDelivery
    {
        return new MessageQueueDelivery();
    }

    private function republish($channel, string $queue, array $rows, array &$stats): void
    {
        foreach ($rows as $row) {
            if (!$this->messages->preparePublication($row, $this->maxAttempts)) {
                continue;
            }
            // Atualizacao acontece ANTES da publicacao, sem zerar attempts.
            $message = new AMQPMessage(json_encode([
                'id' => (int) $row['id'], 'chave' => $row['chave'],
                'type' => $row['type'], 'payload' => json_decode($row['payload'], true),
                'batch_id' => $row['batch_id'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
            $channel->basic_publish($message, '', $queue);
            $stats['republished']++;
            if (strtoupper($row['status']) === 'FAILED') {
                $stats['recovered_failed']++;
            }
            $this->log("Mensagem #{$row['id']}: republicada sem reiniciar tentativas");
        }
    }

    private function deliver(int $id, string $chave, string $source, array &$stats): void
    {
        $result = $this->delivery->deliver($id, $chave,
            fn(string $type, array $payload, string $tenant) => $this->processMessage($type, $payload, $tenant));
        $stats['processed']++;
        $outcome = $result['outcome'];
        if ($outcome === 'ignored') {
            $stats['ignored']++;
        } elseif (in_array($outcome, ['sent', 'skipped'], true)) {
            $stats['successful']++;
        } else {
            $stats['failed']++;
            if ($outcome === 'uncertain') { $stats['uncertain']++; }
        }
        $attempt = $result['attempt'] ?? '-';
        $this->log("Mensagem #{$id}: origem={$source}; tentativa={$attempt}; resultado={$outcome}");
    }

    private function result(array $stats, string $message): array
    {
        $this->log($message . ': ' . json_encode($stats));
        return ['success' => $stats['failed'] === 0 && $stats['uncertain'] === 0,
            'message' => $message, 'data' => $stats];
    }

    /**
     * Processa uma mensagem usando o service apropriado
     *
     * Define $_SESSION['chave'] para que os Models (Smtp, Whatsapp, Sms)
     * consigam resolver credenciais do tenant via QueryBuilder.
     */
    protected function processMessage(string $type, array $payload, ?string $chave = null): array
    {
        $previousSession = $_SESSION ?? [];
        $providerStarted = false;
        if ($chave !== null) {
            $_SESSION['chave'] = $chave;
            $payload['chave'] = $chave;
        }

        try {
            $channelDecision = $this->channelPolicy->evaluate($type, $payload, $chave, true);
            if (!$channelDecision['allowed']) {
                return [
                    'success' => true,
                    'skipped' => true,
                    'message' => $channelDecision['message'],
                ];
            }

            if (!$this->destinatarioAutorizado($type, $payload, $chave)) {
                return [
                    'success' => true,
                    'skipped' => true,
                    'message' => 'Canal desmarcado nas preferencias do cliente',
                ];
            }

            $providerStarted = true;
            return match ($type) {
                'email' => (new EmailService())->send($payload),
                'sms' => (new SmsService())->send($payload),
                'whatsapp' => (new WhatsAppService())->send($payload),
                default => throw new \InvalidArgumentException("Tipo de mensagem desconhecido: {$type}"),
            };
        } catch (\Throwable $e) {
            return ['success' => false, 'retryable' => !$providerStarted, 'uncertain' => $providerStarted];
        } finally {
            $_SESSION = $previousSession;
        }
    }

    /**
     * Revalida a preferencia imediatamente antes do envio.
     * Payloads legados e mensagens nao destinadas a clientes permanecem compativeis.
     */
    private function destinatarioAutorizado(string $type, array $payload, ?string $chave): bool
    {
        if (($payload['_email_preference_bypass'] ?? '') === 'cliente_password_reset') {
            return true;
        }

        if (($payload['_recipient_entity_type'] ?? '') !== 'cliente') {
            return true;
        }

        $clienteId = (int) ($payload['_recipient_entity_id'] ?? 0);
        $destinatario = trim((string) ($payload['to'] ?? ''));
        if ($clienteId <= 0 || $destinatario === '') {
            return false;
        }

        if ($type === 'email') {
            return (new ContatoEmail())->podeEnviarPara(
                'cliente',
                $clienteId,
                $destinatario,
                $chave
            );
        }

        if (in_array($type, ['whatsapp', 'sms'], true)) {
            return (new ContatoTelefone())->podeEnviarPara(
                'cliente',
                $clienteId,
                $destinatario,
                $type,
                $chave
            );
        }

        return false;
    }

    /**
     * Obtém conexão com RabbitMQ
     */
    protected function getConnection(): AMQPStreamConnection
    {
        $host = Database::env('RABBITMQ_HOST', 'localhost');
        $port = (int) Database::env('RABBITMQ_PORT', '5672');
        $user = Database::env('RABBITMQ_USER', 'guest');
        $password = Database::env('RABBITMQ_PASSWORD', 'guest');
        $vhost = Database::env('RABBITMQ_VHOST', '/');

        return new AMQPStreamConnection($host, $port, $user, $password, $vhost);
    }

    private function technicalDaysAgo(int $days): string
    {
        return \App\Helpers\DateHelper::formatTimestamp(
            \App\Helpers\DateHelper::timestamp() - ($days * 86400),
            'Y-m-d H:i:s',
            false
        );
    }

    private function technicalMinutesAgo(int $minutes): string
    {
        return \App\Helpers\DateHelper::formatTimestamp(
            \App\Helpers\DateHelper::timestamp() - ($minutes * 60),
            'Y-m-d H:i:s',
            false
        );
    }
}
