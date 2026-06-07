<?php

namespace App\Helpers;

use Mpdf\Mpdf;
use App\Helpers\FileHelper;

/**
 * PdfHelper - Gerenciador centralizado para geração de PDFs
 *
 * Responsável por:
 * - Criar instâncias mPDF com configurações padrão
 * - Adicionar watermark lateral automática "Sistema 7Carros.com.br"
 * - Garantir consistência visual em todos os PDFs do sistema
 *
 * @example
 * // Uso básico
 * $mpdf = PdfHelper::create();
 * PdfHelper::writeHtml($mpdf, $html);
 * $mpdf->Output('documento.pdf', 'I');
 *
 * @example
 * // Com opções customizadas
 * $mpdf = PdfHelper::create([
 *     'format' => 'A5',
 *     'orientation' => 'L'
 * ]);
 *
 * @example
 * // Sem watermark (casos especiais)
 * $mpdf = PdfHelper::create(['watermark' => false]);
 */
class PdfHelper
{
    /**
     * Margens do corpo (mm) quando o PDF usa SetHTMLHeader / SetHTMLFooter para documentos
     * personalizados. Devem reservar a área onde cabeçalho e rodapé são pintados;
     * valores apenas em @page CSS não bastam — WriteHTML pode restaurar tMargin/bMargin para orig_tMargin.
     *
     * Cabeçalho multas/contratos/locações: mesmo layout (~logo + dados); margem superior única.
     * Rodapé: contratos/locações (assinaturas) usa DOCUMENTO_HTML_FOOTER_MARGIN_BOTTOM_MM;
     * multas (só numeração) usa DOCUMENTO_MULTAS_HTML_FOOTER_MARGIN_BOTTOM_MM.
     *
     * @see https://mpdf.github.io/headers-footers/method-2.html
     */
    public const DOCUMENTO_HTML_HEADER_MARGIN_TOP_MM = 35;
    public const DOCUMENTO_HTML_FOOTER_MARGIN_BOTTOM_MM = 65;
    public const DOCUMENTO_MULTAS_HTML_FOOTER_MARGIN_BOTTOM_MM = 20;

    /**
     * Texto padrão da watermark lateral (fallback se i18n não disponível)
     */
    private const WATERMARK_TEXT_FALLBACK = 'Sistema 7Carros.com.br';

    /**
     * Chave de tradução para a watermark
     */
    private const WATERMARK_TRANSLATION_KEY = 'common.pdf.watermark';

    /**
     * Tamanho maximo (bytes) de cada chunk enviado ao WriteHTML do mPDF.
     * Deve ficar abaixo do pcre.backtrack_limit padrao (1.000.000).
     */
    private const WRITE_HTML_CHUNK_SIZE = 500000;

    /**
     * Configurações padrão do mPDF
     */
    private const DEFAULT_OPTIONS = [
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 5,
        'margin_bottom' => 5,
        'default_font' => 'Arial'
    ];

    /**
     * Cria uma instância do mPDF com configurações padrão e watermark
     *
     * @param array $options Opções para sobrescrever os padrões
     *                       - Todas as opções do mPDF são aceitas
     *                       - 'watermark' => false para desabilitar a watermark
     *                       - 'watermark_text' => 'Texto' para customizar o texto
     * @return Mpdf Instância configurada do mPDF
     */
    public static function create(array $options = []): Mpdf
    {
        // Extrair opções customizadas que não são do mPDF
        $showWatermark = $options['watermark'] ?? true;
        $watermarkText = $options['watermark_text'] ?? self::getWatermarkText();
        unset($options['watermark'], $options['watermark_text']);

        // Extrair propriedades de instância (setadas apos construcao)
        $autoTopMargin = $options['setAutoTopMargin'] ?? null;
        $autoBottomMargin = $options['setAutoBottomMargin'] ?? null;
        $autoMarginPadding = $options['autoMarginPadding'] ?? null;
        unset($options['setAutoTopMargin'], $options['setAutoBottomMargin'], $options['autoMarginPadding']);

        // Mesclar opções com padrões
        $mpdfOptions = array_merge(self::DEFAULT_OPTIONS, $options);
        $isLandscape = self::isLandscapeOrientation($mpdfOptions);

        // Criar instância do mPDF
        $mpdf = new Mpdf($mpdfOptions);

        // Aplicar propriedades de auto-margin (header/footer auto-stretch)
        // Ref: https://mpdf.github.io/reference/mpdf-variables/setautotopmargin.html
        if ($autoTopMargin !== null) {
            $mpdf->setAutoTopMargin = $autoTopMargin;
        }
        if ($autoBottomMargin !== null) {
            $mpdf->setAutoBottomMargin = $autoBottomMargin;
        }
        if ($autoMarginPadding !== null) {
            $mpdf->autoMarginPadding = $autoMarginPadding;
        }

        // Adicionar watermark se habilitada
        if ($showWatermark) {
            self::addWatermark($mpdf, $watermarkText, $isLandscape);
        }

        return $mpdf;
    }

