<?php
/** Included by test_mcp_integration.php: real Gateway and disposable local OAuth credentials. */
declare(strict_types=1);

function handshakeMcp(string $method, array $params, string $token, ?string $version = '2025-11-25'): array
{
    $body = ['jsonrpc' => '2.0', 'method' => $method, 'params' => (object) $params];
    if (!str_starts_with($method, 'notifications/')) $body['id'] = 1;
    $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream',
        'Authorization' => 'Bearer ' . $token];
    if ($version !== null) $headers['MCP-Protocol-Version'] = $version;
    $response = (new \App\Controllers\Mcp\Gateway())->handle(new \Nyholm\Psr7\ServerRequest('POST',
        \App\Services\Mcp\Config::resource(), $headers, json_encode($body, JSON_THROW_ON_ERROR)));
    return [$response->getStatusCode(), json_decode((string) $response->getBody(), true), $response];
}

$previousErrorLog = ini_get('error_log');
$diagnosticLog = $directory . '/discovery.log';
ini_set('error_log', $diagnosticLog);
try {
    foreach (['2025-03-26', '2025-06-18', '2025-11-25'] as $revision) {
        [$status, $data, $response] = handshakeMcp('initialize', ['protocolVersion' => $revision,
            'capabilities' => (object) [], 'clientInfo' => ['name' => 'fixture', 'version' => '1']], $access, null);
        checkMcp($status === 200 && ($data['result']['protocolVersion'] ?? '') === $revision,
            'Initialize negocia ' . $revision . ': ' . json_encode($data));
        checkMcp(($data['result']['serverInfo']['name'] ?? '') === '7Carros'
            && ($data['result']['serverInfo']['version'] ?? '') === '1.0.2'
            && isset($data['result']['capabilities']['tools']) && !$response->hasHeader('Mcp-Session-Id'),
            'Initialize anuncia identidade e ferramentas sem sessão.');
        [$status, $data, $response] = handshakeMcp('notifications/initialized', [], $access, $revision);
        checkMcp($status === 202 && (string) $response->getBody() === '', 'Notificação aceita sem resposta JSON.');
        [$status, $data, $response] = handshakeMcp('tools/list', [], $access, $revision);
        checkMcp($status === 200 && count($data['result']['tools'] ?? []) === 11 && !$response->hasHeader('Mcp-Session-Id'),
            'Descoberta autenticada sem _meta ou MCP-Method em ' . $revision);
        [$status, $data] = handshakeMcp('tools/call', ['name' => 'listar_clientes', 'arguments' => (object) []], $access, $revision);
        checkMcp($status === 200 && empty($data['result']['isError'])
            && count($data['result']['structuredContent']['dados'] ?? []) === 1,
            'Consulta mantém isolamento com cookie de outro tenant em ' . $revision);
        [$status, $data] = handshakeMcp('tools/call', ['name' => 'obter_cliente', 'arguments' => ['id' => $clients[$b]]], $access, $revision);
        checkMcp($status === 200 && array_key_exists('dados', $data['result']['structuredContent'] ?? [])
            && $data['result']['structuredContent']['dados'] === null, 'Detalhe de outro tenant permanece oculto.');
        [$status] = handshakeMcp('tools/list', [], 'invalid-token', $revision);
        checkMcp($status === 401, 'Cada requisição do fluxo initialize exige bearer válido.');
    }
    [$status, $data] = handshakeMcp('tools/list', [], $access, null);
    checkMcp($status === 200 && count($data['result']['tools'] ?? []) === 11, 'Sem header usa revisão padrão do SDK.');
    [$status, $data] = handshakeMcp('tools/list', [], $access, '2099-01-01');
    checkMcp($status === 400 && isset($data['error']), 'Revisão desconhecida permanece rejeitada.');
    [$status, $data] = handshakeMcp('tools/list', [], $access, '2026-07-28');
    checkMcp($status === 400 && isset($data['error']), 'Revisão moderna continua exigindo metadados.');

    [$status, $limitedTokens] = tokenMcp(grantMcp($a, $employees[$a], $clientId, ['frota:read']));
    checkMcp($status === 200, 'Credencial limitada para descoberta.');
    [$status, $data] = handshakeMcp('tools/list', [], $limitedTokens['access_token']);
    checkMcp($status === 200 && array_column($data['result']['tools'] ?? [], 'name') === ['listar_veiculos', 'obter_veiculo'],
        'Fluxo initialize publica somente ferramentas consentidas.');
    [$status, $data] = handshakeMcp('tools/call', ['name' => 'listar_clientes', 'arguments' => (object) []], $limitedTokens['access_token']);
    checkMcp(isset($data['error']) || !empty($data['result']['isError']), 'Ferramenta fora do catálogo permanece bloqueada.');
    $limitedContext = (new \App\Services\Mcp\TokenValidator($store))->validate($limitedTokens['access_token']);
    $diagnosticTool = new \App\Services\Mcp\ToolService($limitedContext, $store, 'fixture-safe-diagnostic', microtime(true));
    $deniedTool = $diagnosticTool->call('clientes', []);
    $invalidTool = $diagnosticTool->call('veiculos', ['por_pagina' => 101]);
    checkMcp($deniedTool->isError && $invalidTool->isError, 'Defesa da ferramenta mantém autorização e validação de filtros.');
    $store->revokeConnection($limitedContext->chave, $limitedContext->connectionId);
    [$status] = handshakeMcp('tools/list', [], $limitedTokens['access_token']);
    checkMcp($status === 401, 'Revogação bloqueia descoberta no fluxo initialize.');

    foreach (['GET', 'DELETE'] as $method) {
        $response = (new \App\Controllers\Mcp\Gateway())->handle(new \Nyholm\Psr7\ServerRequest($method, \App\Services\Mcp\Config::resource()));
        checkMcp($response->getStatusCode() === 405 && $response->getHeaderLine('Allow') === 'POST, OPTIONS',
            $method . ' não cria stream nem sessão.');
    }
    $response = (new \App\Controllers\Mcp\Gateway())->handle(new \Nyholm\Psr7\ServerRequest('POST', \App\Services\Mcp\Config::resource(),
        ['Authorization' => 'Bearer ' . $access, 'Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream'], str_repeat(' ', 65537)));
    checkMcp($response->getStatusCode() === 413, 'Novo transporte preserva limite de 64 KiB.');

    foreach ([['MCP_ENABLED', 'false', 'true', 503], ['MCP_ISSUER', 'invalid', 'https://mcp.example.test', 503]] as [$key, $value, $restore, $expected]) {
        putenv($key . '=' . $value);
        try {
            $response = (new \App\Controllers\Mcp\Gateway())->handle(new \Nyholm\Psr7\ServerRequest('POST', 'https://mcp.example.test/mcp'));
            checkMcp($response->getStatusCode() === $expected && preg_match('/^[a-f0-9]{32}$/D', $response->getHeaderLine('X-Request-Id')) === 1
                && $response->getHeaderLine('Cache-Control') === 'no-store', 'Resposta antecipada/exceção tem referência e não é cacheável.');
        } finally { putenv($key . '=' . $restore); }
    }
    $response = (new \App\Controllers\Mcp\Gateway())->handle(new \Nyholm\Psr7\ServerRequest('POST', \App\Services\Mcp\Config::resource(),
        ['Origin' => 'https://evil.example', 'MCP-Protocol-Version' => 'private-header-value']));
    checkMcp($response->getStatusCode() === 403 && $response->hasHeader('X-Request-Id'), 'Origem rejeitada tem referência.');
    foreach (['' => 'missing_authorization', 'Bearer private-malformed-value?' => 'invalid_bearer',
        'Bearer invalid-token' => 'token_rejected'] as $header => $reason) {
        $response = (new \App\Controllers\Mcp\Gateway())->handle(new \Nyholm\Psr7\ServerRequest('POST', \App\Services\Mcp\Config::resource(),
            $header === '' ? [] : ['Authorization' => $header]));
        $requestLog = file_get_contents($diagnosticLog);
        checkMcp($response->getStatusCode() === 401 && str_contains($requestLog, '"reason":"' . $reason . '"'),
            'Diagnóstico identifica ' . $reason . ' sem divulgar credencial.');
    }
    $logs = file_get_contents($diagnosticLog);
    checkMcp(str_contains($logs, '[MCP-TOOL]') && str_contains($logs, '"stage":"authorization"') && str_contains($logs, '"stage":"arguments"')
        && str_contains($logs, '"exception":') && str_contains($logs, '"code":')
        && !str_contains($logs, 'SELECT ') && !str_contains($logs, '"arguments":'), 'Falha de ferramenta registra apenas diagnóstico técnico.');
    checkMcp(str_contains($logs, 'request_received') && str_contains($logs, 'authenticated')
        && str_contains($logs, 'transport_response') && str_contains($logs, 'authentication_rejected')
        && str_contains($logs, 'response_ready') && str_contains($logs, 'unavailable'), 'Logs identificam etapas de sucesso e falha.');
    checkMcp(!str_contains($logs, $access) && !str_contains($logs, $limitedTokens['access_token'])
        && !str_contains($logs, 'private-header-value') && !str_contains($logs, 'private-malformed-value')
        && !str_contains($logs, 'invalid-token') && !str_contains($logs, 'evil.example'),
        'Diagnóstico não registra bearer, header arbitrário ou origem rejeitada.');
} finally {
    ini_set('error_log', $previousErrorLog);
}
