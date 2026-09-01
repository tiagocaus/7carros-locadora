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
    openssl_pkey_export($privateKey, $encryptedPrivateKeyPem, 'senha-chave');
    openssl_pkcs12_export($certificate, $pkcs12, $privateKey, 'senha-teste');
    openssl_pkcs12_export($certificate, $pkcs12WithoutPassword, $privateKey, '');

    $caKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $caCsr = openssl_csr_new(['commonName' => 'CA Teste'], $caKey, ['digest_alg' => 'sha256']);
    $caCertificate = openssl_csr_sign($caCsr, null, $caKey, 3, ['digest_alg' => 'sha256']);
    $leafKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $leafCsr = openssl_csr_new(['commonName' => 'Certificado com Cadeia'], $leafKey, ['digest_alg' => 'sha256']);
    $leafCertificate = openssl_csr_sign($leafCsr, $caCertificate, $caKey, 2, ['digest_alg' => 'sha256']);
    openssl_pkcs12_export($leafCertificate, $pkcs12WithChain, $leafKey, 'senha-cadeia', ['extracerts' => [$caCertificate]]);

    $certFile = $temp . '/certificate.pem';
    $keyFile = $temp . '/private.key';
    $p12File = $temp . '/certificate.p12';
    $p12WithoutPasswordFile = $temp . '/certificate-empty-password.p12';
    $encryptedKeyFile = $temp . '/private-encrypted.key';
    $p12WithChainFile = $temp . '/certificate-chain.p12';
    $otherPrivateKeyFile = $temp . '/other-private.key';
    $expiredCertificateFile = $temp . '/expired-certificate.pem';
    file_put_contents($certFile, $certificatePem);
    file_put_contents($keyFile, $privateKeyPem);
    file_put_contents($p12File, $pkcs12);
    file_put_contents($p12WithoutPasswordFile, $pkcs12WithoutPassword);
    file_put_contents($encryptedKeyFile, $encryptedPrivateKeyPem);
    file_put_contents($p12WithChainFile, $pkcs12WithChain);
    $otherPrivateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($otherPrivateKey, $otherPrivateKeyPem);
    file_put_contents($otherPrivateKeyFile, $otherPrivateKeyPem);
    $expiredCertificate = openssl_csr_sign($csr, null, $privateKey, 0, ['digest_alg' => 'sha256']);
    openssl_x509_export($expiredCertificate, $expiredCertificatePem);
    file_put_contents($expiredCertificateFile, $expiredCertificatePem);

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
    $storedCertificateMode = fileperms($service->storedPath($pem['filename'])) & 0777;
    $storedKeyMode = fileperms($service->storedPath($pem['key_filename'])) & 0777;
    if ($storedCertificateMode !== 0600 || $storedKeyMode !== 0600) {
        $fail('Certificado ou chave privada não foram armazenados com permissão 0600.');
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

    $p12WithoutPassword = $service->upload(
        ['name' => 'certificate-empty-password.p12', 'tmp_name' => $p12WithoutPasswordFile, 'error' => UPLOAD_ERR_OK],
        12,
        '1111111111111',
        '',
        null,
        'pkcs12'
    );
    if (empty($p12WithoutPassword['success'])) {
        $fail('Certificado P12 com senha vazia foi rejeitado.');
    }

    $encryptedPair = $service->upload(
        ['name' => 'certificate.pem', 'tmp_name' => $certFile, 'error' => UPLOAD_ERR_OK],
        13,
        '1111111111111',
        'senha-chave',
        ['name' => 'private-encrypted.key', 'tmp_name' => $encryptedKeyFile, 'error' => UPLOAD_ERR_OK],
        'pem_pair'
    );
    if (empty($encryptedPair['success'])) {
        $fail('Chave privada protegida por passphrase foi rejeitada.');
    }

    $wrongMode = $service->upload(
        ['name' => 'certificate.pem', 'tmp_name' => $certFile, 'error' => UPLOAD_ERR_OK],
        14,
        '1111111111111',
        '',
        ['name' => 'private.key', 'tmp_name' => $keyFile, 'error' => UPLOAD_ERR_OK],
        'pkcs12'
    );
    if (!empty($wrongMode['success'])) {
        $fail('Upload com modo incompatível com a extensão foi aceito.');
    }

    $mismatchedPair = $service->upload(
        ['name' => 'certificate.pem', 'tmp_name' => $certFile, 'error' => UPLOAD_ERR_OK],
        16,
        '1111111111111',
        '',
        ['name' => 'other-private.key', 'tmp_name' => $otherPrivateKeyFile, 'error' => UPLOAD_ERR_OK],
        'pem_pair'
    );
    if (!empty($mismatchedPair['success'])) {
        $fail('Chave privada que não corresponde ao certificado foi aceita.');
    }

    sleep(1);
    $expiredPair = $service->upload(
        ['name' => 'expired-certificate.pem', 'tmp_name' => $expiredCertificateFile, 'error' => UPLOAD_ERR_OK],
        17,
        '1111111111111',
        '',
        ['name' => 'private.key', 'tmp_name' => $keyFile, 'error' => UPLOAD_ERR_OK],
        'pem_pair'
    );
    if (!empty($expiredPair['success'])) {
        $fail('Certificado vencido foi aceito.');
    }

    $chainUpload = $service->upload(
        ['name' => 'certificate-chain.p12', 'tmp_name' => $p12WithChainFile, 'error' => UPLOAD_ERR_OK],
        15,
        '1111111111111',
        'senha-cadeia',
        null,
        'pkcs12'
    );
    if (empty($chainUpload['success'])) {
        $fail('P12 com cadeia intermediária foi rejeitado.');
    }
    $chainPrepared = $service->prepare([
        'certificado_formato' => 'pkcs12',
        'certificado_arquivo' => $chainUpload['filename'],
        'certificado_senha' => $chainUpload['password_encrypted'],
    ]);
    $preparedChainContent = file_get_contents($chainPrepared['certPath']);
    if ($preparedChainContent === false || substr_count($preparedChainContent, 'BEGIN CERTIFICATE') < 2) {
        $fail('A cadeia intermediária do P12 não foi preservada.');
    }
    $service->cleanupPrepared($chainPrepared);

    echo "[OK] Upload, modos, senha vazia, passphrase e cadeia de certificados validados.\n";
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
