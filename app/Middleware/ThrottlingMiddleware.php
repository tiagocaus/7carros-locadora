<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Config\Security;
use App\Services\RequestFingerprintService;
use App\Services\SecurityLogService;

/**
 * Middleware de Throttling
 *
 * Analisa o fingerprint da requisição e adiciona delay artificial
 * para requisições suspeitas. Também pode bloquear se o score for muito alto.
 */
class ThrottlingMiddleware
{
    /**
     * Executa o middleware
     */
    public function handle(Request $request): bool
    {
        if (!Security::THROTTLE['enabled'] || !Security::FINGERPRINT['enabled']) {
            return true;
        }

        $ipAddress = $request->ip();
        $userId = Auth::id();
        $endpoint = $request->path();

        // Analisa fingerprint da requisição
        $analysis = RequestFingerprintService::analyzeRequest($ipAddress, $userId, $endpoint);
        $score = $analysis['score'];
        $factors = $analysis['factors'];

        // Armazena score na requisição para uso posterior
        $request->setSecurityScore($score);
        $request->setSecurityFactors($factors);

        // Determina ação baseada no score
        $thresholds = Security::FINGERPRINT['thresholds'];

        if ($score > $thresholds['very_suspicious']) {
            // Score muito alto - bloqueia
            return $this->handleBot($request, $ipAddress, $endpoint, $score, $factors, $userId);
        }

        if ($score > $thresholds['suspicious']) {
            // Score alto - throttle pesado + log
            $this->logSuspicious($ipAddress, $endpoint, $score, $factors, $userId, 'heavy_throttle');
        } elseif ($score > $thresholds['normal']) {
            // Score médio - throttle leve
            $this->logSuspicious($ipAddress, $endpoint, $score, $factors, $userId, 'light_throttle');
        }

        // Aplica delay se necessário
        $delay = Security::getThrottleDelay($score);
        if ($delay > 0) {
            usleep($delay * 1000); // Converte para microsegundos
        }

        return true;
    }

    /**
     * Trata requisição identificada como bot
     */
    private function handleBot(
        Request $request,
        string $ipAddress,
        string $endpoint,
        int $score,
        array $factors,
        ?int $userId
    ): bool {
        // Loga evento
        SecurityLogService::logFingerprint(
            $ipAddress,
            $endpoint,
            $score,
            $factors,
            'blocked',
            $userId,
            Auth::chave()
        );

        // Bloqueia IP temporariamente
        BlockedIpMiddleware::blockIp(
            $ipAddress,
            'Comportamento automatizado detectado (score: ' . $score . ')',
            Security::BLOCKED_IP['temp_block_duration']
        );

        // Loga bloqueio
        SecurityLogService::logBlock(
            $ipAddress,
            $endpoint,
            'Bot detected',
            Security::BLOCKED_IP['temp_block_duration'],
            $userId,
            Auth::chave()
        );

        // Responde
        if ($request->isAjax() || str_starts_with($request->path(), '/api/')) {
            Response::json([
                'success' => false,
                'message' => 'Comportamento suspeito detectado. Acesso temporariamente bloqueado.',
                'blocked' => true,
            ], 403);
        } else {
            http_response_code(403);
            echo 'Comportamento suspeito detectado. Acesso temporariamente bloqueado.';
            exit;
        }

        return false;
    }

    /**
     * Loga comportamento suspeito
     */
    private function logSuspicious(
        string $ipAddress,
        string $endpoint,
        int $score,
        array $factors,
        ?int $userId,
        string $action
    ): void {
        SecurityLogService::logFingerprint(
            $ipAddress,
            $endpoint,
            $score,
            $factors,
            $action,
            $userId,
            Auth::chave()
        );
    }

    /**
     * Verifica se uma requisição é suspeita (método estático)
     *
     * @return array ['suspicious' => bool, 'score' => int, 'factors' => array]
     */
    public static function checkRequest(Request $request): array
    {
        $ipAddress = $request->ip();
        $userId = Auth::id();
        $endpoint = $request->url();

        $analysis = RequestFingerprintService::analyzeRequest($ipAddress, $userId, $endpoint);
        $thresholds = Security::FINGERPRINT['thresholds'];

        return [
            'suspicious' => $analysis['score'] > $thresholds['normal'],
            'very_suspicious' => $analysis['score'] > $thresholds['suspicious'],
            'is_bot' => $analysis['score'] > $thresholds['very_suspicious'],
            'score' => $analysis['score'],
            'factors' => $analysis['factors'],
        ];
    }
}
