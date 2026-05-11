<?php

namespace App\Models;

use App\Classes\QueryBuilder;
use App\Core\Database;
use mysqli;

/**
 * Classe base para todos os Models
 *
 * Implementa conexão mysqli Singleton compartilhada entre todas as instâncias.
 * Elimina duplicação de código e reduz conexões ao banco.
 */
abstract class Model
{
    private static ?mysqli $mysqli = null;
    protected QueryBuilder $qb;

    public function __construct()
    {
        $this->qb = new QueryBuilder(self::getConnection());
    }

    /**
     * Retorna conexão mysqli compartilhada (Singleton)
     */
    protected static function getConnection(): mysqli
    {
        if (self::$mysqli === null || !self::$mysqli->ping()) {
            self::$mysqli = self::createConnection();
        }
        return self::$mysqli;
    }

    /**
     * Cria nova conexão mysqli
     */
    private static function createConnection(): mysqli
    {
        $host = Database::env('DB_HOST', 'localhost');
        $username = Database::env('DB_USERNAME');
        $password = Database::env('DB_PASSWORD');
        $database = Database::env('DB_DATABASE');
        $port = (int) Database::env('DB_PORT', '3306');

        $mysqli = new mysqli($host, $username, $password, $database, $port);

        if ($mysqli->connect_error) {
            throw new \RuntimeException('Erro ao conectar com o banco de dados: ' . $mysqli->connect_error);
        }

        $mysqli->set_charset('utf8mb4');
        return $mysqli;
    }

    /**
     * Retorna instância mysqli para operações que precisam de acesso direto
     * (transações, etc)
     */
    protected function getMysqli(): mysqli
    {
        return self::getConnection();
    }

    /**
     * Retorna a conexao mysqli compartilhada (Singleton) para uso externo
     * por Services e helpers que precisam passar um mysqli adiante.
     */
    public static function sharedMysqli(): mysqli
    {
        return self::getConnection();
    }

    /**
     * QueryBuilder com filtro de chave automático
     */
    protected function query(): QueryBuilder
    {
        return $this->qb;
    }

    /**
     * Fecha conexão (uso em scripts CLI ou testes)
     */
    public static function closeConnection(): void
    {
        if (self::$mysqli !== null) {
            self::$mysqli->close();
            self::$mysqli = null;
        }
    }
}
