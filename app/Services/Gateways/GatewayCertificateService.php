<?php

namespace App\Services\Gateways;

/**
 * Gerencia certificados digitais enviados para gateways bancarios.
 */
class GatewayCertificateService
{
    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? dirname(__DIR__, 3) . '/storage/certificates/gateways';
    }

    /**
     * @param array<string, mixed> $file
     * @param array<string, mixed>|null $privateKeyFile
     * @return array<string, mixed>
     */
    public function upload(
        array $file,
        int $gatewayId,
        string $chave,
        string $senha = '',
        ?array $privateKeyFile = null
    ): array {
        if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024
            || (int) ($privateKeyFile['size'] ?? 0) > 5 * 1024 * 1024) {
            return ['success' => false, 'message' => 'O certificado e a chave privada devem ter no máximo 5 MB cada.'];
        }

        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

        if (in_array($ext, ['pfx', 'p12'], true)) {
            return $this->uploadPkcs12($file, $gatewayId, $chave, $senha, $ext);
        }

        if (!in_array($ext, ['pem', 'crt', 'cer'], true)) {
            return ['success' => false, 'message' => 'Formato inválido. Envie .pfx, .p12, .pem, .crt ou .cer.'];
        }

        return $this->uploadPemPair($file, $privateKeyFile, $gatewayId, $chave, $senha);
    }

    /**
     * @return array<string, mixed>
     */
    public function validate(string $content, string $senha): array
    {
        $certs = [];
        if (!openssl_pkcs12_read($content, $certs, $senha)) {
            return ['success' => false, 'message' => 'Senha do certificado incorreta ou arquivo inválido.'];
        }

        if (empty($certs['cert']) || empty($certs['pkey'])) {
            return ['success' => false, 'message' => 'Certificado não contém chave pública ou privada.'];
        }

        return $this->validateCertificateData($certs['cert']);
    }

    /**
     * @return array<string, mixed>
     */
    public function readData(string $content, string $senha): array
    {
        $certs = [];
        if (!openssl_pkcs12_read($content, $certs, $senha)) {
            return [];
        }

        return $this->readCertificateData($certs['cert']);
    }

    /**
     * @param array<string, mixed> $credentials
     * @return array{certPath: string, keyPath: string, publicCert: string, temporary: bool}
     */
    public function prepare(array $credentials): array
    {
        $format = strtolower((string) ($credentials['certificado_formato'] ?? 'pkcs12'));
        $filename = (string) ($credentials['certificado_arquivo'] ?? '');

        if ($format === 'pem') {
            $keyFilename = (string) ($credentials['certificado_chave_arquivo'] ?? '');
            if ($filename === '' || $keyFilename === '') {
                throw new \RuntimeException('Certificado PEM ou chave privada não configurados.');
            }

            $certPath = $this->storedPath($filename);
            $keyPath = $this->storedPath($keyFilename);
            if (!is_file($certPath) || !is_file($keyPath)) {
                throw new \RuntimeException('Arquivo do certificado ou chave privada não encontrado.');
            }

            $storedCertificate = file_get_contents($certPath);
            $publicCert = '';
            if ($storedCertificate === false || !openssl_x509_export($storedCertificate, $publicCert)) {
                throw new \RuntimeException('Erro ao ler o certificado público.');
            }

            return [
                'certPath' => $certPath,
                'keyPath' => $keyPath,
                'publicCert' => $publicCert,
                'temporary' => false,
            ];
        }

        return $this->extractPem(
            $filename,
            (string) ($credentials['certificado_senha'] ?? '')
        ) + ['temporary' => true];
    }

    /**
     * @return array{certPath: string, keyPath: string, publicCert: string}
     */
    public function extractPem(string $filename, string $encryptedPassword): array
    {
        $path = $this->storedPath($filename);
        if (!is_file($path)) {
            throw new \RuntimeException('Arquivo do certificado não encontrado.');
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('Erro ao ler o arquivo do certificado.');
        }

        $password = decrypt($encryptedPassword);
        if ($password === null) {
            throw new \RuntimeException('Erro ao descriptografar a senha do certificado.');
        }

        $certs = [];
        if (!openssl_pkcs12_read($content, $certs, $password)) {
            throw new \RuntimeException('Erro ao ler o certificado. Verifique a senha.');
        }

        $publicCert = '';
        if (!openssl_x509_export($certs['cert'], $publicCert) || $publicCert === '') {
            throw new \RuntimeException('Erro ao exportar o certificado público.');
        }

        $privateKey = '';
        if (!openssl_pkey_export($certs['pkey'], $privateKey) || $privateKey === '') {
            throw new \RuntimeException('Erro ao exportar a chave privada do certificado.');
        }

        $certPath = sys_get_temp_dir() . '/gateway_cert_' . uniqid('', true) . '.pem';
        $keyPath = sys_get_temp_dir() . '/gateway_key_' . uniqid('', true) . '.pem';

        if (file_put_contents($certPath, $publicCert) === false || file_put_contents($keyPath, $privateKey) === false) {
            $this->cleanupPem($certPath, $keyPath);
            throw new \RuntimeException('Erro ao criar arquivos temporários do certificado.');
        }
        chmod($certPath, 0600);
        chmod($keyPath, 0600);

        return ['certPath' => $certPath, 'keyPath' => $keyPath, 'publicCert' => $publicCert];
    }

    /**
     * @param array{certPath: string, keyPath: string, publicCert: string, temporary: bool} $prepared
     */
    public function cleanupPrepared(array $prepared): void
    {
        if (!empty($prepared['temporary'])) {
            $this->cleanupPem($prepared['certPath'], $prepared['keyPath']);
        }
    }

    public function cleanupPem(string $certPath, string $keyPath): void
    {
        if (is_file($certPath)) {
            unlink($certPath);
        }
        if (is_file($keyPath)) {
            unlink($keyPath);
        }
    }

    public function remove(string $filename): bool
    {
        if ($filename === '') {
            return true;
        }

        $path = $this->storedPath($filename);
        return !is_file($path) || unlink($path);
    }

    public function storedPath(string $filename): string
    {
        return $this->basePath . '/' . basename($filename);
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    private function uploadPkcs12(array $file, int $gatewayId, string $chave, string $senha, string $ext): array
    {
        if ($senha === '') {
            return ['success' => false, 'message' => 'Senha do certificado é obrigatória para arquivos PFX/P12.'];
        }

        $content = $this->readUploadedFile($file);
        if ($content === null) {
            return ['success' => false, 'message' => 'Erro ao ler o arquivo enviado.'];
        }

        $validation = $this->validate($content, $senha);
        if (!$validation['success']) {
            return $validation;
        }

        $this->ensureDirectory();
        $filename = $this->generateFilename($chave, $gatewayId, 'certificate', $ext);
        if (!$this->writeSecureFile($this->storedPath($filename), $content)) {
            return ['success' => false, 'message' => 'Erro ao salvar o certificado.'];
        }

        return [
            'success' => true,
            'message' => 'Certificado enviado com sucesso.',
            'format' => 'pkcs12',
            'filename' => $filename,
            'key_filename' => null,
            'password_encrypted' => encrypt($senha),
            'data' => $this->readData($content, $senha),
        ];
    }

    /**
     * @param array<string, mixed> $file
     * @param array<string, mixed>|null $privateKeyFile
     * @return array<string, mixed>
     */
    private function uploadPemPair(
        array $file,
        ?array $privateKeyFile,
        int $gatewayId,
        string $chave,
        string $senha
    ): array {
        if ($privateKeyFile === null || ($privateKeyFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Envie a chave privada correspondente ao certificado PEM/CRT/CER.'];
        }

        $certificateContent = $this->readUploadedFile($file);
        $privateKeyContent = $this->readUploadedFile($privateKeyFile);
        if ($certificateContent === null || $privateKeyContent === null) {
            return ['success' => false, 'message' => 'Erro ao ler o certificado ou a chave privada.'];
        }

        $certificatePem = $this->normalizeCertificatePem($certificateContent);
        $certificate = openssl_x509_read($certificatePem);
        $privateKey = openssl_pkey_get_private($privateKeyContent, $senha);
        if ($certificate === false) {
            return ['success' => false, 'message' => 'Certificado PEM/CRT/CER inválido.'];
        }
        if ($privateKey === false) {
            return ['success' => false, 'message' => 'Chave privada inválida ou passphrase incorreta.'];
        }
        if (!openssl_x509_check_private_key($certificate, $privateKey)) {
            return ['success' => false, 'message' => 'A chave privada não corresponde ao certificado enviado.'];
        }

        $validation = $this->validateCertificateData($certificatePem);
        if (!$validation['success']) {
            return $validation;
        }

        $normalizedPrivateKey = '';
        if (!openssl_pkey_export($privateKey, $normalizedPrivateKey)) {
            return ['success' => false, 'message' => 'Não foi possível normalizar a chave privada.'];
        }

        $this->ensureDirectory();
        $filename = $this->generateFilename($chave, $gatewayId, 'certificate', 'pem');
        $keyFilename = $this->generateFilename($chave, $gatewayId, 'private_key', 'key');
        $certPath = $this->storedPath($filename);
        $keyPath = $this->storedPath($keyFilename);

        $combinedPem = rtrim($certificatePem) . "\n" . $normalizedPrivateKey;
        if (!$this->writeSecureFile($certPath, $combinedPem) || !$this->writeSecureFile($keyPath, $normalizedPrivateKey)) {
            $this->remove($filename);
            $this->remove($keyFilename);
            return ['success' => false, 'message' => 'Erro ao salvar o certificado ou a chave privada.'];
        }

        return [
            'success' => true,
            'message' => 'Certificado enviado com sucesso.',
            'format' => 'pem',
            'filename' => $filename,
            'key_filename' => $keyFilename,
            'password_encrypted' => encrypt(''),
            'data' => $this->readCertificateData($certificatePem),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCertificateData(string $certificate): array
    {
        $certData = openssl_x509_parse($certificate);
        if (!$certData) {
            return ['success' => false, 'message' => 'Não foi possível ler os dados do certificado.'];
        }

        if ((int) ($certData['validTo_time_t'] ?? 0) < \App\Helpers\DateHelper::timestamp()) {
            return ['success' => false, 'message' => 'O certificado digital está vencido.'];
        }

        return ['success' => true];
    }

    /**
     * @return array<string, mixed>
     */
    private function readCertificateData(string $certificate): array
    {
        $certData = openssl_x509_parse($certificate);
        if (!$certData) {
            return [];
        }

        $cn = (string) ($certData['subject']['CN'] ?? '');
        $document = '';
        // CNPJ precisa vir antes de CPF; caso contrario a alternativa de 11
        // digitos captura apenas o prefixo de um CNPJ valido.
        if (preg_match('/(\d{14}|\d{11})/', $cn, $matches)) {
            $document = $matches[1];
        }

        return [
            'documento' => $document,
            'razao_social' => str_contains($cn, ':') ? trim(explode(':', $cn)[0]) : $cn,
            'valido_de' => \App\Helpers\DateHelper::formatTimestamp((int) ($certData['validFrom_time_t'] ?? 0), 'Y-m-d'),
            'valido_ate' => \App\Helpers\DateHelper::formatTimestamp((int) ($certData['validTo_time_t'] ?? 0), 'Y-m-d'),
            'emissor' => $certData['issuer']['CN'] ?? '',
            'serial' => $certData['serialNumberHex'] ?? '',
            'subject' => $cn,
        ];
    }

    /**
     * @param array<string, mixed> $file
     */
    private function readUploadedFile(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return null;
        }

        $content = file_get_contents((string) ($file['tmp_name'] ?? ''));
        return $content === false || $content === '' ? null : $content;
    }

    private function normalizeCertificatePem(string $content): string
    {
        if (str_contains($content, '-----BEGIN CERTIFICATE-----')) {
            return $content;
        }

        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split(base64_encode($content), 64, "\n")
            . "-----END CERTIFICATE-----\n";
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0700, true);
        }
    }

    private function writeSecureFile(string $path, string $content): bool
    {
        if (file_put_contents($path, $content) === false) {
            return false;
        }

        chmod($path, 0600);
        return true;
    }

    private function generateFilename(string $chave, int $gatewayId, string $role, string $ext): string
    {
        $timestamp = \App\Helpers\DateHelper::timestamp();
        do {
            $filename = $chave . '_gateway_' . $gatewayId . '_' . $role . '_' . $timestamp . '.' . $ext;
            $timestamp++;
        } while (is_file($this->storedPath($filename)));

        return $filename;
    }
}
