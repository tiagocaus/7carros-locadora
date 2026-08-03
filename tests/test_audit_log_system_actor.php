<?php

require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Core\Database;
use App\Services\AuditLogService;

function assertAuditSystemActor(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

$_SESSION['chave'] = '1111111111111';
unset($_SESSION['user_id']);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$prefix = 'Teste ator Sistema ' . bin2hex(random_bytes(6));
$ids = [];

try {
    $ids[] = AuditLogService::registrar($prefix . ' simples');
    $ids[] = AuditLogService::registrarComCampos($prefix . ' campos', [
        AuditLogService::campo('Origem', null, 'Webhook', 'Sistema'),
    ]);
    $ids[] = AuditLogService::registrarComAuditFrontend(
        $prefix . ' frontend',
        json_encode([['label' => 'Origem', 'para' => 'Sistema']], JSON_UNESCAPED_UNICODE),
        null
    );

    assertAuditSystemActor(count(array_filter($ids)) === 3, 'Os tres formatos de auditoria devem ser gravados.');

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $rows = Database::fetchAll(
        "SELECT id, id_funcionario FROM logs WHERE id IN ({$placeholders}) ORDER BY id",
        $ids
    );

    assertAuditSystemActor(count($rows) === 3, 'Os registros de auditoria de teste nao foram encontrados.');
    foreach ($rows as $row) {
        assertAuditSystemActor((int) $row['id_funcionario'] === 0, 'Processo sem usuario deve usar o ator Sistema (0).');
    }

    echo "OK: auditorias sem funcionario usam o ator Sistema.\n";
} finally {
    if ($ids !== []) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        Database::execute("DELETE FROM logs WHERE id IN ({$placeholders})", $ids);
    }
}
