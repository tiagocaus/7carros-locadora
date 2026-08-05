<?php

declare(strict_types=1);

namespace App\Services;

use App\Classes\QueryBuilder;
use App\Core\Database;
use App\Helpers\FileHelper;
use App\I18n\TemplateRenderer;
use App\I18n\TemplateVariables;
use App\I18n\Translator;
use App\Models\MatrizFilial;
use App\Models\Model;

/**
 * Service para gerenciamento de templates de mensagem
 *
 * Responsabilidades:
 * - Buscar templates (customizados ou padrão)
 * - Salvar templates customizados
 * - Renderizar templates com contexto
 * - Gerenciar tipos de template
 *
 * @example
 * $service = new MessageTemplateService($mysqli, 'chave_tenant');
 * $template = $service->getTemplate('welcome', 'email', 'pt_BR');
 * $rendered = $service->render('welcome', 'email', $context);
 */
class MessageTemplateService
{
    private QueryBuilder $db;
    private string $chave;
    private TemplateRenderer $renderer;
    private ?array $empresaCache = null;

    public function __construct(?\mysqli $mysqli, string $chave)
    {
        $this->db = new QueryBuilder($mysqli ?? Model::sharedMysqli());
        $this->db->withoutChave(); // Templates usam chave manualmente
        $this->chave = $chave;
        $this->renderer = new TemplateRenderer();
    }

    /**
     * Busca um template pelo tipo, canal e locale
     *
     * Prioridade:
     * 1. Template customizado da empresa (chave)
     * 2. Template padrão do sistema
     *
     * @param string $typeSlug Slug do tipo (ex: 'welcome', 'rental_confirmation')
     * @param string $channel Canal ('email', 'sms', 'whatsapp')
     * @param string|null $locale Locale (null = locale atual)
     * @return array|null Template encontrado ou null
     */
    public function getTemplate(string $typeSlug, string $channel, ?string $locale = null): ?array
    {
        $locale = $locale ?? Translator::getInstance()->getLocale();

        // 1. Buscar tipo de template
        $type = $this->getTemplateType($typeSlug);
        if (!$type) {
            return null;
        }

        // 2. Buscar template customizado da empresa
        $custom = $this->db->getRow(
            'message_templates',
            ['*'],
            'chave = ? AND template_type_id = ? AND locale = ? AND channel = ? AND is_active = 1',
            [$this->chave, $type['id'], $locale, $channel],
            true
        );

        if ($custom) {
            $custom['is_custom'] = true;
            $custom['type'] = $type;
            return $custom;
        }

        // 3. Buscar template padrão do sistema (chave = '0')
        $default = $this->db->getRow(
            'message_templates',
            ['*'],
            'chave = ? AND template_type_id = ? AND locale = ? AND channel = ? AND is_active = 1',
            ['0', $type['id'], $locale, $channel],
            true
        );

        if ($default) {
            $default['is_custom'] = false;
            $default['type'] = $type;
            return $default;
        }

        // 4. Fallback para locale padrão (pt_BR)
        if ($locale !== 'pt_BR') {
            return $this->getTemplate($typeSlug, $channel, 'pt_BR');
        }

        return null;
    }

