<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Middleware de Visitante
 *
 * Redireciona usuários autenticados para o dashboard
 */
class GuestMiddleware
{
    /**
     * Executa o middleware
     */
    public function handle(Request $request): bool
    {
        // Se o usuário está autenticado, redireciona para o dashboard
        if (Auth::check()) {
            Response::redirect('/dashboard');
        }

        // Continua com a requisição
        return true;
    }
}
