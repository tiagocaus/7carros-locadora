<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Oficina;
use App\Services\AuditLogService;

/**
 * Controller de Oficinas
 *
 * Gerencia operacoes CRUD de oficinas para manutencoes.
 */
class OficinasController
{
    /**
     * Renderiza a pagina de oficinas
     *
     * GET /pages/oficinas
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.oficinas.index');
        Response::html($html);
    }

    /**
     * Renderiza a pagina de adicionar/editar oficina
     *
     * GET /pages/oficinas/adicionar
     */
    public function viewAdicionar(Request $request): void
    {
        $html = Template::render('pages.oficinas.adicionar');
        Response::html($html);
    }

    /**
     * Lista todas as oficinas do tenant (com paginacao e busca)
     *
     * GET /api/oficinas
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

            $model = new Oficina();

            // Buscar oficinas paginadas
            $oficinas = $model->listarPaginado($chave, $page, $perPage, $search);

            // Contar total de registros
            $total = $model->contar($chave, $search);

            // Calcular total de paginas
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $oficinas,
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
                'message' => 'Erro ao buscar oficinas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe uma oficina especifica
     *
     * GET /api/oficinas/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            $model = new Oficina();
            $oficina = $model->buscarPorId($id);

            if (!$oficina) {
                Response::json([
                    'success' => false,
                    'message' => 'Oficina nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($oficina['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Oficina nao encontrada'
                ], 404);
                return;
            }

            Response::json([
                'success' => true,
                'data' => $oficina
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar oficina: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria uma nova oficina
     *
     * POST /oficinas/salvar
     */
    public function store(Request $request): void
    {
        try {
            $dados = $request->all();
            $dados['chave'] = Auth::chave();

            // Validacao basica
            if (empty($dados['empresa'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Nome da empresa e obrigatorio'
                ], 400);
                return;
            }

            $model = new Oficina();
            $id = $model->criar($dados);

            // Log de auditoria
            AuditLogService::registrarComAuditFrontend(
                'Oficina',
                'criar',
                $id,
                $dados['empresa'],
                $dados
            );

            Response::json([
                'success' => true,
                'message' => 'Oficina criada com sucesso',
                'data' => ['id' => $id]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar oficina: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza uma oficina
     *
     * POST /oficinas/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            $model = new Oficina();
            $oficina = $model->buscarPorId($id);

            if (!$oficina) {
                Response::json([
                    'success' => false,
                    'message' => 'Oficina nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($oficina['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode editar esta oficina'
                ], 403);
                return;
            }

            $dados = $request->all();
            $model->atualizar($id, $dados);

            // Log de auditoria
            AuditLogService::registrarComAuditFrontend(
                'Oficina',
                'editar',
                $id,
                $oficina['empresa'],
                $dados
            );

            Response::json([
                'success' => true,
                'message' => 'Oficina atualizada com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar oficina: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui uma oficina
     *
     * POST /oficinas/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            $model = new Oficina();
            $oficina = $model->buscarPorId($id);

            if (!$oficina) {
                Response::json([
                    'success' => false,
                    'message' => 'Oficina nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($oficina['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode excluir esta oficina'
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
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu oficina [{$oficina['empresa']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Oficina excluida com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir oficina: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca oficinas para select (usado em manutencoes)
     *
     * GET /api/oficinas/buscar
     * Query params: q
     */
    public function buscar(Request $request): void
    {
        try {
            $chave = Auth::chave();
            $search = $request->query('q', '');

            $model = new Oficina();
            $oficinas = $model->listarParaSelect($chave, $search);

            $data = array_map(function ($o) {
                return ['id' => $o['id'], 'text' => $o['empresa']];
            }, $oficinas);

            Response::json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar oficinas: ' . $e->getMessage()
            ], 500);
        }
    }
}
