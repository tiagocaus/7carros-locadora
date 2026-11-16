<?php
/** Included with disposable localhost tenants; no actual loopback browser or external client. */
declare(strict_types=1);
use App\Services\Mcp\{ClientRegistry, AuthorizationService, Config};
use App\Controllers\Mcp\Gateway;
use Nyholm\Psr7\ServerRequest;

$claudeId = ClientRegistry::CLAUDE_CODE_CLIENT_ID;
$existingClaude = $store->registeredClient($claudeId);
if ($existingClaude === null) {
    $store->registerClient($claudeId, 'Claude Code', ClientRegistry::CLAUDE_CODE_REDIRECTS, null, null, false);
    $claudeClientCreated = true;
} else {
    checkMcp((int) $existingClaude['enabled'] === 1 && $existingClaude['secret_hash'] === null && $existingClaude['metadata'] === null
        && json_decode($existingClaude['redirect_uris'], true) === ClientRegistry::CLAUDE_CODE_REDIRECTS,
        'Cliente Claude preexistente deve coincidir; teste nunca altera seu cadastro.');
}
$claudeService = new AuthorizationService($store);
foreach (ClientRegistry::CLAUDE_CODE_REDIRECTS as $callback) {
    $verifierClaude = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    $claudeQuery = ['client_id' => $claudeId, 'response_type' => 'code', 'redirect_uri' => $callback,
        'scope' => 'frota:read', 'resource' => Config::resource(), 'state' => bin2hex(random_bytes(16)),
        'code_challenge_method' => 'S256', 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifierClaude, true)), '+/', '-_'), '=')];
    $pendingClaude = $claudeService->prepare($a, $employees[$a], $claudeQuery);
    checkMcp($pendingClaude['redirect_origin'] === substr($callback, 0, -strlen('/callback')),
        'CSP do Claude usa somente origem HTTP local exata validada.');
    foreach (['http://127.0.0.1:8766/callback', 'http://localhost:8765/callback?extra=1', 'http://localhost.evil:8765/callback'] as $badCallback) {
        $rejected = false;
        try { $claudeService->prepare($a, $employees[$a], array_replace($claudeQuery, ['redirect_uri' => $badCallback])); }
        catch (InvalidArgumentException) { $rejected = true; }
        checkMcp($rejected, 'Retorno inválido bloqueado antes da flexibilização de porta da biblioteca.');
    }
    $plainRejected = false;
    try { $claudeService->prepare($a, $employees[$a], array_replace($claudeQuery, ['code_challenge_method' => 'plain'])); }
    catch (InvalidArgumentException) { $plainRejected = true; }
    checkMcp($plainRejected, 'Callback local não relaxa PKCE S256.');
    $responseClaude = $claudeService->complete($pendingClaude, $a, $employees[$a], ['frota:read'], true);
    parse_str(parse_url($responseClaude->getHeaderLine('Location'), PHP_URL_QUERY), $locationClaude);
    checkMcp($responseClaude->getStatusCode() === 303 && str_starts_with($responseClaude->getHeaderLine('Location'), $callback . '?')
        && $locationClaude['state'] === $claudeQuery['state'] && $locationClaude['iss'] === Config::issuer(),
        'Callback Claude preserva código, state e emissor sem mudar o destino.');
    $claudeGrant = ['grant_type' => 'authorization_code', 'client_id' => $claudeId, 'code' => $locationClaude['code'],
        'code_verifier' => $verifierClaude, 'redirect_uri' => $callback, 'resource' => Config::resource()];
    [$badStatus] = tokenMcp(array_replace($claudeGrant, ['code_verifier' => str_repeat('z', 64)]));
    checkMcp($badStatus === 400, 'Verificador PKCE incorreto rejeitado no cliente público.');
    [$badStatus] = tokenMcp(array_replace($claudeGrant, ['redirect_uri' => 'http://127.0.0.1:8766/callback']));
    checkMcp($badStatus === 400, 'Troca de código exige o mesmo callback exato.');
    [$statusClaude, $tokensClaude] = tokenMcp($claudeGrant);
    checkMcp($statusClaude === 200 && isset($tokensClaude['access_token'], $tokensClaude['refresh_token']),
        'Cliente Claude público troca código sem segredo.');
    [$statusClaude, $dataClaude] = requestMcp('tools/call', ['name' => 'listar_veiculos',
        'arguments' => ['pergunta_usuario' => 'Quantos veículos tenho?']], $tokensClaude['access_token']);
    checkMcp($statusClaude === 200 && count($dataClaude['result']['structuredContent']['dados'] ?? []) === 1,
        'Consulta Claude respeita tenant e escopo no transporte existente.');
    [$statusClaude, $nextClaude] = tokenMcp(['grant_type' => 'refresh_token', 'refresh_token' => $tokensClaude['refresh_token'],
        'client_id' => $claudeId, 'resource' => Config::resource()]);
    checkMcp($statusClaude === 200 && $nextClaude['refresh_token'] !== $tokensClaude['refresh_token'], 'Refresh Claude rotaciona.');
    $revokeClaude = (new ServerRequest('POST', Config::issuer() . '/oauth/mcp/revoke',
        ['Content-Type' => 'application/x-www-form-urlencoded']))->withParsedBody(['client_id' => $claudeId, 'token' => $nextClaude['refresh_token']]);
    checkMcp((new Gateway())->handle($revokeClaude)->getStatusCode() === 200, 'Revogação Claude aceita a credencial própria.');
    [$statusClaude] = requestMcp('tools/list', [], $nextClaude['access_token']);
    checkMcp($statusClaude === 401, 'Revogação Claude invalida o acesso da conexão.');
}
