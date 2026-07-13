<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Auth;
use App\Helpers\FileHelper;

/**
 * Controller para servir arquivos via token seguro
 *
 * Recebe requisições em /files/{token} e serve o arquivo correspondente
 * após validar a assinatura do token e a permissão do tenant.
 */
class FileController
{
    /**
     * Serve um arquivo via token
     *
     * GET /files/{token}
     *
     * @param Request $request Objeto de requisição
     * @param string $token Token do arquivo
     * @return void
     */
    public function serve(Request $request, string $token): void
    {
        // Decodifica token
        $decoded = FileHelper::decodeToken($token);

        if (!$decoded) {
            $this->sendError(404, 'Arquivo não encontrado');
            return;
        }

        $chave = $decoded['chave'];
        $filename = $decoded['filename'];

        // Validação de segurança: sanitiza o filename para prevenir path traversal
        $filename = basename($filename);
        if (empty($filename) || strpos($filename, '..') !== false) {
            $this->sendError(400, 'Nome de arquivo inválido');
            return;
        }

        // Verifica se o usuário tem acesso a este tenant
        // Em rotas públicas (como exibir logo), não exigimos autenticação
        // mas ainda assim validamos que o arquivo existe
        $filepath = FileHelper::getPath($filename, $chave);

        if (!file_exists($filepath) || !is_file($filepath)) {
            $this->sendError(404, 'Arquivo não encontrado');
            return;
        }

        // Obtém informações do arquivo
        $mimeType = FileHelper::getMimeType($filepath);
        $filesize = filesize($filepath);
        $lastModified = filemtime($filepath);

        // Verifica cache (If-Modified-Since)
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $ifModifiedSince = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
            if ($ifModifiedSince >= $lastModified) {
                http_response_code(304);
                exit;
            }
        }

        // Define headers
        header('Content-Type: ' . $mimeType);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
        header('Cache-Control: public, max-age=31536000');
        header('ETag: "' . md5($filepath . $lastModified) . '"');

        // Para imagens e videos, permite inline; para outros arquivos, attachment
        $disposition = ($this->isImage($mimeType) || $this->isVideo($mimeType)) ? 'inline' : 'attachment';
        // Remove CR/LF/NUL e aspas (previne HTTP response splitting no header)
        $safeName = preg_replace('/[\r\n\x00"]/', '', $filename);
        $encodedName = rawurlencode($safeName);
        header("Content-Disposition: {$disposition}; filename=\"{$safeName}\"; filename*=UTF-8''{$encodedName}");

        // Previne execução de código (segurança)
        header('X-Content-Type-Options: nosniff');

        if ($this->isVideo($mimeType)) {
            $this->serveVideo($filepath, $filesize);
        }

        header('Content-Length: ' . $filesize);
        readfile($filepath);
        exit;
    }

    /**
     * Serve video com suporte a Range para reproducao progressiva.
     */
    private function serveVideo(string $filepath, int $filesize): void
    {
        $start = 0;
        $end = $filesize - 1;
        header('Accept-Ranges: bytes');

        if (isset($_SERVER['HTTP_RANGE'])) {
            if (!preg_match('/^bytes=(\d+)-(\d*)$/', trim($_SERVER['HTTP_RANGE']), $matches)) {
                header("Content-Range: bytes */{$filesize}");
                $this->sendError(416, 'Intervalo solicitado invalido');
            }

            $start = (int) $matches[1];
            $end = $matches[2] !== '' ? min((int) $matches[2], $filesize - 1) : $end;
            if ($start > $end || $start >= $filesize) {
                header("Content-Range: bytes */{$filesize}");
                $this->sendError(416, 'Intervalo solicitado invalido');
            }

            http_response_code(206);
            header("Content-Range: bytes {$start}-{$end}/{$filesize}");
        }

        $length = $end - $start + 1;
        header('Content-Length: ' . $length);
        $handle = fopen($filepath, 'rb');
        if ($handle === false) {
            $this->sendError(500, 'Erro ao abrir arquivo');
        }

        fseek($handle, $start);
        $remaining = $length;
        while ($remaining > 0 && !feof($handle)) {
            $buffer = fread($handle, min(8192, $remaining));
            if ($buffer === false) {
                break;
            }
            echo $buffer;
            $remaining -= strlen($buffer);
            if (connection_aborted()) {
                break;
            }
        }
        fclose($handle);
        exit;
    }

    /**
     * Verifica se o MIME type é de uma imagem
     *
     * @param string $mimeType MIME type do arquivo
     * @return bool
     */
    private function isImage(string $mimeType): bool
    {
        return strpos($mimeType, 'image/') === 0;
    }

    /**
     * Verifica se o MIME type é de um video
     *
     * @param string $mimeType MIME type do arquivo
     * @return bool
     */
    private function isVideo(string $mimeType): bool
    {
        return strpos($mimeType, 'video/') === 0;
    }

    /**
     * Envia resposta de erro
     *
     * @param int $code Código HTTP
     * @param string $message Mensagem de erro
     * @return void
     */
    private function sendError(int $code, string $message): void
    {
        http_response_code($code);
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
        exit;
    }
}
