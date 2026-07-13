<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\FileHelper;
use App\Models\Gravacao;
use App\Services\GravacaoUploadService;

/**
 * Controller de Gravacoes de Tela
 *
 * Gerencia upload, listagem e exclusao de gravacoes
 */
class GravacoesController
{
    private const UPLOAD_DIR = '/storage/uploads/';
    private const MAX_SIZE = GravacaoUploadService::MAX_SIZE;

    public function iniciarUpload(Request $request): void
    {
        try {
            $chave = Auth::chave();
            if (!$chave) {
                Response::json(['success' => false, 'message' => 'Sessao expirada'], 401);
                return;
            }

            $manifest = (new GravacaoUploadService())->iniciar(
                $chave,
                (string) $request->input('mime_type', ''),
                (int) $request->input('size', 0)
            );

            Response::json(['success' => true, 'data' => $manifest]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Erro ao iniciar upload: ' . $e->getMessage()], 500);
        }
    }

    public function uploadChunk(Request $request, string $uploadId): void
    {
        try {
            $chave = Auth::chave();
            if (!$chave) {
                Response::json(['success' => false, 'message' => 'Sessao expirada'], 401);
                return;
            }
            if (!isset($_FILES['chunk'])) {
                Response::json(['success' => false, 'message' => 'Parte da gravacao nao enviada'], 422);
                return;
            }

            $result = (new GravacaoUploadService())->salvarParte(
                $chave,
                $uploadId,
                (int) $request->input('index', -1),
                $_FILES['chunk']
            );
            Response::json(['success' => true, 'data' => $result]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Erro ao enviar parte: ' . $e->getMessage()], 500);
        }
    }

    public function finalizarUpload(Request $request, string $uploadId): void
    {
        try {
            $chave = Auth::chave();
            if (!$chave) {
                Response::json(['success' => false, 'message' => 'Sessao expirada'], 401);
                return;
            }

            $service = new GravacaoUploadService();
            $file = $service->finalizar($chave, $uploadId);
            $sizeFormatted = $this->formatSize((int) $file['size_bytes']);
            $model = new Gravacao();
            $id = $model->criar([
                'arquivo' => $file['arquivo'],
                'size' => $sizeFormatted,
            ]);
            if (!$id) {
                @unlink($file['filepath']);
                throw new \RuntimeException('Erro ao registrar gravacao');
            }
            $videoUrl = FileHelper::url($file['arquivo'], $chave);

            Response::json([
                'success' => true,
                'message' => 'Gravacao salva com sucesso',
                'data' => [
                    'id' => $id,
                    'arquivo' => $file['arquivo'],
                    'size' => $sizeFormatted,
                    'url' => $videoUrl,
                    'share_url' => $videoUrl,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Erro ao finalizar upload: ' . $e->getMessage()], 500);
        }
    }

    public function cancelarUpload(Request $request, string $uploadId): void
    {
        try {
            $chave = Auth::chave();
            if (!$chave) {
                Response::json(['success' => false, 'message' => 'Sessao expirada'], 401);
                return;
            }
            (new GravacaoUploadService())->cancelar($chave, $uploadId);
            Response::json(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Erro ao cancelar upload'], 500);
        }
    }

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
                $videoUrl = FileHelper::url($gravacao['arquivo'], $chave);
                $gravacao['url'] = $videoUrl;
                $gravacao['share_url'] = $videoUrl;
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
            $filename = 'gravacao_' . \App\Helpers\DateHelper::systemNow('Ymd_His') . '_' . uniqid() . '.' . $extension;
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
     * Deleta gravacao
     *
     * POST /api/gravacoes/{id}/excluir
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
