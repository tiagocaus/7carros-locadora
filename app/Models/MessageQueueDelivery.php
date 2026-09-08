<?php

namespace App\Models;

use App\Helpers\DateHelper;

/** Estado de entrega. Leituras globais sao exclusivas do CRON. */
class MessageQueueDelivery extends Model
{
    public const UNCERTAIN = 'ENVIO_INCERTO: conferir antes de reenviar';

    public function findMessage(int $id, string $chave): ?array
    {
        return $this->qb->table('messages_queue')->withChave($chave)
            ->where('id', '=', $id)->first();
    }

    public function claim(int $id, string $chave, int $maxAttempts): bool
    {
        $stmt = $this->getMysqli()->prepare("UPDATE messages_queue
            SET status = 'PROCESSING', attempts = attempts + 1, updated_at = ?
            WHERE id = ? AND chave = ? AND status = 'PENDING'
                AND processed_at IS NULL AND attempts < ?");
        $now = DateHelper::systemNow();
        $stmt->bind_param('sisi', $now, $id, $chave, $maxAttempts);
        try {
            $stmt->execute();
            return $stmt->affected_rows === 1;
        } finally {
            $stmt->close();
        }
    }

    public function finish(int $id, string $chave, string $status, ?string $error = null): bool
    {
        $data = ['status' => $status, 'error_message' => $error, 'updated_at' => DateHelper::systemNow()];
        if (in_array($status, ['SENT', 'SKIPPED'], true)) {
            $data['processed_at'] = $data['updated_at'];
        }
        return $this->qb->table('messages_queue')->withChave($chave)
            ->where('id', '=', $id)->where('status', '=', 'PROCESSING')
            ->whereNull('processed_at')->update($data) === 1;
    }

    public function pending(int $limit, int $maxAttempts, ?string $before = null): array
    {
        $query = $this->qb->table('messages_queue')->withoutChave()
            ->where('status', '=', 'PENDING')->whereNull('processed_at')
            ->where('attempts', '<', $maxAttempts);
        if ($before !== null) {
            $query->whereRaw('COALESCE(updated_at, created_at) < ?', [$before]);
        }
        return $query->orderBy('id', 'ASC')->limit($limit)->get();
    }

    public function publicationFailures(int $maxAttempts, string $since): array
    {
        return $this->qb->table('messages_queue')->withoutChave()
            ->where('status', '=', 'FAILED')->whereNull('processed_at')
            ->where('error_message', 'LIKE', 'Erro ao publicar na fila:%')
            ->where('attempts', '<', $maxAttempts)->where('created_at', '>=', $since)
            ->orderBy('id', 'ASC')->limit(20)->get();
    }

    /** Antes de publicar, nunca depois: nao pode reativar um envio concorrente. */
    public function preparePublication(array $message, int $maxAttempts): bool
    {
        $query = $this->qb->table('messages_queue')->withChave((string) $message['chave'])
            ->where('id', '=', (int) $message['id'])->whereNull('processed_at')
            ->where('status', '=', strtoupper($message['status']))
            ->where('attempts', '<', $maxAttempts)
            ->whereRaw('updated_at <=> ?', [$message['updated_at']]);
        if (strtoupper($message['status']) === 'FAILED') {
            $query->where('error_message', 'LIKE', 'Erro ao publicar na fila:%');
        } elseif (strtoupper($message['status']) !== 'PENDING') {
            return false;
        }
        return $query->update([
            'status' => 'PENDING',
            'updated_at' => DateHelper::systemNow(),
        ]) === 1;
    }

    /** Processamento interrompido nao prova que o provedor deixou de enviar. */
    public function expireProcessing(string $before): array
    {
        $rows = $this->qb->table('messages_queue')->withoutChave()
            ->where('status', '=', 'PROCESSING')->whereNull('processed_at')
            ->whereRaw('COALESCE(updated_at, created_at) < ?', [$before])->limit(100)->get();
        $expired = [];
        foreach ($rows as $row) {
            $changed = $this->qb->table('messages_queue')->withChave($row['chave'])
                ->where('id', '=', (int) $row['id'])->where('status', '=', 'PROCESSING')
                ->whereNull('processed_at')->whereRaw('updated_at <=> ?', [$row['updated_at']])
                ->update(['status' => 'FAILED', 'error_message' => self::UNCERTAIN,
                    'updated_at' => DateHelper::systemNow()]);
            if ($changed === 1) {
                $expired[] = (int) $row['id'];
            }
        }
        return $expired;
    }

    public function failExhausted(int $maxAttempts): void
    {
        $this->qb->table('messages_queue')->withoutChave()
            ->where('status', '=', 'PENDING')->where('attempts', '>=', $maxAttempts)
            ->whereNull('processed_at')->update([
                'status' => 'FAILED', 'updated_at' => DateHelper::systemNow(),
                'error_message' => 'Limite de tentativas atingido',
            ]);
    }
}
