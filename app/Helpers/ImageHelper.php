<?php

namespace App\Helpers;

use App\Core\Auth;

/**
 * ImageHelper - Processamento universal de arquivos e imagens
 *
 * Converte imagens automaticamente para WebP (30% menor que PNG)
 * PDFs e outros documentos mantêm extensão original
 *
 * @example
 * // Uso simples (padrão: webp, qualidade 80, chave da sessão)
 * $filename = ImageHelper::save($base64, 'assinatura');
 * $filename = ImageHelper::save($base64, 'foto_cliente');
 * $filename = ImageHelper::save($pdfBase64, 'documento');  // mantém .pdf
 *
 * // Com opções
 * $filename = ImageHelper::save($base64, 'logo', format: 'png');
 * $filename = ImageHelper::save($base64, 'foto', quality: 90);
 * $filename = ImageHelper::save($base64, 'img', chave: '1111111111111');
 */
class ImageHelper
{
    private const UPLOAD_DIR = '/storage/uploads/';
    private const MAX_SIZE = 5 * 1024 * 1024; // 5MB
    private const IMAGE_MIMES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
    private const ALLOWED_MIMES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf'];

    /**
     * Salva arquivo (imagens convertidas para WebP, PDFs mantêm extensão)
     *
     * @param string $base64 Dados base64 do arquivo (com ou sem prefixo data:...)
     * @param string $prefix Prefixo do nome (ex: 'assinatura', 'foto', 'documento')
     * @param string $format Formato de saída: 'webp' (padrão), 'png', ou 'original'
     * @param int $quality Qualidade da imagem (0-100), padrão 80
     * @param string|null $chave Chave do tenant (usa sessão se null)
     * @return string|null Nome do arquivo salvo ou null se falhar
     */
    public static function save(
        string $base64,
        string $prefix = 'img',
        string $format = 'webp',
        int $quality = 80,
        ?string $chave = null
    ): ?string {
        // Validar arquivo
        $validation = self::validate($base64);
        if (!$validation['valid']) {
            return null;
        }

        // Decodificar base64
        $data = preg_replace('/^data:[a-z0-9\/+\-]+;base64,/i', '', $base64);
        $fileData = base64_decode($data);
        if ($fileData === false) {
            return null;
        }

        $mime = $validation['mime'];

        // Processar arquivo
        if (self::isImage($mime)) {
            $forceWhiteBackground = self::shouldForceWhiteBackground($prefix);

            // Imagem: converter para formato desejado
            if ($format === 'png') {
                $processedData = self::toPng($fileData, $forceWhiteBackground);
                $extension = 'png';
            } elseif ($format === 'original') {
                $processedData = $forceWhiteBackground ? self::toPng($fileData, true) : $fileData;
                $extension = self::getExtensionFromMime($mime);
                if ($forceWhiteBackground) {
                    $extension = 'png';
                }
            } else {
                // WebP (padrão)
                $processedData = self::toWebP($fileData, $quality, $forceWhiteBackground);
                $extension = 'webp';
            }
        } else {
            // PDF ou outro: manter original
            $processedData = $fileData;
            $extension = self::getExtensionFromMime($mime);
        }

        if ($processedData === null) {
            return null;
        }

        // Obter chave do tenant
        $chave = $chave ?? Auth::chave();
        if (!$chave) {
            return null;
        }

        // Criar diretório
        $uploadDir = APP_ROOT . self::UPLOAD_DIR . $chave . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Gerar nome do arquivo: {prefix}_{uniqid}.{ext}
        $filename = "{$prefix}_" . uniqid() . ".{$extension}";
        $filepath = $uploadDir . $filename;

        // Salvar
        if (file_put_contents($filepath, $processedData) === false) {
            return null;
        }

        return $filename;
    }

    /**
     * Valida arquivo base64
     *
     * @param string $base64 Dados base64
     * @return array ['valid' => bool, 'error' => ?string, 'mime' => ?string]
     */
    public static function validate(string $base64): array
    {
        $result = ['valid' => false, 'error' => null, 'mime' => null];

        // Decodificar
        $data = preg_replace('/^data:[a-z0-9\/+\-]+;base64,/i', '', $base64);
        $fileData = base64_decode($data);

        if ($fileData === false) {
            $result['error'] = 'Base64 inválido';
            return $result;
        }

        // Verificar tamanho
        if (strlen($fileData) > self::MAX_SIZE) {
            $result['error'] = 'Arquivo muito grande (máx 5MB)';
            return $result;
        }

        // Verificar tipo real do arquivo (não confia no header base64)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($fileData);

        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            $result['error'] = 'Tipo de arquivo não permitido';
            return $result;
        }

