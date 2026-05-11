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
        if (session_status() === PHP_SESSION_NONE) {
            // Configurações de segurança da sessão
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.gc_maxlifetime', '14400'); // 4 horas
            ini_set('session.cookie_lifetime', '14400'); // 4 horas

            session_start();

            // Proteção contra session hijacking
            if (!self::has('_session_initiated')) {
                session_regenerate_id(true);
                self::set('_session_initiated', true);
                self::set('_user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '');
                self::set('_ip_address', $_SERVER['REMOTE_ADDR'] ?? '');
            }

            // Validar fingerprint da sessão
            if (!self::validateFingerprint()) {
                self::destroy();
                session_start();
            }
        }
    }

    /**
     * Valida o fingerprint da sessão para detectar hijacking
     */
    private static function validateFingerprint(): bool
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (self::has('_user_agent') && self::get('_user_agent') !== $userAgent) {
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

        // Limpa todas as variáveis de sessão
        $_SESSION = [];

        // Remove o cookie de sessão
        if (ini_get('session.use_cookies')) {
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

        // Destrói a sessão
        session_destroy();
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
