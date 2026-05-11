<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\ComissaoInvestidor;
use App\Models\Fornecedor;
use App\Services\ComissaoInvestidorService;

/**
 * Controller de Comissoes Investidores
 *
 * Gerencia a visualizacao e operacoes de comissoes de investidores.
 */
class ComissoesInvestidoresController
{
    /**
     * Renderiza a pagina de comissoes
     *
     * GET /pages/comissoes-investidores
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.comissoes-investidores.index');
        Response::html($html);
    }

    /**
     * Lista comissoes com paginacao e filtros
     *
     * GET /api/comissoes-investidores
     * Query params: page, perPage, id_fornecedor, status, tipo_origem, data_inicio, data_fim
     */
    public function index(Request $request): void
    {
        try {
            $chave = Auth::chave();

            // Parametros de paginacao
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));

            // Filtros
            $filtros = [
                'id_fornecedor' => $request->query('id_fornecedor'),
                'status' => $request->query('status'),
                'tipo_origem' => $request->query('tipo_origem'),
                'data_inicio' => $request->query('data_inicio'),
                'data_fim' => $request->query('data_fim'),
                'id_veiculo' => $request->query('id_veiculo'),
            ];

            // Remover filtros vazios
            $filtros = array_filter($filtros, fn($v) => $v !== null && $v !== '');

            $model = new ComissaoInvestidor();

            // Buscar comissoes paginadas
            $comissoes = $model->listarPaginado($chave, $page, $perPage, $filtros);

            // Contar total
            $total = $model->contar($chave, $filtros);

            // Total de paginas
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $comissoes,
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
                'message' => 'Erro ao buscar comissoes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe uma comissao especifica
     *
     * GET /api/comissoes-investidores/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            $model = new ComissaoInvestidor();
            $comissao = $model->buscarPorId($id);

            if (!$comissao) {
                Response::json([
                    'success' => false,
                    'message' => 'Comissao nao encontrada'
                ], 404);
                return;
            }

            // Verificar tenant
            $chave = Auth::chave();
            if ($comissao['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Comissao nao encontrada'
                ], 404);
                return;
            }

            Response::json([
                'success' => true,
                'data' => $comissao
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar comissao: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna totais de comissoes por status
     *
     * GET /api/comissoes-investidores/totais
     */
    public function totais(Request $request): void
    {
        try {
            $chave = Auth::chave();

            // Filtros opcionais
            $filtros = [
                'id_fornecedor' => $request->query('id_fornecedor'),
                'data_inicio' => $request->query('data_inicio'),
                'data_fim' => $request->query('data_fim'),
            ];
            $filtros = array_filter($filtros, fn($v) => $v !== null && $v !== '');

            $model = new ComissaoInvestidor();
            $totais = $model->totaisPorStatus($chave, $filtros);

            Response::json([
                'success' => true,
                'data' => $totais
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar totais: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marca uma comissao como paga
     *
     * POST /comissoes-investidores/{id}/pagar
     */
    public function pagar(Request $request, int $id): void
    {
        try {
            $chave = Auth::chave();
            $service = new ComissaoInvestidorService();

            $result = $service->marcarComoPago($id, $chave);

            Response::json($result);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao marcar como pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancela uma comissao
     *
     * POST /comissoes-investidores/{id}/cancelar
     */
    public function cancelar(Request $request, int $id): void
    {
        try {
            $chave = Auth::chave();
            $motivo = $request->input('motivo', '');

            $service = new ComissaoInvestidorService();
            $result = $service->cancelar($id, $chave, $motivo);

            Response::json($result);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao cancelar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resumo por investidor
     *
     * GET /api/comissoes-investidores/resumo
     */
    public function resumo(Request $request): void
    {
        try {
            $chave = Auth::chave();
            $mesReferencia = $request->query('mes');

            $service = new ComissaoInvestidorService();
            $resumo = $service->resumoPorInvestidor($chave, $mesReferencia);

            Response::json([
                'success' => true,
                'data' => $resumo
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar resumo: ' . $e->getMessage()
            ], 500);
        }
    }

}
