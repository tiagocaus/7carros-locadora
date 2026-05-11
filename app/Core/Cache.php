<?php

namespace App\Core;

use Redis;
use Exception;

/**
 * Sistema de Cache com Redis
 *
 * Fornece cache em memória com suporte a multi-tenancy
 * e fallback automático caso Redis não esteja disponível
 */
class Cache
{
    private static ?Redis $redis = null;
    private static bool $enabled = true;
    private static array $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0
    ];

    /**
     * Inicializa a conexão com Redis
     */
    private static function connect(): void
    {
        if (self::$redis !== null) {
            return;
        }

        try {
            self::$redis = new Redis();

            $host = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
            $port = (int)($_ENV['REDIS_PORT'] ?? 6379);
            $timeout = (float)($_ENV['REDIS_TIMEOUT'] ?? 2.5);
            $password = $_ENV['REDIS_PASSWORD'] ?? null;
            $database = (int)($_ENV['REDIS_DATABASE'] ?? 0);

            // Conecta ao Redis
            $connected = self::$redis->connect($host, $port, $timeout);

            if (!$connected) {
                throw new Exception("Não foi possível conectar ao Redis");
            }

            // Autentica se houver senha
            if ($password) {
                self::$redis->auth($password);
            }

            // Seleciona o database
            self::$redis->select($database);

            // Define prefixo global
            $prefix = $_ENV['REDIS_PREFIX'] ?? '7carros_cache:';
            self::$redis->setOption(Redis::OPT_PREFIX, $prefix);

        } catch (Exception $e) {
            self::$enabled = false;

            // Log do erro em desenvolvimento
            if (($_ENV['APP_DEBUG'] ?? false) === true) {
                error_log("Cache Redis Error: " . $e->getMessage());
            }
        }
    }

    /**
     * Gera uma chave de cache com namespace do tenant
     */
    private static function key(string $key, ?string $tenant = null): string
    {
        // Se não passar tenant, tenta obter da sessão
        if ($tenant === null && Session::has('chave')) {
            $tenant = Session::get('chave');
        }

        // Se houver tenant, adiciona ao namespace
        if ($tenant) {
            return "tenant:{$tenant}:{$key}";
        }

        return "global:{$key}";
    }

    /**
     * Obtém um valor do cache
     */
    public static function get(string $key, mixed $default = null, ?string $tenant = null): mixed
    {
        if (!self::$enabled) {
            return $default;
        }

        self::connect();

        try {
            $cacheKey = self::key($key, $tenant);
            $value = self::$redis->get($cacheKey);

            if ($value === false) {
                self::$stats['misses']++;
                return $default;
            }

            self::$stats['hits']++;
            // allowed_classes: false bloqueia gadget chains via deserializacao
            return unserialize($value, ['allowed_classes' => false]);

        } catch (Exception $e) {
            self::$stats['misses']++;
            return $default;
        }
    }

    /**
     * Define um valor no cache
     */
    public static function set(string $key, mixed $value, ?int $ttl = null, ?string $tenant = null): bool
    {
        if (!self::$enabled) {
            return false;
        }

        self::connect();

        try {
            $cacheKey = self::key($key, $tenant);
            $serialized = serialize($value);

            // TTL padrão: 1 hora (3600 segundos)
            $ttl = $ttl ?? (int)($_ENV['CACHE_TTL'] ?? 3600);

            $result = self::$redis->setex($cacheKey, $ttl, $serialized);

            if ($result) {
                self::$stats['sets']++;
            }

            return $result;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Obtém um valor do cache ou executa callback e armazena
     *
     * @param string $key Chave do cache
     * @param int $ttl Tempo de vida em segundos
     * @param callable $callback Função a executar se não houver cache
     * @param string|null $tenant Tenant (chave) para namespace
     * @return mixed
     */
    public static function remember(string $key, int $ttl, callable $callback, ?string $tenant = null): mixed
    {
        // Tenta obter do cache
        $value = self::get($key, null, $tenant);

        if ($value !== null) {
            return $value;
        }

        // Executa callback e armazena
        $value = $callback();
        self::set($key, $value, $ttl, $tenant);

        return $value;
    }

    /**
     * Remove um valor do cache
     */
    public static function forget(string $key, ?string $tenant = null): bool
    {
        if (!self::$enabled) {
            return false;
        }

        self::connect();

        try {
            $cacheKey = self::key($key, $tenant);
            $result = self::$redis->del($cacheKey) > 0;

            if ($result) {
                self::$stats['deletes']++;
            }

            return $result;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Remove múltiplas chaves do cache
     */
    public static function forgetMany(array $keys, ?string $tenant = null): int
    {
        if (!self::$enabled) {
            return 0;
        }

        $deleted = 0;
        foreach ($keys as $key) {
            if (self::forget($key, $tenant)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Remove todas as chaves de um tenant
     */
    public static function flushTenant(?string $tenant = null): int
    {
        if (!self::$enabled) {
            return 0;
        }

        self::connect();

        try {
            // Se não passar tenant, usa o da sessão
            if ($tenant === null && Session::has('chave')) {
                $tenant = Session::get('chave');
            }

            if (!$tenant) {
                return 0;
            }

            // Busca todas as chaves do tenant
            $pattern = self::key('*', $tenant);
            $keys = self::$redis->keys($pattern);

            if (empty($keys)) {
                return 0;
            }

            // Remove todas as chaves
            return self::$redis->del($keys);

        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Limpa todo o cache (USE COM CUIDADO!)
     */
    public static function flush(): bool
    {
        if (!self::$enabled) {
            return false;
        }

        self::connect();

        try {
            return self::$redis->flushDB();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Verifica se uma chave existe no cache
     */
    public static function has(string $key, ?string $tenant = null): bool
    {
        if (!self::$enabled) {
            return false;
        }

        self::connect();

        try {
            $cacheKey = self::key($key, $tenant);
            return self::$redis->exists($cacheKey) > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Incrementa um valor numérico no cache
     */
    public static function increment(string $key, int $value = 1, ?string $tenant = null): int|false
    {
        if (!self::$enabled) {
            return false;
        }

        self::connect();

        try {
            $cacheKey = self::key($key, $tenant);
            return self::$redis->incrBy($cacheKey, $value);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Decrementa um valor numérico no cache
     */
    public static function decrement(string $key, int $value = 1, ?string $tenant = null): int|false
    {
        if (!self::$enabled) {
            return false;
        }

        self::connect();

        try {
            $cacheKey = self::key($key, $tenant);
            return self::$redis->decrBy($cacheKey, $value);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Define múltiplos valores de uma vez
     */
    public static function setMany(array $values, ?int $ttl = null, ?string $tenant = null): bool
    {
        if (!self::$enabled) {
            return false;
        }

        $success = true;
        foreach ($values as $key => $value) {
            if (!self::set($key, $value, $ttl, $tenant)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Obtém múltiplos valores de uma vez
     */
    public static function getMany(array $keys, ?string $tenant = null): array
    {
        if (!self::$enabled) {
            return [];
        }

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = self::get($key, null, $tenant);
        }

        return $result;
    }

    /**
     * Obtém o tempo restante de vida de uma chave (em segundos)
     */
    public static function ttl(string $key, ?string $tenant = null): int
    {
        if (!self::$enabled) {
            return -2;
        }

        self::connect();

        try {
            $cacheKey = self::key($key, $tenant);
            return self::$redis->ttl($cacheKey);
        } catch (Exception $e) {
            return -2;
        }
    }

    /**
     * Obtém estatísticas de uso do cache
     */
    public static function stats(): array
    {
        $total = self::$stats['hits'] + self::$stats['misses'];
        $hitRate = $total > 0 ? round((self::$stats['hits'] / $total) * 100, 2) : 0;

        return [
            'enabled' => self::$enabled,
            'hits' => self::$stats['hits'],
            'misses' => self::$stats['misses'],
            'sets' => self::$stats['sets'],
            'deletes' => self::$stats['deletes'],
            'hit_rate' => $hitRate . '%',
            'total_requests' => $total
        ];
    }

    /**
     * Reseta as estatísticas
     */
    public static function resetStats(): void
    {
        self::$stats = [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0
        ];
    }

    /**
     * Verifica se o cache está habilitado e funcionando
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * Obtém informações do servidor Redis
     */
    public static function info(): array
    {
        if (!self::$enabled) {
            return ['status' => 'disabled'];
        }

        self::connect();

        try {
            $info = self::$redis->info();
            return [
                'status' => 'connected',
                'version' => $info['redis_version'] ?? 'unknown',
                'used_memory' => $info['used_memory_human'] ?? 'unknown',
                'connected_clients' => $info['connected_clients'] ?? 0,
                'total_keys' => self::$redis->dbSize()
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}
