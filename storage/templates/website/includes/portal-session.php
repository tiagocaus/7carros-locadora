<?php

/**
 * Sessao local do website. O token opaco da API nunca e entregue ao browser.
 */
function portalSessionStart(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $sessionPath = session_save_path();
    if ($sessionPath === '' || !is_dir($sessionPath) || !is_writable($sessionPath)) {
        session_save_path(sys_get_temp_dir());
    }
    session_name('portal_area');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    if (empty($_SESSION['portal_csrf'])) {
        $_SESSION['portal_csrf'] = bin2hex(random_bytes(24));
    }
}

function portalCsrfValido(array $payload): bool
{
    $recebido = (string) ($payload['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $esperado = (string) ($_SESSION['portal_csrf'] ?? '');
    return $recebido !== '' && $esperado !== '' && hash_equals($esperado, $recebido);
}

function portalJson(array $dados, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
