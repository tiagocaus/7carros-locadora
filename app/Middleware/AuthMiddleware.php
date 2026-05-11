<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\I18n\Translator;

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
     * 3. Deixar o Translator usar seu fallback padrão
     */
    private function loadUserLocale(): void
    {
        // Se já tiver um locale na sessão, não precisa buscar do banco
        if (!empty($_SESSION['ui_locale'])) {
            return;
        }

        // Buscar locale do funcionário no banco
        $user = Auth::user();
        if (!empty($user['ui_locale'])) {
            $locale = $user['ui_locale'];

            // Validar e definir o locale
            $translator = Translator::getInstance();
            if ($translator->isSupported($locale)) {
                $_SESSION['ui_locale'] = $locale;
                $translator->setLocale($locale);
            }
        }
    }
}