    /**
     * Renderiza um template com contexto
     *
     * @param string $typeSlug Slug do tipo
     * @param string $channel Canal
     * @param array $context Dados para substituição
     * @param string|null $locale Locale (null = locale do cliente ou empresa)
     * @return array|null ['subject' => string, 'content' => string, 'content_plain' => string]
     */
    public function render(string $typeSlug, string $channel, array $context, ?string $locale = null): ?array
    {
        // Determinar locale do destinatário
        if ($locale === null) {
            $locale = $context['cliente']['preferred_locale']
                ?? $context['empresa']['locale']
                ?? Translator::getInstance()->getLocale();
        }

        $template = $this->getTemplate($typeSlug, $channel, $locale);
        if (!$template) {
            return null;
        }

        $this->renderer->setLocale($locale);

        // Enriquece empresa.* com dados da matriz (mapeando BD -> variaveis de template)
        $context = $this->enrichEmpresaContext($context);

        // Renderizar o conteúdo do template
        $renderedContent = $this->renderer->render($template['content'], $context);
        $parcelaDescricao = TemplateVariables::resolve('fatura.parcela_descricao', $context, $locale);
        if ($parcelaDescricao !== null && !$this->templateExibeParcela((string) $template['content'])) {
            $renderedContent = $this->appendParcela($renderedContent, $parcelaDescricao, $channel);
        }

        $renderedPlain = null;
        if (!empty($template['content_plain'])) {
            $renderedPlain = $this->renderer->render($template['content_plain'], $context);
            if ($parcelaDescricao !== null && !$this->templateExibeParcela((string) $template['content_plain'])) {
                $renderedPlain = $this->appendParcela($renderedPlain, $parcelaDescricao, 'plain');
            }
        }

        // Para emails, envolver no layout base
        if ($channel === 'email') {
            $renderedContent = $this->renderEmailLayout($renderedContent, $context, $locale);
        }

        return [
            'subject' => $template['subject']
                ? $this->renderer->render($template['subject'], $context)
                : null,
            'content' => $renderedContent,
            'content_plain' => $renderedPlain ?? $this->renderer->toPlainText($renderedContent),
            'locale' => $locale,
            'channel' => $channel,
            'type_slug' => $typeSlug,
            'is_custom' => $template['is_custom'],
        ];
    }

    /**
     * Aplica o layout e o branding do tenant a um conteudo HTML ja renderizado.
     *
     * Permite que mensagens consolidadas, que nao usam um template individual,
     * compartilhem o mesmo cabecalho e rodape dos demais emails do tenant.
     */
    public function renderEmailLayout(string $content, array $context, ?string $locale = null): string
    {
        $locale = $locale
            ?? $context['cliente']['preferred_locale']
            ?? $context['empresa']['locale']
            ?? Translator::getInstance()->getLocale();

        $this->renderer->setLocale($locale);
        $context = $this->enrichEmpresaContext($context);

        return $this->wrapInEmailLayout($content, $context);
    }

    private function templateExibeParcela(string $content): bool
    {
        return preg_match('/{{\s*fatura\.(?:parcela|total_parcelas|parcela_descricao)\s*}}/i', $content) === 1;
    }

    private function appendParcela(string $content, string $descricao, string $channel): string
    {
        if ($channel === 'email') {
            return $content
                . '<div style="background:#f8fafc;padding:12px 15px;border-radius:8px;margin:15px 0;">'
                . '<strong>' . htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8') . '</strong>'
                . '</div>';
        }

        return rtrim($content) . "\n\n" . $descricao;
    }

    /**
     * Enriquece $context['empresa'] com dados da matriz do tenant.
     *
     * Mapeia os dados da matriz e seus contatos normalizados para os nomes de
     * variavel usados nos templates (ex.: cpf_cnpj -> cnpj, rua -> endereco).
     * Merge aditivo: so preenche chaves ausentes ou com valor vazio.
     * Pula enrichment para emails do sistema (_system_message).
     */
    private function enrichEmpresaContext(array $context): array
    {
        if (!empty($context['_system_message'])) {
            return $context;
        }

        if ($this->empresaCache === null) {
            try {
                $matriz = (new MatrizFilial())->buscarDadosEmpresaPorChave($this->chave);
            } catch (\Throwable $e) {
                $matriz = null;
            }

            if (!$matriz) {
                $this->empresaCache = [];
                return $context;
            }

            $this->empresaCache = [
                'nome_fantasia' => $matriz['nome_fantasia'] ?? '',
                'razao_social'  => $matriz['razao_social'] ?? '',
                'cnpj'          => $matriz['cpf_cnpj'] ?? '',
                'email'         => $matriz['email'] ?? '',
                'telefone'      => $matriz['telefone'] ?? '',
                'whatsapp'      => $matriz['whatsapp'] ?? '',
                'endereco'      => $matriz['rua'] ?? '',
                'numero'        => $matriz['num'] ?? '',
                'complemento'   => $matriz['compl'] ?? '',
                'bairro'        => $matriz['bairro'] ?? '',
                'cidade'        => $matriz['cidade'] ?? '',
                'uf'            => $matriz['estado'] ?? '',
                'cep'           => $matriz['cep'] ?? '',
                'site'          => $matriz['site'] ?? '',
                'logo_url'      => $this->resolveLogoUrl($matriz['logo'] ?? null),
                'locale'        => $matriz['locale'] ?? 'pt_BR',
            ];
        }

        if ($this->empresaCache === []) {
            return $context;
        }

        $provided = $context['empresa'] ?? [];
        $merged = $provided;
        foreach ($this->empresaCache as $key => $value) {
            if (!array_key_exists($key, $merged) || $merged[$key] === '' || $merged[$key] === null) {
                $merged[$key] = $value;
            }
        }
        $context['empresa'] = $merged;

        return $context;
    }