        $result['valid'] = true;
        $result['mime'] = $mime;
        return $result;
    }

    /**
     * Gera URL pública para o arquivo (delega para FileHelper)
     *
     * @param string|null $filename Nome do arquivo
     * @param string|null $chave Chave do tenant
     * @return string URL pública ou string vazia
     */
    public static function url(?string $filename, ?string $chave = null): string
    {
        return FileHelper::url($filename, $chave);
    }

    /**
     * Deleta arquivo (delega para FileHelper)
     *
     * @param string $filename Nome do arquivo
     * @param string|null $chave Chave do tenant
     * @return bool Sucesso
     */
    public static function delete(string $filename, ?string $chave = null): bool
    {
        return FileHelper::delete($filename, $chave);
    }

    /**
     * Verifica se arquivo existe (delega para FileHelper)
     *
     * @param string $filename Nome do arquivo
     * @param string|null $chave Chave do tenant
     * @return bool
     */
    public static function exists(string $filename, ?string $chave = null): bool
    {
        return FileHelper::exists($filename, $chave);
    }

    /**
     * Converte imagem para WebP
     *
     * @param string $imageData Dados binários da imagem
     * @param int $quality Qualidade (0-100)
     * @param bool $whiteBackground Se true, compoe transparência sobre fundo branco
     * @return string|null Dados WebP ou null se falhar
     */
    private static function toWebP(string $imageData, int $quality = 80, bool $whiteBackground = false): ?string
    {
        $image = self::createImageFromString($imageData);
        if ($image === false) {
            return null;
        }

        if ($whiteBackground) {
            $image = self::flattenOnWhite($image);
        } else {
            // Preservar transparência
            imagesavealpha($image, true);
            imagealphablending($image, true);
        }

        $webpData = self::captureImageOutput(static fn() => imagewebp($image, null, $quality));

        imagedestroy($image);

        return $webpData ?: null;
    }

    /**
     * Converte imagem para PNG
     *
     * @param string $imageData Dados binários da imagem
     * @param bool $whiteBackground Se true, compoe transparência sobre fundo branco
     * @return string|null Dados PNG ou null se falhar
     */
    private static function toPng(string $imageData, bool $whiteBackground = false): ?string
    {
        $image = self::createImageFromString($imageData);
        if ($image === false) {
            return null;
        }

        if ($whiteBackground) {
            $image = self::flattenOnWhite($image);
        } else {
            imagesavealpha($image, true);
        }

        $pngData = self::captureImageOutput(static fn() => imagepng($image, null, 6)); // Compressão média (0-9)

        imagedestroy($image);

        return $pngData ?: null;
    }

    private static function createImageFromString(string $imageData): \GdImage|false
    {
        set_error_handler(static fn() => true);
        try {
            return imagecreatefromstring($imageData);
        } finally {
            restore_error_handler();
        }
    }

    private static function captureImageOutput(callable $callback): ?string
    {
        set_error_handler(static fn() => true);
        ob_start();
        try {
            $success = (bool) $callback();
            $data = ob_get_clean();
        } finally {
            restore_error_handler();
        }

        return $success && $data !== false && $data !== '' ? $data : null;
    }

    /**
     * Verifica se o MIME é de imagem
     */
    private static function isImage(string $mime): bool
    {
        return in_array($mime, self::IMAGE_MIMES, true);
    }

    /**
     * Assinaturas devem sempre ter fundo branco para evitar fundo preto em PDF/JPEG.
     */
    private static function shouldForceWhiteBackground(string $prefix): bool
    {
        return str_starts_with($prefix, 'assinatura');
    }

    /**
     * Compoe uma imagem potencialmente transparente sobre fundo branco.
     *
     * @param \GdImage $image Imagem original
     * @return \GdImage Imagem sem transparencia
     */
    private static function flattenOnWhite(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, false);
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);
        imagedestroy($image);

        return $canvas;
    }

    /**
     * Obtém extensão a partir do MIME type
     */
    private static function getExtensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }
}
