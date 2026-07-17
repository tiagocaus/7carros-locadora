<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\N8nNovosClientesService;
use InvalidArgumentException;
use Throwable;

class N8nController
{
    private N8nNovosClientesService $service;

    public function __construct()
    {
        $this->service = new N8nNovosClientesService();
    }

    /**
     * GET /api/n8n/novos-clientes?dias=1,5,10
     */
    public function novosClientes(Request $request): void
    {
        try {
            Response::json($this->service->listar($request->query('dias')));
        } catch (InvalidArgumentException $exception) {
            Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 400);
        } catch (Throwable $exception) {
            error_log('[N8N] Erro ao listar novos clientes: ' . $exception->getMessage());
            Response::json([
                'success' => false,
                'message' => 'Erro interno ao consultar novos clientes.',
            ], 500);
        }
    }
}
