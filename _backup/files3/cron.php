#!/usr/bin/env php
<?php

/**
 * CRON Entry Point
 *
 * Execute scheduled jobs via command line
 *
 * Usage:
 *   php cron.php
 *
 * Crontab Configuration (runs every 15 minutes):
 *   *\/15 * * * * /usr/bin/php /path/to/project/cron.php >> /path/to/project/storage/logs/cron/execution.log 2>&1
 *
 * Note: Remove backslash from *\/15 in actual crontab
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

// Load configuration
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

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

try {
    // Create CRON runner
    $runner = new \App\Cron\CronRunner();

    // Register jobs
    // Add all jobs that should run on every CRON execution
    $runner->registerJob(new \App\Cron\Jobs\CalculateOverdueFeesJob());

    // TODO: Add more jobs here as needed
    // Example:
    // $runner->registerJob(new \App\Cron\Jobs\SendBirthdayEmailsJob());
    // $runner->registerJob(new \App\Cron\Jobs\CleanupOldLogsJob());

    // Execute all jobs
    $summary = $runner->run();

    // Display summary
    echo "\n";
    echo "======================================\n";
    echo "  Execution Summary\n";
    echo "======================================\n";
    echo "Total Jobs: {$summary['total_jobs']}\n";
    echo "Successful: {$summary['successful']}\n";
    echo "Failed: {$summary['failed']}\n";
    echo "Duration: {$summary['duration']}s\n";
    echo "Timestamp: {$summary['timestamp']}\n";
    echo "======================================\n\n";

    // Exit with appropriate code
    exit($runner->isSuccessful() ? 0 : 1);

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
