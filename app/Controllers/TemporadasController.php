<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Temporada;
use App\Models\TemporadaGrupo;
use App\Models\Grupo;
use App\Services\AuditLogService;

/**
 * Controller de Temporadas
 *
 * Gerencia operacoes CRUD de temporadas (alta/baixa) para ajuste de precos
 */
class TemporadasController
{
    /**
     * Renderiza a pagina de temporadas
     *
     * GET /pages/temporadas
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.temporadas.index');
        Response::html($html);
    }

    /**
     * Lista todas as temporadas do tenant (com paginacao e busca)
     *
     * GET /api/temporadas
     * Query params: page, perPage, search, pais, ativo
     */
    public function index(Request $request): void
    {
        try {
            $chave = Auth::chave();

            // Parametros de paginacao
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');

            $temporadaModel = new Temporada();

            // Buscar temporadas paginadas
            $temporadas = $temporadaModel->listarPaginado($chave, $page, $perPage, $search);

            // Contar total de registros
            $total = $temporadaModel->contar($chave, $search);

            // Calcular total de paginas
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            // Adicionar periodo formatado
            foreach ($temporadas as &$t) {
                $t['periodo'] = $temporadaModel->formatarPeriodo($t);
            }
            unset($t);

            Response::json([
                'success' => true,
                'data' => $temporadas,
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
                'message' => 'Erro ao buscar temporadas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista templates do sistema (chave='0')
     *
     * GET /api/temporadas/templates
     * Query params: pais
     */
    public function templates(Request $request): void
    {
        try {
            $pais = $request->query('pais');

            $temporadaModel = new Temporada();
            $templates = $temporadaModel->listarTemplates($pais);

            // Adicionar periodo formatado
            foreach ($templates as &$t) {
                $t['periodo'] = $temporadaModel->formatarPeriodo($t);
            }
            unset($t);

            Response::json([
                'success' => true,
                'data' => $templates
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar templates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe uma temporada especifica
     *
     * GET /api/temporadas/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            $temporadaModel = new Temporada();
            $temporada = $temporadaModel->buscarPorId($id);

            if (!$temporada) {
                Response::json([
                    'success' => false,
                    'message' => 'Temporada nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($temporada['chave'] !== $chave && $temporada['chave'] !== '0') {
                Response::json([
                    'success' => false,
                    'message' => 'Temporada nao encontrada'
                ], 404);
                return;
            }

            $temporada['periodo'] = $temporadaModel->formatarPeriodo($temporada);

            Response::json([
                'success' => true,
                'data' => $temporada
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar temporada: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria uma nova temporada
     *
     * POST /temporadas/salvar
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

            $temporadaModel = new Temporada();
            $id = $temporadaModel->criar($dados);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", adicionou temporada [{$dados['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Temporada criada com sucesso',
                'data' => ['id' => $id]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar temporada: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza uma temporada
     *
     * POST /temporadas/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            $temporadaModel = new Temporada();
            $temporada = $temporadaModel->buscarPorId($id);

            if (!$temporada) {
                Response::json([
                    'success' => false,
                    'message' => 'Temporada nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($temporada['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode editar esta temporada'
                ], 403);
                return;
            }

            $dados = $request->all();
            $temporadaModel->atualizar($id, $dados);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou temporada [{$temporada['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Temporada atualizada com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar temporada: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui uma temporada
     *
     * POST /temporadas/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            $temporadaModel = new Temporada();
            $temporada = $temporadaModel->buscarPorId($id);

            if (!$temporada) {
                Response::json([
                    'success' => false,
                    'message' => 'Temporada nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($temporada['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode excluir esta temporada'
                ], 403);
                return;
            }

            $temporadaModel->excluir($id);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu temporada [{$temporada['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Temporada excluida com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir temporada: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ativa um template do sistema para o tenant
     *
     * POST /temporadas/ativar-template
     */
    public function ativarTemplate(Request $request): void
    {
        try {
            $templateId = (int) $request->input('template_id');

            if (!$templateId) {
                Response::json([
                    'success' => false,
                    'message' => 'Template ID e obrigatorio'
                ], 400);
                return;
            }

            $temporadaModel = new Temporada();
            $chave = Auth::chave();

            $id = $temporadaModel->ativarTemplate($templateId, $chave);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", ativou template de temporada [ID: {$id}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Template ativado com sucesso',
                'data' => ['id' => $id]
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao ativar template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista ajustes por grupo de uma temporada
     *
     * GET /api/temporadas/{id}/ajustes
     */
    public function ajustes(Request $request, int $id): void
    {
        try {
            $temporadaModel = new Temporada();
            $temporada = $temporadaModel->buscarPorId($id);

            if (!$temporada) {
                Response::json([
                    'success' => false,
                    'message' => 'Temporada nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($temporada['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Temporada nao encontrada'
                ], 404);
                return;
            }

            $temporadaGrupoModel = new TemporadaGrupo();
            $ajustes = $temporadaGrupoModel->listarPorTemporada($id);

            // Buscar todos os grupos do tenant para mostrar na interface
            $grupos = (new Grupo())->listarParaSelect();

            // Mapear ajustes por grupo
            $ajustesPorGrupo = [];
            foreach ($ajustes as $a) {
                $ajustesPorGrupo[$a['id_grupo']] = $a['ajuste_percentual'];
            }

            // Montar lista completa
            $resultado = [];
            foreach ($grupos as $g) {
                $resultado[] = [
                    'id_grupo' => $g['id'],
                    'grupo_nome' => $g['nome'],
                    'ajuste_percentual' => $ajustesPorGrupo[$g['id']] ?? null
                ];
            }

            Response::json([
                'success' => true,
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar ajustes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Salva ajustes por grupo em lote
     *
     * POST /temporadas/{id}/ajustes
     */
    public function salvarAjustes(Request $request, int $id): void
    {
        try {
            $temporadaModel = new Temporada();
            $temporada = $temporadaModel->buscarPorId($id);

            if (!$temporada) {
                Response::json([
                    'success' => false,
                    'message' => 'Temporada nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($temporada['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode editar esta temporada'
                ], 403);
                return;
            }

            $ajustes = $request->input('ajustes', []);

            $temporadaGrupoModel = new TemporadaGrupo();
            $count = $temporadaGrupoModel->salvarEmLote($id, $chave, $ajustes);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou ajustes da temporada [{$temporada['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Ajustes salvos com sucesso',
                'data' => ['count' => $count]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao salvar ajustes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista grupos disponiveis para ajuste
     *
     * GET /api/temporadas/grupos
     */
    public function grupos(Request $request): void
    {
        try {
            $grupos = (new Grupo())->listarParaSelect();

            Response::json([
                'success' => true,
                'data' => $grupos
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar grupos: ' . $e->getMessage()
            ], 500);
        }
    }
}
