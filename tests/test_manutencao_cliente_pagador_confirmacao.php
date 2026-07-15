<?php

require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Controllers\ManutencoesController;
use App\Core\Database;
use App\Services\AuditLogService;

function checkClientePagadorConfirmacao(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$controller = new ManutencoesController();
$reflection = new ReflectionClass($controller);

$viewSource = file_get_contents(APP_ROOT . '/app/Views/pages/manutencoes/adicionar.php');
checkClientePagadorConfirmacao($viewSource !== false, 'View de manutencao deve estar disponivel para validacao.');
checkClientePagadorConfirmacao(
    str_contains($viewSource, "<?= aviso(t('modules.manutencao.helpers.client_payer')) ?>"),
    'Instrucao do cliente pagador deve usar o helper aviso() no rotulo.'
);
checkClientePagadorConfirmacao(
    substr_count($viewSource, "modules.manutencao.helpers.client_payer") === 1,
    'Instrucao do cliente pagador deve existir somente no helper aviso(), sem texto duplicado abaixo do campo.'
);

$normalizar = $reflection->getMethod('normalizarClientePagador');
checkClientePagadorConfirmacao($normalizar->invoke($controller, '') === null, 'Cliente vazio deve ser normalizado como null.');
checkClientePagadorConfirmacao($normalizar->invoke($controller, '0') === null, 'Cliente zero deve ser normalizado como null.');
checkClientePagadorConfirmacao($normalizar->invoke($controller, '42') === 42, 'Cliente valido deve ser normalizado como inteiro.');

$adicionarAudit = $reflection->getMethod('adicionarConfirmacaoClientePagadorAudit');
$auditOriginal = json_encode([
    'Dados' => [
        ['label' => 'Status', 'de' => 'Criada', 'para' => 'Aberta'],
        ['label' => 'Cliente responsável pelo pagamento', 'de' => 'Cliente A', 'para' => 'Cliente B'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$auditSemFinanceiro = json_decode($adicionarAudit->invoke($controller, $auditOriginal, false), true);
checkClientePagadorConfirmacao(count($auditSemFinanceiro['Dados'] ?? []) === 3, 'Confirmacao deve ser adicionada sem remover campos alterados.');
checkClientePagadorConfirmacao(
    str_contains((string) ($auditSemFinanceiro['Dados'][2]['para'] ?? ''), 'confirmou'),
    'Auditoria deve registrar que o usuario confirmou o aviso.'
);

$auditComFinanceiro = json_decode($adicionarAudit->invoke($controller, $auditOriginal, true), true);
checkClientePagadorConfirmacao(
    str_contains((string) ($auditComFinanceiro['Dados'][2]['para'] ?? ''), 'não serão alterados'),
    'Auditoria deve registrar que financeiros existentes nao serao alterados.'
);

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

$_SESSION['chave'] = '1111111111111';
$_SESSION['user_id'] = Database::fetchColumn(
    'SELECT id FROM funcionarios WHERE chave = ? ORDER BY id LIMIT 1',
    [$_SESSION['chave']]
) ?: null;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$logId = 0;
try {
    $logId = AuditLogService::registrarComAuditFrontend(
        'Teste automatizado de confirmacao do cliente pagador',
        null,
        json_encode($auditComFinanceiro, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    checkClientePagadorConfirmacao($logId > 0, 'Confirmacao deve gerar registro de auditoria.');

    $log = Database::fetchOne(
        'SELECT campos_alterados FROM logs WHERE id = ? AND chave = ?',
        [$logId, $_SESSION['chave']]
    );
    $camposLog = json_decode((string) ($log['campos_alterados'] ?? ''), true);
    checkClientePagadorConfirmacao(
        count($camposLog['Dados'] ?? []) === 3,
        'Log deve manter campos alterados e confirmacao no mesmo registro.'
    );
} finally {
    if ($logId > 0) {
        Database::execute('DELETE FROM logs WHERE id = ? AND chave = ?', [$logId, $_SESSION['chave']]);
    }
}

foreach (['pt_BR', 'pt_PT', 'en_US', 'es_ES', 'it_IT'] as $locale) {
    $translations = require APP_ROOT . "/app/Lang/{$locale}/modules/manutencao.php";
    checkClientePagadorConfirmacao(!empty($translations['fields']['client']), "Traducao do campo ausente em {$locale}.");
    checkClientePagadorConfirmacao(!empty($translations['helpers']['client_payer']), "Helper do pagador ausente em {$locale}.");
    checkClientePagadorConfirmacao(!empty($translations['messages']['payer_confirm_title']), "Titulo do modal ausente em {$locale}.");
    checkClientePagadorConfirmacao(!empty($translations['audit_payer']['confirmation_label']), "Auditoria do pagador ausente em {$locale}.");
}

echo "OK: confirmacao e auditoria do cliente pagador validadas.\n";
