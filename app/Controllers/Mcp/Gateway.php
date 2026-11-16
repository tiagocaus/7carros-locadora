<?php

declare(strict_types=1);
namespace App\Controllers\Mcp;

use App\Models\Mcp\Store;
use App\Services\Mcp\{Catalog, Config, RateLimiter, TokenValidator, ToolService};
use App\Services\Mcp\OAuth\{Repository, Revoker, ServerFactory};
use League\OAuth2\Server\Exception\OAuthServerException;
use Mcp\Server\Transport\StreamableHttpTransport;
use Mcp\Server\Transport\Http\Middleware\{CorsMiddleware, DnsRebindingProtectionMiddleware};
use Nyholm\Psr7\{Factory\Psr17Factory, Response};
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};

/** Dedicated machine routes run before browser Session::start(). No database connections here. */
final class Gateway
{
    public const PATHS = ['/mcp', '/oauth/mcp/token', '/oauth/mcp/revoke',
        '/.well-known/oauth-protected-resource/mcp', '/.well-known/oauth-authorization-server'];

    public static function request(): ServerRequestInterface
    {
        $factory = new Psr17Factory();
        $request = (new ServerRequestCreator($factory, $factory, $factory, $factory))->fromGlobals();
        // The factory adds Host from the URI and the creator appends the SAPI Host.
        // Remove only that construction artifact, preserving conflicting input for rejection.
        $hosts = $request->getHeader('Host');
        $sourceHost = $_SERVER['HTTP_HOST'] ?? '';
        if (count($hosts) > 1 && is_string($sourceHost) && $sourceHost !== ''
            && $hosts[0] === $request->getUri()->getAuthority()
            && array_values(array_unique(array_slice($hosts, 1))) === [$sourceHost]) {
            $request = $request->withHeader('Host', $sourceHost);
        }
        // Some SAPIs expose CGI variables that getallheaders() does not include.
        // Keep an existing header authoritative; never read credentials from URLs.
        if (!$request->hasHeader('Authorization')) {
            $headers = ServerRequestCreator::getHeadersFromServer($_SERVER);
            if (isset($headers['authorization'])) $request = $request->withHeader('Authorization', $headers['authorization']);
        }
        return $request;
    }

