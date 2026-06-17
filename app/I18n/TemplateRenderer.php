<?php

declare(strict_types=1);

namespace App\I18n;

/**
 * Renderiza templates substituindo variáveis no formato {{entidade.campo}}
 *
 * Suporta:
 * - Variáveis simples: {{cliente.nome}}
 * - Variáveis computadas: {{cliente.endereco_completo}}
 * - Variáveis com subníveis: {{contrato.valor.parcela}}
 * - Formatação automática por tipo (currency, date, phone, document)
 * - Fallback para locale padrão
 *
 * @example
 * $renderer = new TemplateRenderer();
 * $context = [
 *     'cliente' => ['nome' => 'João', 'email' => 'joao@email.com'],
 *     'empresa' => ['nome_fantasia' => 'ABC Locadora']
 * ];
 * $html = $renderer->render($template, $context);
 */
class TemplateRenderer
{
    /**
     * Padrão regex para encontrar variáveis {{entidade.campo}} e {{entidade.campo.subcampo}}
     */
    private const VARIABLE_PATTERN = '/\{\{([a-z_]+)\.([a-z_]+(?:\.[a-z_]+)*)\}\}/i';

    /**
     * Locale atual para formatação
     */
    private string $locale;

    /**
     * Se deve mostrar variáveis não resolvidas
     */
    private bool $showUnresolved = false;

    /**
     * Placeholder para variáveis não resolvidas
     */
    private string $unresolvedPlaceholder = '';

    public function __construct(?string $locale = null)
    {
        $this->locale = $locale ?? Translator::getInstance()->getLocale();
    }

    /**
     * Renderiza um template substituindo variáveis
     *
     * @param string $template Template com variáveis {{entidade.campo}}
     * @param array $context Dados para substituição
     * @return string Template renderizado
     */
    public function render(string $template, array $context): string
    {
        return preg_replace_callback(
            self::VARIABLE_PATTERN,
            function ($matches) use ($context) {
                $entity = $matches[1];
                $field = $matches[2];
                $variable = "{$entity}.{$field}";

                $value = TemplateVariables::resolve($variable, $context, $this->locale);

                if ($value !== null) {
                    return $value;
                }

                // Variável não resolvida
                if ($this->showUnresolved) {
                    return $matches[0]; // Mantém a variável original
                }

                return $this->unresolvedPlaceholder;
            },
            $template
        );
    }

    /**
     * Renderiza template para um locale específico
     *
     * @param string $template Template com variáveis
     * @param array $context Dados para substituição
     * @param string $locale Locale para formatação
     * @return string Template renderizado
     */
    public function renderForLocale(string $template, array $context, string $locale): string
    {
        $originalLocale = $this->locale;
        $this->locale = $locale;

        $result = $this->render($template, $context);

        $this->locale = $originalLocale;
        return $result;
    }

    /**
     * Extrai todas as variáveis de um template
     *
     * @param string $template Template para analisar
     * @return array Lista de variáveis encontradas ['cliente.nome', 'empresa.cnpj', ...]
     */
    public function extractVariables(string $template): array
    {
        preg_match_all(self::VARIABLE_PATTERN, $template, $matches);

        $variables = [];
        foreach ($matches[1] as $i => $entity) {
            $field = $matches[2][$i];
            $variables[] = "{$entity}.{$field}";
        }

        return array_unique($variables);
    }

    /**
     * Valida variáveis de um template
     *
     * @param string $template Template para validar
     * @param array $availableEntities Entidades disponíveis ['cliente', 'empresa', ...]
     * @return array Erros encontrados
     */
    public function validateVariables(string $template, array $availableEntities = []): array
    {
        $variables = $this->extractVariables($template);
        $errors = [];

        foreach ($variables as $variable) {
            $parts = explode('.', $variable, 2);
            $entity = $parts[0];
            $field = $parts[1];

            // Verificar se a entidade é permitida
            if ($availableEntities && !in_array($entity, $availableEntities, true)) {
                $errors[] = [
                    'variable' => $variable,
                    'error' => 'entity_not_allowed',
                    'message' => t('validation.template.entity_not_allowed', ['entity' => $entity]),
                ];
                continue;
            }

            // Verificar se a variável existe
            if (!TemplateVariables::exists($variable)) {
                $errors[] = [
                    'variable' => $variable,
                    'error' => 'variable_not_found',
                    'message' => t('validation.template.variable_not_found', ['variable' => $variable]),
                ];
            }
        }

        return $errors;
    }

