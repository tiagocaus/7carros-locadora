<?php
/** Aplica somente a permissão do relatório; executar no terminal do servidor. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Core\Database;
$ambiente = 'development';
foreach ($argv as $arg) if (str_starts_with($arg, '--env=')) $ambiente = substr($arg, 6);
if (!in_array($ambiente, ['development', 'production'], true)) throw new InvalidArgumentException('Ambiente inválido.');
$_ENV['APP_ENV'] = $ambiente;
if (Database::env('DB_HOST') !== 'localhost') throw new RuntimeException('Execute no servidor do ambiente com DB_HOST=localhost.');
$db = Database::getConnection();
$nome = '00432_create_relatorio_historico_odometros_permission.php';
$query = $db->prepare('SELECT COUNT(*) FROM migrations WHERE migration = ?');
$query->execute([$nome]);
$registrada = (bool) $query->fetchColumn();
echo 'Ambiente: ' . $ambiente . "\nMigration registrada: " . ($registrada ? 'sim' : 'não') . "\n";
if (!in_array('--aplicar', $argv, true)) { echo "Diagnóstico concluído. Use --aplicar para criar a permissão.\n"; exit; }
$migration = require dirname(__DIR__) . '/app/Database/migrations/' . $nome;
$migration->up();
$query = $db->prepare('INSERT IGNORE INTO migrations (migration) VALUES (?)');
$query->execute([$nome]);
echo "Permissão aplicada para Proprietário e Gerente. Nenhuma leitura foi alterada.\n";
