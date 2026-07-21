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
$queryBuilder = file_get_contents(APP_ROOT . '/app/Classes/QueryBuilder.php');
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
        && str_contains($controller, "AuditLogService::campo('Observacao'")
        && str_contains($controller, "\$campo['de'] !== \$campo['para']")
        && str_contains($controller, "if (\$resultado['alterado'] ?? true)"),
    'correcao registra somente campos efetivamente alterados'
);

checkContratoOdometro(
    str_contains($queryBuilder, 'public function lockForUpdate(): self')
        && str_contains($queryBuilder, "\$sql .= ' FOR UPDATE'")
        && str_contains($queryBuilder, '$this->lockForUpdate = false;')
        && str_contains($model, '->lockForUpdate()')
        && str_contains($model, 'listarPorContratoVeiculo(')
        && str_contains($model, 'bool $bloquearParaAtualizacao = false')
        && str_contains($model, "'alterado' => false")
        && str_contains($model, "'alterado' => true"),
    'edicao bloqueia a leitura e ignora reenvio sem mudanca'
);

require_once APP_ROOT . '/app/Classes/QueryBuilder.php';
$_SESSION['chave'] = '1111111111111';
$queryBuilderReflection = new ReflectionClass(\App\Classes\QueryBuilder::class);
/** @var \App\Classes\QueryBuilder $queryBuilderSemConexao */
$queryBuilderSemConexao = $queryBuilderReflection->newInstanceWithoutConstructor();
$sqlComBloqueio = $queryBuilderSemConexao
    ->table('contratos_odometros')
    ->where('id_contrato_veiculo', '=', 123)
    ->orderBy('data')
    ->orderBy('id')
    ->lockForUpdate()
    ->toSql();
$sqlAposReset = $queryBuilderSemConexao
    ->table('contratos_odometros')
    ->where('id', '=', 456)
    ->toSql();
unset($_SESSION['chave']);

checkContratoOdometro(
    str_ends_with($sqlComBloqueio, 'ORDER BY data ASC, id ASC FOR UPDATE')
        && !str_contains($sqlAposReset, 'FOR UPDATE'),
    'QueryBuilder gera FOR UPDATE no fim do SELECT e limpa o bloqueio na query seguinte'
);

checkContratoOdometro(
    str_contains($view, 'class="odometer-history')
        && str_contains($view, 'API.post(`/api/contratos/${contratoId}/odometros/${updateButton.dataset.readingId}`')
        && str_contains($view, "_method: 'PUT'")
        && str_contains($view, 'DateHelper.formatDateTime(item.created_at)')
        && str_contains($view, "action: 'openAlert'")
        && !preg_match('/\balert\s*\(/', $view),
    'offcanvas possui edicao inline, method spoofing e usa openAlert'
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
        && str_contains($view, 'grid-template-columns: 88px 72px minmax(0, 1fr) 34px'),
    'historico mantem dados em uma linha, exibe horario ao final e omite observacao vazia'
);

checkContratoOdometro(
    str_contains($view, "const odometerInput = form?.querySelector('.odometro-input')")
        && str_contains($view, "const observationInput = form?.querySelector('.odometro-obs')")
        && str_contains($view, 'if (!card || !form || !odometerInput || !observationInput)')
        && !str_contains($view, "card.querySelector('.odometro-input').value")
        && !str_contains($view, "card.querySelector('.odometro-obs').value"),
    'salvamento valida e reutiliza os campos do formulario sem acessar null.value'
);

checkContratoOdometro(
    str_contains($view, "const row = updateButton.closest('.odometer-history-row')")
        && !str_contains($view, "const row = updateButton.closest('[data-reading-id]')")
        && str_contains($view, "const dateInput = row?.querySelector('.edit-reading-date')")
        && str_contains($view, "const odometerInput = row?.querySelector('.edit-reading-km')")
        && str_contains($view, "const observationInput = row?.querySelector('.edit-reading-obs')")
        && str_contains($view, 'if (!card || !row || !dateInput || !odometerInput)')
        && str_contains($view, "const obs = observationInput?.value || ''")
        && !preg_match('/querySelector\([^\n]+\)\.value/', $view),
    'edicao usa a linha do historico, valida os campos e aceita observacao ausente'
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
