<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\SiteConfig;
use App\Models\SiteCredencial;
use App\Models\SiteAparencia;
use App\Models\SitePreset;
use App\Models\SiteConteudo;
use App\Models\SiteSeo;
use App\Models\SiteIntegracao;
use App\Models\SiteIdioma;
use App\Models\SiteDeployLog;
use App\Models\SiteLink;
use App\Models\SiteBanner;
use App\Services\WebsiteService;
use App\Services\AuditLogService;
use App\Helpers\FileHelper;

class WebsiteController
{
    private function canManageWebsite(): bool
    {
        return Auth::can('website.configurar') || Auth::can('website.editar');
    }

    // =========================================================================
    // VIEWS (renderizam HTML)
    // =========================================================================

    /**
     * GET /pages/website/configuracoes
     */
    public function configuracoes(Request $request): void
    {
        $html = Template::render('pages.website.configuracoes');
        Response::html($html);
    }

    /**
     * GET /pages/website/ativar
     */
    public function ativar(Request $request): void
    {
        $html = Template::render('pages.website.ativar');
        Response::html($html);
    }

    /**
     * GET /pages/website/aparencia
     */
    public function aparencia(Request $request): void
    {
        $html = Template::render('pages.website.aparencia');
        Response::html($html);
    }

    /**
     * GET /pages/website/conteudos
     */
    public function conteudos(Request $request): void
    {
        $html = Template::render('pages.website.conteudos');
        Response::html($html);
    }

    /**
     * GET /pages/website/seo
     */
    public function seo(Request $request): void
    {
        $html = Template::render('pages.website.seo');
        Response::html($html);
    }

    /**
     * GET /pages/website/banners
     */
    public function banners(Request $request): void
    {
        $html = Template::render('pages.website.banners');
        Response::html($html);
    }

    /**
     * GET /pages/website/integracoes
     */
    public function integracoes(Request $request): void
    {
        $html = Template::render('pages.website.integracoes');
        Response::html($html);
    }

    /**
     * GET /pages/website/publicar
     */
    public function deploy(Request $request): void
    {
        $html = Template::render('pages.website.publicar');
        Response::html($html);
    }

    // =========================================================================
    // API - CONFIGURACAO
    // =========================================================================

