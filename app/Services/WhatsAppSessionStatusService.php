<?php

namespace App\Services;

use App\Core\Database;

/**
 * Consulta e valida o estado operacional de uma sessao WhatsApp na WuzAPI.
 *
 * Falhas de transporte ou respostas fora do contrato sao inconclusivas: elas
 * nunca devem ser interpretadas como confirmacao de desconexao.
 */
class WhatsAppSessionStatusService
{
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_CONNECTING = 'connecting';
    public const STATUS_DISCONNECTED = 'disconnected';

    private const DISCONNECT_CONFIRMATION_DELAY_US = 1_000_000;

    private string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? Database::env('WHATSAPP_API_URL', ''), '/');
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '';
    }

    /**
     * @return array{conclusive: bool, status: ?string, owner: ?string, reason: ?string, http_code: int, curl_errno: int, attempts: int}
     */
    public function consultar(string $instanceToken): array
    {
        if (!$this->isConfigured()) {
            return $this->inconclusive('not_configured');
        }

        $response = $this->requestStatus($instanceToken);
        $httpCode = (int) ($response['http_code'] ?? 0);
        $curlErrno = (int) ($response['curl_errno'] ?? 0);

        if ($curlErrno !== 0) {
            return $this->inconclusive('curl_error', $httpCode, $curlErrno);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return $this->inconclusive('http_error', $httpCode);
        }

        $body = $response['body'] ?? null;
        if (!is_string($body) || trim($body) === '') {
            return $this->inconclusive('empty_body', $httpCode);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            return $this->inconclusive('invalid_json', $httpCode);
        }

        if (($decoded['success'] ?? null) !== true) {
            return $this->inconclusive('provider_unsuccessful', $httpCode);
        }

        $providerCode = $decoded['code'] ?? null;
        if (!is_int($providerCode) || $providerCode < 200 || $providerCode >= 300) {
            return $this->inconclusive('invalid_envelope', $httpCode);
        }

        $data = $decoded['data'] ?? null;
        if (!is_array($data)) {
            return $this->inconclusive('invalid_payload', $httpCode);
        }

        $connected = $this->booleanField($data, 'Connected', 'connected');
        $loggedIn = $this->booleanField($data, 'LoggedIn', 'loggedIn');
        if ($connected === null || $loggedIn === null) {
            return $this->inconclusive('invalid_status_fields', $httpCode);
        }

        $status = self::STATUS_DISCONNECTED;
        if ($loggedIn) {
            $status = self::STATUS_CONNECTED;
        } elseif ($connected) {
            $status = self::STATUS_CONNECTING;
        }

        return [
            'conclusive' => true,
            'status' => $status,
            'owner' => $this->ownerFromPayload($data),
            'reason' => null,
            'http_code' => $httpCode,
            'curl_errno' => 0,
            'attempts' => 1,
        ];
    }

    /**
     * Confirma uma resposta negativa para nao persistir oscilacoes momentaneas.
     *
     * @return array{conclusive: bool, status: ?string, owner: ?string, reason: ?string, http_code: int, curl_errno: int, attempts: int}
     */
    public function consultarComConfirmacao(string $instanceToken): array
    {
        $first = $this->consultar($instanceToken);
        if (!$first['conclusive'] || $first['status'] !== self::STATUS_DISCONNECTED) {
            return $first;
        }

        $this->pauseBeforeConfirmation();
        $second = $this->consultar($instanceToken);
        $second['attempts'] = 2;

        if (!$second['conclusive']) {
            $second['reason'] = 'disconnect_confirmation_' . ($second['reason'] ?? 'inconclusive');
        }

        return $second;
    }

    public static function diagnostic(array $result): string
    {
        $details = [(string) ($result['reason'] ?? 'unknown')];
        $httpCode = (int) ($result['http_code'] ?? 0);
        $curlErrno = (int) ($result['curl_errno'] ?? 0);

        if ($httpCode > 0) {
            $details[] = "http={$httpCode}";
        }
        if ($curlErrno > 0) {
            $details[] = "curl={$curlErrno}";
        }

        return implode(', ', $details);
    }

    /**
     * Ponto de extensao para testes sem chamadas reais ao provedor.
     *
     * @return array{http_code: int, body: string|false, curl_errno: int}
     */
    protected function requestStatus(string $instanceToken): array
    {
        $ch = curl_init($this->baseUrl . '/session/status');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'token: ' . $instanceToken,
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        return [
            'http_code' => $httpCode,
            'body' => $body,
            'curl_errno' => $curlErrno,
        ];
    }

    protected function pauseBeforeConfirmation(): void
    {
        usleep(self::DISCONNECT_CONFIRMATION_DELAY_US);
    }

    private function booleanField(array $data, string $pascalCase, string $camelCase): ?bool
    {
        if (array_key_exists($pascalCase, $data)) {
            return is_bool($data[$pascalCase]) ? $data[$pascalCase] : null;
        }
        if (array_key_exists($camelCase, $data)) {
            return is_bool($data[$camelCase]) ? $data[$camelCase] : null;
        }

        return null;
    }

    private function ownerFromPayload(array $data): ?string
    {
        foreach (['Jid', 'jid', 'remoteJid', 'remote_jid', 'owner'] as $field) {
            $value = $data[$field] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function inconclusive(string $reason, int $httpCode = 0, int $curlErrno = 0): array
    {
        return [
            'conclusive' => false,
            'status' => null,
            'owner' => null,
            'reason' => $reason,
            'http_code' => $httpCode,
            'curl_errno' => $curlErrno,
            'attempts' => 1,
        ];
    }
}
