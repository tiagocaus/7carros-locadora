<?php

declare(strict_types=1);

namespace App\Services\Mcp;

use App\Core\Database;

final class Config
{
    public static function value(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        return $value === false ? Database::env($key, $default) : $value;
    }

    public static function issuer(): string
    {
        $url = rtrim((string) self::value('MCP_ISSUER', ''), '/');
        $parts = parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || !empty($parts['path'])) {
            throw new \RuntimeException('Configure MCP_ISSUER como origem HTTPS sem caminho.');
        }
        return $url;
    }

    public static function resource(): string
    {
        return self::issuer() . '/mcp';
    }

    public static function enabled(?string $chave = null): bool
    {
        if (self::value('MCP_ENABLED', 'false') !== 'true') {
            return false;
        }
        if ($chave === null) {
            return true;
        }
        $pilots = array_filter(array_map('trim', explode(',', (string) self::value('MCP_PILOT_TENANTS', ''))));
        return in_array($chave, $pilots, true);
    }

    public static function origins(): array
    {
        $origins = array_values(array_unique(array_filter(array_merge([self::issuer()],
            array_map('trim', explode(',', (string) self::value('MCP_ALLOWED_ORIGINS', '')))))));
        foreach ($origins as $origin) {
            $p = parse_url($origin);
            if (!$p || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || !empty($p['path'])
                || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment'])) {
                throw new \RuntimeException('Origens MCP devem ser origens HTTPS exatas, sem wildcard.');
            }
        }
        return $origins;
    }
}
