<?php

namespace App\Core;

/**
 * Wrapper para Requisições HTTP
 *
 * Fornece interface simplificada para acessar dados de requisição
 */
class Request
{
    private array $get;
    private array $post;
    private array $server;
    private array $files;
    private array $cookies;
    private ?array $json = null;

    // Dados de segurança (preenchidos pelos middlewares)
    private int $securityScore = 0;
    private array $securityFactors = [];

    public function __construct()
    {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->server = $_SERVER;
        $this->files = $_FILES;
        $this->cookies = $_COOKIE;

        // Detecta e parseia JSON no corpo da requisição
        if ($this->isJson()) {
            $this->json = json_decode(file_get_contents('php://input'), true) ?? [];
        }
    }

    /**
     * Cria uma nova instância da requisição
     */
    public static function capture(): self
    {
        return new self();
    }

    /**
     * Obtém um valor de $_GET
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    /**
     * Obtém um valor de $_POST ou JSON body
     */
    public function input(string $key, mixed $default = null): mixed
    {
        // Primeiro tenta JSON, depois POST
        if ($this->json !== null && isset($this->json[$key])) {
            return $this->json[$key];
        }

        return $this->post[$key] ?? $default;
    }

    /**
     * Obtém todos os dados de input (POST + JSON)
     */
    public function all(): array
    {
        return $this->json ?? $this->post;
    }

    /**
     * Obtém apenas os campos especificados
     */
    public function only(array $keys): array
    {
        $data = $this->all();
        return array_intersect_key($data, array_flip($keys));
    }

    /**
     * Obtém todos exceto os campos especificados
     */
    public function except(array $keys): array
    {
        $data = $this->all();
        return array_diff_key($data, array_flip($keys));
    }

    /**
     * Verifica se um campo existe no input
     */
    public function has(string $key): bool
    {
        $data = $this->all();
        return isset($data[$key]);
    }

    /**
     * Verifica se todos os campos existem
     */
    public function hasAll(array $keys): bool
    {
        foreach ($keys as $key) {
            if (!$this->has($key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Obtém um arquivo enviado
     */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /**
     * Verifica se um arquivo foi enviado
     */
    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] === UPLOAD_ERR_OK;
    }

    /**
     * Obtém um cookie
     */
    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * Obtém o método HTTP da requisição
     */
    public function method(): string
    {
        // Verifica se há spoofing de método via _method
        if ($this->input('_method')) {
            return strtoupper($this->input('_method'));
        }

        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Verifica se o método é GET
     */
    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }

    /**
     * Verifica se o método é POST
     */
    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    /**
     * Verifica se o método é PUT
     */
    public function isPut(): bool
    {
        return $this->method() === 'PUT';
    }

    /**
     * Verifica se o método é DELETE
     */
    public function isDelete(): bool
    {
        return $this->method() === 'DELETE';
    }

    /**
     * Verifica se o método é PATCH
     */
    public function isPatch(): bool
    {
        return $this->method() === 'PATCH';
    }

    /**
     * Verifica se a requisição é AJAX
     */
    public function isAjax(): bool
    {
        return isset($this->server['HTTP_X_REQUESTED_WITH']) &&
               strtolower($this->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Verifica se a resposta esperada pela requisicao e JSON.
     */
    public function expectsJson(): bool
    {
        $accept = strtolower($this->server['HTTP_ACCEPT'] ?? '');

        return $this->isAjax()
            || $this->isJson()
            || str_contains($accept, 'application/json');
    }

    /**
     * Verifica se a requisição é JSON
     */
    public function isJson(): bool
    {
        $contentType = $this->server['CONTENT_TYPE'] ?? '';
        return str_contains($contentType, 'application/json');
    }

    /**
     * Obtém a URL completa da requisição
     */
    public function url(): string
    {
        $protocol = $this->isSecure() ? 'https' : 'http';
        $host = $this->server['HTTP_HOST'] ?? 'localhost';
        $uri = $this->server['REQUEST_URI'] ?? '/';

        return "$protocol://$host$uri";
    }

    /**
     * Obtém o path da requisição (sem query string)
     */
    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        return strtok($uri, '?');
    }

    /**
     * Verifica se a requisição é HTTPS
     */
    public function isSecure(): bool
    {
        return isset($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off';
    }

    /**
     * Obtém o IP do cliente.
     *
     * Só confia em headers X-Forwarded-For / Client-IP se o REMOTE_ADDR
     * estiver na lista TRUSTED_PROXIES (env). Caso contrário, qualquer
     * cliente pode forjar o IP via header (brute-force bypass).
     */
    public function ip(): string
    {
        $remoteAddr = $this->server['REMOTE_ADDR'] ?? '0.0.0.0';

        $trustedProxies = array_filter(array_map('trim', explode(',', $_ENV['TRUSTED_PROXIES'] ?? '')));

        if (!empty($trustedProxies) && in_array($remoteAddr, $trustedProxies, true)) {
            // Prefere X-Forwarded-For (padrao RFC 7239 / Cloudflare / Nginx)
            if (!empty($this->server['HTTP_X_FORWARDED_FOR'])) {
                $ips = explode(',', $this->server['HTTP_X_FORWARDED_FOR']);
                $candidate = trim($ips[0]);
                if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                    return $candidate;
                }
            }

            if (!empty($this->server['HTTP_CLIENT_IP'])) {
                $candidate = trim($this->server['HTTP_CLIENT_IP']);
                if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                    return $candidate;
                }
            }
        }

        return $remoteAddr;
    }

    /**
     * Obtém o User Agent
     */
    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Obtém um header HTTP
     */
    public function header(string $key, mixed $default = null): mixed
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $this->server[$key] ?? $default;
    }

    /**
     * Obtém o referer (página anterior)
     */
    public function referer(): ?string
    {
        return $this->server['HTTP_REFERER'] ?? null;
    }

    /**
     * Valida o token CSRF
     */
    public function validateCsrfToken(): bool
    {
        $token = $this->input('_token') ?? $this->header('X-CSRF-TOKEN');

        if (!$token) {
            return false;
        }

        $sessionToken = Session::get('csrf_token');

        return $sessionToken && hash_equals($sessionToken, $token);
    }

    /**
     * Sanitiza um valor de input
     */
    public function sanitize(string $key): string
    {
        $value = $this->input($key, '');
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Obtém um valor de SERVER
     */
    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    /**
     * Define o score de segurança da requisição
     * (usado pelos middlewares de segurança)
     */
    public function setSecurityScore(int $score): void
    {
        $this->securityScore = $score;
    }

    /**
     * Obtém o score de segurança da requisição
     */
    public function getSecurityScore(): int
    {
        return $this->securityScore;
    }

    /**
     * Define os fatores de segurança detectados
     * (usado pelos middlewares de segurança)
     */
    public function setSecurityFactors(array $factors): void
    {
        $this->securityFactors = $factors;
    }

    /**
     * Obtém os fatores de segurança detectados
     */
    public function getSecurityFactors(): array
    {
        return $this->securityFactors;
    }

    /**
     * Verifica se a requisição foi marcada como suspeita
     */
    public function isSuspicious(): bool
    {
        return $this->securityScore > 30;
    }
}
