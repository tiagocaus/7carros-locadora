<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Config\Security;
use App\Models\Security\BlockedIp;

/**
 * Middleware de Verificação de IPs Bloqueados
 *
 * Verifica se o IP da requisição está na lista de bloqueio
 * e impede o acesso se estiver.
 */
class BlockedIpMiddleware
{
    private BlockedIp $model;

    public function __construct()
    {
        $this->model = new BlockedIp();
    }

    /**
     * Executa o middleware
     */
    public function handle(Request $request): bool
    {
        $ipAddress = $request->ip();

        // Verifica se está na lista de bloqueio permanente (configuração)
        if (in_array($ipAddress, Security::BLOCKED_IP['permanent_blocks'], true)) {
            $this->denyAccess($request, 'IP permanentemente bloqueado');
            return false;
        }

        // Verifica se está bloqueado no banco de dados
        $blocked = $this->isIpBlocked($ipAddress);

        if ($blocked) {
            $this->denyAccess($request, $blocked['reason'], $blocked['blocked_until']);
            return false;
        }

        return true;
    }

    /**
     * Verifica se o IP está bloqueado no banco de dados
     *
     * @param string $ipAddress
     * @return array|null Dados do bloqueio ou null se não bloqueado
     */
    private function isIpBlocked(string $ipAddress): ?array
    {
        $result = $this->model->verificarBloqueio($ipAddress);

        if (!$result) {
            return null;
        }

        // Se o bloqueio expirou, remove o registro
        if (!$result['permanent'] && $result['blocked_until'] && strtotime($result['blocked_until']) <= time()) {
            $this->unblockIp($ipAddress);
            return null;
        }

        return $result;
    }

    /**
     * Bloqueia um IP
     *
     * @param string $ipAddress
     * @param string $reason
     * @param int|null $duration Duração em segundos (null = permanente)
     */
    public static function blockIp(string $ipAddress, string $reason, ?int $duration = null): void
    {
        $model = new BlockedIp();
        $model->bloquear($ipAddress, $reason, $duration);
    }

    /**
     * Desbloqueia um IP
     *
     * @param string $ipAddress
     */
    public static function unblockIp(string $ipAddress): void
    {
        $model = new BlockedIp();
        $model->desbloquear($ipAddress);
    }

    /**
     * Verifica se um IP está bloqueado (método estático)
     *
     * @param string $ipAddress
     * @return bool
     */
    public static function isBlocked(string $ipAddress): bool
    {
        $middleware = new self();
        return $middleware->isIpBlocked($ipAddress) !== null;
    }

    /**
     * Nega acesso à requisição
     */
    private function denyAccess(Request $request, string $reason, ?string $blockedUntil = null): void
    {
        $message = 'Acesso negado. ' . $reason;

        if ($blockedUntil) {
            $remaining = strtotime($blockedUntil) - time();
            if ($remaining > 0) {
                $minutes = ceil($remaining / 60);
                $message .= " Tente novamente em {$minutes} minutos.";
            }
        }

        if ($request->isAjax() || str_starts_with($request->url(), '/api/')) {
            Response::json([
                'success' => false,
                'message' => $message,
                'blocked' => true,
            ], 403);
        } else {
            http_response_code(403);
            echo $message;
            exit;
        }
    }

    /**
     * Limpa bloqueios expirados
     *
     * @return int Número de registros removidos
     */
    public static function cleanup(): int
    {
        $model = new BlockedIp();
        return $model->limparExpirados();
    }
}
