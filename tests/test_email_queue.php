#!/usr/bin/env php
<?php

/**
 * Teste de Envio de Email via Fila RabbitMQ
 *
 * Uso:
 *   php tests/test_email_queue.php
 *
 * Este script simula o envio de emails através do sistema de mensageria.
 * As mensagens serão adicionadas à fila e processadas pelo worker CRON.
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

// Simula sessão (para multi-tenancy)
session_start();
$_SESSION['chave'] = '1111111111111'; // Chave real de tenant para testes

// Identificador de batch para rastreamento
$batchId = 'test_email_' . date('YmdHis');

echo "========================================\n";
echo "  TESTE: Envio de Email via Fila\n";
echo "========================================\n\n";

try {
    // Teste 1: Email simples
    echo "1. Enviando email simples...\n";
    $messageId1 = queue_message('email', [
        'to' => 'tiagopereiracaus@gmail.com',
        'to_name' => 'João Silva',
        'subject' => 'Teste de Email - Sistema de Mensageria',
        'body' => '<h1>Email de Teste</h1><p>Este é um email de teste enviado através do sistema de mensageria com RabbitMQ.</p><p>Mensagem ID: ' . time() . '</p>',
        'body_text' => 'Email de Teste\n\nEste é um email de teste enviado através do sistema de mensageria com RabbitMQ.',
    ], null, $batchId);
    echo "   ✓ Mensagem adicionada à fila (ID: {$messageId1})\n\n";

    // Teste 2: Email com cópia
    echo "2. Enviando email com cópia (CC)...\n";
    $messageId2 = queue_message('email', [
        'to' => 'tiago_caus@hotmail.com',
        'to_name' => 'Maria Santos',
        'subject' => 'Teste de Email com Cópia',
        'body' => '<h1>Email com Cópia</h1><p>Este email tem cópias para outros destinatários.</p>',
        'cc' => ['copia1@exemplo.com', 'copia2@exemplo.com'],
    ], null, $batchId);
    echo "   ✓ Mensagem adicionada à fila (ID: {$messageId2})\n\n";

    // Teste 3: Email com anexo (simulado)
    echo "3. Enviando email com anexo...\n";
    $messageId3 = queue_message('email', [
        'to' => 'contato@7carros.com.br',
        'subject' => 'Contrato de Locação - Anexo',
        'body' => '<h1>Contrato de Locação</h1><p>Segue em anexo o contrato de locação.</p>',
        'attachments' => [
            __DIR__ . '/../storage/uploads/contrato_teste.pdf', // Caminho simulado
        ],
    ], null, $batchId);
    echo "   ✓ Mensagem adicionada à fila (ID: {$messageId3})\n\n";

    // Teste 4: Email completo (todos os campos)
    echo "4. Enviando email completo (todos os campos)...\n";
    $messageId4 = queue_message('email', [
        'to' => 'sac@hostcia.net',
        'to_name' => 'Pedro Oliveira',
        'subject' => 'Email Completo - Todos os Campos',
        'body' => '<h1>Email Completo</h1><p>Este email usa todos os campos disponíveis.</p>',
        'body_text' => 'Email Completo\n\nEste email usa todos os campos disponíveis.',
        'cc' => ['gerente@exemplo.com'],
        'bcc' => ['arquivo@exemplo.com'],
        'reply_to' => 'suporte@exemplo.com',
        'reply_to_name' => 'Suporte 7Carros',
    ], null, $batchId);
    echo "   ✓ Mensagem adicionada à fila (ID: {$messageId4})\n\n";

    echo "========================================\n";
    echo "  RESUMO\n";
    echo "========================================\n";
    echo "Total de mensagens adicionadas: 4\n";
    echo "Mensagem IDs: {$messageId1}, {$messageId2}, {$messageId3}, {$messageId4}\n\n";
    echo "Próximos passos:\n";
    echo "1. Execute o worker CRON: php cron.php\n";
    echo "2. Verifique os logs: tail -f storage/logs/cron/execution.log\n";
    echo "3. Consulte o banco: SELECT * FROM messages_queue WHERE id IN ({$messageId1}, {$messageId2}, {$messageId3}, {$messageId4});\n\n";

} catch (\Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
