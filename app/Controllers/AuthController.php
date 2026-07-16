<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\FuncionarioPasswordReset;
use App\Models\LoginAttempt;
use App\Services\AuthPasswordResetService;
use App\Views\Template;

/**
 * Controller de Autenticação
 *
 * Gerencia login, logout e recuperação de senha
 */
class AuthController
{
    private LoginAttempt $loginAttemptModel;
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_BLOCK_MINUTES = 15;

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
        $expectsJson = $request->expectsJson();

        if (empty($username) || empty($password)) {
            if ($expectsJson) {
                Response::json([
                    'success' => false,
                    'message' => 'Usuário e senha são obrigatórios',
                ], 422);
            }

            Response::backWithError('Usuário e senha são obrigatórios');
        }

        // Proteção contra brute force (simples)
        $this->checkLoginAttempts($username, $request->ip(), $expectsJson);

        // Tenta autenticar com motivo detalhado para mensagens claras
        $authResult = Auth::attemptDetailed($username, $password, $remember);
        if ($authResult['success']) {
            // Limpa tentativas de login
            $this->clearLoginAttempts($username, $request->ip());

            if (!Auth::canAccessWebSystem()) {
                Auth::logout();

                if ($expectsJson) {
                    Response::json([
                        'success' => false,
                        'message' => Auth::WEB_SYSTEM_ACCESS_DENIED,
                    ], 403);
                }

                Response::redirectWithError('/login', Auth::WEB_SYSTEM_ACCESS_DENIED);
            }

            // Usuario autorizado: respeita a URL pretendida ou abre o dashboard.
            $intendedUrl = Session::get('intended_url');
            Session::remove('intended_url');

            if ($intendedUrl) {
                if ($expectsJson) {
                    $this->respondLoginSuccess($intendedUrl);
                }

                Response::redirect($intendedUrl);
            } else {
                if ($expectsJson) {
                    $this->respondLoginSuccess('/dashboard');
                }

                Response::redirect('/dashboard');
            }
        }

        if (($authResult['reason'] ?? null) === Auth::ATTEMPT_SUSPENDED) {
            $message = 'Seu acesso está suspenso. Isso pode acontecer por fatura vencida. Entre em contato com o suporte para regularizar o acesso.';
            if ($expectsJson) {
                Response::json([
                    'success' => false,
                    'message' => $message,
                ], 403);
            }

            Response::backWithError(
                $message
            );
        }

        if (($authResult['reason'] ?? null) === Auth::ATTEMPT_INACTIVE) {
            $message = 'Seu usuário está inativo. Entre em contato com o suporte para verificar o acesso.';
            if ($expectsJson) {
                Response::json([
                    'success' => false,
                    'message' => $message,
                ], 403);
            }

            Response::backWithError(
                $message
            );
        }

        // Login falhou por usuário ou senha, registra tentativa
        $attemptInfo = $this->recordLoginAttempt($username, $request->ip());
        $message = $this->invalidCredentialsMessage($attemptInfo);

        if ($expectsJson) {
            Response::json([
                'success' => false,
                'message' => $message,
                'attempts' => $attemptInfo['attempts'] ?? null,
                'remaining' => $attemptInfo['remaining'] ?? null,
                'blocked' => $attemptInfo['blocked'] ?? false,
                'blocked_until' => $attemptInfo['blocked_until'] ?? null,
            ], 401);
        }

        Session::flash('error', $message);

