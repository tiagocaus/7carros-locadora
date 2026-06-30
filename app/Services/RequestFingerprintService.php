<?php

namespace App\Services;

use App\Config\Security;
use App\Models\Security\RequestFingerprint;

/**
 * Service para análise de fingerprint de requisições
 *
 * Analisa características das requisições HTTP para detectar
 * comportamento de bots e scrapers.
 */
class RequestFingerprintService
{
    private string $ipAddress;
    private ?int $userId;
    private string $endpoint;
    private array $factors = [];
    private int $score = 0;
    private RequestFingerprint $model;

    public function __construct(string $ipAddress, ?int $userId, string $endpoint)
    {
        $this->ipAddress = $ipAddress;
        $this->userId = $userId;
        $this->endpoint = $endpoint;
        $this->model = new RequestFingerprint();
    }

    /**
     * Analisa a requisição e retorna o score de suspeita
     *
     * @return int Score de 0 (normal) a 100 (definitivamente bot)
     */
    public function analyze(): int
    {
        if (!Security::FINGERPRINT['enabled']) {
            return 0;
        }

        $this->factors = [];
        $this->score = 0;

        // Analisa cada fator
        $this->checkMissingHeaders();
        $this->checkUserAgent();
        $this->checkTimingAnomaly();
        $this->checkSequentialPages();
        $this->checkDatacenterIp();
        $this->checkReferer();

        // Limita score a 100
        $this->score = min(100, $this->score);

        // Registra o fingerprint
        $this->recordFingerprint();

        return $this->score;
    }

    /**
     * Retorna os fatores que contribuíram para o score
     */
    public function getFactors(): array
    {
        return $this->factors;
    }

    /**
     * Retorna o score calculado
     */
    public function getScore(): int
    {
        return $this->score;
    }

    /**
     * Verifica headers ausentes que navegadores normalmente enviam
     */
    private function checkMissingHeaders(): void
    {
        $requiredHeaders = Security::FINGERPRINT['required_browser_headers'];
        $missingHeaders = [];

        foreach ($requiredHeaders as $header) {
            $serverKey = 'HTTP_' . str_replace('-', '_', strtoupper($header));
            if (empty($_SERVER[$serverKey])) {
                $missingHeaders[] = $header;
            }
        }

        if (!empty($missingHeaders)) {
            $weight = Security::FINGERPRINT['weights']['missing_headers'];
            // Peso proporcional à quantidade de headers ausentes
            $penalty = (int) ($weight * (count($missingHeaders) / count($requiredHeaders)));
            $this->score += $penalty;
            $this->factors['missing_headers'] = [
                'headers' => $missingHeaders,
                'penalty' => $penalty,
            ];
        }
    }

