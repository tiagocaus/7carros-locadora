<?php

declare(strict_types=1);
namespace App\Services\Mcp;

use App\Models\Mcp\Store;
use App\Services\Mcp\OAuth\{Repository, Scope, ServerFactory, User};
use Nyholm\Psr7\{Response, ServerRequest, Uri};
use Psr\Http\Message\ResponseInterface;

final class AuthorizationService
{
    public function __construct(private readonly Store $store) {}

    public function prepare(string $chave, int $employeeId, array $query): array
    {
        if (!Config::enabled($chave)) throw new \RuntimeException('Integração indisponível para esta locadora.');
        if (($query['resource'] ?? null) !== Config::resource() || ($query['code_challenge_method'] ?? null) !== 'S256'
            || !is_string($query['code_challenge'] ?? null) || !preg_match('/^[A-Za-z0-9_-]{43}$/D', $query['code_challenge'])
            || !is_string($query['state'] ?? null) || $query['state'] === '' || strlen($query['state']) > 1024
            || array_filter($query, fn ($v) => !is_string($v))) {
            throw new \InvalidArgumentException('Requisição de autorização inválida.');
        }
        $user = $this->store->employee($chave, $employeeId);
        $permissions = $this->store->permissions($chave, $employeeId);
        if (!$user || $user['status'] !== 'A' || !in_array('mcp.conectar', $permissions, true)) {
            throw new \RuntimeException('Seu usuário não pode conectar aplicativos de IA.', 403);
        }
        $client = $this->store->client($query['client_id'] ?? '');
        if (!$client || !ClientPolicy::permitsIdentity($client, $chave, $employeeId)) {
            throw new \RuntimeException('Aplicativo ou funcionário fora da liberação MCP.', 403);
        }
        // League's native-app validator ignores the port for 127.0.0.1.
        // Enforce our exact, client-scoped callback policy before calling it.
        if (isset($query['redirect_uri'])) ClientRegistry::validateRedirect($query['redirect_uri'], $client['client_id']);
        $repo = new Repository($this->store);
        $auth = ServerFactory::build($repo)->validateAuthorizationRequest((new ServerRequest('GET', Config::issuer() . '/oauth/mcp/authorize'))->withQueryParams($query));
        $scopes = [];
        foreach ($auth->getScopes() as $scope) {
            $id = $scope->getIdentifier();
            if (isset(Catalog::SCOPES[$id]) && in_array(Catalog::SCOPES[$id], $permissions, true)) $scopes[] = $id;
        }
        if (!$scopes) throw new \RuntimeException('Nenhuma categoria disponível para autorização.', 403);
        return ['query' => $query, 'scopes' => $scopes, 'application' => $auth->getClient()->getName(),
            'redirect_origin' => self::redirectOrigin((string) $auth->getRedirectUri(), $auth->getClient()->getIdentifier()),
            'branch_access' => $this->store->consentBranches($chave, $employeeId),
            'chave' => $chave, 'employee_id' => $employeeId, 'expires' => time() + 300];
    }

    public function complete(array $pending, string $chave, int $employeeId, array $approved, bool $allow,
        ?callable $claim = null, ?string $requestId = null): ResponseInterface
    {
        if (($pending['expires'] ?? 0) <= time()) throw new \RuntimeException('Autorização expirada. Reconecte no aplicativo.', 410);
        if (($pending['chave'] ?? '') !== $chave || ($pending['employee_id'] ?? 0) !== $employeeId) {
            throw new \RuntimeException('A identidade da autorização mudou. Reconecte no aplicativo.', 403);
        }
        if (!array_is_list($approved) || array_filter($approved, fn ($s) => !is_string($s))
            || count($approved) !== count(array_unique($approved)) || ($allow && $approved === [])
            || array_diff($approved, $pending['scopes'] ?? [])) {
            throw new \InvalidArgumentException('Categorias de autorização inválidas.');
        }
        $fresh = $this->prepare($chave, $employeeId, $pending['query']);
        if (array_diff($approved, $fresh['scopes'])) {
            throw new \RuntimeException('As permissões mudaram. Reconecte no aplicativo.', 403);
        }
        // Claim only after validation, before any issuance. Never restore a claimed attempt.
        if ($claim !== null) $claim();
        $issuedId = null;
        $response = $this->store->transaction(function () use ($fresh, $chave, $employeeId, $approved, $allow, $requestId, &$issuedId) {
            $repo = new Repository($this->store); $server = ServerFactory::build($repo);
            $auth = $server->validateAuthorizationRequest((new ServerRequest('GET', Config::issuer() . '/oauth/mcp/authorize'))->withQueryParams($fresh['query']));
            $auth->setAuthorizationApproved($allow && $approved !== []);
            if ($auth->isAuthorizationApproved()) {
                $id = $this->store->createConnection($chave, $employeeId, $auth->getClient()->getIdentifier(), array_values($approved));
                $issuedId = $id;
                $context = $this->store->context($id);
                $auth->setUser(new User($id));
                $auth->setScopes(array_map(fn ($s) => new Scope($s), $approved));
                $response = $server->completeAuthorizationRequest($auth, new Response());
                $this->store->context($id); // suspension during consent/code issuance blocks release
                $this->store->audit($context, 'authorize', 'success', $requestId ?? bin2hex(random_bytes(16)));
            } else {
                $auth->setUser(new User('denied'));
                try { $response = $server->completeAuthorizationRequest($auth, new Response()); }
                catch (\League\OAuth2\Server\Exception\OAuthServerException $e) {
                    $response = $e->generateHttpResponse(new Response());
                }
            }
            $uri = new Uri($response->getHeaderLine('Location'));
            if ($uri->getHost() !== '') {
                parse_str($uri->getQuery(), $query); $query['iss'] = Config::issuer();
                $response = $response->withHeader('Location', (string) $uri->withQuery(http_build_query($query)));
            }
            return $response->withStatus(303)->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer');
        });
        // A fresh read after commit observes a suspension concurrent with code issuance.
        if ($issuedId !== null) $this->store->context($issuedId);
        return $response;
    }

    private static function redirectOrigin(string $redirect, string $clientId): string
    {
        // Called exclusively after the OAuth library validates the exact registered URI.
        ClientRegistry::validateRedirect($redirect, $clientId);
        $uri = new Uri($redirect);
        $host = $uri->getHost();
        if (!preg_match('/\A(?:[a-z0-9][a-z0-9.-]*|\[[0-9a-f:]+\])\z/i', $host)) {
            throw new \InvalidArgumentException('Origem de retorno inválida.');
        }
        return $uri->getScheme() . '://' . $host . ($uri->getPort() === null ? '' : ':' . $uri->getPort());
    }
}
