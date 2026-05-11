<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\CodigoIndicacao;

/**
 * Controller de Programa de Indicacao
 *
 * Gerencia a tela do programa de indicacao.
 */
class ProgramaIndicacaoController
{
    /**
     * Renderiza a pagina do programa de indicacao
     *
     * GET /pages/programa-indicacao
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.programa-indicacao.index');
        Response::html($html);
    }

    /**
     * Retorna ou cria o codigo de indicacao do tenant
     *
     * GET /api/programa-indicacao/codigo
     */
    public function getOrCreateCodigo(Request $request): void
    {
        try {
            $chave = Auth::chave();
            $model = new CodigoIndicacao();

            $dados = $model->buscarOuCriar($chave);

            Response::json([
                'success' => true,
                'data' => [
                    'codigo' => $dados['codigo'],
                ],
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar codigo: ' . $e->getMessage()
            ], 500);
        }
    }
}
