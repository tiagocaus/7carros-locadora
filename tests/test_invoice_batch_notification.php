#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\InvoiceBatchNotificationService;
use App\Services\MessageTemplateService;
use App\I18n\TemplateVariables;

function assertBatch(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$layoutCalls = [];
$service = new InvoiceBatchNotificationService(
    static function (string $content, array $context, string $chave, ?string $locale) use (&$layoutCalls): string {
        $layoutCalls[] = compact('context', 'chave', 'locale');
        return '<!DOCTYPE html><html><body><header><img src="https://example.com/logo.png" alt="Empresa Teste">'
            . '<strong>Empresa Teste</strong></header><main>' . $content . '</main>'
            . '<footer>Empresa Teste Ltda. | CNPJ 00.000.000/0001-00</footer></body></html>';
    }
);
$cliente = [
    'nome_rsocial' => 'Cliente <Teste>',
    'email' => 'cliente@example.com',
    'telefone' => '5511999999999',
    'preferred_locale' => 'pt_BR',
];
$faturas = [
    [
        'id' => 10,
        'chave' => 'tenant-teste',
        'codigo' => 'FAT-10',
        'descricao' => 'Parcela futura',
        'data_venci' => '2030-01-10',
        'valor_total' => 100,
        'parcela' => 1,
        'total_parcelas' => 2,
        'link_pagamento' => 'https://example.com/pagar/10',
        'notification_type' => 'pre_due',
    ],
    [
        'id' => 11,
        'chave' => 'tenant-teste',
        'codigo' => 'FAT-11',
        'descricao' => 'Parcela vencida',
        'data_venci' => '2020-01-10',
        'valor_total' => 200,
        'parcela' => 2,
        'total_parcelas' => 2,
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
assertBatch(substr_count($email['body'], 'border-collapse:collapse;border:1px') === 2, 'Email deve conter duas tabelas de faturas');
assertBatch(str_contains($email['body'], 'Cliente &lt;Teste&gt;'), 'Nome do cliente nao foi escapado');
assertBatch(str_starts_with($email['body'], '<!DOCTYPE html>'), 'Layout HTML nao foi aplicado');
assertBatch(str_contains($email['body'], '<img'), 'Logo do tenant ausente no layout');
assertBatch(str_contains($email['body'], 'CNPJ 00.000.000/0001-00'), 'Dados do tenant ausentes no layout');
assertBatch(($layoutCalls[0]['chave'] ?? null) === 'tenant-teste', 'Tenant incorreto ao renderizar layout');
assertBatch(($layoutCalls[0]['locale'] ?? null) === 'pt_BR', 'Locale incorreto ao renderizar layout');
assertBatch(str_contains($email['body_text'], 'FAT-10'), 'Fatura futura ausente no texto');
assertBatch(str_contains($email['body_text'], 'FAT-11'), 'Fatura vencida ausente no texto');
assertBatch(str_contains($email['body'], 'Parcela 1 de 2'), 'Parcela futura ausente no email');
assertBatch(str_contains($email['body'], 'Parcela 2 de 2'), 'Parcela vencida ausente no email');
assertBatch(str_contains($email['body_text'], 'Parcela 1 de 2'), 'Parcela futura ausente no texto');
assertBatch(str_contains($email['body'], 'class="invoice-table"'), 'Tabela responsiva ausente no email');
assertBatch(str_contains($email['body'], 'class="invoice-nowrap"'), 'Colunas sem quebra ausentes no email');
assertBatch(($layoutCalls[0]['context']['_email_layout'] ?? null) === 'wide', 'Lote com varias faturas deve usar layout largo');

$emailUnitarioDireto = $service->buildBatchPayload('email', [$faturas[0]], $cliente, 7);
assertBatch(
    !isset($layoutCalls[1]['context']['_email_layout']),
    'Payload com uma fatura nao deve ampliar o layout'
);
assertBatch(str_contains($emailUnitarioDireto['body'], 'FAT-10'), 'Payload unitario direto invalido');

$whatsapp = $service->buildBatchPayload('whatsapp', $faturas, $cliente, 7);
assertBatch($whatsapp['to'] === '5511999999999', 'Destinatario de WhatsApp incorreto');
assertBatch(str_contains($whatsapp['message'], 'Proximas do vencimento:'), 'Secao futura ausente no WhatsApp');
assertBatch(str_contains($whatsapp['message'], 'Vencidas:'), 'Secao vencida ausente no WhatsApp');
assertBatch(str_contains($whatsapp['message'], 'Parcela 2 de 2'), 'Parcela ausente no WhatsApp');

$sms = $service->buildBatchPayload('sms', $faturas, $cliente, 7);
assertBatch(str_contains($sms['message'], 'Parcela 1 de 2'), 'Parcela ausente no SMS');

assertBatch(TemplateVariables::formatInvoiceInstallment(1, 1, 'pt_BR') === 'Parcela 1 de 1', 'Parcela unica deve ser exibida');
assertBatch(TemplateVariables::formatInvoiceInstallment(2, 12, 'en_US') === 'Installment 2 of 12', 'Parcela em ingles incorreta');
assertBatch(TemplateVariables::formatInvoiceInstallment(0, 12, 'pt_BR') === null, 'Parcela zero deve ser omitida');

$vencidas = $service->buildBatchPayload('email', [$faturas[1], $faturas[1]], $cliente, 7);
assertBatch(str_contains($vencidas['subject'], '2 faturas vencidas'), 'Assunto de vencidas incorreto');

$reflection = new ReflectionClass(MessageTemplateService::class);
$templateService = $reflection->newInstanceWithoutConstructor();
$layoutOptionsMethod = $reflection->getMethod('resolveEmailLayoutOptions');
$layoutOptionsMethod->setAccessible(true);
$wideLayout = $layoutOptionsMethod->invoke($templateService, ['_email_layout' => 'wide']);
$defaultLayout = $layoutOptionsMethod->invoke($templateService, []);
assertBatch($wideLayout['width_attribute'] === '100%', 'Layout largo deve ocupar 100%');
assertBatch($wideLayout['max_width'] === '1000px', 'Layout largo deve limitar em 1000px');
assertBatch($wideLayout['css_width'] === '100%', 'Largura CSS do layout largo incorreta');
assertBatch($defaultLayout['width_attribute'] === '600', 'Layout padrao deve manter 600px');
assertBatch($defaultLayout['max_width'] === '600px', 'Limite do layout padrao incorreto');
assertBatch($defaultLayout['css_width'] === '100%', 'Largura CSS do layout padrao incorreta');

$brandingMethod = $reflection->getMethod('buildBrandingHeader');
$brandingMethod->setAccessible(true);

$brandingComLogo = $brandingMethod->invoke($templateService, [
    'nome_fantasia' => 'Empresa <Teste>',
    'logo_url' => 'https://example.com/logo.png?x=1&y=2',
]);
assertBatch(str_contains($brandingComLogo, '<img'), 'Branding deve renderizar logo valida');
assertBatch(str_contains($brandingComLogo, 'Empresa &lt;Teste&gt;'), 'Nome do tenant deve ser escapado');
assertBatch(str_contains($brandingComLogo, '&amp;'), 'URL da logo deve ser escapada');

$brandingSemLogo = $brandingMethod->invoke($templateService, [
    'nome_fantasia' => 'Empresa sem Logo',
    'logo_url' => 'javascript:alert(1)',
]);
assertBatch(!str_contains($brandingSemLogo, '<img'), 'Logo insegura nao deve ser renderizada');
assertBatch(str_contains($brandingSemLogo, 'Empresa sem Logo'), 'Nome deve ser fallback sem logo');

echo "OK: agrupamento de cobrancas validado\n";
