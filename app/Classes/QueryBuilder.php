<?php

namespace App\Classes;

use mysqli;
use Exception;

/**
 * QueryBuilder - Database Abstraction Layer com API Fluente
 *
 * Wrapper do mysqli com:
 * - API fluente para construção de queries complexas
 * - Suporte a JOINs, GROUP BY, HAVING, UNION, subqueries
 * - Prepared statements automáticos (proteção SQL injection)
 * - Filtro automático por tenant (chave)
 *
 * Exemplo:
 * ```php
 * $results = $qb->table('financeiro', 'f')
 *     ->select(['f.*', 'c.nome AS cliente_nome'])
 *     ->leftJoin('clientes', 'c', 'f.id_cliente', '=', 'c.id')
 *     ->where('f.tipo', '=', 'R')
 *     ->orderByDesc('f.data_venci')
 *     ->limit(10)
 *     ->get();
 * ```
 *
 * @see docs/querybuilder.md
 */
class QueryBuilder
{
    // Conexão
    private mysqli $mysqli;

    // Multi-tenancy
    private bool $useChave = true;
    private bool $includeGlobals = false;
    private ?string $chave = null;

    // Estado da query (Builder Pattern)
    private ?string $table = null;
    private ?string $tableAlias = null;
    private array $columns = ['*'];
    private bool $distinct = false;
    private array $joins = [];
    private array $wheres = [];
    private array $whereParams = [];
    private array $selectParams = []; // Parâmetros de subqueries no SELECT (aparecem antes do WHERE no SQL)
    private array $groupBy = [];
    private array $having = [];
    private array $havingParams = [];
    private array $orderBy = [];
    private ?int $limitValue = null;
    private ?int $offsetValue = null;
    private array $unions = [];

    // Debug
    private ?string $lastQuery = null;
    private ?string $lastError = null;
    private int $lastAffectedRows = 0;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->mysqli->set_charset('utf8mb4');

