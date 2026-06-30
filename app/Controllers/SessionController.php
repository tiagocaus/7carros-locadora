<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class SessionController
{
    /**
     * Regenera o token CSRF e retorna o novo valor.
     * Rota sem api_csrf para permitir chamada com token expirado.
     */
    public function refresh(Request $request): void
    {
        Session::set('csrf_token', bin2hex(random_bytes(32)));
        Session::set('csrf_token_time', \App\Helpers\DateHelper::timestamp());

        Response::json([
            'success' => true,
            'csrf_token' => Session::get('csrf_token')
        ]);
    }
}
