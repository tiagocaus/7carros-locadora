<?php

/**
 * Teste estrutural do fluxo de correcao do historico de odometro.
 *
 * Execute: php tests/test_contrato_odometro_edicao.php
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

$checks = 0;
$failures = 0;

function checkContratoOdometro(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures++;
    }
    echo '   ' . ($condition ? 'PASS' : 'FAIL') . " {$message}\n";
}

$model = file_get_contents(APP_ROOT . '/app/Models/ContratoOdometro.php');
$controller = file_get_contents(APP_ROOT . '/app/Controllers/ContratosController.php');
$routes = file_get_contents(APP_ROOT . '/app/Routes/web.php');
$view = file_get_contents(APP_ROOT . '/app/Views/pages/contratos/offcanvas-odometro.php');
$docs = file_get_contents(APP_ROOT . '/docs/contratos.md');
$migration = file_get_contents(APP_ROOT . '/app/Database/migrations/00407_allow_multiple_contract_odometer_readings_per_day.php');
$ptBr = file_get_contents(APP_ROOT . '/app/Lang/pt_BR/modules/contratos.php');

echo "=== Teste correcao de odometro do contrato ===\n";

checkContratoOdometro(
    str_contains($routes, "put('/api/contratos/{id}/odometros/{leituraId}'")
        && str_contains($routes, "'permission:contratos.editar', 'api_csrf', 'rate_limit', 'throttle'"),
    'rota PUT exige permissao e middlewares de API'
);

checkContratoOdometro(
    str_contains($controller, "FilialHelper::temAcessoFilial")
        && str_contains($controller, "\$contrato['status'] ?? '') !== 'A'")
        && str_contains($controller, "\$data > \$hoje"),
    'controller valida filial, contrato ativo e intervalo da data'
);

checkContratoOdometro(
    !str_contains($model, "'error' => 'duplicate_date'")
        && !str_contains($model, '$existente = $this->buscarPorData')
        && str_contains($model, "'error' => (int) \$item['id'] === \$id ? 'lower_than_previous' : 'higher_than_next'")
        && str_contains($model, "'diferenca' => \$diferenca"),
    'model sempre insere e protege sequencia com recalculo de diferencas'
);

checkContratoOdometro(
    str_contains($migration, "dropIndexIfExists(self::TABLE, self::UNIQUE_INDEX)")
        && str_contains($migration, "['chave', 'id_contrato_veiculo', 'data', 'id']")
        && str_contains($migration, "['id_contrato', 'id_contrato_veiculo', 'data', 'id']")
        && str_contains($migration, 'Rollback bloqueado'),
    'migration libera varias leituras diarias e protege rollback com duplicidades'
);

checkContratoOdometro(
    str_contains($model, '$odometroVeiculo === $odometroUltimoOriginal')
        && str_contains($model, 'max($odometroVeiculo, $odometroUltimoNovo)'),
    'sincronizacao do veiculo preserva leitura externa mais recente'
);

checkContratoOdometro(
    str_contains($controller, 'AuditLogService::registrarComCampos')
        && str_contains($controller, "AuditLogService::campo('Odometro'")
        && str_contains($controller, "AuditLogService::campo('Observacao'"),
    'correcao registra auditoria dos campos alterados'
);

checkContratoOdometro(
    str_contains($view, 'class="odometer-history')
        && str_contains($view, 'API.put(`/api/contratos/${contratoId}/odometros/${updateButton.dataset.readingId}`')
        && str_contains($view, 'DateHelper.formatDateTime(item.created_at)')
        && str_contains($view, "action: 'openAlert'")
        && !preg_match('/\balert\s*\(/', $view),
    'offcanvas possui edicao inline e usa openAlert'
);

checkContratoOdometro(
    str_contains($ptBr, "'save_reading' => 'Registrar leitura'")
        && str_contains($view, 'DateHelper.formatDateTime(item.created_at)')
        && str_contains($controller, "'created_at' => \$registro['created_at']"),
    'interface usa Registrar leitura e diferencia registros pelo horario'
);

checkContratoOdometro(
    str_contains($view, 'odometer-history-registered-at')
        && str_contains($view, "item.obs.trim() ?")
        && !str_contains($view, "escapeText(item.obs || '—')")
        && !str_contains($view, '.odometer-history-km { grid-column: 1; grid-row: 2;')
        && str_contains($view, 'grid-template-columns: 88px 62px minmax(0, 1fr) 34px'),
    'historico mantem dados em uma linha, exibe horario ao final e omite observacao vazia'
);

checkContratoOdometro(
    str_contains($view, "aviso(t('modules.contratos.quick_odometer.minimum_hint'))")
        && !str_contains($view, 'odometro-minimo-label</span></p>'),
    'orientacao do input fica associada ao rotulo com aviso()'
);

checkContratoOdometro(
    str_contains($docs, 'as 5 leituras mais recentes')
        && str_contains($docs, 'inclusive quando ja existe outra leitura')
        && str_contains($docs, 'todas as diferencas cronologicas'),
    'documentacao descreve historico e regras da correcao'
);

echo "\nResultado: " . ($checks - $failures) . "/{$checks} verificacoes passaram.\n";
exit($failures === 0 ? 0 : 1);
