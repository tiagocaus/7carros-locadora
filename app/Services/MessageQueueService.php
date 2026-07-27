<?php

namespace App\Services;

use App\Classes\QueryBuilder;
use App\Core\Database;
use App\Models\Sms;
use App\Models\Whatsapp;
use App\Models\Model;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Exception\AMQPConnectionException;

/**
 * Service para gerenciar fila de mensagens usando RabbitMQ
 *
 * Publica mensagens na fila RabbitMQ e mantém rastreamento no banco de dados
 */
class MessageQueueService
{
    private ?AMQPStreamConnection $connection = null;
    private ?\PhpAmqpLib\Channel\AMQPChannel $channel = null;
    private QueryBuilder $qb;
    private string $queueName;
    private string $host;
    private int $port;
    private string $user;
    private string $password;
    private string $vhost;
    private NotificationChannelPolicyService $channelPolicy;

    public function __construct(
        ?QueryBuilder $qb = null,
        ?NotificationChannelPolicyService $channelPolicy = null
    )
    {
        $this->qb = $qb ?? $this->createQueryBuilder();
        $this->channelPolicy = $channelPolicy ?? new NotificationChannelPolicyService();
        
        // Carrega configurações do RabbitMQ
        $this->host = Database::env('RABBITMQ_HOST', 'localhost');
        $this->port = (int) Database::env('RABBITMQ_PORT', '5672');
        $this->user = Database::env('RABBITMQ_USER', 'guest');
        $this->password = Database::env('RABBITMQ_PASSWORD', 'guest');
        $this->vhost = Database::env('RABBITMQ_VHOST', '/');
        $this->queueName = Database::env('RABBITMQ_QUEUE_NAME', 'messages_queue');
    }

