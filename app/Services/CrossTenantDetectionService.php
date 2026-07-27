<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Database;
use App\Config\Security;
use App\Services\AuditLogService;

/**
 * Service para detecção de tentativas de acesso cross-tenant
 *
 * Detecta e loga quando um usuário tenta acessar IDs de registros
 * que pertencem a outro tenant.
 */
class CrossTenantDetectionService
{
    /**
     * Verifica se um ID existe em outro tenant e loga a tentativa
     *
     * @param string $table Nome da tabela
     * @param int $id ID do registro
     * @param string|null $chaveAtual Chave do tenant atual (opcional, usa sessão)
     * @return CrossTenantCheckResult Resultado da verificação
     */
    public static function check(
        string $table,
        int $id,
        ?string $chaveAtual = null
    ): CrossTenantCheckResult {
        $config = Security::CROSS_TENANT;

        // Se desabilitado ou tabela não monitorada, retorna sem verificar
        if (!$config['enabled'] || !in_array($table, $config['monitored_tables'], true)) {
            return new CrossTenantCheckResult(
                exists: false,
                isCrossTenant: false,
                wasLogged: false,
                attemptCount: 0,
                suspicionScore: 0
            );
        }

        $chaveAtual = $chaveAtual ?? Auth::chave();
        $userId = Auth::id();
        $cacheKey = "cross_tenant:{$table}:{$id}";

        // Tenta obter do cache primeiro (usa tenant null para cache global)
        $cachedResult = Cache::get($cacheKey, null, 'global');

        if ($cachedResult !== null) {
            // Registro existe em cache
            if (!$cachedResult['exists']) {
                // ID não existe em nenhum tenant
                return new CrossTenantCheckResult(
                    exists: false,
                    isCrossTenant: false,
                    wasLogged: false,
                    attemptCount: 0,
                    suspicionScore: 0
                );
            }

            if ($cachedResult['chave'] === $chaveAtual) {
                // Pertence ao tenant atual - não é cross-tenant
                return new CrossTenantCheckResult(
                    exists: true,
                    isCrossTenant: false,
                    wasLogged: false,
                    attemptCount: 0,
                    suspicionScore: 0
                );
            }

            // É cross-tenant - logar e retornar
            return self::handleCrossTenantAttempt(
                $table,
                $id,
                $cachedResult['chave'],
                $userId,
                $chaveAtual
            );
        }

        // Query sem filtro de chave para verificar existência global
        $record = Database::fetchOne(
            "SELECT id, chave FROM `{$table}` WHERE id = ? LIMIT 1",
            [$id]
        );

        // Cachear resultado (cache global, não por tenant)
        $cacheData = [
            'exists' => $record !== null,
            'chave' => $record['chave'] ?? null
        ];
        Cache::set($cacheKey, $cacheData, $config['cache_ttl'], 'global');

        // ID não existe em nenhum tenant
        if (!$record) {
            return new CrossTenantCheckResult(
                exists: false,
                isCrossTenant: false,
                wasLogged: false,
                attemptCount: 0,
                suspicionScore: 0
            );
        }

        // Pertence ao tenant atual
        if ($record['chave'] === $chaveAtual) {
            return new CrossTenantCheckResult(
                exists: true,
                isCrossTenant: false,
                wasLogged: false,
                attemptCount: 0,
                suspicionScore: 0
            );
        }

        // É cross-tenant
        return self::handleCrossTenantAttempt(
            $table,
            $id,
            $record['chave'],
            $userId,
            $chaveAtual
        );
    }

    /**
     * Processa e loga uma tentativa cross-tenant
     */
    private static function handleCrossTenantAttempt(
        string $table,
        int $id,
        string $targetChave,
        ?int $userId,
        ?string $userChave
    ): CrossTenantCheckResult {
        $config = Security::CROSS_TENANT;

        // Contar tentativas recentes do usuário
        $attemptCount = self::incrementAttemptCount($userId);

        // Calcular score de suspeita
        $score = min($attemptCount * $config['score_per_attempt'], $config['max_score']);

        // Determinar ação baseada no score
        $actionTaken = match (true) {
            $score >= 70 => 'flagged',
            $score >= 50 => 'warned',
            default => 'logged'
        };

        // Logar no SecurityLogService
        SecurityLogService::log(
            'cross_tenant_attempt',
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $_SERVER['REQUEST_URI'] ?? '',
            [
                'table' => $table,
                'target_id' => $id,
                'target_chave_hash' => self::hashChave($targetChave),
                'attempt_count' => $attemptCount,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ],
            $score,
            $actionTaken,
            $userId,
            $userChave
        );

        // Registrar na tabela de auditoria (logs)
        AuditLogService::registrar(
            "ALERTA SEGURANÇA: Tentativa de acesso cross-tenant - Tabela: {$table}, ID: {$id}, Tentativas: {$attemptCount}, Score: {$score}"
        );

        // Enviar email de alerta apenas quando usuário for suspeito (5+ tentativas)
        if ($score >= 75) {
            self::enviarAlertaEmail($table, $id, $userId, $attemptCount, $score);
        }

        return new CrossTenantCheckResult(
            exists: true,
            isCrossTenant: true,
            wasLogged: true,
            attemptCount: $attemptCount,
            suspicionScore: $score
        );
    }

