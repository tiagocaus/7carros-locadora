<?php
/** Included by test_mcp_integration.php while disposable fixtures exist. */
declare(strict_types=1);
use App\Services\Mcp\Config;
$listener = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
if (!$listener) throw new RuntimeException('Não foi possível reservar porta de teste HTTP.');
$address = stream_socket_get_name($listener, false); fclose($listener);
$sessionDirectory = $directory . '/http-sessions'; mkdir($sessionDirectory, 0700);
$serverLog = $directory . '/http-server.log';
$previousConnectionLimit = getenv('MCP_CONNECTION_RATE_LIMIT');
$previousTenantLimit = getenv('MCP_TENANT_RATE_LIMIT');
putenv('MCP_CONNECTION_RATE_LIMIT=1000'); putenv('MCP_TENANT_RATE_LIMIT=5000');
$environment = array_merge(getenv(), ['MCP_CONSENT_TEST_DIRECTORY' => $sessionDirectory,
    'MCP_CONSENT_TEST_TENANT' => $a, 'MCP_CONSENT_TEST_EMPLOYEE' => (string) $employees[$a]]);
$process = proc_open([PHP_BINARY, '-S', $address, __DIR__ . '/fixtures/mcp-consent-router.php'],
    [0 => ['pipe', 'r'], 1 => ['file', $serverLog, 'a'], 2 => ['file', $serverLog, 'a']], $pipes, dirname(__DIR__), $environment);
