<?php

namespace App\Services\Split;

/**
 * Implementacao de split para o gateway Asaas.
 * Usa a API: POST /v3/payments/{id}/split
 * Docs: https://docs.asaas.com/reference/configurar-split-de-cobranca
 */
class AsaasSplitService implements SplitServiceInterface
{
    private array $credentials;
    private bool $sandbox;

    public function __construct(array $credentials, bool $sandbox = false)
    {
        $this->credentials = $credentials;
        $this->sandbox = $sandbox;
    }

    public function suportaSplit(): bool
    {
        return true;
    }

    public function configurarSplit(string $externalId, array $splits): array
    {
        $payload = [
            'splits' => array_map(fn($s) => [
                'walletId' => $s['wallet_id'],
                'fixedValue' => round((float) $s['valor'], 2),
            ], $splits)
        ];

        return $this->request('POST', "/payments/{$externalId}/split", $payload);
    }

    public function removerSplit(string $externalId): array
    {
        return $this->request('DELETE', "/payments/{$externalId}/split");
    }

    public function consultarSplit(string $externalId): array
    {
        return $this->request('GET', "/payments/{$externalId}/split");
    }

    private function getBaseUrl(): string
    {
        return $this->sandbox
            ? 'https://sandbox.asaas.com/api/v3'
            : 'https://api.asaas.com/api/v3';
    }

    /**
     * Executa requisicao HTTP para a API Asaas
     */
    private function request(string $method, string $path, array $data = []): array
    {
        $url = $this->getBaseUrl() . $path;
        $apiKey = $this->credentials['api_key'] ?? '';

        $ch = curl_init();

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            "access_token: {$apiKey}",
        ];

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        switch (strtoupper($method)) {
            case 'POST':
                $curlOptions[CURLOPT_POST] = true;
                if (!empty($data)) {
                    $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;

            case 'DELETE':
                $curlOptions[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;

            case 'GET':
            default:
                break;
        }

        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("[AsaasSplit] cURL error: {$error}");
            return [
                'success' => false,
                'message' => "Erro de conexao: {$error}",
                'raw' => []
            ];
        }

        $decoded = json_decode($response, true) ?? [];

        $success = $httpCode >= 200 && $httpCode < 300;

        if (!$success) {
            $errorMsg = $decoded['errors'][0]['description'] ?? "HTTP {$httpCode}";
            error_log("[AsaasSplit] API error ({$httpCode}): {$errorMsg}");
        }

        return [
            'success' => $success,
            'message' => $success ? 'Split configurado com sucesso' : ($decoded['errors'][0]['description'] ?? "Erro HTTP {$httpCode}"),
            'raw' => $decoded,
            'data' => $decoded
        ];
    }
}
