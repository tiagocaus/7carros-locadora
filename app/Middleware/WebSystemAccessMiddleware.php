<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Impede que funcionarios sem acesso ao dashboard entrem no sistema web.
 *
 * Este middleware nao participa da autenticacao do aplicativo de vistoria.
 */
class WebSystemAccessMiddleware
{
    public function handle(Request $request): bool
    {
        if (Auth::canAccessWebSystem()) {
            return true;
        }

        Auth::logout();

        if ($request->expectsJson()) {
            Response::json([
                'success' => false,
                'message' => Auth::WEB_SYSTEM_ACCESS_DENIED,
                'redirect' => '/login',
            ], 403);
        }

        Response::redirectWithError('/login', Auth::WEB_SYSTEM_ACCESS_DENIED);
    }
}