    /**
     * Retorna preview do template com valores de exemplo
     *
     * @param string $template Template para preview
     * @return string Template com valores de exemplo
     */
    public function preview(string $template): string
    {
        return preg_replace_callback(
            self::VARIABLE_PATTERN,
            function ($matches) {
                $entity = $matches[1];
                $field = $matches[2];
                $variable = "{$entity}.{$field}";

                $info = TemplateVariables::getInfo($variable);

                if ($info && isset($info['example'])) {
                    // Não escapar se o tipo for 'html' (tabelas, assinaturas, etc.)
                    if (($info['type'] ?? '') === 'html') {
                        return '<span class="template-preview-var" title="' . htmlspecialchars($variable) . '">'
                            . $info['example']
                            . '</span>';
                    }

                    return '<span class="template-preview-var" title="' . htmlspecialchars($variable) . '">'
                        . htmlspecialchars($info['example'])
                        . '</span>';
                }

                return '<span class="template-preview-var template-preview-unknown" title="Variável não encontrada">'
                    . htmlspecialchars($matches[0])
                    . '</span>';
            },
            $template
        );
    }

    /**
     * Converte template legado para novo formato
     *
     * @param string $template Template com variáveis $nome
     * @return string Template com variáveis {{entidade.campo}}
     */
    public function convertLegacy(string $template): string
    {
        $mapping = TemplateVariables::getLegacyMapping();

        foreach ($mapping as $legacy => $new) {
            // Escapar $ para regex
            $pattern = '/' . preg_quote($legacy, '/') . '/';
            $template = preg_replace($pattern, '{{' . $new . '}}', $template);
        }

        return $template;
    }

    /**
     * Remove formatação de destaque (tarja amarela) de documentos legados
     *
     * @param string $template Template HTML com spans de destaque
     * @return string Template limpo
     */
    public function cleanLegacyFormatting(string $template): string
    {
        // Remove spans com background-color amarelo ao redor de variáveis
        $pattern = '/<span[^>]*style="[^"]*background-color:\s*(yellow|#ff0|#ffff00|rgb\(255,\s*255,\s*0\))[^"]*"[^>]*>(.*?)<\/span>/is';

        return preg_replace($pattern, '$2', $template);
    }

    /**
     * Processa template completamente: limpa, converte e valida
     *
     * @param string $legacyTemplate Template legado
     * @return array ['template' => string, 'variables' => array, 'errors' => array]
     */
    public function processLegacyTemplate(string $legacyTemplate): array
    {
        // 1. Limpar formatação
        $template = $this->cleanLegacyFormatting($legacyTemplate);

        // 2. Converter variáveis
        $template = $this->convertLegacy($template);

        // 3. Extrair variáveis
        $variables = $this->extractVariables($template);

        // 4. Validar
        $errors = $this->validateVariables($template);

        return [
            'template' => $template,
            'variables' => $variables,
            'errors' => $errors,
        ];
    }

    /**
     * Gera versão texto puro de um template HTML
     *
     * Útil para SMS ou versões plaintext de email
     *
     * @param string $htmlTemplate Template HTML
     * @return string Versão texto puro
     */
    public function toPlainText(string $htmlTemplate): string
    {
        // Remover scripts e styles
        $text = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $htmlTemplate);

        // Converter <br> e </p> em quebras de linha
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/p>/i', "\n\n", $text);
        $text = preg_replace('/<\/div>/i', "\n", $text);
        $text = preg_replace('/<\/li>/i', "\n", $text);

        // Converter links para texto + URL
        $text = preg_replace('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>([^<]+)<\/a>/i', '$2 ($1)', $text);

        // Remover todas as outras tags
        $text = strip_tags($text);

        // Decodificar entidades HTML
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Limpar espaços extras
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n\s*\n/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Define se deve mostrar variáveis não resolvidas
     */
    public function showUnresolved(bool $show = true): self
    {
        $this->showUnresolved = $show;
        return $this;
    }

    /**
     * Define placeholder para variáveis não resolvidas
     */
    public function setUnresolvedPlaceholder(string $placeholder): self
    {
        $this->unresolvedPlaceholder = $placeholder;
        return $this;
    }

    /**
     * Define o locale para formatação
     */
    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * Retorna o locale atual
     */
    public function getLocale(): string
    {
        return $this->locale;
    }
}
