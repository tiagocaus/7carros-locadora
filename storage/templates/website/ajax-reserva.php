<?php
/**
 * Proxy AJAX — submete pedido de reserva a API publica do sistema.
 *
 * Recebe JSON do JS (custom.js) com dados do cliente e base64 dos documentos
 * (opcionais conforme site_config.envio_documentos + obrigatoriedade). Usa
 * SiteApi::criarReserva para repassar ao backend mantendo o X-Site-Token
 * server-side.
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

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payload invalido']);
    exit;
}

// Se cliente esta logado na sessao do site, injeta no payload (o backend
// usa id_cliente em vez de criar um novo cliente). Sobrepoe qualquer valor
// que o JS tenha enviado — confiamos apenas na sessao server-side.
if (!empty($_SESSION['cliente_id'])) {
    $payload['cliente_id'] = (int) $_SESSION['cliente_id'];
}

$resposta = $api->criarReserva($payload);
echo json_encode($resposta);
