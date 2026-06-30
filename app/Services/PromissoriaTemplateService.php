<?php

declare(strict_types=1);

namespace App\Services;

use App\Classes\QueryBuilder;
use App\I18n\TemplateRenderer;
use App\I18n\TemplateVariables;
use App\I18n\Translator;

/**
 * Service para gerenciamento de templates de promissoria
 *
 * Responsabilidades:
 * - Buscar templates (customizados ou padrao)
 * - Salvar templates customizados
 * - Renderizar templates com contexto
 * - Gerenciar tipos de template
 *
 * @example
 * $service = new PromissoriaTemplateService($mysqli, 'chave_tenant');
 * $template = $service->getTemplate('promissoria_texto_quitada', 'pt_BR');
 * $rendered = $service->render('promissoria_texto_quitada', $context);
 */
class PromissoriaTemplateService
{
    private QueryBuilder $db;
    private string $chave;
    private TemplateRenderer $renderer;

    public function __construct(\mysqli $mysqli, string $chave)
    {
        $this->db = new QueryBuilder($mysqli);
        $this->db->withoutChave(); // Templates usam chave manualmente
        $this->chave = $chave;
        $this->renderer = new TemplateRenderer();
    }

    /**
     * Busca um template pelo tipo e locale
     *
     * Prioridade:
     * 1. Template customizado da empresa (chave)
     * 2. Template padrao do sistema (chave = '0')
     * 3. Fallback para pt_BR
     *
     * @param string $typeSlug Slug do tipo (ex: 'promissoria_texto_quitada')
     * @param string|null $locale Locale (null = locale atual)
     * @return array|null Template encontrado ou null
     */
    public function getTemplate(string $typeSlug, ?string $locale = null): ?array
    {
        $locale = $locale ?? Translator::getInstance()->getLocale();

        // 1. Buscar tipo de template
        $type = $this->getTemplateType($typeSlug);
        if (!$type) {
            return null;
        }

        // 2. Buscar template customizado da empresa
        $custom = $this->db->getRow(
            'promissoria_templates',
            ['*'],
            'chave = ? AND template_type_id = ? AND locale = ? AND is_active = 1',
            [$this->chave, $type['id'], $locale],
            true
        );

        if ($custom) {
            $custom['is_custom'] = true;
            $custom['type'] = $type;
            return $custom;
        }

        // 3. Buscar template padrao do sistema (chave = '0')
        $default = $this->db->getRow(
            'promissoria_templates',
            ['*'],
            'chave = ? AND template_type_id = ? AND locale = ? AND is_active = 1',
            ['0', $type['id'], $locale],
            true
        );

        if ($default) {
            $default['is_custom'] = false;
            $default['type'] = $type;
            return $default;
        }

        // 4. Fallback para locale padrao (pt_BR)
        if ($locale !== 'pt_BR') {
            return $this->getTemplate($typeSlug, 'pt_BR');
        }

        return null;
    }

    /**
     * Renderiza um template com contexto
     *
     * @param string $typeSlug Slug do tipo
     * @param array $context Dados para substituicao
     * @param string|null $locale Locale (null = locale da empresa ou sessao)
     * @return string|null Template renderizado ou null se nao encontrado
     */
    public function render(string $typeSlug, array $context, ?string $locale = null): ?string
    {
        // Determinar locale
        if ($locale === null) {
            $locale = $context['empresa']['locale']
                ?? Translator::getInstance()->getLocale();
        }

        $template = $this->getTemplate($typeSlug, $locale);
        if (!$template) {
            return null;
        }

        $this->renderer->setLocale($locale);

        // Renderizar o conteudo do template
        return $this->renderer->render($template['content'], $context);
    }

    /**
     * Salva ou atualiza um template customizado
     *
     * @param string $typeSlug Slug do tipo
     * @param string $locale Locale
     * @param string $content Conteudo do template
     * @param int|null $userId ID do funcionario que esta salvando
     * @return int ID do template salvo
     */
    public function saveTemplate(string $typeSlug, string $locale, string $content, ?int $userId = null): int
    {
        $type = $this->getTemplateType($typeSlug);
        if (!$type) {
            throw new \InvalidArgumentException("Tipo de template '{$typeSlug}' nao encontrado");
        }

        // Verificar se ja existe template customizado
        $existing = $this->db->getRow(
            'promissoria_templates',
            ['id'],
            'chave = ? AND template_type_id = ? AND locale = ?',
            [$this->chave, $type['id'], $locale],
            true
        );

        $templateData = [
            'content' => $content,
            'is_active' => 1,
        ];

        if ($existing) {
            // Atualizar
            $templateData['updated_at'] = now();
            if ($userId) {
                $templateData['updated_by'] = $userId;
            }

            $this->db->withoutChave()
                ->table('promissoria_templates')
                ->where('id', '=', $existing['id'])
                ->update($templateData);

            return (int) $existing['id'];
        } else {
            // Inserir
            $templateData['chave'] = $this->chave;
            $templateData['template_type_id'] = $type['id'];
            $templateData['locale'] = $locale;
            $templateData['created_at'] = now();
            if ($userId) {
                $templateData['created_by'] = $userId;
            }

            return $this->db->withoutChave()->table('promissoria_templates')->insert($templateData);
        }
    }

