<?php

/**
 * Smoke test do portal. Nao envia mensagens e usa somente o tenant de testes.
 *
 * Execute: php tests/test_portal_cliente_investidor.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Core\Database;
use App\Helpers\DateHelper;
use App\Models\PortalRepository;
use App\Models\PortalSession;

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

$chave = '1111111111111';
$_SESSION['chave'] = $chave;
DateHelper::clearCache();
$fails = 0;

function portalCheck(bool $condition, string $message): void
{
    global $fails;
    echo ($condition ? "✓ " : "✗ ") . $message . PHP_EOL;
    if (!$condition) {
        $fails++;
    }
}

$tables = [
    'portal_sessions',
    'fornecedor_password_resets',
    'portal_audit_logs',
    'portal_indicacao_codigos',
    'portal_indicacao_eventos',
];
foreach ($tables as $table) {
    $exists = Database::fetchAll(
        'SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        [$table]
    );
    portalCheck((int) ($exists[0]['total'] ?? 0) === 1, "Tabela {$table} disponivel");
}

$clientes = Database::fetchAll(
    "SELECT id FROM clientes WHERE chave = ? AND (situacao IS NULL OR situacao = 'A') LIMIT 1",
    [$chave]
);
if ($clientes) {
    $idCliente = (int) $clientes[0]['id'];
    $repo = new PortalRepository();
    portalCheck($repo->perfil('cliente', $idCliente) !== null, 'Perfil do cliente isolado pelo tenant');
    portalCheck(isset($repo->dashboardCliente($idCliente)['totais']), 'Dashboard do cliente');
    foreach (['contratos', 'locacoes', 'faturas', 'multas', 'manutencoes', 'veiculos'] as $recurso) {
        $resultado = $repo->listarCliente($recurso, $idCliente, 1, 5);
        portalCheck(isset($resultado['data'], $resultado['pagination']), "Listagem de {$recurso}");
    }

    $sessionModel = new PortalSession();
    $token = $sessionModel->criar($chave, 'cliente', $idCliente, '127.0.0.1', 'portal-smoke-test');
    portalCheck(strlen($token) === 64, 'Token opaco com 256 bits');
    portalCheck(
        $sessionModel->validar($chave, $token, 'portal-smoke-test', false) !== null,
        'Sessao valida com user-agent correto'
    );
    portalCheck(
        $sessionModel->validar($chave, $token, 'outro-user-agent', false) === null,
        'Sessao rejeita troca de user-agent'
    );
    Database::query(
        'DELETE FROM portal_sessions WHERE chave = ? AND token_hash = ?',
        [$chave, hash('sha256', $token)]
    );
} else {
    echo "– Tenant de testes sem cliente; consultas de cliente ignoradas." . PHP_EOL;
}

$investidores = Database::fetchAll(
    'SELECT id FROM fornecedores WHERE chave = ? AND investidor = 1 LIMIT 1',
    [$chave]
);
if ($investidores) {
    $idInvestidor = (int) $investidores[0]['id'];
    $repo = new PortalRepository();
    portalCheck($repo->perfil('investidor', $idInvestidor) !== null, 'Perfil do investidor');
    portalCheck(
        isset($repo->dashboardInvestidor($idInvestidor, date('Y-m-d', strtotime('-1 year')), date('Y-m-d'))['totais']),
        'Dashboard do investidor'
    );
    foreach (['veiculos', 'manutencoes', 'comissoes', 'operacoes'] as $recurso) {
        $resultado = $repo->listarInvestidor(
            $recurso,
            $idInvestidor,
            1,
            5,
            date('Y-m-d', strtotime('-1 year')),
            date('Y-m-d')
        );
        portalCheck(isset($resultado['data'], $resultado['pagination']), "Investidor: {$recurso}");
    }
} else {
    echo "– Tenant de testes sem investidor; consultas do investidor ignoradas." . PHP_EOL;
}

portalCheck(
    is_file(APP_ROOT . '/storage/templates/website/assets/js/portal.min.js')
    && is_file(APP_ROOT . '/storage/templates/website/assets/css/portal.min.css'),
    'Assets de producao minificados'
);
portalCheck(
    json_decode(file_get_contents(APP_ROOT . '/storage/templates/website/versao.json'), true)['versao'] === '1.3.0',
    'Template do website na versao 1.3.0'
);

exit($fails > 0 ? 1 : 0);
