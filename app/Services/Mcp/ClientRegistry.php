<?php

declare(strict_types=1);
namespace App\Services\Mcp;

/** Approval is an operator action. Metadata never registers or approves an application itself. */
final class ClientRegistry
{
    public static function capabilities(array $document): array
    {
        // The plural field is authoritative. A malformed plural field must not
        // silently fall back to the legacy singular preference.
        $methods = array_key_exists('token_endpoint_auth_methods_supported', $document)
            ? self::stringList($document['token_endpoint_auth_methods_supported'])
            : self::stringList([$document['token_endpoint_auth_method'] ?? 'none']);
        if (!in_array('none', $methods, true)) {
            throw new \InvalidArgumentException('CIMD não oferece um método de autenticação compatível.');
        }
        $advertised = self::stringList($document['grant_types'] ?? ['authorization_code']);
        $grants = array_values(array_intersect(['authorization_code', 'refresh_token'], $advertised));
        if (!in_array('authorization_code', $grants, true)) {
            throw new \InvalidArgumentException('CIMD deve suportar authorization_code.');
        }
        if (isset($document['response_types']) && !in_array('code', self::stringList($document['response_types']), true)) {
            throw new \InvalidArgumentException('CIMD deve suportar a resposta code.');
        }
        return ['token_endpoint_auth_method' => 'none', 'grant_types' => $grants];
    }

    private static function stringList(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value) || $value === []) {
            throw new \InvalidArgumentException('Lista de capacidades CIMD inválida.');
        }
        foreach ($value as $item) {
            if (!is_string($item) || $item === '' || strlen($item) > 512 || preg_match('/[\x00-\x20\x7f]/', $item)) {
                throw new \InvalidArgumentException('Capacidade CIMD inválida.');
            }
        }
        return array_values(array_unique($value));
    }

    public static function validateDocument(string $clientId, array $document): array
    {
        if (($document['client_id'] ?? null) !== $clientId || !is_string($document['client_name'] ?? null)
            || trim($document['client_name']) === '' || mb_strlen($document['client_name']) > 150) {
            throw new \InvalidArgumentException('Identificação CIMD inválida.');
        }
        $redirects = self::stringList($document['redirect_uris'] ?? null);
        if (count($redirects) > 20) throw new \InvalidArgumentException('Retornos CIMD excedidos.');
        foreach ($redirects as $uri) self::validateRedirect($uri);
        self::capabilities($document);
        return $document;
    }

    public static function fingerprint(array $document): string
    {
        $canonical = static function (mixed $value) use (&$canonical): mixed {
            if (!is_array($value)) return $value;
            if (!array_is_list($value)) ksort($value);
            return array_map($canonical, $value);
        };
        return hash('sha256', json_encode($canonical($document), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public static function envelope(array $document): array
    {
        return ['document' => $document, 'negotiated' => self::capabilities($document),
            'document_sha256' => self::fingerprint($document), 'verified_at' => date(DATE_ATOM)];
    }

    public static function validateRedirect(string $uri): void
    {
        $p = parse_url($uri);
        if (!$p || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user'])
            || isset($p['pass']) || isset($p['fragment']) || preg_match('/[\x00-\x20\x7f]/', $uri)) {
            throw new \InvalidArgumentException('O retorno deve ser uma URL HTTPS exata, sem fragmento ou credenciais.');
        }
    }

    public static function validateMetadataUrl(string $url, array $allowedHosts): array
    {
        $p = parse_url($url);
        if (!$p || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || empty($p['path']) || $p['path'] === '/'
            || isset($p['user']) || isset($p['pass']) || isset($p['fragment']) || isset($p['query'])
            || (isset($p['port']) && $p['port'] !== 443) || !in_array(strtolower($p['host']), $allowedHosts, true)
            || filter_var($p['host'], FILTER_VALIDATE_IP) || preg_match('/[\x00-\x20\x7f]/', $url)) {
            throw new \InvalidArgumentException('URL CIMD fora dos destinos HTTPS aprovados.');
        }
        return $p;
    }

    public static function publicIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
        $packed = inet_pton($ip);
        $blocked = strlen($packed) === 4
            ? ['0.0.0.0/8', '100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4']
            : ['2001::/23', '2001:db8::/32', '2002::/16', '3fff::/20'];
        if (strlen($packed) === 16 && (ord($packed[0]) & 0xe0) !== 0x20) return false;
        foreach ($blocked as $cidr) {
            [$network, $bits] = explode('/', $cidr); $network = inet_pton($network); $match = true;
            for ($i = 0; $i < (int) $bits; $i++) {
                $byte = intdiv($i, 8); $mask = 1 << (7 - $i % 8);
                if ((ord($packed[$byte]) & $mask) !== (ord($network[$byte]) & $mask)) { $match = false; break; }
            }
            if ($match) return false;
        }
        return true;
    }

    public function fetch(string $clientId): array
    {
        $hosts = array_values(array_filter(array_map(fn ($s) => strtolower(trim($s)),
            explode(',', (string) Config::value('MCP_CIMD_HOSTS', '')))));
        $url = $clientId;
        for ($redirect = 0; $redirect <= 3; $redirect++) {
            $p = self::validateMetadataUrl($url, $hosts);
            $dns = dns_get_record($p['host'], DNS_A | DNS_AAAA);
            $ips = [];
            foreach ($dns ?: [] as $record) {
                $ip = $record['ip'] ?? $record['ipv6'] ?? null;
                if ($ip !== null) {
                    if (!self::publicIp($ip)) {
                        throw new \RuntimeException('CIMD resolveu para endereço não público.');
                    }
                    $ips[] = $ip;
                }
            }
            if (!$ips) throw new \RuntimeException('Destino CIMD não resolvido.');
            $body = ''; $location = ''; $type = '';
            $curl = curl_init($url);
            curl_setopt_array($curl, [CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROXY => '',
                CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_RESOLVE => [$p['host'] . ':443:' . (str_contains($ips[0], ':') ? '[' . $ips[0] . ']' : $ips[0])],
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$body): int {
                    if (strlen($body) + strlen($chunk) > 65536) return 0;
                    $body .= $chunk; return strlen($chunk);
                },
                CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$location, &$type): int {
                    if (stripos($line, 'Location:') === 0) $location = trim(substr($line, 9));
                    if (stripos($line, 'Content-Type:') === 0) $type = strtolower(trim(substr($line, 13)));
                    return strlen($line);
                },
            ]);
            $success = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl);
            if ($success === false) throw new \RuntimeException('Falha ao buscar CIMD.');
            if (in_array($status, [301, 302, 303, 307, 308], true) && $location !== '' && $redirect < 3) {
                // Absolute URLs only; every new destination is allowlisted, resolved and pinned again.
                $url = $location; continue;
            }
            if ($status !== 200 || !str_starts_with($type, 'application/json')) throw new \RuntimeException('Resposta CIMD inválida.');
            $doc = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($doc)) throw new \RuntimeException('Metadados CIMD inválidos.');
            return self::validateDocument($clientId, $doc);
        }
        throw new \RuntimeException('Redirecionamentos CIMD excedidos.');
    }
}
