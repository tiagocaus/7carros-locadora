#!/usr/bin/env php
<?php

/**
 * Regressao: falhas de session_start() nao podem causar recursao entre
 * Session::start() e Session::has().
 *
 * Execute: php tests/test_session_start.php
 */

$autoload = realpath(__DIR__ . '/../vendor/autoload.php');
if ($autoload === false) {
    throw new RuntimeException('Autoloader nao encontrado.');
}

function runSessionScenario(string $autoload, string $scenario): string
{
    $code = 'require ' . var_export($autoload, true) . ';' . $scenario;
    $command = escapeshellarg(PHP_BINARY)
        . ' -d display_errors=0 -d log_errors=0 -r '
        . escapeshellarg($code);

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        throw new RuntimeException('Cenario de sessao falhou: ' . implode("\n", $output));
    }

    return implode("\n", $output);
}

$invalidCookie = runSessionScenario($autoload, <<<'PHP'
$_SERVER['HTTP_USER_AGENT'] = 'SessionTest/1.0';
ini_set('session.save_path', sys_get_temp_dir());
session_name('SESSION_TEST_INVALID');
$_COOKIE[session_name()] = 'id!invalido';
\App\Core\Session::start();
if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['_session_initiated'])) {
    exit(2);
}
echo 'RECOVERED';
PHP);

if ($invalidCookie !== 'RECOVERED') {
    throw new RuntimeException('Cookie invalido nao foi recuperado.');
}

$persistentFailure = runSessionScenario($autoload, <<<'PHP'
ini_set('session.save_path', '/diretorio/inexistente/para/session-test');
try {
    \App\Core\Session::start();
    exit(3);
} catch (\RuntimeException $e) {
    echo 'CONTROLLED';
}
PHP);

if ($persistentFailure !== 'CONTROLLED') {
    throw new RuntimeException('Falha persistente de sessao nao foi controlada.');
}

$frontController = realpath(__DIR__ . '/../public/index.php');
if ($frontController === false) {
    throw new RuntimeException('Front controller nao encontrado.');
}
$frontControllerFailure = runSessionScenario($autoload, sprintf(
    "ini_set('session.save_path', '/diretorio/inexistente/para/session-front-test'); require %s;",
    var_export($frontController, true)
));
if ($frontControllerFailure !== 'Servico temporariamente indisponivel.') {
    throw new RuntimeException('Front controller nao retornou resposta generica para falha de sessao.');
}

$fingerprint = runSessionScenario($autoload, <<<'PHP'
$_SERVER['HTTP_USER_AGENT'] = 'SessionTest/Original';
ini_set('session.save_path', sys_get_temp_dir());
session_name('SESSION_TEST_FINGERPRINT');
\App\Core\Session::start();
$oldId = session_id();
session_write_close();
session_id($oldId);
$_SERVER['HTTP_USER_AGENT'] = 'SessionTest/Changed';
\App\Core\Session::start();
if (session_id() === $oldId || ($_SESSION['_user_agent'] ?? '') !== 'SessionTest/Changed') {
    exit(4);
}
echo 'RESET';
PHP);

if ($fingerprint !== 'RESET') {
    throw new RuntimeException('Fingerprint divergente nao reiniciou a sessao.');
}

$activeSession = runSessionScenario($autoload, <<<'PHP'
$_SERVER['HTTP_USER_AGENT'] = 'SessionTest/Active';
ini_set('session.save_path', sys_get_temp_dir());
session_name('SESSION_TEST_ACTIVE');
\App\Core\Session::start();
$_SESSION['authenticated'] = true;
$_SESSION['user_id'] = 123;
$_SESSION['_last_activity_at'] = time() - \App\Core\Session::INACTIVITY_TIMEOUT + 60;
$oldId = session_id();
session_write_close();
session_id($oldId);
\App\Core\Session::start();
if (($_SESSION['authenticated'] ?? false) !== true || session_id() !== $oldId) {
    exit(5);
}
if ((int) ($_SESSION['_last_activity_at'] ?? 0) < time() - 2) {
    exit(6);
}
if ((int) session_get_cookie_params()['lifetime'] !== 0) {
    exit(7);
}
echo 'ACTIVE';
PHP);

if ($activeSession !== 'ACTIVE') {
    throw new RuntimeException('Sessao com atividade recente foi encerrada indevidamente.');
}

$expiredSession = runSessionScenario($autoload, <<<'PHP'
$_SERVER['HTTP_USER_AGENT'] = 'SessionTest/Expired';
$_SERVER['REQUEST_URI'] = '/clientes/salvar?diagnostico=nao-logar';
ini_set('session.save_path', sys_get_temp_dir());
session_name('SESSION_TEST_EXPIRED');
\App\Core\Session::start();
$_SESSION['authenticated'] = true;
$_SESSION['user_id'] = 456;
$_SESSION['chave'] = 'tenant-de-teste';
$_SESSION['_last_activity_at'] = time() - \App\Core\Session::INACTIVITY_TIMEOUT - 1;
$oldId = session_id();
session_write_close();
session_id($oldId);
\App\Core\Session::start();
if (($_SESSION['authenticated'] ?? false) === true || session_id() === $oldId) {
    exit(8);
}
if (\App\Core\Session::invalidationReason() !== 'inactivity') {
    exit(9);
}
echo 'EXPIRED';
PHP);

if ($expiredSession !== 'EXPIRED') {
    throw new RuntimeException('Sessao inativa nao foi encerrada corretamente.');
}

$legacyCookie = runSessionScenario($autoload, <<<'PHP'
$_SERVER['HTTP_USER_AGENT'] = 'SessionTest/LegacyCookie';
ini_set('session.save_path', sys_get_temp_dir());
session_name('SESSION_TEST_LEGACY_COOKIE');
\App\Core\Session::start();
unset($_SESSION['_cookie_policy_version']);
$oldId = session_id();
session_write_close();
session_id($oldId);
\App\Core\Session::start();
if ((int) ($_SESSION['_cookie_policy_version'] ?? 0) < 2) {
    exit(10);
}
if ((int) session_get_cookie_params()['lifetime'] !== 0) {
    exit(11);
}
echo 'MIGRATED';
PHP);

if ($legacyCookie !== 'MIGRATED') {
    throw new RuntimeException('Cookie legado nao foi migrado para a nova politica.');
}

$expiredAjax = runSessionScenario($autoload, <<<'PHP'
$_SERVER['HTTP_USER_AGENT'] = 'SessionTest/AjaxExpired';
$_SERVER['REQUEST_URI'] = '/clientes/salvar';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
ini_set('session.save_path', sys_get_temp_dir());
session_name('SESSION_TEST_AJAX_EXPIRED');
\App\Core\Session::start();
$_SESSION['authenticated'] = true;
$_SESSION['_last_activity_at'] = time() - \App\Core\Session::INACTIVITY_TIMEOUT - 1;
$oldId = session_id();
session_write_close();
session_id($oldId);
\App\Core\Session::start();
$request = \App\Core\Request::capture();
(new \App\Middleware\AuthMiddleware())->handle($request);
PHP);

$expiredAjaxData = json_decode($expiredAjax, true);
if (
    !is_array($expiredAjaxData)
    || ($expiredAjaxData['session_expired'] ?? false) !== true
    || ($expiredAjaxData['session_reason'] ?? null) !== 'inactivity'
    || ($expiredAjaxData['redirect'] ?? null) !== '/login'
) {
    throw new RuntimeException('Resposta AJAX expirada nao possui diagnostico de sessao.');
}

echo "OK: sessao usa inatividade real, migra cookie legado, diagnostica AJAX e preserva protecoes existentes.\n";
