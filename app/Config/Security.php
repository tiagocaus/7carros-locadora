<?php

namespace App\Config;

/**
 * Configuração de Segurança Anti-Scraping
 *
 * Define as configurações para proteção contra bots, scraping e abusos de API.
 * Inclui rate limiting, fingerprinting, quotas e honeypots.
 */
class Security
{
    /**
     * Configurações de Rate Limiting
     */
    public const RATE_LIMIT = [
        'enabled' => true,
        'default_limit' => 60,      // Requisições por janela
        'default_window' => 60,     // Janela em segundos

        // Limites específicos por endpoint (regex pattern)
        'endpoints' => [
            '/api/clientes' => ['limit' => 30, 'window' => 60],
            '/api/funcionarios' => ['limit' => 30, 'window' => 60],
            '/api/financeiro' => ['limit' => 120, 'window' => 60],
            '/api/veiculos' => ['limit' => 30, 'window' => 60],
            '/api/roles' => ['limit' => 20, 'window' => 60],
            '/webhook/whmcs' => ['limit' => 10, 'window' => 60],
        ],

        // Limites por método HTTP
        'methods' => [
            'GET' => ['limit' => 60, 'window' => 60],
            'POST' => ['limit' => 20, 'window' => 60],
            'PUT' => ['limit' => 20, 'window' => 60],
            'DELETE' => ['limit' => 10, 'window' => 60],
        ],
    ];

    /**
     * Configurações de Fingerprinting de Requisição
     */
    public const FINGERPRINT = [
        'enabled' => true,

        // Thresholds de score de suspeita (0-100)
        'thresholds' => [
            'normal' => 30,           // 0-30: sem restrição
            'suspicious' => 50,       // 31-50: ativa throttling
            'very_suspicious' => 70,  // 51-70: throttling + alerta
            'bot' => 100,             // 71-100: bloqueia
        ],

        // Pesos para cada fator de análise
        'weights' => [
            'missing_headers' => 20,      // Falta Accept-Language, Accept-Encoding
            'suspicious_user_agent' => 30, // curl, wget, python-requests, vazio
            'timing_anomaly' => 25,        // Intervalos exatos (ex: cada 1000ms)
            'sequential_pages' => 15,      // Acessa page=1,2,3,4... rapidamente
            'datacenter_ip' => 40,         // IPs de datacenter/proxy conhecidos
            'inconsistent_referer' => 10,  // Referer não bate com navegação
        ],

        // User-agents suspeitos (regex patterns)
        'suspicious_user_agents' => [
            '/^curl\//i',
            '/^wget\//i',
            '/python-requests/i',
            '/python-urllib/i',
            '/^Java\//i',
            '/^Go-http-client/i',
            '/scrapy/i',
            '/bot/i',
            '/spider/i',
            '/crawler/i',
            '/^$/i',  // vazio
        ],

        // Headers que devem estar presentes em navegadores reais
        'required_browser_headers' => [
            'Accept',
            'Accept-Language',
            'Accept-Encoding',
        ],

        // Variação de timing considerada anormal (desvio padrão em ms)
        'timing_stddev_threshold' => 50,  // Se desvio < 50ms, provável bot

        // Quantidade de requisições para análise de timing
        'timing_sample_size' => 10,
    ];

    /**
     * Configurações de Throttling
     */
    public const THROTTLE = [
        'enabled' => true,

        // Delays em milissegundos por faixa de score
        'delays' => [
            'normal' => 0,           // Score 0-30
            'suspicious' => 500,     // Score 31-50: 0.5s
            'very_suspicious' => 2000, // Score 51-70: 2s
            'bot' => 5000,           // Score 71+: 5s (antes de bloquear)
        ],
    ];

    /**
     * Configurações de Quotas por Plano
     */
    public const QUOTA = [
        'enabled' => true,

        // Limites de registros acessados por dia
        'records_per_day' => [
            'G' => 200,         // Gratuito
            'P0' => 500,        // Junior
            'P1' => 1000,       // Iniciante
            'P2' => 2000,       // Intermediário
            'P3' => 5000,       // Avançado
            'P4' => 10000,      // Ilimitado
            'P6' => 10000,      // Ilimitado Mb
        ],

        // Limites de exportações por dia
        'exports_per_day' => [
            'G' => 1,
            'P0' => 2,
            'P1' => 5,
            'P2' => 10,
            'P3' => 20,
            'P4' => PHP_INT_MAX,  // Sem limite
            'P6' => PHP_INT_MAX,
        ],
    ];

    /**
     * Configurações de Honeypot
     */
    public const HONEYPOT = [
        'enabled' => true,

        // Duração do ban em segundos (24 horas)
        'ban_duration' => 86400,

        // Endpoints armadilha (não existem no sistema real)
        'trap_endpoints' => [
            '/api/v2/clientes',
            '/api/v2/users',
            '/api/users',
            '/api/clientes/export-all',
            '/api/clientes/dump',
            '/api/admin/dump',
            '/api/database/export',
            '/api/backup',
            '/.env',
            '/wp-admin',
            '/wp-login.php',
            '/phpinfo.php',
            '/admin.php',
        ],
    ];

