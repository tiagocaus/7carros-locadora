<?php

namespace App\Services;

use App\Helpers\DateHelper;

/**
 * Gerencia uploads de gravacoes em partes sem manter o arquivo inteiro em memoria.
 */
class GravacaoUploadService
{
    public const MAX_SIZE = 200 * 1024 * 1024;
    public const CHUNK_SIZE = 1 * 1024 * 1024;
    public const TEMP_TTL = 86400;

    private const ALLOWED_MIME_TYPES = [
        'video/webm',
        'video/mp4',
        'video/x-matroska',
    ];

    public function iniciar(string $chave, string $mimeType, int $size): array
    {
        $mimeType = $this->normalizarMime($mimeType);
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException('Tipo de arquivo nao permitido. Use WebM ou MP4.');
        }
        if ($size <= 0 || $size > self::MAX_SIZE) {
            throw new \InvalidArgumentException('Arquivo invalido ou maior que 200MB.');
        }

        $uploadId = bin2hex(random_bytes(16));
        $totalChunks = (int) ceil($size / self::CHUNK_SIZE);
        $dir = $this->getTempUploadDir($chave, $uploadId);
        if (!is_dir($dir) && !$this->executarOperacaoArquivo(fn() => mkdir($dir, 0755, true)) && !is_dir($dir)) {
            throw new \RuntimeException('Nao foi possivel preparar o upload.');
        }

        $manifest = [
            'upload_id' => $uploadId,
            'mime_type' => $mimeType,
            'size' => $size,
            'chunk_size' => self::CHUNK_SIZE,
            'total_chunks' => $totalChunks,
            'created_at' => DateHelper::systemNow(),
        ];

