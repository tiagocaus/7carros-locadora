<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Helpers\PlanoLimiteHelper;
use App\Views\Template;

/**
 * Controller para gerenciar funcionalidades relacionadas a planos
 */
class PlanoController
{
    /**
     * Renderiza a página de limite atingido
     */
    public function viewLimiteAtingido(): void
    {
        if (!Auth::check()) {
            Response::redirect('/login');
            return;
        }

        $recurso = $_GET['recurso'] ?? '';
        $label = $_GET['label'] ?? 'registros';
        $limite = (int)($_GET['limite'] ?? 0);
        $plano = $_GET['plano'] ?? 'Atual';

        $html = Template::render('pages.limite-atingido', [
            'recurso' => $recurso,
            'label' => $label,
            'limite' => $limite,
            'plano' => $plano
        ]);

        Response::html($html);
    }

    /**
     * API para verificar limite de um recurso
     *
     * GET /api/plano/verificar-limite?recurso=veiculos
     */
    public function verificarLimite(): void
    {
        if (!Auth::check()) {
            Response::json(['error' => 'Não autenticado'], 401);
            return;
        }

        $recurso = $_GET['recurso'] ?? '';

        if (empty($recurso)) {
            Response::json(['error' => 'Parâmetro "recurso" é obrigatório'], 400);
            return;
        }

        try {
            $response = PlanoLimiteHelper::getApiResponse($recurso);
            Response::json($response);
        } catch (\InvalidArgumentException $e) {
            Response::json(['error' => $e->getMessage()], 400);
        }
    }
}