    /**
     * Restaura template para o padrao do sistema
     *
     * @param string $typeSlug Slug do tipo
     * @param string $locale Locale
     * @return bool Se foi deletado com sucesso
     */
    public function restoreDefault(string $typeSlug, string $locale): bool
    {
        $type = $this->getTemplateType($typeSlug);
        if (!$type) {
            return false;
        }

        return $this->db->withoutChave()
            ->table('promissoria_templates')
            ->whereRaw('chave = ? AND template_type_id = ? AND locale = ?', [$this->chave, $type['id'], $locale])
            ->delete() > 0;
    }

    /**
     * Lista todos os tipos de template
     *
     * @param string|null $category Filtrar por categoria ('promissoria' ou 'parcela')
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

        $types = $this->db->table('promissoria_template_types')
            ->withoutChave()
            ->whereRaw($where, $params)
            ->orderBy('sort_order')
            ->get();

        // Decodificar JSON e traduzir nomes
        $translator = Translator::getInstance();
        foreach ($types as &$type) {
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
            'promissoria_template_types',
            ['*'],
            'slug = ? AND is_active = 1',
            [$slug],
            true
        );

        if ($type) {
            $type['available_variables'] = json_decode($type['available_variables'], true) ?? [];
        }

        return $type;
    }

    /**
     * Lista templates da empresa para todos os tipos
     *
     * @return array Templates por tipo e locale
     */
    public function getAllTemplates(): array
    {
        $types = $this->getTemplateTypes();
        $locales = array_keys(Translator::getInstance()->getSupportedLocales());
        $result = [];

        foreach ($types as $type) {
            foreach ($locales as $locale) {
                $template = $this->getTemplate($type['slug'], $locale);
                if ($template) {
                    $result[$type['slug']][$locale] = $template;
                }
            }
        }

        return $result;
    }

    /**
     * Retorna templates para um tipo especifico
     *
     * @param string $typeSlug Slug do tipo
     * @return array Templates por locale
     */
    public function getTemplatesForType(string $typeSlug): array
    {
        $locales = array_keys(Translator::getInstance()->getSupportedLocales());
        $result = [];

        foreach ($locales as $locale) {
            $template = $this->getTemplate($typeSlug, $locale);
            if ($template) {
                $result[$locale] = $template;
            }
        }

        return $result;
    }

    /**
     * Valida um template antes de salvar
     *
     * @param string $typeSlug Slug do tipo
     * @param string $content Conteudo do template
     * @return array Erros de validacao (vazio se valido)
     */
    public function validateTemplate(string $typeSlug, string $content): array
    {
        $type = $this->getTemplateType($typeSlug);
        if (!$type) {
            return [['error' => 'type_not_found', 'message' => 'Tipo de template nao encontrado']];
        }

        return $this->renderer->validateVariables($content, $type['available_variables']);
    }

    /**
     * Retorna preview de um template
     *
     * @param string $content Conteudo do template
     * @return string HTML com variaveis destacadas
     */
    public function preview(string $content): string
    {
        return $this->renderer->preview($content);
    }

    /**
     * Retorna variaveis disponiveis para um tipo de template
     *
     * @param string $typeSlug Slug do tipo
     * @param string|null $locale Locale para labels
     * @return array Variaveis organizadas por entidade
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
     * Verifica se o tenant tem template customizado
     *
     * @param string $typeSlug Slug do tipo
     * @param string $locale Locale
     * @return bool
     */
    public function isCustomized(string $typeSlug, string $locale): bool
    {
        $type = $this->getTemplateType($typeSlug);
        if (!$type) {
            return false;
        }

        $existing = $this->db->getRow(
            'promissoria_templates',
            ['id'],
            'chave = ? AND template_type_id = ? AND locale = ? AND is_active = 1',
            [$this->chave, $type['id'], $locale],
            true
        );

        return $existing !== null;
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