    /**
     * Cria instância do QueryBuilder
     */
    private function createQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder(Model::sharedMysqli());
    }

    /**
     * Obtém conexão com RabbitMQ (lazy loading)
     */
    private function getConnection(): AMQPStreamConnection
    {
        if ($this->connection === null || !$this->connection->isConnected()) {
            try {
                $this->amqpLog('connect.start');
                $this->connection = new AMQPStreamConnection(
                    $this->host,
                    $this->port,
                    $this->user,
                    $this->password,
                    $this->vhost
                );
                $this->amqpLog('connect.ok');
            } catch (AMQPConnectionException $e) {
                $this->amqpLog('connect.error', ['error' => $e->getMessage()]);
                error_log("Erro ao conectar ao RabbitMQ: " . $e->getMessage());
                throw new \RuntimeException("Não foi possível conectar ao RabbitMQ: " . $e->getMessage(), 0, $e);
            }
        }

        return $this->connection;
    }

    /**
     * Obtém canal do RabbitMQ
     */
    private function getChannel(): \PhpAmqpLib\Channel\AMQPChannel
    {
        if ($this->channel === null || !$this->channel->is_open()) {
            $connection = $this->getConnection();
            $this->amqpLog('channel.create.start');
            $this->channel = $connection->channel();
            $this->amqpLog('channel.create.ok');
            
            // Declara a fila (cria se não existir)
            $this->amqpLog('queue.declare.start');
            $this->channel->queue_declare(
                $this->queueName,
                false, // passive
                true,  // durable (sobrevive a reinicializações)
                false, // exclusive
                false  // auto_delete
            );
            $this->amqpLog('queue.declare.ok');
        }

        return $this->channel;
    }

    /**
     * Publica uma mensagem na fila
     *
     * @param string $type Tipo de mensagem: 'email', 'sms', 'whatsapp'
     * @param array $payload Dados da mensagem (será serializado como JSON)
     * @param string|null $chave Chave do tenant (opcional, usa $_SESSION se não fornecido)
     * @param string|null $batchId Identificador de batch/lote (opcional, para rastreamento)
     * @return int ID da mensagem salva no banco de dados
     * @throws \InvalidArgumentException Se tipo inválido
     * @throws \RuntimeException Se falhar ao publicar
     */
    public function publish(string $type, array $payload, ?string $chave = null, ?string $batchId = null): int
    {
        // Valida tipo
        $allowedTypes = ['email', 'sms', 'whatsapp'];
        if (!in_array($type, $allowedTypes, true)) {
            throw new \InvalidArgumentException("Tipo de mensagem inválido. Deve ser um de: " . implode(', ', $allowedTypes));
        }

        // Obtém chave do tenant
        if ($chave === null) {
            $chave = $_SESSION['chave'] ?? null;
        }

        $this->validateForPublication($type, $payload, true, $chave);

        // Verifica se deve bloquear notificação em desenvolvimento
        if ($this->shouldBlockNotification($chave)) {
            // Salva registro com status 'skipped' para auditoria (não publica no RabbitMQ)
            $messageId = $this->qb->table('messages_queue')->insert([
                'chave' => $chave,
                'batch_id' => $batchId,
                'type' => $type,
                'status' => 'skipped',
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'attempts' => 0,
                'error_message' => 'Bloqueado em desenvolvimento (tenant não autorizado)',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $messageId;
        }

        // Salva registro no banco de dados com status 'pending'
        $messageId = $this->qb->table('messages_queue')->insert([
            'chave' => $chave,
            'batch_id' => $batchId,
            'type' => $type,
            'status' => 'pending',
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'attempts' => 0,
            'error_message' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Prepara mensagem para RabbitMQ
        $messageData = [
            'id' => $messageId,
            'type' => $type,
            'payload' => $payload,
            'chave' => $chave,
            'batch_id' => $batchId,
        ];

        try {
            // Publica na fila RabbitMQ
            $channel = $this->getChannel();
            $message = new AMQPMessage(
                json_encode($messageData, JSON_UNESCAPED_UNICODE),
                [
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT, // Persiste mensagem
                ]
            );

            $this->amqpLog('publish.start', ['message_id' => $messageId, 'type' => $type]);
            $channel->basic_publish($message, '', $this->queueName);
            $this->amqpLog('publish.ok', ['message_id' => $messageId, 'type' => $type]);

            return $messageId;

        } catch (\Exception $e) {
            $this->amqpLog('publish.error', [
                'message_id' => $messageId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            // Se falhar ao publicar no RabbitMQ, atualiza status para failed
            $query = $this->qb->table('messages_queue');
            if ($chave !== null) {
                $query->withChave($chave);
            }
            $query
                ->where('id', '=', $messageId)
                ->update([
                    'status' => 'failed',
                    'error_message' => 'Erro ao publicar na fila: ' . $e->getMessage(),
                    'updated_at' => now(),
                ]);

            error_log("Erro ao publicar mensagem na fila RabbitMQ: " . $e->getMessage());
            throw new \RuntimeException("Erro ao publicar mensagem na fila: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Fecha conexões com RabbitMQ
     */
    public function close(): void
    {
        if ($this->channel !== null && $this->channel->is_open()) {
            $this->amqpLog('channel.close.start');
            $this->channel->close();
            $this->amqpLog('channel.close.ok');
        }

        if ($this->connection !== null && $this->connection->isConnected()) {
            $this->amqpLog('connection.close.start');
            $this->connection->close();
            $this->amqpLog('connection.close.ok');
        }
    }

    /**
     * Destrutor: fecha conexões automaticamente
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Verifica se deve bloquear notificação em ambiente de desenvolvimento
     *
     * Em desenvolvimento, só permite notificações para o tenant de teste configurado.
     * Isso evita enviar mensagens para clientes reais durante testes.
     *
     * @param string|null $chave Chave do tenant
     * @return bool True se deve bloquear, False se pode enviar
     */
    private function shouldBlockNotification(?string $chave): bool
    {
        $appEnv = Database::env('APP_ENV', 'production');

        // Só bloqueia em desenvolvimento
        if ($appEnv !== 'development') {
            return false;
        }

        // Tenant permitido em desenvolvimento
        $allowedTenant = Database::env('DEV_ALLOWED_NOTIFICATION_TENANT', '');

        // Se não configurou tenant permitido, permite todos
        if (empty($allowedTenant)) {
            return false;
        }

        // Bloqueia se tenant for diferente do permitido
        return $chave !== $allowedTenant;
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
            'host' => $this->host,
            'port' => $this->port,
            'vhost' => $this->vhost,
            'queue' => $this->queueName,
            'time' => \App\Helpers\DateHelper::isoNow(),
        ], $context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Valida dados minimos antes de salvar/publicar mensagem.
     */
    public function validateForPublication(
        string $type,
        array $payload,
        bool $validateContent = true,
        ?string $chave = null
    ): void
    {
        $allowedTypes = ['email', 'sms', 'whatsapp'];
        if (!in_array($type, $allowedTypes, true)) {
            throw new \InvalidArgumentException("Tipo de mensagem inválido. Deve ser um de: " . implode(', ', $allowedTypes));
        }

        $this->validateRecipient($type, $payload);

        if ($validateContent) {
            $this->validateContent($type, $payload);
        }

        $chave = $chave ?? ($_SESSION['chave'] ?? null);
        $this->channelPolicy->assertAllowed($type, $payload, $chave);
        $this->validateChannelAvailability($type, $payload);
    }

    /**
     * Valida destinatario do canal.
     */
    private function validateRecipient(string $type, array $payload): void
    {
        $to = trim((string) ($payload['to'] ?? ''));

        if ($type === 'email') {
            if ($to === '') {
                throw new \InvalidArgumentException('Cliente sem email cadastrado');
            }

            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Email do cliente invalido');
            }

            return;
        }

        if (!in_array($type, ['whatsapp', 'sms'], true)) {
            return;
        }

        if ($to === '') {
            $message = $type === 'whatsapp'
                ? 'Cliente nao tem WhatsApp cadastrado'
                : 'Cliente sem telefone cadastrado';
            throw new \InvalidArgumentException($message);
        }

        if (!$this->isValidPhone($to)) {
            $message = $type === 'whatsapp'
                ? 'Cliente nao tem WhatsApp cadastrado'
                : 'Telefone do cliente invalido';
            throw new \InvalidArgumentException($message);
        }
    }

    /**
     * Valida conteudo minimo para evitar itens inutilizaveis na fila.
     */
    private function validateContent(string $type, array $payload): void
    {
        if ($type === 'email') {
            if (empty($payload['subject'])) {
                throw new \InvalidArgumentException("Campo 'subject' e obrigatorio");
            }

            if (empty($payload['body'])) {
                throw new \InvalidArgumentException("Campo 'body' e obrigatorio");
            }

            return;
        }

        if ($type === 'sms' && empty($payload['message'])) {
            throw new \InvalidArgumentException("Campo 'message' e obrigatorio");
        }

        if ($type === 'whatsapp' && empty($payload['message']) && empty($payload['media_url'])) {
            throw new \InvalidArgumentException("Campo 'message' ou 'media_url' e obrigatorio");
        }
    }

    /**
     * Valida telefone com formato plausivel apos remover mascara.
     */
    private function isValidPhone(string $phone): bool
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        $length = strlen($digits);

        if ($length === 0) {
            return false;
        }

        if (str_starts_with($digits, '55')) {
            return $length === 12 || $length === 13;
        }

        return $length >= 8 && $length <= 15;
    }

    /**
     * Valida se o canal do tenant esta pronto antes de publicar na fila.
     */
    private function validateChannelAvailability(string $type, array $payload): void
    {
        if ($type === 'email') {
            return;
        }

        if ($type === 'whatsapp' && !empty($payload['_system_message'])) {
            return;
        }

        $idMatrizFilial = (int) ($payload['id_matriz_filial'] ?? 0);

        if ($idMatrizFilial <= 0) {
            throw new \InvalidArgumentException("Filial nao informada para envio por {$type}");
        }

        if ($type === 'whatsapp') {
            $whatsapp = (new Whatsapp())->buscarConectadaPorFilial($idMatrizFilial);

            if (!$whatsapp) {
                throw new \InvalidArgumentException('Nenhuma instancia WhatsApp conectada para esta filial');
            }

            return;
        }

        if ($type === 'sms') {
            $sms = (new Sms())->buscarValidadaPorFilial($idMatrizFilial);

            if (!$sms) {
                throw new \InvalidArgumentException('Nenhuma conexao SMS configurada ou validada para esta filial');
            }
        }
    }
}
