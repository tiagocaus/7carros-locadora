<?php

namespace App\Models\Security;

use App\Models\Model;

/**
 * Model para gerenciamento de IPs Bloqueados
 *
 * Tabela: security_blocked_ips (sem coluna chave)
 */
class BlockedIp extends Model
{
    /**
     * Verifica se um IP está bloqueado
     *
     * @param string $ipAddress IP a verificar
     * @return array|null Dados do bloqueio ou null se não bloqueado
     */
    public function verificarBloqueio(string $ipAddress): ?array
    {
        return $this->qb
            ->table('security_blocked_ips')
            ->withoutChave()
            ->select(['reason', 'blocked_until', 'permanent'])
            ->where('ip_address', '=', $ipAddress)
            ->whereNested(function ($q) {
                $q->where('permanent', '=', 1)
                  ->orWhereRaw('blocked_until IS NULL')
                  ->orWhereRaw('blocked_until > NOW()');
            })
            ->first();
    }

    /**
     * Bloqueia um IP
     *
     * @param string $ipAddress IP a bloquear
     * @param string $reason Motivo do bloqueio
     * @param int|null $duracao Duração em segundos (null = permanente)
     * @return void
     */
    public function bloquear(string $ipAddress, string $reason, ?int $duracao = null): void
    {
        $now = date('Y-m-d H:i:s');
        $blockedUntil = $duracao ? date('Y-m-d H:i:s', time() + $duracao) : null;
        $permanent = $duracao === null ? 1 : 0;

        $sql = "INSERT INTO security_blocked_ips
                (ip_address, reason, blocked_until, permanent, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    reason = VALUES(reason),
                    blocked_until = VALUES(blocked_until),
                    permanent = VALUES(permanent),
                    updated_at = VALUES(updated_at)";

        $stmt = $this->getMysqli()->prepare($sql);
        $stmt->bind_param('sssiss', $ipAddress, $reason, $blockedUntil, $permanent, $now, $now);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Desbloqueia um IP
     *
     * @param string $ipAddress IP a desbloquear
     * @return int Número de registros removidos
     */
    public function desbloquear(string $ipAddress): int
    {
        return $this->qb
            ->table('security_blocked_ips')
            ->withoutChave()
            ->where('ip_address', '=', $ipAddress)
            ->delete();
    }

    /**
     * Remove bloqueios expirados
     *
     * @return int Número de registros removidos
     */
    public function limparExpirados(): int
    {
        return $this->qb
            ->table('security_blocked_ips')
            ->withoutChave()
            ->where('permanent', '=', 0)
            ->whereNotNull('blocked_until')
            ->whereRaw('blocked_until < NOW()')
            ->delete();
    }

    /**
     * Lista todos os IPs bloqueados
     *
     * @return array Lista de IPs bloqueados
     */
    public function listar(): array
    {
        return $this->qb
            ->table('security_blocked_ips')
            ->withoutChave()
            ->orderBy('created_at', 'DESC')
            ->get();
    }
}
