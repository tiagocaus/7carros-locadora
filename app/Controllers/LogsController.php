<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Log;
use App\Models\MessageQueueLog;
use App\Models\McpAuditLog;

/**
 * Controller de Logs (Auditoria)
 *
 * Gerencia visualização de logs do sistema
 */
class LogsController
{
    /** GET /api/logs/mcp: tenant-scoped MCP tool audit with bounded previews. */
    public function mcp(Request $request): void
    {
        if (!Auth::can('logs.visualizar') || empty($_SESSION['chave'])) {
            Response::json(['success' => false, 'message' => t('modules.logs.mcp.forbidden')], 403);
            return;
        }
        try {
            $page = max(1, min(1000000, (int) $request->query('page', 1)));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $term = $request->query('search', '');
            $search = is_string($term) ? mb_substr(trim($term), 0, 200) : '';
            $matchingTools = [];
            if ($search !== '') {
                foreach (McpAuditLog::tools() as $tool) {
                    if (mb_stripos(t('modules.logs.mcp.operations.' . $tool), $search) !== false) $matchingTools[] = $tool;
                }
            }
            $model = new McpAuditLog();
            $rows = $model->listarPaginado($page, $perPage, $search, $matchingTools);
            foreach ($rows as &$row) {
                $row['operacao'] = t('modules.logs.mcp.operations.' . $row['evento']);
                foreach (['solicitacao_previa', 'retorno_previa', 'pergunta_previa'] as $field) {
                    if ($row[$field] !== null && mb_strlen($row[$field]) > 255) {
                        $row[$field] = mb_substr($row[$field], 0, 254) . '…';
                    }
                }
                if (is_string($row['pergunta_previa']) && trim($row['pergunta_previa']) === '') $row['pergunta_previa'] = null;
                $row['conteudo_disponivel'] = (bool) $row['conteudo_disponivel'];
            }
            unset($row);
            $total = $search === '' ? null : $model->contar($search, $matchingTools);
            $totalPages = $total === null ? null : max(1, (int) ceil($total / $perPage));
            Response::json(['success' => true, 'data' => $rows, 'pagination' => [
                'page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => $totalPages,
                'returned' => count($rows),
                'hasNext' => $totalPages === null ? count($rows) === $perPage : $page < $totalPages,
                'hasPrev' => $page > 1,
            ]]);
        } catch (\Throwable $e) {
            error_log('[MCP-AUDIT-VIEW] exception=' . get_class($e) . ' code=' . (int) $e->getCode());
            Response::json(['success' => false, 'message' => t('modules.logs.messages.load_error')], 500);
        }
    }

    public function mcpDetalhe(Request $request, string $id): void
    {
        if (!Auth::can('logs.visualizar') || empty($_SESSION['chave'])) {
            Response::json(['success' => false, 'message' => t('modules.logs.mcp.forbidden')], 403);
            return;
        }
        try {
            $row = ctype_digit($id) && strlen($id) <= 10 && (int) $id > 0
                ? (new McpAuditLog())->detalhar((int) $id) : null;
            if ($row === null) {
                Response::json(['success' => false, 'message' => t('modules.logs.mcp.not_found')], 404);
                return;
            }
            if (is_string($row['pergunta_usuario']) && trim($row['pergunta_usuario']) === '') $row['pergunta_usuario'] = null;
            $row['operacao'] = t('modules.logs.mcp.operations.' . $row['evento']);
            Response::json(['success' => true, 'data' => $row]);
        } catch (\Throwable $e) {
            error_log('[MCP-AUDIT-DETAIL] exception=' . get_class($e) . ' code=' . (int) $e->getCode());
            Response::json(['success' => false, 'message' => t('modules.logs.messages.load_error')], 500);
        }
    }

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
