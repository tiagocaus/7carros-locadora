<?php

require_once __DIR__ . '/includes/api.php';

header('Content-Type: application/json; charset=utf-8');

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados invalidos.']);
    exit;
}

echo json_encode($api->validarPromocao($payload), JSON_UNESCAPED_UNICODE);
