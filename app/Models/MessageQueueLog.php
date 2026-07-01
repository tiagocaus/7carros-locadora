<?php

namespace App\Models;

/**
 * Consulta registros de envio da fila de mensageria.
 */
class MessageQueueLog extends Model
{
    /**
     * Lista envios com paginacao, busca e filtros.
     */
    public function listarPaginado(
        int $page = 1,
        int $perPage = 10,
        ?string $search = null,
        ?string $type = null,
        ?string $status = null
    ): array {
        $query = $this->baseQuery();
        $this->aplicarFiltros($query, $search, $type, $status);

        return $query
            ->orderByDesc('mq.created_at')
            ->orderByDesc('mq.id')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta envios com os mesmos filtros da listagem.
     */
    public function contar(?string $search = null, ?string $type = null, ?string $status = null): int
    {
        $query = $this->qb->table('messages_queue', 'mq');
        $this->aplicarFiltros($query, $search, $type, $status);

        return $query->count();
    }

    private function baseQuery(): \App\Classes\QueryBuilder
    {
        return $this->qb
            ->table('messages_queue', 'mq')
            ->select([
                'mq.id',
                'mq.type',
                'mq.status',
                'mq.attempts',
                'mq.error_message',
                'mq.created_at',
                'mq.updated_at',
                'mq.processed_at',
                'mq.batch_id',
                'mq.payload',
                "JSON_UNQUOTE(JSON_EXTRACT(mq.payload, '$.to')) AS destinatario",
                "JSON_UNQUOTE(JSON_EXTRACT(mq.payload, '$.to_name')) AS destinatario_nome",
                "JSON_UNQUOTE(JSON_EXTRACT(mq.payload, '$.subject')) AS assunto",
                "JSON_UNQUOTE(JSON_EXTRACT(mq.payload, '$.message')) AS mensagem_texto",
                "JSON_UNQUOTE(JSON_EXTRACT(mq.payload, '$.caption')) AS legenda",
                "JSON_UNQUOTE(JSON_EXTRACT(mq.payload, '$.id_matriz_filial')) AS id_matriz_filial",
            ]);
    }

    private function aplicarFiltros(
        \App\Classes\QueryBuilder $query,
        ?string $search,
        ?string $type,
        ?string $status
    ): void {
        $allowedTypes = ['email', 'sms', 'whatsapp'];
        if ($type && in_array($type, $allowedTypes, true)) {
            $query->where('mq.type', '=', $type);
        }

        $allowedStatuses = ['pending', 'processing', 'sent', 'failed', 'skipped'];
        if ($status && in_array($status, $allowedStatuses, true)) {
            $query->where('mq.status', '=', $status);
        }

        $search = trim((string) $search);
        if ($search === '') {
            return;
        }

        $searchTerm = "%{$search}%";
        $query->whereNested(function ($q) use ($searchTerm) {
            $q->where('mq.type', 'LIKE', $searchTerm)
                ->orWhere('mq.status', 'LIKE', $searchTerm)
                ->orWhere('mq.error_message', 'LIKE', $searchTerm)
                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(mq.payload, '$.to')) LIKE ?", [$searchTerm])
                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(mq.payload, '$.to_name')) LIKE ?", [$searchTerm])
                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(mq.payload, '$.subject')) LIKE ?", [$searchTerm])
                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(mq.payload, '$.message')) LIKE ?", [$searchTerm])
                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(mq.payload, '$.caption')) LIKE ?", [$searchTerm]);
        });
    }
}
