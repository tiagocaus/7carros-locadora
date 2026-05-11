<?php

declare(strict_types=1);

namespace App\I18n;

/**
 * Classe principal de tradução do sistema
 *
 * Implementa um sistema de internacionalização (i18n) que:
 * - Carrega arquivos de tradução organizados por locale
 * - Suporta fallback para locale padrão (pt_BR)
 * - Permite substituição de variáveis nas traduções
 * - Usa padrão Singleton para eficiência
 *
 * @example
 * // Uso básico
 * $translator = Translator::getInstance();
 * echo $translator->get('common.buttons.save'); // "Salvar"
 *
 * // Com variáveis
 * echo $translator->get('messages.welcome', ['nome' => 'João']); // "Bem-vindo, João!"
 *
 * // Usando helper global t()
 * echo t('common.buttons.save');
 * echo t('messages.welcome', ['nome' => 'João']);
 */
class Translator
{
    private static ?Translator $instance = null;

    /**
     * Locale atual do sistema
     */
    private string $locale;

    /**
     * Locale de fallback quando tradução não existe
     */
    private string $fallbackLocale = 'pt_BR';

    /**
     * Cache de traduções carregadas
     * Estrutura: ['locale' => ['arquivo' => ['chave' => 'valor']]]
     */
    private array $translations = [];

    /**
     * Caminho base para arquivos de tradução
     */
    private string $langPath;

    /**
     * Locales suportados pelo sistema
     */
    private const SUPPORTED_LOCALES = [
        'pt_BR' => [
            'name' => 'Português (Brasil)',
            'flag' => '🇧🇷',
            'code' => 'pt-BR',
        ],
        'pt_PT' => [
            'name' => 'Português (Portugal)',
            'flag' => '🇵🇹',
            'code' => 'pt-PT',
        ],
        'en_US' => [
            'name' => 'English (US)',
            'flag' => '🇺🇸',
            'code' => 'en-US',
        ],
        'es_ES' => [
            'name' => 'Español',
            'flag' => '🇪🇸',
            'code' => 'es-ES',
        ],
        'it_IT' => [
            'name' => 'Italiano',
            'flag' => '🇮🇹',
            'code' => 'it-IT',
        ],
    ];

    /**
     * Construtor privado (Singleton)
     */
    private function __construct()
    {
        $this->langPath = dirname(__DIR__) . '/Lang/';
        $this->locale = $this->determineLocale();
        $this->loadTranslations($this->locale);
    }

    /**
     * Previne clonagem (Singleton)
     */
    private function __clone() {}

    /**
     * Previne deserialização (Singleton)
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }

    /**
     * Obtém instância única do Translator
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Traduz uma chave de tradução
     *
     * @param string $key Chave no formato 'arquivo.chave' ou 'arquivo.chave.subchave'
     * @param array $replace Variáveis para substituição no formato [:nome => valor]
     * @param string|null $locale Locale específico (opcional, usa atual se não informado)
     * @return string Texto traduzido ou a própria chave se não encontrado
     *
     * @example
     * $translator->get('common.buttons.save'); // "Salvar"
     * $translator->get('messages.greeting', ['nome' => 'João']); // "Olá, João!"
     * $translator->get('common.yes', [], 'en_US'); // "Yes"
     */
    public function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $this->normalizeLocale($locale ?? $this->locale);

        // Garantir que traduções do locale estão carregadas
        if (!isset($this->translations[$locale])) {
            $this->loadTranslations($locale);
        }

        $translation = $this->findTranslation($key, $locale);

        // Fallback para locale padrão se tradução não encontrada
        if ($translation === null && $locale !== $this->fallbackLocale) {
            if (!isset($this->translations[$this->fallbackLocale])) {
                $this->loadTranslations($this->fallbackLocale);
            }
            $translation = $this->findTranslation($key, $this->fallbackLocale);
        }

        // Retorna a chave se tradução não encontrada
        if ($translation === null) {
            return $key;
        }

