<?php

namespace App\Middleware;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/**
 * Autenticacao das rotas publicas consumidas pelo n8n.
 */
class N8nAuthMiddleware
{
    public function handle(Request $request): bool
    {
        $secret = trim((string) Database::env('N8N_API_TOKEN', ''));

        if ($secret === '') {
            error_log('[N8N] N8N_API_TOKEN não configurado');
            Response::json([
                'success' => false,
                'message' => 'Service unavailable',
            ], 503);
            return false;
        }

        $token = trim((string) $request->header('X-N8N-Token', ''));
        if ($token === '' || !hash_equals($secret, $token)) {
            error_log('[N8N] Autenticação falhou - IP: ' . $request->ip());
            Response::json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
            return false;
        }

        return true;
    }
}
