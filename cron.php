#!/usr/bin/env php
<?php

/**
 * CRON Entry Point
 *
 * Execute scheduled jobs via command line
 *
 * Usage:
 *   php cron.php              # Executa jobs agendados para o momento atual
 *   php cron.php --list       # Lista todos os jobs e suas frequências
 *   php cron.php --force      # Força execução de todos os jobs (ignora schedule)
 *
 * Crontab Configuration (runs every minute):
 *   * * * * * /usr/bin/php /path/to/project/cron.php >> /path/to/project/storage/logs/cron/execution.log 2>&1
 * 
 * TESTE: Rodar em loop a cada 1 minuto (simula crontab real):
 *   while true; do clear; php cron.php; sleep 10; done
 */

// Carrega Composer autoloader (se existir)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Autoloader PSR-4 (classes do projeto)
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Carrega helpers
require_once __DIR__ . '/app/Helpers/helpers.php';

// Carrega variáveis de ambiente (.env)
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, '"\'');
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
}

// Load configuration (opcional)
if (file_exists(__DIR__ . '/config/app.php')) {
    require_once __DIR__ . '/config/app.php';
}
if (file_exists(__DIR__ . '/config/database.php')) {
    require_once __DIR__ . '/config/database.php';
}

// Ensure we're running from CLI
if (php_sapi_name() !== 'cli') {
    echo "This script can only be run from the command line.\n";
    exit(1);
}

// Display startup message
echo "======================================\n";
echo "  CRON Job Runner\n";
echo "  Started: " . date('Y-m-d H:i:s') . "\n";
echo "======================================\n\n";

// Processa argumentos da linha de comando
$options = getopt('', ['list', 'force', 'help']);

