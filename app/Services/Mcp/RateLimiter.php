<?php

declare(strict_types=1);
namespace App\Services\Mcp;

/** Single-server deployment: one exclusive tenant lock includes connection counters. */
final class RateLimiter
{
    public function consume(string $tenant, string $connection): bool
    {
        $directory = dirname(__DIR__, 3) . '/storage/cache/mcp-rate';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Limite de requisições indisponível.');
        }
        $file = fopen($directory . '/' . hash('sha256', $tenant) . '.json', 'c+');
        if (!$file || !flock($file, LOCK_EX)) throw new \RuntimeException('Limite de requisições indisponível.');
        try {
            $raw = stream_get_contents($file);
            $data = $raw === '' ? [] : json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            $now = time();
            $data = array_filter($data, fn ($v) => is_array($v) && ($v['until'] ?? 0) > $now);
            $tenantLimit = max(1, (int) Config::value('MCP_TENANT_RATE_LIMIT', 300));
            $connectionLimit = max(1, (int) Config::value('MCP_CONNECTION_RATE_LIMIT', 60));
            $keys = ['tenant' => $tenantLimit, hash('sha256', $connection) => $connectionLimit];
            foreach ($keys as $key => $limit) if (($data[$key]['count'] ?? 0) >= $limit) return false;
            foreach ($keys as $key => $limit) {
                $data[$key] ??= ['count' => 0, 'until' => $now + 60];
                $data[$key]['count']++;
            }
            $encoded = json_encode($data, JSON_THROW_ON_ERROR);
            rewind($file);
            if (!ftruncate($file, 0) || fwrite($file, $encoded) !== strlen($encoded) || !fflush($file)) {
                throw new \RuntimeException('Limite de requisições indisponível.');
            }
            return true;
        } finally { flock($file, LOCK_UN); fclose($file); }
    }
}
