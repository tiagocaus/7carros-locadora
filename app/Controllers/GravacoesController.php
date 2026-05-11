<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\FileHelper;
use App\Models\Gravacao;

/**
 * Controller de Gravacoes de Tela
 *
 * Gerencia upload, listagem e exclusao de gravacoes
 */
class GravacoesController
{
    private const UPLOAD_DIR = '/storage/uploads/';
    private const MAX_SIZE = 200 * 1024 * 1024; // 200MB

    /**
     * Lista gravacoes do tenant
     *
     * GET /api/gravacoes
     */
    public function index(Request $request): void
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(50, (int) $request->query('perPage', 10)));

            $model = new Gravacao();
            $gravacoes = $model->listarPaginado($page, $perPage);
            $total = $model->contar();
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            // Adiciona URLs para cada gravacao
            $chave = Auth::chave();
            foreach ($gravacoes as &$gravacao) {
                $gravacao['url'] = $this->generateVideoUrl($gravacao['id']);
                $gravacao['share_url'] = FileHelper::url($gravacao['arquivo'], $chave);
            }
            unset($gravacao);

            Response::json([
                'success' => true,
                'data' => $gravacoes,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'totalPages' => $totalPages,
                    'hasNext' => $page < $totalPages,
                    'hasPrev' => $page > 1
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar gravacoes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recebe upload de gravacao
     *
     * POST /api/gravacoes
     */
    public function store(Request $request): void
    {
        try {
            // Verifica se tem arquivo
            if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
                $errorMsg = $this->getUploadErrorMessage($_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE);
                Response::json([
                    'success' => false,
                    'message' => $errorMsg
                ], 400);
                return;
            }

            $file = $_FILES['video'];

            // Valida tamanho
            if ($file['size'] > self::MAX_SIZE) {
                Response::json([
                    'success' => false,
                    'message' => 'Arquivo muito grande. Maximo permitido: 200MB'
                ], 400);
                return;
            }

            // Valida tipo (webm ou mp4)
            $allowedTypes = ['video/webm', 'video/mp4', 'video/x-matroska'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes, true)) {
                Response::json([
                    'success' => false,
                    'message' => 'Tipo de arquivo nao permitido. Use WebM ou MP4.'
                ], 400);
                return;
            }

            // Determina extensao
            $extension = $mimeType === 'video/mp4' ? 'mp4' : 'webm';

            // Obtém chave do tenant
            $chave = Auth::chave();
            if (!$chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Sessao expirada'
                ], 401);
                return;
            }

            // Cria diretorio se nao existir
            $uploadDir = $this->getUploadDir($chave);
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Gera nome unico
            $filename = 'gravacao_' . date('Ymd_His') . '_' . uniqid() . '.' . $extension;
            $filepath = $uploadDir . $filename;

            // Move arquivo
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao salvar arquivo'
                ], 500);
                return;
            }

            // Formata tamanho
            $sizeFormatted = $this->formatSize($file['size']);

            // Salva no banco
            $model = new Gravacao();
            $id = $model->criar([
                'arquivo' => $filename,
                'size' => $sizeFormatted
            ]);

            if (!$id) {
                // Remove arquivo se falhar no banco
                @unlink($filepath);
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao registrar gravacao'
                ], 500);
                return;
            }

            Response::json([
                'success' => true,
                'message' => 'Gravacao salva com sucesso',
                'data' => [
                    'id' => $id,
                    'arquivo' => $filename,
                    'size' => $sizeFormatted
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao processar gravacao: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Serve arquivo de video para download/visualizacao
     *
     * GET /api/gravacoes/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            $model = new Gravacao();
            $gravacao = $model->buscarPorId($id);

            if (!$gravacao) {
                Response::json([
                    'success' => false,
                    'message' => 'Gravacao nao encontrada'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            $filepath = $this->getUploadDir($chave) . $gravacao['arquivo'];

            if (!file_exists($filepath)) {
                Response::json([
                    'success' => false,
                    'message' => 'Arquivo nao encontrado'
                ], 404);
                return;
            }

            // Determina mime type
            $extension = pathinfo($filepath, PATHINFO_EXTENSION);
            $mimeType = $extension === 'mp4' ? 'video/mp4' : 'video/webm';

            $filesize = filesize($filepath);
            $download = $request->query('download', '0') === '1';

            // Suporte a Range requests (streaming)
            $start = 0;
            $end = $filesize - 1;

            if (isset($_SERVER['HTTP_RANGE'])) {
                $range = $_SERVER['HTTP_RANGE'];
                if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
                    $start = (int) $matches[1];
                    if (!empty($matches[2])) {
                        $end = (int) $matches[2];
                    }
                }

                header('HTTP/1.1 206 Partial Content');
                header("Content-Range: bytes {$start}-{$end}/{$filesize}");
            } else {
                header('HTTP/1.1 200 OK');
            }

            $length = $end - $start + 1;

            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . $length);
            header('Accept-Ranges: bytes');

            if ($download) {
                header('Content-Disposition: attachment; filename="' . $gravacao['arquivo'] . '"');
            } else {
                header('Content-Disposition: inline');
            }

            // Envia arquivo
            $fp = fopen($filepath, 'rb');
            if ($start > 0) {
                fseek($fp, $start);
            }

            $bufferSize = 8192;
            $bytesRemaining = $length;

            while ($bytesRemaining > 0 && !feof($fp)) {
                $readSize = min($bufferSize, $bytesRemaining);
                echo fread($fp, $readSize);
                $bytesRemaining -= $readSize;
                flush();
            }

            fclose($fp);
            exit;
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao servir video: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deleta gravacao
     *
     * DELETE /api/gravacoes/{id}
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            $model = new Gravacao();
            $gravacao = $model->buscarPorId($id);

            if (!$gravacao) {
                Response::json([
                    'success' => false,
                    'message' => 'Gravacao nao encontrada'
                ], 404);
                return;
            }

            // Deleta arquivo fisico
            $chave = Auth::chave();
            $filepath = $this->getUploadDir($chave) . $gravacao['arquivo'];

            if (file_exists($filepath)) {
                @unlink($filepath);
            }

            // Deleta registro do banco
            $model->deletar($id);

            Response::json([
                'success' => true,
                'message' => 'Gravacao excluida com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir gravacao: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtem diretorio de upload do tenant
     */
    private function getUploadDir(string $chave): string
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/..' . self::UPLOAD_DIR . $chave . '/';
    }

    /**
     * Gera URL para o video
     */
    private function generateVideoUrl(int $id): string
    {
        return '/api/gravacoes/' . $id;
    }

    /**
     * Formata tamanho do arquivo
     */
    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2, ',', '.') . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2, ',', '.') . ' KB';
        }
        return $bytes . ' bytes';
    }

    /**
     * Retorna mensagem de erro de upload
     */
    private function getUploadErrorMessage(int $error): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'Arquivo excede o limite do servidor',
            UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o limite do formulario',
            UPLOAD_ERR_PARTIAL => 'Upload incompleto',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo enviado',
            UPLOAD_ERR_NO_TMP_DIR => 'Diretorio temporario nao encontrado',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever arquivo',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensao'
        ];

        return $messages[$error] ?? 'Erro desconhecido no upload';
    }
}