        // Substituir variáveis no formato :nome
        return $this->replaceVariables($translation, $replace);
    }

    /**
     * Alias para get()
     */
    public function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        return $this->get($key, $replace, $locale);
    }

    /**
     * Verifica se uma tradução existe
     */
    public function has(string $key, ?string $locale = null): bool
    {
        $locale = $this->normalizeLocale($locale ?? $this->locale);

        if (!isset($this->translations[$locale])) {
            $this->loadTranslations($locale);
        }

        return $this->findTranslation($key, $locale) !== null;
    }

    /**
     * Obtém todas as traduções de um arquivo
     */
    public function getFile(string $file, ?string $locale = null): array
    {
        $locale = $this->normalizeLocale($locale ?? $this->locale);

        if (!isset($this->translations[$locale])) {
            $this->loadTranslations($locale);
        }

        return $this->translations[$locale][$file] ?? [];
    }

    /**
     * Define o locale atual
     */
    public function setLocale(string $locale): void
    {
        $locale = $this->normalizeLocale($locale);

        if (!$this->isSupported($locale)) {
            throw new \InvalidArgumentException("Locale '{$locale}' não é suportado");
        }

        $this->locale = $locale;

        // Carregar traduções se ainda não carregadas
        if (!isset($this->translations[$locale])) {
            $this->loadTranslations($locale);
        }

        // Persistir na sessão
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['ui_locale'] = $locale;
        }
    }

    /**
     * Obtém o locale atual
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Obtém o locale de fallback
     */
    public function getFallbackLocale(): string
    {
        return $this->fallbackLocale;
    }

    /**
     * Obtém lista de locales suportados
     */
    public function getSupportedLocales(): array
    {
        return self::SUPPORTED_LOCALES;
    }

    /**
     * Verifica se um locale é suportado
     */
    public function isSupported(string $locale): bool
    {
        $locale = $this->normalizeLocale($locale);
        return isset(self::SUPPORTED_LOCALES[$locale]);
    }

    /**
     * Obtém informações de um locale
     */
    public function getLocaleInfo(?string $locale = null): ?array
    {
        $locale = $this->normalizeLocale($locale ?? $this->locale);
        return self::SUPPORTED_LOCALES[$locale] ?? null;
    }

    /**
     * Limpa cache de traduções (útil para testes)
     */
    public function clearCache(): void
    {
        $this->translations = [];
    }

    /**
     * Recarrega traduções do locale atual
     */
    public function reload(): void
    {
        unset($this->translations[$this->locale]);
        $this->loadTranslations($this->locale);
    }

    /**
     * Determina o locale baseado em várias fontes
     */
    private function determineLocale(): string
    {
        // 1. Verificar sessão do usuário (preferência individual)
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (!empty($_SESSION['ui_locale'])) {
                $locale = $this->normalizeLocale($_SESSION['ui_locale']);
                if ($this->isSupported($locale)) {
                    return $locale;
                }
            }

            // 2. Verificar preferência do funcionário logado
            if (!empty($_SESSION['user_locale'])) {
                $locale = $this->normalizeLocale($_SESSION['user_locale']);
                if ($this->isSupported($locale)) {
                    return $locale;
                }
            }

            // 3. Verificar locale da empresa
            if (!empty($_SESSION['empresa_ui_locale'])) {
                $locale = $this->normalizeLocale($_SESSION['empresa_ui_locale']);
                if ($this->isSupported($locale)) {
                    return $locale;
                }
            }
        }

        // 4. Verificar header Accept-Language do navegador
        $browserLocale = $this->detectBrowserLocale();
        if ($browserLocale !== null) {
            return $browserLocale;
        }

        // 5. Fallback
        return $this->fallbackLocale;
    }

    /**
     * Detecta locale do navegador via Accept-Language
     */
    private function detectBrowserLocale(): ?string
    {
        if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return null;
        }

        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'];

        // Mapear códigos de idioma para locales suportados
        $browserToLocale = [
            'pt-BR' => 'pt_BR',
            'pt' => 'pt_BR',
            'en-US' => 'en_US',
            'en' => 'en_US',
            'es' => 'es_ES',
            'es-ES' => 'es_ES',
            'it' => 'it_IT',
            'it-IT' => 'it_IT',
        ];

        // Extrair idiomas do header
        preg_match_all('/([a-z]{2}(?:-[A-Z]{2})?)/i', $acceptLanguage, $matches);

        foreach ($matches[1] as $lang) {
            $normalizedLang = str_replace('_', '-', $lang);
            if (isset($browserToLocale[$normalizedLang])) {
                return $browserToLocale[$normalizedLang];
            }

            // Tentar apenas o código do idioma (ex: "pt" de "pt-BR")
            $shortLang = substr($lang, 0, 2);
            if (isset($browserToLocale[$shortLang])) {
                return $browserToLocale[$shortLang];
            }
        }

        return null;
    }

    /**
     * Normaliza formato de locale (pt-BR -> pt_BR)
     */
    private function normalizeLocale(string $locale): string
    {
        return str_replace('-', '_', $locale);
    }

    /**
     * Carrega todos os arquivos de tradução de um locale
     */
    private function loadTranslations(string $locale): void
    {
        $locale = $this->normalizeLocale($locale);
        $localePath = $this->langPath . $locale . '/';

        // Se diretório não existe, tentar fallback
        if (!is_dir($localePath)) {
            $localePath = $this->langPath . $this->fallbackLocale . '/';
            if (!is_dir($localePath)) {
                $this->translations[$locale] = [];
                return;
            }
        }

        $this->translations[$locale] = [];

        // Carregar arquivos na raiz do locale
        $this->loadFilesFromDirectory($localePath, $locale, '');

        // Carregar subpastas (ex: modules/)
        $this->loadSubdirectories($localePath, $locale);
    }

    /**
     * Carrega arquivos PHP de um diretório
     */
    private function loadFilesFromDirectory(string $path, string $locale, string $prefix): void
    {
        $files = glob($path . '*.php');

        foreach ($files as $file) {
            $filename = basename($file, '.php');
            $key = $prefix ? "{$prefix}.{$filename}" : $filename;

            $content = require $file;

            if (is_array($content)) {
                $this->translations[$locale][$key] = $content;
            }
        }
    }

    /**
     * Carrega subdiretórios recursivamente
     */
    private function loadSubdirectories(string $basePath, string $locale): void
    {
        $dirs = glob($basePath . '*', GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            $dirName = basename($dir);
            $this->loadFilesFromDirectory($dir . '/', $locale, $dirName);
        }
    }

    /**
     * Busca uma tradução por chave
     *
     * Suporta arquivos em subdiretórios:
     * - 'common.buttons.save' -> arquivo 'common', chave 'buttons.save'
     * - 'modules.clientes.title' -> arquivo 'modules.clientes', chave 'title'
     */
    private function findTranslation(string $key, string $locale): ?string
    {
        $parts = explode('.', $key);

        if (count($parts) < 2) {
            return null;
        }

        // Tentar encontrar o arquivo correspondente
        // Primeiro tenta arquivo simples, depois arquivo com subdiretório
        $file = null;
        $value = null;

        // Estratégia 1: Arquivo simples (ex: 'common')
        $simpleFile = $parts[0];
        if (isset($this->translations[$locale][$simpleFile])) {
            $file = $simpleFile;
            $value = $this->translations[$locale][$file];
            array_shift($parts);
        }
        // Estratégia 2: Arquivo em subdiretório (ex: 'modules.clientes')
        elseif (count($parts) >= 2) {
            $subdirFile = $parts[0] . '.' . $parts[1];
            if (isset($this->translations[$locale][$subdirFile])) {
                $file = $subdirFile;
                $value = $this->translations[$locale][$file];
                array_shift($parts); // remove 'modules'
                array_shift($parts); // remove 'clientes'
            }
        }

        // Se não encontrou nenhum arquivo, retorna null
        if ($file === null || $value === null) {
            return null;
        }

        // Navegar pela estrutura aninhada
        foreach ($parts as $part) {
            if (!is_array($value) || !isset($value[$part])) {
                return null;
            }
            $value = $value[$part];
        }

        return is_string($value) ? $value : null;
    }

    /**
     * Substitui variáveis no texto traduzido
     */
    private function replaceVariables(string $text, array $replace): string
    {
        if (empty($replace)) {
            return $text;
        }

        foreach ($replace as $key => $value) {
            // Suporta formato :nome
            $text = str_replace(":{$key}", (string) $value, $text);
        }

        return $text;
    }
}
