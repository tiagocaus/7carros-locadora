<?php

namespace App\Services;

use App\Config\Security;
use App\Models\Security\SecurityLog;

/**
 * Service para logging de eventos de segurança
 *
 * Registra todos os eventos de segurança no banco de dados para
 * análise posterior e ajuste de regras de proteção.
 */
class SecurityLogService
{
    /**
     * Registra um evento de segurança
     *
     * @param string $eventType Tipo do evento (rate_limit, fingerprint, quota, honeypot, block, suspicious)
     * @param string $ipAddress Endereço IP da requisição
     * @param string $endpoint Endpoint acessado
     * @param array $details Detalhes adicionais do evento
     * @param int $score Score de suspeita (0-100)
     * @param string|null $actionTaken Ação tomada (blocked, throttled, warned, etc.)
     * @param int|null $userId ID do usuário (se autenticado)
     * @param string|null $chave Chave do tenant
     * @return int ID do log criado
     */
    public static function log(
        string $eventType,
        string $ipAddress,
        string $endpoint,
        array $details = [],
        int $score = 0,
        ?string $actionTaken = null,
        ?int $userId = null,
        ?string $chave = null
    ): int {
        if (!Security::LOGGING['enabled']) {
            return 0;
        }

        // Verifica se o tipo de evento deve ser logado
        if (!self::shouldLog($eventType)) {
            return 0;
        }

        $model = new SecurityLog();
        return $model->registrar([
            'event_type' => $eventType,
            'ip_address' => $ipAddress,
            'user_id' => $userId,
            'chave' => $chave,
            'endpoint' => $endpoint,
            'details' => $details,
            'score' => $score,
            'action_taken' => $actionTaken,
        ]);
    }

    /**
     * Registra evento de rate limit excedido
     */
    public static function logRateLimit(
        string $ipAddress,
        string $endpoint,
        int $currentHits,
        int $limit,
        ?int $userId = null,
        ?string $chave = null
    ): int {
        return self::log(
            'rate_limit',
            $ipAddress,
            $endpoint,
            [
                'hits' => $currentHits,
                'limit' => $limit,
                'exceeded_by' => $currentHits - $limit,
            ],
            0,
            'blocked',
            $userId,
            $chave
        );
    }

    /**
     * Registra evento de fingerprint suspeito
     */
    public static function logFingerprint(
        string $ipAddress,
        string $endpoint,
        int $score,
        array $factors,
        string $actionTaken,
        ?int $userId = null,
        ?string $chave = null
    ): int {
        return self::log(
            'fingerprint',
            $ipAddress,
            $endpoint,
            [
                'factors' => $factors,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ],
            $score,
            $actionTaken,
            $userId,
            $chave
        );
    }

    /**
     * Registra evento de quota excedida
     */
    public static function logQuota(
        string $ipAddress,
        string $endpoint,
        int $currentUsage,
        int $limit,
        string $quotaType,
        ?int $userId = null,
        ?string $chave = null
    ): int {
        return self::log(
            'quota',
            $ipAddress,
            $endpoint,
            [
                'current_usage' => $currentUsage,
                'limit' => $limit,
                'quota_type' => $quotaType,
            ],
            0,
            'blocked',
            $userId,
            $chave
        );
    }

    /**
     * Registra evento de acesso a honeypot
     */
    public static function logHoneypot(
        string $ipAddress,
        string $endpoint,
        ?int $userId = null,
        ?string $chave = null
    ): int {
        return self::log(
            'honeypot',
            $ipAddress,
            $endpoint,
            [
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'referer' => $_SERVER['HTTP_REFERER'] ?? '',
                'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            ],
            100, // Score máximo para honeypot
            'banned',
            $userId,
            $chave
        );
    }

    /**
     * Registra evento de IP bloqueado
     */
    public static function logBlock(
        string $ipAddress,
        string $endpoint,
        string $reason,
        int $duration,
        ?int $userId = null,
        ?string $chave = null
    ): int {
        return self::log(
            'block',
            $ipAddress,
            $endpoint,
            [
                'reason' => $reason,
                'duration_seconds' => $duration,
                'blocked_until' => date('Y-m-d H:i:s', time() + $duration),
            ],
            100,
            'blocked',
            $userId,
            $chave
        );
    }

    /**
     * Verifica se um tipo de evento deve ser logado
     */
    private static function shouldLog(string $eventType): bool
    {
        $config = Security::LOGGING['log_events'];
        return $config[$eventType] ?? false;
    }

    /**
     * Limpa logs antigos baseado na configuração de retenção
     *
     * @return int Número de registros excluídos
     */
    public static function cleanup(): int
    {
        $retentionDays = Security::LOGGING['retention_days'];
        $model = new SecurityLog();
        return $model->limparAntigos($retentionDays);
    }

    /**
     * Obtém estatísticas de segurança para um período
     *
     * @param string $startDate Data inicial (Y-m-d)
     * @param string $endDate Data final (Y-m-d)
     * @return array Estatísticas agrupadas por tipo de evento
     */
    public static function getStats(string $startDate, string $endDate): array
    {
        $model = new SecurityLog();
        return $model->obterEstatisticas($startDate, $endDate);
    }

    /**
     * Obtém os IPs mais suspeitos
     *
     * @param int $limit Quantidade de IPs a retornar
     * @param int $days Período em dias para análise
     * @return array Lista de IPs com contagem de eventos
     */
    public static function getTopSuspiciousIps(int $limit = 10, int $days = 7): array
    {
        $model = new SecurityLog();
        return $model->obterIpsSuspeitos($limit, $days);
    }
}
