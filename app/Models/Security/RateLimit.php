<?php

namespace App\Models\Security;

use App\Models\Model;

/**
 * Model para gerenciamento de Rate Limiting
 *
 * Tabela: security_rate_limits (sem coluna chave)
 */
class RateLimit extends Model
{
    /**
     * Incrementa contador e retorna valor atual (operação atômica)
     *
     * @param string $identifier Identificador único
     * @param string $ipAddress IP da requisição
     * @param int|null $userId ID do usuário (se autenticado)
     * @param string $endpoint Endpoint acessado
     * @param int $window Janela de tempo em segundos
     * @return int Número atual de hits
     */
    public function incrementarEObter(
        string $identifier,
        string $ipAddress,
        ?int $userId,
        string $endpoint,
        int $window
    ): int {
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + $window);

        // Usa INSERT ... ON DUPLICATE KEY UPDATE para operação atômica
        $sql = "INSERT INTO security_rate_limits
                (identifier, ip_address, user_id, endpoint, hits, window_start, expires_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    hits = IF(expires_at < NOW(), 1, hits + 1),
                    window_start = IF(expires_at < NOW(), NOW(), window_start),
                    expires_at = IF(expires_at < NOW(), ?, expires_at),
                    updated_at = NOW()";

        $stmt = $this->getMysqli()->prepare($sql);
        $stmt->bind_param(
            'ssissssss',
            $identifier,
            $ipAddress,
            $userId,
            $endpoint,
            $now,
            $expiresAt,
            $now,
            $now,
            $expiresAt
        );
        $stmt->execute();
        $stmt->close();

        // Obtém valor atual
        $result = $this->buscarPorIdentifier($identifier);
        return (int) ($result['hits'] ?? 1);
    }

    /**
     * Busca registro por identificador
     *
     * @param string $identifier Identificador único
     * @return array|null Dados ou null
     */
    public function buscarPorIdentifier(string $identifier): ?array
    {
        return $this->qb
            ->table('security_rate_limits')
            ->withoutChave()
            ->where('identifier', '=', $identifier)
            ->first();
    }

    /**
     * Busca expiração por identificador
     *
     * @param string $identifier Identificador único
     * @return array|null Dados com expires_at ou null
     */
    public function buscarExpiracao(string $identifier): ?array
    {
        return $this->qb
            ->table('security_rate_limits')
            ->withoutChave()
            ->select(['expires_at'])
            ->where('identifier', '=', $identifier)
            ->first();
    }

    /**
     * Remove registros expirados
     *
     * @return int Número de registros removidos
     */
    public function limparExpirados(): int
    {
        return $this->qb
            ->table('security_rate_limits')
            ->withoutChave()
            ->whereRaw('expires_at < NOW()')
            ->delete();
    }

    /**
     * Reseta rate limit para IP/usuário/endpoint
     *
     * @param string|null $ipAddress IP (opcional)
     * @param int|null $userId ID do usuário (opcional)
     * @param string|null $endpoint Endpoint (opcional)
     * @return int Número de registros removidos
     */
    public function resetar(?string $ipAddress = null, ?int $userId = null, ?string $endpoint = null): int
    {
        $query = $this->qb
            ->table('security_rate_limits')
            ->withoutChave();

        if ($ipAddress !== null) {
            $query->where('ip_address', '=', $ipAddress);
        }

        if ($userId !== null) {
            $query->where('user_id', '=', $userId);
        }

        if ($endpoint !== null) {
            $query->where('endpoint', '=', $endpoint);
        }

        // Só deleta se algum filtro foi aplicado
        if ($ipAddress !== null || $userId !== null || $endpoint !== null) {
            return $query->delete();
        }

        return 0;
    }

    /**
     * Obtém estatísticas de rate limiting
     *
     * @return array Estatísticas
     */
    public function obterEstatisticas(): array
    {
        $sql = "SELECT
                    COUNT(*) as total_records,
                    COUNT(DISTINCT ip_address) as unique_ips,
                    COUNT(DISTINCT user_id) as unique_users,
                    SUM(hits) as total_hits,
                    AVG(hits) as avg_hits,
                    MAX(hits) as max_hits
                FROM security_rate_limits
                WHERE expires_at > NOW()";

        $result = $this->getMysqli()->query($sql);
        $row = $result->fetch_assoc();
        $result->free();

        return $row ?: [];
    }
}