    /**
     * Obtém o texto da watermark traduzido para o idioma atual
     *
     * @return string Texto traduzido ou fallback
     */
    private static function getWatermarkText(): string
    {
        // Usa função t() se disponível (sistema i18n)
        if (function_exists('t')) {
            $translated = t(self::WATERMARK_TRANSLATION_KEY);
            // Se retornou a própria chave, usa fallback
            if ($translated !== self::WATERMARK_TRANSLATION_KEY) {
                return $translated;
            }
        }

        return self::WATERMARK_TEXT_FALLBACK;
    }

    /**
     * Detecta a orientação configurada para ajustar elementos fixos da página.
     *
     * @param array $options Opções finais usadas na criação do mPDF
     * @return bool True quando o PDF está em modo horizontal
     */
    private static function isLandscapeOrientation(array $options): bool
    {
        if (strtoupper((string) ($options['orientation'] ?? 'P')) === 'L') {
            return true;
        }

        $format = $options['format'] ?? self::DEFAULT_OPTIONS['format'];
        if (is_string($format)) {
            return str_ends_with(strtoupper($format), '-L');
        }

        if (is_array($format) && isset($format[0], $format[1])) {
            return (float) $format[0] > (float) $format[1];
        }

        return false;
    }

    /**
     * Adiciona a watermark lateral rotacionada ao PDF
     *
     * O texto aparece rotacionado 90 graus na lateral esquerda,
     * um pouco abaixo do centro da página, em todas as páginas.
     *
     * Nota: mPDF usa propriedade 'rotate: -90' (não CSS transform)
     *
     * @param Mpdf $mpdf Instância do mPDF
     * @param string $text Texto da watermark
     * @param bool $isLandscape Se true, usa posicionamento otimizado para PDF horizontal
     */
    private static function addWatermark(Mpdf $mpdf, string $text, bool $isLandscape = false): void
    {
        $top = $isLandscape ? '85mm' : '130mm';

        // mPDF usa 'rotate: -90' (propriedade específica, NÃO CSS transform)
        // position: fixed faz aparecer em TODAS as páginas
        // left: -2mm posiciona na borda esquerda da página
        // top varia por orientação: A4 vertical = 297mm, A4 horizontal = 210mm
        // width/height são definidos ANTES da rotação
        $watermarkHtml = '
        <div style="position: fixed; left: -4mm; top: ' . $top . '; width: 100mm; height: 10mm; rotate: -90;">
            <span style="font-size: 8pt; color: #525252; font-family: Arial, sans-serif; white-space: nowrap;">
                ' . htmlspecialchars($text) . '
            </span>
        </div>';

        // Escrever watermark (position: fixed garante repetição em todas as páginas)
        $mpdf->WriteHTML($watermarkHtml);
    }

