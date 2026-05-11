#!/usr/bin/env php
<?php

/**
 * Verifica Status das Mensagens na Fila
 *
 * Uso:
 *   php tests/check_queue_status.php
 *
 * Este script consulta o banco de dados para verificar o status das mensagens
 * na fila, mostrando estatísticas e detalhes das mensagens recentes.
 */

// Carrega autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Carrega helpers
require_once __DIR__ . '/../app/Helpers/helpers.php';

// Carrega variáveis de ambiente
if (file_exists(__DIR__ . '/../.env.development')) {
    $lines = file(__DIR__ . '/../.env.development', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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

use App\Classes\QueryBuilder;
use App\Core\Database;
use mysqli;

echo "========================================\n";
echo "  STATUS DA FILA DE MENSAGENS\n";
echo "========================================\n\n";

try {
    // Conecta ao banco
    $mysqli = new mysqli(
        Database::env('DB_HOST'),
        Database::env('DB_USERNAME'),
        Database::env('DB_PASSWORD'),
        Database::env('DB_DATABASE'),
        (int) Database::env('DB_PORT', '3306')
    );
    
    if ($mysqli->connect_error) {
        throw new \RuntimeException("Erro ao conectar ao banco: " . $mysqli->connect_error);
    }
    
    $mysqli->set_charset('utf8mb4');
    $qb = new QueryBuilder($mysqli);
    $qb->withoutChave(); // Para ver todas as mensagens

    // Estatísticas gerais
    echo "📊 ESTATÍSTICAS GERAIS\n";
    echo "-----------------------\n";
    
    $total = $qb->select('messages_queue', ['COUNT(*) as total'], '1=1');
    $totalCount = $total[0]['total'] ?? 0;
    echo "Total de mensagens: {$totalCount}\n\n";

    // Estatísticas por tipo
    echo "📋 POR TIPO\n";
    echo "-----------\n";
    $porTipo = $qb->execute("
        SELECT type, COUNT(*) as total
        FROM messages_queue
        GROUP BY type
        ORDER BY total DESC
    ");
    foreach ($porTipo as $row) {
        echo "  {$row['type']}: {$row['total']}\n";
    }
    echo "\n";

    // Estatísticas por status
    echo "📊 POR STATUS\n";
    echo "-------------\n";
    $porStatus = $qb->execute("
        SELECT status, COUNT(*) as total
        FROM messages_queue
        GROUP BY status
        ORDER BY total DESC
    ");
    foreach ($porStatus as $row) {
        $status = $row['status'];
        $total = $row['total'];
        $icon = match($status) {
            'pending' => '⏳',
            'processing' => '⚙️',
            'sent' => '✅',
            'failed' => '❌',
            default => '❓',
        };
        echo "  {$icon} {$status}: {$total}\n";
    }
    echo "\n";

    // Mensagens pendentes
    echo "⏳ MENSAGENS PENDENTES (últimas 10)\n";
    echo "-----------------------------------\n";
    $pendentes = $qb->select('messages_queue', ['*'], 'status = ?', ['pending'], 'id DESC', 10);
    if (empty($pendentes)) {
        echo "  Nenhuma mensagem pendente.\n\n";
    } else {
        foreach ($pendentes as $msg) {
            echo "  ID: {$msg['id']} | Tipo: {$msg['type']} | Criada: {$msg['created_at']}\n";
        }
        echo "\n";
    }

    // Mensagens falhadas
    echo "❌ MENSAGENS FALHADAS (últimas 10)\n";
    echo "----------------------------------\n";
    $falhadas = $qb->select('messages_queue', ['*'], 'status = ?', ['failed'], 'id DESC', 10);
    if (empty($falhadas)) {
        echo "  Nenhuma mensagem falhada.\n\n";
    } else {
        foreach ($falhadas as $msg) {
            $erro = substr($msg['error_message'] ?? 'Sem mensagem de erro', 0, 60);
            echo "  ID: {$msg['id']} | Tipo: {$msg['type']} | Tentativas: {$msg['attempts']}\n";
            echo "    Erro: {$erro}...\n";
        }
        echo "\n";
    }

    // Mensagens enviadas hoje
    echo "✅ MENSAGENS ENVIADAS HOJE\n";
    echo "--------------------------\n";
    $hoje = date('Y-m-d');
    $enviadasHoje = $qb->execute("
        SELECT type, COUNT(*) as total
        FROM messages_queue
        WHERE status = 'sent' AND DATE(processed_at) = ?
        GROUP BY type
    ", [$hoje]);
    
    if (empty($enviadasHoje)) {
        echo "  Nenhuma mensagem enviada hoje.\n\n";
    } else {
        $totalHoje = 0;
        foreach ($enviadasHoje as $row) {
            echo "  {$row['type']}: {$row['total']}\n";
            $totalHoje += $row['total'];
        }
        echo "  Total: {$totalHoje}\n\n";
    }

    // Mensagens com mais tentativas
    echo "🔄 MENSAGENS COM MAIS TENTATIVAS\n";
    echo "---------------------------------\n";
    $comTentativas = $qb->select('messages_queue', ['*'], 'attempts > 0', [], 'attempts DESC, id DESC', 5);
    if (empty($comTentativas)) {
        echo "  Nenhuma mensagem com tentativas.\n\n";
    } else {
        foreach ($comTentativas as $msg) {
            echo "  ID: {$msg['id']} | Tipo: {$msg['type']} | Tentativas: {$msg['attempts']} | Status: {$msg['status']}\n";
        }
        echo "\n";
    }

    echo "========================================\n";
    echo "  COMANDOS ÚTEIS\n";
    echo "========================================\n";
    echo "Ver todas as mensagens:\n";
    echo "  SELECT * FROM messages_queue ORDER BY id DESC LIMIT 20;\n\n";
    echo "Reprocessar mensagem falhada:\n";
    echo "  UPDATE messages_queue SET status='pending', attempts=0 WHERE id=X;\n\n";
    echo "Limpar mensagens antigas (mais de 30 dias):\n";
    echo "  DELETE FROM messages_queue WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);\n\n";

} catch (\Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
