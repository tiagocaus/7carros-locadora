<?php
/** Deterministic metadata/policy tests. No network, database or credentials. */
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Services\Mcp\{ClientPolicy, ClientRegistry};
use App\Services\Mcp\OAuth\Client;
$_ENV['APP_ENV'] = 'development';
$count = 0;
function expectCimd(bool $ok, string $message): void {
    global $count;
    if (!$ok) throw new RuntimeException($message);
    $count++;
}
function rejectCimd(callable $run, string $message): void {
    $rejected = false;
    try { $run(); } catch (InvalidArgumentException|RuntimeException) { $rejected = true; }
    expectCimd($rejected, $message);
}
// Public metadata verified 16/11/2026. It contains no client secrets or user data.
$document = json_decode(<<<'JSON'
{"client_id":"https://chatgpt.com/oauth/client.json","client_uri":"https://chatgpt.com/","redirect_uris":["https://chatgpt.com/connector_platform_oauth_redirect"],"token_endpoint_auth_method":"private_key_jwt","token_endpoint_auth_methods_supported":["none","private_key_jwt"],"grant_types":["authorization_code","refresh_token","urn:ietf:params:oauth:grant-type:jwt-bearer"],"response_types":["code"],"client_name":"ChatGPT","logo_uri":"https://persistent.oaistatic.com/sonic/misc/openai-logo.png","token_endpoint_auth_signing_alg":"RS256","jwks_uri":"https://chatgpt.com/oauth/jwks.json"}
JSON, true, 32, JSON_THROW_ON_ERROR);
$id = $document['client_id'];
$cap = ClientRegistry::capabilities(ClientRegistry::validateDocument($id, $document));
expectCimd($cap === ['token_endpoint_auth_method' => 'none', 'grant_types' => ['authorization_code', 'refresh_token']], 'Negociar interseção do documento real do ChatGPT.');
expectCimd(ClientRegistry::envelope($document)['document'] === $document, 'Preservar documento original.');
$reordered = array_reverse($document, true);
expectCimd(ClientRegistry::fingerprint($document) === ClientRegistry::fingerprint($reordered), 'Hash ignora apenas ordem de propriedades.');
$legacy = $document; unset($legacy['token_endpoint_auth_methods_supported']); $legacy['token_endpoint_auth_method'] = 'none';
expectCimd(ClientRegistry::capabilities($legacy)['token_endpoint_auth_method'] === 'none', 'Compatibilidade com método singular none.');
$defaults = ['client_id' => $id, 'client_name' => 'Cliente', 'redirect_uris' => ['https://client.example/callback']];
expectCimd(ClientRegistry::capabilities($defaults)['grant_types'] === ['authorization_code'], 'Default OAuth é somente authorization_code.');
$row = ['client_id' => $id, 'name' => 'ChatGPT', 'redirect_uris' => json_encode($document['redirect_uris']),
    'secret_hash' => null, 'metadata' => json_encode(ClientRegistry::envelope($document))];
