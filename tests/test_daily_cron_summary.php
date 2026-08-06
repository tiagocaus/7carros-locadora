<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Helpers/helpers.php';

use App\Crons\DailyCronSummaryStore;
use App\Crons\Jobs\BaseJob;
use App\Crons\Jobs\RenovarContratosJob;
use App\Crons\Jobs\SendDailyCronSummaryJob;

function assertCronSummary(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$translationExpected = [
    'pt_BR' => 'Processados 372 tenants, 9803 veículos, 97 OS geradas',
    'pt_PT' => 'Processados 372 tenants, 9803 veículos, 97 OS geradas',
    'en_US' => 'Processed 372 tenants, 9803 vehicles, 97 WOs generated',
    'es_ES' => 'Procesados 372 tenants, 9803 vehículos, 97 OT generadas',
    'it_IT' => 'Elaborati 372 tenants, 9803 veicoli, 97 OdL generati',
];

foreach ($translationExpected as $locale => $expected) {
    $actual = t('modules.manutencao.cron.result', [
        'tenants' => 372,
        'veiculos' => 9803,
        'os' => 97,
    ], $locale);
    assertCronSummary($actual === $expected, "Placeholder de manutencao incorreto para {$locale}: {$actual}");
    assertCronSummary(!str_contains($actual, ':ve'), "Placeholder residual para {$locale}: {$actual}");
}

$store = new DailyCronSummaryStore(sys_get_temp_dir() . '/daily-cron-summary-test-' . getmypid());
$filterLogs = new ReflectionMethod($store, 'filterLogs');
$logs = [];

for ($i = 1; $i <= 77; $i++) {
    $logs[] = sprintf(
        '[2026-08-06 01:10:%02d] [ERROR] [RenovarContratos] Erro ao renovar contrato #C%04d: Autorenovacao bloqueada: valor do contrato zerado',
        $i % 60,
        $i
    );
}

$logs[] = '[2026-08-06 00:05:03] [WARNING] [CheckPreventiveMaintenance] Notificacao sms nao enfileirada: Envio por SMS desativado nas configuracoes desta empresa/filial (Empresa A)';
$logs[] = '[2026-08-06 00:05:04] [WARNING] [CheckPreventiveMaintenance] Notificacao sms nao enfileirada: Envio por SMS desativado nas configuracoes desta empresa/filial (Empresa B)';
$logs[] = '[2026-08-06 00:05:05] [WARNING] [CheckPreventiveMaintenance] Notificacao email nao enfileirada: Email do cliente invalido';

$groupedLogs = $filterLogs->invoke($store, $logs);
assertCronSummary(count($groupedLogs) === 3, 'Os logs deveriam formar tres grupos distintos.');
assertCronSummary($groupedLogs[0]['count'] === 77, 'Os 77 erros de renovacao nao foram agrupados.');
assertCronSummary($groupedLogs[0]['examples'] === ['#C0001', '#C0002', '#C0003'], 'Os exemplos de contratos devem ser limitados a tres.');
assertCronSummary($groupedLogs[1]['count'] === 2, 'Os avisos de SMS por empresa nao foram agrupados.');
assertCronSummary($groupedLogs[1]['examples'] === ['Empresa A', 'Empresa B'], 'As empresas de exemplo nao foram preservadas.');

$summary = [
    'date' => '2026-08-06',
    'jobs' => [],
];

foreach (DailyCronSummaryStore::EXPECTED_JOBS as $jobId => $meta) {
    $summary['jobs'][$jobId] = [
        'success' => true,
        'status' => BaseJob::STATUS_SUCCESS,
        'message' => 'Job concluido',
        'duration' => 1.0,
        'data' => [],
        'logs' => [],
    ];
}

// Simula um registro legado, anterior ao campo status.
$summary['jobs'][RenovarContratosJob::class] = [
    'success' => true,
    'message' => '132 contrato(s) renovado(s) em 139 tenant(s)',
    'duration' => 2.73,
    'data' => [
        'tenants_processados' => 139,
        'contratos_renovados' => 132,
        'erros' => array_fill(0, 77, ['erro' => 'valor zerado']),
    ],
    'logs' => [$groupedLogs[0]],
];

$job = new SendDailyCronSummaryJob();
$calculateStats = new ReflectionMethod($job, 'calculateStats');
$subjectPrefix = new ReflectionMethod($job, 'subjectPrefix');
$orderedJobs = new ReflectionMethod($job, 'orderedJobs');
$buildHtml = new ReflectionMethod($job, 'buildHtml');

$stats = $calculateStats->invoke($job, $summary);
assertCronSummary($stats['successful'] === 6, 'A quantidade de sucessos integrais esta incorreta.');
assertCronSummary($stats['partial'] === 1, 'O registro legado com erros deveria ser sucesso parcial.');
assertCronSummary($stats['failed'] === 0 && $stats['missing'] === 0, 'Nao deveria haver falhas nem jobs ausentes.');
assertCronSummary($subjectPrefix->invoke($job, $stats) === '[ATENCAO] ', 'Sucesso parcial deve sinalizar o assunto.');
assertCronSummary(
    $subjectPrefix->invoke($job, array_merge($stats, ['failed' => 1])) === '[ERRO] ',
    'Falhas devem ter precedencia sobre sucessos parciais no assunto.'
);

$ordered = $orderedJobs->invoke($job, $summary);
$renewal = array_values(array_filter($ordered, static fn (array $item): bool => $item['label'] === 'Renovacao de Contratos'))[0];
assertCronSummary($renewal['status'] === 'SUCESSO PARCIAL', 'O status visual da renovacao deveria ser SUCESSO PARCIAL.');

$html = $buildHtml->invoke($job, $summary, $stats);
assertCronSummary(!str_contains($html, 'display:flex'), 'O HTML do resumo nao deve usar flex nas linhas.');
assertCronSummary(str_contains($html, 'role="presentation"'), 'As metricas devem usar tabelas de apresentacao.');
assertCronSummary(str_contains($html, 'word-break:break-word'), 'Mensagens longas devem permitir quebra segura.');
assertCronSummary(str_contains($html, '77x Erro ao renovar contrato'), 'O HTML deve exibir a contagem do grupo de erros.');

$summary['jobs'][RenovarContratosJob::class]['message'] = '<script>unsafe()</script>';
$escapedHtml = $buildHtml->invoke($job, $summary, $stats);
assertCronSummary(!str_contains($escapedHtml, '<script>'), 'Mensagens do resumo devem ser escapadas no HTML.');
assertCronSummary(str_contains($escapedHtml, '&lt;script&gt;'), 'O HTML deve preservar de forma segura o texto escapado.');

echo "OK: resumo diario dos CRONs\n";