if (!is_resource($process)) throw new RuntimeException('Servidor HTTP de teste não iniciou.');
fclose($pipes[0]);
$cookie = '';
$http = static function (string $path, ?array $post = null) use ($address, &$cookie): array {
    $headers = ['Accept: text/html'];
    if ($cookie !== '') $headers[] = 'Cookie: ' . $cookie;
    if ($post !== null) $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    $options = ['method' => $post === null ? 'GET' : 'POST', 'header' => implode("\r\n", $headers),
        'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10];
    if ($post !== null) $options['content'] = http_build_query($post);
    $body = file_get_contents('http://' . $address . $path, false, stream_context_create(['http' => $options]));
    $responseHeaders = []; $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+ (\d+)/', $header, $matches)) $status = (int) $matches[1];
        elseif (str_contains($header, ':')) {
            [$key, $value] = explode(':', $header, 2); $responseHeaders[strtolower($key)] = trim($value);
            if (strtolower($key) === 'set-cookie') $cookie = explode(';', trim($value))[0];
        }
    }
    return [$status, $body, $responseHeaders];
};
try {
    $ready = false;
    for ($attempt = 0; $attempt < 40; $attempt++) {
        $socket = @stream_socket_client('tcp://' . $address, $err, $msg, 0.1);
        if ($socket) { fclose($socket); $ready = true; break; }
        usleep(50000);
    }
    checkMcp($ready, 'Servidor HTTP local iniciou.');
    [$status] = $http('/api/logs/mcp');
    checkMcp($status === 403, 'API da auditoria MCP exige logs.visualizar.');
    [$status] = $http('/api/logs/mcp/1');
    checkMcp($status === 403, 'Detalhe exige logs.visualizar.');
    $auditPermission = $qb->table('permissions')->withoutChave()->where('`key`', '=', 'logs.visualizar')->first();
    checkMcp($auditPermission !== null, 'Permissão existente de logs disponível no schema local.');
    $qb->table('funcionarios_role_permissions')->withoutChave()->insert(['role_id' => $roles[0], 'permission_id' => $auditPermission['id']]);
    try {
        [$status, $body] = $http('/api/logs/mcp?perPage=2');
        $auditResponse = json_decode($body, true);
        checkMcp($status === 200 && count($auditResponse['data'] ?? []) === 2
            && $auditResponse['pagination']['total'] === null && $auditResponse['pagination']['hasNext'],
            'API entrega auditoria com paginação sem contagem total e permissão atual.');
        $auditRows = $auditResponse['data'];
        checkMcp(count(array_diff(array_column($auditRows, 'evento'), \App\Models\McpAuditLog::tools())) === 0
            && isset($auditRows[0]['operacao']) && !isset($auditRows[0]['chave']), 'API publica somente operações traduzidas e campos resumidos.');
        $payloadRow = $auditRows[0];
        $httpQuestion = "  Pergunta fiel? Á🚙\n" . str_repeat('ç🚙', 400);
        $longPayload = json_encode(['argumentos' => ['pergunta_usuario' => $httpQuestion], 'dados' => [str_repeat('Á🚙', 400)]], JSON_UNESCAPED_UNICODE);
        $qb->table('mcp_audit')->withChave($a)->where('id', '=', $payloadRow['id'])
            ->update(['request_payload' => $longPayload, 'response_payload' => $longPayload]);
        [$status, $body] = $http('/api/logs/mcp?perPage=2');
        $preview = json_decode($body, true)['data'][0];
        checkMcp(mb_strlen($preview['solicitacao_previa']) === 255 && str_ends_with($preview['solicitacao_previa'], '…')
            && !isset($preview['solicitacao']) && !isset($preview['retorno']) && $preview['conteudo_disponivel'] === true,
            'API limita prévias a 255 caracteres Unicode e não envia conteúdo completo.');
        checkMcp($preview['pergunta_previa'] === mb_substr($httpQuestion, 0, 254) . '…' && !isset($preview['pergunta_usuario']), 'API retorna só prévia da pergunta com 255 caracteres Unicode.');
        [$status, $body] = $http('/api/logs/mcp/' . $payloadRow['id']);
        $detailResponse = json_decode($body, true);
        checkMcp($status === 200 && $detailResponse['data']['retorno'] === $longPayload && $detailResponse['data']['pergunta_usuario'] === $httpQuestion
            && isset($detailResponse['data']['registros']), 'API de detalhe retorna conteúdo completo e quantidade.');
        $foreignId = $qb->table('mcp_audit')->withChave($b)->insert([
            'chave' => $b, 'employee_id' => $employees[$b], 'connection_id' => 'http-audit-fixture',
            'client_id' => $clientId, 'request_id' => bin2hex(random_bytes(16)), 'event' => 'listar_veiculos',
            'result' => 'success', 'response_payload' => $longPayload,
        ]);
        [$foreignStatus, $foreignBody] = $http('/api/logs/mcp/' . $foreignId);
        [$missingStatus, $missingBody] = $http('/api/logs/mcp/4294967295');
        [$invalidStatus] = $http('/api/logs/mcp/invalid');
        $qb->table('mcp_audit')->withChave($b)->where('id', '=', $foreignId)->delete();
        checkMcp($foreignStatus === 404 && $missingStatus === 404 && $invalidStatus === 404 && $foreignBody === $missingBody,
            'IDs ausentes, inválidos e de outro tenant não revelam dados.');
        [$status, $body] = $http('/api/logs/mcp?' . http_build_query(['perPage' => 2, 'page' => 2]));
        checkMcp($status === 200 && !array_intersect(array_column($auditRows, 'id'), array_column(json_decode($body, true)['data'], 'id')),
            'API pagina sem duplicar chamadas.');
        [$status, $body] = $http('/api/logs/mcp?' . http_build_query(['search' => 'Listar veículos']));
        $auditSearch = json_decode($body, true);
        checkMcp($status === 200 && $auditSearch['pagination']['total'] > 0
            && count(array_diff(array_column($auditSearch['data'], 'evento'), ['listar_veiculos'])) === 0, 'Busca reconhece operação traduzida.');
        [$status, $body] = $http('/api/logs/mcp?' . http_build_query(['search' => 'no-match-' . $run]));
        checkMcp($status === 200 && json_decode($body, true)['data'] === [], 'API retorna estado vazio na busca sem resultados.');
    } finally {
        $qb->table('funcionarios_role_permissions')->withoutChave()->where('role_id', '=', $roles[0])
            ->where('permission_id', '=', $auditPermission['id'])->delete();
    }
    [$status, $machineTokens] = tokenMcp(grantMcp($a, $employees[$a], $clientId, ['frota:read', 'clientes:read']));
    checkMcp($status === 200, 'Credencial descartável para teste HTTP do transporte.');
    $machineHttp = static function (string $method, array $params, string $revision, ?string $bearer,
        string $host = 'mcp.example.test') use ($address): array {
        $headers = ['Host: ' . $host, 'Content-Type: application/json', 'Accept: application/json, text/event-stream',
            'Cookie: PHPSESSID=other-tenant-fixture', 'MCP-Protocol-Version: ' . $revision];
        if ($bearer !== null) $headers[] = 'Authorization: Bearer ' . $bearer;
        if ($revision === '2026-07-28') {
            $params['_meta'] = ['io.modelcontextprotocol/protocolVersion' => $revision,
                'io.modelcontextprotocol/clientCapabilities' => (object) []];
            $headers[] = 'MCP-Method: ' . $method;
            if (isset($params['name'])) $headers[] = 'MCP-Name: ' . $params['name'];
        }
        $body = ['jsonrpc' => '2.0', 'method' => $method, 'params' => (object) $params];
        if (!str_starts_with($method, 'notifications/')) $body['id'] = 1;
        $raw = file_get_contents('http://' . $address . '/mcp', false, stream_context_create(['http' => [
            'method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => json_encode($body, JSON_THROW_ON_ERROR),
            'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10]]));
        $status = 0; $responseHeaders = [];
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('/^HTTP\/\S+ (\d+)/', $line, $matches)) $status = (int) $matches[1];
            elseif (str_contains($line, ':')) { [$key, $value] = explode(':', $line, 2); $responseHeaders[strtolower($key)] = trim($value); }
        }
        return [$status, json_decode($raw, true), $responseHeaders, $raw];
    };
    foreach (['2025-11-25', '2026-07-28'] as $revision) {
        if ($revision === '2025-11-25') {
            [$status, $data] = $machineHttp('initialize', ['protocolVersion' => $revision, 'capabilities' => (object) [],
                'clientInfo' => ['name' => 'http-fixture', 'version' => '1']], $revision, $machineTokens['access_token']);
            checkMcp($status === 200 && ($data['result']['protocolVersion'] ?? '') === $revision, 'Initialize pela rede e Gateway::request().');
            [$status, , , $raw] = $machineHttp('notifications/initialized', [], $revision, $machineTokens['access_token']);
            checkMcp($status === 202 && $raw === '', 'Notificação HTTP sem resposta de protocolo.');
        }
        [$status, $data, $headers] = $machineHttp('tools/list', [], $revision, $machineTokens['access_token']);
        checkMcp($status === 200 && count($data['result']['tools'] ?? []) === 4 && isset($headers['x-request-id'])
            && !isset($headers['set-cookie']) && !isset($headers['mcp-session-id']), 'Descoberta HTTP real autenticada em ' . $revision);
        [$status, $data] = $machineHttp('tools/call', ['name' => 'listar_clientes', 'arguments' => (object) []], $revision, $machineTokens['access_token']);
        $rows = $data['result']['structuredContent']['dados'] ?? [];
        checkMcp($status === 200 && count($rows) === 1 && (int) $rows[0]['id'] === (int) $clients[$a],
            'Consulta HTTP preserva tenant/filial e ignora cookie de outra identidade em ' . $revision);
    }
    [$status] = $machineHttp('tools/list', [], '2026-07-28', null);
    checkMcp($status === 401, 'HTTP sem bearer permanece bloqueado.');
    [$status] = $machineHttp('tools/list', [], '2026-07-28', $machineTokens['access_token'], 'invalid.example');
    checkMcp($status === 403, 'HTTP com Host não autorizado permanece bloqueado.');
    $machineContext = (new \App\Services\Mcp\TokenValidator($store))->validate($machineTokens['access_token']);
    $store->revokeConnection($machineContext->chave, $machineContext->connectionId);
    [$status] = $machineHttp('tools/list', [], '2026-07-28', $machineTokens['access_token']);
    checkMcp($status === 401, 'Revogação bloqueia o transporte HTTP real.');
    $query = ['client_id' => $clientId, 'response_type' => 'code', 'redirect_uri' => 'https://assistant.example.test/callback',
        'scope' => 'frota:read clientes:read', 'resource' => Config::resource(), 'state' => 'fixture-state-not-for-logs',
        'code_challenge_method' => 'S256', 'code_challenge' => str_repeat('a', 43)];
    $open = static function (array $parameters) use ($http): array {
        [$status, $body, $headers] = $http('/oauth/mcp/authorize?' . http_build_query($parameters));
        preg_match('/name="nonce" value="([a-f0-9]{48})"/', $body, $nonce);
        preg_match('/name="_token" value="([a-f0-9]+)"/', $body, $csrf);
        return [$status, $body, $headers, ['nonce' => $nonce[1] ?? '', '_token' => $csrf[1] ?? '',
            'scopes' => ['frota:read'], 'decision' => 'allow']];
    };
    foreach (\App\Services\Mcp\ClientRegistry::CLAUDE_CODE_REDIRECTS as $callback) {
        $nativeQuery = array_replace($query, ['client_id' => \App\Services\Mcp\ClientRegistry::CLAUDE_CODE_CLIENT_ID,
            'redirect_uri' => $callback]);
        [$nativeStatus, , $nativeHeaders, $nativePost] = $open($nativeQuery);
        $nativeOrigin = substr($callback, 0, -strlen('/callback'));
        checkMcp($nativeStatus === 200 && ($nativeHeaders['content-security-policy'] ?? '') === "frame-ancestors 'none'; form-action 'self' $nativeOrigin; base-uri 'none'",
            'Consentimento HTTP Claude possui CSP com a origem loopback exata.');
        [$nativeStatus, , $nativeHeaders] = $http('/oauth/mcp/authorize', $nativePost);
        checkMcp($nativeStatus === 303 && str_starts_with($nativeHeaders['location'] ?? '', $callback . '?'),
            'Consentimento HTTP Claude retorna ao callback aprovado sem wildcard.');
        $nativeQuery['redirect_uri'] = 'http://127.0.0.1:8766/callback';
        [$nativeStatus, , $nativeHeaders] = $http('/oauth/mcp/authorize?' . http_build_query($nativeQuery));
        checkMcp($nativeStatus >= 400 && $nativeStatus < 500 && !isset($nativeHeaders['location']),
            'Callback Claude com porta divergente não recebe redirect do navegador.');
    }
    [$status, $body, $headers, $post] = $open($query);
    checkMcp($status === 200 && strlen($post['nonce']) === 48 && $post['_token'] !== '', 'GET HTTP apresenta consentimento com sessão e CSRF reais.');
    checkMcp(($headers['content-security-policy'] ?? '') === "frame-ancestors 'none'; form-action 'self' https://assistant.example.test; base-uri 'none'",
        'CSP permite somente self e a origem do retorno validado, sem wildcard.');
    checkMcp(str_contains($body, 'mcp-consent.min.js') && str_contains($body, 'data-mcp-consent'), 'Tela HTTP carrega proteção minificada de envio único.');
    $countConnections = static fn () => $qb->table('mcp_connections')->withChave($a)->count();
    $before = $countConnections();
    $badPost = $post; $badPost['_token'] = 'invalid';
    [$status] = $http('/oauth/mcp/authorize', $badPost);
    checkMcp($status === 403 && $countConnections() === $before, 'CSRF inválido não autoriza nem consome tentativa.');
    $badPost = $post; unset($badPost['decision']);
    [$status] = $http('/oauth/mcp/authorize', $badPost);
    checkMcp($status === 400 && $countConnections() === $before, 'Decisão ausente retorna 400 sem criar conexão.');
    $badPost = $post; $badPost['scopes'] = [];
    [$status] = $http('/oauth/mcp/authorize', $badPost);
    checkMcp($status === 400 && $countConnections() === $before, 'Autorizar sem categorias retorna 400 sem consumir tentativa.');
    $badPost = $post; $badPost['scopes'] = ['frota:read', 'financeiro:read'];
    [$status] = $http('/oauth/mcp/authorize', $badPost);
    checkMcp($status === 400 && $countConnections() === $before, 'Categoria fora do consentimento não consome a tentativa.');
    [$status, $body, $headers] = $http('/oauth/mcp/authorize', $post);
    parse_str(parse_url($headers['location'] ?? '', PHP_URL_QUERY) ?: '', $redirect);
    checkMcp($status === 303 && str_starts_with($headers['location'] ?? '', 'https://assistant.example.test/callback?')
        && ($redirect['state'] ?? '') === $query['state'] && ($redirect['iss'] ?? '') === Config::issuer()
        && isset($redirect['code']) && $countConnections() === $before + 1, 'POST HTTP sem JavaScript emite 303 com code, state e iss e uma única conexão.');
    checkMcp(preg_match('/^[a-f0-9]{32}$/D', $headers['x-request-id'] ?? '') === 1, 'POST identifica resposta com request ID seguro.');
    [$status, $body] = $http('/oauth/mcp/authorize', $post);
    checkMcp($status === 409 && str_contains($body, 'já foi enviada') && $countConnections() === $before + 1, 'POST repetido retorna 409 e não emite outra conexão.');
    $missingPost = $post; $missingPost['nonce'] = str_repeat('0', 48);
    [$status] = $http('/oauth/mcp/authorize', $missingPost);
    checkMcp($status === 410 && $countConnections() === $before + 1, 'Tentativa inexistente retorna 410 sem emissão.');
    [, , , $denyPost] = $open($query); $denyPost['decision'] = 'deny';
    [$status, , $headers] = $http('/oauth/mcp/authorize', $denyPost);
    parse_str(parse_url($headers['location'] ?? '', PHP_URL_QUERY) ?: '', $denied);
    checkMcp($status === 303 && ($denied['error'] ?? '') === 'access_denied' && ($denied['state'] ?? '') === $query['state']
        && ($denied['iss'] ?? '') === Config::issuer() && $countConnections() === $before + 1, 'Recusa usa 303, preserva state/iss e não cria conexão.');
    [, , , $expiredPost] = $open($query); $http('/expire-fixture');
    [$status] = $http('/oauth/mcp/authorize', $expiredPost);
    checkMcp($status === 410 && $countConnections() === $before + 1, 'Tentativa expirada retorna 410 sem emissão.');
    $invalidQuery = $query; $invalidQuery['redirect_uri'] = 'https://evil.example/callback';
    [$status, $body, $headers] = $open($invalidQuery);
    checkMcp(in_array($status, [400, 403], true) && !isset($headers['location']) && !isset($headers['content-security-policy'])
        && !str_contains($body, 'evil.example'), 'Retorno não cadastrado não recebe redirecionamento nem permissão na CSP.');
    [, , , $forbiddenPost] = $open($query);
    setEmployeeMcp($a, $employees[$a], ['status' => 'S']);
    [$status] = $http('/oauth/mcp/authorize', $forbiddenPost);
    checkMcp($status === 403 && $countConnections() === $before + 1, 'Suspensão entre GET e POST bloqueia emissão no fluxo HTTP.');
    setEmployeeMcp($a, $employees[$a], ['status' => 'A']);
    $http('/fail-key-fixture');
    [$status, $body] = $open($query);
    checkMcp($status === 503 && str_contains($body, 'Referência:') && !str_contains($body, 'missing-mcp-fixture-key'),
        'Falha interna retorna 503 com referência e sem revelar detalhes criptográficos.');
    $logs = file_get_contents($serverLog);
    $consentLogs = implode("\n", array_filter(explode("\n", $logs), fn ($line) => str_contains($line, '[MCP-CONSENT]')));
    checkMcp(str_contains($consentLogs, 'response_ready') && !str_contains($consentLogs, $post['nonce'])
        && !str_contains($consentLogs, $query['state']) && !str_contains($consentLogs, $redirect['code']), 'Logs de consentimento registram etapas sem nonce, state ou código.');
} finally {
    proc_terminate($process); proc_close($process);
    foreach (glob($sessionDirectory . '/*') as $file) unlink($file);
    rmdir($sessionDirectory);
    putenv('MCP_CONNECTION_RATE_LIMIT=' . $previousConnectionLimit);
    putenv('MCP_TENANT_RATE_LIMIT=' . $previousTenantLimit);
}
