<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\VeiculoAcessorio;
use App\Services\AuditLogService;

/**
 * Controller de Acessorios de Veiculos
 *
 * Gerencia operacoes CRUD de acessorios de veiculos.
 */
class VeiculosAcessoriosController
{
    /**
     * Lista todos os acessorios do tenant (com paginacao e busca)
     *
     * GET /api/veiculos-acessorios
     * Query params: page, perPage, search
     */
    public function index(Request $request): void
    {
        try {
            $chave = Auth::chave();

            // Parametros de paginacao
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');

            $model = new VeiculoAcessorio();

            // Buscar acessorios paginados
            $acessorios = $model->listarPaginado($chave, $page, $perPage, $search);

            // Contar total de registros
            $total = $model->contar($chave, $search);

            // Calcular total de paginas
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $acessorios,
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
                'message' => 'Erro ao buscar acessorios: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista todos os acessorios (sem paginacao, para selects)
     *
     * GET /api/veiculos-acessorios/todos
     */
    public function todos(Request $request): void
    {
        try {
            $chave = Auth::chave();
            $model = new VeiculoAcessorio();
            $acessorios = $model->listar($chave);

            Response::json([
                'success' => true,
                'data' => $acessorios
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar acessorios: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe um acessorio especifico
     *
     * GET /api/veiculos-acessorios/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            $model = new VeiculoAcessorio();
            $acessorio = $model->buscarPorId($id);

            if (!$acessorio) {
                Response::json([
                    'success' => false,
                    'message' => 'Acessorio nao encontrado'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($acessorio['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Acessorio nao encontrado'
                ], 404);
                return;
            }

            Response::json([
                'success' => true,
                'data' => $acessorio
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar acessorio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria um novo acessorio
     *
     * POST /veiculos-acessorios/salvar
     */
    public function store(Request $request): void
    {
        try {
            // Verificar permissao
            if (!Auth::can('veiculos_acessorios.criar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para criar acessorios'
                ], 403);
                return;
            }

            $dados = $request->all();
            $dados['chave'] = Auth::chave();

            // Validacao basica
            if (empty($dados['nome'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Nome e obrigatorio'
                ], 400);
                return;
            }

            $model = new VeiculoAcessorio();
            $id = $model->criar($dados);

            // Log de auditoria
            AuditLogService::registrarComAuditFrontend(
                ($_SESSION['user_name'] ?? 'Sistema') . ", adicionou acessório de veículo [{$dados['nome']}]",
                $dados['_audit_data'] ?? null,
                null
            );

            Response::json([
                'success' => true,
                'message' => 'Acessorio criado com sucesso',
                'data' => ['id' => $id]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar acessorio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza um acessorio
     *
     * POST /veiculos-acessorios/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            // Verificar permissao
            if (!Auth::can('veiculos_acessorios.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para editar acessorios'
                ], 403);
                return;
            }

            $model = new VeiculoAcessorio();
            $acessorio = $model->buscarPorId($id);

            if (!$acessorio) {
                Response::json([
                    'success' => false,
                    'message' => 'Acessorio nao encontrado'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($acessorio['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode editar este acessorio'
                ], 403);
                return;
            }

            $dados = $request->all();

            // Validacao basica
            if (isset($dados['nome']) && empty($dados['nome'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Nome e obrigatorio'
                ], 400);
                return;
            }

            $model->atualizar($id, $dados);

            // Log de auditoria
            AuditLogService::registrarComAuditFrontend(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou acessório de veículo [{$acessorio['nome']}]",
                null,
                $dados['_audit_changes'] ?? null
            );

            Response::json([
                'success' => true,
                'message' => 'Acessorio atualizado com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar acessorio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui um acessorio
     *
     * POST /veiculos-acessorios/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            // Verificar permissao
            if (!Auth::can('veiculos_acessorios.excluir')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para excluir acessorios'
                ], 403);
                return;
            }

            $model = new VeiculoAcessorio();
            $acessorio = $model->buscarPorId($id);

            if (!$acessorio) {
                Response::json([
                    'success' => false,
                    'message' => 'Acessorio nao encontrado'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($acessorio['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode excluir este acessorio'
                ], 403);
                return;
            }

            $model->excluir($id);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu acessório de veículo [{$acessorio['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Acessorio excluido com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir acessorio: ' . $e->getMessage()
            ], 500);
        }
    }
}
