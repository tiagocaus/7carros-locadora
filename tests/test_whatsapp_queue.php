#!/usr/bin/env php
<?php

/**
 * Teste de Envio de WhatsApp via Fila RabbitMQ
 *
 * Uso:
 *   php tests/test_whatsapp_queue.php
 *
 * Este script simula o envio de mensagens WhatsApp através do sistema de mensageria.
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
$batchId = 'test_whatsapp_' . date('YmdHis');

echo "========================================\n";
echo "  TESTE: Envio de WhatsApp via Fila\n";
echo "========================================\n\n";

// Número de teste (substitua pelo número real para testes)
$numeroTeste = '5527997240407'; // Formato: código do país + DDD + número

try {
    // Teste 1: Mensagem de texto simples
    echo "1. Enviando mensagem de texto simples...\n";
    $messageId1 = queue_message('whatsapp', [
        'to' => $numeroTeste,
        'message' => 'Olá! Esta é uma mensagem de teste do sistema de mensageria.',
    ], null, $batchId);
    echo "   ✓ Mensagem adicionada à fila (ID: {$messageId1})\n\n";

    // Teste 2: Mensagem formatada (com emojis e formatação)
    echo "2. Enviando mensagem formatada...\n";
    $messageId2 = queue_message('whatsapp', [
        'to' => $numeroTeste,
        'message' => "🚗 *7Carros Locadora*\n\nOlá! Seu contrato foi confirmado.\n\n📋 Contrato: #12345\n🚙 Veículo: Honda Civic 2023\n📅 Período: 01/02/2025 a 10/02/2025\n💰 Valor: R$ 1.500,00\n\nQualquer dúvida, estamos à disposição!",
    ], null, $batchId);
    echo "   ✓ Mensagem adicionada à fila (ID: {$messageId2})\n\n";

    // Teste 3: Mensagem com mídia (imagem/documento)
    echo "3. Enviando mensagem com mídia...\n";
    $messageId3 = queue_message('whatsapp', [
        'to' => $numeroTeste,
        'media_url' => 'https://example.com/contrato.pdf',
        'caption' => 'Segue em anexo seu contrato de locação.',
    ], null, $batchId);
    echo "   ✓ Mensagem adicionada à fila (ID: {$messageId3})\n\n";

    // Teste 4: Notificação de contrato (exemplo real)
    echo "4. Enviando notificação de contrato...\n";
    $contrato = [
        'id' => 12345,
        'veiculo' => 'Honda Civic 2023',
        'data_inicio' => '01/02/2025',
        'data_fim' => '10/02/2025',
        'valor_total' => 1500.00,
    ];
    
    $mensagem = "🚗 *7Carros Locadora*\n\n";
    $mensagem .= "Olá! Seu contrato foi confirmado.\n\n";
    $mensagem .= "📋 Contrato: #{$contrato['id']}\n";
    $mensagem .= "🚙 Veículo: {$contrato['veiculo']}\n";
    $mensagem .= "📅 Período: {$contrato['data_inicio']} a {$contrato['data_fim']}\n";
    $mensagem .= "💰 Valor: R$ " . number_format($contrato['valor_total'], 2, ',', '.') . "\n\n";
    $mensagem .= "Qualquer dúvida, estamos à disposição!";
    
    $messageId4 = queue_message('whatsapp', [
        'to' => $numeroTeste,
        'message' => $mensagem,
    ], null, $batchId);
    echo "   ✓ Mensagem adicionada à fila (ID: {$messageId4})\n\n";

    // Teste 5: Teste com diferentes formatos de número
    echo "5. Testando diferentes formatos de número...\n";
    $formatos = [
        '11999999999',           // Sem código do país
        '(11) 99999-9999',      // Com formatação
        '+55 11 99999-9999',    // Com código do país
        '5511999999999',        // Formato completo
    ];

    foreach ($formatos as $index => $numero) {
        $messageId = queue_message('whatsapp', [
            'to' => $numero,
            'message' => "Teste de formato de número: {$numero}",
        ], null, $batchId);
        echo "   ✓ Formato " . ($index + 1) . " adicionado (ID: {$messageId})\n";
    }
    echo "\n";

    echo "========================================\n";
    echo "  RESUMO\n";
    echo "========================================\n";
    echo "Total de mensagens adicionadas: 8\n";
    echo "Mensagem IDs principais: {$messageId1}, {$messageId2}, {$messageId3}, {$messageId4}\n\n";
    echo "⚠️  IMPORTANTE:\n";
    echo "- Substitua '{$numeroTeste}' por um número real para testes\n";
    echo "- Certifique-se de que a Evolution API está configurada corretamente\n";
    echo "- Verifique as variáveis MENSAGERIA_API_URL e MENSAGERIA_API_KEY no .env\n\n";
    echo "Próximos passos:\n";
    echo "1. Execute o worker CRON: php cron.php\n";
    echo "2. Verifique os logs: tail -f storage/logs/cron/execution.log\n";
    echo "3. Consulte o banco: SELECT * FROM messages_queue WHERE type = 'whatsapp' ORDER BY id DESC LIMIT 10;\n\n";

} catch (\Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