        $this->writeJsonAtomic($dir . '/manifest.json', $manifest);
        return $manifest;
    }

    public function salvarParte(string $chave, string $uploadId, int $index, array $file): array
    {
        $manifest = $this->carregarManifesto($chave, $uploadId);
        $totalChunks = (int) $manifest['total_chunks'];
        if ($index < 0 || $index >= $totalChunks) {
            throw new \InvalidArgumentException('Indice de parte invalido.');
        }
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException($this->getUploadErrorMessage($uploadError));
        }
        if (empty($file['tmp_name'])) {
            throw new \InvalidArgumentException('O servidor nao criou o arquivo temporario da parte enviada.');
        }

        $chunkSize = (int) ($manifest['chunk_size'] ?? 0);
        if ($chunkSize <= 0 || $chunkSize > self::MAX_SIZE) {
            throw new \RuntimeException('Tamanho de parte invalido no manifesto do upload.');
        }
        $expectedSize = $index === $totalChunks - 1
            ? (int) $manifest['size'] - ($index * $chunkSize)
            : $chunkSize;
        $actualSize = (int) ($file['size'] ?? 0);
        if ($actualSize !== $expectedSize) {
            throw new \InvalidArgumentException('Tamanho da parte do upload invalido.');
        }

        $dir = $this->getTempUploadDir($chave, $uploadId);
        $target = $dir . '/chunk_' . $index . '.part';
        $storedSize = is_file($target)
            ? $this->executarOperacaoArquivo(fn() => filesize($target))
            : false;
        if ($storedSize === $expectedSize) {
            return ['index' => $index, 'received' => true];
        }

        $temporaryTarget = $target . '.uploading';
        $this->removerArquivoSeExistir($temporaryTarget);
        $moved = $this->executarOperacaoArquivo(
            fn() => move_uploaded_file((string) $file['tmp_name'], $temporaryTarget)
        );
        if (!$moved && is_file((string) $file['tmp_name'])) {
            $moved = $this->executarOperacaoArquivo(
                fn() => rename((string) $file['tmp_name'], $temporaryTarget)
            );
            if (!$moved) {
                $moved = $this->executarOperacaoArquivo(
                    fn() => copy((string) $file['tmp_name'], $temporaryTarget)
                );
            }
        }
        $temporarySize = is_file($temporaryTarget)
            ? $this->executarOperacaoArquivo(fn() => filesize($temporaryTarget))
            : false;
        if (!$moved || $temporarySize !== $expectedSize) {
            $this->removerArquivoSeExistir($temporaryTarget);
            throw new \RuntimeException('Falha ao armazenar parte da gravacao.');
        }
        if (!$this->executarOperacaoArquivo(fn() => rename($temporaryTarget, $target))) {
            $this->removerArquivoSeExistir($temporaryTarget);
            throw new \RuntimeException('Falha ao concluir parte da gravacao.');
        }

        return ['index' => $index, 'received' => true];
    }

    public function finalizar(string $chave, string $uploadId): array
    {
        $manifest = $this->carregarManifesto($chave, $uploadId);
        $tempDir = $this->getTempUploadDir($chave, $uploadId);
        $uploadDir = $this->getFinalUploadDir($chave);
        if (!is_dir($uploadDir) && !$this->executarOperacaoArquivo(fn() => mkdir($uploadDir, 0755, true)) && !is_dir($uploadDir)) {
            throw new \RuntimeException('Nao foi possivel preparar o diretorio da gravacao.');
        }

        $assembling = $uploadDir . '/.gravacao_' . $uploadId . '.assembling';
        $output = $this->executarOperacaoArquivo(fn() => fopen($assembling, 'wb'));
        if ($output === false) {
            throw new \RuntimeException('Nao foi possivel montar a gravacao.');
        }

        try {
            for ($index = 0; $index < (int) $manifest['total_chunks']; $index++) {
                $partPath = $tempDir . '/chunk_' . $index . '.part';
                if (!is_file($partPath)) {
                    throw new \InvalidArgumentException('Upload incompleto. Parte ' . ($index + 1) . ' ausente.');
                }
                $input = $this->executarOperacaoArquivo(fn() => fopen($partPath, 'rb'));
                if ($input === false) {
                    throw new \RuntimeException('Nao foi possivel ler uma parte da gravacao.');
                }
                $copied = $this->executarOperacaoArquivo(fn() => stream_copy_to_stream($input, $output));
                fclose($input);
                if ($copied === false) {
                    throw new \RuntimeException('Falha ao montar uma parte da gravacao.');
                }
            }
        } catch (\Throwable $e) {
            fclose($output);
            $this->removerArquivoSeExistir($assembling);
            throw $e;
        }
        fclose($output);

        $finalSize = is_file($assembling)
            ? (int) $this->executarOperacaoArquivo(fn() => filesize($assembling))
            : 0;
        if ($finalSize !== (int) $manifest['size'] || $finalSize > self::MAX_SIZE) {
            $this->removerArquivoSeExistir($assembling);
            throw new \InvalidArgumentException('Tamanho final da gravacao invalido.');
        }

        $finfo = $this->executarOperacaoArquivo(fn() => finfo_open(FILEINFO_MIME_TYPE));
        $mimeType = $finfo
            ? (string) $this->executarOperacaoArquivo(fn() => finfo_file($finfo, $assembling))
            : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            $this->removerArquivoSeExistir($assembling);
            throw new \InvalidArgumentException('Conteudo da gravacao nao e WebM ou MP4 valido.');
        }

        $extension = $mimeType === 'video/mp4' ? 'mp4' : 'webm';
        $filename = 'gravacao_' . DateHelper::systemNow('Ymd_His') . '_' . uniqid() . '.' . $extension;
        $finalPath = $uploadDir . '/' . $filename;
        if (!$this->executarOperacaoArquivo(fn() => rename($assembling, $finalPath))) {
            $this->removerArquivoSeExistir($assembling);
            throw new \RuntimeException('Nao foi possivel concluir o arquivo da gravacao.');
        }

        $this->removeDirectory($tempDir);
        return [
            'arquivo' => $filename,
            'filepath' => $finalPath,
            'size_bytes' => $finalSize,
            'mime_type' => $mimeType,
        ];
    }

    public function cancelar(string $chave, string $uploadId): void
    {
        $this->validarUploadId($uploadId);
        $this->removeDirectory($this->getTempUploadDir($chave, $uploadId));
    }

    public function limparExpirados(): int
    {
        $base = $this->getTempBaseDir();
        if (!is_dir($base)) {
            return 0;
        }

        $removed = 0;
        foreach (glob($base . '/*/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $manifest = $dir . '/manifest.json';
            $reference = is_file($manifest) ? filemtime($manifest) : filemtime($dir);
            if ($reference !== false && $reference < time() - self::TEMP_TTL) {
                $this->removeDirectory($dir);
                $removed++;
            }
        }
        return $removed;
    }

    private function carregarManifesto(string $chave, string $uploadId): array
    {
        $this->validarUploadId($uploadId);
        $path = $this->getTempUploadDir($chave, $uploadId) . '/manifest.json';
        if (!is_file($path)) {
            throw new \InvalidArgumentException('Upload nao encontrado ou expirado.');
        }
        $contents = $this->executarOperacaoArquivo(fn() => file_get_contents($path));
        $manifest = $contents !== false ? json_decode((string) $contents, true) : null;
        if (!is_array($manifest) || ($manifest['upload_id'] ?? '') !== $uploadId) {
            throw new \RuntimeException('Manifesto de upload invalido.');
        }
        return $manifest;
    }

    private function writeJsonAtomic(string $path, array $data): void
    {
        $temporary = $path . '.tmp';
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $written = $this->executarOperacaoArquivo(fn() => file_put_contents($temporary, $json, LOCK_EX));
        if ($written === false || !$this->executarOperacaoArquivo(fn() => rename($temporary, $path))) {
            $this->removerArquivoSeExistir($temporary);
            throw new \RuntimeException('Nao foi possivel registrar o upload.');
        }
    }

    /**
     * Executa uma operacao de filesystem sem deixar warnings escaparem para o
     * handler global, que responde HTML. O chamador converte falhas em JSON.
     */
    private function executarOperacaoArquivo(callable $operation): mixed
    {
        set_error_handler(static fn() => true);
        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }

    private function removerArquivoSeExistir(string $path): void
    {
        if (is_file($path)) {
            $this->executarOperacaoArquivo(fn() => unlink($path));
        }
    }

    private function normalizarMime(string $mimeType): string
    {
        return strtolower(trim(explode(';', $mimeType, 2)[0]));
    }

    private function getUploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE => 'A parte excedeu o limite de upload configurado no servidor.',
            UPLOAD_ERR_FORM_SIZE => 'A parte excedeu o limite permitido pelo formulario.',
            UPLOAD_ERR_PARTIAL => 'A parte foi recebida apenas parcialmente. Tente novamente.',
            UPLOAD_ERR_NO_FILE => 'Nenhuma parte da gravacao foi recebida pelo servidor.',
            UPLOAD_ERR_NO_TMP_DIR => 'O diretorio temporario de uploads nao esta disponivel no servidor.',
            UPLOAD_ERR_CANT_WRITE => 'O servidor nao conseguiu gravar a parte no disco.',
            UPLOAD_ERR_EXTENSION => 'Uma extensao do servidor interrompeu o envio da parte.',
            default => 'A parte da gravacao nao foi recebida corretamente (erro ' . $error . ').',
        };
    }

    private function validarUploadId(string $uploadId): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $uploadId)) {
            throw new \InvalidArgumentException('Identificador de upload invalido.');
        }
    }

    private function getTempBaseDir(): string
    {
        return $this->rootPath() . '/storage/temp/gravacoes';
    }

    private function getTempUploadDir(string $chave, string $uploadId): string
    {
        $this->validarUploadId($uploadId);
        return $this->getTempBaseDir() . '/' . basename($chave) . '/' . $uploadId;
    }

    private function getFinalUploadDir(string $chave): string
    {
        return $this->rootPath() . '/storage/uploads/' . basename($chave);
    }

    private function rootPath(): string
    {
        return defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : $this->removerArquivoSeExistir($path);
        }
        $this->executarOperacaoArquivo(fn() => rmdir($dir));
    }
}
