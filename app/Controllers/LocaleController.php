<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\I18n\Translator;
use App\Models\Funcionario;

/**
 * Controller para gerenciamento de idioma (locale)
 *
 * Endpoints:
 * - POST /api/locale/set - Define o idioma da interface
 * - GET /api/locale/current - Retorna o idioma atual
 * - GET /api/locale/supported - Lista idiomas suportados
 */
class LocaleController
{
    private Translator $translator;

    public function __construct()
    {
        $this->translator = Translator::getInstance();
    }

    /**
     * Define o idioma da interface do usuário
     *
     * POST /api/locale/set
     * Body: { "locale": "en_US" }
     *
     * O idioma é salvo:
     * 1. Na sessão (efeito imediato)
     * 2. No banco de dados (persistente) - se usuário estiver logado
     */
    public function set(): void
    {
        $data = $this->getJsonInput();

        if (empty($data['locale'])) {
            Response::json([
                'success' => false,
                'message' => t('validation.required', ['attribute' => 'locale']),
            ], 400);
            return;
        }

        $locale = $this->normalizeLocale($data['locale']);

        // Validar se o locale é suportado
        if (!$this->translator->isSupported($locale)) {
            Response::json([
                'success' => false,
                'message' => t('messages.error.generic'),
                'error' => "Locale '{$locale}' não é suportado.",
                'supported' => array_keys($this->translator->getSupportedLocales()),
            ], 400);
            return;
        }

        // 1. Definir na sessão (efeito imediato)
        $this->translator->setLocale($locale);

        // 2. Persistir no banco se usuário estiver logado
        $this->persistLocale($locale);

        // Retornar informações do novo locale
        $localeInfo = $this->translator->getLocaleInfo($locale);

        Response::json([
            'success' => true,
            'message' => t('messages.success.settings_saved'),
            'locale' => $locale,
            'info' => $localeInfo,
        ]);
    }

    /**
     * Retorna o idioma atual da interface
     *
     * GET /api/locale/current
     */
    public function current(): void
    {
        $locale = $this->translator->getLocale();
        $localeInfo = $this->translator->getLocaleInfo($locale);

        Response::json([
            'success' => true,
            'locale' => $locale,
            'info' => $localeInfo,
        ]);
    }

    /**
     * Lista todos os idiomas suportados
     *
     * GET /api/locale/supported
     */
    public function supported(): void
    {
        $locales = $this->translator->getSupportedLocales();
        $currentLocale = $this->translator->getLocale();

        // Formatar para o frontend
        $formatted = [];
        foreach ($locales as $code => $info) {
            $formatted[] = [
                'code' => $code,
                'name' => $info['name'],
                'flag' => $info['flag'],
                'htmlCode' => $info['code'], // pt-BR format for HTML lang attribute
                'isCurrent' => $code === $currentLocale,
            ];
        }

        Response::json([
            'success' => true,
            'current' => $currentLocale,
            'locales' => $formatted,
        ]);
    }

    /**
     * Obtém dados JSON do body da requisição
     */
    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Normaliza o formato do locale (pt-BR -> pt_BR)
     */
    private function normalizeLocale(string $locale): string
    {
        return str_replace('-', '_', trim($locale));
    }

    /**
     * Persiste o locale no banco de dados
     */
    private function persistLocale(string $locale): void
    {
        $funcionarioId = Auth::id();

        if (empty($funcionarioId)) {
            return;
        }

        try {
            (new Funcionario())->atualizarLocale(
                (int) $funcionarioId,
                $locale
            );
            $_SESSION['ui_locale'] = $locale;
            $_SESSION['user_locale'] = $locale;
        } catch (\Exception $e) {
            error_log("Erro ao persistir locale: " . $e->getMessage());
        }
    }
}