    /**
     * Configurações de IPs Bloqueados
     */
    public const BLOCKED_IP = [
        // Tempo de bloqueio temporário em segundos (1 hora)
        'temp_block_duration' => 3600,

        // IPs sempre bloqueados (lista estática)
        'permanent_blocks' => [
            // Adicionar IPs conhecidos de atacantes
        ],

        // Ranges de IP de datacenters conhecidos (para aumentar score)
        'datacenter_ranges' => [
            // AWS
            '3.0.0.0/8',
            '13.0.0.0/8',
            '18.0.0.0/8',
            '52.0.0.0/8',
            '54.0.0.0/8',
            // Google Cloud
            '35.0.0.0/8',
            '34.0.0.0/8',
            // DigitalOcean
            '104.131.0.0/16',
            '104.236.0.0/16',
            // Vultr
            '45.32.0.0/16',
            '45.63.0.0/16',
            // OVH
            '51.0.0.0/8',
            '54.36.0.0/16',
        ],
    ];

    /**
     * Configurações de Detecção Cross-Tenant
     */
    public const CROSS_TENANT = [
        'enabled' => true,

        // Tabelas monitoradas para tentativas cross-tenant
        'monitored_tables' => [
            'clientes',
            'contratos',
            'veiculos',
            'financeiro',
            'funcionarios',
            'reservas',
            'manutencoes',
        ],

        // Limite de tentativas antes de marcar como suspeito
        'attempt_threshold' => 5,

        // Janela de tempo para contar tentativas (segundos)
        'attempt_window' => 300, // 5 minutos

        // TTL do cache de verificação (segundos)
        'cache_ttl' => 60,

        // Score de suspeita por tentativa
        'score_per_attempt' => 15,

        // Score máximo
        'max_score' => 100,
    ];

    /**
     * Configurações de Logging
     */
    public const LOGGING = [
        'enabled' => true,

        // Eventos que devem ser logados
        'log_events' => [
            'rate_limit' => true,          // Rate limit excedido
            'fingerprint' => true,         // Score de suspeita alto
            'quota' => true,               // Quota excedida
            'honeypot' => true,            // Acesso a honeypot
            'block' => true,               // IP bloqueado
            'suspicious' => true,          // Comportamento suspeito
            'cross_tenant_attempt' => true, // Tentativa de acesso cross-tenant
        ],

        // Retenção de logs em dias
        'retention_days' => 30,
    ];

    /**
     * Obtém limite de rate para um endpoint específico
     */
    public static function getRateLimit(string $endpoint, string $method = 'GET'): array
    {
        $config = self::RATE_LIMIT;

        // Verifica se há configuração específica para o endpoint
        foreach ($config['endpoints'] as $pattern => $limits) {
            if (str_starts_with($endpoint, $pattern)) {
                return $limits;
            }
        }

        // Verifica limite por método
        if (isset($config['methods'][$method])) {
            return $config['methods'][$method];
        }

        // Retorna padrão
        return [
            'limit' => $config['default_limit'],
            'window' => $config['default_window'],
        ];
    }

    /**
     * Obtém quota de registros por dia para um plano
     */
    public static function getRecordsQuota(string $plano): int
    {
        return self::QUOTA['records_per_day'][$plano] ?? self::QUOTA['records_per_day']['G'];
    }

    /**
     * Obtém quota de exportações por dia para um plano
     */
    public static function getExportsQuota(string $plano): int
    {
        return self::QUOTA['exports_per_day'][$plano] ?? self::QUOTA['exports_per_day']['G'];
    }

    /**
     * Verifica se um User-Agent é suspeito
     */
    public static function isSuspiciousUserAgent(string $userAgent): bool
    {
        foreach (self::FINGERPRINT['suspicious_user_agents'] as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verifica se um endpoint é honeypot
     */
    public static function isHoneypotEndpoint(string $endpoint): bool
    {
        return in_array($endpoint, self::HONEYPOT['trap_endpoints'], true);
    }

    /**
     * Obtém delay de throttling baseado no score
     */
    public static function getThrottleDelay(int $score): int
    {
        $thresholds = self::FINGERPRINT['thresholds'];
        $delays = self::THROTTLE['delays'];

        return match (true) {
            $score > $thresholds['very_suspicious'] => $delays['bot'],
            $score > $thresholds['suspicious'] => $delays['very_suspicious'],
            $score > $thresholds['normal'] => $delays['suspicious'],
            default => $delays['normal'],
        };
    }

    /**
     * Verifica se um IP está em range de datacenter
     */
    public static function isDatacenterIp(string $ip): bool
    {
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return false;
        }

        foreach (self::BLOCKED_IP['datacenter_ranges'] as $range) {
            [$subnet, $bits] = explode('/', $range);
            $subnetLong = ip2long($subnet);
            $mask = -1 << (32 - (int) $bits);

            if (($ipLong & $mask) === ($subnetLong & $mask)) {
                return true;
            }
        }

        return false;
    }
}