    /**
     * Envolve o conteudo do email no layout base
     *
     * Usa layout-system.php para mensagens de sistema (7Carros) ou
     * layout.php para mensagens de tenant.
     *
     * @param string $content Conteudo renderizado do template
     * @param array $context Dados para substituicao (empresa, etc)
     * @return string Email completo com layout
     */
    private function wrapInEmailLayout(string $content, array $context): string
    {
        // Selecionar layout: sistema (7Carros) ou tenant
        $layoutFile = !empty($context['_system_message'])
            ? 'layout-system.php'
            : 'layout.php';

        $layoutPath = __DIR__ . '/../Views/emails/' . $layoutFile;

        if (!file_exists($layoutPath)) {
            return $content;
        }

        $layout = file_get_contents($layoutPath);

        $layoutOptions = $this->resolveEmailLayoutOptions($context);
        $layout = str_replace(
            ['%%EMAIL_LAYOUT_WIDTH%%', '%%EMAIL_LAYOUT_MAX_WIDTH%%', '%%EMAIL_LAYOUT_CSS_WIDTH%%'],
            [
                $layoutOptions['width_attribute'],
                $layoutOptions['max_width'],
                $layoutOptions['css_width'],
            ],
            $layout
        );

        // Substituir {{content}} pelo conteudo renderizado
        $layout = str_replace('{{content}}', $content, $layout);
        $layout = str_replace(
            '{{empresa.branding_header}}',
            $this->buildBrandingHeader($context['empresa'] ?? []),
            $layout
        );

        // Renderizar variaveis da empresa no layout
        return $this->renderer->render($layout, $context);
    }

    /**
     * Mantem o layout compacto por padrao e amplia apenas conteudos tabulares
     * que declaram explicitamente o modo wide.
     *
     * @return array{width_attribute: string, max_width: string, css_width: string}
     */
    private function resolveEmailLayoutOptions(array $context): array
    {
        if (($context['_email_layout'] ?? '') === 'wide') {
            return [
                'width_attribute' => '100%',
                'max_width' => '1000px',
                'css_width' => '100%',
            ];
        }

        return [
            'width_attribute' => '600',
            'max_width' => '600px',
            'css_width' => '100%',
        ];
    }

    private function resolveLogoUrl(?string $filename): string
    {
        $filename = trim((string) $filename);
        if ($filename === '' || !FileHelper::exists($filename, $this->chave)) {
            return '';
        }

        try {
            $relativeUrl = FileHelper::url($filename, $this->chave);
            if ($relativeUrl === '') {
                return '';
            }

            $appUrl = rtrim(Database::env('APP_URL', 'https://locadora.7carros.com'), '/');
            return $appUrl . '/' . ltrim($relativeUrl, '/');
        } catch (\Throwable) {
            return '';
        }
    }

    private function buildBrandingHeader(array $empresa): string
    {
        $nome = trim((string) ($empresa['nome_fantasia'] ?? ''));
        $logoUrl = trim((string) ($empresa['logo_url'] ?? ''));
        $nomeEscapado = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
        $logoSegura = filter_var($logoUrl, FILTER_VALIDATE_URL)
            && in_array(strtolower((string) parse_url($logoUrl, PHP_URL_SCHEME)), ['http', 'https'], true);

        if (!$logoSegura) {
            return '<h1 style="margin:0;color:#ffffff;font-size:24px;font-weight:600;">'
                . $nomeEscapado
                . '</h1>';
        }

        $logoEscapada = htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8');

        return '<div style="display:inline-block;background:#ffffff;border-radius:8px;padding:10px 16px;margin-bottom:12px;">'
            . '<img src="' . $logoEscapada . '" alt="' . $nomeEscapado . '" '
            . 'style="display:block;max-width:220px;max-height:90px;width:auto;height:auto;border:0;">'
            . '</div>'
            . '<div style="margin:0;color:#ffffff;font-size:20px;font-weight:600;">'
            . $nomeEscapado
            . '</div>';
    }

