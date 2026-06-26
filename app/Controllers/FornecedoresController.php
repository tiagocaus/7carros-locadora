<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Fornecedor;
use App\Models\Pais;
use App\Services\AuditLogService;

/**
 * Controller de Fornecedores
 *
 * Gerencia operacoes CRUD de fornecedores e investidores.
 */
class FornecedoresController
{
    /**
     * Renderiza a pagina de fornecedores
     *
     * GET /pages/fornecedores
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.fornecedores.index');
        Response::html($html);
    }

    /**
     * Renderiza a pagina de adicionar/editar fornecedor
     *
     * GET /pages/fornecedores/adicionar
     * GET /pages/fornecedores/{id}/editar
     */
    public function viewAdicionar(Request $request, ?int $id = null): void
    {
        $paisModel = new Pais();
        $data = [
            'id' => $id,
            'paises' => $paisModel->listarAtivos(),
        ];
        $html = Template::render('pages.fornecedores.adicionar', $data);
        Response::html($html);
    }

    /**
     * Lista todos os fornecedores do tenant (com paginacao e busca)
     *
     * GET /api/fornecedores
     * Query params: page, perPage, search, tipo
     */
    public function index(Request $request): void
    {
        try {
            $chave = Auth::chave();

            // Parametros de paginacao
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');
            $tipo = $request->query('tipo', 'todos'); // todos, fornecedor, investidor

            $model = new Fornecedor();

            // Buscar fornecedores paginados
            $fornecedores = $model->listarPaginado($chave, $page, $perPage, $search, $tipo);

            // Contar total de registros
            $total = $model->contar($chave, $search, $tipo);

            // Calcular total de paginas
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $fornecedores,
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
                'message' => 'Erro ao buscar fornecedores: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe um fornecedor especifico
     *
     * GET /api/fornecedores/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            $model = new Fornecedor();
            $fornecedor = $model->buscarPorId($id);

            if (!$fornecedor) {
                Response::json([
                    'success' => false,
                    'message' => 'Fornecedor nao encontrado'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($fornecedor['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Fornecedor nao encontrado'
                ], 404);
                return;
            }

            Response::json([
                'success' => true,
                'data' => $fornecedor
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar fornecedor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria um novo fornecedor
     *
     * POST /fornecedores/salvar
     */
    public function store(Request $request): void
    {
        try {
            $dados = $request->all();
            $dados['chave'] = Auth::chave();

            // Validacao basica
            if (empty($dados['nome_rsocial'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Nome/Razao Social e obrigatorio'
                ], 400);
                return;
            }

            $model = new Fornecedor();
            $id = $model->criar($dados);

            // Log de auditoria
            AuditLogService::registrarComAuditFrontend(
                'Fornecedor',
                'criar',
                $id,
                $dados['nome_rsocial'],
                $dados
            );

            Response::json([
                'success' => true,
                'message' => 'Fornecedor criado com sucesso',
                'data' => ['id' => $id]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar fornecedor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza um fornecedor
     *
     * POST /fornecedores/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            $model = new Fornecedor();
            $fornecedor = $model->buscarPorId($id);

            if (!$fornecedor) {
                Response::json([
                    'success' => false,
                    'message' => 'Fornecedor nao encontrado'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($fornecedor['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode editar este fornecedor'
                ], 403);
                return;
            }

            $dados = $request->all();
            $model->atualizar($id, $dados);

            // Log de auditoria
            AuditLogService::registrarComAuditFrontend(
                'Fornecedor',
                'editar',
                $id,
                $fornecedor['nome_rsocial'],
                $dados
            );

            Response::json([
                'success' => true,
                'message' => 'Fornecedor atualizado com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar fornecedor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui um fornecedor
     *
     * POST /fornecedores/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            $model = new Fornecedor();
            $fornecedor = $model->buscarPorId($id);

            if (!$fornecedor) {
                Response::json([
                    'success' => false,
                    'message' => 'Fornecedor nao encontrado'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($fornecedor['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode excluir este fornecedor'
                ], 403);
                return;
            }

            // Verificar vinculos
            $vinculos = $model->verificarVinculos($id);
            if (!empty($vinculos)) {
                Response::json([
                    'success' => false,
                    'message' => 'Nao e possivel excluir: ' . implode(', ', $vinculos)
                ], 400);
                return;
            }

            $model->excluir($id);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu fornecedor [{$fornecedor['nome_rsocial']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Fornecedor excluido com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir fornecedor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista investidores para select
     *
     * GET /api/fornecedores/investidores/select
     * Query params: search ou q
     */
    public function investidoresSelect(Request $request): void
    {
        try {
            $chave = Auth::chave();
            $search = $request->query('search', $request->query('q', ''));

            $model = new Fornecedor();
            $investidores = $model->listarInvestidoresSelect($chave, $search);

            Response::json([
                'success' => true,
                'data' => $investidores
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar investidores: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista fornecedores para select (usado no financeiro)
     *
     * GET /api/fornecedores/select
     * Query params: search
     */
    public function fornecedoresSelect(Request $request): void
    {
        try {
            $chave = Auth::chave();
            $search = $request->query('search', $request->query('q', ''));

            $model = new Fornecedor();
            $fornecedores = $model->listarFornecedoresSelect($chave, $search);

            Response::json([
                'success' => true,
                'data' => $fornecedores
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar fornecedores: ' . $e->getMessage()
            ], 500);
        }
    }
}
