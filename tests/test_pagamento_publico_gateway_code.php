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

foreach (['Arquivo do Certificado', 'Chave Privada', 'Senha/Passphrase'] as $label) {
    if (!str_contains($view, $label)) {
        $fail("Label original ausente: {$label}.");
    }
}

foreach (['gatewayCertFileLabel', 'gatewayPrivateKeyLabel', 'gatewayCertPasswordLabel'] as $dynamicLabel) {
    if (str_contains($view, $dynamicLabel)) {
        $fail("O label {$dynamicLabel} não deve ser alterado dinamicamente.");
    }
}

if (!str_contains($view, "aviso('Envie PFX/P12")
    || !str_contains($view, "aviso('Obrigatória somente")
    || !str_contains($view, "aviso('Para PFX/P12")) {
    $fail('As orientações de certificado devem permanecer nos helpers aviso().');
}

echo "[OK] gateway_code público e labels de certificado validados.\n";
