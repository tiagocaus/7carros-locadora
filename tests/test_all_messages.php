#!/usr/bin/env php
<?php

/**
 * Teste Completo: Envio de Todos os Tipos de Mensagens
 *
 * Uso:
 *   php tests/test_all_messages.php
 *
 * Este script testa o envio de todos os tipos de mensagens (email, SMS, WhatsApp)
 * através do sistema de mensageria em uma única execução.
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
$batchId = 'test_all_' . date('YmdHis');

echo "========================================\n";
echo "  TESTE COMPLETO: Todos os Tipos\n";
echo "========================================\n\n";

$results = [
    'email' => [],
    'sms' => [],
    'whatsapp' => [],
];

try {
    // ========== EMAILS ==========
    echo "📧 TESTANDO EMAILS\n";
    echo "-------------------\n";

    $emailId1 = queue_message('email', [
        'to' => 'teste@exemplo.com',
        'subject' => 'Teste Completo - Email',
        'body' => '<h1>Email de Teste</h1><p>Teste completo do sistema de mensageria.</p>',
    ], null, $batchId);
    $results['email'][] = $emailId1;
    echo "✓ Email 1 adicionado (ID: {$emailId1})\n";

    $emailId2 = queue_message('email', [
        'to' => 'teste2@exemplo.com',
        'subject' => 'Email com Cópia',
        'body' => '<p>Email com cópia para outros destinatários.</p>',
        'cc' => ['copia@exemplo.com'],
    ], null, $batchId);
    $results['email'][] = $emailId2;
    echo "✓ Email 2 adicionado (ID: {$emailId2})\n\n";

    // ========== SMS ==========
    echo "📱 TESTANDO SMS\n";
    echo "----------------\n";

    $smsId1 = queue_message('sms', [
        'to' => '5511999999999',
        'message' => 'Teste de SMS - Sistema de Mensageria',
    ], null, $batchId);
    $results['sms'][] = $smsId1;
    echo "✓ SMS 1 adicionado (ID: {$smsId1})\n";

    $smsId2 = queue_message('sms', [
        'to' => '5511999999999',
        'message' => 'Código de verificação: 123456',
    ], null, $batchId);
    $results['sms'][] = $smsId2;
    echo "✓ SMS 2 adicionado (ID: {$smsId2})\n\n";

    // ========== WHATSAPP ==========
    echo "💬 TESTANDO WHATSAPP\n";
    echo "---------------------\n";

    $whatsappId1 = queue_message('whatsapp', [
        'to' => '5511999999999',
        'message' => 'Teste de WhatsApp - Sistema de Mensageria',
    ], null, $batchId);
    $results['whatsapp'][] = $whatsappId1;
    echo "✓ WhatsApp 1 adicionado (ID: {$whatsappId1})\n";

    $whatsappId2 = queue_message('whatsapp', [
        'to' => '5511999999999',
        'message' => "🚗 *7Carros Locadora*\n\nTeste completo do sistema!",
    ], null, $batchId);
    $results['whatsapp'][] = $whatsappId2;
    echo "✓ WhatsApp 2 adicionado (ID: {$whatsappId2})\n\n";

    // ========== RESUMO ==========
    echo "========================================\n";
    echo "  RESUMO DO TESTE\n";
    echo "========================================\n";
    echo "Total de mensagens adicionadas: " . (count($results['email']) + count($results['sms']) + count($results['whatsapp'])) . "\n\n";
    
    echo "📧 Emails: " . count($results['email']) . "\n";
    foreach ($results['email'] as $id) {
        echo "   - ID: {$id}\n";
    }
    echo "\n";
    
    echo "📱 SMS: " . count($results['sms']) . "\n";
    foreach ($results['sms'] as $id) {
        echo "   - ID: {$id}\n";
    }
    echo "\n";
    
    echo "💬 WhatsApp: " . count($results['whatsapp']) . "\n";
    foreach ($results['whatsapp'] as $id) {
        echo "   - ID: {$id}\n";
    }
    echo "\n";
    
    echo "========================================\n";
    echo "  PRÓXIMOS PASSOS\n";
    echo "========================================\n";
    echo "1. Execute o worker CRON para processar as mensagens:\n";
    echo "   php cron.php\n\n";
    echo "2. Monitore os logs em tempo real:\n";
    echo "   tail -f storage/logs/cron/execution.log\n\n";
    echo "3. Verifique o status das mensagens no banco:\n";
    echo "   SELECT type, status, COUNT(*) as total\n";
    echo "   FROM messages_queue\n";
    echo "   WHERE id IN (" . implode(', ', array_merge($results['email'], $results['sms'], $results['whatsapp'])) . ")\n";
    echo "   GROUP BY type, status;\n\n";
    echo "4. Consulte mensagens específicas:\n";
    echo "   SELECT * FROM messages_queue WHERE type = 'email' ORDER BY id DESC LIMIT 5;\n";
    echo "   SELECT * FROM messages_queue WHERE type = 'sms' ORDER BY id DESC LIMIT 5;\n";
    echo "   SELECT * FROM messages_queue WHERE type = 'whatsapp' ORDER BY id DESC LIMIT 5;\n\n";

} catch (\Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
