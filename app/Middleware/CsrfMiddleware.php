<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Middleware de Proteção CSRF
 *
 * Valida tokens CSRF em requisições POST, PUT, DELETE, PATCH
 */
class CsrfMiddleware
{
    /**
     * Executa o middleware
     */
    public function handle(Request $request): bool
    {
        // Ignora para métodos GET e HEAD
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        // Valida o token CSRF
        if (!$request->validateCsrfToken()) {
            // Se for API/AJAX, retorna JSON
            if ($request->expectsJson()) {
                Response::json([
                    'success' => false,
                    'message' => 'Token CSRF inválido'
                ], 419);
            }

            // Retorna erro
            Response::forbidden('Token CSRF inválido ou expirado');
        }

        // Continua com a requisição
        return true;
    }
}
