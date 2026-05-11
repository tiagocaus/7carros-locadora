<?php

namespace App\Classes;

use mysqli;
use Exception;

/**
 * QueryBuilder - Database Abstraction Layer
 *
 * Wrapper do mysqli com:
 * - Prepared statements automáticos (proteção SQL injection)
 * - Filtro automático por tenant (chave)
 * - Interface fluente
 * - Retorno de arrays ao invés de mysqli_result
 *
 * @see docs/querybuilder.md
 */
class QueryBuilder
{
    private mysqli $mysqli;
    private bool $useChave = true;
    private ?string $chave = null;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->mysqli->set_charset('utf8mb4');

        // Obtém chave da sessão (tenant atual)
        $this->chave = $_SESSION['chave'] ?? null;
    }

    /**
     * Desabilita filtro automático por chave na próxima query
     *
     * Útil para tabelas compartilhadas ou operações administrativas
     */
    public function withoutChave(): self
    {
        $this->useChave = false;
        return $this;
    }

    /**
     * SELECT - Retorna array de resultados
     *
     * @param string $table Nome da tabela
     * @param array $columns Colunas a selecionar (default: ['*'])
     * @param string|null $where Condição WHERE (usar ? para placeholders)
     * @param array $params Parâmetros para prepared statement
     * @param string|null $orderBy Ordenação (ex: 'id DESC')
     * @param int|null $limit Limite de resultados
     * @param int|null $offset Offset para paginação
     * @return array Array de associações (rows)
     */
    public function select(
        string $table,
        array $columns = ['*'],
        ?string $where = null,
        array $params = [],
        ?string $orderBy = null,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $columnsStr = implode(', ', $columns);
        $sql = "SELECT {$columnsStr} FROM {$table}";

        // Adiciona filtro por chave
        [$whereClause, $params] = $this->buildWhereWithChave($where, $params);

        if ($whereClause) {
            $sql .= " WHERE {$whereClause}";
        }

        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }

        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
        }

        if ($offset !== null) {
            $sql .= " OFFSET {$offset}";
        }

        return $this->query($sql, $params);
    }

    /**
     * INSERT - Retorna ID inserido
     *
     * @param string $table Nome da tabela
     * @param array $data Array associativo [coluna => valor]
     * @return int ID inserido
     */
    public function insert(string $table, array $data): int
    {
        // Adiciona chave automaticamente se habilitado
        if ($this->useChave && $this->chave) {
            $data['chave'] = $this->chave;
        }

        $columns = array_keys($data);
        $values = array_values($data);

        $columnsStr = implode(', ', $columns);
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        $sql = "INSERT INTO {$table} ({$columnsStr}) VALUES ({$placeholders})";

        $this->execute($sql, $values);

        $insertId = $this->mysqli->insert_id;
        $this->resetChaveFlag();

        return $insertId;
    }

    /**
     * UPDATE - Retorna booleano de sucesso
     *
     * @param string $table Nome da tabela
     * @param array $data Array associativo [coluna => valor]
     * @param string $where Condição WHERE (usar ? para placeholders)
     * @param array $params Parâmetros para WHERE
     * @return bool Sucesso
     */
    public function update(string $table, array $data, string $where, array $params = []): bool
    {
        $setParts = [];
        $values = [];

        foreach ($data as $column => $value) {
            $setParts[] = "{$column} = ?";
            $values[] = $value;
        }

        $setStr = implode(', ', $setParts);

        // Adiciona filtro por chave
        [$whereClause, $allParams] = $this->buildWhereWithChave($where, $params);

        // Combina valores do SET com valores do WHERE
        $allParams = array_merge($values, $allParams);

        $sql = "UPDATE {$table} SET {$setStr} WHERE {$whereClause}";

        $this->execute($sql, $allParams);

        $affected = $this->mysqli->affected_rows;
        $this->resetChaveFlag();

        return $affected > 0;
    }

    /**
     * DELETE - Retorna booleano de sucesso
     *
     * @param string $table Nome da tabela
     * @param string $where Condição WHERE (usar ? para placeholders)
     * @param array $params Parâmetros para WHERE
     * @return bool Sucesso
     */
    public function delete(string $table, string $where, array $params = []): bool
    {
        // Adiciona filtro por chave
        [$whereClause, $allParams] = $this->buildWhereWithChave($where, $params);

        $sql = "DELETE FROM {$table} WHERE {$whereClause}";

        $this->execute($sql, $allParams);

        $affected = $this->mysqli->affected_rows;
        $this->resetChaveFlag();

        return $affected > 0;
    }

    /**
     * Retorna uma única linha
     *
     * @param string $table
     * @param array $columns
     * @param string|null $where
     * @param array $params
     * @return array|null
     */
    public function getRow(
        string $table,
        array $columns = ['*'],
        ?string $where = null,
        array $params = []
    ): ?array {
        $results = $this->select($table, $columns, $where, $params, null, 1);
        return $results[0] ?? null;
    }

    /**
     * Retorna um único valor (primeira coluna da primeira linha)
     *
     * @param string $table
     * @param string $column
     * @param string|null $where
     * @param array $params
     * @return mixed
     */
    public function getValue(
        string $table,
        string $column,
        ?string $where = null,
        array $params = []
    ): mixed {
        $row = $this->getRow($table, [$column], $where, $params);
        return $row ? array_values($row)[0] : null;
    }

    /**
     * Conta registros
     *
     * @param string $table
     * @param string|null $where
     * @param array $params
     * @return int
     */
    public function count(string $table, ?string $where = null, array $params = []): int
    {
        $count = $this->getValue($table, 'COUNT(*) as total', $where, $params);
        return (int)$count;
    }

    /**
     * Verifica se registro existe
     *
     * @param string $table
     * @param string $where
     * @param array $params
     * @return bool
     */
    public function exists(string $table, string $where, array $params = []): bool
    {
        return $this->count($table, $where, $params) > 0;
    }

    /**
     * Executa query SELECT customizada e retorna array de resultados
     *
     * @param string $sql Query SQL completa
     * @param array $params Parâmetros para prepared statement
     * @return array Array de resultados
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->prepareAndBind($sql, $params);
        $stmt->execute();

        $result = $stmt->get_result();
        $rows = [];

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $stmt->close();
        $this->resetChaveFlag();

        return $rows;
    }

    /**
     * Executa query INSERT/UPDATE/DELETE
     *
     * @param string $sql Query SQL
     * @param array $params Parâmetros
     * @return void
     */
    public function execute(string $sql, array $params = []): void
    {
        $stmt = $this->prepareAndBind($sql, $params);
        $stmt->execute();

        if ($stmt->errno) {
            throw new Exception("Erro na execução: {$stmt->error}");
        }

        $stmt->close();
    }

    /**
     * Inicia transação
     */
    public function beginTransaction(): void
    {
        $this->mysqli->begin_transaction();
    }

    /**
     * Confirma transação
     */
    public function commit(): void
    {
        $this->mysqli->commit();
    }

    /**
     * Reverte transação
     */
    public function rollback(): void
    {
        $this->mysqli->rollback();
    }

    /**
     * Retorna último ID inserido
     */
    public function getLastInsertId(): int
    {
        return $this->mysqli->insert_id;
    }

    /**
     * Retorna número de linhas afetadas
     */
    public function getAffectedRows(): int
    {
        return $this->mysqli->affected_rows;
    }

    /**
     * Escapa string (use apenas quando não puder usar prepared statements)
     *
     * @deprecated Prefira prepared statements
     */
    public function escape(string $value): string
    {
        return $this->mysqli->real_escape_string($value);
    }

    /**
     * Prepara statement e faz bind dos parâmetros
     *
     * @param string $sql
     * @param array $params
     * @return \mysqli_stmt
     */
    private function prepareAndBind(string $sql, array $params): \mysqli_stmt
    {
        $stmt = $this->mysqli->prepare($sql);

        if (!$stmt) {
            throw new Exception("Erro ao preparar query: {$this->mysqli->error}");
        }

        if (!empty($params)) {
            $types = $this->getParamTypes($params);
            $stmt->bind_param($types, ...$params);
        }

        return $stmt;
    }

    /**
     * Determina tipos dos parâmetros para bind_param
     *
     * @param array $params
     * @return string String de tipos (ex: 'ssi' = string, string, int)
     */
    private function getParamTypes(array $params): string
    {
        $types = '';

        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } elseif (is_string($param)) {
                $types .= 's';
            } else {
                $types .= 'b'; // blob
            }
        }

        return $types;
    }

    /**
     * Constrói cláusula WHERE incluindo filtro por chave
     *
     * @param string|null $where
     * @param array $params
     * @return array [whereClause, params]
     */
    private function buildWhereWithChave(?string $where, array $params): array
    {
        $whereParts = [];

        // Adiciona filtro por chave se habilitado
        if ($this->useChave && $this->chave) {
            $whereParts[] = 'chave = ?';
            array_unshift($params, $this->chave); // Adiciona chave no início dos params
        }

        // Adiciona condição WHERE customizada
        if ($where) {
            $whereParts[] = "({$where})";
        }

        $whereClause = implode(' AND ', $whereParts);

        return [$whereClause, $params];
    }

    /**
     * Reseta flag de useChave para true após cada operação
     */
    private function resetChaveFlag(): void
    {
        $this->useChave = true;
    }

    /**
     * Fecha conexão
     */
    public function close(): void
    {
        $this->mysqli->close();
    }

    /**
     * Retorna instância mysqli (para casos especiais)
     */
    public function getMysqli(): mysqli
    {
        return $this->mysqli;
    }
}
