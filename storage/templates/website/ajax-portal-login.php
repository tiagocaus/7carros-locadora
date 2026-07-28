<?php

require_once __DIR__ . '/includes/portal-session.php';
portalSessionStart();
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    portalJson(['success' => false, 'message' => 'Metodo nao permitido.'], 405);
}

$payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
if (!portalCsrfValido($payload)) {
    portalJson(['success' => false, 'message' => 'Sessao expirada. Recarregue a pagina.'], 403);
}

$acao = (string) ($payload['acao'] ?? 'login');
$perfil = (string) ($payload['perfil'] ?? '');
$usuario = trim((string) ($payload['usuario'] ?? ''));

if ($acao === 'reset') {
    $resposta = $api->portalRequest('POST', '/api/public/portal/senha/solicitar', [
        'perfil' => $perfil,
        'usuario' => $usuario,
    ]);
    portalJson($resposta ?: [
        'success' => true,
        'message' => 'Se o cadastro for localizado, enviaremos as instrucoes por e-mail.',
    ]);
}

$senha = (string) ($payload['senha'] ?? '');
if ($usuario === '' || $senha === '' || !in_array($perfil, ['cliente', 'investidor'], true)) {
    portalJson(['success' => false, 'message' => 'Preencha o perfil, usuario e senha.'], 422);
}

$resposta = $api->portalRequest('POST', '/api/public/portal/login', [
    'perfil' => $perfil,
    'usuario' => $usuario,
    'senha' => $senha,
]);

if (!empty($resposta['success']) && !empty($resposta['data']['token'])) {
    session_regenerate_id(true);
    $_SESSION['portal_token'] = (string) $resposta['data']['token'];
    $_SESSION['portal_perfil'] = (string) $resposta['data']['perfil'];
    $_SESSION['portal_nome'] = (string) ($resposta['data']['nome'] ?? '');
    $_SESSION['portal_csrf'] = bin2hex(random_bytes(24));
    unset($resposta['data']['token']);
    $resposta['data']['csrf'] = $_SESSION['portal_csrf'];
}

portalJson($resposta ?: ['success' => false, 'message' => 'Nao foi possivel entrar no portal.'], 200);
