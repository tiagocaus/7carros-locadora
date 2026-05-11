<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\LoginAttempt;
use App\Views\Template;

/**
 * Controller de Autenticação
 *
 * Gerencia login, logout e recuperação de senha
 */
class AuthController
{
    private LoginAttempt $loginAttemptModel;

    public function __construct()
    {
        $this->loginAttemptModel = new LoginAttempt();
    }

    /**
     * Exibe o formulário de login
     */
    public function showLogin(Request $request): void
    {
        $html = Template::render('auth.login');
        Response::html($html);
    }

    /**
     * Processa o login
     */
    public function login(Request $request): void
    {
        // Validação básica
        $username = trim($request->input('username', ''));
        $password = $request->input('password', '');
        $remember = $request->input('remember') === 'on';

        if (empty($username) || empty($password)) {
            Response::backWithError('Usuário e senha são obrigatórios');
        }

        // Proteção contra brute force (simples)
        $this->checkLoginAttempts($username, $request->ip());

        // Tenta autenticar
        if (Auth::attempt($username, $password, $remember)) {
            // Limpa tentativas de login
            $this->clearLoginAttempts($username, $request->ip());

            // Redireciona para a URL pretendida ou destino baseado em permissoes
            $intendedUrl = Session::get('intended_url');
            Session::remove('intended_url');

            if ($intendedUrl) {
                Response::redirect($intendedUrl);
            } elseif (Auth::can('dashboard.visualizar')) {
                Response::redirect('/dashboard');
            } else {
                // Usuario sem acesso ao dashboard (ex: funcionario so com checklists.criar)
                Response::redirect('/checklists/digital');
            }
        }

        // Login falhou, registra tentativa
        $this->recordLoginAttempt($username, $request->ip());

        // Retorna com erro
        Response::backWithErrors(
            ['username' => 'Credenciais inválidas ou usuário inativo'],
            ['username' => $username]
        );
    }

    /**
     * Faz logout do usuário
     */
    public function logout(Request $request): void
    {
        Auth::logout();
        Response::redirectWithSuccess('/login', 'Você saiu com sucesso');
    }

    /**
     * Verifica tentativas de login (rate limiting)
     */
    private function checkLoginAttempts(string $username, string $ip): void
    {
        $attempts = $this->loginAttemptModel->buscarBloqueio($username, $ip);

        if ($attempts) {
            $minutesLeft = ceil((strtotime($attempts['bloqueado_ate']) - time()) / 60);
            Response::backWithError(
                "Muitas tentativas de login. Tente novamente em $minutesLeft minutos."
            );
        }
    }

    /**
     * Registra uma tentativa de login falhada
     */
    private function recordLoginAttempt(string $username, string $ip): void
    {
        // Verifica se já existe um registro
        $attempt = $this->loginAttemptModel->buscar($username, $ip);

        if ($attempt) {
            $newAttempts = $attempt['tentativas'] + 1;
            $bloqueadoAte = null;

            // Bloqueia por 15 minutos após 5 tentativas
            if ($newAttempts >= 5) {
                $bloqueadoAte = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            }

            $this->loginAttemptModel->incrementar($username, $ip, $newAttempts, $bloqueadoAte);
        } else {
            $this->loginAttemptModel->registrar($username, $ip);
        }
    }

    /**
     * Limpa as tentativas de login após sucesso
     */
    private function clearLoginAttempts(string $username, string $ip): void
    {
        $this->loginAttemptModel->limpar($username, $ip);
    }
}
