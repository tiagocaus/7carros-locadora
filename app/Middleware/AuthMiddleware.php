<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\I18n\Translator;
use App\Models\Funcionario;

/**
 * Middleware de Autenticação
 *
 * Protege rotas que exigem autenticação
 */
class AuthMiddleware
{
    /**
     * Executa o middleware
     */
    public function handle(Request $request): bool
    {
        // Tenta autenticar via remember token se não estiver autenticado
        if (!Auth::check()) {
            Auth::attemptRememberToken();
        }

        // Verifica se está autenticado
        if (!Auth::check()) {
            // Se for uma requisição AJAX, retorna JSON
            if ($request->isAjax()) {
                Response::json([
                    'success' => false,
                    'message' => 'Não autenticado',
                    'redirect' => '/login'
                ], 401);
            }

            // Salva a URL de destino para redirecionar depois do login
            $_SESSION['intended_url'] = $request->url();

            // Redireciona para o login
            Response::redirect('/login');
        }

        // Carregar preferência de locale do usuário se ainda não estiver na sessão
        $this->loadUserLocale();

        // Continua com a requisição
        return true;
    }

    /**
     * Carrega a preferência de locale do usuário autenticado
     *
     * Prioridade:
     * 1. ui_locale da sessão (já definido pelo usuário durante a sessão)
     * 2. ui_locale do banco de dados (preferência persistida)
     * 3. locale da empresa/filial vinculada ao usuário
     * 4. Deixar o Translator usar seu fallback padrão
     */
    private function loadUserLocale(): void
    {
        $translator = Translator::getInstance();

        // Se já tiver um locale na sessão, sincronizar o Translator da requisição
        if (!empty($_SESSION['ui_locale'])) {
            $locale = $_SESSION['ui_locale'];
            if ($translator->isSupported($locale)) {
                $translator->setLocale($locale);
                return;
            }

            unset($_SESSION['ui_locale']);
        }

        // Buscar locale do funcionário no banco
        $funcionarioId = Auth::id();
        if ($funcionarioId) {
            try {
                $funcionario = (new Funcionario())->buscarPorId((int) $funcionarioId);
                $locale = $funcionario['ui_locale'] ?? null;

                if (!empty($locale) && $translator->isSupported($locale)) {
                    $_SESSION['user_locale'] = $locale;
                    $translator->setLocale($locale);
                    return;
                }
            } catch (\Exception $e) {
                error_log('Erro ao carregar locale do funcionário: ' . $e->getMessage());
            }
        }

        // Buscar locale configurado na empresa/filial como fallback da interface
        $empresa = Auth::empresa();
        $locale = $empresa['locale'] ?? null;
        if (!empty($locale) && $translator->isSupported($locale)) {
            $_SESSION['empresa_ui_locale'] = $locale;
            $translator->setLocale($locale);
            unset($_SESSION['ui_locale']);
        }
    }
}
