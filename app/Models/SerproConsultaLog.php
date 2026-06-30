<?php

namespace App\Models;

/**
 * Model SerproConsultaLog
 *
 * Log tecnico de chamadas a API de consultas online.
 * Registra request/response completos para debug e auditoria.
 */
class SerproConsultaLog extends Model
{
    /**
     * Registra chamada a API SERPRO
     */
    public function registrar(
        string $chave,
        string $tipoOperacao,
        string $endpoint,
        ?string $placa = null,
        ?array $requestHeaders = null,
        ?array $requestPayload = null,
        ?int $responseStatus = null,
        ?array $responsePayload = null,
        string $status = 'sucesso',
        ?string $erroMensagem = null,
        ?int $idSerproTransacao = null,
        ?int $duracaoMs = null
    ): int {
        return $this->qb
            ->table('serpro_consultas_log')
            ->withoutChave()
            ->insert([
                'chave' => $chave,
                'tipo_operacao' => $tipoOperacao,
                'placa' => $placa,
                'endpoint' => $endpoint,
                'request_headers' => $requestHeaders ? json_encode($requestHeaders) : null,
                'request_payload' => $requestPayload ? json_encode($requestPayload) : null,
                'response_status' => $responseStatus,
                'response_payload' => $responsePayload ? json_encode($responsePayload) : null,
                'status' => $status,
                'erro_mensagem' => $erroMensagem,
                'id_serpro_transacao' => $idSerproTransacao,
                'duracao_ms' => $duracaoMs,
                'created_at' => now(),
            ]);
    }

    /**
     * Lista logs paginados do tenant
     */
    public function listarPaginado(
        int $page,
        int $perPage,
        string $filtroTipo = '',
        ?string $filtroPlaca = null,
        ?string $filtroStatus = null
    ): array {
        $query = $this->qb
            ->table('serpro_consultas_log', 'scl')
            ->select([
                'scl.id',
                'scl.tipo_operacao',
                'scl.placa',
                'scl.endpoint',
                'scl.response_status',
                'scl.status',
                'scl.erro_mensagem',
                'scl.duracao_ms',
                'scl.created_at',
            ]);

        if (!empty($filtroTipo)) {
            $query->where('scl.tipo_operacao', '=', $filtroTipo);
        }

        if (!empty($filtroPlaca)) {
            $query->where('scl.placa', '=', $filtroPlaca);
        }

        if (!empty($filtroStatus)) {
            $query->where('scl.status', '=', $filtroStatus);
        }

        return $query
            ->orderByDesc('scl.created_at')
            ->orderByDesc('scl.id')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta total de logs com filtros
     */
    public function contar(
        string $filtroTipo = '',
        ?string $filtroPlaca = null,
        ?string $filtroStatus = null
    ): int {
        $query = $this->qb
            ->table('serpro_consultas_log', 'scl');

        if (!empty($filtroTipo)) {
            $query->where('scl.tipo_operacao', '=', $filtroTipo);
        }

        if (!empty($filtroPlaca)) {
            $query->where('scl.placa', '=', $filtroPlaca);
        }

        if (!empty($filtroStatus)) {
            $query->where('scl.status', '=', $filtroStatus);
        }

        return $query->count();
    }

    /**
     * Busca log por ID com payload completo
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('serpro_consultas_log')
            ->where('id', '=', $id)
            ->first();
    }
}
