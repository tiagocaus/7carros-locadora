<?php

declare(strict_types=1);

namespace App\Services\Mcp;

use App\Models\Mcp\Store;

/** Operator-only report. No tokens, secrets, signatures or private key contents leave this class. */
final class Readiness
{
    public static function keys(): bool
    {
        try {
            $privatePath = (string) Config::value('MCP_PRIVATE_KEY', '');
            $publicPath = (string) Config::value('MCP_PUBLIC_KEY', '');
            if (!str_starts_with($privatePath, 'file:///') || !str_starts_with($publicPath, '/')) return false;
            $privatePath = substr($privatePath, 7);
            if (!is_readable($privatePath) || !is_readable($publicPath)) return false;
            $mode = fileperms($privatePath);
            if ($mode === false || ($mode & 0077) !== 0) return false;
            $private = openssl_pkey_get_private(file_get_contents($privatePath));
            $public = openssl_pkey_get_public(file_get_contents($publicPath));
            if ($private === false || $public === false) return false;
            $details = openssl_pkey_get_details($private);
            if (!$details || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] < 2048) return false;
            $signature = '';
            return openssl_sign('7carros-mcp-readiness', $signature, $private, OPENSSL_ALGO_SHA256)
                && openssl_verify('7carros-mcp-readiness', $signature, $public, OPENSSL_ALGO_SHA256) === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function report(Store $store, string $clientId, ?array $document, string $username): array
    {
        $checks = [];
        $checks['php_8_3_ou_superior'] = PHP_VERSION_ID >= 80300;
        foreach (['openssl', 'sodium', 'mysqli', 'curl'] as $extension) $checks['ext_' . $extension] = extension_loaded($extension);
        $checks['sdk_oauth_psr7'] = class_exists(\Mcp\Server\Transport\StreamableHttpTransport::class)
            && class_exists(\League\OAuth2\Server\AuthorizationServer::class) && class_exists(\Nyholm\Psr7\Factory\Psr17Factory::class);
        try { Config::issuer(); Config::origins(); $checks['issuer_origens'] = true; }
        catch (\Throwable) { $checks['issuer_origens'] = false; }
        $checks['segredo_configurado'] = strlen((string) Config::value('MCP_ENCRYPTION_KEY', '')) >= 32;
        $checks['chaves_legiveis_restritas_e_correspondentes'] = self::keys();
        $checks['migration_mcp'] = $store->mcpMigrationApplied();
        $client = $store->registeredClient($clientId);
        $checks['cliente_aprovado_habilitado'] = $client !== null && (int) $client['enabled'] === 1;
        $metadata = $client && $client['metadata'] !== null ? json_decode($client['metadata'], true, 32, JSON_THROW_ON_ERROR) : [];
        $checks['documento_cimd_igual_ao_aprovado'] = $document === null
            ? !isset($metadata['document'])
            : isset($metadata['document']) && ClientRegistry::fingerprint($document) === ClientRegistry::fingerprint($metadata['document']);
        $pilot = ClientPolicy::pilotMode();
        $checks['politica_cliente'] = $client !== null && ($pilot
            ? ClientPolicy::employeeIds() !== [] && in_array($clientId, ClientPolicy::items('MCP_PILOT_CLIENT_IDS'), true)
            : (Config::value('APP_ENV', 'development') !== 'production' || (int) $client['homologated'] === 1));
        $tenants = ClientPolicy::items('MCP_PILOT_TENANTS');
        $checks['locadoras_configuradas'] = $tenants !== [];
        $resolved = [];
        foreach ($tenants as $index => $chave) {
            $employee = $store->employeeByUsername($chave, $username);
            if (!$employee) continue;
            $id = (int) $employee['id'];
            $resolved[] = ['piloto' => $index + 1, 'employee_id' => $id, 'ativo' => $employee['status'] === 'A',
                'mcp_conectar' => in_array('mcp.conectar', $store->permissions($chave, $id), true),
                'incluido_no_piloto' => !$pilot || in_array($id, ClientPolicy::employeeIds(), true)];
        }
        $checks['funcionario_autorizado'] = count($resolved) === 1 && $resolved[0]['ativo']
            && $resolved[0]['mcp_conectar'] && $resolved[0]['incluido_no_piloto'];
        $checks['piloto_exclusivo_do_funcionario'] = !$pilot || (count($resolved) === 1 && ClientPolicy::employeeIds() === [$resolved[0]['employee_id']]);
        return ['ready_to_enable' => !in_array(false, $checks, true), 'mcp_enabled' => Config::enabled(),
            'pilot_mode' => $pilot, 'client_id' => $clientId, 'checks' => $checks, 'employee' => $resolved,
            'negotiated' => $document === null ? null : ClientRegistry::capabilities($document),
            'note' => 'Diagnóstico CLI sem gravações. HTTP externo, PHP web e homologação real são verificações separadas.'];
    }
}
