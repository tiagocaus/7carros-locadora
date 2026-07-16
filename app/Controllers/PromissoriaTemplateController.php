<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Services\PromissoriaTemplateService;
use App\I18n\Translator;
use App\Models\Promissoria;

/**
 * Controller de Templates de Promissoria
 *
 * Gerencia operacoes CRUD de templates de promissoria.
 *
 * Permissoes:
 * - promissorias_templates.visualizar
 * - promissorias_templates.editar
 */
class PromissoriaTemplateController
{
    private ?PromissoriaTemplateService $service = null;
    private Promissoria $promissoriaModel;

    public function __construct()
    {
        $this->promissoriaModel = new Promissoria();
    }

    /**
     * Obtem instancia do service
     */
    private function getService(): PromissoriaTemplateService
    {
        if ($this->service === null) {
            $this->service = new PromissoriaTemplateService(
                $this->promissoriaModel->getMysqliConnection(),
                Auth::chave()
            );
        }
        return $this->service;
    }

    /**
     * Renderiza a pagina de edicao de templates
     *
     * GET /pages/promissorias/templates
     */
    public function view(Request $request): void
    {
        if (!Auth::can('promissorias_templates.visualizar')) {
            Response::html('<p>Voce nao tem permissao para acessar esta pagina.</p>', 403);
            return;
        }

        $html = Template::render('pages.promissorias.templates.index');
        Response::html($html);
    }

    /**
     * Lista tipos de templates disponiveis
     *
     * GET /api/promissorias/templates/types
     * Query params: category
     */
    public function listTypes(Request $request): void
    {
        try {
            if (!Auth::can('promissorias_templates.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para visualizar templates'
                ], 403);
                return;
            }

            $category = $request->query('category');

            $service = $this->getService();
            $types = $service->getTemplateTypes($category);

            // Adicionar informacao de customizacao por tipo
            $locale = Translator::getInstance()->getLocale();
            foreach ($types as &$type) {
                $type['is_customized'] = $service->isCustomized($type['slug'], $locale);
            }

            Response::json([
                'success' => true,
                'data' => $types
            ]);
        } catch (\Throwable $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao listar tipos de template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca um template especifico
     *
     * GET /api/promissorias/templates/{slug}
     * Query params: locale
     */
    public function show(Request $request, string $slug): void
    {
        try {
            if (!Auth::can('promissorias_templates.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para visualizar templates'
                ], 403);
                return;
            }

            $locale = $request->query('locale') ?? Translator::getInstance()->getLocale();

            $service = $this->getService();
            $template = $service->getTemplate($slug, $locale);

            if (!$template) {
                Response::json([
                    'success' => false,
                    'message' => 'Template nao encontrado'
                ], 404);
                return;
            }

            // Adicionar variaveis disponiveis
            $template['available_variables'] = $service->getAvailableVariables($slug, $locale);

            Response::json([
                'success' => true,
                'data' => $template
            ]);
        } catch (\Throwable $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Salva um template customizado
     *
     * POST /api/promissorias/templates/{slug}
     * Body: { locale, content }
     */
    public function save(Request $request, string $slug): void
    {
        try {
            if (!Auth::can('promissorias_templates.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para editar templates'
                ], 403);
                return;
            }

            $locale = $request->input('locale') ?? Translator::getInstance()->getLocale();
            $content = $request->input('content');

            if (empty($content)) {
                Response::json([
                    'success' => false,
                    'message' => 'O conteudo do template e obrigatorio'
                ], 400);
                return;
            }

            $service = $this->getService();

            // Validar template
            $errors = $service->validateTemplate($slug, $content);
            if (!empty($errors)) {
                Response::json([
                    'success' => false,
                    'message' => 'Template contem variaveis invalidas',
                    'errors' => $errors
                ], 400);
                return;
            }

            $userId = Auth::id();
            $templateId = $service->saveTemplate($slug, $locale, $content, $userId);

            Response::json([
                'success' => true,
                'message' => 'Template salvo com sucesso',
                'data' => [
                    'id' => $templateId
                ]
            ]);
        } catch (\Throwable $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao salvar template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restaura template para o padrao do sistema
     *
     * POST /api/promissorias/templates/{slug}/restore
     * Body: { locale }
     */
    public function restore(Request $request, string $slug): void
    {
        try {
            if (!Auth::can('promissorias_templates.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para editar templates'
                ], 403);
                return;
            }

            $locale = $request->input('locale') ?? Translator::getInstance()->getLocale();

            $service = $this->getService();
            $restored = $service->restoreDefault($slug, $locale);

            if ($restored) {
                Response::json([
                    'success' => true,
                    'message' => 'Template restaurado para o padrao'
                ]);
            } else {
                Response::json([
                    'success' => true,
                    'message' => 'Template ja estava no padrao'
                ]);
            }
        } catch (\Throwable $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao restaurar template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna variaveis disponiveis para um tipo de template
     *
     * GET /api/promissorias/templates/{slug}/variables
     * Query params: locale
     */
    public function variables(Request $request, string $slug): void
    {
        try {
            if (!Auth::can('promissorias_templates.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para visualizar templates'
                ], 403);
                return;
            }

            $locale = $request->query('locale') ?? Translator::getInstance()->getLocale();

            $service = $this->getService();
            $variables = $service->getAvailableVariables($slug, $locale);

            Response::json([
                'success' => true,
                'data' => $variables
            ]);
        } catch (\Throwable $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar variaveis: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gera preview de um template
     *
     * POST /api/promissorias/templates/preview
     * Body: { content }
     */
    public function preview(Request $request): void
    {
        try {
            if (!Auth::can('promissorias_templates.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para visualizar templates'
                ], 403);
                return;
            }

            $content = $request->input('content', '');

            $service = $this->getService();
            $preview = $service->preview($content);

            Response::json([
                'success' => true,
                'data' => [
                    'html' => $preview
                ]
            ]);
        } catch (\Throwable $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao gerar preview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista todos os templates (para exportar ou backup)
     *
     * GET /api/promissorias/templates
     */
    public function listAll(Request $request): void
    {
        try {
            if (!Auth::can('promissorias_templates.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para visualizar templates'
                ], 403);
                return;
            }

            $service = $this->getService();
            $templates = $service->getAllTemplates();

            Response::json([
                'success' => true,
                'data' => $templates
            ]);
        } catch (\Throwable $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao listar templates: ' . $e->getMessage()
            ], 500);
        }
    }
}
