<?php

namespace App\Services;

use App\Core\Database;

class WhoisJsonService
{
    private const ENDPOINT = 'https://whoisjson.com/api/v1/domain-availability';
    private const CONNECT_TIMEOUT = 2;
    private const REQUEST_TIMEOUT = 5;

    private readonly ?\Closure $httpClient;
    private readonly string $apiKey;

    public function __construct(?callable $httpClient = null, ?string $apiKey = null)
    {
        $this->httpClient = $httpClient !== null ? \Closure::fromCallable($httpClient) : null;
        $this->apiKey = trim($apiKey ?? Database::env('APIWHOISJSON_API_KEY', ''));
    }

    /**
     * Consulta a disponibilidade de registro de um dominio via WhoisJSON.
     *
     * @return array{dominio:string,disponivel:bool|null}
     */
    public function verificarDisponibilidade(string $dominio): array
    {
        $dominioNormalizado = $this->normalizarDominio($dominio);
        if ($dominioNormalizado === '') {
            throw new \InvalidArgumentException('Dominio invalido');
        }

        if ($this->apiKey === '') {
            throw new \RuntimeException('APIWHOISJSON_API_KEY nao configurada');
        }

        $url = self::ENDPOINT . '?' . http_build_query(
            ['domain' => $dominioNormalizado],
            '',
            '&',
            PHP_QUERY_RFC3986
        );
        $headers = [
            'Accept: application/json',
            'Authorization: TOKEN=' . $this->apiKey,
        ];

        $response = $this->httpClient !== null
            ? ($this->httpClient)($url, $headers, self::CONNECT_TIMEOUT, self::REQUEST_TIMEOUT)
            : $this->request($url, $headers);

        $curlErrno = (int) ($response['curl_errno'] ?? 0);
        if ($curlErrno !== 0) {
            throw new \RuntimeException('Falha de conexao com WhoisJSON (cURL ' . $curlErrno . ')');
        }

        $status = (int) ($response['status'] ?? 0);
        if ($status !== 200) {
            throw new \RuntimeException('WhoisJSON retornou HTTP ' . $status);
        }

        $payload = json_decode((string) ($response['body'] ?? ''), true);
        if (!is_array($payload) || !array_key_exists('available', $payload)) {
            throw new \RuntimeException('Resposta invalida do WhoisJSON');
        }

        $available = $payload['available'];
        if ($available === true || $available === false) {
            $disponivel = $available;
        } elseif ($available === 'unknown' || $available === null) {
            $disponivel = null;
        } else {
            throw new \RuntimeException('Status de disponibilidade invalido do WhoisJSON');
        }

        return [
            'dominio' => $dominioNormalizado,
            'disponivel' => $disponivel,
        ];
    }

    /**
     * @return array{status:int,body:string,curl_errno:int}
     */
    private function request(string $url, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
        ]);

        $body = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $status,
            'body' => $body === false ? '' : (string) $body,
            'curl_errno' => $curlErrno,
        ];
    }

    private function normalizarDominio(string $dominio): string
    {
        $dominio = strtolower(trim($dominio));
        $dominio = preg_replace('#^https?://#i', '', $dominio);
        $dominio = preg_split('/[\/?#]/', $dominio)[0] ?? '';
        $dominio = rtrim(trim($dominio), '.');

        if (str_starts_with($dominio, 'www.')) {
            $dominio = substr($dominio, 4);
        }

        if (
            $dominio === ''
            || !str_contains($dominio, '.')
            || filter_var($dominio, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
        ) {
            return '';
        }

        return $dominio;
    }
}
