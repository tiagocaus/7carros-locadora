<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\PlanoDeContas;
use App\Services\AuditLogService;

/**
 * Controller de Planos de Contas
 *
 * Gerencia operações CRUD de planos de contas contábeis,
 * com suporte a internacionalização (i18n).
 */
class PlanosDeContasController
{
    /**
     * Renderiza a página de listagem de planos de contas
     *
     * GET /pages/planos-de-contas
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.planos-de-contas.index');
        Response::html($html);
    }

    /**
     * Renderiza a página de adicionar/editar plano de contas
     *
     * GET /pages/planos-de-contas/adicionar
     */
    public function viewAdicionar(Request $request): void
    {
        $html = Template::render('pages.planos-de-contas.adicionar');
        Response::html($html);
    }

    /**
     * Lista todos os planos de contas do tenant (com paginação e busca)
     *
     * GET /api/planos-de-contas
     * Query params: page, perPage, search, tipo
     */
    public function index(Request $request): void
    {
        if (!Auth::can('planos_contas.visualizar')) {
            Response::json([
                'success' => false,
                'message' => t('common.errors.no_permission')
            ], 403);
            return;
        }

        try {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');
            $tipo = $request->query('tipo', '');

            $model = new PlanoDeContas();

            $planos = $model->listarPaginado($page, $perPage, $search, $tipo);
            $total = $model->contar($search, $tipo);

            // Adicionar descrição traduzida e flag de sistema a cada plano
            foreach ($planos as &$plano) {
                $plano['descricao'] = PlanoDeContas::getDescricao($plano);
                $plano['tipo_label'] = PlanoDeContas::getTipoLabel($plano['tipo']);
                $plano['is_system'] = ($plano['chave'] === '0');
                unset($plano['chave']); // Não expor chave na API
            }

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
                'message' => t('modules.planos_contas.messages.error_list') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca planos de contas para autocomplete/select
     *
     * GET /api/planos-de-contas/buscar
     * Query params: termo, tipo
     */
    public function buscar(Request $request): void
    {
        try {
            $termo = $request->query('termo', '');
            $tipo = $request->query('tipo', null);

            if (strlen($termo) < 1) {
                Response::json([
                    'success' => true,
                    'data' => []
                ]);
                return;
            }

            $model = new PlanoDeContas();
            $planos = $model->buscar($termo, $tipo, 20);

            // Formatar para select
            $resultado = [];
            foreach ($planos as $plano) {
                $resultado[] = [
                    'id' => $plano['id'],
                    'hierarquia' => $plano['hierarquia'],
                    'descricao' => PlanoDeContas::getDescricao($plano),
                    'tipo' => $plano['tipo'],
                    'tipo_label' => PlanoDeContas::getTipoLabel($plano['tipo']),
                    'texto' => $plano['hierarquia'] . ' - ' . PlanoDeContas::getDescricao($plano)
                ];
            }

            Response::json([
                'success' => true,
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista planos de contas por tipo (para select de conta pai)
     *
     * GET /api/planos-de-contas/por-tipo?tipo=A&q=busca
     */
    public function listarPorTipo(Request $request): void
    {
        if (!Auth::can('planos_contas.visualizar')) {
            Response::json([
                'success' => false,
                'message' => t('common.errors.no_permission')
            ], 403);
            return;
        }

        try {
            $tipo = $request->query('tipo', '');
            $search = $request->query('q', '');

            if (empty($tipo) || !in_array($tipo, ['A', 'P', 'D', 'R'], true)) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.planos_contas.messages.tipo_invalid')
                ], 400);
                return;
            }

            $model = new PlanoDeContas();
            $planos = $model->listarPorTipo($tipo, $search);

            // Formatar para chosen-select (value/text) com indentação hierárquica
            $resultado = [];
            foreach ($planos as $plano) {
                // Calcular nível pela quantidade de pontos na hierarquia
                $nivel = substr_count($plano['hierarquia'], '.');
                // Adicionar indentação visual usando espaços não quebráveis (4 por nível)
                $indent = str_repeat("\u{00A0}\u{00A0}\u{00A0}\u{00A0}", $nivel);

                $resultado[] = [
                    'value' => $plano['hierarquia'],
                    'text' => $indent . $plano['hierarquia'] . ' - ' . PlanoDeContas::getDescricao($plano)
                ];
            }

            Response::json([
                'success' => true,
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => t('modules.planos_contas.messages.error_list') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sugere o próximo código hierárquico disponível
     *
     * GET /api/planos-de-contas/proximo-codigo?pai=1.1&tipo=A
     */
    public function proximoCodigo(Request $request): void
    {
        if (!Auth::can('planos_contas.criar')) {
            Response::json([
                'success' => false,
                'message' => t('common.errors.no_permission')
            ], 403);
            return;
        }

        try {
            $pai = $request->query('pai', null);
            $tipo = $request->query('tipo', null);

            // Se não tem pai, precisa do tipo
            if (empty($pai) && empty($tipo)) {
                Response::json([
                    'success' => false,
                    'message' => 'Tipo é obrigatório para contas raiz'
                ], 400);
                return;
            }

            $model = new PlanoDeContas();
            $codigo = $model->sugerirProximoCodigo($pai, $tipo);

            Response::json([
                'success' => true,
                'data' => [
                    'codigo' => $codigo
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao sugerir código: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Valida se um código hierárquico está disponível
     *
     * GET /api/planos-de-contas/validar-codigo?codigo=1.1.03&excludeId=123
     */
    public function validarCodigo(Request $request): void
    {
        if (!Auth::can('planos_contas.visualizar')) {
            Response::json([
                'success' => false,
                'message' => t('common.errors.no_permission')
            ], 403);
            return;
        }

        try {
            $codigo = $request->query('codigo', '');
            $excludeId = $request->query('excludeId', null);

            if (empty($codigo)) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.planos_contas.messages.hierarquia_required')
                ], 400);
                return;
            }

            $model = new PlanoDeContas();
            $existe = $model->hierarquiaExiste($codigo, $excludeId ? (int) $excludeId : null);

            Response::json([
                'success' => true,
                'data' => [
                    'disponivel' => !$existe,
                    'mensagem' => $existe
                        ? t('modules.planos_contas.messages.codigo_em_uso')
                        : t('modules.planos_contas.messages.codigo_disponivel')
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao validar código: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe um plano de contas específico
     *
     * GET /api/planos-de-contas/{id}
     */
    public function show(Request $request, int $id): void
    {
        if (!Auth::can('planos_contas.visualizar')) {
            Response::json([
                'success' => false,
                'message' => t('common.errors.no_permission')
            ], 403);
            return;
        }

        try {
            $model = new PlanoDeContas();
            $plano = $model->buscarPorId($id);

            if (!$plano) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.planos_contas.messages.not_found')
                ], 404);
                return;
            }

            // Verificar acesso ao tenant
            $chave = Auth::chave();
            if ($plano['chave'] !== '0' && $plano['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.planos_contas.messages.not_found')
                ], 404);
                return;
            }

            // Decodificar traduções
            $plano['traducoes'] = !empty($plano['descricao_i18n'])
                ? json_decode($plano['descricao_i18n'], true)
                : [];

            // Adicionar descrição traduzida
            $plano['descricao'] = PlanoDeContas::getDescricao($plano);
            $plano['tipo_label'] = PlanoDeContas::getTipoLabel($plano['tipo']);

            Response::json([
                'success' => true,
                'data' => $plano
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar plano de contas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria um novo plano de contas
     *
     * POST /planos-de-contas/salvar
     */
    public function store(Request $request): void
    {
        if (!Auth::can('planos_contas.criar')) {
            Response::json([
                'success' => false,
                'message' => t('common.errors.no_permission')
            ], 403);
            return;
        }

        try {
            $hierarquia = trim($request->input('hierarquia', ''));
            $tipo = trim($request->input('tipo', ''));

            // Validações básicas
            if (empty($hierarquia)) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.planos_contas.messages.hierarquia_required')
                ], 400);
                return;
            }

            // Validar formato: apenas números e pontos, padrão correto
            if (!preg_match('/^[0-9]+(\.[0-9]+)*$/', $hierarquia)) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.planos_contas.messages.formato_invalido')
                ], 400);
                return;
            }

            if (!in_array($tipo, ['A', 'P', 'D', 'R'], true)) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.planos_contas.messages.tipo_invalid')
                ], 400);
                return;
            }

            $model = new PlanoDeContas();

            // Verificar duplicidade de hierarquia
            if ($model->hierarquiaExiste($hierarquia)) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.planos_contas.messages.hierarquia_exists')
                ], 400);
                return;
            }

            // Montar traduções
            $traducoes = [
                'pt_BR' => trim($request->input('descricao_pt_BR', '')),
                'en_US' => trim($request->input('descricao_en_US', '')),
                'es_ES' => trim($request->input('descricao_es_ES', '')),
                'it_IT' => trim($request->input('descricao_it_IT', '')),
                'pt_PT' => trim($request->input('descricao_pt_PT', '')),
            ];

            // pt_BR é obrigatório
            if (empty($traducoes['pt_BR'])) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.planos_contas.messages.descricao_required')
                ], 400);
                return;
            }

