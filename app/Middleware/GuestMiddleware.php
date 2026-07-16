<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Middleware de Visitante
 *
 * Redireciona usuarios autenticados e autorizados para o dashboard.
 */
class GuestMiddleware
{
    /**
     * Executa o middleware
     */
    public function handle(Request $request): bool
    {
        // Uma sessao sem permissao web nao deve gerar loop login/dashboard.
        if (Auth::check()) {
            if (Auth::canAccessWebSystem()) {
                Response::redirect('/dashboard');
            }

            Auth::logout();
            Response::redirectWithError('/login', Auth::WEB_SYSTEM_ACCESS_DENIED);
        }

        // Continua com a requisição
        return true;
    }
}
