<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Services\NotificationService;

/**
 * Controller da tela "Todas as notificacoes".
 *
 * Renderiza a view e expoe a API de listagem paginada (filtravel por categoria).
 */
class NotificacoesController
{
    /**
     * Renderiza a tela /pages/notificacoes.
     */
    public function view(Request $request): void
    {
        $categoria = (string) ($request->query('categoria', 'all'));
        $html = Template::render('pages.notificacoes.index', [
            'categoria' => $categoria,
        ]);
        Response::html($html);
    }

    /**
     * GET /api/notifications/list?categoria=X&page=Y&perPage=Z
     */
    public function list(Request $request): void
    {
        try {
            $categoria = (string) ($request->query('categoria', 'all'));
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 25)));

            $service = new NotificationService();
            $data = $service->getList($categoria, $page, $perPage);

            Response::json([
                'success' => true,
                'data' => $data['items'],
                'pagination' => [
                    'page' => $data['page'],
                    'perPage' => $data['perPage'],
                    'total' => $data['total'],
                    'totalPages' => $data['total'] > 0 ? (int) ceil($data['total'] / $data['perPage']) : 1,
                ],
                'categoria' => $data['categoria'],
            ]);
        } catch (\Throwable $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao carregar notificacoes: ' . $e->getMessage(),
            ], 500);
        }
    }
}
