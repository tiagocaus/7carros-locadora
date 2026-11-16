<?php
/** Existing disposable tenants only; read-only audit Model and tenant-safe JOIN regression. */
declare(strict_types=1);

$auditModel = new \App\Models\McpAuditLog();
$previousTenant = $_SESSION['chave'] ?? null;
$_SESSION['chave'] = $a;
$auditCountBefore = $auditModel->contar();
$auditFixtureIds = [];
try {
    foreach ([[$a, $employees[$a], 'listar_veiculos'], [$a, $employees[$b], 'obter_veiculo'],
        [$a, $employees[$a], 'token'], [$b, $employees[$b], 'listar_veiculos']] as [$tenant, $employee, $event]) {
        $auditFixtureIds[] = $qb->table('mcp_audit')->withChave($tenant)->insert([
            'chave' => $tenant, 'employee_id' => $employee, 'connection_id' => 'audit-view-fixture',
            'client_id' => 'audit-view-missing-client', 'request_id' => bin2hex(random_bytes(16)),
            'event' => $event, 'result' => 'success', 'records' => 7, 'duration_ms' => 12,
        ]);
    }
    $questionText = "  Pergunta fiel? Á🚙\n" . str_repeat('ç🚙', 300);
    $payloadRequest = json_encode(['ferramenta' => 'listar_veiculos', 'argumentos' => ['pergunta_usuario' => $questionText, 'filtros' => ['busca' => str_repeat('Á🚙', 300)]]], JSON_UNESCAPED_UNICODE);
    $payloadResponse = json_encode(['dados' => [['nome' => '<img src=x onerror=alert(1)>', 'texto' => str_repeat('ç🚙', 400)]]], JSON_UNESCAPED_UNICODE);
    $qb->table('mcp_audit')->withChave($a)->where('id', '=', $auditFixtureIds[0])
        ->update(['request_payload' => $payloadRequest, 'response_payload' => $payloadResponse]);
    $detail = $auditModel->detalhar($auditFixtureIds[0]);
    checkMcp($detail['pergunta_usuario'] === $questionText, 'Model preserva a pergunta completa, sem normalizar espaços ou quebras de linha.');
    checkMcp($detail['solicitacao'] === $payloadRequest && $detail['retorno'] === $payloadResponse,
        'Detalhe preserva JSON completo, Unicode e texto não confiável sem truncamento.');
    checkMcp($auditModel->detalhar($auditFixtureIds[3]) === null && $auditModel->detalhar($auditFixtureIds[2]) === null,
        'Detalhe não acessa outro tenant nem eventos técnicos por ID.');
    checkMcp($auditModel->detalhar($auditFixtureIds[1])['solicitacao'] === null,
        'Histórico sem conteúdo permanece consultável com payload nulo.');
    checkMcp($auditModel->contar() === $auditCountBefore + 2, 'Auditoria visual exclui transporte/token e registros de outro tenant.');
    $auditRows = $auditModel->listarPaginado(1, 2);
    checkMcp($auditRows[0]['pergunta_previa'] === null && $auditRows[1]['pergunta_previa'] === mb_substr($questionText, 0, 256), 'Prévia lê apenas 256 caracteres da pergunta; legado retorna null.');
    checkMcp(mb_strlen($auditRows[1]['solicitacao_previa']) === 256
        && !isset($auditRows[1]['solicitacao']) && !isset($auditRows[1]['retorno']),
        'Model limita prévias no SQL sem carregar payload completo na listagem.');
    checkMcp(count($auditRows) === 2 && array_column($auditRows, 'id') === [$auditFixtureIds[1], $auditFixtureIds[0]],
        'Paginação da auditoria mantém ordem determinística e isolamento.');
    checkMcp($auditRows[0]['usuario_nome'] === null && $auditRows[0]['aplicativo'] === null,
        'JOIN de funcionário de outro tenant e aplicativo ausente não revela identidade.');
    checkMcp($auditRows[1]['usuario_nome'] === 'Teste MCP' && (int) $auditRows[1]['registros'] === 7
        && (int) $auditRows[1]['duracao_ms'] === 12, 'Projeção exibe funcionário, registros retornados e duração existentes.');
    checkMcp(!array_key_exists('chave', $auditRows[0]) && !array_key_exists('connection_id', $auditRows[0])
        && !array_key_exists('arguments', $auditRows[0]), 'API do Model não expõe identidade tenant, conexão ou payloads.');
    checkMcp($auditModel->contar('no-match-' . $run) === 0 && $auditModel->listarPaginado(1, 10, "%' OR 1=1 --") === [],
        'Busca vazia e tentativa de injeção não escapam do tenant.');
    checkMcp($auditModel->contar('nome-traduzido', ['listar_veiculos']) >= 1,
        'Busca por operação traduzida inclui somente ferramentas permitidas.');
    checkMcp($auditModel->contar('nome-traduzido', ['token']) === 0, 'Busca traduzida não libera eventos técnicos.');
    foreach ([str_repeat('🚙', 400), str_repeat('a', 250) . '\\"' . str_repeat('🚙', 400),
        str_repeat("\n\t\\\"", 500), str_repeat('Á', 254) . '🚙é🚗'] as $escapedQuestion) {
        // Default json_encode deliberately produces escaped surrogate pairs and control characters.
        $escapedPayload = json_encode(['argumentos' => ['pergunta_usuario' => $escapedQuestion]]);
        $qb->table('mcp_audit')->withChave($a)->where('id', '=', $auditFixtureIds[1])->update(['request_payload' => $escapedPayload]);
        checkMcp($auditModel->detalhar($auditFixtureIds[1])['pergunta_usuario'] === $escapedQuestion
            && $auditModel->listarPaginado(1, 1)[0]['pergunta_previa'] === mb_substr($escapedQuestion, 0, 256),
            'Prévia JSON limitada preserva pares substitutos, controles e escapes nas bordas do corte.');
    }
    foreach (['{invalid', '{"argumentos":{"pergunta_usuario":null}}', '{"argumentos":{"pergunta_usuario":{}}}'] as $legacyPayload) {
        $qb->table('mcp_audit')->withChave($a)->where('id', '=', $auditFixtureIds[1])->update(['request_payload' => $legacyPayload]);
        checkMcp($auditModel->detalhar($auditFixtureIds[1])['pergunta_usuario'] === null
            && $auditModel->listarPaginado(1, 1)[0]['pergunta_previa'] === null,
            'JSON inválido e pergunta sem tipo string não quebram histórico.');
    }
    $_SESSION['chave'] = $b;
    $auditTenantB = $auditModel->listarPaginado(1, 1);
    checkMcp((int) $auditTenantB[0]['id'] === $auditFixtureIds[3], 'Troca de tenant em Model reutilizado relê o escopo.');
    unset($_SESSION['chave']);
    $auditMissingTenant = false;
    try { $auditModel->listarPaginado(1, 10); } catch (RuntimeException) { $auditMissingTenant = true; }
    checkMcp($auditMissingTenant, 'Auditoria sem contexto não consulta dados globais.');
} finally {
    foreach ([$a, $b] as $tenant) {
        $qb->table('mcp_audit')->withChave($tenant)->whereIn('id', $auditFixtureIds)->delete();
    }
    if ($previousTenant === null) unset($_SESSION['chave']); else $_SESSION['chave'] = $previousTenant;
}
