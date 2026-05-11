<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\NotificationService;

/**
 * Controller de Notificacoes
 *
 * Endpoint API para contagem de notificacoes (refresh on-demand).
 */
class NotificationController
{
    /**
     * Retorna contadores de notificacoes (dados frescos, sem cache)
     */
    public function counts(Request $request): void
    {
        $service = new NotificationService();
        $data = $service->getCounts(fresh: true);

        Response::json(['success' => true, 'data' => $data]);
    }
}
