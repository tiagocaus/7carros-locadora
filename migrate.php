<?php

/**
 * Migration Runner
 *
 * Executa migrations do banco de dados
 *
 * Uso:
 *   php migrate.php                  # Executa migrations pendentes
 *   php migrate.php --rollback       # Reverte última migration
 *   php migrate.php --env=production # Define ambiente
 */

require __DIR__ . '/vendor/autoload.php';

use App\Core\Database;

// Define ambiente (development ou production)
$env = 'development';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--env=')) {
        $env = str_replace('--env=', '', $arg);
    }
}

// Define variável de ambiente
putenv("APP_ENV=$env");

echo "🚀 7Carros Migration Runner\n";
echo "Environment: $env\n";
echo str_repeat('=', 50) . "\n\n";

try {
    $pdo = Database::getConnection();

    // Cria tabela de migrations se não existir
    createMigrationsTableIfNotExists($pdo);

    // Verifica se é rollback
    $isRollback = in_array('--rollback', $argv);

    if ($isRollback) {
        rollbackLastMigration($pdo);
    } else {
        runPendingMigrations($pdo);
    }

} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

/**
 * Cria a tabela de migrations se não existir
 */
function createMigrationsTableIfNotExists(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_migration (migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Executa migrations pendentes
 */
function runPendingMigrations(PDO $pdo): void
{
    $migrationsPath = __DIR__ . '/app/Database/migrations';

    if (!is_dir($migrationsPath)) {
        echo "⚠️  Diretório de migrations não encontrado: $migrationsPath\n";
        return;
    }

    // Obtém migrations executadas
    $executed = getExecutedMigrations($pdo);

    // Obtém arquivos de migration
    $files = glob($migrationsPath . '/*.php');
    sort($files);

    if (empty($files)) {
        echo "ℹ️  Nenhuma migration encontrada.\n";
        return;
    }

    $pending = [];
    foreach ($files as $file) {
        $filename = basename($file);
        if (!in_array($filename, $executed)) {
            $pending[] = $file;
        }
    }

    if (empty($pending)) {
        echo "✅ Todas as migrations já foram executadas.\n";
        return;
    }

    echo "📋 Migrations pendentes: " . count($pending) . "\n\n";

    foreach ($pending as $file) {
        runMigration($pdo, $file);
    }

    echo "\n✅ Todas as migrations foram executadas com sucesso!\n";
}

/**
 * Executa uma migration
 */
function runMigration(PDO $pdo, string $file): void
{
    $filename = basename($file);

    echo "⏳ Executando: $filename... ";

    try {
        $pdo->beginTransaction();

        // Carrega a migration
        $migration = require $file;

        // Executa o método up()
        $migration->up();

        // Registra a migration
        $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
        $stmt->execute([$filename]);

        // Verifica se ainda há transação ativa antes de fazer commit
        // (CREATE/ALTER TABLE causam commit implícito no MySQL)
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }

        echo "✅\n";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        echo "❌\n";
        throw new Exception("Erro ao executar migration '$filename': " . $e->getMessage());
    }
}

/**
 * Reverte a última migration
 */
function rollbackLastMigration(PDO $pdo): void
{
    $stmt = $pdo->query("
        SELECT migration FROM migrations
        ORDER BY id DESC LIMIT 1
    ");

    $lastMigration = $stmt->fetch(PDO::FETCH_COLUMN);

    if (!$lastMigration) {
        echo "ℹ️  Nenhuma migration para reverter.\n";
        return;
    }

    echo "⏳ Revertendo: $lastMigration... ";

    try {
        $pdo->beginTransaction();

        $file = __DIR__ . '/app/Database/migrations/' . $lastMigration;

        if (!file_exists($file)) {
            throw new Exception("Arquivo de migration não encontrado: $file");
        }

        // Carrega a migration
        $migration = require $file;

        // Executa o método down()
        $migration->down();

        // Remove o registro
        $stmt = $pdo->prepare("DELETE FROM migrations WHERE migration = ?");
        $stmt->execute([$lastMigration]);

        $pdo->commit();

        echo "✅\n";
        echo "\n✅ Migration revertida com sucesso!\n";

    } catch (Exception $e) {
        $pdo->rollback();
        echo "❌\n";
        throw new Exception("Erro ao reverter migration: " . $e->getMessage());
    }
}

/**
 * Obtém lista de migrations já executadas
 */
function getExecutedMigrations(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT migration FROM migrations ORDER BY id");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