try {
    // Create Scheduler
    $scheduler = new \App\Crons\Scheduler();

    // ========================================
    // Registrar Jobs com suas Frequências
    // ========================================

    // Fila de Mensagens: Email, SMS, WhatsApp
    // Executa a cada 1 minuto para processar mensagens pendentes
    $scheduler->job(new \App\Crons\Jobs\ProcessMessageQueueJob())
              ->everyMinute();

    // Sincronizacao de Status WhatsApp
    // Executa a cada 5 minutos para verificar estado real das conexoes na Evolution API
    $scheduler->job(new \App\Crons\Jobs\SyncWhatsappStatusJob())
              ->everyFiveMinutes();

    // Manutenção Preventiva
    // Executa diariamente às 00:00 para verificar veículos próximos da manutenção
    $scheduler->job(new \App\Crons\Jobs\CheckPreventiveMaintenanceJob())
              ->dailyAt('00:00');

    // Comissoes Mensais de Investidores
    // Executa no dia 1 de cada mes às 06:00 para gerar comissoes mensais
    $scheduler->job(new \App\Crons\Jobs\GerarComissoesMensaisJob())
              ->monthlyOn(1, '06:00');

    // Limpeza de Gravacoes de Tela
    // Executa diariamente as 03:00 para remover gravacoes com mais de 30 dias
    $scheduler->job(new \App\Crons\Jobs\CleanupOldRecordingsJob())
              ->dailyAt('03:00');

    // Renovacao Automatica de Contratos
    // Executa diariamente as 06:00 para renovar contratos com auto_renovacao ativa
    $scheduler->job(new \App\Crons\Jobs\RenovarContratosJob())
              ->dailyAt('06:00');

    // Encargos de Veiculos - Geracao Financeira
    // Executa diariamente as 06:00 para gerar lancamentos financeiros de encargos proximos ao vencimento
    // e renovar encargos recorrentes vencidos
    $scheduler->job(new \App\Crons\Jobs\GerarEncargosFinanceiroJob())
              ->dailyAt('06:00');

    // Auto-consulta online de infracoes
    // Executa diariamente as 07:00 para consultar infracoes de tenants com auto-consulta ativa
    $scheduler->job(new \App\Crons\Jobs\SerproAutoConsultaJob())
              ->dailyAt('07:00');

    // Indicacoes de Condutor - Sincronizacao de Status
    // Executa a cada 30 minutos para atualizar status de indicacoes enviadas/processando/pendentes
    $scheduler->job(new \App\Crons\Jobs\SerproSyncIndicacoesStatusJob())
              ->everyThirtyMinutes();

    // NFS-e - Emissao Automatica
    // Executa a cada 5 minutos para emitir NFS-e de pagamentos confirmados (max 50/exec)
    $scheduler->job(new \App\Crons\Jobs\NFSeEmitirAutoJob())
              ->everyFiveMinutes();

    // NFS-e - Reenvio de Rejeitadas
    // Executa a cada 5 minutos para reenviar NFS-e rejeitadas com erro recuperavel (max 20/exec, max 3 tentativas)
    $scheduler->job(new \App\Crons\Jobs\NFSeReenviarJob())
              ->everyFiveMinutes();

    // NFS-e - Envio de Email
    // Executa a cada 5 minutos para enviar PDF de NFS-e autorizada por email (max 30/exec)
    $scheduler->job(new \App\Crons\Jobs\NFSeEnviarEmailJob())
              ->everyFiveMinutes();

    // Rotacao de Authorization Holds (Bloqueio)
    // Executa diariamente as 06:30 - rotaciona holds que expiram em 2 dias e marca expirados
    $scheduler->job(new \App\Crons\Jobs\RotateAuthorizationHoldsJob())
              ->dailyAt('06:30');

    // ========================================
    // Exemplos de outros jobs (descomentar quando necessário)
    // ========================================
    // $scheduler->job(new \App\Crons\Jobs\CalculateOverdueFeesJob())
    //           ->hourly();
    //
    // $scheduler->job(new \App\Crons\Jobs\SendBirthdayEmailsJob())
    //           ->dailyAt('08:00');
    //
    // $scheduler->job(new \App\Crons\Jobs\CleanupOldLogsJob())
    //           ->weeklyOn(0, '03:00');  // Domingo às 03:00
    //
    // $scheduler->job(new \App\Crons\Jobs\GenerateReportsJob())
    //           ->monthlyOn(1, '06:00'); // Dia 1 às 06:00

    // ========================================
    // Processar Comandos
    // ========================================

    // --help: Exibe ajuda
    if (isset($options['help'])) {
        echo "Uso: php cron.php [opções]\n\n";
        echo "Opções:\n";
        echo "  --list    Lista todos os jobs e suas frequências\n";
        echo "  --force   Força execução de todos os jobs (ignora schedule)\n";
        echo "  --help    Exibe esta ajuda\n\n";
        exit(0);
    }

    // --list: Lista jobs agendados
    if (isset($options['list'])) {
        $scheduler->printList();
        exit(0);
    }

    // --force: Força execução de todos (usa CronRunner legado)
    if (isset($options['force'])) {
        echo "MODO FORÇADO: Executando todos os jobs independente do schedule\n\n";
        $runner = new \App\Crons\CronRunner();
        foreach ($scheduler->getScheduledJobs() as $scheduledJob) {
            $runner->registerJob($scheduledJob->getJob());
        }
        $summary = $runner->run();

        echo "\n";
        echo "======================================\n";
        echo "  Execution Summary (Forced)\n";
        echo "======================================\n";
        echo "Total Jobs: {$summary['total_jobs']}\n";
        echo "Successful: {$summary['successful']}\n";
        echo "Failed: {$summary['failed']}\n";
        echo "Duration: {$summary['duration']}s\n";
        echo "======================================\n\n";

        exit($runner->isSuccessful() ? 0 : 1);
    }

    // Execução normal: apenas jobs agendados para agora
    $summary = $scheduler->run();

    // Display summary
    echo "\n";
    echo "======================================\n";
    echo "  Execution Summary\n";
    echo "======================================\n";
    echo "Total Scheduled: {$summary['total_scheduled']}\n";
    echo "Executed: {$summary['executed']}\n";
    echo "Skipped: {$summary['skipped']}\n";
    echo "Successful: {$summary['successful']}\n";
    echo "Failed: {$summary['failed']}\n";
    echo "Duration: {$summary['duration']}s\n";
    echo "Timestamp: {$summary['timestamp']}\n";
    echo "======================================\n\n";

    // Exit with appropriate code
    exit($scheduler->isSuccessful() ? 0 : 1);

} catch (\Exception $e) {
    echo "\n";
    echo "======================================\n";
    echo "  FATAL ERROR\n";
    echo "======================================\n";
    echo "Message: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n";
    echo "======================================\n\n";

    // Write to error log
    $logDir = __DIR__ . '/storage/logs/cron';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $errorLog = $logDir . '/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $errorMessage = "[{$timestamp}] FATAL ERROR: {$e->getMessage()}\n";
    $errorMessage .= "File: {$e->getFile()}:{$e->getLine()}\n";
    $errorMessage .= "Trace:\n{$e->getTraceAsString()}\n\n";

    file_put_contents($errorLog, $errorMessage, FILE_APPEND);

    exit(1);
}
