#!/usr/bin/env php
<?php

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Services\Gateways\GatewayCertificateService;

$fail = static function (string $message): never {
    fwrite(STDERR, "[FAIL] {$message}\n");
    exit(1);
};

$temp = sys_get_temp_dir() . '/gateway_certificate_test_' . bin2hex(random_bytes(6));
mkdir($temp, 0700, true);
putenv('RANDFILE=' . $temp . '/openssl-rand');

try {
    $privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr = openssl_csr_new(['commonName' => 'Empresa Teste:12345678000199'], $privateKey, ['digest_alg' => 'sha256']);
    $certificate = openssl_csr_sign($csr, null, $privateKey, 2, ['digest_alg' => 'sha256']);
    if ($privateKey === false || $certificate === false) {
        $fail('Não foi possível gerar certificado de teste.');
    }

    openssl_x509_export($certificate, $certificatePem);
    openssl_pkey_export($privateKey, $privateKeyPem);
    openssl_pkcs12_export($certificate, $pkcs12, $privateKey, 'senha-teste');

    $certFile = $temp . '/certificate.pem';
    $keyFile = $temp . '/private.key';
    $p12File = $temp . '/certificate.p12';
    file_put_contents($certFile, $certificatePem);
    file_put_contents($keyFile, $privateKeyPem);
    file_put_contents($p12File, $pkcs12);

    $service = new GatewayCertificateService($temp . '/stored');
    $pem = $service->upload(
        ['name' => 'certificate.pem', 'tmp_name' => $certFile, 'error' => UPLOAD_ERR_OK],
        10,
        '1111111111111',
        '',
        ['name' => 'private.key', 'tmp_name' => $keyFile, 'error' => UPLOAD_ERR_OK]
    );
    if (empty($pem['success']) || $pem['format'] !== 'pem' || empty($pem['key_filename'])) {
        $fail('Par PEM + chave privada não foi armazenado.');
    }
    if (($pem['data']['documento'] ?? null) !== '12345678000199') {
        $fail('CNPJ completo não foi extraído do certificado PEM.');
    }

    $prepared = $service->prepare([
        'certificado_formato' => 'pem',
        'certificado_arquivo' => $pem['filename'],
        'certificado_chave_arquivo' => $pem['key_filename'],
    ]);
    if (!is_file($prepared['certPath']) || !is_file($prepared['keyPath']) || $prepared['temporary']) {
        $fail('Certificado PEM armazenado não foi preparado corretamente.');
    }

    $p12 = $service->upload(
        ['name' => 'certificate.p12', 'tmp_name' => $p12File, 'error' => UPLOAD_ERR_OK],
        11,
        '1111111111111',
        'senha-teste'
    );
    if (empty($p12['success']) || $p12['format'] !== 'pkcs12') {
        $fail('Certificado P12 não foi armazenado.');
    }
    if (($p12['data']['documento'] ?? null) !== '12345678000199') {
        $fail('CNPJ completo não foi extraído do certificado P12.');
    }
    $extracted = $service->prepare([
        'certificado_formato' => 'pkcs12',
        'certificado_arquivo' => $p12['filename'],
        'certificado_senha' => $p12['password_encrypted'],
    ]);
    if (!is_file($extracted['certPath']) || !is_file($extracted['keyPath']) || !$extracted['temporary']) {
        $fail('Certificado P12 não foi extraído para mTLS.');
    }
    $service->cleanupPrepared($extracted);

    echo "[OK] Upload e preparação de certificados PEM e P12 validados.\n";
} finally {
    if (is_dir($temp)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($temp);
    }
}