    /**
     * Incrementa e retorna contagem de tentativas do usuário
     */
    private static function incrementAttemptCount(?int $userId): int
    {
        if (!$userId) {
            return 1;
        }

        $config = Security::CROSS_TENANT;
        $cacheKey = "cross_tenant_attempts:{$userId}";

        // Tenta incrementar no cache
        $count = Cache::increment($cacheKey, 1, 'global');

        // Se retornou false ou 1, pode ser primeira tentativa
        // Precisamos definir o TTL
        if ($count === false || $count === 1) {
            // Usar get/set para garantir TTL
            $currentCount = Cache::get($cacheKey, 0, 'global');
            $newCount = $currentCount + 1;
            Cache::set($cacheKey, $newCount, $config['attempt_window'], 'global');
            return $newCount;
        }

        return $count;
    }

    /**
     * Verifica se usuário está com comportamento suspeito
     *
     * @param int|null $userId ID do usuário (usa sessão se null)
     * @return bool
     */
    public static function isUserSuspicious(?int $userId = null): bool
    {
        $userId = $userId ?? Auth::id();
        if (!$userId) {
            return false;
        }

        $config = Security::CROSS_TENANT;
        $cacheKey = "cross_tenant_attempts:{$userId}";
        $count = Cache::get($cacheKey, 0, 'global');

        return $count >= $config['attempt_threshold'];
    }

    /**
     * Obtém estatísticas de tentativas do usuário
     *
     * @param int|null $userId ID do usuário
     * @return array
     */
    public static function getUserAttemptStats(?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        if (!$userId) {
            return [
                'attempt_count' => 0,
                'is_suspicious' => false,
                'threshold' => Security::CROSS_TENANT['attempt_threshold'],
            ];
        }

        $config = Security::CROSS_TENANT;
        $cacheKey = "cross_tenant_attempts:{$userId}";
        $count = Cache::get($cacheKey, 0, 'global');

        return [
            'attempt_count' => $count,
            'is_suspicious' => $count >= $config['attempt_threshold'],
            'threshold' => $config['attempt_threshold'],
        ];
    }

    /**
     * Limpa contagem de tentativas do usuário (para testes ou reset manual)
     *
     * @param int $userId ID do usuário
     * @return bool
     */
    public static function clearAttemptCount(int $userId): bool
    {
        $cacheKey = "cross_tenant_attempts:{$userId}";
        return Cache::forget($cacheKey, 'global');
    }

    /**
     * Gera hash parcial da chave para log (não expõe chave completa)
     */
    private static function hashChave(string $chave): string
    {
        $length = strlen($chave);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        // Mostra primeiros 4 caracteres + asteriscos
        return substr($chave, 0, 4) . str_repeat('*', $length - 4);
    }

    /**
     * Envia email de alerta sobre tentativa cross-tenant
     */
    private static function enviarAlertaEmail(
        string $table,
        int $id,
        ?int $userId,
        int $attemptCount,
        int $score
    ): void {
        $email = Database::env('APP_COMPANY_EMAIL');
        if (empty($email)) {
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
        $uri = $_SERVER['REQUEST_URI'] ?? 'desconhecida';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'desconhecido';
        $chave = Auth::chave() ?? 'não autenticado';
        $userName = $_SESSION['user_name'] ?? 'Desconhecido';

        $body = "
        <h2>🚨 Alerta de Segurança - Tentativa Cross-Tenant</h2>

        <p>Uma tentativa de acesso cross-tenant foi detectada:</p>

        <table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse;'>
            <tr><td><strong>Tabela</strong></td><td>{$table}</td></tr>
            <tr><td><strong>ID Acessado</strong></td><td>{$id}</td></tr>
            <tr><td><strong>Usuário</strong></td><td>{$userName} (ID: " . ($userId ?? 'N/A') . ")</td></tr>
            <tr><td><strong>Tenant (chave)</strong></td><td>{$chave}</td></tr>
            <tr><td><strong>IP</strong></td><td>{$ip}</td></tr>
            <tr><td><strong>Tentativas</strong></td><td>{$attemptCount}</td></tr>
            <tr><td><strong>Score de Suspeita</strong></td><td>{$score}/100</td></tr>
            <tr><td><strong>URI</strong></td><td>{$uri}</td></tr>
            <tr><td><strong>User Agent</strong></td><td>{$userAgent}</td></tr>
            <tr><td><strong>Data/Hora</strong></td><td>" . format_datetime(now()) . "</td></tr>
        </table>

        <p><strong>Ação Recomendada:</strong> Verifique os logs de segurança para mais detalhes.</p>
        ";

        try {
            queue_system_message('email', [
                'to' => $email,
                'subject' => "[ALERTA SEGURANCA] Tentativa de acesso cross-tenant detectada",
                'body' => $body,
            ], null, true);
        } catch (\Throwable $e) {
            // Falha silenciosa - nao interromper o fluxo por erro de email
            error_log("Erro ao enviar alerta cross-tenant: " . $e->getMessage());
        }
    }
}
