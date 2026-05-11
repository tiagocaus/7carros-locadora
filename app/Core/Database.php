<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * Gerenciamento de Conexões com Banco de Dados
 *
 * Implementa o padrão Singleton para conexões PDO
 */
class Database
{
    private static ?PDO $connection = null;
    private static array $config = [];

    /**
     * Carrega as configurações do .env
     */
    private static function loadConfig(): void
    {
        if (!empty(self::$config)) {
            return;
        }

        // Determina qual arquivo .env usar
        $envFile = __DIR__ . '/../../.env.' . ($_ENV['APP_ENV'] ?? 'development');

        if (!file_exists($envFile)) {
            $envFile = __DIR__ . '/../../.env.development';
        }

        if (!file_exists($envFile)) {
            $envFile = __DIR__ . '/../../.env.production';
        }

        if (!file_exists($envFile)) {
            throw new \RuntimeException('Arquivo .env não encontrado');
        }

        // Parse do arquivo .env
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Ignora comentários
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            // Parse da linha KEY=VALUE
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove aspas do valor
                $value = trim($value, '"\'');

                self::$config[$key] = $value;
            }
        }
    }

    /**
     * Obtém um valor de configuração
     */
    public static function env(string $key, mixed $default = null): mixed
    {
        self::loadConfig();
        return self::$config[$key] ?? $default;
    }

    /**
     * Obtém a conexão PDO (Singleton)
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            self::connect();
        }

        return self::$connection;
    }

    /**
     * Estabelece a conexão com o banco de dados
     */
    private static function connect(): void
    {
        self::loadConfig();

        $driver = self::env('DB_DRIVER', 'mysql');
        $host = self::env('DB_HOST', 'localhost');
        $port = self::env('DB_PORT', '3306');
        $database = self::env('DB_DATABASE');
        $username = self::env('DB_USERNAME');
        $password = self::env('DB_PASSWORD');
        $charset = self::env('DB_CHARSET', 'utf8mb4');

        try {
            $dsn = "$driver:host=$host;port=$port;dbname=$database;charset=$charset";

            self::$connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $charset COLLATE {$charset}_unicode_ci"
            ]);
        } catch (PDOException $e) {
            // Em produção, não expor detalhes do erro
            if (self::env('APP_ENV') === 'production') {
                throw new \RuntimeException('Erro ao conectar com o banco de dados');
            }

            throw new \RuntimeException('Erro ao conectar com o banco: ' . $e->getMessage());
        }
    }

    /**
     * Fecha a conexão
     */
    public static function disconnect(): void
    {
        self::$connection = null;
    }

    /**
     * Inicia uma transação
     */
    public static function beginTransaction(): bool
    {
        return self::getConnection()->beginTransaction();
    }

    /**
     * Confirma uma transação
     */
    public static function commit(): bool
    {
        return self::getConnection()->commit();
    }

    /**
     * Reverte uma transação
     */
    public static function rollback(): bool
    {
        return self::getConnection()->rollback();
    }

    /**
     * Verifica se está em uma transação
     */
    public static function inTransaction(): bool
    {
        return self::getConnection()->inTransaction();
    }

    /**
     * Executa uma query e retorna o statement
     */
    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Executa uma query e retorna todos os resultados
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Executa uma query e retorna um único resultado
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $result = self::query($sql, $params)->fetch();
        return $result ?: null;
    }

    /**
     * Executa uma query e retorna um único valor
     */
    public static function fetchColumn(string $sql, array $params = []): mixed
    {
        return self::query($sql, $params)->fetchColumn();
    }

    /**
     * Executa um INSERT e retorna o ID inserido
     */
    public static function insert(string $sql, array $params = []): string
    {
        self::query($sql, $params);
        return self::getConnection()->lastInsertId();
    }

    /**
     * Executa um UPDATE/DELETE e retorna o número de linhas afetadas
     */
    public static function execute(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    /**
     * Obtém a instância do QueryBuilder (se disponível)
     */
    public static function table(string $table): \App\Classes\QueryBuilder
    {
        return new \App\Classes\QueryBuilder($table);
    }

    /**
     * Insere dados em uma tabela usando array associativo
     *
     * @param string $table Nome da tabela
     * @param array $data Dados a inserir [coluna => valor]
     * @return bool
     */
    public static function insertArray(string $table, array $data): bool
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})";

        return self::execute($sql, array_values($data)) > 0;
    }

    /**
     * Insere dados e retorna o ID inserido
     *
     * @param string $table Nome da tabela
     * @param array $data Dados a inserir [coluna => valor]
     * @return int ID inserido
     */
    public static function insertGetId(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})";

        self::query($sql, array_values($data));
        return (int) self::getConnection()->lastInsertId();
    }

    /**
     * Deleta registros de uma tabela
     *
     * @param string $table Nome da tabela
     * @param string $where Condição WHERE (sem a palavra WHERE)
     * @param array $params Parâmetros para a condição
     * @return int Número de registros deletados
     */
    public static function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM `{$table}` WHERE {$where}";
        return self::execute($sql, $params);
    }
}
