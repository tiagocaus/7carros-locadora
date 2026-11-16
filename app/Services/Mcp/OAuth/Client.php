<?php

declare(strict_types=1);
namespace App\Services\Mcp\OAuth;

use App\Services\Mcp\ClientRegistry;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\{EntityTrait, ClientTrait};

final class Client implements ClientEntityInterface
{
    use EntityTrait, ClientTrait;
    private array $grants = ['authorization_code', 'refresh_token'];
    public function __construct(array $row)
    {
        $this->identifier = $row['client_id'];
        $this->name = $row['name'];
        $this->redirectUri = json_decode($row['redirect_uris'], true, 32, JSON_THROW_ON_ERROR);
        $this->isConfidential = $row['secret_hash'] !== null;
        $metadata = $row['metadata'] === null ? null : json_decode($row['metadata'], true, 32, JSON_THROW_ON_ERROR);
        if (isset($metadata['document'])) {
            $capabilities = ClientRegistry::capabilities($metadata['document']);
            if ($this->isConfidential || (isset($metadata['negotiated']) && $metadata['negotiated'] !== $capabilities)) {
                throw new \RuntimeException('Configuração OAuth divergente do CIMD aprovado.');
            }
            $this->grants = $capabilities['grant_types'];
        }
    }
    public function supportsGrantType(string $grantType): bool
    {
        return in_array($grantType, $this->grants, true);
    }
}