    /**
     * Escreve HTML no mPDF com protecao contra limite pcre.backtrack_limit.
     *
     * HTML grande (documentos TinyMCE, checklists extensos) excede o limite padrao
     * do PHP e dispara MpdfException. Este metodo aumenta os limites PCRE e, se
     * necessario, divide o HTML em chunks nos fechamentos de tag.
     *
     * @see https://mpdf.github.io/troubleshooting/known-issues.html
     */
    public static function writeHtml(Mpdf $mpdf, string $html): void
    {
        if (function_exists('ini_set')) {
            @ini_set('pcre.backtrack_limit', '10000000');
            @ini_set('pcre.recursion_limit', '10000000');
        }

        if (strlen($html) <= self::WRITE_HTML_CHUNK_SIZE) {
            $mpdf->WriteHTML($html);
            return;
        }

        foreach (self::splitHtmlChunks($html) as $chunk) {
            $mpdf->WriteHTML($chunk);
        }
    }

    /**
     * Divide HTML em segmentos validos respeitando fechamentos de tag.
     *
     * @return list<string>
     */
    private static function splitHtmlChunks(string $html, int $maxLength = self::WRITE_HTML_CHUNK_SIZE): array
    {
        $chunks = [];
        $offset = 0;
        $length = strlen($html);

        while ($offset < $length) {
            $remaining = $length - $offset;
            if ($remaining <= $maxLength) {
                $chunks[] = substr($html, $offset);
                break;
            }

            $cutAt = self::findSafeHtmlCutPosition($html, $offset, $maxLength);
            $chunks[] = substr($html, $offset, $cutAt);
            $offset += $cutAt;
        }

        return $chunks;
    }

    /**
     * Encontra ponto seguro para cortar HTML (ultimo fechamento de tag no segmento).
     */
    private static function findSafeHtmlCutPosition(string $html, int $offset, int $maxLength): int
    {
        $cutPosition = $offset + $maxLength;
        if (self::isInsideHtmlTag($html, $cutPosition)) {
            $tagEnd = strpos($html, '>', $cutPosition);
            if ($tagEnd !== false) {
                return $tagEnd + 1 - $offset;
            }

            return strlen($html) - $offset;
        }

        $segment = substr($html, $offset, $maxLength);
        $tagStart = strrpos($segment, '</');
        if ($tagStart !== false) {
            $tagEnd = strpos($segment, '>', $tagStart);
            if ($tagEnd !== false) {
                return $tagEnd + 1;
            }
        }

        $genericTagEnd = strrpos($segment, '>');
        if ($genericTagEnd !== false) {
            return $genericTagEnd + 1;
        }

        return $maxLength;
    }

    /**
     * Verifica se uma posicao absoluta do HTML esta dentro de uma tag aberta.
     */
    private static function isInsideHtmlTag(string $html, int $position): bool
    {
        $before = substr($html, 0, $position);
        $lastOpen = strrpos($before, '<');
        if ($lastOpen === false) {
            return false;
        }

        $lastClose = strrpos($before, '>');
        return $lastClose === false || $lastOpen > $lastClose;
    }

    /**
     * Cria PDF e retorna como string
     *
     * @param string $html Conteúdo HTML do PDF
     * @param array $options Opções do mPDF
     * @return string Conteúdo binário do PDF
     */
    public static function generateAsString(string $html, array $options = []): string
    {
        $mpdf = self::create($options);
        self::writeHtml($mpdf, $html);
        return $mpdf->Output('', 'S');
    }

    /**
     * Cria PDF e envia para o navegador (inline)
     *
     * @param string $html Conteúdo HTML do PDF
     * @param string $filename Nome do arquivo
     * @param array $options Opções do mPDF
     */
    public static function outputInline(string $html, string $filename, array $options = []): void
    {
        $mpdf = self::create($options);
        self::writeHtml($mpdf, $html);
        $mpdf->Output($filename, 'I');
    }

    /**
     * Cria PDF e força download
     *
     * @param string $html Conteúdo HTML do PDF
     * @param string $filename Nome do arquivo
     * @param array $options Opções do mPDF
     */
    public static function outputDownload(string $html, string $filename, array $options = []): void
    {
        $mpdf = self::create($options);
        self::writeHtml($mpdf, $html);
        $mpdf->Output($filename, 'D');
    }