    /**
     * Verifica se o User-Agent é suspeito
     */
    private function checkUserAgent(): void
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (Security::isSuspiciousUserAgent($userAgent)) {
            $weight = Security::FINGERPRINT['weights']['suspicious_user_agent'];
            $this->score += $weight;
            $this->factors['suspicious_user_agent'] = [
                'user_agent' => $userAgent,
                'penalty' => $weight,
            ];
        }
    }

    /**
     * Analisa padrões de timing para detectar bots
     *
     * Bots geralmente fazem requisições em intervalos muito regulares
     */
    private function checkTimingAnomaly(): void
    {
        $sampleSize = Security::FINGERPRINT['timing_sample_size'];

        // Obtém últimas requisições deste IP para o endpoint
        $results = $this->model->buscarIntervalos($this->ipAddress, $this->endpoint, $sampleSize);

        if (count($results) < 5) {
            // Não há dados suficientes para análise
            return;
        }

        // Calcula desvio padrão dos intervalos
        $intervals = array_column($results, 'interval_ms');
        $stdDev = $this->calculateStdDev($intervals);

        // Se desvio padrão muito baixo, provavelmente é bot
        $threshold = Security::FINGERPRINT['timing_stddev_threshold'];
        if ($stdDev < $threshold && $stdDev > 0) {
            $weight = Security::FINGERPRINT['weights']['timing_anomaly'];
            $this->score += $weight;
            $this->factors['timing_anomaly'] = [
                'stddev_ms' => round($stdDev, 2),
                'threshold_ms' => $threshold,
                'sample_size' => count($intervals),
                'penalty' => $weight,
            ];
        }
    }

    /**
     * Verifica se está acessando páginas em sequência rápida
     */
    private function checkSequentialPages(): void
    {
        // Obtém o parâmetro de página da requisição
        $page = (int) ($_GET['page'] ?? 0);

        if ($page <= 1) {
            return;
        }

        // Verifica se as últimas requisições foram para páginas sequenciais
        $results = $this->model->buscarPaginasRecentes($this->ipAddress, $this->endpoint, 2);

        if (count($results) < 3) {
            return;
        }

        // Verifica se são páginas sequenciais
        $pages = array_column($results, 'page_number');
        $pages[] = $page;
        sort($pages);

        $isSequential = true;
        for ($i = 1; $i < count($pages); $i++) {
            if ($pages[$i] - $pages[$i - 1] !== 1) {
                $isSequential = false;
                break;
            }
        }

        if ($isSequential && count($pages) >= 4) {
            $weight = Security::FINGERPRINT['weights']['sequential_pages'];
            $this->score += $weight;
            $this->factors['sequential_pages'] = [
                'pages' => $pages,
                'penalty' => $weight,
            ];
        }
    }

    /**
     * Verifica se o IP é de um datacenter conhecido
     */
    private function checkDatacenterIp(): void
    {
        if (Security::isDatacenterIp($this->ipAddress)) {
            $weight = Security::FINGERPRINT['weights']['datacenter_ip'];
            $this->score += $weight;
            $this->factors['datacenter_ip'] = [
                'ip' => $this->ipAddress,
                'penalty' => $weight,
            ];
        }
    }

    /**
     * Verifica inconsistências no Referer
     */
    private function checkReferer(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';

        // Se não há referer mas está acessando API diretamente, suspeito
        if (empty($referer) && str_starts_with($this->endpoint, '/api/')) {
            // Aceita se é a primeira requisição (ex: digitou URL diretamente)
            // Mas para APIs de listagem, é suspeito
            if (preg_match('/^\/api\/(clientes|funcionarios|veiculos)/', $this->endpoint)) {
                $weight = Security::FINGERPRINT['weights']['inconsistent_referer'];
                $this->score += $weight;
                $this->factors['inconsistent_referer'] = [
                    'reason' => 'API access without referer',
                    'penalty' => $weight,
                ];
            }
        }

        // Se referer existe mas é de domínio diferente
        if (!empty($referer)) {
            $refererHost = parse_url($referer, PHP_URL_HOST);
            $currentHost = $_SERVER['HTTP_HOST'] ?? '';

            if ($refererHost && $currentHost && $refererHost !== $currentHost) {
                $weight = Security::FINGERPRINT['weights']['inconsistent_referer'];
                $this->score += $weight;
                $this->factors['inconsistent_referer'] = [
                    'reason' => 'External referer',
                    'referer_host' => $refererHost,
                    'penalty' => $weight,
                ];
            }
        }
    }

    /**
     * Registra o fingerprint da requisição no banco
     */
    private function recordFingerprint(): void
    {
        $now = now();
        $nowMs = (int) (microtime(true) * 1000);

        // Calcula intervalo desde última requisição
        $lastRequest = $this->model->buscarUltimaRequisicao($this->ipAddress, $this->endpoint);
        $intervalMs = null;

        if ($lastRequest) {
            $intervalMs = $nowMs - (int) $lastRequest['request_time_ms'];
        }

        // Obtém número da página
        $pageNumber = isset($_GET['page']) ? (int) $_GET['page'] : null;

        $this->model->registrar([
            'ip_address' => $this->ipAddress,
            'user_id' => $this->userId,
            'endpoint' => $this->endpoint,
            'page_number' => $pageNumber,
            'request_time_ms' => $nowMs,
            'interval_ms' => $intervalMs,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Limpa registros antigos (mantém últimas 24 horas)
        $this->cleanupOldRecords();
    }

    /**
     * Remove registros de fingerprint antigos
     */
    private function cleanupOldRecords(): void
    {
        // Executa limpeza apenas 1% das vezes para não impactar performance
        if (mt_rand(1, 100) !== 1) {
            return;
        }

        $this->model->limparAntigos(24);
    }

    /**
     * Calcula o desvio padrão de um array de valores
     */
    private function calculateStdDev(array $values): float
    {
        $count = count($values);
        if ($count < 2) {
            return 0;
        }

        $mean = array_sum($values) / $count;
        $sumSquaredDiffs = 0;

        foreach ($values as $value) {
            $sumSquaredDiffs += pow($value - $mean, 2);
        }

        return sqrt($sumSquaredDiffs / ($count - 1));
    }

    /**
     * Método estático para análise rápida
     */
    public static function analyzeRequest(string $ipAddress, ?int $userId, string $endpoint): array
    {
        $service = new self($ipAddress, $userId, $endpoint);
        $score = $service->analyze();

        return [
            'score' => $score,
            'factors' => $service->getFactors(),
        ];
    }
}
