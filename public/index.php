<?php

/**
 * 7Carros Locadora - Entry Point
 *
 * Front Controller que recebe todas as requisições HTTP
 */

// Define o caminho raiz da aplicação
define('APP_ROOT', dirname(__DIR__));

// Carrega o autoloader do Composer
require APP_ROOT . '/vendor/autoload.php';

// Carrega as funções helper globais
require APP_ROOT . '/app/Helpers/helpers.php';

use App\Core\Router;
use App\Core\Request;
use App\Core\Session;
use App\Core\Response;
use App\Core\Database;

// Padroniza o relogio da aplicacao antes de sessoes, logs e regras de bloqueio.
date_default_timezone_set(Database::env('APP_TIMEZONE', 'America/Sao_Paulo'));

// Inicia a sessão
try {
    Session::start();
} catch (\Throwable $e) {
    error_log('[Session] Falha controlada ao iniciar sessao: ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Servico temporariamente indisponivel.';
    exit;
}

// Captura a requisição
$request = Request::capture();

// Cria e configura o router
$router = new Router();

// Registra os middlewares
$router->middleware('auth', \App\Middleware\AuthMiddleware::class);
$router->middleware('web_system_access', \App\Middleware\WebSystemAccessMiddleware::class);
$router->middleware('guest', \App\Middleware\GuestMiddleware::class);
$router->middleware('csrf', \App\Middleware\CsrfMiddleware::class);
$router->middleware('api_csrf', \App\Middleware\ApiCsrfMiddleware::class);
$router->middleware('permission', \App\Middleware\PermissionMiddleware::class);

// Middlewares de segurança anti-scraping
$router->middleware('blocked_ip', \App\Middleware\BlockedIpMiddleware::class);
$router->middleware('rate_limit', \App\Middleware\RateLimitMiddleware::class);
$router->middleware('throttle', \App\Middleware\ThrottlingMiddleware::class);
$router->middleware('honeypot', \App\Middleware\HoneypotMiddleware::class);
$router->middleware('whmcs_auth', \App\Middleware\WhmcsAuthMiddleware::class);
$router->middleware('n8n_auth', \App\Middleware\N8nAuthMiddleware::class);

// Verificações de segurança globais (executadas antes das rotas)
// 1. Verifica se IP está bloqueado
$blockedIpMiddleware = new \App\Middleware\BlockedIpMiddleware();
if (!$blockedIpMiddleware->handle($request)) {
    exit;
}

// 2. Verifica acesso a honeypots
$honeypotMiddleware = new \App\Middleware\HoneypotMiddleware();
if (!$honeypotMiddleware->handle($request)) {
    exit;
}

// Carrega as rotas
require APP_ROOT . '/app/Routes/web.php';

// Trata erros globais em produção
if (Database::env('APP_ENV') === 'production') {
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        // Respeita o operador @ e o nivel atual de error_reporting.
        // Sem esta verificacao, warnings intencionalmente suprimidos viram HTML 500.
        if (!(error_reporting() & $errno)) {
            return false;
        }
        error_log("Error [$errno]: $errstr in $errfile on line $errline");
        Response::serverError('Ocorreu um erro. Tente novamente mais tarde.');
    });

    set_exception_handler(function($exception) {
        error_log("Exception: " . $exception->getMessage());
        Response::serverError('Ocorreu um erro. Tente novamente mais tarde.');
    });
}

// Despacha a requisição para a rota correspondente
try {
    $router->dispatch($request);
} catch (\Exception $e) {
    // Log do erro
    error_log($e->getMessage());

    // Em desenvolvimento, mostra detalhes do erro
    if (Database::env('APP_ENV', 'production') === 'development') {
        echo "<h1>Erro</h1>";
        echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>Arquivo:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
        echo "<p><strong>Linha:</strong> " . $e->getLine() . "</p>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    } else {
        // Em produção, mostra mensagem genérica
        Response::serverError();
    }
}
