<?php

declare(strict_types=1);
namespace App\Services\Mcp\OAuth;

use App\Models\Mcp\Store;
use App\Services\Mcp\Config;
use League\OAuth2\Server\CryptTrait;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\{SignedWith, IssuedBy, PermittedFor};

final class Revoker
{
    use CryptTrait;
    public function revoke(Store $store, string $token, string $clientId): void
    {
        $row = null;
        try {
            $this->setEncryptionKey((string) Config::value('MCP_ENCRYPTION_KEY', ''));
            $payload = json_decode($this->decrypt($token), true, 32, JSON_THROW_ON_ERROR);
            if (($payload['client_id'] ?? null) === $clientId && is_string($payload['refresh_token_id'] ?? null)) {
                $row = $store->credentials('refresh', $payload['refresh_token_id']);
            }
        } catch (\Throwable) {
            try {
                $signer = new Sha256(); $key = InMemory::file((string) Config::value('MCP_PUBLIC_KEY', ''));
                $jwt = Configuration::forAsymmetricSigner($signer, InMemory::plainText('unused'), $key);
                $parsed = $jwt->parser()->parse($token);
                if ($parsed instanceof Plain && $jwt->validator()->validate($parsed,
                    new SignedWith($signer, $key), new IssuedBy(Config::issuer()), new PermittedFor(Config::resource()))
                    && $parsed->claims()->get('client_id') === $clientId) {
                    $row = $store->credentials('access', (string) $parsed->claims()->get('jti'));
                }
            } catch (\Throwable) { /* RFC 7009: unknown tokens also return success. */ }
        }
        if (!$row) return;
        $connection = $store->connection($row['connection_id']);
        if ($connection && $connection['client_id'] === $clientId && $connection['chave'] === $row['chave']) {
            $store->revokeConnection($row['chave'], $row['connection_id']);
            $store->auditConnection($row['connection_id'], 'revoke', 'success');
        }
    }
}
