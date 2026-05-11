<?php
/**
 * Proxy AJAX — apenas checa se ha cliente com o CPF/CNPJ informado no tenant.
 * Retorna { success, existe } sem nenhum dado pessoal.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/includes/functions.php';

$documento = preg_replace('/\D/', '', (string) ($_GET['documento'] ?? ''));
if (strlen($documento) < 11) {
    echo json_encode(['success' => true, 'existe' => false]);
    exit;
}

echo json_encode($api->clienteExiste($documento));