$entity = new Client($row);
expectCimd(!$entity->isConfidential() && $entity->supportsGrantType('authorization_code') && $entity->supportsGrantType('refresh_token'), 'Cliente público com grants negociados.');
expectCimd(!$entity->supportsGrantType('urn:ietf:params:oauth:grant-type:jwt-bearer') && !$entity->supportsGrantType('client_credentials'), 'Grants extras não são habilitados.');
$row['metadata'] = json_encode(ClientRegistry::envelope($defaults));
expectCimd(!(new Client($row))->supportsGrantType('refresh_token'), 'Não habilitar refresh ausente no cliente.');
foreach ([null, [], ['private_key_jwt'], 'none', [null], ['none', 1], [''], ['none invalid']] as $methods) {
    $bad = $document; $bad['token_endpoint_auth_methods_supported'] = $methods;
    rejectCimd(fn () => ClientRegistry::validateDocument($id, $bad), 'Rejeitar métodos incompatíveis ou malformados.');
}
$bad = $legacy; $bad['token_endpoint_auth_method'] = 'private_key_jwt';
rejectCimd(fn () => ClientRegistry::validateDocument($id, $bad), 'Método singular incompatível não é none.');
foreach ([[], ['client_credentials'], 'authorization_code', ['authorization_code', null]] as $grants) {
    $bad = $document; $bad['grant_types'] = $grants;
    rejectCimd(fn () => ClientRegistry::validateDocument($id, $bad), 'Grants devem oferecer código e ter tipos válidos.');
}
foreach (['https://elsewhere.example/client.json', 'http://chatgpt.com/oauth/client.json'] as $other) {
    rejectCimd(fn () => ClientRegistry::validateDocument($other, $document), 'Identificação exata.');
}
foreach ([[], ['http://client.example/callback'], ['https://user:pass@client.example/callback'], ['https://client.example/callback#fragment']] as $redirects) {
    $bad = $document; $bad['redirect_uris'] = $redirects;
    rejectCimd(fn () => ClientRegistry::validateDocument($id, $bad), 'Retornos HTTPS exatos e válidos.');
}
$bad = $document; $bad['response_types'] = ['token'];
rejectCimd(fn () => ClientRegistry::validateDocument($id, $bad), 'Sem grant implícito.');
foreach (['http://chatgpt.com/client.json', 'https://127.0.0.1/client.json', 'https://chatgpt.com@127.0.0.1/client.json',
    'https://chatgpt.com/client.json?method=none', 'https://unapproved.example/client.json', 'https://chatgpt.com:8443/client.json'] as $url) {
    rejectCimd(fn () => ClientRegistry::validateMetadataUrl($url, ['chatgpt.com']), 'Não relaxar SSRF/allowlist na negociação.');
}
$environment = ['APP_ENV' => 'production', 'MCP_ENABLED' => 'true', 'MCP_PILOT_MODE' => 'true',
    'MCP_PILOT_EMPLOYEE_IDS' => '123', 'MCP_PILOT_CLIENT_IDS' => $id, 'MCP_PILOT_TENANTS' => 'tenant-test-a'];
$old = [];
foreach ($environment as $key => $value) { $old[$key] = getenv($key); putenv($key . '=' . $value); }
try {
    $pending = ['enabled' => 1, 'homologated' => 0, 'client_id' => $id];
    expectCimd(ClientPolicy::permitsIdentity($pending, 'tenant-test-a', 123), 'Piloto permite aplicativo aprovado ainda não homologado.');
    expectCimd(!ClientPolicy::permitsIdentity($pending, 'tenant-test-a', 124), 'Outro funcionário da mesma locadora bloqueado.');
    expectCimd(!ClientPolicy::permitsIdentity($pending, 'tenant-test-b', 123), 'Outro tenant bloqueado.');
    expectCimd(!ClientPolicy::permitsClient(array_replace($pending, ['client_id' => 'other'])), 'Outro aplicativo bloqueado.');
    expectCimd(!ClientPolicy::permitsClient(array_replace($pending, ['enabled' => 0])), 'Aplicativo desabilitado bloqueado.');
    foreach (['MCP_PILOT_EMPLOYEE_IDS', 'MCP_PILOT_CLIENT_IDS', 'MCP_PILOT_TENANTS'] as $key) {
        putenv($key . '='); expectCimd(!ClientPolicy::permitsClient($pending), 'Lista vazia bloqueia piloto: ' . $key);
        putenv($key . '=' . $environment[$key]);
    }
    foreach (['0', '-1', '00123', '123,invalid'] as $value) {
        putenv('MCP_PILOT_EMPLOYEE_IDS=' . $value);
        expectCimd(!ClientPolicy::permitsClient($pending), 'IDs inválidos bloqueiam toda a configuração.');
    }
    putenv('MCP_PILOT_EMPLOYEE_IDS=123');
    $homologated = array_replace($pending, ['homologated' => 1]);
    expectCimd(!ClientPolicy::permitsIdentity($homologated, 'tenant-test-a', 124), 'Homologar não amplia o piloto.');
    putenv('MCP_PILOT_MODE=false');
    expectCimd(!ClientPolicy::permitsClient($pending), 'Produção fora do piloto bloqueia pendentes.');
    expectCimd(ClientPolicy::permitsIdentity($homologated, 'tenant-test-a', 124), 'Saída explícita do piloto mantém tenant e RBAC para homologados.');
    putenv('MCP_ENABLED=false');
    expectCimd(!ClientPolicy::permitsClient($homologated), 'Desligar MCP interrompe todo acesso.');
    putenv('MCP_ENABLED=true');putenv('MCP_PILOT_MODE=invalid');
    rejectCimd(fn () => ClientPolicy::permitsClient($homologated), 'Modo inválido não libera acesso normal.');
} finally {
    foreach ($old as $key => $value) putenv($value === false ? $key : $key . '=' . $value);
}
echo "OK: $count verificações CIMD e política de piloto, sem rede ou banco.\n";
