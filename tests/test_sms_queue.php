#!/usr/bin/env php
<?php

/**
 * Teste de Envio de SMS via Fila RabbitMQ
 *
 * Uso:
 *   php tests/test_sms_queue.php
 *
 * Este script simula o envio de SMS através do sistema de mensageria.
 * As mensagens serão adicionadas à fila e processadas pelo worker CRON.
 *
 * NOTA: O serviço de SMS está com estrutura base e precisa de integração
 * com provedor de SMS (Twilio, Zenvia, TotalVoice, etc.)
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
$batchId = 'test_sms_' . date('YmdHis');

echo "========================================\n";
echo "  TESTE: Envio de SMS via Fila\n";
echo "========================================\n\n";

// Número de teste (substitua pelo número real para testes)
$numeroTeste = '5527997240407';

try {
    // Teste 1: SMS simples
    echo "1. Enviando SMS simples...\n";
    $messageId1 = queue_message('sms', [
        'to' => $numeroTeste,
        'message' => 'Este é um SMS de teste do sistema de mensageria.',
    ], null, $batchId);
    echo "   ✓ Mensagem adicionada à fila (ID: {$messageId1})\n\n";

    // Teste 2: Código de verificação
    echo "2. Enviando código de verificação...\n";
    $codigo = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $messageId2 = queue_message('sms', [
        'to' => $numeroTeste,
        'message' => "Seu código de verificação é: {$codigo}. Válido por 10 minutos.",
    ], null, $batchId);
    echo "   ✓ Mensagem adicionada à fila (ID: {$messageId2})\n";
    echo "   Código gerado: {$codigo}\n\n";

    // Teste 3: Notificação de lembrete
    echo "3. Enviando lembrete de vencimento...\n";
    $messageId3 = queue_message('sms', [
        'to' => $numeroTeste,
        'message' => '7Carros: Lembrete - Seu contrato vence em 3 dias. Valor: R$ 1.500,00',
    ], null, $batchId);
    echo "   ✓ Mensagem adicionada à fila (ID: {$messageId3})\n\n";

    // Teste 4: Confirmação de ação
    echo "4. Enviando confirmação de ação...\n";
    $messageId4 = queue_message('sms', [
        'to' => $numeroTeste,
        'message' => '7Carros: Seu contrato #12345 foi confirmado com sucesso!',
    ], null, $batchId);
    echo "   ✓ Mensagem adicionada à fila (ID: {$messageId4})\n\n";

    // Teste 5: SMS longo (testar limite de caracteres)
    echo "5. Enviando SMS longo...\n";
    $mensagemLonga = '7Carros Locadora - Esta é uma mensagem longa para testar o limite de caracteres do SMS. ';
    $mensagemLonga .= 'Geralmente SMS tem limite de 160 caracteres, mas alguns provedores suportam mensagens concatenadas. ';
    $mensagemLonga .= 'Esta mensagem deve ser dividida automaticamente se necessário.';
    $messageId5 = queue_message('sms', [
        'to' => $numeroTeste,
        'message' => $mensagemLonga,
    ], null, $batchId);
    echo "   ✓ Mensagem adicionada à fila (ID: {$messageId5})\n";
    echo "   Tamanho: " . strlen($mensagemLonga) . " caracteres\n\n";

    echo "========================================\n";
    echo "  RESUMO\n";
    echo "========================================\n";
    echo "Total de mensagens adicionadas: 5\n";
    echo "Mensagem IDs: {$messageId1}, {$messageId2}, {$messageId3}, {$messageId4}, {$messageId5}\n\n";
    echo "⚠️  IMPORTANTE:\n";
    echo "- O serviço de SMS está com estrutura base\n";
    echo "- É necessário integrar com um provedor de SMS (Twilio, Zenvia, TotalVoice, etc.)\n";
    echo "- As mensagens serão adicionadas à fila, mas falharão no processamento até a integração\n\n";
    echo "Próximos passos:\n";
    echo "1. Execute o worker CRON: php cron.php\n";
    echo "2. Verifique os logs: tail -f storage/logs/cron/execution.log\n";
    echo "3. Consulte o banco: SELECT * FROM messages_queue WHERE type = 'sms' ORDER BY id DESC LIMIT 10;\n\n";

} catch (\Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
