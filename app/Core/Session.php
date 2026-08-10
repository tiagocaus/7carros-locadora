<?php

namespace App\Core;

/**
 * Gerenciamento de Sessões
 *
 * Fornece interface segura para manipulação de sessões PHP
 * com proteção contra session hijacking e fixation
 */
class Session
{
    public const INACTIVITY_TIMEOUT = 14400;

    private const COOKIE_POLICY_VERSION = 2;

    /**
     * Contexto efemero da invalidacao ocorrida na requisicao atual.
     * Nunca armazena o identificador ou o conteudo do cookie de sessao.
     */
    private static ?array $invalidationContext = null;

    /**
     * Inicia a sessão se ainda não foi iniciada
     */
    public static function start(): void
    {
        $status = session_status();
        if ($status === PHP_SESSION_ACTIVE) {
            return;
        }
        if ($status === PHP_SESSION_DISABLED) {
            throw new \RuntimeException('Sessoes PHP indisponiveis');
        }

        self::configure();

        // O PHP 8.3 pode manter o modulo de sessao inutilizavel durante toda a
        // request depois de rejeitar um ID ilegal. Valida antes da primeira
        // chamada nativa para que a recuperacao ainda seja possivel.
        if (!self::isIncomingSessionIdValid()) {
            self::recordInvalidation('invalid_cookie');
            self::discardIncomingSessionId();
        }

        if (!self::openNativeSession()) {
            // Um cookie de sessao corrompido/invalido nao pode provocar novas
            // chamadas recursivas a start(). Descarta somente o identificador
            // recebido e faz uma unica tentativa com um ID novo.
            self::discardIncomingSessionId();
            if (!self::openNativeSession()) {
                self::recordInvalidation('storage_failure');
                throw new \RuntimeException('Nao foi possivel iniciar a sessao');
            }
        }

        self::initializeMetadata();

        if (!self::validateFingerprint()) {
            self::invalidateAndRestart('fingerprint_mismatch');
        }

        if (self::hasExceededInactivityTimeout()) {
            self::invalidateAndRestart('inactivity');
        }

        self::touchActivity();
        self::migrateCookiePolicy();
    }

    private static function configure(): void
    {
        $isSecure = self::isSecureRequest();

        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_secure', $isSecure ? '1' : '0');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.gc_maxlifetime', (string) self::INACTIVITY_TIMEOUT);