    /**
     * GET /api/website/config
     */
    public function getConfig(Request $request): void
    {
        try {
            $model = new SiteConfig();
            $config = $model->buscarPorChave();

            Response::json(['success' => true, 'data' => $config]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/website/config
     */
    public function updateConfig(Request $request): void
    {
        try {
            if (!$this->canManageWebsite()) {
                Response::json(['success' => false, 'message' => t('common.errors.forbidden')], 403);
                return;
            }

            $dados = $request->only([
                'manutencao', 'reserva_online', 'overbooking', 'pagamento_antecipado',
                'idioma_padrao', 'whatsapp_flutuante', 'whatsapp_numero', 'whatsapp_mensagem',
                // Pre-cadastro e documentos no passo 4 do site publico
                'cadastro_simples', 'envio_documentos',
                'doc_cnh_obrigatorio', 'doc_cpf_obrigatorio',
                'doc_rg_obrigatorio', 'doc_comprovante_obrigatorio',
                // Reserva requer confirmacao manual da locadora
                'reserva_requer_confirmacao',
            ]);

            // Converter para int os campos booleanos
            $boolFields = [
                'manutencao', 'reserva_online', 'overbooking', 'pagamento_antecipado', 'whatsapp_flutuante',
                'cadastro_simples', 'envio_documentos',
                'doc_cnh_obrigatorio', 'doc_cpf_obrigatorio', 'doc_rg_obrigatorio', 'doc_comprovante_obrigatorio',
                'reserva_requer_confirmacao',
            ];
            foreach ($boolFields as $field) {
                if (isset($dados[$field])) {
                    $dados[$field] = (int) $dados[$field];
                }
            }

            $model = new SiteConfig();
            $model->criarOuAtualizar($dados);

            AuditLogService::registrarComAuditFrontend(
                'Atualizou configurações do website',
                $request->input('_audit_data'),
                $request->input('_audit_changes')
            );

            Response::json(['success' => true, 'message' => t('common.messages.saved')]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API - APARENCIA
    // =========================================================================

    /**
     * GET /api/website/aparencia
     */
    public function getAparencia(Request $request): void
    {
        try {
            $model = new SiteAparencia();
            $aparencia = $model->buscarPorChave();

            if ($aparencia) {
                $aparencia['logo_url']    = FileHelper::url($aparencia['logo'] ?? null);
                $aparencia['favicon_url'] = FileHelper::url($aparencia['favicon'] ?? null);
            }

            // Incluir presets fixos e customizados
            $presetModel = new SitePreset();
            $presetsCustom = $presetModel->listar();

            Response::json([
                'success'          => true,
                'data'             => $aparencia,
                'presets_fixos'    => \App\Config\WebsiteThemes::PRESETS,
                'presets_custom'   => $presetsCustom,
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/website/aparencia
     */
    public function updateAparencia(Request $request): void
    {
        try {
            if (!$this->canManageWebsite()) {
                Response::json(['success' => false, 'message' => t('common.errors.forbidden')], 403);
                return;
            }

            $dados = $request->only([
                'preset_cor', 'cores_customizadas', 'css_customizado',
                'fonte_primaria', 'fonte_url', 'logo_fundo_branco',
                'logo_alinhamento',
            ]);

            if (isset($dados['logo_fundo_branco'])) {
                $dados['logo_fundo_branco'] = (int) $dados['logo_fundo_branco'];
            }

            $model = new SiteAparencia();
            $atual = $model->buscarPorChave();

            // Processar logo: base64 -> arquivo em disco, grava so o filename
            $logoBase64 = $request->input('logo_base64');
            if (!empty($logoBase64)) {
                if (!empty($atual['logo'])) {
                    FileHelper::delete($atual['logo']);
                }
                $dados['logo'] = FileHelper::save($logoBase64, 'logo');
            } elseif ($request->input('remover_logo')) {
                if (!empty($atual['logo'])) {
                    FileHelper::delete($atual['logo']);
                }
                $dados['logo'] = null;
            }

            // Mesmo tratamento para favicon
            $faviconBase64 = $request->input('favicon_base64');
            if (!empty($faviconBase64)) {
                if (!empty($atual['favicon'])) {
                    FileHelper::delete($atual['favicon']);
                }
                $dados['favicon'] = FileHelper::save($faviconBase64, 'favicon');
            } elseif ($request->input('remover_favicon')) {
                if (!empty($atual['favicon'])) {
                    FileHelper::delete($atual['favicon']);
                }
                $dados['favicon'] = null;
            }

            $model->criarOuAtualizar($dados);

            AuditLogService::registrarComAuditFrontend(
                'Atualizou aparência do website',
                $request->input('_audit_data'),
                $request->input('_audit_changes')
            );

            Response::json(['success' => true, 'message' => t('common.messages.saved')]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/website/aparencia/reset
     */
    public function resetAparencia(Request $request): void
    {
        try {
            if (!$this->canManageWebsite()) {
                Response::json(['success' => false, 'message' => t('common.errors.forbidden')], 403);
                return;
            }

            $action = $request->input('action', 'reset'); // 'reset' ou 'undo'
            $model = new SiteAparencia();

            if ($action === 'undo') {
                $model->undoCssReset();
            } else {
                $model->resetCss();
            }

            Response::json(['success' => true, 'message' => t('common.messages.saved')]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API - CONTEUDOS
    // =========================================================================

    /**
     * GET /api/website/conteudos/{pagina}
     */
    public function getConteudos(Request $request, string $pagina): void
    {
        try {
            $idioma = $request->query('idioma', 'pt_BR');
            $model = new SiteConteudo();
            $conteudos = $model->buscarPorPaginaIdioma($pagina, $idioma);

            Response::json(['success' => true, 'data' => $conteudos]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/website/conteudos/{pagina}
     */
    public function updateConteudos(Request $request, string $pagina): void
    {
        try {
            if (!$this->canManageWebsite()) {
                Response::json(['success' => false, 'message' => t('common.errors.forbidden')], 403);
                return;
            }

            $idioma = $request->input('idioma', 'pt_BR');
            $secoes = $request->input('secoes', []);

            $model = new SiteConteudo();
            foreach ($secoes as $secao => $conteudo) {
                $model->salvar($pagina, (string) $secao, $idioma, $conteudo);
            }

            Response::json(['success' => true, 'message' => t('common.messages.saved')]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API - SEO
    // =========================================================================

    /**
     * GET /api/website/seo/{pagina}
     */
    public function getSeo(Request $request, string $pagina): void
    {
        try {
            $idioma = $request->query('idioma', 'pt_BR');
            $model = new SiteSeo();
            $seo = $model->buscarPorPaginaIdioma($pagina, $idioma);

            Response::json(['success' => true, 'data' => $seo]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/website/seo/{pagina}
     */
    public function updateSeo(Request $request, string $pagina): void
    {
        try {
            if (!$this->canManageWebsite()) {
                Response::json(['success' => false, 'message' => t('common.errors.forbidden')], 403);
                return;
            }

            $idioma = $request->input('idioma', 'pt_BR');
            $dados = $request->only([
                'meta_titulo', 'meta_descricao', 'meta_keywords',
                'og_titulo', 'og_descricao', 'og_imagem',
                'dados_estruturados',
            ]);

            $model = new SiteSeo();
            $model->salvar($pagina, $idioma, $dados);

            Response::json(['success' => true, 'message' => t('common.messages.saved')]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API - INTEGRACOES
    // =========================================================================

    /**
     * GET /api/website/integracoes
     */
    public function getIntegracoes(Request $request): void
    {
        try {
            $model = new SiteIntegracao();
            Response::json(['success' => true, 'data' => $model->listar()]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/website/integracoes
     */
    public function saveIntegracao(Request $request): void
    {
        try {
            if (!$this->canManageWebsite()) {
                Response::json(['success' => false, 'message' => t('common.errors.forbidden')], 403);
                return;
            }

            $dados = $request->only(['tipo', 'codigo', 'descricao', 'ativo', 'ordem']);
            if (isset($dados['ativo'])) {
                $dados['ativo'] = (int) $dados['ativo'];
            }

            $model = new SiteIntegracao();
            $id = $request->input('id');

            if ($id) {
                $model->atualizar((int) $id, $dados);
            } else {
                $model->criar($dados);
            }

            Response::json(['success' => true, 'message' => t('common.messages.saved')]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/website/integracoes/{id}
     */
    public function deleteIntegracao(Request $request, int $id): void
    {
        try {
            if (!$this->canManageWebsite()) {
                Response::json(['success' => false, 'message' => t('common.errors.forbidden')], 403);
                return;
            }

            $model = new SiteIntegracao();
            $model->excluir($id);

            Response::json(['success' => true, 'message' => t('common.messages.deleted')]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API - BANNERS
    // =========================================================================

    /**
     * GET /api/website/banners
     */
    public function getBanners(Request $request): void
    {
        try {
            $idioma = $request->query('idioma');
            $model = new SiteBanner();
            $banners = $model->listar($idioma);
            foreach ($banners as &$banner) {
                $banner['foto_url'] = FileHelper::url($banner['foto'] ?? null);
            }

            Response::json(['success' => true, 'data' => $banners]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/website/banners
     */
    public function saveBanner(Request $request): void
    {
        try {
            if (!$this->canManageWebsite()) {
                Response::json(['success' => false, 'message' => t('common.errors.forbidden')], 403);
                return;
            }

            $dados = $request->only(['titulo', 'mensagem', 'alt_text', 'link_url', 'link_target', 'idioma', 'ativo', 'ordem']);
            if (isset($dados['ativo'])) {
                $dados['ativo'] = (int) $dados['ativo'];
            }
            $dados['titulo'] = trim((string)($dados['titulo'] ?? ''));
            $dados['mensagem'] = (string)($dados['mensagem'] ?? '');

            if ($dados['titulo'] === '') {
                Response::json(['success' => false, 'message' => t('messages.info.required_field') . ': ' . t('modules.website.banner_title')], 422);
                return;
            }

            $fotoBase64 = $request->input('foto_base64', '');
            if (empty($fotoBase64)) {
                Response::json(['success' => false, 'message' => t('messages.info.required_field') . ': ' . t('modules.website.banner_image')], 422);
                return;
            }

            $filename = FileHelper::save($fotoBase64, 'banner');
            if (!$filename) {
                Response::json(['success' => false, 'message' => t('messages.error.upload_failed')], 422);
                return;
            }
            $dados['foto'] = $filename;

            $model = new SiteBanner();
            $id = $model->criar($dados);

            Response::json(['success' => true, 'message' => t('common.messages.saved'), 'id' => $id]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /api/website/banners/{id}
     */
    public function updateBanner(Request $request, int $id): void
    {
        $novoArquivo = '';

        try {
            if (!$this->canManageWebsite()) {
                Response::json(['success' => false, 'message' => t('common.errors.forbidden')], 403);
                return;
            }

            $dados = $request->only(['titulo', 'mensagem', 'alt_text', 'link_url', 'link_target', 'idioma', 'ativo', 'ordem']);
            if (isset($dados['ativo'])) {
                $dados['ativo'] = (int) $dados['ativo'];
            }
            if (isset($dados['titulo'])) {
                $dados['titulo'] = trim((string)$dados['titulo']);
                if ($dados['titulo'] === '') {
                    Response::json(['success' => false, 'message' => t('messages.info.required_field') . ': ' . t('modules.website.banner_title')], 422);
                    return;
                }
            }
            if (array_key_exists('mensagem', $dados)) {
                $dados['mensagem'] = (string)$dados['mensagem'];
            }

            $model = new SiteBanner();
            $atual = $model->buscarPorId($id);
            if (!$atual) {
                Response::json(['success' => false, 'message' => t('messages.error.not_found')], 404);
                return;
            }

            $fotoBase64 = $request->input('foto_base64', '');
            if (!empty($fotoBase64)) {
                $novoArquivo = FileHelper::save($fotoBase64, 'banner') ?: '';
                if ($novoArquivo === '') {
                    Response::json(['success' => false, 'message' => t('messages.error.upload_failed')], 422);
                    return;
                }
                $dados['foto'] = $novoArquivo;
            }

            $atualizados = $model->atualizar($id, $dados);
            if ($novoArquivo !== '' && $atualizados < 1) {
                FileHelper::delete($novoArquivo);
                $novoArquivo = '';
                Response::json(['success' => false, 'message' => t('messages.error.upload_failed')], 422);
                return;
            }

            if ($novoArquivo !== '' && !empty($atual['foto']) && $atual['foto'] !== $novoArquivo) {
                FileHelper::delete($atual['foto']);
            }

            Response::json(['success' => true, 'message' => t('common.messages.saved')]);
        } catch (\Throwable $e) {
            if ($novoArquivo !== '') {
                FileHelper::delete($novoArquivo);
            }
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/website/banners/{id}
     */
    public function deleteBanner(Request $request, int $id): void
    {
        try {
            if (!$this->canManageWebsite()) {
                Response::json(['success' => false, 'message' => t('common.errors.forbidden')], 403);
                return;
            }

            $model = new SiteBanner();
            $banner = $model->buscarPorId($id);
            if (!$banner) {
                Response::json(['success' => false, 'message' => t('messages.error.not_found')], 404);
                return;
            }

            $deleted = $model->excluir($id);
            if ($deleted < 1) {
                Response::json(['success' => false, 'message' => t('messages.error.not_found')], 404);
                return;
            }

            if (!empty($banner['foto'])) {
                FileHelper::delete($banner['foto']);
            }

            Response::json(['success' => true, 'message' => t('common.messages.deleted')]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/website/banners/reordenar
     */
    public function reordenarBanners(Request $request): void
    {
        try {
            if (!$this->canManageWebsite()) {
                Response::json(['success' => false, 'message' => t('common.errors.forbidden')], 403);
                return;
            }

            $ordens = $request->input('ordens', []);
            $model = new SiteBanner();
            $model->reordenar($ordens);

            Response::json(['success' => true]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API - LINKS
    // =========================================================================

    /**
     * GET /api/website/links
     */
    public function getLinks(Request $request): void
    {
        try {
            $model = new SiteLink();
            Response::json(['success' => true, 'data' => $model->listar()]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/website/links
     */
    public function saveLinks(Request $request): void
    {
        try {
            if (!$this->canManageWebsite()) {
                Response::json(['success' => false, 'message' => t('common.errors.forbidden')], 403);
                return;
            }

            $links = $request->input('links', []);
            $model = new SiteLink();
            $model->salvarTodos($links);

            Response::json(['success' => true, 'message' => t('common.messages.saved')]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API - IDIOMAS
    // =========================================================================

    /**
     * GET /api/website/idiomas
     */
    public function getIdiomas(Request $request): void
    {
        try {
            $model = new SiteIdioma();
            Response::json(['success' => true, 'data' => $model->listar()]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/website/idiomas
     */
    public function saveIdiomas(Request $request): void
    {
        try {
            if (!$this->canManageWebsite()) {
                Response::json(['success' => false, 'message' => t('common.errors.forbidden')], 403);
                return;
            }

            $idiomas = $request->input('idiomas', []);
            $model = new SiteIdioma();
            $model->salvar($idiomas);

            Response::json(['success' => true, 'message' => t('common.messages.saved')]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API - PRESETS
    // =========================================================================

    /**
     * POST /api/website/presets
     */
    public function savePreset(Request $request): void
    {
        try {
            if (!$this->canManageWebsite()) {
                Response::json(['success' => false, 'message' => t('common.errors.forbidden')], 403);
                return;
            }

            $nome = $request->input('nome');
            $cores = $request->input('cores');

            if (empty($nome) || empty($cores)) {
                Response::json(['success' => false, 'message' => t('common.errors.required_fields')], 422);
                return;
            }

            // Nao permitir nomes iguais aos presets fixos
            if (\App\Config\WebsiteThemes::isPresetFixo($nome)) {
                Response::json(['success' => false, 'message' => t('modules.website.preset_name_reserved')], 422);
                return;
            }

            $model = new SitePreset();
            $model->criar($nome, $cores);

            Response::json(['success' => true, 'message' => t('common.messages.saved')]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/website/presets/{id}
     */
    public function deletePreset(Request $request, int $id): void
    {
        try {
            if (!$this->canManageWebsite()) {
                Response::json(['success' => false, 'message' => t('common.errors.forbidden')], 403);
                return;
            }

            $model = new SitePreset();
            $model->excluir($id);

            Response::json(['success' => true, 'message' => t('common.messages.deleted')]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API - ATIVACAO E DNS
    // =========================================================================

    /**
     * POST /api/website/ativar
     */
    public function submitAtivacao(Request $request): void
    {
        try {
            $dados = [
                'dominio'          => $request->input('dominio'),
                'empresa'          => $request->input('empresa'),
                'plano'            => $request->input('plano'),
                'quer_registro'    => (bool) $request->input('quer_registro'),
                'quer_hospedagem'  => (bool) $request->input('quer_hospedagem'),
            ];

            if (empty($dados['dominio'])) {
                Response::json(['success' => false, 'message' => t('modules.website.domain_required')], 422);
                return;
            }

            $service = new WebsiteService();
            $result = $service->solicitarAtivacao($dados);

            Response::json($result);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/website/verificar-dominio
     */
    public function verificarDominio(Request $request): void
    {
        try {
            $dominio = $request->query('dominio', '');
            $service = new WebsiteService();
            $result = $service->verificarDominio($dominio);

            Response::json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // API - DEPLOY
    // =========================================================================

    /**
     * POST /api/website/deploy
     */
    public function executarDeploy(Request $request): void
    {
        try {
            if (!Auth::can('website.deploy')) {
                Response::json(['success' => false, 'message' => t('common.errors.forbidden')], 403);
                return;
            }

            $chave = Auth::chave();
            $funcionarioId = Auth::id();

            @set_time_limit(300);
            @ini_set('memory_limit', '512M');

            // O set_error_handler global (public/index.php) converte qualquer warning
            // em HTTP 500 — FTP/RecursiveIterator emitem warnings rotineiros durante
            // o deploy. Silenciamos temporariamente.
            set_error_handler(function () { return true; });

            try {
                $builder = new \App\Services\WebsiteBuilderService();
                $result = $builder->deploy($chave, $funcionarioId);
            } finally {
                restore_error_handler();
            }

            Response::json($result);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/website/deploy/status
     */
    public function deployStatus(Request $request): void
    {
        try {
            $configModel = new SiteConfig();
            $config = $configModel->buscarPorChave();

            // Ler versao do template
            $versaoArquivo = null;
            $versaoJsonPath = APP_ROOT . '/storage/templates/website/versao.json';
            if (file_exists($versaoJsonPath)) {
                $json = json_decode(file_get_contents($versaoJsonPath), true);
                $versaoArquivo = $json['versao'] ?? null;
            }

            Response::json([
                'success'            => true,
                'versao_atual'       => $config['versao'] ?? null,
                'versao_template'    => $versaoArquivo,
                'ultimo_deploy_em'   => $config['ultimo_deploy_em'] ?? null,
                'update_disponivel'  => $versaoArquivo && $config && version_compare($versaoArquivo, $config['versao'] ?? '0.0.0', '>'),
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/website/deploy/log
     */
    public function deployLog(Request $request): void
    {
        try {
            $model = new SiteDeployLog();
            Response::json(['success' => true, 'data' => $model->listarRecentes()]);
        } catch (\Throwable $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage(),
                'file'    => basename($e->getFile()) . ':' . $e->getLine(),
            ], 500);
        }
    }

    /**
     * POST /api/website/preview
     */
    public function preview(Request $request): void
    {
        try {
            if (!$this->canManageWebsite()) {
                Response::json(['success' => false, 'message' => t('common.errors.forbidden')], 403);
                return;
            }

            // Placeholder — sera implementado na Fase 4
            Response::json(['success' => false, 'message' => t('modules.website.preview_not_available')]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // WEBHOOK WHMCS
    // =========================================================================

    /**
     * GET /api/webhook/whmcs/site-ativacao
     *
     * Parâmetros esperados (query string):
     * - chave         : chave do tenant
     * - secret        : TENANT_ONBOARD_SECRET
     * - ftp_host      : host FTP (plaintext)
     * - ftp_porta     : porta (opcional, default 21)
     * - ftp_usuario   : usuário (plaintext)
     * - ftp_senha     : senha criptografada (base64 AES-256-CBC, chave = sha256(secret))
     * - ftp_diretorio : diretório remoto (opcional, default /public_html)
     */
    public function webhookWhmcsAtivacao(Request $request): void
    {
        try {
            $chave  = $request->query('chave', '');
            $secret = $request->query('secret', '');

            $ftpData = [
                'host'      => $request->query('ftp_host', ''),
                'porta'     => $request->query('ftp_porta', '21'),
                'usuario'   => $request->query('ftp_usuario', ''),
                'senha'     => $request->query('ftp_senha', ''),
                'diretorio' => $request->query('ftp_diretorio', '/public_html'),
            ];

            if (empty($chave) || empty($secret)) {
                Response::json(['success' => false, 'message' => 'Parâmetros incompletos'], 400);
                return;
            }

            $service = new WebsiteService();
            $result = $service->processarCallbackWhmcs($chave, $secret, $ftpData);

            if (!empty($result['redirect']) && !empty($result['redirect_url'])) {
                Response::redirect($result['redirect_url']);
                return;
            }

            $statusCode = $result['success'] ? 200 : 401;
            Response::json($result, $statusCode);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
