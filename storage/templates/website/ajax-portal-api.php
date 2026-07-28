<?php

require_once __DIR__ . '/includes/portal-session.php';
portalSessionStart();
require_once __DIR__ . '/includes/functions.php';

$token = (string) ($_SESSION['portal_token'] ?? '');
$perfil = (string) ($_SESSION['portal_perfil'] ?? '');
if ($token === '' || !in_array($perfil, ['cliente', 'investidor'], true)) {
    portalJson(['success' => false, 'message' => 'Sessao expirada.', 'session_expired' => true], 401);
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
if ($method !== 'GET' && !portalCsrfValido($payload)) {
    portalJson(['success' => false, 'message' => 'Sessao expirada. Recarregue a pagina.'], 403);
}

$acao = (string) ($_GET['action'] ?? '');
$recursosCliente = ['contratos', 'locacoes', 'faturas', 'multas', 'manutencoes', 'veiculos', 'indicacao'];
$recursosInvestidor = ['veiculos', 'manutencoes', 'comissoes', 'operacoes', 'desempenho'];
$endpoint = '';
$apiMethod = $method;
$dados = $method === 'GET' ? $_GET : $payload;
unset($dados['action'], $dados['_csrf']);

if ($acao === 'sessao' && $method === 'GET') {
    $endpoint = '/api/public/portal/sessao';
} elseif ($acao === 'dashboard' && $method === 'GET') {
    $endpoint = '/api/public/portal/dashboard';
} elseif ($acao === 'perfil' && $method === 'PUT') {
    $endpoint = '/api/public/portal/perfil';
} elseif ($acao === 'senha' && $method === 'POST') {
    $endpoint = '/api/public/portal/senha';
} elseif ($acao === 'link-pagamento' && $method === 'POST' && ctype_digit((string) ($dados['id'] ?? ''))) {
    $endpoint = '/api/public/portal/faturas/' . (int) $dados['id'] . '/link-pagamento';
    unset($dados['id']);
} elseif (
    $method === 'GET'
    && (
        ($perfil === 'cliente' && in_array($acao, $recursosCliente, true))
        || ($perfil === 'investidor' && in_array($acao, $recursosInvestidor, true))
    )
) {
    $endpoint = '/api/public/portal/' . $acao;
}

if ($endpoint === '') {
    portalJson(['success' => false, 'message' => 'Operacao invalida.'], 422);
}

$resposta = $api->portalRequest($apiMethod, $endpoint, $dados, $token);
if (!empty($resposta['session_expired'])) {
    unset($_SESSION['portal_token'], $_SESSION['portal_perfil'], $_SESSION['portal_nome']);
}
portalJson($resposta ?: ['success' => false, 'message' => 'Servico temporariamente indisponivel.']);
