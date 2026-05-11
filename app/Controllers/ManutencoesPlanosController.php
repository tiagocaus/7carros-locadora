<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\ManutencaoPlano;
use App\Services\AuditLogService;

/**
 * Controller de Planos de Manutenção
 *
 * Gerencia operações CRUD de planos de manutenção preventiva.
 */
class ManutencoesPlanosController
{
    /**
     * Renderiza a página de planos de manutenção
     *
     * GET /pages/manutencoes-planos
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.manutencoes-planos.index');
        Response::html($html);
    }

    /**
     * Lista todos os planos do tenant (com paginação e busca)
     *
     * GET /api/manutencoes-planos
     * Query params: page, perPage, search
     */
    public function index(Request $request): void
    {
        try {
            $chave = Auth::chave();

            // Parâmetros de paginação
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');

            $planoModel = new ManutencaoPlano();

            // Buscar planos paginados
            $planos = $planoModel->listarPaginado($chave, $page, $perPage, $search);

            // Contar total de registros
            $total = $planoModel->contar($chave, $search);

            // Calcular total de páginas
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $planos,
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
                'message' => t('modules.manutencao_plano.messages.load_error') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe um plano específico
     *
     * GET /api/manutencoes-planos/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            $planoModel = new ManutencaoPlano();
            $plano = $planoModel->buscarPorId($id);

            if (!$plano) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.manutencao_plano.messages.not_found')
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($plano['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.manutencao_plano.messages.not_found')
                ], 404);
                return;
            }

            // Decodificar o JSON de intervalos para o frontend
            $plano['intervalos'] = json_decode($plano['array'], true) ?? [];

            Response::json([
                'success' => true,
                'data' => $plano
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => t('modules.manutencao_plano.messages.load_error') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria um novo plano
     *
     * POST /manutencoes-planos/salvar
     */
    public function store(Request $request): void
    {
        try {
            $dados = $request->all();
            $dados['chave'] = Auth::chave();

            // Validação básica
            if (empty($dados['nome'])) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.manutencao_plano.messages.name_required')
                ], 400);
                return;
            }

            $planoModel = new ManutencaoPlano();
            $id = $planoModel->criar($dados);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", adicionou plano de manutenção [{$dados['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => t('modules.manutencao_plano.messages.created'),
                'data' => ['id' => $id]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => t('modules.manutencao_plano.messages.save_error') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza um plano
     *
     * POST /manutencoes-planos/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            $planoModel = new ManutencaoPlano();
            $plano = $planoModel->buscarPorId($id);

            if (!$plano) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.manutencao_plano.messages.not_found')
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($plano['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.manutencao_plano.messages.not_found')
                ], 403);
                return;
            }

            $dados = $request->all();

            // Validação básica
            if (isset($dados['nome']) && empty($dados['nome'])) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.manutencao_plano.messages.name_required')
                ], 400);
                return;
            }

            $planoModel->atualizar($id, $dados);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou plano de manutenção [{$plano['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => t('modules.manutencao_plano.messages.updated')
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => t('modules.manutencao_plano.messages.save_error') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui um plano
     *
     * POST /manutencoes-planos/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            $planoModel = new ManutencaoPlano();
            $plano = $planoModel->buscarPorId($id);

            if (!$plano) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.manutencao_plano.messages.not_found')
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($plano['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.manutencao_plano.messages.not_found')
                ], 403);
                return;
            }

            $planoModel->excluir($id);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu plano de manutenção [{$plano['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => t('modules.manutencao_plano.messages.deleted')
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => t('modules.manutencao_plano.messages.delete_error') . ': ' . $e->getMessage()
            ], 500);
        }
    }
}
