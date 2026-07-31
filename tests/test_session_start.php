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

echo "OK: sessao recupera cookie invalido, limita falha, responde genericamente e renova fingerprint.\n";
