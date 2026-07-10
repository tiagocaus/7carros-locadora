#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\InvoiceBatchNotificationService;

function assertBatch(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$service = new InvoiceBatchNotificationService();
$cliente = [
    'nome_rsocial' => 'Cliente <Teste>',
    'email' => 'cliente@example.com',
    'telefone' => '5511999999999',
];
$faturas = [
    [
        'id' => 10,
        'codigo' => 'FAT-10',
        'descricao' => 'Parcela futura',
        'data_venci' => '2030-01-10',
        'valor_total' => 100,
        'link_pagamento' => 'https://example.com/pagar/10',
        'notification_type' => 'pre_due',
    ],
    [
        'id' => 11,
        'codigo' => 'FAT-11',
        'descricao' => 'Parcela vencida',
        'data_venci' => '2020-01-10',
        'valor_total' => 200,
        'link_pagamento' => 'https://example.com/pagar/11',
        'notification_type' => 'overdue',
    ],
];

$email = $service->buildBatchPayload('email', $faturas, $cliente, 7);
assertBatch($email['to'] === 'cliente@example.com', 'Destinatario de email incorreto');
assertBatch($email['id_matriz_filial'] === 7, 'Filial do email incorreta');
assertBatch(str_contains($email['subject'], '2 faturas para pagamento'), 'Assunto misto incorreto');
assertBatch(str_contains($email['body'], 'Proximas do vencimento'), 'Secao pre-vencimento ausente');
assertBatch(str_contains($email['body'], 'Vencidas'), 'Secao de vencidas ausente');
assertBatch(substr_count($email['body'], '<table') === 2, 'Email deve conter duas tabelas');
assertBatch(str_contains($email['body'], 'Cliente &lt;Teste&gt;'), 'Nome do cliente nao foi escapado');
assertBatch(str_contains($email['body_text'], 'FAT-10'), 'Fatura futura ausente no texto');
assertBatch(str_contains($email['body_text'], 'FAT-11'), 'Fatura vencida ausente no texto');

$whatsapp = $service->buildBatchPayload('whatsapp', $faturas, $cliente, 7);
assertBatch($whatsapp['to'] === '5511999999999', 'Destinatario de WhatsApp incorreto');
assertBatch(str_contains($whatsapp['message'], 'Proximas do vencimento:'), 'Secao futura ausente no WhatsApp');
assertBatch(str_contains($whatsapp['message'], 'Vencidas:'), 'Secao vencida ausente no WhatsApp');

$vencidas = $service->buildBatchPayload('email', [$faturas[1], $faturas[1]], $cliente, 7);
assertBatch(str_contains($vencidas['subject'], '2 faturas vencidas'), 'Assunto de vencidas incorreto');

echo "OK: agrupamento de cobrancas validado\n";