    /**
     * Salva ou atualiza um template customizado
     *
     * @param string $typeSlug Slug do tipo
     * @param string $channel Canal
     * @param string $locale Locale
     * @param array $data ['subject' => string, 'content' => string, 'content_plain' => string]
     * @param int|null $userId ID do funcionário que está salvando
     * @return int ID do template salvo
     */
    public function saveTemplate(string $typeSlug, string $channel, string $locale, array $data, ?int $userId = null): int
    {
        $type = $this->getTemplateType($typeSlug);
        if (!$type) {
            throw new \InvalidArgumentException("Tipo de template '{$typeSlug}' não encontrado");
        }

        // Verificar se já existe template customizado
        $existing = $this->db->getRow(
            'message_templates',
            ['id'],
            'chave = ? AND template_type_id = ? AND locale = ? AND channel = ?',
            [$this->chave, $type['id'], $locale, $channel],
            true
        );

        $templateData = [
            'subject' => $data['subject'] ?? null,
            'content' => $data['content'],
            'content_plain' => $data['content_plain'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
        ];

        if ($existing) {
            // Atualizar
            $templateData['updated_at'] = now();
            if ($userId) {
                $templateData['updated_by'] = $userId;
            }

            $this->db->withoutChave()
                ->table('message_templates')
                ->where('id', '=', $existing['id'])
                ->update($templateData);

            return (int) $existing['id'];
        } else {
            // Inserir
            $templateData['chave'] = $this->chave;
            $templateData['template_type_id'] = $type['id'];
            $templateData['locale'] = $locale;
            $templateData['channel'] = $channel;
            $templateData['created_at'] = now();
            if ($userId) {
                $templateData['created_by'] = $userId;
            }

            return $this->db->withoutChave()->table('message_templates')->insert($templateData);
        }
    }

    /**
     * Restaura template para o padrão do sistema
     *
     * @param string $typeSlug Slug do tipo
     * @param string $channel Canal
     * @param string $locale Locale
     * @return bool Se foi deletado com sucesso
     */
    public function restoreDefault(string $typeSlug, string $channel, string $locale): bool
    {
        $type = $this->getTemplateType($typeSlug);
        if (!$type) {
            return false;
        }

        return $this->db->withoutChave()
            ->table('message_templates')
            ->whereRaw('chave = ? AND template_type_id = ? AND locale = ? AND channel = ?', [$this->chave, $type['id'], $locale, $channel])
            ->delete() > 0;
    }

    /**
     * Lista todos os tipos de template
     *
     * @param string|null $category Filtrar por categoria
     * @return array Lista de tipos
     */
    public function getTemplateTypes(?string $category = null): array
    {
        $where = 'is_active = 1';
        $params = [];

        if ($category) {
            $where .= ' AND category = ?';
            $params[] = $category;
        }

        $types = $this->db->table('message_template_types')
            ->withoutChave()
            ->whereRaw($where, $params)
            ->orderBy('sort_order')
            ->get();

        // Decodificar JSON e traduzir nomes
        $translator = Translator::getInstance();
        foreach ($types as &$type) {
            $type['channels'] = json_decode($type['channels'], true) ?? [];
            $type['available_variables'] = json_decode($type['available_variables'], true) ?? [];
            $type['name'] = $translator->get($type['name_key']);
            $type['description'] = $type['description_key']
                ? $translator->get($type['description_key'])
                : null;
        }

        return $types;
    }

    /**
     * Busca um tipo de template pelo slug
     */
    public function getTemplateType(string $slug): ?array
    {
        $type = $this->db->getRow(
            'message_template_types',
            ['*'],
            'slug = ? AND is_active = 1',
            [$slug],
            true
        );

        if ($type) {
            $type['channels'] = json_decode($type['channels'], true) ?? [];
            $type['available_variables'] = json_decode($type['available_variables'], true) ?? [];

            // Traduzir nome e descrição
            $translator = Translator::getInstance();
            $type['name'] = $translator->get($type['name_key']);
            $type['description'] = $type['description_key']
                ? $translator->get($type['description_key'])
                : null;
        }

        return $type;
    }

    /**
     * Lista templates da empresa para um tipo específico
     *
     * @param string $typeSlug Slug do tipo
     * @return array Templates por locale e canal
     */
    public function getTemplatesForType(string $typeSlug): array
    {
        $type = $this->getTemplateType($typeSlug);
        if (!$type) {
            return [];
        }

        $locales = array_keys(Translator::getInstance()->getSupportedLocales());
        $result = [];

        foreach ($type['channels'] as $channel) {
            foreach ($locales as $locale) {
                $template = $this->getTemplate($typeSlug, $channel, $locale);
                if ($template) {
                    $result[$channel][$locale] = $template;
                }
            }
        }

        return $result;
    }

    /**
     * Valida um template antes de salvar
     *
     * @param string $typeSlug Slug do tipo
     * @param string $content Conteúdo do template
     * @return array Erros de validação (vazio se válido)
     */
    public function validateTemplate(string $typeSlug, string $content): array
    {
        $type = $this->getTemplateType($typeSlug);
        if (!$type) {
            return [['error' => 'type_not_found', 'message' => 'Tipo de template não encontrado']];
        }

        return $this->renderer->validateVariables($content, $type['available_variables']);
    }

    /**
     * Retorna preview de um template
     *
     * @param string $content Conteúdo do template
     * @return string HTML com variáveis destacadas
     */
    public function preview(string $content): string
    {
        return $this->renderer->preview($content);
    }

    /**
     * Retorna variáveis disponíveis para um tipo de template
     *
     * @param string $typeSlug Slug do tipo
     * @param string|null $locale Locale para labels
     * @return array Variáveis organizadas por entidade
     */
    public function getAvailableVariables(string $typeSlug, ?string $locale = null): array
    {
        $type = $this->getTemplateType($typeSlug);
        if (!$type) {
            return [];
        }

        $locale = $locale ?? Translator::getInstance()->getLocale();
        $allVars = TemplateVariables::getForFrontend($locale);

        // Filtrar apenas entidades permitidas
        $result = [];
        foreach ($type['available_variables'] as $entity) {
            if (isset($allVars[$entity])) {
                $result[$entity] = $allVars[$entity];
            }
        }

        return $result;
    }

    /**
     * Salva um template padrão do sistema
     * (Usar apenas em seeders/migrations)
     *
     * @param int $typeId ID do tipo
     * @param string $locale Locale
     * @param string $channel Canal
     * @param array $data Dados do template
     */
    public function saveDefaultTemplate(int $typeId, string $locale, string $channel, array $data): int
    {
        // Verificar se já existe template padrão (chave = '0')
        $existing = $this->db->getRow(
            'message_templates',
            ['id'],
            'chave = ? AND template_type_id = ? AND locale = ? AND channel = ?',
            ['0', $typeId, $locale, $channel],
            true
        );

        $templateData = [
            'template_type_id' => $typeId,
            'locale' => $locale,
            'channel' => $channel,
            'subject' => $data['subject'] ?? null,
            'content' => $data['content'],
            'content_plain' => $data['content_plain'] ?? null,
            'is_active' => 1,
        ];

        if ($existing) {
            $templateData['updated_at'] = now();
            $this->db->withoutChave()
                ->table('message_templates')
                ->where('id', '=', $existing['id'])
                ->update($templateData);
            return (int) $existing['id'];
        } else {
            $templateData['chave'] = '0'; // Template padrão do sistema
            return $this->db->withoutChave()->table('message_templates')->insert($templateData);
        }
    }

    /**
     * Define a chave do tenant
     */
    public function setChave(string $chave): self
    {
        $this->chave = $chave;
        return $this;
    }
}
