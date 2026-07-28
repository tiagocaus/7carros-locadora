<?php

require_once __DIR__ . '/includes/portal-session.php';
portalSessionStart();
require_once __DIR__ . '/includes/functions.php';

$payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && portalCsrfValido($payload)) {
    $token = (string) ($_SESSION['portal_token'] ?? '');
    if ($token !== '') {
        $api->portalRequest('POST', '/api/public/portal/logout', [], $token);
    }
}

$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}
portalJson(['success' => true]);
