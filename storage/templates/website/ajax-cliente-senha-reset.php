<?php
/**
 * Proxy AJAX — solicita reset de senha. Sempre retorna success=true,
 * mesmo se documento nao existir (evita enumeration).
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo nao permitido']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$documento = preg_replace('/\D/', '', (string) ($payload['documento'] ?? ''));

if (strlen($documento) < 11) {
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode($api->clienteSenhaReset($documento));