        // O navegador nao deve impor um limite absoluto contado desde o login.
        // A expiracao por inatividade e validada no servidor; o remember_token
        // continua sendo o mecanismo de persistencia apos fechar o navegador.
        ini_set('session.cookie_lifetime', '0');
    }

    private static function openNativeSession(): bool
    {
        return @session_start() && session_status() === PHP_SESSION_ACTIVE;
    }

    private static function isIncomingSessionIdValid(): bool
    {
        $id = (string) ($_COOKIE[session_name()] ?? session_id());
        if ($id === '') {
            return true;
        }

        return strlen($id) <= 256 && preg_match('/^[A-Za-z0-9,-]+$/D', $id) === 1;
    }

    private static function initializeMetadata(): void
    {
        if (isset($_SESSION['_session_initiated'])) {
            return;
        }

        if (!session_regenerate_id(true)) {
            throw new \RuntimeException('Nao foi possivel regenerar a sessao');
        }

        $_SESSION['_session_initiated'] = true;
        $_SESSION['_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['_ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['_last_activity_at'] = time();
        $_SESSION['_cookie_policy_version'] = self::COOKIE_POLICY_VERSION;
    }

    private static function invalidateAndRestart(string $reason): void
    {
        self::recordInvalidation($reason, [
            'user_id' => (int) ($_SESSION['user_id'] ?? 0),
            'chave' => (string) ($_SESSION['chave'] ?? ''),
        ]);

        self::destroyActiveSession();
        if (!self::openNativeSession()) {
            throw new \RuntimeException('Nao foi possivel reiniciar a sessao');
        }
        self::initializeMetadata();
    }

    private static function hasExceededInactivityTimeout(): bool
    {
        if (($_SESSION['authenticated'] ?? false) !== true) {
            return false;
        }

        $lastActivity = (int) ($_SESSION['_last_activity_at'] ?? 0);

        // Sessoes criadas antes desta politica ganham a referencia a partir da
        // primeira requisicao apos a publicacao, sem logout forcado no deploy.
        if ($lastActivity <= 0) {
            return false;
        }

        return (time() - $lastActivity) > self::INACTIVITY_TIMEOUT;
    }

    private static function touchActivity(): void
    {
        $_SESSION['_last_activity_at'] = time();
    }

    private static function migrateCookiePolicy(): void
    {
        if ((int) ($_SESSION['_cookie_policy_version'] ?? 0) >= self::COOKIE_POLICY_VERSION) {
            return;
        }

        self::sendSessionCookie();
        $_SESSION['_cookie_policy_version'] = self::COOKIE_POLICY_VERSION;
    }

    private static function sendSessionCookie(): void
    {
        if (!ini_get('session.use_cookies') || headers_sent() || session_id() === '') {
            return;
        }

        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires' => 0,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => $params['samesite'] ?: 'Lax',
        ]);
    }

    private static function isSecureRequest(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off') {
            return true;
        }

        $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
        return $forwardedProto === 'https';
    }

    private static function discardIncomingSessionId(): void
    {
        self::expireSessionCookie();
        unset($_COOKIE[session_name()]);
        @session_id('');
    }

    /**
     * Valida o fingerprint da sessão para detectar hijacking
     */
    private static function validateFingerprint(): bool
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (isset($_SESSION['_user_agent']) && $_SESSION['_user_agent'] !== $userAgent) {
            return false;
        }

        // IP removido da validacao: celulares mudam de IP frequentemente
        // (troca de torre, wifi<->4G), causando perda de sessao indevida

        return true;
    }

    private static function recordInvalidation(string $reason, array $context = []): void
    {
        $chave = (string) ($context['chave'] ?? '');
        self::$invalidationContext = [
            'reason' => $reason,
            'user_id' => (int) ($context['user_id'] ?? 0),
            'tenant_ref' => $chave !== '' ? substr(hash('sha256', $chave), 0, 12) : null,
            'endpoint' => strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?') ?: '/',
        ];

        error_log('[Session] Invalidacao: ' . json_encode(
            self::$invalidationContext,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
    }

    public static function invalidationReason(): ?string
    {
        return self::$invalidationContext['reason'] ?? null;
    }

    /**
     * Define um valor na sessão
     */
    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Obtém um valor da sessão
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Verifica se uma chave existe na sessão
     */
    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove um valor da sessão
     */
    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Define uma mensagem flash (disponível apenas na próxima requisição)
     */
    public static function flash(string $key, mixed $value): void
    {
        self::start();
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Obtém uma mensagem flash
     */
    public static function getFlash(string $key, mixed $default = null): mixed
    {
        self::start();

        $value = $_SESSION['_flash'][$key] ?? $default;

        // Remove a mensagem após leitura
        unset($_SESSION['_flash'][$key]);

        return $value;
    }

    /**
     * Verifica se existe uma mensagem flash
     */
    public static function hasFlash(string $key): bool
    {
        self::start();
        return isset($_SESSION['_flash'][$key]);
    }

    /**
     * Obtém todas as mensagens flash e as remove
     */
    public static function getAllFlash(): array
    {
        self::start();

        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return $flash;
    }

    /**
     * Armazena os dados antigos do formulário (para repopular em caso de erro)
     */
    public static function flashOld(array $data): void
    {
        self::flash('old', $data);
    }

    /**
     * Obtém um valor antigo do formulário
     */
    public static function old(string $key, mixed $default = null): mixed
    {
        $old = self::getFlash('old', []);
        return $old[$key] ?? $default;
    }

    /**
     * Regenera o ID da sessão (previne session fixation)
     */
    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    /**
     * Destrói completamente a sessão
     */
    public static function destroy(): void
    {
        self::start();

        self::destroyActiveSession();
    }

    private static function destroyActiveSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        // Limpa todas as variáveis de sessão
        $_SESSION = [];

        // Remove o cookie de sessão
        self::expireSessionCookie();

        // Destrói a sessão
        session_destroy();
        unset($_COOKIE[session_name()]);
        @session_id('');
    }

    private static function expireSessionCookie(): void
    {
        if (!ini_get('session.use_cookies') || headers_sent()) {
            return;
        }

        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => $params['samesite'] ?: 'Lax',
        ]);
    }

    /**
     * Obtém todas as variáveis de sessão
     */
    public static function all(): array
    {
        self::start();
        return $_SESSION;
    }

    /**
     * Limpa todas as variáveis de sessão (mas mantém a sessão ativa)
     */
    public static function clear(): void
    {
        self::start();
        $_SESSION = [];
    }

    /**
     * Obtém o ID da sessão atual
     */
    public static function id(): string
    {
        self::start();
        return session_id();
    }
}
