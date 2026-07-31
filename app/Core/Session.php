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
            self::discardIncomingSessionId();
        }

        if (!self::openNativeSession()) {
            // Um cookie de sessao corrompido/invalido nao pode provocar novas
            // chamadas recursivas a start(). Descarta somente o identificador
            // recebido e faz uma unica tentativa com um ID novo.
            self::discardIncomingSessionId();
            if (!self::openNativeSession()) {
                throw new \RuntimeException('Nao foi possivel iniciar a sessao');
            }
        }

        self::initializeMetadata();

        if (!self::validateFingerprint()) {
            self::destroyActiveSession();
            if (!self::openNativeSession()) {
                throw new \RuntimeException('Nao foi possivel reiniciar a sessao');
            }
            self::initializeMetadata();
        }
    }

    private static function configure(): void
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        $isSecure = $https !== '' && $https !== 'off';

        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_secure', $isSecure ? '1' : '0');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.gc_maxlifetime', '14400'); // 4 horas
        ini_set('session.cookie_lifetime', '14400'); // 4 horas
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
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
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
