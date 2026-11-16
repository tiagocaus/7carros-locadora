<?php

declare(strict_types=1);
namespace App\Services\Mcp\OAuth;

use App\Services\Mcp\Config;
use League\OAuth2\Server\CryptKeyInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\{EntityTrait, TokenEntityTrait};
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;

final class AccessToken implements AccessTokenEntityInterface
{
    use EntityTrait, TokenEntityTrait;
    private CryptKeyInterface $privateKey;
    public function setPrivateKey(CryptKeyInterface $privateKey): void { $this->privateKey = $privateKey; }
    public function toString(): string
    {
        $jwt = Configuration::forAsymmetricSigner(new Sha256(),
            InMemory::plainText($this->privateKey->getKeyContents(), $this->privateKey->getPassPhrase() ?? ''),
            InMemory::plainText('unused'));
        $now = new \DateTimeImmutable();
        return $jwt->builder()->issuedBy(Config::issuer())->permittedFor(Config::resource())
            ->relatedTo((string) $this->getUserIdentifier())->identifiedBy($this->getIdentifier())
            ->issuedAt($now)->canOnlyBeUsedAfter($now)->expiresAt($this->getExpiryDateTime())
            ->withClaim('client_id', $this->getClient()->getIdentifier())
            ->withClaim('scopes', array_map(fn ($s) => $s->getIdentifier(), $this->getScopes()))
            ->getToken($jwt->signer(), $jwt->signingKey())->toString();
    }
}
