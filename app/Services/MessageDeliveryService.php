<?php

namespace App\Services;

use App\Models\MessageQueueDelivery;

/** Unico caminho de entrega para RabbitMQ e fallback pelo banco. */
class MessageDeliveryService
{
    public function __construct(private MessageQueueDelivery $messages, private int $maxAttempts)
    {
    }

    public function deliver(int $id, string $chave, callable $send): array
    {
        if (!$this->messages->claim($id, $chave, $this->maxAttempts)) {
            return ['outcome' => 'ignored', 'attempt' => null];
        }

        // Se o banco falhar daqui em diante, permanece PROCESSING: nunca reenvia cegamente.
        $message = $this->messages->findMessage($id, $chave);
        if ($message === null) {
            throw new \RuntimeException('Mensagem reservada nao encontrada');
        }
        $payload = json_decode($message['payload'], true);
        if (!is_array($payload)) {
            $result = ['success' => false, 'retryable' => false, 'uncertain' => false];
        } else {
            try {
                $result = $send($message['type'], $payload, $chave);
            } catch (\Throwable $e) {
                // Excecao desconhecida pode acontecer depois de o provedor aceitar o envio.
                $result = ['success' => false, 'uncertain' => true];
            }
        }

        $error = null;
        if (!empty($result['skipped'])) {
            $status = 'SKIPPED';
            $error = 'Envio nao autorizado pelas preferencias do canal ou destinatario';
        } elseif (!empty($result['success'])) {
            $status = 'SENT';
        } elseif (($result['uncertain'] ?? !isset($result['retryable'])) === true) {
            $status = 'FAILED';
            $error = MessageQueueDelivery::UNCERTAIN;
        } else {
            $retry = !empty($result['retryable']) && (int) $message['attempts'] < $this->maxAttempts;
            $status = $retry ? 'PENDING' : 'FAILED';
            $error = $retry ? 'Falha confirmada antes da entrega; nova tentativa pendente'
                : 'Falha confirmada; envio encerrado ou limite de tentativas atingido';
        }
        $saved = $this->messages->finish($id, $chave, $status, $error);
        return [
            'outcome' => !$saved ? 'uncertain' : ($error === MessageQueueDelivery::UNCERTAIN ? 'uncertain' : strtolower($status)),
            'attempt' => (int) $message['attempts'],
        ];
    }
}
