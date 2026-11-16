<?php
/** Local fixtures only; never sends email/SMS/WhatsApp or calls external services. */
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Classes\QueryBuilder;
use App\Core\Database;
use App\Models\Model;
use App\Models\Mcp\{Store, ReadModel};
use App\Controllers\Mcp\Gateway;
use App\Services\Mcp\{AuthorizationService, Catalog, ClientRegistry, Config, Context, RateLimiter, Readiness, TokenValidator};
use Nyholm\Psr7\ServerRequest;

// A separate process holds the business query while another connection suspends its user.
if (in_array($argv[1] ?? '', ['--suspend-during-read', '--security-suspend-during-read'], true)) {
    $_ENV['APP_ENV'] = 'development';
    if (Database::env('DB_HOST') !== 'localhost' || !str_starts_with($argv[2] ?? '', 'mcp_test_a_')) exit(1);
    $lock = Model::sharedMysqli();
    try {
        $lock->query('LOCK TABLES clientes WRITE');
        echo "ready\n"; flush();
        usleep(700000);
        $writer = new mysqli(Database::env('DB_HOST'), Database::env('DB_USERNAME'), Database::env('DB_PASSWORD'),
            Database::env('DB_DATABASE'), (int) Database::env('DB_PORT', 3306));
        if ($argv[1] === '--security-suspend-during-read') {
            (new QueryBuilder($writer))->table('mcp_security_state')->withChave($argv[2])->where('employee_id', '=', (int) $argv[3])
                ->update(['suspended_until' => time() + 1800]);
        } else {
        (new QueryBuilder($writer))->table('funcionarios')->withChave($argv[2])->where('id', '=', (int) $argv[3])->update(['status' => 'S']);
        }
        $lock->query('UNLOCK TABLES');
        $writer->close();
        exit(0);
    } catch (Throwable) { $lock->query('UNLOCK TABLES'); exit(1); }
}

$_ENV['APP_ENV'] = 'development';
putenv('APP_ENV=development');
if (Database::env('DB_HOST') !== 'localhost') throw new RuntimeException('Teste exige MySQL localhost.');
date_default_timezone_set('America/Sao_Paulo');
$run = bin2hex(random_bytes(6));
$a = 'mcp_test_a_' . $run; $b = 'mcp_test_b_' . $run;
$clientId = 'test-mcp-' . $run;
$cimdClientId = 'https://fixture-' . $run . '.example/client.json'; $db = Model::sharedMysqli(); $qb = new QueryBuilder($db);
$store = new Store(); $count = 0;
$directory = sys_get_temp_dir() . '/7carros-mcp-' . $run;
mkdir($directory, 0700);
ini_set('session.save_path', $directory);
session_start();
putenv('RANDFILE=' . $directory . '/random-state');
$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
openssl_pkey_export($key, $private);
file_put_contents($directory . '/private.pem', $private); chmod($directory . '/private.pem', 0600);
file_put_contents($directory . '/public.pem', openssl_pkey_get_details($key)['key']);
unset($private, $key);
foreach (['MCP_ENABLED' => 'true', 'MCP_ALL_TENANTS' => 'false', 'MCP_PILOT_TENANTS' => "$a,$b", 'MCP_ISSUER' => 'https://mcp.example.test',
    'MCP_PILOT_MODE' => 'false', 'MCP_PILOT_EMPLOYEE_IDS' => '', 'MCP_PILOT_CLIENT_IDS' => '',
    'MCP_PRIVATE_KEY' => 'file://' . $directory . '/private.pem', 'MCP_PUBLIC_KEY' => $directory . '/public.pem',
    'MCP_SECURITY_INFRACTIONS' => '1000', 'MCP_ENCRYPTION_KEY' => bin2hex(random_bytes(32)), 'MCP_CONNECTION_RATE_LIMIT' => '1000', 'MCP_TENANT_RATE_LIMIT' => '5000'] as $k => $v) putenv("$k=$v");
