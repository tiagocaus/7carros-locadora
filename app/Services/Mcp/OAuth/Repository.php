<?php

declare(strict_types=1);
namespace App\Services\Mcp\OAuth;

use App\Models\Mcp\Store;
use App\Services\Mcp\{Catalog, ClientPolicy};
use League\OAuth2\Server\Entities\{AccessTokenEntityInterface, AuthCodeEntityInterface, ClientEntityInterface, RefreshTokenEntityInterface, ScopeEntityInterface};
use League\OAuth2\Server\Repositories\{AccessTokenRepositoryInterface, AuthCodeRepositoryInterface, ClientRepositoryInterface, RefreshTokenRepositoryInterface, ScopeRepositoryInterface};
use League\OAuth2\Server\Exception\OAuthServerException;

final class Repository implements AccessTokenRepositoryInterface, AuthCodeRepositoryInterface,
    ClientRepositoryInterface, RefreshTokenRepositoryInterface, ScopeRepositoryInterface
{
    public bool $reuseDetected = false;
    public ?string $attemptedConnection = null;
    public function __construct(public readonly Store $store) {}

    public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface
    {
        $row = $this->store->client($clientIdentifier);
        if (!$row || !ClientPolicy::permitsClient($row)) return null;
        return new Client($row);
    }
    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        $row = $this->store->client($clientIdentifier);
        return $row !== null && ClientPolicy::permitsClient($row) && (new Client($row))->supportsGrantType((string) $grantType)
            && ($row['secret_hash'] === null || ($clientSecret !== null && password_verify($clientSecret, $row['secret_hash'])));
    }
    public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface
    {
        return isset(Catalog::SCOPES[$identifier]) ? new Scope($identifier) : null;
    }
    public function finalizeScopes(array $scopes, string $grantType, ClientEntityInterface $clientEntity,
        ?string $userIdentifier = null, ?string $authCodeId = null): array
    {
        $this->attemptedConnection = $userIdentifier;
        try { $c = $this->store->context((string) $userIdentifier, $clientEntity->getIdentifier()); }
        catch (\Throwable) {
            throw OAuthServerException::accessDenied('Autorização indisponível.');
        }
        return array_values(array_filter($scopes, fn ($s) => $c->permits($s->getIdentifier())));
    }
    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, ?string $userIdentifier = null): AccessTokenEntityInterface
    {
        $token = new AccessToken(); $token->setClient($clientEntity);
        if ($userIdentifier !== null) $token->setUserIdentifier($userIdentifier);
        foreach ($scopes as $s) $token->addScope($s);
        return $token;
    }
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        $this->persist('access', $accessTokenEntity, $accessTokenEntity->getUserIdentifier());
    }
    public function getNewAuthCode(): AuthCodeEntityInterface { return new AuthCode(); }
    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        $this->persist('code', $authCodeEntity, $authCodeEntity->getUserIdentifier());
    }
    public function getNewRefreshToken(): ?RefreshTokenEntityInterface { return new RefreshToken(); }
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        $id = (string) $refreshTokenEntity->getAccessToken()->getUserIdentifier();
        $connection = $this->store->connection($id);
        if (!$connection) throw OAuthServerException::accessDenied();
        $absolute = new \DateTimeImmutable($connection['expires_at']);
        if ($refreshTokenEntity->getExpiryDateTime() > $absolute) $refreshTokenEntity->setExpiryDateTime($absolute);
        $this->persist('refresh', $refreshTokenEntity, $id);
    }
    private function persist(string $kind, object $entity, ?string $id): void
    {
        $c = $this->store->context((string) $id);
        $this->store->persistCredential($c, $kind, $entity->getIdentifier(), $entity->getExpiryDateTime());
    }
    public function revokeAccessToken(string $tokenId): void { $this->store->revokeCredential('access', $tokenId); }
    public function revokeAuthCode(string $codeId): void { $this->store->revokeCredential('code', $codeId); }
    public function revokeRefreshToken(string $tokenId): void { $this->store->revokeCredential('refresh', $tokenId); }
    public function isAccessTokenRevoked(string $tokenId): bool { return $this->revoked('access', $tokenId); }
    public function isAuthCodeRevoked(string $codeId): bool { return $this->revoked('code', $codeId); }
    public function isRefreshTokenRevoked(string $tokenId): bool { return $this->revoked('refresh', $tokenId); }
    private function revoked(string $kind, string $id): bool
    {
        $row = $this->store->credentials($kind, $id, $kind !== 'access');
        if (!$row) return true;
        $this->attemptedConnection = $row['connection_id'];
        // Check before League consumes/rotates a valid code or refresh token.
        $owner = $this->store->connection($row['connection_id']);
        if ($owner && $row['revoked_at'] === null) {
            try { (new \App\Models\Mcp\SecurityState())->assertAllowed($owner['chave'], (int) $owner['employee_id']); }
            catch (\App\Services\Mcp\SecurityException) { throw OAuthServerException::accessDenied('Acesso MCP temporariamente suspenso.'); }
        }
        if ($row['revoked_at'] !== null) {
            if ($kind === 'refresh') {
                // Survives invalid_grant: the token endpoint commits this revocation explicitly.
                $this->store->revokeConnection($row['chave'], $row['connection_id']);
                $this->store->auditConnection($row['connection_id'], 'refresh_reuse', 'revoked');
                $this->reuseDetected = true;
            }
            return true;
        }
        if (strtotime($row['expires_at']) <= time()) return true;
        $connection = $this->store->connection($row['connection_id'], $kind !== 'access');
        return !$connection || $connection['chave'] !== $row['chave'] || $connection['revoked_at'] !== null;
    }
}