            // Preencher idiomas vazios com pt_BR
            foreach ($traducoes as $locale => $valor) {
                if (empty($valor)) {
                    $traducoes[$locale] = $traducoes['pt_BR'];
                }
            }

            $dados = [
                'chave' => Auth::chave(),
                'hierarquia' => $hierarquia,
                'descricao_i18n' => json_encode($traducoes, JSON_UNESCAPED_UNICODE),
                'tipo' => $tipo
            ];

            $id = $model->criarComAuditoria($dados);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", adicionou plano de contas [{$hierarquia} - {$traducoes['pt_BR']}]"
            );

            Response::json([
                'success' => true,
                'message' => t('modules.planos_contas.messages.created'),
                'data' => ['id' => $id]
            ], 201);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => t('modules.planos_contas.messages.error_create') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza um plano de contas
     *
     * POST /planos-de-contas/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        if (!Auth::can('planos_contas.editar')) {
            Response::json([
                'success' => false,
                'message' => t('common.errors.no_permission')
            ], 403);
            return;
        }

        try {
            $model = new PlanoDeContas();
            $plano = $model->buscarPorId($id);

            if (!$plano) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.planos_contas.messages.not_found')
                ], 404);
                return;
            }

            // Verificar acesso ao tenant (não pode editar planos do sistema)
            $chave = Auth::chave();
            if ($plano['chave'] === '0') {
                Response::json([
                    'success' => false,
                    'message' => t('modules.planos_contas.messages.cannot_edit_system')
                ], 403);
                return;
            }

            if ($plano['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => t('common.errors.no_permission')
                ], 403);
                return;
            }

            $hierarquia = trim($request->input('hierarquia', ''));
            $tipo = trim($request->input('tipo', ''));

            // Validações básicas
            if (empty($hierarquia)) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.planos_contas.messages.hierarquia_required')
                ], 400);
                return;
            }

            // Validar formato: apenas números e pontos, padrão correto
            if (!preg_match('/^[0-9]+(\.[0-9]+)*$/', $hierarquia)) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.planos_contas.messages.formato_invalido')
                ], 400);
                return;
            }

            if (!in_array($tipo, ['A', 'P', 'D', 'R'], true)) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.planos_contas.messages.tipo_invalid')
                ], 400);
                return;
            }

            // Verificar duplicidade de hierarquia (excluindo o próprio)
            if ($model->hierarquiaExiste($hierarquia, $id)) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.planos_contas.messages.hierarquia_exists')
                ], 400);
                return;
            }

            // Montar traduções
            $traducoes = [
                'pt_BR' => trim($request->input('descricao_pt_BR', '')),
                'en_US' => trim($request->input('descricao_en_US', '')),
                'es_ES' => trim($request->input('descricao_es_ES', '')),
                'it_IT' => trim($request->input('descricao_it_IT', '')),
                'pt_PT' => trim($request->input('descricao_pt_PT', '')),
            ];

            // pt_BR é obrigatório
            if (empty($traducoes['pt_BR'])) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.planos_contas.messages.descricao_required')
                ], 400);
                return;
            }

            // Preencher idiomas vazios com pt_BR
            foreach ($traducoes as $locale => $valor) {
                if (empty($valor)) {
                    $traducoes[$locale] = $traducoes['pt_BR'];
                }
            }

            $dados = [
                'hierarquia' => $hierarquia,
                'descricao_i18n' => json_encode($traducoes, JSON_UNESCAPED_UNICODE),
                'tipo' => $tipo
            ];

            $model->atualizarComAuditoria($id, $dados);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou plano de contas [{$hierarquia} - {$traducoes['pt_BR']}]"
            );

            Response::json([
                'success' => true,
                'message' => t('modules.planos_contas.messages.updated')
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => t('modules.planos_contas.messages.error_update') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui um plano de contas
     *
     * POST /planos-de-contas/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        if (!Auth::can('planos_contas.excluir')) {
            Response::json([
                'success' => false,
                'message' => t('common.errors.no_permission')
            ], 403);
            return;
        }

        try {
            $model = new PlanoDeContas();
            $plano = $model->buscarPorId($id);

            if (!$plano) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.planos_contas.messages.not_found')
                ], 404);
                return;
            }

            // Verificar acesso ao tenant (não pode excluir planos do sistema)
            $chave = Auth::chave();
            if ($plano['chave'] === '0') {
                Response::json([
                    'success' => false,
                    'message' => t('modules.planos_contas.messages.cannot_delete_system')
                ], 403);
                return;
            }

            if ($plano['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => t('common.errors.no_permission')
                ], 403);
                return;
            }

            // Verificar se possui lançamentos
            if ($model->possuiLancamentos($id)) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.planos_contas.messages.has_transactions')
                ], 400);
                return;
            }

            $descricao = PlanoDeContas::getDescricao($plano);

            $model->deletarComAuditoria($id);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu plano de contas [{$plano['hierarquia']} - {$descricao}]"
            );

            Response::json([
                'success' => true,
                'message' => t('modules.planos_contas.messages.deleted')
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => t('modules.planos_contas.messages.error_delete') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista tipos de plano de contas para select
     *
     * GET /api/planos-de-contas/tipos
     */
    public function tipos(Request $request): void
    {
        try {
            $tipos = PlanoDeContas::getTipos();

            $resultado = [];
            foreach ($tipos as $codigo => $label) {
                $resultado[] = [
                    'id' => $codigo,
                    'nome' => $label
                ];
            }

            Response::json([
                'success' => true,
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage()
            ], 500);
        }
    }
}