    /**
     * Cria PDF e salva em arquivo
     *
     * @param string $html Conteúdo HTML do PDF
     * @param string $filepath Caminho completo do arquivo
     * @param array $options Opções do mPDF
     * @return bool True se salvou com sucesso
     */
    public static function saveToFile(string $html, string $filepath, array $options = []): bool
    {
        try {
            $mpdf = self::create($options);
            self::writeHtml($mpdf, $html);
            $mpdf->Output($filepath, 'F');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ==================== IMAGENS PARA PDF ====================

    /** @var array<int,string> Arquivos JPEG temporarios criados a partir de WebP */
    private static array $tmpImageFiles = [];

    /** @var bool Indica se ja registramos a funcao de cleanup no shutdown */
    private static bool $cleanupRegistered = false;

    /**
     * Resolve o caminho absoluto de uma imagem para uso em mPDF.
     *
     * Comportamento:
     * - Se filename vazio ou arquivo nao existe: retorna ''
     * - Se imagem NAO e WebP: retorna o path local direto (mPDF lida bem com JPEG/PNG)
     * - Se for WebP: converte para JPEG temporario (mPDF e ~90x mais lento processando WebP)
     *   e auto-agenda cleanup do arquivo temp ao final do request
     *
     * Cache: o JPEG temp e reusado se ja existe e e mais recente que o WebP de origem.
     *
     * NUNCA passe URL HTTP (ex: FileHelper::url()) para mPDF — sempre path local.
     *
     * @param string|null $filename Nome do arquivo (ex: "logo_xxx.webp")
     * @param string $chave Chave do tenant
     * @return string Path absoluto pronto para uso em <img src="..."> ou string vazia
     */
    public static function resolveImagePath(?string $filename, string $chave): string
    {
        if (empty($filename)) {
            return '';
        }

        $path = FileHelper::getPath($filename, $chave);
        if (!file_exists($path)) {
            return '';
        }

        $mime = @mime_content_type($path);
        if ($mime !== 'image/webp') {
            return $path;
        }

        $jpegPath = sys_get_temp_dir() . '/pdfimg_white_' . md5($path) . '.jpg';

        // Cache: reusa JPEG se ja existe e e mais recente que o WebP original
        $needConvert = !file_exists($jpegPath) || filemtime($jpegPath) < filemtime($path);
        if ($needConvert) {
            $img = @imagecreatefromwebp($path);
            if (!$img) {
                // Fallback: deixa o mPDF lidar com o WebP cru (mais lento mas funciona)
                return $path;
            }
            $img = self::flattenImageOnWhite($img);
            imagejpeg($img, $jpegPath, 85);
            imagedestroy($img);
        }

        self::registerTmpImageFile($jpegPath);
        return $jpegPath;
    }

    /**
     * Compoe imagem potencialmente transparente sobre fundo branco para JPEG/PDF.
     */
    private static function flattenImageOnWhite(\GdImage $img): \GdImage
    {
        $width = imagesx($img);
        $height = imagesy($img);
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, false);
        imagecopy($canvas, $img, 0, 0, 0, 0, $width, $height);
        imagedestroy($img);

        return $canvas;
    }

    /**
     * Registra um arquivo temp para limpeza automatica ao final do request.
     */
    private static function registerTmpImageFile(string $path): void
    {
        self::$tmpImageFiles[] = $path;
        if (!self::$cleanupRegistered) {
            register_shutdown_function([self::class, 'cleanupTmpImageFiles']);
            self::$cleanupRegistered = true;
        }
    }

    /**
     * Apaga todos os arquivos temp gerados por resolveImagePath() neste request.
     * Invocada automaticamente via shutdown function. Pode ser chamada manualmente
     * em testes ou cenarios especiais.
     */
    public static function cleanupTmpImageFiles(): void
    {
        foreach (self::$tmpImageFiles as $f) {
            if (file_exists($f)) {
                @unlink($f);
            }
        }
        self::$tmpImageFiles = [];
    }
}