    public static function emit(ResponseInterface $response): void
    {
        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) foreach ($values as $value) header($name . ': ' . $value, false);
        echo $response->getBody();
    }

    public static function json(array $data, int $status = 200): ResponseInterface
    {
        return new Response($status, ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store'],
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $requestId = bin2hex(random_bytes(16));
        $started = microtime(true);
        $method = in_array($request->getMethod(), ['GET', 'POST', 'OPTIONS', 'DELETE', 'PUT', 'PATCH', 'HEAD'], true)
            ? $request->getMethod() : 'other';
        $path = in_array($request->getUri()->getPath(), self::PATHS, true) ? $request->getUri()->getPath() : 'other';
        $version = $request->getHeaderLine('MCP-Protocol-Version');
        self::trace($requestId, 'request_received', ['http_method' => $method, 'path' => $path,
            'revision' => preg_match('/^\d{4}-\d{2}-\d{2}$/D', $version) ? $version : ($version === '' ? 'absent' : 'invalid')]);
        try {
            $response = $this->handleRequest($request, $requestId);
        } catch (\App\Services\Mcp\SecurityException $e) {
            $response = self::json($e->payload(), 403)->withHeader('Retry-After', (string) $e->retryAfter);
        } catch (OAuthServerException $e) {
            self::trace($requestId, 'oauth_rejected', ['exception' => get_class($e)]);
            $response = self::json(['error' => $e->getErrorType()], $e->getHttpStatusCode());
        } catch (\Throwable $e) {
            self::trace($requestId, 'unavailable', ['exception' => get_class($e)]);
            $response = self::json(['error' => 'temporarily_unavailable', 'request_id' => $requestId], 503);
        }
        self::trace($requestId, 'response_ready', ['status' => $response->getStatusCode(),
            'duration_ms' => (int) ((microtime(true) - $started) * 1000)]);
        return $response->withHeader('X-Request-Id', $requestId)->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Cache-Control', 'no-store');
    }

    /** Only fixed stages and explicitly selected technical fields; never log request bodies or credentials. */
    private static function trace(string $requestId, string $stage, array $fields = []): void
    {
        error_log('[MCP] ' . json_encode(['request_id' => $requestId, 'stage' => $stage] + $fields,
            JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function handleRequest(ServerRequestInterface $request, string $requestId): ResponseInterface
    {
        if (!Config::enabled()) return self::json(['error' => 'temporarily_unavailable'], 503);
        if ($request->getUri()->getScheme() !== 'https' || strtolower($request->getUri()->getHost()) !== strtolower((string) parse_url(Config::issuer(), PHP_URL_HOST))) {
            return self::json(['error' => 'invalid_origin'], 403);
        }
        $origin = $request->getHeaderLine('Origin');
        if ($origin !== '' && !in_array($origin, Config::origins(), true)) return self::json(['error' => 'invalid_origin'], 403);
        $response = $this->dispatch($request, $request->getUri()->getPath(), $requestId);
        if ($origin !== '') $response = $response->withHeader('Access-Control-Allow-Origin', $origin)->withHeader('Vary', 'Origin');
        return $response;
    }

    private function dispatch(ServerRequestInterface $request, string $path, string $requestId): ResponseInterface
    {
        $method = $request->getMethod();
        if ($method === 'OPTIONS') return new Response(204, ['Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type, MCP-Protocol-Version, MCP-Method, MCP-Name', 'Cache-Control' => 'no-store']);
        if ($path === '/.well-known/oauth-protected-resource/mcp' && $method === 'GET') {
            return self::json(['resource' => Config::resource(), 'authorization_servers' => [Config::issuer()],
                'scopes_supported' => array_values(array_filter(array_keys(Catalog::SCOPES), fn ($s) => str_ends_with($s, ':read'))), 'bearer_methods_supported' => ['header']]);
        }
        if ($path === '/.well-known/oauth-authorization-server' && $method === 'GET') {
            return self::json(['issuer' => Config::issuer(), 'authorization_endpoint' => Config::issuer() . '/oauth/mcp/authorize',
                'token_endpoint' => Config::issuer() . '/oauth/mcp/token', 'revocation_endpoint' => Config::issuer() . '/oauth/mcp/revoke',
                'response_types_supported' => ['code'], 'grant_types_supported' => ['authorization_code', 'refresh_token'],
                'code_challenge_methods_supported' => ['S256'], 'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post'],
                'revocation_endpoint_auth_methods_supported' => ['none', 'client_secret_post'],
                'authorization_response_iss_parameter_supported' => true, 'client_id_metadata_document_supported' => true,
                'scopes_supported' => array_keys(Catalog::SCOPES)]);
        }
        if ($method !== 'POST') return self::json(['error' => 'method_not_allowed'], 405)
            ->withHeader('Allow', $path === '/mcp' ? 'POST, OPTIONS' : 'POST');
        if ($path === '/mcp') return $this->mcp($request, $requestId);
        if (!str_starts_with(strtolower($request->getHeaderLine('Content-Type')), 'application/x-www-form-urlencoded')) {
            return self::json(['error' => 'invalid_request'], 415);
        }
        if ($request->getBody()->getSize() !== null && $request->getBody()->getSize() > 65536) return self::json(['error' => 'invalid_request'], 413);
        if (!(new RateLimiter())->consume('oauth:' . ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown'), 'oauth')) {
            return self::json(['error' => 'slow_down'], 429)->withHeader('Retry-After', '60');
        }
        $params = $request->getParsedBody();
        if (!is_array($params) || array_filter($params, fn ($v) => !is_string($v))) return self::json(['error' => 'invalid_request'], 400);
        $store = new Store(); $repo = new Repository($store);
        if ($path === '/oauth/mcp/revoke') {
            if (!$repo->getClientEntity($params['client_id'] ?? '')
                || !$repo->validateClient($params['client_id'] ?? '', $params['client_secret'] ?? null, 'refresh_token')) {
                return self::json(['error' => 'invalid_client'], 401);
            }
            (new Revoker())->revoke($store, $params['token'] ?? '', $params['client_id']);
            return new Response(200, ['Cache-Control' => 'no-store']);
        }
        if (($params['resource'] ?? '') !== Config::resource()
            || !in_array($params['grant_type'] ?? '', ['authorization_code', 'refresh_token'], true)) {
            return self::json(['error' => 'invalid_target'], 400);
        }
        // Repositories lock credentials before consumption; all issuance and consumption are atomic.
        try {
            $response = $store->transaction(function () use ($repo, $request) {
                try { return ServerFactory::build($repo)->respondToAccessTokenRequest($request, new Response()); }
                catch (OAuthServerException $e) {
                    if ($repo->reuseDetected) return self::json(['error' => 'invalid_grant'], 400);
                    throw $e;
                }
            });
        } catch (OAuthServerException $e) {
            if ($repo->attemptedConnection !== null) $store->auditConnection($repo->attemptedConnection, 'token', 'blocked');
            throw $e;
        }
        if ($response->getStatusCode() === 200) {
            $issued = json_decode((string) $response->getBody(), true, 32, JSON_THROW_ON_ERROR);
            // Outside the issuance transaction: read current status, not its MVCC snapshot.
            try {
                $context = (new TokenValidator($store))->validate($issued['access_token']);
                $store->audit($context, 'token', 'success', $requestId);
            } catch (\Throwable) {
                return self::json(['error' => 'access_denied'], 403);
            }
        }
        return $response;
    }

    private function mcp(ServerRequestInterface $request, string $requestId): ResponseInterface
    {
        $started = microtime(true);
        set_time_limit(15);
        $store = new Store();
        $header = $request->getHeaderLine('Authorization');
        $reason = $header === '' ? 'missing_authorization' : 'invalid_bearer';
        try {
            if (!preg_match('/^Bearer ([A-Za-z0-9._~-]+)$/D', $header, $m)) throw new \RuntimeException();
            $reason = 'token_rejected';
            $context = (new TokenValidator($store))->validate($m[1]);
        } catch (\App\Services\Mcp\SecurityException $e) {
            throw $e;
        } catch (\Throwable $e) {
            self::trace($requestId, 'authentication_rejected', ['status' => 401, 'reason' => $reason, 'exception' => get_class($e)]);
            return self::json(['error' => 'invalid_token'], 401)->withHeader('WWW-Authenticate',
                'Bearer resource_metadata="' . Config::issuer() . '/.well-known/oauth-protected-resource/mcp", error="invalid_token"');
        }
        self::trace($requestId, 'authenticated');
        if (!(new RateLimiter())->consume($context->chave, $context->connectionId)) {
            $store->audit($context, 'request', 'rate_limited', $requestId);
            return self::json(['error' => 'rate_limited'], 429)->withHeader('Retry-After', '60');
        }
        $factory = new Psr17Factory();
        $server = (new ToolService($context, $store, $requestId, $started))->server();
        $response = $server->run(new StreamableHttpTransport($request, $factory, $factory, maxBodyBytes: 65536,
            middleware: [new CorsMiddleware(Config::origins(), allowedMethods: ['POST']),
                new DnsRebindingProtectionMiddleware([(string) parse_url(Config::issuer(), PHP_URL_HOST)], Config::origins())]));
        self::trace($requestId, 'transport_response', ['status' => $response->getStatusCode()]);
        try { $after = (new TokenValidator($store))->validate($m[1]); }
        catch (\App\Services\Mcp\SecurityException $e) { throw $e; }
        catch (\Throwable) {
            $store->audit($context, 'request', 'blocked', $requestId);
            return self::json(['error' => 'access_denied'], 403);
        }
        if ($after != $context || microtime(true) - $started >= 15) {
            $store->audit($context, 'request', 'blocked', $requestId);
            return self::json(['error' => 'access_denied'], 403);
        }
        $store->touch($context);
        $store->audit($context, 'request', $response->getStatusCode() < 400 ? 'success' : 'blocked', $requestId,
            0, (int) ((microtime(true) - $started) * 1000));
        return $response;
    }
}
