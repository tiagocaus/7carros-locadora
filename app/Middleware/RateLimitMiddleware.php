<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Config\Security;
use App\Services\SecurityLogService;
use App\Models\Security\RateLimit;

/**
 * Middleware de Rate Limiting
 *
 * Limita a quantidade de requisições por IP/usuário em uma janela de tempo.
 * Protege contra abuso e ataques de força bruta.
 */
class RateLimitMiddleware
{
    private RateLimit $model;

    public function __construct()
    {
        $this->model = new RateLimit();
    }

    /**
     * Executa o middleware
     */
    public function handle(Request $request, ?string $routePath = null): bool
    {
        if (!Security::RATE_LIMIT['enabled']) {
            return true;
        }

        $ipAddress = $request->ip();
        $userId = Auth::id();
        $endpoint = $this->normalizeEndpoint($routePath ?? $request->path());
        $method = $request->method();

        // Obtém limites para este endpoint/método
        $limits = Security::getRateLimit($endpoint, $method);
        $limit = $limits['limit'];
        $window = $limits['window'];

        // Gera identificador único para esta combinação
        $identifier = $this->generateIdentifier($ipAddress, $userId, $endpoint);

        // Verifica e atualiza contador
        $rateLimit = $this->incrementAndGetDetails($identifier, $ipAddress, $userId, $endpoint, $window);
        $currentHits = (int) $rateLimit['hits'];
        $retryAfter = $this->secondsUntilReset($rateLimit['expires_at'] ?? null, $window);

        // Adiciona headers de rate limit
        $this->addRateLimitHeaders($currentHits, $limit, $window, $retryAfter);

        // Verifica se excedeu o limite
        if ($currentHits > $limit) {
            $this->handleLimitExceeded($request, $ipAddress, $endpoint, $currentHits, $limit, $userId, $retryAfter);
            return false;
        }

        return true;
    }

    /**
     * Normaliza o endpoint removendo parâmetros e IDs
     */
    private function normalizeEndpoint(string $url): string
    {
        // Remove query string
        $url = strtok($url, '?');

        // Remove IDs numéricos no final (ex: /api/clientes/123 -> /api/clientes)
        $url = preg_replace('/\/\d+$/', '', $url);

        return $url;
    }

    /**
     * Gera identificador único para rate limiting
     */
    private function generateIdentifier(string $ipAddress, ?int $userId, string $endpoint): string
    {
        // Se usuário autenticado, usa user_id + endpoint
        // Se não, usa IP + endpoint
        if ($userId) {
            return "user:{$userId}:{$endpoint}";
        }

        return "ip:{$ipAddress}:{$endpoint}";
    }

    /**
     * Incrementa contador e retorna dados atuais da janela
     */
    private function incrementAndGetDetails(
        string $identifier,
        string $ipAddress,
        ?int $userId,
        string $endpoint,
        int $window
    ): array {
        return $this->model->incrementarEObterDetalhes($identifier, $ipAddress, $userId, $endpoint, $window);
    }

    /**
     * Adiciona headers de rate limit à resposta
     */
    private function addRateLimitHeaders(int $current, int $limit, int $window, int $retryAfter): void
    {
        $remaining = max(0, $limit - $current);

        header("X-RateLimit-Limit: {$limit}");
        header("X-RateLimit-Remaining: {$remaining}");
        header("X-RateLimit-Window: {$window}");
        header("X-RateLimit-Reset: {$retryAfter}");
    }

    /**
     * Trata limite excedido
     */
    private function handleLimitExceeded(
        Request $request,
        string $ipAddress,
        string $endpoint,
        int $currentHits,
        int $limit,
        ?int $userId,
        int $retryAfter
    ): void {
        // Loga o evento
        SecurityLogService::logRateLimit(
            $ipAddress,
            $endpoint,
            $currentHits,
            $limit,
            $userId,
            Auth::chave()
        );

        // Headers de rate limit
        header("Retry-After: {$retryAfter}");
        header("X-RateLimit-Remaining: 0");

        // Resposta
        if ($request->isAjax() || str_starts_with($request->path(), '/api/')) {
            Response::json([
                'success' => false,
                'message' => "Muitas requisições. Aguarde {$retryAfter} segundos.",
                'retry_after' => $retryAfter,
                'rate_limited' => true,
            ], 429);
        } else {
            http_response_code(429);
            echo "Muitas requisições. Aguarde {$retryAfter} segundos.";
            exit;
        }
    }

    /**
     * Calcula segundos até o fim da janela usando o horário da aplicação.
     */
    private function secondsUntilReset(?string $expiresAt, int $fallback): int
    {
        if (!$expiresAt) {
            return $fallback;
        }

        $expiresTimestamp = strtotime($expiresAt);
        if ($expiresTimestamp === false) {
            return $fallback;
        }

        return max(1, $expiresTimestamp - time());
    }

    /**
     * Limpa registros expirados
     *
     * @return int Número de registros removidos
     */
    public static function cleanup(): int
    {
        $model = new RateLimit();
        return $model->limparExpirados();
    }

    /**
     * Reseta o rate limit para um identificador
     */
    public static function reset(string $ipAddress, ?int $userId = null, ?string $endpoint = null): void
    {
        $model = new RateLimit();
        $model->resetar($ipAddress, $userId, $endpoint);
    }

    /**
     * Obtém estatísticas de rate limiting
     */
    public static function getStats(): array
    {
        $model = new RateLimit();
        return $model->obterEstatisticas();
    }
}