        // Obtém chave da sessão (tenant atual)
        $this->chave = $_SESSION['chave'] ?? null;
    }

    // =========================================================================
    // INICIALIZAÇÃO E TABELA
    // =========================================================================

    /**
     * Define a tabela principal da query
     *
     * @param string $table Nome da tabela
     * @param string|null $alias Alias opcional (ex: 'f' para financeiro)
     * @return self
     */
    public function table(string $table, ?string $alias = null): self
    {
        // Preserva configurações de tenancy — são contexto, não estado de query
        $useChave = $this->useChave;
        $includeGlobals = $this->includeGlobals;

        $this->reset();

        $this->useChave = $useChave;
        $this->includeGlobals = $includeGlobals;
        $this->table = $table;
        $this->tableAlias = $alias;

        // Re-lê a chave da sessão a cada nova query, pois o Model pode ter sido
        // instanciado antes da sessão ser populada (webhooks, CRON, scripts CLI).
        $this->chave = $_SESSION['chave'] ?? null;

        return $this;
    }

    /**
     * Alias para table()
     */
    public function from(string $table, ?string $alias = null): self
    {
        return $this->table($table, $alias);
    }

    // =========================================================================
    // SELECT
    // =========================================================================

    /**
     * Define as colunas a selecionar
     *
     * @param array|string $columns Colunas (ex: ['id', 'nome'] ou 'id, nome')
     * @return self
     */
    public function select(array|string $columns = ['*']): self
    {
        if (is_string($columns)) {
            $columns = array_map('trim', explode(',', $columns));
        }
        $this->columns = $columns;
        return $this;
    }

    /**
     * Adiciona colunas ao SELECT existente
     *
     * @param array|string $columns
     * @return self
     */
    public function addSelect(array|string $columns): self
    {
        if (is_string($columns)) {
            $columns = array_map('trim', explode(',', $columns));
        }

        // Remove '*' se estiver adicionando colunas específicas
        if ($this->columns === ['*']) {
            $this->columns = [];
        }

        $this->columns = array_merge($this->columns, $columns);
        return $this;
    }

    /**
     * Adiciona expressão SQL raw ao SELECT
     *
     * @param string $expression Expressão SQL (ex: 'COUNT(*) AS total')
     * @return self
     */
    public function selectRaw(string $expression): self
    {
        if ($this->columns === ['*']) {
            $this->columns = [];
        }
        $this->columns[] = $expression;
        return $this;
    }

    /**
     * Adiciona subquery ao SELECT
     *
     * @param callable $callback Função que recebe QueryBuilder e retorna a query
     * @param string $alias Alias para a subquery
     * @return self
     */
    public function selectSubquery(callable $callback, string $alias): self
    {
        $subQuery = new self($this->mysqli);
        $callback($subQuery);
        $subQuery->useChave = false; // Subqueries não aplicam chave automaticamente (DEPOIS do callback pois table() faz reset)

        $sql = '(' . $subQuery->toSql() . ') AS ' . $alias;

        // Mescla parâmetros da subquery em selectParams (aparecem antes do WHERE no SQL)
        $this->selectParams = array_merge($this->selectParams, $subQuery->getBindings());

        if ($this->columns === ['*']) {
            $this->columns = [];
        }
        $this->columns[] = $sql;

        return $this;
    }

    /**
     * Adiciona DISTINCT ao SELECT
     *
     * @return self
     */
    public function distinct(): self
    {
        $this->distinct = true;
        return $this;
    }

    // =========================================================================
    // JOINs
    // =========================================================================

    /**
     * Adiciona JOIN genérico
     *
     * @param string $table Tabela a juntar
     * @param string $alias Alias da tabela
     * @param string $first Primeira coluna (ex: 'f.id_cliente')
     * @param string $operator Operador (ex: '=')
     * @param string $second Segunda coluna (ex: 'c.id')
     * @param string $type Tipo de JOIN (INNER, LEFT, RIGHT)
     * @return self
     */
    public function join(string $table, string $alias, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = [
            'type' => strtoupper($type),
            'table' => $table,
            'alias' => $alias,
            'condition' => "{$first} {$operator} {$second}",
        ];
        return $this;
    }

    /**
     * LEFT JOIN
     */
    public function leftJoin(string $table, string $alias, string $first, string $operator, string $second): self
    {
        return $this->join($table, $alias, $first, $operator, $second, 'LEFT');
    }

    /**
     * RIGHT JOIN
     */
    public function rightJoin(string $table, string $alias, string $first, string $operator, string $second): self
    {
        return $this->join($table, $alias, $first, $operator, $second, 'RIGHT');
    }

    /**
     * INNER JOIN
     */
    public function innerJoin(string $table, string $alias, string $first, string $operator, string $second): self
    {
        return $this->join($table, $alias, $first, $operator, $second, 'INNER');
    }

    /**
     * JOIN com condição SQL raw
     *
     * @param string $table Tabela
     * @param string $alias Alias
     * @param string $condition Condição completa (ex: 'f.id = c.id AND c.ativo = 1')
     * @param string $type Tipo de JOIN
     * @return self
     */
    public function joinRaw(string $table, string $alias, string $condition, string $type = 'INNER'): self
    {
        $this->joins[] = [
            'type' => strtoupper($type),
            'table' => $table,
            'alias' => $alias,
            'condition' => $condition,
        ];
        return $this;
    }

    /**
     * LEFT JOIN com condição raw
     */
    public function leftJoinRaw(string $table, string $alias, string $condition): self
    {
        return $this->joinRaw($table, $alias, $condition, 'LEFT');
    }

    // =========================================================================
    // WHERE
    // =========================================================================

    /**
     * Adiciona condição WHERE
     *
     * @param string $column Coluna
     * @param string $operator Operador (=, !=, <, >, <=, >=, LIKE, etc.)
     * @param mixed $value Valor (opcional se operador for IS NULL/IS NOT NULL)
     * @return self
     */
    public function where(string $column, string $operator, mixed $value = null): self
    {
        // Suporte para where('coluna', 'valor') sem operador explícito
        if ($value === null && !in_array(strtoupper($operator), ['IS NULL', 'IS NOT NULL'], true)) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'AND',
            'column' => $column,
            'operator' => strtoupper($operator),
            'value' => $value,
        ];

        if (!in_array(strtoupper($operator), ['IS NULL', 'IS NOT NULL'], true)) {
            $this->whereParams[] = $value;
        }

        return $this;
    }

    /**
     * Adiciona condição WHERE com OR
     */
    public function orWhere(string $column, string $operator, mixed $value = null): self
    {
        if ($value === null && !in_array(strtoupper($operator), ['IS NULL', 'IS NOT NULL'], true)) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'OR',
            'column' => $column,
            'operator' => strtoupper($operator),
            'value' => $value,
        ];

        if (!in_array(strtoupper($operator), ['IS NULL', 'IS NOT NULL'], true)) {
            $this->whereParams[] = $value;
        }

        return $this;
    }

    /**
     * WHERE IN
     *
     * @param string $column
     * @param array $values
     * @return self
     */
    public function whereIn(string $column, array $values): self
    {
        if (empty($values)) {
            // Condição impossível para evitar erro de SQL
            $this->wheres[] = [
                'type' => 'AND',
                'raw' => '1 = 0',
            ];
            return $this;
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        $this->wheres[] = [
            'type' => 'AND',
            'raw' => "{$column} IN ({$placeholders})",
        ];

        $this->whereParams = array_merge($this->whereParams, $values);

        return $this;
    }

    /**
     * WHERE NOT IN
     */
    public function whereNotIn(string $column, array $values): self
    {
        if (empty($values)) {
            return $this; // Sem valores, não filtra nada
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        $this->wheres[] = [
            'type' => 'AND',
            'raw' => "{$column} NOT IN ({$placeholders})",
        ];

        $this->whereParams = array_merge($this->whereParams, $values);

        return $this;
    }

    /**
     * WHERE BETWEEN
     */
    public function whereBetween(string $column, mixed $min, mixed $max): self
    {
        $this->wheres[] = [
            'type' => 'AND',
            'raw' => "{$column} BETWEEN ? AND ?",
        ];

        $this->whereParams[] = $min;
        $this->whereParams[] = $max;

        return $this;
    }

    /**
     * WHERE IS NULL
     */
    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'AND',
            'raw' => "{$column} IS NULL",
        ];
        return $this;
    }

    /**
     * WHERE IS NOT NULL
     */
    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'AND',
            'raw' => "{$column} IS NOT NULL",
        ];
        return $this;
    }

    /**
     * WHERE LIKE
     */
    public function whereLike(string $column, string $pattern): self
    {
        return $this->where($column, 'LIKE', $pattern);
    }

    /**
     * WHERE com SQL raw
     *
     * @param string $sql Condição SQL (pode usar ? para placeholders)
     * @param array $params Parâmetros
     * @return self
     */
    public function whereRaw(string $sql, array $params = []): self
    {
        $this->wheres[] = [
            'type' => 'AND',
            'raw' => $sql,
        ];

        $this->whereParams = array_merge($this->whereParams, $params);

        return $this;
    }

    /**
     * WHERE OR com SQL raw
     */
    public function orWhereRaw(string $sql, array $params = []): self
    {
        $this->wheres[] = [
            'type' => 'OR',
            'raw' => $sql,
        ];

        $this->whereParams = array_merge($this->whereParams, $params);

        return $this;
    }

    /**
     * WHERE agrupado com parênteses (para condições complexas)
     *
     * Exemplo:
     * ->whereNested(function($q) {
     *     $q->where('status', '=', 'A')
     *       ->orWhere('status', '=', 'P');
     * })
     *
     * Gera: AND (status = ? OR status = ?)
     *
     * @param callable $callback
     * @return self
     */
    public function whereNested(callable $callback): self
    {
        $nestedBuilder = new self($this->mysqli);
        $nestedBuilder->useChave = false;
        $callback($nestedBuilder);

        $nestedSql = $nestedBuilder->buildWhereClause(false);

        if (!empty($nestedSql)) {
            $this->wheres[] = [
                'type' => 'AND',
                'raw' => '(' . $nestedSql . ')',
            ];

            $this->whereParams = array_merge($this->whereParams, $nestedBuilder->whereParams);
        }

        return $this;
    }

    /**
     * OR WHERE agrupado
     */
    public function orWhereNested(callable $callback): self
    {
        $nestedBuilder = new self($this->mysqli);
        $nestedBuilder->useChave = false;
        $callback($nestedBuilder);

        $nestedSql = $nestedBuilder->buildWhereClause(false);

        if (!empty($nestedSql)) {
            $this->wheres[] = [
                'type' => 'OR',
                'raw' => '(' . $nestedSql . ')',
            ];

            $this->whereParams = array_merge($this->whereParams, $nestedBuilder->whereParams);
        }

        return $this;
    }

    /**
     * WHERE com subquery
     *
     * Exemplo:
     * ->whereSubquery('id', 'IN', function($q) {
     *     $q->table('pedidos')->select(['id_cliente'])->where('status', '=', 'A');
     * })
     */
    public function whereSubquery(string $column, string $operator, callable $callback): self
    {
        $subQuery = new self($this->mysqli);
        $subQuery->useChave = false;
        $callback($subQuery);

        $sql = "{$column} {$operator} (" . $subQuery->toSql() . ")";

        $this->wheres[] = [
            'type' => 'AND',
            'raw' => $sql,
        ];

        $this->whereParams = array_merge($this->whereParams, $subQuery->getBindings());

        return $this;
    }

    // =========================================================================
    // GROUP BY / HAVING
    // =========================================================================

    /**
     * GROUP BY
     *
     * @param string|array $columns Coluna(s) para agrupar
     * @return self
     */
    public function groupBy(string|array $columns): self
    {
        if (is_string($columns)) {
            $columns = [$columns];
        }

        $this->groupBy = array_merge($this->groupBy, $columns);

        return $this;
    }

    /**
     * HAVING
     *
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @return self
     */
    public function having(string $column, string $operator, mixed $value): self
    {
        $this->having[] = [
            'type' => 'AND',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        $this->havingParams[] = $value;

        return $this;
    }

    /**
     * HAVING com SQL raw
     */
    public function havingRaw(string $sql, array $params = []): self
    {
        $this->having[] = [
            'type' => 'AND',
            'raw' => $sql,
        ];

        $this->havingParams = array_merge($this->havingParams, $params);

        return $this;
    }

    // =========================================================================
    // ORDER BY
    // =========================================================================

    /**
     * ORDER BY
     *
     * @param string $column Coluna
     * @param string $direction ASC ou DESC
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        // Whitelist: identificador simples ou table.column (sem backticks, sem espacos).
        // Bloqueia SQL injection via ORDER BY (que nao aceita placeholders).
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $column)) {
            throw new \InvalidArgumentException("orderBy: coluna invalida '{$column}'. Use orderByRaw() para expressoes.");
        }

        $direction = strtoupper($direction);
        if ($direction !== 'ASC' && $direction !== 'DESC') {
            throw new \InvalidArgumentException("orderBy: direcao invalida '{$direction}'. Use ASC ou DESC.");
        }

        $this->orderBy[] = "{$column} {$direction}";
        return $this;
    }

    /**
     * ORDER BY DESC
     */
    public function orderByDesc(string $column): self
    {
        return $this->orderBy($column, 'DESC');
    }

    /**
     * ORDER BY com SQL raw
     */
    public function orderByRaw(string $sql): self
    {
        $this->orderBy[] = $sql;
        return $this;
    }

    // =========================================================================
    // LIMIT / OFFSET
    // =========================================================================

    /**
     * LIMIT
     */
    public function limit(int $limit): self
    {
        $this->limitValue = $limit;
        return $this;
    }

    /**
     * OFFSET
     */
    public function offset(int $offset): self
    {
        $this->offsetValue = $offset;
        return $this;
    }

    /**
     * Alias para limit()
     */
    public function take(int $limit): self
    {
        return $this->limit($limit);
    }

    /**
     * Alias para offset()
     */
    public function skip(int $offset): self
    {
        return $this->offset($offset);
    }

    /**
     * Paginação simplificada
     *
     * @param int $page Número da página (começando em 1)
     * @param int $perPage Itens por página
     * @return self
     */
    public function paginate(int $page, int $perPage): self
    {
        $this->limitValue = $perPage;
        $this->offsetValue = ($page - 1) * $perPage;
        return $this;
    }

    // =========================================================================
    // UNION
    // =========================================================================

    /**
     * UNION
     *
     * @param QueryBuilder $query Query a unir
     * @return self
     */
    public function union(QueryBuilder $query): self
    {
        $this->unions[] = [
            'type' => 'UNION',
            'query' => $query,
        ];
        return $this;
    }

    /**
     * UNION ALL
     */
    public function unionAll(QueryBuilder $query): self
    {
        $this->unions[] = [
            'type' => 'UNION ALL',
            'query' => $query,
        ];
        return $this;
    }

    // =========================================================================
    // MULTI-TENANCY
    // =========================================================================

    /**
     * Desabilita filtro automático por chave na query atual
     *
     * USE COM CAUTELA - apenas para queries administrativas cross-tenant
     */
    public function withoutChave(): self
    {
        $this->useChave = false;
        return $this;
    }

    /**
     * Inclui registros globais (chave='0') junto com registros do tenant
     *
     * Útil para tabelas que têm templates/configurações padrão do sistema:
     * - feriados (nacionais + personalizados)
     * - temporadas (templates + customizadas)
     * - roles (sistema + customizadas)
     *
     * Gera: WHERE (chave = $_SESSION['chave'] OR chave = '0')
     *
     * @return self
     */
    public function withGlobals(): self
    {
        $this->includeGlobals = true;
        return $this;
    }

    /**
     * Define uma chave específica para a query
     *
     * @param string|null $chave Chave do tenant (null usa a da sessão)
     * @return self
     */
    public function withChave(?string $chave = null): self
    {
        $this->useChave = true;
        if ($chave !== null) {
            $this->chave = $chave;
        }
        return $this;
    }

    // =========================================================================
    // EXECUÇÃO - SELECT
    // =========================================================================

    /**
     * Executa a query e retorna array de resultados
     *
     * @return array Array de rows (arrays associativos)
     */
    public function get(): array
    {
        $sql = $this->toSql();
        $params = $this->getBindings();

        $this->lastQuery = $sql;

        $result = $this->executeQuery($sql, $params);

        $this->reset();

        return $result;
    }

    /**
     * Retorna apenas a primeira linha ou null
     *
     * @return array|null
     */
    public function first(): ?array
    {
        $this->limitValue = 1;
        $results = $this->get();
        return $results[0] ?? null;
    }

    /**
     * Retorna uma única linha (método legado para compatibilidade)
     *
     * @param string $table Nome da tabela
     * @param array $columns Colunas a selecionar
     * @param string|null $where Cláusula WHERE
     * @param array $params Parâmetros para prepared statement
     * @param bool $withoutChave Se true, ignora filtro de tenant
     * @return array|null
     */
    public function getRow(
        string $table,
        array $columns = ['*'],
        ?string $where = null,
        array $params = [],
        bool $withoutChave = false
    ): ?array {
        $query = $this->table($table)->select($columns);

        if ($withoutChave) {
            $query->withoutChave();
        }

        if ($where !== null) {
            $query->whereRaw($where, $params);
        }

        return $query->first();
    }

    /**
     * Retorna valor de uma única coluna da primeira linha
     *
     * @param string $column Nome da coluna
     * @return mixed
     */
    public function value(string $column): mixed
    {
        $this->columns = [$column];
        $row = $this->first();
        return $row ? array_values($row)[0] : null;
    }

    /**
     * Retorna array de valores de uma coluna
     *
     * @param string $column Nome da coluna
     * @param string|null $key Coluna para usar como chave do array
     * @return array
     */
    public function pluck(string $column, ?string $key = null): array
    {
        if ($key) {
            $this->columns = [$key, $column];
        } else {
            $this->columns = [$column];
        }

        $results = $this->get();

        if ($key) {
            $plucked = [];
            foreach ($results as $row) {
                $plucked[$row[$key]] = $row[$column];
            }
            return $plucked;
        }

        return array_column($results, $column);
    }

    /**
     * Verifica se existem resultados
     *
     * @return bool
     */
    public function exists(): bool
    {
        $this->columns = ['1'];
        $this->limitValue = 1;
        return $this->first() !== null;
    }

    /**
     * Verifica se não existem resultados
     */
    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    // =========================================================================
    // EXECUÇÃO - AGREGAÇÕES
    // =========================================================================

    /**
     * COUNT
     *
     * @param string $column Coluna (default: *)
     * @return int
     */
    public function count(string $column = '*'): int
    {
        $this->columns = ["COUNT({$column}) AS aggregate"];
        $row = $this->first();
        return (int) ($row['aggregate'] ?? 0);
    }

    /**
     * SUM
     */
    public function sum(string $column): float
    {
        $this->columns = ["SUM({$column}) AS aggregate"];
        $row = $this->first();
        return (float) ($row['aggregate'] ?? 0);
    }

    /**
     * AVG
     */
    public function avg(string $column): float
    {
        $this->columns = ["AVG({$column}) AS aggregate"];
        $row = $this->first();
        return (float) ($row['aggregate'] ?? 0);
    }

    /**
     * MIN
     */
    public function min(string $column): mixed
    {
        $this->columns = ["MIN({$column}) AS aggregate"];
        $row = $this->first();
        return $row['aggregate'] ?? null;
    }

    /**
     * MAX
     */
    public function max(string $column): mixed
    {
        $this->columns = ["MAX({$column}) AS aggregate"];
        $row = $this->first();
        return $row['aggregate'] ?? null;
    }

    // =========================================================================
    // EXECUÇÃO - CRUD
    // =========================================================================

    /**
     * INSERT - Retorna ID inserido
     *
     * @param array $data Array associativo [coluna => valor]
     * @return int ID inserido
     */
    public function insert(array $data): int
    {
        $table = $this->table;

        if (!$table) {
            throw new Exception('Tabela não definida. Use table() antes de insert()');
        }

        // Adiciona chave automaticamente se habilitado
        if ($this->useChave && $this->chave) {
            $data['chave'] = $this->chave;
        }

        $columns = array_keys($data);
        $values = array_values($data);

        $escapedColumns = array_map(fn($col) => "`{$col}`", $columns);
        $columnsStr = implode(', ', $escapedColumns);
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        $sql = "INSERT INTO {$table} ({$columnsStr}) VALUES ({$placeholders})";

        $this->lastQuery = $sql;
        $this->executeStatement($sql, $values);

        $insertId = $this->mysqli->insert_id;
        $this->reset();

        return $insertId;
    }

    /**
     * Alias para insert()
     */
    public function insertGetId(array $data): int
    {
        return $this->insert($data);
    }

    /**
     * UPDATE - Retorna número de linhas afetadas
     *
     * Usa as condições WHERE definidas anteriormente
     *
     * @param array $data Array associativo [coluna => valor]
     * @return int Linhas afetadas
     */
    public function update(array $data): int
    {
        $table = $this->table;

        if (!$table) {
            throw new Exception('Tabela não definida. Use table() antes de update()');
        }

        $setParts = [];
        $values = [];

        foreach ($data as $column => $value) {
            $setParts[] = "`{$column}` = ?";
            $values[] = $value;
        }

        $setStr = implode(', ', $setParts);

        // Constrói WHERE
        $whereClause = $this->buildWhereClauseWithChave();
        $allParams = array_merge($values, $this->whereParams);

        $sql = "UPDATE {$table} SET {$setStr}";

        if (!empty($whereClause)) {
            $sql .= " WHERE {$whereClause}";
        }

        $this->lastQuery = $sql;
        $this->executeStatement($sql, $allParams);

        $affected = $this->lastAffectedRows;
        $this->reset();

        return $affected;
    }

    /**
     * DELETE - Retorna número de linhas afetadas
     *
     * Usa as condições WHERE definidas anteriormente
     *
     * @return int Linhas afetadas
     */
    public function delete(): int
    {
        $table = $this->table;

        if (!$table) {
            throw new Exception('Tabela não definida. Use table() antes de delete()');
        }

        $whereClause = $this->buildWhereClauseWithChave();

        if (empty($whereClause)) {
            throw new Exception('DELETE sem WHERE não é permitido. Use whereRaw("1=1") se realmente deseja deletar tudo.');
        }

        $sql = "DELETE FROM {$table} WHERE {$whereClause}";

        $this->lastQuery = $sql;
        $this->executeStatement($sql, $this->whereParams);

        $affected = $this->lastAffectedRows;
        $this->reset();

        return $affected;
    }

    // =========================================================================
    // TRANSAÇÕES
    // =========================================================================

    public function beginTransaction(): void
    {
        $this->mysqli->begin_transaction();
    }

    public function commit(): void
    {
        $this->mysqli->commit();
    }

    public function rollback(): void
    {
        $this->mysqli->rollback();
    }

    // =========================================================================
    // DEBUG
    // =========================================================================

    /**
     * Retorna o SQL que seria executado (sem executar)
     *
     * @return string
     */
    public function toSql(): string
    {
        $sql = $this->buildSelectSql();

        // Adiciona UNIONs
        foreach ($this->unions as $union) {
            $sql .= ' ' . $union['type'] . ' ' . $union['query']->toSql();
        }

        return $sql;
    }

    /**
     * Retorna os parâmetros de binding
     *
     * @return array
     */
    public function getBindings(): array
    {
        // Ordem: selectParams (subqueries) + whereParams (WHERE clause) + havingParams
        $bindings = array_merge($this->selectParams, $this->whereParams);

        // Adiciona parâmetros de HAVING
        $bindings = array_merge($bindings, $this->havingParams);

        // Adiciona parâmetros de UNIONs
        foreach ($this->unions as $union) {
            $bindings = array_merge($bindings, $union['query']->getBindings());
        }

        return $bindings;
    }

    /**
     * Dump SQL e bindings (para debug)
     */
    public function dd(): void
    {
        echo "SQL: " . $this->toSql() . "\n";
        echo "Bindings: " . print_r($this->getBindings(), true) . "\n";
        exit;
    }

    /**
     * Retorna a última query executada
     */
    public function getLastQuery(): ?string
    {
        return $this->lastQuery;
    }

    /**
     * Retorna o último erro
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    // =========================================================================
    // UTILITÁRIOS
    // =========================================================================

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
     * Clona o builder para reutilização
     */
    public function clone(): self
    {
        return clone $this;
    }

    /**
     * Retorna nova instância limpa
     */
    public function newQuery(): self
    {
        $new = new self($this->mysqli);
        $new->chave = $this->chave;
        return $new;
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

    /**
     * Escapa string (depreciado - use prepared statements)
     *
     * @deprecated
     */
    public function escape(string $value): string
    {
        return $this->mysqli->real_escape_string($value);
    }

    // =========================================================================
    // MÉTODOS INTERNOS
    // =========================================================================

    /**
     * Reseta o estado do builder para nova query
     */
    private function reset(): void
    {
        $this->table = null;
        $this->tableAlias = null;
        $this->columns = ['*'];
        $this->distinct = false;
        $this->joins = [];
        $this->wheres = [];
        $this->whereParams = [];
        $this->selectParams = [];
        $this->groupBy = [];
        $this->having = [];
        $this->havingParams = [];
        $this->orderBy = [];
        $this->limitValue = null;
        $this->offsetValue = null;
        $this->unions = [];
        $this->useChave = true;
        $this->includeGlobals = false;
    }

    /**
     * Constrói o SQL do SELECT
     */
    private function buildSelectSql(): string
    {
        $table = $this->table;

        if (!$table) {
            throw new Exception('Tabela não definida. Use table() primeiro.');
        }

        // SELECT
        $distinctStr = $this->distinct ? 'DISTINCT ' : '';
        $columnsStr = implode(', ', $this->columns);
        $sql = "SELECT {$distinctStr}{$columnsStr}";

        // FROM
        $tableStr = $this->tableAlias ? "{$table} {$this->tableAlias}" : $table;
        $sql .= " FROM {$tableStr}";

        // JOINs
        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} {$join['alias']} ON {$join['condition']}";
        }

        // WHERE
        $whereClause = $this->buildWhereClauseWithChave();
        if (!empty($whereClause)) {
            $sql .= " WHERE {$whereClause}";
        }

        // GROUP BY
        if (!empty($this->groupBy)) {
            $sql .= " GROUP BY " . implode(', ', $this->groupBy);
        }

        // HAVING
        if (!empty($this->having)) {
            $havingClause = $this->buildHavingClause();
            if (!empty($havingClause)) {
                $sql .= " HAVING {$havingClause}";
            }
        }

        // ORDER BY
        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . implode(', ', $this->orderBy);
        }

        // LIMIT
        if ($this->limitValue !== null) {
            $sql .= " LIMIT {$this->limitValue}";
        }

        // OFFSET
        if ($this->offsetValue !== null) {
            $sql .= " OFFSET {$this->offsetValue}";
        }

        return $sql;
    }

    /**
     * Constrói a cláusula WHERE
     */
    private function buildWhereClause(bool $includeType = true): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $parts = [];
        $isFirst = true;

        foreach ($this->wheres as $where) {
            $prefix = '';

            if (!$isFirst) {
                $prefix = $where['type'] . ' ';
            }

            if (isset($where['raw'])) {
                $parts[] = $prefix . $where['raw'];
            } else {
                $operator = $where['operator'];

                if (in_array($operator, ['IS NULL', 'IS NOT NULL'], true)) {
                    $parts[] = $prefix . "{$where['column']} {$operator}";
                } else {
                    $parts[] = $prefix . "{$where['column']} {$operator} ?";
                }
            }

            $isFirst = false;
        }

        return implode(' ', $parts);
    }

    /**
     * Constrói WHERE incluindo filtro por chave do tenant
     */
    private function buildWhereClauseWithChave(): string
    {
        $whereParts = [];

        // Adiciona filtro por chave se habilitado
        if ($this->useChave && $this->chave) {
            // Determina o prefixo da coluna chave
            $chaveColumn = $this->tableAlias ? "{$this->tableAlias}.chave" : 'chave';

            if ($this->includeGlobals) {
                // Inclui registros globais: (chave = ? OR chave = '0')
                $whereParts[] = "({$chaveColumn} = ? OR {$chaveColumn} = '0')";
                array_unshift($this->whereParams, $this->chave);
            } else {
                // Padrão: apenas registros do tenant
                $whereParts[] = "{$chaveColumn} = ?";
                array_unshift($this->whereParams, $this->chave);
            }
        }

        // Adiciona condições WHERE definidas
        $whereClause = $this->buildWhereClause(true);
        if (!empty($whereClause)) {
            $whereParts[] = $whereClause;
        }

        return implode(' AND ', $whereParts);
    }

    /**
     * Constrói a cláusula HAVING
     */
    private function buildHavingClause(): string
    {
        if (empty($this->having)) {
            return '';
        }

        $parts = [];
        $isFirst = true;

        foreach ($this->having as $having) {
            $prefix = '';

            if (!$isFirst) {
                $prefix = $having['type'] . ' ';
            }

            if (isset($having['raw'])) {
                $parts[] = $prefix . $having['raw'];
            } else {
                $parts[] = $prefix . "{$having['column']} {$having['operator']} ?";
            }

            $isFirst = false;
        }

        return implode(' ', $parts);
    }

    /**
     * Executa query SELECT e retorna resultados
     */
    private function executeQuery(string $sql, array $params): array
    {
        $stmt = $this->prepareAndBind($sql, $params);
        $stmt->execute();

        $result = $stmt->get_result();
        $rows = [];

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $stmt->close();

        return $rows;
    }

    /**
     * Executa statement INSERT/UPDATE/DELETE
     */
    private function executeStatement(string $sql, array $params): void
    {
        $stmt = $this->prepareAndBind($sql, $params);
        $stmt->execute();

        if ($stmt->errno) {
            $this->lastError = $stmt->error;
            throw new Exception("Erro na execução: {$stmt->error}");
        }

        // Salva affected_rows do stmt ANTES de fechar (após close, mysqli->affected_rows pode retornar -1)
        $this->lastAffectedRows = $stmt->affected_rows;

        $stmt->close();
    }

    /**
     * Prepara statement e faz bind dos parâmetros
     */
    private function prepareAndBind(string $sql, array $params): \mysqli_stmt
    {
        $stmt = $this->mysqli->prepare($sql);

        if (!$stmt) {
            $this->lastError = $this->mysqli->error;
            throw new Exception("Erro ao preparar query: {$this->mysqli->error}\nSQL: {$sql}");
        }

        if (!empty($params)) {
            $types = $this->getParamTypes($params);
            $stmt->bind_param($types, ...$params);
        }

        return $stmt;
    }

    /**
     * Determina tipos dos parâmetros para bind_param
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
            } elseif (is_null($param)) {
                $types .= 's'; // NULL como string
            } else {
                $types .= 'b'; // blob
            }
        }

        return $types;
    }
}
