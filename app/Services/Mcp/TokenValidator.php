<?php

declare(strict_types=1);
namespace App\Services\Mcp;

use App\Models\Mcp\Store;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\{SignedWith, IssuedBy, PermittedFor, StrictValidAt};
use Psr\Clock\ClockInterface;

final class TokenValidator
{
    public function __construct(private readonly Store $store) {}
    public function validate(string $token): Context
    {
        if (strlen($token) > 16384) throw new \RuntimeException('Credencial inválida.');
        $signer = new Sha256();
        $key = InMemory::file((string) Config::value('MCP_PUBLIC_KEY', ''));
        $jwt = Configuration::forAsymmetricSigner($signer, InMemory::plainText('unused'), $key);
        $parsed = $jwt->parser()->parse($token);
        $clock = new class implements ClockInterface {
            public function now(): \DateTimeImmutable { return new \DateTimeImmutable(); }
        };
        if (!$parsed instanceof Plain || !$jwt->validator()->validate($parsed,
            new SignedWith($signer, $key), new IssuedBy(Config::issuer()), new PermittedFor(Config::resource()),
            new StrictValidAt($clock))) throw new \RuntimeException('Credencial inválida.');
        $claims = $parsed->claims();
        $id = $claims->get('jti'); $subject = $claims->get('sub');
        $client = $claims->get('client_id'); $scopes = $claims->get('scopes');
        if (!is_string($id) || !is_string($subject) || !is_string($client) || !is_array($scopes)
            || !array_is_list($scopes) || array_filter($scopes, fn ($s) => !is_string($s))) {
            throw new \RuntimeException('Credencial inválida.');
        }
        $credential = $this->store->credentials('access', $id);
        if (!$credential || $credential['connection_id'] !== $subject || $credential['revoked_at'] !== null
            || strtotime($credential['expires_at']) <= time()) throw new \RuntimeException('Credencial inválida.');
        try { $context = $this->store->context($subject, $client, $scopes); }
        catch (\Throwable $e) {
            $this->store->auditConnection($subject, 'request', 'blocked');
            throw $e;
        }
        if ($context->chave !== $credential['chave']) throw new \RuntimeException('Credencial inválida.');
        return $context;
    }
}
