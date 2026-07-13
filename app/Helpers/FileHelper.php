<?php

namespace App\Helpers;

use App\Core\Auth;
use App\Core\Database;

/**
 * FileHelper - Gerenciador centralizado de uploads de arquivos
 *
 * Responsável por:
 * - Salvar arquivos base64
 * - Gerar URLs públicas com tokens seguros
 * - Deletar arquivos
 * - Servir arquivos com validação de tenant
 */
class FileHelper
{
    /**
     * Diretório base para uploads
     */
    private const UPLOAD_DIR = '/storage/uploads/';

    /**
     * Salva um arquivo base64
     *
     * @param string $base64 Dados base64 do arquivo (com ou sem prefixo data:...)
     * @param string $prefix Prefixo para o nome do arquivo (ex: 'logo', 'foto', 'documento')
     * @return string|null Nome do arquivo salvo ou null se falhar
     */
    public static function save(string $base64, string $prefix = 'file'): ?string
    {
        // Detecta tipo de arquivo
        $extension = self::detectExtension($base64);
        if (!$extension) {
            return null;
        }

        // Remove prefixo data URL se existir
        $data = preg_replace('/^data:[a-z0-9\/+\-]+;base64,/i', '', $base64);
        $data = base64_decode($data);

        if ($data === false) {
            return null;
        }

        // Obtém chave do tenant
        $chave = Auth::chave();
        if (!$chave) {
            return null;
        }

        // Cria diretório se não existir
        $uploadDir = self::getUploadDir($chave);
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Gera nome único
        $filename = $prefix . '_' . uniqid() . '.' . $extension;
        $filepath = $uploadDir . $filename;

        // Salva arquivo
        if (file_put_contents($filepath, $data) === false) {
            return null;
        }

        return $filename;
    }

    /**
     * Gera URL pública com token seguro
     *
     * @param string|null $filename Nome do arquivo
     * @param string|null $chave Chave do tenant (usa Auth::chave() se não fornecido)
     * @return string URL pública (/files/{token}) ou string vazia se não houver arquivo
     */
    public static function url(?string $filename, ?string $chave = null): string
    {
        if (empty($filename)) {
            return '';
        }

        $chave = $chave ?? Auth::chave();
        if (!$chave) {
            return '';
        }

        $token = self::generateToken($filename, $chave);
        return '/files/' . $token;
    }

    /**
     * Deleta um arquivo
     *
     * @param string $filename Nome do arquivo
     * @param string|null $chave Chave do tenant (usa Auth::chave() se não fornecido)
     * @return bool True se deletou com sucesso
     */
    public static function delete(string $filename, ?string $chave = null): bool
    {
        $chave = $chave ?? Auth::chave();
        if (!$chave) {
            return false;
        }

        $filepath = self::getPath($filename, $chave);

        if (file_exists($filepath)) {
            return @unlink($filepath);
        }

        return false;
    }

    /**
     * Gera token seguro para URL do arquivo
     *
     * O token contém:
     * - Payload base64: chave|filename
     * - Assinatura HMAC (16 primeiros caracteres)
     *
     * @param string $filename Nome do arquivo
     * @param string $chave Chave do tenant
     * @return string Token único
     */
    public static function generateToken(string $filename, string $chave): string
    {
        $secret = self::appSecret();
        $payload = $chave . '|' . $filename;
        $signature = hash_hmac('sha256', $payload, $secret);

        // Token = base64url(payload) + assinatura (16 chars)
        $encoded = self::base64urlEncode($payload);
        return $encoded . substr($signature, 0, 16);
    }

    /**
     * Decodifica token para obter informações do arquivo
     *
     * @param string $token Token da URL
     * @return array|null ['chave' => string, 'filename' => string] ou null se inválido
     */
    public static function decodeToken(string $token): ?array
    {
        if (strlen($token) < 20) {
            return null;
        }

        $signature = substr($token, -16);
        $encoded = substr($token, 0, -16);
        $payload = self::base64urlDecode($encoded);

        if (!$payload || strpos($payload, '|') === false) {
            return null;
        }

        [$chave, $filename] = explode('|', $payload, 2);

        // Valida assinatura
        $secret = self::appSecret();
        $expectedSig = substr(hash_hmac('sha256', $payload, $secret), 0, 16);

        if (!hash_equals($expectedSig, $signature)) {
            return null;
        }

        return [
            'chave' => $chave,
            'filename' => $filename
        ];
    }

