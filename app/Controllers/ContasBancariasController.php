<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\ContaBancaria;
use App\Services\AuditLogService;

/**
 * Controller de Contas Bancarias/Caixa
 *
 * Gerencia operacoes CRUD de contas bancarias e caixas.
 */
class ContasBancariasController
{
    /**
     * Renderiza a pagina de contas bancarias
     *
     * GET /pages/contas-bancarias
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.contas-bancarias.index');
        Response::html($html);
    }

    /**
     * Renderiza a pagina de adicionar/editar conta bancaria
     *
     * GET /pages/contas-bancarias/adicionar
     */
    public function viewAdicionar(Request $request): void
    {
        $html = Template::render('pages.contas-bancarias.adicionar');
        Response::html($html);
    }

    /**
     * Lista todas as contas bancarias do tenant (com paginacao e busca)
     *
     * GET /api/contas-bancarias
     * Query params: page, perPage, search
     */
    public function index(Request $request): void
    {
        try {
            // Parametros de paginacao
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');

            $contaModel = new ContaBancaria();

            // Buscar contas paginadas
            $contas = $contaModel->listarPaginado($page, $perPage, $search);

            // Contar total de registros
            $total = $contaModel->contar($search);

            // Calcular total de paginas
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $contas,
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
                'message' => 'Erro ao buscar contas bancarias: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca contas bancarias para select
     *
     * GET /api/contas-bancarias/buscar
     */
    public function buscar(Request $request): void
    {
        try {
            $search = $request->query('q', '');
            $contaModel = new ContaBancaria();

            // Buscar contas (limitado a 50)
            $contas = $contaModel->listarPaginado(1, 50, $search);

            // Formatar para chosen
            $data = array_map(function ($c) {
                return [
                    'id' => $c['id'],
                    'text' => $c['nome']
                ];
            }, $contas);

            Response::json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Exibe uma conta bancaria especifica
     *
     * GET /api/contas-bancarias/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            $contaModel = new ContaBancaria();
            $conta = $contaModel->buscarPorId($id);

            if (!$conta) {
                Response::json([
                    'success' => false,
                    'message' => 'Conta bancaria nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($conta['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Conta bancaria nao encontrada'
                ], 404);
                return;
            }

            Response::json([
                'success' => true,
                'data' => $conta
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar conta bancaria: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria uma nova conta bancaria
     *
     * POST /contas-bancarias/salvar
     */
    public function store(Request $request): void
    {
        try {
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

            $contaModel = new ContaBancaria();
            $id = $contaModel->criar($dados);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", adicionou conta bancaria [{$dados['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Conta bancaria criada com sucesso',
                'data' => ['id' => $id]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar conta bancaria: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza uma conta bancaria
     *
     * POST /contas-bancarias/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            $contaModel = new ContaBancaria();
            $conta = $contaModel->buscarPorId($id);

            if (!$conta) {
                Response::json([
                    'success' => false,
                    'message' => 'Conta bancaria nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($conta['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode editar esta conta bancaria'
                ], 403);
                return;
            }

            $dados = $request->all();
            $contaModel->atualizar($id, $dados);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou conta bancaria [{$conta['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Conta bancaria atualizada com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar conta bancaria: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui uma conta bancaria
     *
     * POST /contas-bancarias/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            $contaModel = new ContaBancaria();
            $conta = $contaModel->buscarPorId($id);

            if (!$conta) {
                Response::json([
                    'success' => false,
                    'message' => 'Conta bancaria nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($conta['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode excluir esta conta bancaria'
                ], 403);
                return;
            }

            $contaModel->excluir($id);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu conta bancaria [{$conta['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Conta bancaria excluida com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir conta bancaria: ' . $e->getMessage()
            ], 500);
        }
    }
}
