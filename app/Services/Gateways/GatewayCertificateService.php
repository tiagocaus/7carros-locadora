<?php

namespace App\Services\Gateways;

/**
 * Gerencia certificados A1 (.pfx/.p12) usados por gateways bancarios.
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
     * @return array<string, mixed>
     */
    public function upload(array $file, int $gatewayId, string $chave, string $senha): array
    {
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['pfx', 'p12'], true)) {
            return ['success' => false, 'message' => 'Formato inválido. Envie um arquivo .pfx ou .p12.'];
        }

        $content = file_get_contents((string) ($file['tmp_name'] ?? ''));
        if ($content === false) {
            return ['success' => false, 'message' => 'Erro ao ler o arquivo enviado.'];
        }

        $validation = $this->validate($content, $senha);
        if (!$validation['success']) {
            return $validation;
        }

        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0700, true);
        }

        $filename = $this->generateFilename($chave, $gatewayId, $ext);
        $path = $this->certificatePath($filename);

        if (file_put_contents($path, $content) === false) {
            return ['success' => false, 'message' => 'Erro ao salvar o certificado.'];
        }

        chmod($path, 0600);

        $data = $this->readData($content, $senha);

        return [
            'success' => true,
            'message' => 'Certificado enviado com sucesso.',
            'filename' => $filename,
            'password_encrypted' => encrypt($senha),
            'data' => $data,
        ];
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

        $certData = openssl_x509_parse($certs['cert']);
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
    public function readData(string $content, string $senha): array
    {
        $certs = [];
        if (!openssl_pkcs12_read($content, $certs, $senha)) {
            return [];
        }

        $certData = openssl_x509_parse($certs['cert']);
        if (!$certData) {
            return [];
        }

        $cn = (string) ($certData['subject']['CN'] ?? '');
        $document = '';
        if (preg_match('/(\d{11}|\d{14})/', $cn, $matches)) {
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
     * Extrai certificado publico e chave privada para arquivos PEM temporarios.
     *
     * @return array{certPath: string, keyPath: string, publicCert: string}
     */
    public function extractPem(string $filename, string $encryptedPassword): array
    {
        $path = $this->certificatePath($filename);
        if (!file_exists($path)) {
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

        return [
            'certPath' => $certPath,
            'keyPath' => $keyPath,
            'publicCert' => $publicCert,
        ];
    }

    public function cleanupPem(string $certPath, string $keyPath): void
    {
        if (file_exists($certPath)) {
            unlink($certPath);
        }
        if (file_exists($keyPath)) {
            unlink($keyPath);
        }
    }

    public function remove(string $filename): bool
    {
        $path = $this->certificatePath($filename);
        if (file_exists($path)) {
            return unlink($path);
        }

        return true;
    }

    private function generateFilename(string $chave, int $gatewayId, string $ext): string
    {
        $timestamp = \App\Helpers\DateHelper::timestamp();

        do {
            $filename = $chave . '_gateway_' . $gatewayId . '_' . $timestamp . '.' . $ext;
            $timestamp++;
        } while (file_exists($this->certificatePath($filename)));

        return $filename;
    }

    private function certificatePath(string $filename): string
    {
        return $this->basePath . '/' . basename($filename);
    }
}