    /**
     * Verifica se arquivo existe
     *
     * @param string $filename Nome do arquivo
     * @param string|null $chave Chave do tenant
     * @return bool
     */
    public static function exists(string $filename, ?string $chave = null): bool
    {
        $chave = $chave ?? Auth::chave();
        if (!$chave) {
            return false;
        }

        return file_exists(self::getPath($filename, $chave));
    }

    /**
     * Obtém caminho completo do arquivo
     *
     * @param string $filename Nome do arquivo
     * @param string|null $chave Chave do tenant
     * @return string Caminho absoluto
     */
    public static function getPath(string $filename, ?string $chave = null): string
    {
        $chave = $chave ?? Auth::chave();
        return self::getUploadDir($chave) . $filename;
    }

    /**
     * Obtém MIME type do arquivo
     *
     * @param string $filepath Caminho completo do arquivo
     * @return string MIME type
     */
    public static function getMimeType(string $filepath): string
    {
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        $extensionMimeTypes = [
            'webm' => 'video/webm',
            'mkv' => 'video/x-matroska',
            'mp4' => 'video/mp4',
        ];

        // Tenta usar finfo
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filepath);
            finfo_close($finfo);
            if ($mimeType && $mimeType !== 'application/octet-stream') {
                return $mimeType;
            }
        }

        // Fallback por extensão
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'zip' => 'application/zip',
            ...$extensionMimeTypes,
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Obtém diretório de upload para um tenant
     *
     * @param string $chave Chave do tenant
     * @return string Caminho absoluto do diretório
     */
    private static function getUploadDir(string $chave): string
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/..' . self::UPLOAD_DIR . $chave . '/';
    }

    /**
     * Detecta extensão do arquivo a partir do base64
     *
     * @param string $base64 Dados base64
     * @return string|null Extensão do arquivo ou null se não detectado
     */
    private static function detectExtension(string $base64): ?string
    {
        // Tenta detectar pelo prefixo data URL
        if (preg_match('/^data:([a-z0-9\/+\-]+);base64,/i', $base64, $matches)) {
            $mimeType = strtolower($matches[1]);
            $extensions = [
                'image/jpeg' => 'jpg',
                'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'image/svg+xml' => 'svg',
                'application/pdf' => 'pdf',
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/vnd.ms-excel' => 'xls',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                'text/plain' => 'txt',
                'text/csv' => 'csv',
                'application/zip' => 'zip',
            ];

            return $extensions[$mimeType] ?? null;
        }

        // Tenta detectar pelos magic bytes
        $data = base64_decode(substr($base64, 0, 100));
        if ($data === false) {
            return null;
        }

        // Magic bytes comuns
        $signatures = [
            "\xFF\xD8\xFF" => 'jpg',
            "\x89PNG\r\n\x1a\n" => 'png',
            "GIF87a" => 'gif',
            "GIF89a" => 'gif',
            "RIFF" => 'webp', // WebP começa com RIFF
            "%PDF" => 'pdf',
            "PK\x03\x04" => 'zip', // Também usado por docx, xlsx
        ];

        foreach ($signatures as $signature => $ext) {
            if (strpos($data, $signature) === 0) {
                return $ext;
            }
        }

        return null;
    }

    /**
     * Obtem APP_KEY para assinatura HMAC.
     * Falha explicitamente se nao configurado (evita tokens forjaveis via default publico).
     */
    private static function appSecret(): string
    {
        $secret = Database::env('APP_KEY', '');
        if ($secret === '' || $secret === 'default_secret_key_change_me') {
            throw new \RuntimeException('APP_KEY nao configurado: defina APP_KEY no .env antes de gerar URLs de arquivo.');
        }
        return $secret;
    }

    /**
     * Codifica para base64url (URL-safe)
     *
     * @param string $data Dados para codificar
     * @return string Base64url encoded
     */
    private static function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decodifica base64url
     *
     * @param string $data Dados base64url encoded
     * @return string|false Dados decodificados ou false se falhar
     */
    private static function base64urlDecode(string $data): string|false
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
