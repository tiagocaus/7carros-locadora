<?php
/** Loopback-only HTTP harness. Never publish or use outside disposable local fixtures. */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!defined('APP_ROOT')) define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/app/Helpers/helpers.php';
$_ENV['APP_ENV'] = 'development';
$directory = getenv('MCP_CONSENT_TEST_DIRECTORY') ?: '';
$tenant = getenv('MCP_CONSENT_TEST_TENANT') ?: '';
$employee = (int) getenv('MCP_CONSENT_TEST_EMPLOYEE');
if (PHP_SAPI !== 'cli-server' || App\Core\Database::env('DB_HOST') !== 'localhost'
    || !str_starts_with($tenant, 'mcp_test_a_') || !is_dir($directory) || $employee < 1) {
    http_response_code(404); exit;
}
date_default_timezone_set('America/Sao_Paulo');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/mcp') {
    // Loopback harness emulates TLS termination only; keep the actual network headers/body.
    $_SERVER['HTTPS'] = 'on';
    \App\Controllers\Mcp\Gateway::emit((new \App\Controllers\Mcp\Gateway())->handle(\App\Controllers\Mcp\Gateway::request()));
    return;
}
ini_set('session.save_path', $directory);
session_start();
$_SESSION['authenticated'] = true;
$_SESSION['user_id'] = $employee;
$_SESSION['chave'] = $tenant;
if ($path === '/api/logs/mcp' || preg_match('~^/api/logs/mcp/([^/]+)$~', $path, $auditMatch)) {
    // Keep permission tests deterministic and independent of the optional local Redis extension.
    (new \ReflectionProperty(\App\Core\Cache::class, 'enabled'))->setValue(null, false);
    if ($path === '/api/logs/mcp') (new \App\Controllers\LogsController())->mcp(\App\Core\Request::capture());
    else (new \App\Controllers\LogsController())->mcpDetalhe(\App\Core\Request::capture(), $auditMatch[1]);
    return;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/health') { echo 'ready'; return; }
if ($path === '/expire-fixture') {
    foreach ($_SESSION['mcp_pending'] ?? [] as $nonce => $pending) $_SESSION['mcp_pending'][$nonce]['expires'] = time() - 1;
    echo 'expired'; return;
}
if ($path === '/fail-key-fixture') { file_put_contents($directory . '/fail-key', 'fixture'); echo 'failed'; return; }
if (is_file($directory . '/fail-key')) putenv('MCP_PRIVATE_KEY=file:///missing-mcp-fixture-key.pem');
if ($path === '/oauth/mcp/authorize') {
    $request = App\Core\Request::capture();
    if ((new App\Middleware\CsrfMiddleware())->handle($request)) {
        (new App\Controllers\Mcp\WebController())->authorize($request);
    }
    return;
}
http_response_code(404);
