<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Config\Security;
use App\Services\SecurityLogService;

/**
 * Middleware de Honeypot
 *
 * Detecta bots que tentam acessar endpoints "armadilha" que
 * não existem no sistema real. Bane IPs que caem na armadilha.
 */
class HoneypotMiddleware
{
    /**
     * Executa o middleware
     *
     * Este middleware deve ser executado ANTES do roteamento normal
     * para interceptar acessos aos endpoints honeypot.
     */
    public function handle(Request $request): bool
    {
        if (!Security::HONEYPOT['enabled']) {
            return true;
        }

        $endpoint = $request->url();

        // Verifica se é um endpoint honeypot
        if (Security::isHoneypotEndpoint($endpoint)) {
            $this->handleHoneypotAccess($request, $endpoint);
            return false;
        }

        return true;
    }

    /**
     * Trata acesso a endpoint honeypot
     */
    private function handleHoneypotAccess(Request $request, string $endpoint): void
    {
        $ipAddress = $request->ip();
        $userId = Auth::id();
        $chave = Auth::chave();

        // Loga o evento
        SecurityLogService::logHoneypot($ipAddress, $endpoint, $userId, $chave);

        // Bane o IP
        $banDuration = Security::HONEYPOT['ban_duration'];
        BlockedIpMiddleware::blockIp(
            $ipAddress,
            "Acesso a endpoint honeypot: {$endpoint}",
            $banDuration
        );

        // Loga o bloqueio
        SecurityLogService::logBlock(
            $ipAddress,
            $endpoint,
            'Honeypot access',
            $banDuration,
            $userId,
            $chave
        );

        // Responde com erro genérico (não revela que é honeypot)
        // Simula uma resposta 404 normal
        if ($request->isAjax() || str_starts_with($endpoint, '/api/')) {
            Response::json([
                'success' => false,
                'message' => 'Recurso não encontrado.',
            ], 404);
        } else {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head>';
            echo '<body><h1>Not Found</h1>';
            echo '<p>The requested URL was not found on this server.</p></body></html>';
            exit;
        }
    }

    /**
     * Registra um novo endpoint honeypot dinamicamente
     *
     * Útil para adicionar endpoints baseados em tentativas de ataque
     */
    public static function addHoneypotEndpoint(string $endpoint): void
    {
        // Em uma implementação mais robusta, isso seria salvo no banco
        // Por ora, os endpoints são definidos na configuração
    }

    /**
     * Verifica se um endpoint é honeypot (método estático)
     */
    public static function isHoneypot(string $endpoint): bool
    {
        return Security::isHoneypotEndpoint($endpoint);
    }

    /**
     * Lista todos os endpoints honeypot configurados
     */
    public static function getHoneypotEndpoints(): array
    {
        return Security::HONEYPOT['trap_endpoints'];
    }
}
