<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Log;
use App\Models\MessageQueueLog;

/**
 * Controller de Logs (Auditoria)
 *
 * Gerencia visualização de logs do sistema
 */
class LogsController
{
    /**
     * Lista todos os logs (com paginação e busca)
     *
     * GET /api/logs - Retorna JSON
     * Query params: page, perPage, search
     */
    public function index(Request $request): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('logs.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar logs'
                ], 403);
                return;
            }

            // Obter parâmetros de paginação e busca
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');

            $logModel = new Log();

            // Buscar logs paginados
            $logs = $logModel->listarPaginado($page, $perPage, $search);

            // Lazy loading: só contar se houver busca (performance)
            if (empty($search)) {
                // Sem busca = não contar (evita COUNT em milhares de registros)
                $total = null;
                $totalPages = null;
                $hasNext = count($logs) === $perPage; // Tem próxima se retornou página cheia
                $hasPrev = $page > 1;
            } else {
                // Com busca = contar normalmente (filtra poucos dados)
                $total = $logModel->contar($search);
                $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
                $hasNext = $page < $totalPages;
                $hasPrev = $page > 1;
            }

            // Retornar JSON com dados de paginação
            Response::json([
                'success' => true,
                'data' => $logs,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'totalPages' => $totalPages,
                    'hasNext' => $hasNext,
                    'hasPrev' => $hasPrev
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista registros de envio da fila de mensageria.
     *
     * GET /api/logs/envios - Retorna JSON
     * Query params: page, perPage, search, type, status
     */
    public function envios(Request $request): void
    {
        try {
            if (!Auth::can('logs.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar logs'
                ], 403);
                return;
            }

            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');
            $type = $request->query('type', '');
            $status = $request->query('status', '');

            $model = new MessageQueueLog();
            $envios = $model->listarPaginado($page, $perPage, $search, $type, $status);
            $total = $model->contar($search, $type, $status);
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $envios,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'totalPages' => $totalPages,
                    'hasNext' => $page < $totalPages,
                    'hasPrev' => $page > 1
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar envios: ' . $e->getMessage()
            ], 500);
        }
    }
}
