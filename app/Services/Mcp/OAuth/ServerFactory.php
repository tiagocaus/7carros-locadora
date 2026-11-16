<?php

declare(strict_types=1);
namespace App\Services\Mcp\OAuth;

use App\Services\Mcp\Config;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\{AuthCodeGrant, RefreshTokenGrant};

final class ServerFactory
{
    public static function build(Repository $repo): AuthorizationServer
    {
        if (strlen((string) Config::value('MCP_ENCRYPTION_KEY', '')) < 32) {
            throw new \RuntimeException('Configure uma chave MCP de criptografia com pelo menos 32 caracteres.');
        }
        $server = new AuthorizationServer($repo, $repo, $repo,
            (string) Config::value('MCP_PRIVATE_KEY', ''), (string) Config::value('MCP_ENCRYPTION_KEY', ''));
        $codes = new AuthCodeGrant($repo, $repo, new \DateInterval('PT60S'));
        $codes->setRefreshTokenTTL(new \DateInterval('P90D'));
        $refresh = new RefreshTokenGrant($repo);
        $refresh->setRefreshTokenTTL(new \DateInterval('P90D'));
        $server->enableGrantType($codes, new \DateInterval('PT10M'));
        $server->enableGrantType($refresh, new \DateInterval('PT10M'));
        return $server;
    }
}
