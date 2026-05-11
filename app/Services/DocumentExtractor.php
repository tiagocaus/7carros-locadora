<?php

namespace App\Services;

use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Service para extração de texto de documentos PDF e DOCX
 *
 * Valida arquivos de upload com múltiplas camadas de segurança
 * e extrai conteúdo convertendo para HTML compatível com TinyMCE.
 */
class DocumentExtractor
{
    /** Tamanho máximo permitido: 10MB */
    private const MAX_SIZE = 10 * 1024 * 1024;

    /** MIME types permitidos */
    private const ALLOWED_MIMES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /** Magic bytes (assinaturas de arquivo) */
    private const MAGIC_BYTES = [
        'pdf' => '%PDF',
        'docx' => "PK\x03\x04",
    ];

    /**
     * Valida arquivo de upload com múltiplas camadas de segurança
     *
     * @param array $file Array do $_FILES
     * @throws \InvalidArgumentException Se arquivo inválido
     */
    public function validate(array $file): void
    {
        // Verificar erro de upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Erro no upload do arquivo');
        }

        // Verificar tamanho
        if ($file['size'] > self::MAX_SIZE) {
            throw new \InvalidArgumentException('Arquivo muito grande (máximo 10MB)');
        }

        // Verificar extensão
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['pdf', 'docx'], true)) {
            throw new \InvalidArgumentException('Apenas arquivos PDF ou DOCX são permitidos');
        }

        // Verificar MIME type real via finfo
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new \InvalidArgumentException('Tipo de arquivo não permitido');
        }

        // Verificar magic bytes
        $handle = fopen($file['tmp_name'], 'rb');
        $bytes = fread($handle, 8);
        fclose($handle);

        $isValid = false;
        foreach (self::MAGIC_BYTES as $type => $signature) {
            if (strpos($bytes, $signature) === 0) {
                $isValid = true;
                break;
            }
        }
        if (!$isValid) {
            throw new \InvalidArgumentException('Arquivo inválido ou corrompido');
        }

        // Para DOCX: verificar estrutura interna e rejeitar macros
        if ($extension === 'docx') {
            $this->validateDocxStructure($file['tmp_name']);
        }
    }

    /**
     * Valida estrutura interna do DOCX e rejeita arquivos com macros
     *
     * @param string $filepath Caminho do arquivo
     * @throws \InvalidArgumentException Se estrutura inválida ou contém macros
     */
    private function validateDocxStructure(string $filepath): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($filepath) !== true) {
            throw new \InvalidArgumentException('Não foi possível abrir o arquivo DOCX');
        }

        // Verificar estrutura válida de DOCX
        if ($zip->locateName('word/document.xml') === false) {
            $zip->close();
            throw new \InvalidArgumentException('Arquivo DOCX inválido');
        }

        // Rejeitar se contiver macros (VBA)
        if ($zip->locateName('word/vbaProject.bin') !== false) {
            $zip->close();
            throw new \InvalidArgumentException('Arquivos com macros não são permitidos por segurança');
        }

        $zip->close();
    }

    /**
     * Extrai conteúdo do arquivo e converte para HTML
     *
     * @param string $filepath Caminho do arquivo temporário
     * @param string $filename Nome original do arquivo
     * @return string HTML extraído
     * @throws \InvalidArgumentException Se formato não suportado
     */
    public function extractToHtml(string $filepath, string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => $this->extractPdfToHtml($filepath),
            'docx' => $this->extractDocxToHtml($filepath),
            default => throw new \InvalidArgumentException('Formato não suportado'),
        };
    }

    /**
     * Extrai texto de PDF e converte para HTML básico
     *
     * @param string $filepath Caminho do arquivo
     * @return string HTML com parágrafos
     */
    private function extractPdfToHtml(string $filepath): string
    {
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($filepath);
            $text = $pdf->getText();
        } catch (\Exception $e) {
            // PDF protegido ou corrompido
            if (stripos($e->getMessage(), 'password') !== false || stripos($e->getMessage(), 'encrypted') !== false) {
                throw new \InvalidArgumentException('PDF protegido por senha não é suportado');
            }
            throw new \InvalidArgumentException('Não foi possível ler o PDF. O arquivo pode estar corrompido.');
        }

        // Se não extraiu texto, provavelmente é um PDF escaneado
        $text = trim($text);
        if (empty($text)) {
            throw new \InvalidArgumentException('Não foi possível extrair texto do PDF. O documento pode ser uma imagem escaneada.');
        }

        // Converter para HTML básico
        // Separar parágrafos por linhas duplas
        $paragraphs = preg_split('/\n\s*\n/', $text);

        $html = '';
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if (!empty($para)) {
                // Escapar HTML e preservar quebras de linha simples
                $para = htmlspecialchars($para, ENT_QUOTES, 'UTF-8');
                $para = nl2br($para);
                $html .= '<p>' . $para . '</p>';
            }
        }

        return $html;
    }

    /**
     * Extrai conteúdo de DOCX preservando formatação como HTML
     *
     * @param string $filepath Caminho do arquivo
     * @return string HTML com formatação
     */
    private function extractDocxToHtml(string $filepath): string
    {
        try {
            $phpWord = WordIOFactory::load($filepath);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Não foi possível ler o arquivo DOCX. O arquivo pode estar corrompido.');
        }

        // Criar writer HTML
        $htmlWriter = WordIOFactory::createWriter($phpWord, 'HTML');

        // Capturar output
        ob_start();
        $htmlWriter->save('php://output');
        $fullHtml = ob_get_clean();

        // Extrair apenas o conteúdo do body
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $fullHtml, $matches)) {
            $bodyContent = $matches[1];
        } else {
            $bodyContent = $fullHtml;
        }

        // Limpar HTML desnecessário
        $bodyContent = $this->cleanHtml($bodyContent);

        if (empty(trim(strip_tags($bodyContent)))) {
            throw new \InvalidArgumentException('O documento está vazio ou não contém texto extraível.');
        }

        return $bodyContent;
    }

    /**
     * Limpa HTML extraído removendo elementos desnecessários
     *
     * @param string $html HTML a limpar
     * @return string HTML limpo
     */
    private function cleanHtml(string $html): string
    {
        // Remover comentários HTML
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        // Remover tags vazias repetidas
        $html = preg_replace('/<(\w+)[^>]*>\s*<\/\1>/i', '', $html);

        // Remover estilos inline excessivos mas manter alguns importantes
        // Manter: font-weight, font-style, text-decoration, text-align
        $html = preg_replace_callback(
            '/style="([^"]*)"/i',
            function ($matches) {
                $style = $matches[1];
                $keepStyles = [];

                // Manter formatação importante
                if (preg_match('/font-weight\s*:\s*bold/i', $style)) {
                    $keepStyles[] = 'font-weight:bold';
                }
                if (preg_match('/font-style\s*:\s*italic/i', $style)) {
                    $keepStyles[] = 'font-style:italic';
                }
                if (preg_match('/text-decoration\s*:\s*underline/i', $style)) {
                    $keepStyles[] = 'text-decoration:underline';
                }
                if (preg_match('/text-align\s*:\s*(left|center|right|justify)/i', $style, $m)) {
                    $keepStyles[] = 'text-align:' . strtolower($m[1]);
                }

                if (empty($keepStyles)) {
                    return '';
                }
                return 'style="' . implode(';', $keepStyles) . '"';
            },
            $html
        );

        // Remover atributos vazios
        $html = preg_replace('/\s+style=""/i', '', $html);

        // Normalizar espaços
        $html = preg_replace('/\s+/', ' ', $html);
        $html = trim($html);

        return $html;
    }
}
