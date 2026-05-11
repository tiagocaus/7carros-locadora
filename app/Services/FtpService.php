<?php

namespace App\Services;

use phpseclib3\Net\SFTP;

/**
 * Service para upload via FTP (nativo) ou SFTP (phpseclib3)
 */
class FtpService
{
    private string $tipo = 'ftp';
    /** @var resource|\phpseclib3\Net\SFTP|null */
    private $connection = null;
    private string $host;
    private int $porta;
    private string $usuario;
    private string $senha;
    private string $diretorio;

    /**
     * Conecta ao servidor FTP/SFTP
     */
    public function connect(string $host, int $porta, string $usuario, string $senha, string $tipo = 'ftp'): bool
    {
        $this->host = $host;
        $this->porta = $porta;
        $this->usuario = $usuario;
        $this->senha = $senha;
        $this->tipo = $tipo;

        if ($tipo === 'sftp') {
            return $this->connectSftp();
        }

        return $this->connectFtp();
    }

    /**
     * Testa a conexao (conecta, lista diretorio, desconecta)
     */
    public function testConnection(): bool
    {
        if ($this->connection === null) {
            return false;
        }

        if ($this->tipo === 'sftp') {
            /** @var SFTP $sftp */
            $sftp = $this->connection;
            return $sftp->pwd() !== false;
        }

        return ftp_systype($this->connection) !== false;
    }

    /**
     * Upload de um arquivo individual
     */
    public function upload(string $localPath, string $remotePath): bool
    {
        if ($this->connection === null) {
            return false;
        }

        if ($this->tipo === 'sftp') {
            /** @var SFTP $sftp */
            $sftp = $this->connection;
            return $sftp->put($remotePath, $localPath, SFTP::SOURCE_LOCAL_FILE);
        }

        $mode = $this->isBinaryFile($localPath) ? FTP_BINARY : FTP_ASCII;
        return @ftp_put($this->connection, $remotePath, $localPath, $mode);
    }

    /**
     * Upload recursivo de um diretorio
     *
     * @return array{arquivos_enviados: int, erros: array}
     */
    public function uploadDirectory(string $localDir, string $remoteDir): array
    {
        $result = ['arquivos_enviados' => 0, 'erros' => []];

        // Garantir que o diretorio remoto existe
        $this->mkdirRemoto($remoteDir);

        $localDir = rtrim($localDir, '/');
        $remoteDir = rtrim($remoteDir, '/');

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($localDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($localDir) + 1);
            $remotePath = $remoteDir . '/' . $relativePath;

            if ($item->isDir()) {
                $this->mkdirRemoto($remotePath);
            } else {
                if ($this->upload($item->getPathname(), $remotePath)) {
                    $result['arquivos_enviados']++;
                } else {
                    $result['erros'][] = $relativePath;
                }
            }
        }

        return $result;
    }

    /**
     * Deleta arquivo remoto
     */
    public function deleteRemoteFile(string $remotePath): bool
    {
        if ($this->connection === null) {
            return false;
        }

        if ($this->tipo === 'sftp') {
            /** @var SFTP $sftp */
            $sftp = $this->connection;
            return $sftp->delete($remotePath);
        }

        return @ftp_delete($this->connection, $remotePath);
    }

    /**
     * Fecha a conexao
     */
    public function disconnect(): void
    {
        if ($this->connection === null) {
            return;
        }

        if ($this->tipo === 'sftp') {
            /** @var SFTP $sftp */
            $sftp = $this->connection;
            $sftp->disconnect();
        } else {
            @ftp_close($this->connection);
        }

        $this->connection = null;
    }

    // =========================================================================
    // PRIVADOS
    // =========================================================================

    private function connectFtp(): bool
    {
        $conn = @ftp_connect($this->host, $this->porta, 10);
        if (!$conn) {
            return false;
        }

        if (!@ftp_login($conn, $this->usuario, $this->senha)) {
            @ftp_close($conn);
            return false;
        }

        // Modo passivo (mais compativel com firewalls)
        ftp_pasv($conn, true);

        $this->connection = $conn;
        return true;
    }

    private function connectSftp(): bool
    {
        try {
            $sftp = new SFTP($this->host, $this->porta, 10);
            if (!$sftp->login($this->usuario, $this->senha)) {
                return false;
            }
            $this->connection = $sftp;
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function mkdirRemoto(string $path): void
    {
        if ($this->tipo === 'sftp') {
            /** @var SFTP $sftp */
            $sftp = $this->connection;
            $sftp->mkdir($path, -1, true);
            return;
        }

        // FTP: criar diretorio recursivamente
        $parts = explode('/', trim($path, '/'));
        $current = '';
        foreach ($parts as $part) {
            $current .= '/' . $part;
            @ftp_mkdir($this->connection, $current);
        }
    }

    private function isBinaryFile(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $binaryExts = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'svg', 'pdf', 'zip', 'gz'];
        return in_array($ext, $binaryExts, true);
    }
}
