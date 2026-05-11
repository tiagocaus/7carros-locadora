<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

/**
 * Middleware de autenticação para webhooks do WHMCS
 *
 * Valida o TENANT_ONBOARD_SECRET via campo POST 'accesshash'
 * enviado pelo WHMCS Server Module.
 */
class WhmcsAuthMiddleware
{
    public function handle(Request $request): bool
    {
        $secret = Database::env('TENANT_ONBOARD_SECRET', '');

        if (empty($secret)) {
            error_log('[WHMCS] TENANT_ONBOARD_SECRET não configurado');
            Response::json(['success' => false, 'message' => 'Service unavailable'], 503);
            return false;
        }

        $token = $request->input('accesshash', '');

        if (empty($token) || !hash_equals($secret, $token)) {
            error_log('[WHMCS] Autenticação falhou - IP: ' . $request->ip());
            Response::json(['success' => false, 'message' => 'Unauthorized'], 401);
            return false;
        }

        return true;
    }
}
