#!/usr/bin/env php
<?php

$root = dirname(__DIR__);
$model = file_get_contents($root . '/app/Models/FormaPagamento.php');
$view = file_get_contents($root . '/app/Views/pages/gateways-pagamento/adicionar.php');

$fail = static function (string $message): never {
    fwrite(STDERR, "[FAIL] {$message}\n");
    exit(1);
};

if ($model === false || $view === false) {
    $fail('Não foi possível carregar os arquivos do hotfix.');
}

if (!str_contains($model, 'gp.gateway_code,') || !str_contains($model, "'gateway_code' => \$row['gateway_code']")) {
    $fail('A forma de pagamento pública não expõe gateway_code para validar as capacidades do gateway.');
}

foreach (['PFX/P12 completo', 'Certificado público + chave privada', 'Certificado público (PEM/CRT/CER)', 'Chave privada'] as $label) {
    if (!str_contains($view, $label)) {
        $fail("Label original ausente: {$label}.");
    }
}

foreach (['gatewayCertFileLabel', 'gatewayCertPasswordLabel', 'gatewayPrivateKeyGroup'] as $dynamicLabel) {
    if (!str_contains($view, $dynamicLabel)) {
        $fail("O elemento dinâmico {$dynamicLabel} está ausente.");
    }
}

foreach (['btnUploadGatewayCert', 'gatewayCertSaveFirst', 'Selecione os arquivos nesta etapa.'] as $removedElement) {
    if (str_contains($view, $removedElement)) {
        $fail("A ação manual ou mensagem redundante ainda está presente: {$removedElement}.");
    }
}

if (!str_contains($view, 'if (hasCertificateUpload)')
    || !str_contains($view, 'await uploadCertificadoGateway()')) {
    $fail('O botão Salvar não está orquestrando o envio automático do certificado.');
}

if (!str_contains($view, "aviso('Escolha PFX/P12")
    || !str_contains($view, "aviso('No modo PFX/P12")
    || !str_contains($view, "aviso('A senha é opcional")) {
    $fail('As orientações de certificado devem permanecer nos helpers aviso().');
}

echo "[OK] gateway_code público e labels de certificado validados.\n";
