<?php
/**
 * Proxy AJAX — autentica cliente. Se OK, grava sessao PHP local
 * ($_SESSION['cliente_id'], cliente_nome). Cookie eh de sessao do
 * navegador (expira ao fechar) por padrao do PHP.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo nao permitido']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$usuario = trim((string) ($payload['usuario'] ?? ''));
$senha   = (string) ($payload['senha'] ?? '');

if (!$usuario || !$senha) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Usuario e senha obrigatorios']);
    exit;
}

$resposta = $api->clienteLogin($usuario, $senha);

if (!empty($resposta['success']) && !empty($resposta['cliente']['id'])) {
    $_SESSION['cliente_id']       = (int) $resposta['cliente']['id'];
    $_SESSION['cliente_nome']     = (string) ($resposta['cliente']['nome'] ?? '');
    $_SESSION['cliente_email']    = (string) ($resposta['cliente']['email'] ?? '');
    $_SESSION['cliente_telefone'] = (string) ($resposta['cliente']['telefone'] ?? '');
}

echo json_encode($resposta);
