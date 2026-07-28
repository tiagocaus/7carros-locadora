<?php

require_once __DIR__ . '/includes/portal-session.php';
portalSessionStart();
require_once __DIR__ . '/includes/functions.php';

$token = (string) ($_SESSION['portal_token'] ?? '');
$id = (string) ($_GET['id'] ?? '');
if ($token === '' || !ctype_digit($id)) {
    http_response_code(404);
    exit('Documento nao encontrado.');
}

$documento = $api->portalDocument('/api/public/portal/faturas/' . (int) $id . '/recibo', $token);
if ($documento['status'] !== 200 || stripos($documento['content_type'], 'pdf') === false) {
    http_response_code($documento['status'] ?: 502);
    exit('Nao foi possivel gerar o documento.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="recibo.pdf"');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
echo $documento['body'];