function checkMcp(bool $condition, string $message): void {
    global $count;
    if (!$condition) throw new RuntimeException($message);
    $count++;
}
function requestMcp(string $method, array $params, string $token, string $version = '2026-07-28', ?string $headerVersion = null): array {
    $params['_meta'] = ['io.modelcontextprotocol/protocolVersion' => $version,
        'io.modelcontextprotocol/clientCapabilities' => (object) []];
    $headers = ['Accept' => 'application/json, text/event-stream', 'Content-Type' => 'application/json',
        'MCP-Protocol-Version' => $headerVersion ?? $version, 'MCP-Method' => $method, 'Authorization' => 'Bearer ' . $token];
    if (isset($params['name'])) $headers['MCP-Name'] = $params['name'];
    $request = new ServerRequest('POST', Config::resource(), $headers,
        json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], JSON_THROW_ON_ERROR),
        '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
    $response = (new Gateway())->handle($request);
    $data = json_decode((string) $response->getBody(), true);
    return [$response->getStatusCode(), $data ?? [], $response];
}
function tokenMcp(array $params): array {
    $request = (new ServerRequest('POST', Config::issuer() . '/oauth/mcp/token',
        ['Content-Type' => 'application/x-www-form-urlencoded'], http_build_query($params),
        '1.1', ['REMOTE_ADDR' => '127.0.0.1']))->withParsedBody($params);
    $response = (new Gateway())->handle($request);
    return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
}
function setEmployeeMcp(string $tenant, int $employee, array $values): void {
    global $qb;
    $qb->table('funcionarios')->withChave($tenant)->where('id', '=', $employee)->update($values);
}
function grantMcp(string $tenant, int $employee, string $clientId, array $scopes): array {
    global $store;
    $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    $query = ['client_id' => $clientId, 'response_type' => 'code', 'redirect_uri' => 'https://assistant.example.test/callback',
        'scope' => implode(' ', $scopes), 'resource' => Config::resource(), 'state' => bin2hex(random_bytes(16)),
        'code_challenge_method' => 'S256', 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')];
    $service = new AuthorizationService($store);
    $pending = $service->prepare($tenant, $employee, $query);
    $response = $service->complete($pending, $tenant, $employee, $scopes, true);
    parse_str(parse_url($response->getHeaderLine('Location'), PHP_URL_QUERY), $location);
    checkMcp(($location['iss'] ?? '') === Config::issuer(), 'Resposta OAuth deve identificar emissor.');
    checkMcp(($location['state'] ?? '') === $query['state'], 'Estado OAuth deve ser preservado.');
    return ['grant_type' => 'authorization_code', 'client_id' => $clientId, 'code' => $location['code'],
        'code_verifier' => $verifier, 'redirect_uri' => $query['redirect_uri'], 'resource' => Config::resource()];
}
$claudeClientCreated = false;
$roles = []; $branches = []; $employees = []; $clients = []; $vehicles = [];
try {
    $store->registerClient($clientId, 'Assistente de teste', ['https://assistant.example.test/callback'], null, null, false);
    $permissionIds = array_column($qb->table('permissions')->withoutChave()->select(['id'])
        ->whereIn('`key`', array_values(array_unique(['mcp.conectar', 'mcp.administrar', ...array_values(Catalog::SCOPES)])))->get(), 'id');
    foreach ([$a, $b] as $tenant) {
        $role = $qb->table('funcionarios_roles')->withChave($tenant)->insert(['chave' => $tenant, 'name' => 'Teste MCP']); $roles[] = $role;
        foreach ($permissionIds as $pid) $qb->table('funcionarios_role_permissions')->withoutChave()->insert(['role_id' => $role, 'permission_id' => $pid]);
        $branch = $qb->table('matrizes_filiais')->withChave($tenant)->insert(['chave' => $tenant, 'nome_fantasia' => 'Teste MCP', 'currency_code' => 'BRL']);
        $branches[$tenant] = $branch;
        $employee = $qb->table('funcionarios')->withChave($tenant)->insert(['chave' => $tenant, 'plano' => '1', 'status' => 'A',
            'id_role' => $role, 'id_matriz_filial' => $branch, 'nome' => 'Teste MCP', 'senha' => password_hash(bin2hex(random_bytes(20)), PASSWORD_DEFAULT)]);
        $employees[$tenant] = $employee;
        $qb->table('funcionarios_filiais')->withChave($tenant)->insert(['chave' => $tenant, 'id_funcionario' => $employee, 'id_matriz_filial' => $branch]);
        $clients[$tenant] = $qb->table('clientes')->withChave($tenant)->insert(['chave' => $tenant, 'id_matriz_filial' => $branch,
            'nome_rsocial' => 'Cliente MCP ' . $tenant, 'cpf_cnpj' => '00000000000', 'foto' => '', 'data_cadastro' => date('Y-m-d')]);
        $vehicles[$tenant] = $qb->table('veiculos')->withChave($tenant)->insert(['chave' => $tenant, 'id_matriz_filial' => $branch,
            'placa' => 'MCPTEST', 'marca' => 'Teste', 'modelo' => $tenant]);
        $qb->table('contatos_emails')->withChave($tenant)->insert(['chave' => $tenant, 'entidade_tipo' => 'cliente',
            'entidade_id' => $clients[$tenant], 'email' => 'fixture@example.invalid']);
        $qb->table('contatos_telefones')->withChave($tenant)->insert(['chave' => $tenant, 'entidade_tipo' => 'cliente',
            'entidade_id' => $clients[$tenant], 'telefone' => '000000000']);
        $qb->table('contratos')->withChave($tenant)->insert(['chave' => $tenant, 'codigo' => 'MC' . bin2hex(random_bytes(5)),
            'id_matriz_filial_retirada' => $branch, 'data_ini' => '2026-11-01 10:00:00', 'data_fim' => '2026-12-01 10:00:00',
            'contagem' => 'D', 'dias' => 30, 'status' => 'A']);
        $qb->table('locacoes')->withChave($tenant)->insert(['chave' => $tenant, 'codigo' => 'ML' . bin2hex(random_bytes(5)),
            'id_matriz_filial_retirada' => $branch, 'id_matriz_filial_devolucao' => $branch,
            'data_saida' => '2026-11-01 10:00:00', 'data_prevista' => '2026-12-01 10:00:00', 'dias' => 30, 'cliente_nome' => 'Teste MCP']);
        $origin = $qb->table('financeiro')->withChave($tenant)->insert(['chave' => $tenant, 'id_matriz_filial' => $branch,
            'tipo' => 'R', 'pago' => 'N', 'data_criada' => date('Y-m-d'), 'data_venci' => date('Y-m-d'),
            'valor_subtotal' => '100.00', 'valor_total' => '100.00', 'parcela' => 1, 'total_parcelas' => 2]);
        $qb->table('financeiro')->withChave($tenant)->insert(['chave' => $tenant, 'id_matriz_filial' => $branch,
            'tipo' => 'R', 'pago' => 'N', 'data_criada' => date('Y-m-d'), 'data_venci' => date('Y-m-d'),
            'valor_subtotal' => '100.00', 'valor_total' => '100.00', 'parcela' => 2, 'total_parcelas' => 2, 'id_financeiro_origem' => $origin]);
    }

    // The consent must describe the same current branch access used by MCP calls.
    $originalAccess = $store->consentBranches($a, $employees[$a]);
    checkMcp(!$originalAccess['all'] && array_column($originalAccess['units'], 'id') === [$branches[$a]], 'Consentimento restrito mostra uma unidade permitida.');
    $extraUnits = [];
    foreach ([['nome_fantasia' => 'Teste MCP'], ['nome_fantasia' => ' ', 'razao_social' => 'Razão & <Teste>'],
        ['nome_fantasia' => null, 'razao_social' => null]] as $values) {
        $extraUnits[] = $qb->table('matrizes_filiais')->withChave($a)->insert(['chave' => $a] + $values);
    }
    $qb->table('funcionarios_filiais')->withChave($a)->delete();
    $allAccess = $store->consentBranches($a, $employees[$a]);
    checkMcp($allAccess['all'] && count($allAccess['units']) === 4, 'Sem restrições, consentimento lista todas as quatro unidades do tenant.');
    checkMcp(!in_array($branches[$b], array_column($allAccess['units'], 'id'), true), 'Lista completa não inclui unidade de outro tenant.');
    $names = array_column($allAccess['units'], 'name', 'id');
    checkMcp($names[$extraUnits[1]] === 'Razão & <Teste>' && $names[$extraUnits[2]] === 'Empresa/filial', 'Nome usa razão social e alternativa para cadastros vazios.');
    checkMcp($names[$branches[$a]] === $names[$extraUnits[0]] && $branches[$a] !== $extraUnits[0], 'Nomes repetidos preservam IDs distintos.');
    $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    $consentQuery = ['client_id' => $clientId, 'response_type' => 'code', 'redirect_uri' => 'https://assistant.example.test/callback',
        'scope' => implode(' ', array_keys(Catalog::SCOPES)), 'resource' => Config::resource(), 'state' => bin2hex(random_bytes(16)),
        'code_challenge_method' => 'S256', 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')];
    $service = new AuthorizationService($store);
    $pending = $service->prepare($a, $employees[$a], $consentQuery);
    checkMcp($pending['branch_access'] === $allAccess && count($pending['scopes']) === count(Catalog::SCOPES), 'Serviço entrega lista completa sem limitar categorias disponíveis.');
    $nonce = 'fixture-consent'; $labels = array_fill_keys(array_keys(Catalog::SCOPES), 'Categoria de teste');
    ob_start(); require dirname(__DIR__) . '/app/Views/pages/mcp/consent.php'; $html = ob_get_clean();
    checkMcp(str_contains($html, 'Empresas/filiais com acesso (4)') && str_contains($html, 'Razão &amp; &lt;Teste&gt;')
        && !str_contains($html, 'Razão & <Teste>') && str_contains($html, '<li>Empresa/filial</li>')
        && !preg_match('/<li>[^<]*#\d+/', $html) && str_contains($html, 'name="_token"'), 'Template lista somente nomes, escapa HTML e mantém CSRF.');
    foreach ([$branches[$a], $extraUnits[0]] as $branch) {
        $qb->table('funcionarios_filiais')->withChave($a)->insert(['chave' => $a, 'id_funcionario' => $employees[$a], 'id_matriz_filial' => $branch]);
    }
    $restricted = $service->prepare($a, $employees[$a], $consentQuery);
    checkMcp(!$restricted['branch_access']['all'] && count($restricted['branch_access']['units']) === 2, 'Nova apresentação acompanha restrição a duas unidades.');
    $response = $service->complete($pending, $a, $employees[$a], $pending['scopes'], true);
    parse_str(parse_url($response->getHeaderLine('Location'), PHP_URL_QUERY), $location);
    [$status, $consentTokens] = tokenMcp(['grant_type' => 'authorization_code', 'code' => $location['code'], 'client_id' => $clientId,
        'redirect_uri' => 'https://assistant.example.test/callback', 'code_verifier' => $verifier, 'resource' => Config::resource()]);
    $currentContext = (new TokenValidator($store))->validate($consentTokens['access_token']);
    checkMcp($status === 200 && !$currentContext->allBranches && count($currentContext->branchIds) === 2, 'Confirmação com todas as categorias usa filiais atuais após mudança de vínculos.');
    $qb->table('funcionarios_filiais')->withChave($a)->delete();
    $qb->table('funcionarios_filiais')->withChave($a)->insert(['chave' => $a, 'id_funcionario' => $employees[$a], 'id_matriz_filial' => $branches[$b]]);
    $invalidAccess = $store->consentBranches($a, $employees[$a]);
    checkMcp(!$invalidAccess['all'] && $invalidAccess['units'] === [], 'Vínculo a filial de outro tenant resulta em lista restrita vazia.');
    $pending = $service->prepare($a, $employees[$a], $consentQuery);
    ob_start(); require dirname(__DIR__) . '/app/Views/pages/mcp/consent.php'; $html = ob_get_clean();
    checkMcp(str_contains($html, 'Nenhuma empresa/filial disponível') && !str_contains($html, 'Todas as empresas/filiais deste cadastro.'), 'Lista vazia mostra ausência de unidades, sem afirmar acesso total.');
    $qb->table('funcionarios_filiais')->withChave($a)->delete();
    $qb->table('funcionarios_filiais')->withChave($a)->insert(['chave' => $a, 'id_funcionario' => $employees[$a], 'id_matriz_filial' => $branches[$a]]);
    $qb->table('matrizes_filiais')->withChave($a)->whereIn('id', $extraUnits)->delete();

    // Public metadata registration is pending, idempotent, and retains negotiated capabilities.
    $cimd = ['client_id' => $cimdClientId, 'client_name' => 'CIMD fixture',
        'redirect_uris' => ['https://assistant.example.test/callback'],
        'token_endpoint_auth_method' => 'private_key_jwt', 'token_endpoint_auth_methods_supported' => ['none', 'private_key_jwt'],
        'grant_types' => ['authorization_code', 'refresh_token', 'urn:ietf:params:oauth:grant-type:jwt-bearer']];
    $store->registerClient($cimdClientId, $cimd['client_name'], $cimd['redirect_uris'], null, $cimd, false);
    $before = $store->registeredClient($cimdClientId);
    $store->registerClient($cimdClientId, $cimd['client_name'], $cimd['redirect_uris'], null, $cimd, false);
    checkMcp($store->registeredClient($cimdClientId)['id'] === $before['id'], 'Aprovação repetida é idempotente.');
    $meta = json_decode($before['metadata'], true);
    checkMcp($meta['negotiated']['token_endpoint_auth_method'] === 'none' && (int) $before['homologated'] === 0, 'Persistir negociação sem homologação antecipada.');
    $rejectedChange = false;
    $changed = $cimd; $changed['redirect_uris'] = ['https://different.example/callback'];
    try { $store->registerClient($cimdClientId, $changed['client_name'], $changed['redirect_uris'], null, $changed, false); }
    catch (RuntimeException) { $rejectedChange = true; }
    checkMcp($rejectedChange && $store->registeredClient($cimdClientId)['redirect_uris'] === $before['redirect_uris'], 'Retornos aprovados não mudam automaticamente.');
    $username = 'mcp_fixture_' . $run;
    setEmployeeMcp($a, $employees[$a], ['usuario' => $username]);
    $otherEmployee = $qb->table('funcionarios')->withChave($a)->insert(['chave' => $a, 'plano' => '1', 'status' => 'A',
        'id_role' => $roles[0], 'nome' => 'Outro piloto fixture', 'senha' => password_hash(bin2hex(random_bytes(20)), PASSWORD_DEFAULT)]);
    putenv('APP_ENV=production');
    $blocked = false;
    try { grantMcp($a, $employees[$a], $cimdClientId, ['frota:read']); } catch (RuntimeException) { $blocked = true; }
    checkMcp($blocked, 'Produção recusa cliente pendente fora do modo piloto.');
    putenv('MCP_PILOT_MODE=true'); putenv('MCP_PILOT_EMPLOYEE_IDS=' . $employees[$a]); putenv('MCP_PILOT_CLIENT_IDS=' . $cimdClientId);
    $readiness = Readiness::report($store, $cimdClientId, $cimd, $username);
    checkMcp($readiness['ready_to_enable'] && $readiness['employee'][0]['employee_id'] === $employees[$a], 'Diagnóstico resolve o funcionário pelo tenant e valida configuração/chaves.');
    $beforeAudit = $qb->table('mcp_audit')->withChave($a)->count();
    Readiness::report($store, $cimdClientId, $cimd, $username);
    checkMcp($beforeAudit === $qb->table('mcp_audit')->withChave($a)->count(), 'Diagnóstico não grava auditorias ou credenciais.');
    $pendingGrant = grantMcp($a, $employees[$a], $cimdClientId, ['frota:read']);
    [$status, $pilotTokens] = tokenMcp($pendingGrant);
    checkMcp($status === 200, 'Piloto em produção emite tokens de cliente pendente aprovado.');
    [$status, $catalog] = requestMcp('tools/list', [], $pilotTokens['access_token']);
    checkMcp($status === 200 && count($catalog['result']['tools'] ?? []) === 2, 'Consentimento de frota retorna somente suas duas ferramentas.');
    $blocked = false;
    try { grantMcp($a, $otherEmployee, $cimdClientId, ['frota:read']); } catch (RuntimeException) { $blocked = true; }
    checkMcp($blocked, 'Outro funcionário com as mesmas permissões não autoriza o cliente piloto.');
    $blocked = false;
    try { grantMcp($b, $employees[$b], $cimdClientId, ['frota:read']); } catch (RuntimeException) { $blocked = true; }
    checkMcp($blocked, 'Outro funcionário/tenant não autoriza o piloto.');
    putenv('MCP_PILOT_EMPLOYEE_IDS=' . $otherEmployee);
    [$status] = requestMcp('tools/list', [], $pilotTokens['access_token']);
    checkMcp($status === 401, 'Remoção do funcionário do piloto bloqueia token já emitido.');
    [$status, $pilotError] = tokenMcp(['grant_type' => 'refresh_token', 'refresh_token' => $pilotTokens['refresh_token'], 'client_id' => $cimdClientId, 'resource' => Config::resource()]);
    checkMcp(in_array($status, [401, 403], true), 'Remoção do piloto bloqueia renovação sem consumir refresh: ' . json_encode(['status' => $status, 'error' => $pilotError['error'] ?? null]));
    putenv('MCP_PILOT_EMPLOYEE_IDS=' . $employees[$a]);
    [$status, $restoredTokens] = tokenMcp(['grant_type' => 'refresh_token', 'refresh_token' => $pilotTokens['refresh_token'], 'client_id' => $cimdClientId, 'resource' => Config::resource()]);
    checkMcp($status === 200 && isset($restoredTokens['refresh_token']), 'Restaurar funcionário permite renovar o refresh preservado após bloqueio do piloto.');
    $pilotTokens = $restoredTokens;
    foreach (['MCP_PILOT_EMPLOYEE_IDS', 'MCP_PILOT_CLIENT_IDS', 'MCP_PILOT_TENANTS'] as $name) {
        $original = getenv($name); putenv($name . '=');
        [$status] = requestMcp('tools/list', [], $pilotTokens['access_token']);
        checkMcp($status === 401, 'Esvaziar lista não libera conexões existentes: ' . $name);
        putenv($name . '=' . $original);
    }
    $badEvidence = false;
    try { $store->markHomologated($cimdClientId, '', $cimd); } catch (InvalidArgumentException) { $badEvidence = true; }
    checkMcp($badEvidence && (int) $store->registeredClient($cimdClientId)['homologated'] === 0, 'Sem evidência não há promoção.');
    $badEvidence = false;
    try { $store->markHomologated($cimdClientId, 'fixture-local', $changed); } catch (RuntimeException) { $badEvidence = true; }
    checkMcp($badEvidence, 'Mudança no documento impede promoção do cadastro anterior.');
    $store->markHomologated($cimdClientId, 'fixture-local-sem-homologacao-externa', $cimd);
    $promoted = $store->registeredClient($cimdClientId);
    checkMcp((int) $promoted['homologated'] === 1 && json_decode($promoted['metadata'], true)['homologation']['protocol'] === '2026-07-28', 'Promoção do fixture registra protocolo e evidência.');
    $blocked = false;
    try { grantMcp($a, $otherEmployee, $cimdClientId, ['frota:read']); } catch (RuntimeException) { $blocked = true; }
    checkMcp($blocked, 'Homologar não amplia o acesso a outros funcionários durante piloto.');
    putenv('MCP_PILOT_CLIENT_IDS=');
    [$status] = tokenMcp(['grant_type' => 'refresh_token', 'refresh_token' => $pilotTokens['refresh_token'], 'client_id' => $cimdClientId, 'resource' => Config::resource()]);
    checkMcp($status >= 400, 'Cliente removido do piloto não renova.');
    // Production global rollout: no tenant/employee/client allowlists are required.
    putenv('MCP_PILOT_MODE=false'); putenv('MCP_ALL_TENANTS=true'); putenv('MCP_PILOT_TENANTS=');
    $globalUsername = 'mcp_global_' . $run;
    setEmployeeMcp($b, $employees[$b], ['usuario' => $globalUsername]);
    $globalReport = Readiness::report($store, $cimdClientId, $cimd, $username);
    checkMcp(!$globalReport['ready_to_enable'] && $globalReport['employee'] === []
        && str_contains($globalReport['note'], '--tenant=CHAVE'), 'Diagnóstico global exige tenant explícito sem buscar usuários entre locadoras.');
    $globalReport = Readiness::report($store, $cimdClientId, $cimd, $globalUsername, $b);
    checkMcp($globalReport['ready_to_enable'] && $globalReport['all_tenants']
        && $globalReport['employee'][0]['employee_id'] === $employees[$b], 'Diagnóstico global resolve somente usuário do tenant informado.');
    $globalGrant = grantMcp($b, $employees[$b], $cimdClientId, ['frota:read']);
    [$status, $globalTokens] = tokenMcp($globalGrant);
    checkMcp($status === 200, 'Locadora fora da lista autoriza e recebe token em produção global.');
    [$status, $globalData] = requestMcp('tools/call', ['name' => 'listar_veiculos', 'arguments' => (object) []], $globalTokens['access_token']);
    $globalRows = $globalData['result']['structuredContent']['dados'] ?? [];
    checkMcp($status === 200 && count($globalRows) === 1 && (int) $globalRows[0]['id'] === $vehicles[$b], 'Consulta global continua isolada por tenant.');
    [$status, $globalData] = requestMcp('tools/call', ['name' => 'obter_veiculo', 'arguments' => ['id' => $vehicles[$a]]], $globalTokens['access_token']);
    checkMcp($status === 200 && array_key_exists('dados', $globalData['result']['structuredContent'] ?? [])
        && $globalData['result']['structuredContent']['dados'] === null, 'Modo global não permite detalhe de outro tenant.');
    [$status, $renewedGlobal] = tokenMcp(['grant_type' => 'refresh_token', 'refresh_token' => $globalTokens['refresh_token'], 'client_id' => $cimdClientId, 'resource' => Config::resource()]);
    checkMcp($status === 200 && isset($renewedGlobal['refresh_token']), 'Renovação global funciona sem lista de tenants.');
    setEmployeeMcp($b, $employees[$b], ['status' => 'S']);
    [$status] = requestMcp('tools/list', [], $renewedGlobal['access_token']);
    checkMcp($status === 401, 'Global mantém suspensão de funcionário.');
    setEmployeeMcp($b, $employees[$b], ['status' => 'A']);
    setEmployeeMcp($b, $employees[$b], ['id_role' => null]);
    [$status] = requestMcp('tools/list', [], $renewedGlobal['access_token']);
    checkMcp($status === 401, 'Global mantém permissão obrigatória de conectar.');
    setEmployeeMcp($b, $employees[$b], ['id_role' => $roles[1]]);
    $globalRevocation = (new ServerRequest('POST', Config::issuer() . '/oauth/mcp/revoke', ['Content-Type' => 'application/x-www-form-urlencoded']))
        ->withParsedBody(['client_id' => $cimdClientId, 'token' => $renewedGlobal['refresh_token']]);
    checkMcp((new Gateway())->handle($globalRevocation)->getStatusCode() === 200, 'Revogação global aceita conexão autorizada.');
    [$status] = requestMcp('tools/list', [], $renewedGlobal['access_token']);
    checkMcp($status === 401, 'Revogação global bloqueia access token existente.');
    putenv('MCP_ALL_TENANTS=false'); putenv('MCP_PILOT_TENANTS=' . $a . ',' . $b);
    putenv('APP_ENV=development'); putenv('MCP_PILOT_MODE=false'); putenv('MCP_PILOT_EMPLOYEE_IDS='); putenv('MCP_PILOT_CLIENT_IDS=');

    $scopes = array_keys(Catalog::SCOPES);
    $grant = grantMcp($a, $employees[$a], $clientId, $scopes);
    [$status, $tokens] = tokenMcp($grant);
    checkMcp($status === 200 && isset($tokens['access_token'], $tokens['refresh_token']), 'Troca OAuth deve funcionar: ' . json_encode(['status' => $status, 'error' => $tokens['error'] ?? null]));
    $access = $tokens['access_token']; $refresh = $tokens['refresh_token'];
    [$status] = tokenMcp($grant); checkMcp($status === 400, 'Código OAuth não pode ser reutilizado.');
    $_SESSION['chave'] = $b; $_COOKIE['PHPSESSID'] = 'tenant-b';
    [$status, $data, $response] = requestMcp('server/discover', [], $access);
    checkMcp($status === 200 && isset($data['result']), 'Descoberta sem initialize: ' . json_encode([$status, $data]));
    $info = $data['result']['_meta'][\Mcp\Schema\RequestMeta::SERVER_INFO] ?? [];
    checkMcp(($info['name'] ?? '') === '7Carros' && ($info['title'] ?? '') === '7Carros Locadora'
        && ($info['version'] ?? '') === '1.0.2' && ($info['websiteUrl'] ?? '') === 'https://7carros.com'
        && ($info['description'] ?? '') === 'Consultas autorizadas de frota, clientes, contratos, locações e financeiro.',
        'Descoberta anuncia identidade, versão, descrição e site do 7Carros.');
    checkMcp(!$response->hasHeader('Mcp-Session-Id'), 'Não deve emitir sessão MCP.');
    [$status, $data] = requestMcp('tools/list', [], $access);
    checkMcp($status === 200 && count($data['result']['tools'] ?? []) === 11, 'Catálogo deve conter 11 ferramentas: ' . json_encode($data));
    require __DIR__ . '/mcp-discovery-checks.php';
    require __DIR__ . '/mcp-audit-view-checks.php';
    foreach (Catalog::CATEGORIES as $category => $def) {
        [$status, $data] = requestMcp('tools/call', ['name' => 'listar_' . $category, 'arguments' => (object) []], $access);
        $result = $data['result'] ?? [];
        checkMcp($status === 200 && empty($result['isError']) && count($result['structuredContent']['dados'] ?? []) === ($category === 'lancamentos' ? 2 : 1),
            'Listagem isolada ' . $category . ': ' . json_encode($data));
    }
    $captured = $qb->table('mcp_audit')->withChave($a)->where('event', '=', 'listar_lancamentos')->where('result', '=', 'success')->orderByDesc('id')->first();
    checkMcp($captured['response_payload'] === $data['result']['content'][0]['text']
        && json_decode($captured['request_payload'], true)['ferramenta'] === 'listar_lancamentos',
        'Auditoria captura a solicitação e exatamente o JSON entregue pela ferramenta.');
    $testService = new \App\Services\Mcp\ToolService($store->context($captured['connection_id'], $clientId, $scopes), $store, bin2hex(random_bytes(16)), microtime(true));
    $errorResult = $testService->call('clientes', ['sql' => 'must-not-be-recorded']);
    $capturedError = $qb->table('mcp_audit')->withChave($a)->where('event', '=', 'listar_clientes')->orderByDesc('id')->first();
    $capturedErrorBody = json_decode($capturedError['response_payload'], true);
    checkMcp($capturedErrorBody['isError'] === true && $capturedErrorBody['mensagem'] === $errorResult->content[0]->text
        && !str_contains($capturedError['request_payload'], 'must-not-be-recorded'),
        'Erro público é auditado sem argumentos desconhecidos ou exceções internas.');
    [$status, $data] = requestMcp('tools/call', ['name' => 'obter_cliente', 'arguments' => ['id' => $clients[$b]]], $access);
    checkMcp(array_key_exists('dados', $data['result']['structuredContent'] ?? []) && $data['result']['structuredContent']['dados'] === null, 'ID de outro tenant não pode vazar.');
    [$status, $data] = requestMcp('tools/call', ['name' => 'obter_cliente', 'arguments' => ['id' => $clients[$a]]], $access);
    checkMcp(!isset($data['result']['structuredContent']['dados']['cpf_cnpj']), 'Documento ausente por padrão.');
    [$status, $data] = requestMcp('tools/call', ['name' => 'obter_cliente', 'arguments' => ['id' => $clients[$a], 'incluir_documentos' => true, 'incluir_contatos' => true]], $access);
    $row = $data['result']['structuredContent']['dados'] ?? [];
    checkMcp(isset($row['cpf_cnpj'], $row['emails'][0], $row['telefones'][0]) && !isset($row['cnh_codigo_seguranca']), 'Autorização adicional e projeção de campos: ' . json_encode($data));
    [$status, $data] = requestMcp('tools/call', ['name' => 'resumir_financeiro', 'arguments' => (object) []], $access);
    checkMcp(($data['result']['structuredContent']['dados'][0]['total'] ?? '') === '200.00', 'Parcelas somadas uma única vez: ' . json_encode($data));
    require __DIR__ . '/mcp-question-checks.php';
    require __DIR__ . '/mcp-security-checks.php';
    require __DIR__ . '/mcp-claude-code-checks.php';
    foreach ([['name' => 'listar_clientes', 'arguments' => ['chave' => $b]],
        ['name' => 'listar_clientes', 'arguments' => ['filtros' => ['por_pagina' => 101]]],
        ['name' => 'listar_contratos', 'arguments' => ['filtros' => ['data_de' => '2026-02-31']]]] as $bad) {
        [$status, $data] = requestMcp('tools/call', $bad, $access);
        checkMcp(isset($data['error']) || !empty($data['result']['isError']), 'Argumentos inválidos devem ser bloqueados.');
    }
    [$status, $data] = requestMcp('tools/call', ['name' => 'listar_clientes', 'arguments' => ['filtros' => ['busca' => "%' OR 1=1 --"]]], $access);
    checkMcp(($data['result']['structuredContent']['dados'] ?? null) === [], 'Busca não executa SQL.');
    foreach ([['2099-01-01', null], ['2026-07-28', '2025-11-25']] as [$version, $header]) {
        [$status, $data] = requestMcp('tools/list', [], $access, $version, $header);
        checkMcp(isset($data['error']), 'Versões desconhecidas e divergências de revisão são rejeitadas.');
    }
    // Both discovery endpoints, Origin checks and absent bearer challenges.
    foreach (['/.well-known/oauth-protected-resource/mcp', '/.well-known/oauth-authorization-server'] as $path) {
        $meta = (new Gateway())->handle(new ServerRequest('GET', Config::issuer() . $path));
        checkMcp($meta->getStatusCode() === 200, 'Discovery OAuth disponível.');
    }
    $unauth = (new Gateway())->handle(new ServerRequest('POST', Config::resource()));
    checkMcp($unauth->getStatusCode() === 401 && str_contains($unauth->getHeaderLine('WWW-Authenticate'), 'resource_metadata='), 'Challenge informa metadados.');
    $origin = (new Gateway())->handle(new ServerRequest('POST', Config::resource(), ['Origin' => 'https://evil.example']));
    checkMcp($origin->getStatusCode() === 403, 'Origin inválido rejeitado.');
    // A validly signed token with wrong issuer/audience/expiry still cannot authenticate.
    $jwt = \Lcobucci\JWT\Configuration::forAsymmetricSigner(new \Lcobucci\JWT\Signer\Rsa\Sha256(),
        \Lcobucci\JWT\Signer\Key\InMemory::file($directory . '/private.pem'), \Lcobucci\JWT\Signer\Key\InMemory::file($directory . '/public.pem'));
    $validClaims = $jwt->parser()->parse($access)->claims();
    foreach (['audience', 'issuer', 'expiry', 'signature'] as $badClaim) {
        $now = new DateTimeImmutable();
        $badToken = $jwt->builder()->issuedBy($badClaim === 'issuer' ? 'https://elsewhere.example' : Config::issuer())
            ->permittedFor($badClaim === 'audience' ? 'https://elsewhere.example/mcp' : Config::resource())
            ->relatedTo($validClaims->get('sub'))->identifiedBy($validClaims->get('jti'))
            ->issuedAt($now->modify('-2 minutes'))->canOnlyBeUsedAfter($now->modify('-2 minutes'))
            ->expiresAt($now->modify($badClaim === 'expiry' ? '-1 minute' : '+10 minutes'))
            ->withClaim('client_id', $clientId)->withClaim('scopes', $scopes)
            ->getToken($jwt->signer(), $jwt->signingKey())->toString();
        if ($badClaim === 'signature') {
            $parts = explode('.', $badToken); $parts[2] = str_repeat('a', strlen($parts[2])); $badToken = implode('.', $parts);
        }
        [$status] = requestMcp('tools/list', [], $badToken);
        checkMcp($status === 401, 'Credencial com ' . $badClaim . ' incorreto rejeitada.');
    }
    // Pagination, independent detail schemas and the branch-sensitive financial aggregate.
    foreach (Catalog::CATEGORIES as $category => $def) {
        $list = (new ReadModel((new TokenValidator($store))->validate($access)))->listing($category, []);
        $first = (int) $list['dados'][0]['id'];
        [$status, $data] = requestMcp('tools/call', ['name' => 'obter_' . Catalog::SINGULAR[$category], 'arguments' => ['id' => $first]], $access);
        checkMcp($status === 200 && empty($data['result']['isError']) && (int) ($data['result']['structuredContent']['dados']['id'] ?? 0) === $first, 'Detalhe autorizado ' . $category);
    }
    [$status, $data] = requestMcp('tools/call', ['name' => 'listar_lancamentos', 'arguments' => ['filtros' => ['por_pagina' => 1, 'pagina' => 2]]], $access);
    checkMcp(count($data['result']['structuredContent']['dados'] ?? []) === 1 && ($data['result']['structuredContent']['paginacao']['total'] ?? 0) === 2, 'Paginação e totais concordam.');
    $basicGrant = grantMcp($a, $employees[$a], $clientId, ['clientes:read']);
    [$status, $basic] = tokenMcp($basicGrant);
    checkMcp($status === 200, 'Consentimento básico funciona.');
    [$status, $data] = requestMcp('tools/call', ['name' => 'obter_cliente', 'arguments' => ['id' => $clients[$a], 'incluir_documentos' => true]], $basic['access_token']);
    checkMcp(!empty($data['result']['isError']), 'Permissão sem consentimento adicional não libera documento.');
    $pipes = [];
    $worker = proc_open([PHP_BINARY, __FILE__, '--suspend-during-read', $a, (string) $employees[$a]],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($worker)) throw new RuntimeException('Worker de concorrência indisponível.');
    fclose($pipes[0]);
    checkMcp(trim((string) fgets($pipes[1])) === 'ready', 'Consulta de concorrência preparada.');
    [$status, $data] = requestMcp('tools/call', ['name' => 'listar_clientes', 'arguments' => (object) []], $access);
    fclose($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[2]);
    checkMcp(proc_close($worker) === 0 && $status === 403 && !isset($data['result']), 'Suspensão durante a consulta impede entrega de dados.');
    setEmployeeMcp($a, $employees[$a], ['status' => 'A']);
    $context = (new TokenValidator($store))->validate($access);
    new ReadModel($context);
    $limits = $db->query("SHOW SESSION VARIABLES WHERE Variable_name IN ('max_execution_time', 'max_statement_time')")->fetch_all(MYSQLI_ASSOC);
    checkMcp(count($limits) === 1 && (float) $limits[0]['Value'] === ($limits[0]['Variable_name'] === 'max_statement_time' ? 15.0 : 15000.0),
        'ReadModel configura limite efetivo de 15 segundos no banco conectado.');
    $blockedBranch = $qb->table('matrizes_filiais')->withChave($a)->insert(['chave' => $a, 'nome_fantasia' => 'Restrita', 'currency_code' => 'USD']);
    $hidden = $qb->table('clientes')->withChave($a)->insert(['chave' => $a, 'id_matriz_filial' => $blockedBranch,
        'nome_rsocial' => 'Restrito', 'foto' => '', 'data_cadastro' => date('Y-m-d')]);
    checkMcp((new ReadModel($context))->detail('clientes', $hidden, false, false) === null, 'Detalhe deve respeitar filial.');
    [$status, $data] = handshakeMcp('tools/call', ['name' => 'obter_cliente', 'arguments' => ['id' => $hidden]], $access);
    checkMcp($status === 200 && ($data['result']['structuredContent']['dados'] ?? null) === null
        && isset($data['result']['structuredContent']), 'Consulta via initialize respeita filial.');
    $qb->table('financeiro')->withChave($a)->insert(['chave' => $a, 'id_matriz_filial' => $blockedBranch,
        'tipo' => 'R', 'pago' => 'N', 'data_criada' => date('Y-m-d'), 'data_venci' => date('Y-m-d'),
        'valor_subtotal' => '50.00', 'valor_total' => '50.00']);
    $restrictedTotals = (new ReadModel($context))->summary([]);
    checkMcp(count($restrictedTotals) === 1 && $restrictedTotals[0]['moeda'] === 'BRL' && $restrictedTotals[0]['total'] === '200.00', 'Agregação respeita filial.');
    $qb->table('funcionarios_filiais')->withChave($a)->insert(['chave' => $a, 'id_funcionario' => $employees[$a], 'id_matriz_filial' => $blockedBranch]);
    $both = (new TokenValidator($store))->validate($access);
    $totals = (new ReadModel($both))->summary([]);
    checkMcp(count($totals) === 2 && array_sum(array_map(fn ($r) => $r['moeda'] === 'USD' ? 1 : 0, $totals)) === 1, 'Moedas distintas não são somadas.');
    $qb->table('funcionarios_filiais')->withChave($a)->where('id_funcionario', '=', $employees[$a])->where('id_matriz_filial', '=', $blockedBranch)->delete();
    $qb->table('locacoes')->withChave($a)->insert(['chave' => $a, 'codigo' => 'LO' . bin2hex(random_bytes(5)),
        'id_matriz_filial_retirada' => $blockedBranch, 'id_matriz_filial_devolucao' => $branches[$a],
        'data_saida' => '2026-11-01 10:00:00', 'data_prevista' => '2026-12-01 10:00:00', 'dias' => 30, 'cliente_nome' => 'Teste MCP']);
    checkMcp((new ReadModel($context))->listing('locacoes', [])['paginacao']['total'] === 2, 'Locação permite filial de retirada OU devolução.');
    $sibling = $qb->table('funcionarios')->withChave($a)->insert(['chave' => $a, 'plano' => '1', 'status' => 'A', 'id_role' => $roles[0],
        'nome' => 'Segundo usuário MCP', 'senha' => password_hash(bin2hex(random_bytes(20)), PASSWORD_DEFAULT)]);
    $siblingGrant = grantMcp($a, $sibling, $clientId, ['clientes:read']);
    [$status, $siblingTokens] = tokenMcp($siblingGrant);
    checkMcp($status === 200, 'Segundo funcionário pode conectar.');
    $none = new Context($a, $employees[$a], $context->connectionId, $clientId, $scopes, $context->permissions, false, []);
    checkMcp((new ReadModel($none))->listing('clientes', [])['paginacao']['total'] === 0, 'Nenhuma filial nunca significa todas.');
    $invalid = false; try { new Context('', 1, 'x', 'y', [], [], true, []); } catch (InvalidArgumentException) { $invalid = true; }
    checkMcp($invalid, 'Contexto sem chave não é construído.');
    $readPid = $qb->table('permissions')->withoutChave()->where('`key`', '=', 'clientes.visualizar')->first()['id'];
    $qb->table('funcionarios_role_permissions')->withoutChave()->where('role_id', '=', $roles[0])->where('permission_id', '=', $readPid)->delete();
    [$status, $data] = requestMcp('tools/list', [], $access);
    checkMcp(count($data['result']['tools'] ?? []) === 9, 'Permissão removida afeta próxima chamada.');
    [$status, $data] = handshakeMcp('tools/list', [], $access);
    checkMcp($status === 200 && count($data['result']['tools'] ?? []) === 9, 'Descoberta via initialize relê permissões atuais.');
    $qb->table('funcionarios_role_permissions')->withoutChave()->insert(['role_id' => $roles[0], 'permission_id' => $readPid]);
    setEmployeeMcp($a, $employees[$a], ['status' => 'S']);
    [$status] = requestMcp('tools/list', [], $access); checkMcp($status === 401, 'Suspensão bloqueia access token válido.');
    [$status] = handshakeMcp('tools/list', [], $access);
    checkMcp($status === 401, 'Suspensão bloqueia descoberta via initialize.');
    [$siblingStatus] = requestMcp('tools/list', [], $siblingTokens['access_token']);
    checkMcp($siblingStatus === 200, 'Suspensão individual preserva conexões de outros funcionários ativos.');
    $newAuthorizationBlocked = false;
    try { grantMcp($a, $employees[$a], $clientId, ['clientes:read']); } catch (Throwable) { $newAuthorizationBlocked = true; }
    checkMcp($newAuthorizationBlocked, 'Suspensão impede nova autorização.');

    [$status] = tokenMcp(['grant_type' => 'refresh_token', 'refresh_token' => $refresh, 'client_id' => $clientId, 'resource' => Config::resource()]);
    checkMcp($status === 401 || $status === 403, 'Suspensão bloqueia renovação.');
    setEmployeeMcp($a, $employees[$a], ['status' => 'A']);
    [$status] = requestMcp('tools/list', [], $access); checkMcp($status === 200, 'Reativação retoma acesso sem reconciliação.');
    $refreshParams = ['grant_type' => 'refresh_token', 'refresh_token' => $refresh, 'client_id' => $clientId, 'resource' => Config::resource()];
    [$status, $next] = tokenMcp($refreshParams); checkMcp($status === 200, 'Renovação deve rotacionar: ' . json_encode(['status' => $status, 'error' => $next['error'] ?? null]));
    $newAccess = $next['access_token'];
    [$status] = tokenMcp($refreshParams); checkMcp($status === 400, 'Refresh reutilizado rejeitado.');
    [$status] = requestMcp('tools/list', [], $newAccess); checkMcp($status === 401, 'Reutilização revoga a família.');
    $qb->table('funcionarios')->withChave($a)->where('id', '=', $sibling)->delete();
    [$status] = requestMcp('tools/list', [], $siblingTokens['access_token']);
    checkMcp($status === 401, 'Funcionário excluído perde acesso mesmo com token válido.');
    // Independent employee/tenant, revocation endpoint and absolute family lifetime.
    $grantB = grantMcp($b, $employees[$b], $clientId, ['clientes:read']);
    [$status, $tokensB] = tokenMcp($grantB);
    checkMcp($status === 200, 'Outro tenant pode autorizar independentemente.');
    $contextB = (new TokenValidator($store))->validate($tokensB['access_token']);
    $recordB = $store->scopedConnection($b, $contextB->connectionId);
    checkMcp(strtotime($recordB['expires_at']) <= time() + 90 * 86400, 'Refresh tem validade absoluta máxima de 90 dias.');
    $revokeParams = ['client_id' => $clientId, 'token' => $tokensB['refresh_token']];
    $revocation = (new ServerRequest('POST', Config::issuer() . '/oauth/mcp/revoke', ['Content-Type' => 'application/x-www-form-urlencoded']))->withParsedBody($revokeParams);
    checkMcp((new Gateway())->handle($revocation)->getStatusCode() === 200, 'Revogação OAuth funciona.');
    [$status] = requestMcp('tools/list', [], $tokensB['access_token']); checkMcp($status === 401, 'Revogar refresh invalida toda a conexão.');
    $qb->table('mcp_credentials')->withChave($b)->where('connection_id', '=', $contextB->connectionId)->delete();
    $qb->table('mcp_connections')->withChave($b)->where('connection_id', '=', $contextB->connectionId)->delete();
    [$status] = requestMcp('tools/list', [], $tokensB['access_token']); checkMcp($status === 401, 'Conexão removida impede uso de credenciais remanescentes.');
    foreach (['127.0.0.1', '10.0.0.1', '100.64.0.1', '169.254.169.254', '192.0.2.1', '224.0.0.1', '::1', '::ffff:127.0.0.1', 'fe80::1', '2001:db8::1'] as $ip) {
        checkMcp(!ClientRegistry::publicIp($ip), 'CIMD rejeita IP especial ' . $ip);
    }
    checkMcp(ClientRegistry::publicIp('8.8.8.8') && ClientRegistry::publicIp('2606:4700:4700::1111'), 'CIMD reconhece endereços públicos.');
    // Rate limiter concurrency primitive and tenant aggregation.
    putenv('MCP_CONNECTION_RATE_LIMIT=1'); putenv('MCP_TENANT_RATE_LIMIT=2');
    $limiter = new RateLimiter();
    checkMcp($limiter->consume('rate-' . $run, 'a') && !$limiter->consume('rate-' . $run, 'a')
        && $limiter->consume('rate-' . $run, 'b') && !$limiter->consume('rate-' . $run, 'c'), 'Limites por conexão e tenant.');
    foreach (['https://localhost/doc', 'http://public.example/doc', 'https://127.0.0.1/doc', 'https://approved.example@127.0.0.1/doc'] as $url) {
        $rejected = false; try { ClientRegistry::validateMetadataUrl($url, ['approved.example']); } catch (Throwable) { $rejected = true; }
        checkMcp($rejected, 'CIMD não permite destinos fora da aprovação.');
    }
    // End-to-end HTTP consent catches response headers/session behavior missed by service-only tests.
    require __DIR__ . '/mcp-consent-http-checks.php';
    echo "OK: $count verificações MCP (OAuth, protocolo, isolamento, filiais, ferramentas, suspensão, rotação e limites).\n";
} finally {
    foreach ([$a, $b] as $tenant) {
        foreach (['mcp_security_state', 'mcp_audit', 'mcp_credentials', 'mcp_connections', 'contatos_emails', 'contatos_telefones',
            'financeiro', 'locacoes', 'contratos', 'clientes', 'veiculos', 'funcionarios_filiais', 'funcionarios', 'matrizes_filiais'] as $table) {
            $qb->table($table)->withChave($tenant)->delete();
        }
    }
    foreach ($roles as $role) $qb->table('funcionarios_role_permissions')->withoutChave()->where('role_id', '=', $role)->delete();
    foreach ([$a, $b] as $tenant) $qb->table('funcionarios_roles')->withChave($tenant)->delete();
    if ($claudeClientCreated) $qb->table('mcp_clients')->withoutChave()->where('client_id', '=', ClientRegistry::CLAUDE_CODE_CLIENT_ID)->delete();
    foreach ([$clientId, $cimdClientId] as $cleanupClient) $qb->table('mcp_clients')->withoutChave()->where('client_id', '=', $cleanupClient)->delete();
    session_write_close();
    foreach (glob($directory . '/*') as $file) unlink($file); rmdir($directory);
    foreach ([$a, $b, 'oauth:127.0.0.1', 'rate-' . $run] as $tenant) {
        $file = dirname(__DIR__) . '/storage/cache/mcp-rate/' . hash('sha256', $tenant) . '.json';
        if (is_file($file)) unlink($file);
    }
    unset($_SESSION['chave'], $_COOKIE['PHPSESSID']);
}
