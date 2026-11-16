<?php

declare(strict_types=1);

namespace App\Services\Mcp;

/** One policy for OAuth discovery, issuance and every credential-backed request. */
final class ClientPolicy
{
    public static function pilotMode(): bool
    {
        $value = Config::value('MCP_PILOT_MODE', 'false');
        if (!in_array($value, ['true', 'false'], true)) {
            throw new \RuntimeException('MCP_PILOT_MODE deve ser true ou false.');
        }
        return $value === 'true';
    }

    public static function employeeIds(): array
    {
        $ids = self::items('MCP_PILOT_EMPLOYEE_IDS');
        foreach ($ids as $id) {
            if (!ctype_digit($id) || (string) (int) $id !== $id || (int) $id < 1) return [];
        }
        return array_map('intval', $ids);
    }

    public static function items(string $key): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', (string) Config::value($key, ''))),
            static fn ($value) => $value !== '')));
    }

    public static function permitsClient(array $client): bool
    {
        if (!Config::enabled() || (int) ($client['enabled'] ?? 0) !== 1) return false;
        if (self::pilotMode()) {
            return self::employeeIds() !== [] && self::items('MCP_PILOT_TENANTS') !== []
                && in_array($client['client_id'], self::items('MCP_PILOT_CLIENT_IDS'), true);
        }
        return Config::value('APP_ENV', 'development') !== 'production' || (int) ($client['homologated'] ?? 0) === 1;
    }

    public static function permitsIdentity(array $client, string $chave, int $employeeId): bool
    {
        return self::permitsClient($client) && Config::enabled($chave)
            && (!self::pilotMode() || in_array($employeeId, self::employeeIds(), true));
    }
}
