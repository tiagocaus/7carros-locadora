<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Middleware de Proteção CSRF para API
 *
 * Bloqueia acesso direto via navegador exigindo X-Requested-With em todos os
 * métodos. Valida token CSRF apenas em escritas (POST/PUT/DELETE/PATCH);
 * GET/HEAD/OPTIONS são protegidos por Same-Origin Policy.
 */
class ApiCsrfMiddleware
{
    /**
     * Executa o middleware
     */
    public function handle(Request $request): bool
    {
        // Bloqueia acesso direto via barra de URL / tags simples (img/script/link).
        // api.js sempre envia X-Requested-With; cross-origin com header customizado
        // dispara CORS preflight, que falha sem CORS aberto.
        if (!$request->isAjax()) {
            Response::json([
                'success' => false,
                'message' => 'Requisição não autorizada'
            ], 419);
            return false;
        }

        // CSRF protege escritas. Leituras são protegidas por Same-Origin Policy
        // (atacante cross-origin não consegue ler a resposta).
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        // Aceita token via header (padrão) ou query string (fallback)
        $token = $request->header('X-CSRF-TOKEN') ?? $request->input('ts');
        $sessionToken = Session::get('csrf_token');

        // Valida o token (timing-safe comparison)
        if (!$token || !$sessionToken || !hash_equals($sessionToken, $token)) {
            Response::json([
                'success' => false,
                'message' => 'TS inválido ou ausente' // TS = Token CSRF
            ], 419);
            return false;
        }

        return true;
    }
}