        Response::backWithErrors(
            ['username' => $message],
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
     * Solicita redefinição de senha do funcionário pelo login.
     */
    public function redefinirSenha(Request $request): void
    {
        $identifier = trim((string) $request->input('identifier', ''));

        if ($identifier !== '') {
            (new AuthPasswordResetService())->requestReset($identifier, $request->ip());
        }

        Response::json([
            'success' => true,
            'message' => 'Se o usuario existir e tiver e-mail cadastrado, enviaremos um link para redefinir a senha.',
        ]);
    }

    /**
     * Exibe o formulario publico para definir nova senha via token.
     */
    public function showResetForm(Request $request): void
    {
        $token = (string) $request->query('token', '');
        $reset = (new FuncionarioPasswordReset())->validar($token);

        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');

        if (!$reset) {
            http_response_code(400);
            echo $this->renderResetPage([
                'titulo' => 'Link invalido ou expirado',
                'corpo' => '<p>Este link de redefinicao expirou ou ja foi usado. Solicite um novo pela tela de login.</p>',
                'form' => false,
                'token' => '',
            ]);
            return;
        }

        $csrfToken = Session::get('csrf_token');
        if (!$csrfToken) {
            $csrfToken = bin2hex(random_bytes(32));
            Session::set('csrf_token', $csrfToken);
            Session::set('csrf_token_time', \App\Helpers\DateHelper::timestamp());
        }

        echo $this->renderResetPage([
            'titulo' => 'Definir nova senha',
            'corpo' => '<p>Digite sua nova senha de acesso ao painel. Minimo 8 caracteres.</p>',
            'form' => true,
            'token' => $token,
            'csrf' => $csrfToken,
        ]);
    }

    /**
     * Aplica a nova senha apos validacao do token recebido por email.
     */
    public function definirSenha(Request $request): void
    {
        $token = (string) $request->input('token', '');
        $senha = (string) $request->input('senha', '');
        $confirmacao = (string) $request->input('senha_confirmacao', '');

        if (strlen($senha) < 8) {
            Response::json([
                'success' => false,
                'message' => 'A senha deve ter pelo menos 8 caracteres.',
            ], 422);
            return;
        }

        if ($confirmacao !== '' && $senha !== $confirmacao) {
            Response::json([
                'success' => false,
                'message' => 'As senhas nao coincidem.',
            ], 422);
            return;
        }

        $ok = (new AuthPasswordResetService())->resetWithToken($token, $senha, $request->ip());
        if (!$ok) {
            Response::json([
                'success' => false,
                'message' => 'Link invalido ou expirado.',
            ], 400);
            return;
        }

        Response::json([
            'success' => true,
            'message' => 'Senha redefinida com sucesso. Acesse o painel com a nova senha.',
        ]);
    }

    /**
     * Verifica tentativas de login (rate limiting)
     */
    private function checkLoginAttempts(string $username, string $ip, bool $expectsJson = false): void
    {
        $attempts = $this->loginAttemptModel->buscarBloqueio($username, $ip);

        if ($attempts) {
            $secondsLeft = $this->secondsUntilUnlock($attempts['bloqueado_ate'] ?? null);
            if ($secondsLeft <= 0) {
                $this->clearLoginAttempts($username, $ip);
                return;
            }

            $minutesLeft = max(1, (int) ceil($secondsLeft / 60));
            $message = "Acesso temporariamente bloqueado por muitas tentativas. Tente novamente em $minutesLeft minutos ou redefina sua senha.";

            if ($expectsJson) {
                Response::json([
                    'success' => false,
                    'message' => $message,
                    'blocked' => true,
                    'blocked_until' => $attempts['bloqueado_ate'] ?? null,
                    'retry_after_seconds' => $secondsLeft,
                ], 429);
            }

            Response::backWithError(
                $message
            );
        }
    }

    /**
     * Retorna os dados da sessao autenticada no fluxo de login JSON.
     */
    private function respondLoginSuccess(string $redirect): void
    {
        $user = Auth::user() ?? [];

        Response::json([
            'success' => true,
            'redirect' => $redirect,
            'user' => [
                'id' => $user['id'] ?? null,
                'nome' => $user['nome'] ?? '',
                'usuario' => $user['usuario'] ?? '',
                'email' => $user['email'] ?? '',
                'plano' => $user['plano'] ?? null,
                'id_matriz_filial' => $user['id_matriz_filial'] ?? null,
                'filiais_permitidas' => $user['filiais_permitidas'] ?? [],
            ],
        ]);
    }

    /**
     * Registra uma tentativa de login falhada
     */
    private function recordLoginAttempt(string $username, string $ip): array
    {
        // Verifica se já existe um registro
        $attempt = $this->loginAttemptModel->buscar($username, $ip);

        if ($attempt) {
            if (!empty($attempt['bloqueado_ate']) && $this->secondsUntilUnlock($attempt['bloqueado_ate']) <= 0) {
                $this->clearLoginAttempts($username, $ip);
                $this->loginAttemptModel->registrar($username, $ip);
                return [
                    'attempts' => 1,
                    'blocked' => false,
                    'blocked_until' => null,
                    'remaining' => self::MAX_LOGIN_ATTEMPTS - 1,
                ];
            }

            $newAttempts = ((int) $attempt['tentativas']) + 1;
            $bloqueadoAte = null;

            // Bloqueia por 15 minutos após 5 tentativas
            if ($newAttempts >= self::MAX_LOGIN_ATTEMPTS) {
                $bloqueadoAte = \App\Helpers\DateHelper::formatTimestamp(
                    \App\Helpers\DateHelper::timestamp() + (self::LOGIN_BLOCK_MINUTES * 60),
                    'Y-m-d H:i:s',
                    false
                );
            }

            $this->loginAttemptModel->incrementar($username, $ip, $newAttempts, $bloqueadoAte);

            return [
                'attempts' => $newAttempts,
                'blocked' => $bloqueadoAte !== null,
                'blocked_until' => $bloqueadoAte,
                'remaining' => max(0, self::MAX_LOGIN_ATTEMPTS - $newAttempts),
            ];
        } else {
            $this->loginAttemptModel->registrar($username, $ip);

            return [
                'attempts' => 1,
                'blocked' => false,
                'blocked_until' => null,
                'remaining' => self::MAX_LOGIN_ATTEMPTS - 1,
            ];
        }
    }

    /**
     * Monta mensagem clara para erro de usuario/senha.
     */
    private function invalidCredentialsMessage(array $attemptInfo): string
    {
        if (!empty($attemptInfo['blocked'])) {
            return 'Usuário ou senha inválidos. Seu acesso foi bloqueado temporariamente por muitas tentativas. Tente novamente em '
                . self::LOGIN_BLOCK_MINUTES
                . ' minutos ou clique em Redefinir senha.';
        }

        $attempts = (int) ($attemptInfo['attempts'] ?? 1);
        $remaining = (int) ($attemptInfo['remaining'] ?? (self::MAX_LOGIN_ATTEMPTS - $attempts));

        if ($attempts >= 2) {
            $plural = $remaining === 1 ? 'tentativa' : 'tentativas';
            return "Usuário ou senha inválidos. Restam {$remaining} {$plural} antes do bloqueio temporário. Se esqueceu a senha, clique em Redefinir senha antes de tentar novamente.";
        }

        return 'Usuário ou senha inválidos.';
    }

    /**
     * Limpa as tentativas de login após sucesso
     */
    private function clearLoginAttempts(string $username, string $ip): void
    {
        $this->loginAttemptModel->limpar($username, $ip);
    }

    /**
     * Calcula segundos restantes de bloqueio usando o timezone da aplicacao.
     */
    private function secondsUntilUnlock(?string $blockedUntil): int
    {
        if (empty($blockedUntil)) {
            return 0;
        }

        $blockedUntilTimestamp = strtotime($blockedUntil);
        if ($blockedUntilTimestamp === false) {
            return 0;
        }

        return max(0, $blockedUntilTimestamp - \App\Helpers\DateHelper::timestamp());
    }

    /**
     * HTML standalone para troca de senha sem depender do layout autenticado.
     */
    private function renderResetPage(array $data): string
    {
        $titulo = htmlspecialchars($data['titulo'], ENT_QUOTES, 'UTF-8');
        $corpo = $data['corpo'];
        $temForm = !empty($data['form']);
        $token = htmlspecialchars((string) ($data['token'] ?? ''), ENT_QUOTES, 'UTF-8');
        $csrf = htmlspecialchars((string) ($data['csrf'] ?? ''), ENT_QUOTES, 'UTF-8');

        $formHtml = '';
        if ($temForm) {
            $formHtml = <<<HTML
<form id="form-reset" method="post" action="/auth/redefinir-senha/definir">
    <input type="hidden" name="_token" value="{$csrf}">
    <input type="hidden" name="token" value="{$token}">
    <label>Nova senha</label>
    <input type="password" name="senha" minlength="8" required autofocus>
    <label>Confirmar senha</label>
    <input type="password" name="senha_confirmacao" minlength="8" required>
    <button type="submit">Salvar nova senha</button>
    <div id="msg"></div>
</form>
<script>
document.getElementById('form-reset').addEventListener('submit', async function(e) {
    e.preventDefault();
    var f = e.target;
    var msg = document.getElementById('msg');
    if (f.senha.value !== f.senha_confirmacao.value) {
        msg.textContent = 'As senhas nao coincidem.';
        msg.className = 'err';
        return;
    }
    msg.textContent = '';
    var fd = new FormData(f);
    try {
        var r = await fetch(f.action, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
        var j = await r.json();
        msg.textContent = j.message || (j.success ? 'Senha redefinida.' : 'Erro.');
        msg.className = j.success ? 'ok' : 'err';
        if (j.success) {
            f.querySelectorAll('input,button').forEach(function(el) { el.disabled = true; });
        }
    } catch (err) {
        msg.textContent = 'Erro de rede.';
        msg.className = 'err';
    }
});
</script>
HTML;
        }

        return <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{$titulo}</title>
<style>
body{font-family:system-ui,-apple-system,sans-serif;background:#f5f7fa;margin:0;padding:40px 16px;color:#1f2937;}
.card{max-width:420px;margin:0 auto;background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.1);padding:32px;}
h1{margin-top:0;color:#1a56db;font-size:22px;}
label{display:block;margin-top:16px;margin-bottom:6px;font-weight:600;font-size:14px;}
input[type=password]{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:4px;font-size:15px;box-sizing:border-box;}
button{margin-top:20px;width:100%;background:#1a56db;color:#fff;border:0;padding:12px;border-radius:4px;font-size:15px;cursor:pointer;}
button:hover{background:#1e429f;}
button:disabled{background:#9ca3af;cursor:not-allowed;}
#msg{margin-top:14px;font-size:14px;}
#msg.err{color:#b91c1c;}
#msg.ok{color:#047857;}
</style>
</head>
<body>
<div class="card">
<h1>{$titulo}</h1>
{$corpo}
{$formHtml}
</div>
</body>
</html>
HTML;
    }
}
