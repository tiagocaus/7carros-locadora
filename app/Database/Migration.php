<?php

namespace App\Database;

use App\Core\Database;
use App\Classes\QueryBuilder;
use PDO;
use mysqli;

/**
 * Classe base para Migrations
 *
 * Fornece métodos para criar, alterar e remover tabelas e colunas
 */
abstract class Migration
{
    protected PDO $pdo;
    private ?QueryBuilder $queryBuilder = null;
    private ?mysqli $mysqli = null;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /**
     * Retorna instância do QueryBuilder para operações DML (lazy loading)
     *
     * Usa mysqli em vez de PDO para ser consistente com o padrão do projeto.
     * O filtro automático por chave (multi-tenancy) é desabilitado pois
     * migrations devem manipular dados de todos os tenants.
     */
    protected function db(): QueryBuilder
    {
        if ($this->queryBuilder === null) {
            $this->mysqli = new mysqli(
                Database::env('DB_HOST'),
                Database::env('DB_USERNAME'),
                Database::env('DB_PASSWORD'),
                Database::env('DB_DATABASE'),
                (int) Database::env('DB_PORT', '3306')
            );
            $this->mysqli->set_charset('utf8mb4');

            // Cria QueryBuilder e desabilita filtro automático por chave
            $this->queryBuilder = new QueryBuilder($this->mysqli);
            $this->queryBuilder->withoutChave();
        }
        return $this->queryBuilder;
    }

    /**
     * Fecha conexão mysqli ao destruir Migration
     */
    public function __destruct()
    {
        if ($this->mysqli !== null && !$this->mysqli->connect_errno) {
            $this->mysqli->close();
        }
    }

    /**
     * Executa a migration (deve ser implementado)
     */
    abstract public function up(): void;

    /**
     * Reverte a migration (deve ser implementado)
     */
    abstract public function down(): void;

    /**
     * Cria uma nova tabela
     */
    protected function create(string $table, callable $callback): void
    {
        $builder = new SchemaBuilder($table, $this->pdo);
        $callback($builder);

        // Finaliza objetos pendentes ANTES de construir o SQL
        $builder->finalizeAll();

        $sql = $builder->buildCreate();
        $this->pdo->exec($sql);
    }

    /**
     * Altera uma tabela existente
     */
    protected function table(string $table, callable $callback): void
    {
        $builder = new SchemaBuilder($table, $this->pdo);
        $builder->setAltering(true); // Ativa modo ALTER TABLE
        $callback($builder);

        // Força finalização de objetos pendentes ANTES de executar SQLs
        $builder->finalizeAll();

        $sqls = $builder->buildAlter();

        foreach ($sqls as $sql) {
            $this->pdo->exec($sql);
        }
    }

    /**
     * Altera uma tabela existente (alias para table())
     *
     * Permite usar $this->alter() em vez de $this->table() para
     * deixar claro que está alterando uma tabela existente.
     */
    protected function alter(string $table, callable $callback): void
    {
        $this->table($table, $callback);
    }

    /**
     * Remove uma tabela
     */
    protected function drop(string $table): void
    {
        $sql = "DROP TABLE IF EXISTS `{$table}`";
        $this->pdo->exec($sql);
    }

    /**
     * Adiciona uma coluna à tabela
     */
    protected function addColumn(string $table, string $column, string $type, array $options = []): void
    {
        $definition = $this->buildColumnDefinition($column, $type, $options);
        $after = isset($options['after']) ? "AFTER `{$options['after']}`" : '';

        $sql = "ALTER TABLE `{$table}` ADD COLUMN {$definition} {$after}";
        $this->pdo->exec($sql);
    }

    /**
     * Adiciona uma coluna apenas se ela não existir
     */
    protected function addColumnIfNotExists(string $table, string $column, string $type, array $options = []): void
    {
        if (!$this->columnExists($table, $column)) {
            $this->addColumn($table, $column, $type, $options);
        }
    }

    /**
     * Remove uma coluna da tabela
     */
    protected function dropColumn(string $table, string $column): void
    {
        $sql = "ALTER TABLE `{$table}` DROP COLUMN `{$column}`";
        $this->pdo->exec($sql);
    }

    /**
     * Remove uma coluna apenas se ela existir
     */
    protected function dropColumnIfExists(string $table, string $column): void
    {
        if ($this->columnExists($table, $column)) {
            $this->dropColumn($table, $column);
        }
    }

    /**
     * Modifica uma coluna existente
     */
    protected function modifyColumn(string $table, string $column, string $type, array $options = []): void
    {
        $definition = $this->buildColumnDefinition($column, $type, $options);
        $sql = "ALTER TABLE `{$table}` MODIFY COLUMN {$definition}";
        $this->pdo->exec($sql);
    }

    /**
     * Adiciona um índice
     */
    protected function addIndex(string $table, string|array $columns, ?string $name = null): void
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $indexName = $name ?? 'idx_' . implode('_', $columns);
        $columnsList = implode('`, `', $columns);

        $sql = "ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$columnsList}`)";
        $this->pdo->exec($sql);
    }

    /**
     * Adiciona um índice apenas se não existir
     */
    protected function addIndexIfNotExists(string $table, string|array $columns, ?string $name = null): void
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $indexName = $name ?? 'idx_' . implode('_', $columns);

        if (!$this->indexExists($table, $indexName)) {
            $this->addIndex($table, $columns, $indexName);
        }
    }

    /**
     * Remove um índice
     */
    protected function dropIndex(string $table, string $indexName): void
    {
        $sql = "ALTER TABLE `{$table}` DROP INDEX `{$indexName}`";
        $this->pdo->exec($sql);
    }

    /**
     * Remove um índice apenas se existir
     */
    protected function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            $this->dropIndex($table, $indexName);
        }
    }

    /**
     * Verifica se uma tabela existe
     */
    protected function tableExists(string $table): bool
    {
        // rowCount() nao eh confiavel para SHOW TABLES no PDO/MySQL — usar fetch()
        $stmt = $this->pdo->query('SHOW TABLES LIKE ' . $this->pdo->quote($table));

        return $stmt && $stmt->fetch() !== false;
    }

    /**
     * Verifica se uma coluna existe
     */
    protected function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->query(
            'SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ' . $this->pdo->quote($column)
        );

        return $stmt && $stmt->fetch() !== false;
    }

    /**
     * Verifica se um índice existe
     */
    protected function indexExists(string $table, string $indexName): bool
    {
        $stmt = $this->pdo->query(
            'SHOW INDEX FROM `' . str_replace('`', '``', $table) . '` WHERE Key_name = ' . $this->pdo->quote($indexName)
        );

        return $stmt && $stmt->fetch() !== false;
    }

    /**
     * Adiciona uma foreign key
     */
    protected function addForeignKey(string $table, string $column, string $referencedTable, string $referencedColumn, string $onDelete = 'RESTRICT', string $onUpdate = 'RESTRICT', ?string $name = null): void
    {
        $fkName = $name ?? "fk_{$table}_{$column}";

        $sql = "ALTER TABLE `{$table}` ADD CONSTRAINT `{$fkName}` " .
               "FOREIGN KEY (`{$column}`) REFERENCES `{$referencedTable}` (`{$referencedColumn}`) " .
               "ON DELETE {$onDelete} ON UPDATE {$onUpdate}";

        $this->pdo->exec($sql);
    }

    /**
     * Adiciona uma foreign key apenas se não existir
     */
    protected function addForeignKeyIfNotExists(string $table, string $column, string $referencedTable, string $referencedColumn, string $onDelete = 'RESTRICT', string $onUpdate = 'RESTRICT', ?string $name = null): void
    {
        $fkName = $name ?? "fk_{$table}_{$column}";

        if (!$this->foreignKeyExists($table, $fkName)) {
            $this->addForeignKey($table, $column, $referencedTable, $referencedColumn, $onDelete, $onUpdate, $name);
        }
    }

    /**
     * Remove uma foreign key
     */
    protected function dropForeignKey(string $table, string $name): void
    {
        $sql = "ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`";
        $this->pdo->exec($sql);
    }

    /**
     * Remove uma foreign key apenas se existir
     */
    protected function dropForeignKeyIfExists(string $table, string $name): void
    {
        if ($this->foreignKeyExists($table, $name)) {
            $this->dropForeignKey($table, $name);
        }
    }

    /**
     * Verifica se uma foreign key existe
     */
    protected function foreignKeyExists(string $table, string $fkName): bool
    {
        $database = $this->pdo->query("SELECT DATABASE()")->fetchColumn();

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = ?
            AND TABLE_NAME = ?
            AND CONSTRAINT_NAME = ?
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ");

        $stmt->execute([$database, $table, $fkName]);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * Renomeia uma coluna
     */
    protected function renameColumn(string $table, string $oldName, string $newName, string $type, array $options = []): void
    {
        $definition = $this->buildColumnDefinition($newName, $type, $options);
        $sql = "ALTER TABLE `{$table}` CHANGE COLUMN `{$oldName}` {$definition}";
        $this->pdo->exec($sql);
    }

    /**
     * Renomeia uma coluna preservando automaticamente seu tipo e propriedades
     *
     * @param string $table Nome da tabela
     * @param string $oldName Nome antigo da coluna
     * @param string $newName Nome novo da coluna
     * @throws \RuntimeException Se a coluna não existir
     */
    protected function renameColumnPreservingType(string $table, string $oldName, string $newName): void
    {
        // Verifica se a coluna existe
        if (!$this->columnExists($table, $oldName)) {
            throw new \RuntimeException("Coluna '{$oldName}' não existe na tabela '{$table}'");
        }

        // Obtém informações da coluna original
        $stmt = $this->pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$oldName}'");
        $columnInfo = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$columnInfo) {
            throw new \RuntimeException("Não foi possível obter informações da coluna '{$oldName}'");
        }

        // Constrói a definição completa da coluna preservando todas as propriedades
        $type = $columnInfo['Type'];
        $null = strtoupper($columnInfo['Null']) === 'YES' ? 'NULL' : 'NOT NULL';
        $default = '';
        
        if ($columnInfo['Default'] !== null) {
            if (is_numeric($columnInfo['Default'])) {
                $default = "DEFAULT {$columnInfo['Default']}";
            } elseif (strtoupper($columnInfo['Default']) === 'CURRENT_TIMESTAMP') {
                $default = 'DEFAULT CURRENT_TIMESTAMP';
            } else {
                $default = "DEFAULT '" . addslashes($columnInfo['Default']) . "'";
            }
        } elseif (strtoupper($columnInfo['Null']) === 'YES') {
            $default = 'DEFAULT NULL';
        }

        $extra = $columnInfo['Extra'] ?: '';

        // Monta o SQL de renomeação
        $sql = "ALTER TABLE `{$table}` CHANGE COLUMN `{$oldName}` `{$newName}` {$type} {$null} {$default} {$extra}";
        $this->pdo->exec($sql);
    }

    /**
     * Copia dados de uma coluna para outra na mesma tabela
     *
     * Útil para migrações onde você quer duplicar dados antes de fazer alterações.
     * Exemplo: copiar dados de 'empresa' para 'nome_fantasia' antes de renomear 'empresa' para 'razao_social'
     *
     * @param string $table Nome da tabela
     * @param string $sourceColumn Coluna de origem (de onde copiar)
     * @param string $targetColumn Coluna de destino (para onde copiar)
     * @param string|null $where Condição WHERE opcional (ex: "`chave` = ?")
     * @param array $whereParams Parâmetros para a condição WHERE
     * @throws \RuntimeException Se alguma coluna não existir
     */
    protected function copyColumnData(string $table, string $sourceColumn, string $targetColumn, ?string $where = null, array $whereParams = []): void
    {
        // Verifica se ambas as colunas existem
        if (!$this->columnExists($table, $sourceColumn)) {
            throw new \RuntimeException("Coluna de origem '{$sourceColumn}' não existe na tabela '{$table}'");
        }

        if (!$this->columnExists($table, $targetColumn)) {
            throw new \RuntimeException("Coluna de destino '{$targetColumn}' não existe na tabela '{$table}'");
        }

        // Monta a query de UPDATE
        $whereClause = $where ? "WHERE {$where}" : '';
        $sql = "UPDATE `{$table}` SET `{$targetColumn}` = `{$sourceColumn}` {$whereClause}";

        // Usa mysqli diretamente via QueryBuilder para prepared statements
        $mysqli = $this->db()->getMysqli();
        $stmt = $mysqli->prepare($sql);

        if (!empty($whereParams)) {
            // Determina tipos dos parametros (s=string, i=int, d=double)
            $types = str_repeat('s', count($whereParams));
            $stmt->bind_param($types, ...$whereParams);
        }

        $stmt->execute();

        if ($stmt->errno) {
            throw new \RuntimeException("Erro ao copiar dados: {$stmt->error}");
        }

        $stmt->close();
    }

    /**
     * Renomeia uma tabela
     */
    protected function renameTable(string $oldName, string $newName): void
    {
        $sql = "RENAME TABLE `{$oldName}` TO `{$newName}`";
        $this->pdo->exec($sql);
    }

    /**
     * Verifica se uma coluna permite NULL
     */
    protected function columnIsNullable(string $table, string $column): bool
    {
        $stmt = $this->pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        $columnInfo = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $columnInfo && strtoupper($columnInfo['Null']) === 'YES';
    }

    /**
     * Verifica se uma coluna é UNSIGNED
     */
    protected function columnIsUnsigned(string $table, string $column): bool
    {
        $stmt = $this->pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        $columnInfo = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $columnInfo && str_contains(strtoupper($columnInfo['Type']), 'UNSIGNED');
    }

    /**
     * Verifica se uma coluna tem valor padrão
     */
    protected function columnHasDefault(string $table, string $column): bool
    {
        $stmt = $this->pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        $columnInfo = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $columnInfo && $columnInfo['Default'] !== null;
    }

    /**
     * Obtém o tipo de uma coluna
     */
    protected function getColumnType(string $table, string $column): ?string
    {
        $stmt = $this->pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        $columnInfo = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $columnInfo ? $columnInfo['Type'] : null;
    }

    /**
     * Executa SQL bruto
     */
    protected function execute(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    /**
     * Constrói a definição de uma coluna
     */
    protected function buildColumnDefinition(string $column, string $type, array $options): string
    {
        $sql = "`{$column}` " . strtoupper($type);

        // Unsigned
        if (!empty($options['unsigned'])) {
            $sql .= ' UNSIGNED';
        }

        // Null/Not Null
        if (isset($options['null']) && $options['null'] === false) {
            $sql .= ' NOT NULL';
        } elseif (isset($options['null']) && $options['null'] === true) {
            $sql .= ' NULL';
        }

        // Default
        if (array_key_exists('default', $options)) {
            if ($options['default'] === null) {
                $sql .= ' DEFAULT NULL';
            } elseif (is_numeric($options['default'])) {
                $sql .= ' DEFAULT ' . $options['default'];
            } elseif (strtoupper($options['default']) === 'CURRENT_TIMESTAMP') {
                $sql .= ' DEFAULT CURRENT_TIMESTAMP';
            } else {
                $sql .= " DEFAULT '" . addslashes($options['default']) . "'";
            }
        }

        // Auto Increment
        if (!empty($options['autoIncrement'])) {
            $sql .= ' AUTO_INCREMENT';
        }

        // Primary Key
        if (!empty($options['primary'])) {
            $sql .= ' PRIMARY KEY';
        }

        // Unique
        if (!empty($options['unique'])) {
            $sql .= ' UNIQUE';
        }

        return $sql;
    }
}

/**
 * Schema Builder - Fluent API para construir schemas de tabelas
 *
 * Permite definir colunas, índices e constraints usando sintaxe fluente
 */
class SchemaBuilder
{
    private string $table;
    private PDO $pdo;
    private array $columns = [];
    private array $indexes = [];
    private array $foreignKeys = [];
    private array $alterations = [];
    private string $engine = 'InnoDB';
    private string $charset = 'utf8mb4';
    private string $collation = 'utf8mb4_unicode_ci';
    private bool $isAltering = false;
    private array $pendingObjects = []; // Objetos Column/ForeignKey pendentes de finalização
    private array $pendingIndexes = []; // Índices pendentes de criação em modo ALTER

    public function __construct(string $table, PDO $pdo)
    {
        $this->table = $table;
        $this->pdo = $pdo;
    }

    /**
     * Registra um objeto pendente para finalização
     */
    public function registerPending(object $object): void
    {
        $this->pendingObjects[] = $object;
    }

    /**
     * Finaliza todos os objetos pendentes na ordem correta
     */
    public function finalizeAll(): void
    {
        try {
            // Separa objetos por tipo
            $columns = [];
            $foreignKeys = [];

            foreach ($this->pendingObjects as $object) {
                if ($object instanceof Column) {
                    $columns[] = $object;
                } elseif ($object instanceof ForeignKey) {
                    $foreignKeys[] = $object;
                }
            }

            // Finaliza na ordem: 1. Colunas, 2. Índices, 3. ForeignKeys
            foreach ($columns as $column) {
                $column->finalize();
            }

            // Processa índices pendentes (apenas em modo ALTER)
            if ($this->isAltering) {
                foreach ($this->pendingIndexes as $index) {
                    $stmt = $this->pdo->query("SHOW INDEX FROM `{$this->table}` WHERE Key_name = '{$index['name']}'");
                    if (!$stmt || $stmt->fetch() === false) {
                        $keyword = $index['type'] === 'UNIQUE' ? 'UNIQUE KEY' : 'INDEX';
                        $this->alterations[] = "ALTER TABLE `{$this->table}` ADD {$keyword} `{$index['name']}` (`{$index['columns']}`)";
                    }
                }
            }

            foreach ($foreignKeys as $fk) {
                $fk->finalize();
            }

            $this->pendingObjects = [];
            $this->pendingIndexes = [];
        } catch (\Exception $e) {
            throw new \RuntimeException("Erro em finalizeAll(): " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Define que o builder está em modo ALTER TABLE
     */
    public function setAltering(bool $isAltering): self
    {
        $this->isAltering = $isAltering;
        return $this;
    }

    /**
     * Verifica se está em modo ALTER TABLE
     */
    public function isAltering(): bool
    {
        return $this->isAltering;
    }

    /**
     * Obtém o nome da tabela
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Obtém a instância PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Coluna ID auto-incremento
     */
    public function id(string $name = 'id'): self
    {
        $this->columns[] = "`{$name}` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY";
        return $this;
    }

    /**
     * Coluna string/varchar
     */
    public function string(string $name, int $length = 255): Column
    {
        return new Column($this, $name, "VARCHAR($length)");
    }

    /**
     * Coluna integer
     */
    public function integer(string $name): Column
    {
        return new Column($this, $name, 'INT');
    }

    /**
     * Coluna bigint
     */
    public function bigInteger(string $name): Column
    {
        return new Column($this, $name, 'BIGINT');
    }

    /**
     * Coluna text
     */
    public function text(string $name): Column
    {
        return new Column($this, $name, 'TEXT');
    }

    /**
     * Coluna longtext
     */
    public function longText(string $name): Column
    {
        return new Column($this, $name, 'LONGTEXT');
    }

    /**
     * Coluna decimal
     */
    public function decimal(string $name, int $precision = 8, int $scale = 2): Column
    {
        return new Column($this, $name, "DECIMAL($precision,$scale)");
    }

    /**
     * Coluna boolean (tinyint(1))
     */
    public function boolean(string $name): Column
    {
        return new Column($this, $name, 'TINYINT(1)');
    }

    /**
     * Coluna date
     */
    public function date(string $name): Column
    {
        return new Column($this, $name, 'DATE');
    }

    /**
     * Coluna datetime
     */
    public function datetime(string $name): Column
    {
        return new Column($this, $name, 'DATETIME');
    }

    /**
     * Coluna timestamp
     */
    public function timestamp(string $name): Column
    {
        return new Column($this, $name, 'TIMESTAMP');
    }

    /**
     * Coluna time
     */
    public function time(string $name): Column
    {
        return new Column($this, $name, 'TIME');
    }

    /**
     * Coluna float
     */
    public function float(string $name, int $precision = 8, int $scale = 2): Column
    {
        return new Column($this, $name, "FLOAT($precision,$scale)");
    }

    /**
     * Coluna double
     */
    public function double(string $name, int $precision = 8, int $scale = 2): Column
    {
        return new Column($this, $name, "DOUBLE($precision,$scale)");
    }

    /**
     * Coluna enum
     */
    public function enum(string $name, array $values): Column
    {
        $valuesList = "'" . implode("','", array_map('addslashes', $values)) . "'";
        return new Column($this, $name, "ENUM($valuesList)");
    }

    /**
     * Coluna json
     */
    public function json(string $name): Column
    {
        return new Column($this, $name, 'JSON');
    }

    /**
     * Timestamps padrão (created_at, updated_at)
     */
    public function timestamps(): self
    {
        $this->columns[] = "`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        $this->columns[] = "`updated_at` DATETIME ON UPDATE CURRENT_TIMESTAMP";
        return $this;
    }

    /**
     * @deprecated O projeto NAO usa soft-delete. Nao usar em novas migrations.
     *             Mantido apenas para compat com migrations antigas ja registradas.
     */
    public function softDeletes(): self
    {
        $this->columns[] = "`deleted_at` DATETIME NULL DEFAULT NULL";
        return $this;
    }

    /**
     * Adiciona uma coluna customizada
     *
     * Em modo CREATE TABLE, adiciona à lista de colunas.
     * Em modo ALTER TABLE, gera SQL de ADD COLUMN automaticamente.
     */
    public function addColumn(string $definition): self
    {
        if ($this->isAltering) {
            // Em modo ALTER, gera SQL de ADD COLUMN
            $this->alterations[] = "ALTER TABLE `{$this->table}` ADD COLUMN {$definition}";
        } else {
            $this->columns[] = $definition;
        }
        return $this;
    }

    /**
     * Cria um índice
     */
    public function index(string|array $columns, string $name = null): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $indexName = $name ?? 'idx_' . implode('_', $columns);
        $columnsList = implode('`, `', $columns);

        if ($this->isAltering) {
            // Armazena para criação posterior (após colunas)
            $this->pendingIndexes[] = [
                'type' => 'INDEX',
                'name' => $indexName,
                'columns' => $columnsList
            ];
        } else {
            $this->indexes[] = "INDEX `{$indexName}` (`{$columnsList}`)";
        }

        return $this;
    }

    /**
     * Cria um índice único
     */
    public function unique(string|array $columns, string $name = null): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $indexName = $name ?? 'idx_' . implode('_', $columns);
        $columnsList = implode('`, `', $columns);

        if ($this->isAltering) {
            // Armazena para criação posterior (após colunas)
            $this->pendingIndexes[] = [
                'type' => 'UNIQUE',
                'name' => $indexName,
                'columns' => $columnsList
            ];
        } else {
            $this->indexes[] = "UNIQUE KEY `{$indexName}` (`{$columnsList}`)";
        }

        return $this;
    }

    /**
     * Cria uma foreign key
     */
    public function foreign(string $column): ForeignKey
    {
        $foreignKey = new ForeignKey($column, $this);
        $this->foreignKeys[] = $foreignKey;
        return $foreignKey;
    }

    /**
     * Remove uma coluna (apenas em modo ALTER)
     */
    public function dropColumn(string $column): self
    {
        if ($this->isAltering) {
            // Verifica se a coluna existe antes de tentar remover
            $stmt = $this->pdo->query("SHOW COLUMNS FROM `{$this->table}` LIKE '{$column}'");
            if ($stmt && $stmt->fetch() !== false) {
                $this->alterations[] = "ALTER TABLE `{$this->table}` DROP COLUMN `{$column}`";
            }
        }
        return $this;
    }

    /**
     * Remove um índice (apenas em modo ALTER)
     */
    public function dropIndex(string $indexName): self
    {
        if ($this->isAltering) {
            // Verifica se o índice existe antes de tentar remover
            $stmt = $this->pdo->query("SHOW INDEX FROM `{$this->table}` WHERE Key_name = '{$indexName}'");
            if ($stmt && $stmt->fetch() !== false) {
                $this->alterations[] = "ALTER TABLE `{$this->table}` DROP INDEX `{$indexName}`";
            }
        }
        return $this;
    }

    /**
     * Remove uma foreign key (apenas em modo ALTER)
     */
    public function dropForeign(string $fkName): self
    {
        if ($this->isAltering) {
            // Verifica se a FK existe antes de tentar remover
            $database = $this->pdo->query("SELECT DATABASE()")->fetchColumn();
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = ?
                AND TABLE_NAME = ?
                AND CONSTRAINT_NAME = ?
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            ");
            $stmt->execute([$database, $this->table, $fkName]);

            if ($stmt->fetchColumn() > 0) {
                $this->alterations[] = "ALTER TABLE `{$this->table}` DROP FOREIGN KEY `{$fkName}`";
            }
        }
        return $this;
    }

    /**
     * Alias para dropForeign - Remove uma foreign key (apenas em modo ALTER)
     *
     * Aceita tanto o nome da constraint quanto o nome da coluna.
     * Se for passado apenas o nome da coluna, assume o padrão fk_{tabela}_{coluna}
     */
    public function dropForeignKey(string $nameOrColumn): self
    {
        if ($this->isAltering) {
            $database = $this->pdo->query("SELECT DATABASE()")->fetchColumn();

            // Tenta primeiro com o nome exato fornecido
            $stmt = $this->pdo->prepare("
                SELECT CONSTRAINT_NAME
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = ?
                AND TABLE_NAME = ?
                AND CONSTRAINT_NAME = ?
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            ");
            $stmt->execute([$database, $this->table, $nameOrColumn]);

            if ($stmt->fetchColumn()) {
                // FK existe com o nome exato
                return $this->dropForeign($nameOrColumn);
            }

            // Tenta com o padrão fk_{tabela}_{coluna}
            $fkName = "fk_{$this->table}_{$nameOrColumn}";
            $stmt = $this->pdo->prepare("
                SELECT CONSTRAINT_NAME
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = ?
                AND TABLE_NAME = ?
                AND CONSTRAINT_NAME = ?
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            ");
            $stmt->execute([$database, $this->table, $fkName]);

            if ($stmt->fetchColumn()) {
                return $this->dropForeign($fkName);
            }
        }

        return $this;
    }

    /**
     * Define o engine da tabela
     */
    public function engine(string $engine): self
    {
        $this->engine = $engine;
        return $this;
    }

    /**
     * Define o charset da tabela
     */
    public function charset(string $charset): self
    {
        $this->charset = $charset;
        return $this;
    }

    /**
     * Constrói SQL para criar tabela
     */
    public function buildCreate(): string
    {
        $parts = $this->columns;

        // Adiciona índices
        $parts = array_merge($parts, $this->indexes);

        // Adiciona foreign keys
        foreach ($this->foreignKeys as $fk) {
            $parts[] = $fk->toCreateSql($this->table);
        }

        $columns = implode(",\n    ", $parts);

        return "CREATE TABLE `{$this->table}` (\n    {$columns}\n) ENGINE={$this->engine} DEFAULT CHARSET={$this->charset} COLLATE={$this->collation}";
    }

    /**
     * Constrói SQLs para alterar tabela
     */
    public function buildAlter(): array
    {
        return $this->alterations;
    }

    /**
     * Adiciona uma alteração à lista
     */
    public function addAlteration(string $sql): void
    {
        $this->alterations[] = $sql;
    }
}

/**
 * Classe auxiliar para definir colunas com fluent API
 */
class Column
{
    private SchemaBuilder $builder;
    private string $name;
    private string $type;
    private bool $nullable = false;
    private mixed $default = '__NO_DEFAULT__';
    private bool $unsigned = false;
    private bool $unique = false;
    private bool $autoIncrement = false;
    private ?string $after = null;

    public function __construct(SchemaBuilder $builder, string $name, string $type)
    {
        $this->builder = $builder;
        $this->name = $name;
        $this->type = $type;

        // Registra este objeto para finalização posterior
        $this->builder->registerPending($this);
    }

    public function nullable(): self
    {
        $this->nullable = true;
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;
        return $this;
    }

    public function unsigned(): self
    {
        $this->unsigned = true;
        return $this;
    }

    public function unique(): self
    {
        $this->unique = true;
        return $this;
    }

    public function autoIncrement(): self
    {
        $this->autoIncrement = true;
        return $this;
    }

    public function after(string $column): self
    {
        $this->after = $column;
        return $this;
    }

    /**
     * Finaliza a definição da coluna (chamado pelo SchemaBuilder)
     */
    public function finalize(): void
    {
        $columnDefinition = "`{$this->name}` {$this->type}";

        if ($this->unsigned) {
            $columnDefinition .= ' UNSIGNED';
        }

        if ($this->nullable) {
            $columnDefinition .= ' NULL';
        } else {
            $columnDefinition .= ' NOT NULL';
        }

        if ($this->default !== '__NO_DEFAULT__') {
            if ($this->default === null) {
                $columnDefinition .= ' DEFAULT NULL';
            } elseif (is_numeric($this->default)) {
                $columnDefinition .= ' DEFAULT ' . $this->default;
            } elseif (strtoupper($this->default) === 'CURRENT_TIMESTAMP') {
                $columnDefinition .= ' DEFAULT CURRENT_TIMESTAMP';
            } else {
                $columnDefinition .= " DEFAULT '" . addslashes($this->default) . "'";
            }
        }

        if ($this->autoIncrement) {
            $columnDefinition .= ' AUTO_INCREMENT';
        }

        if ($this->unique) {
            $columnDefinition .= ' UNIQUE';
        }

        // Se está em modo ALTER, gera ALTER TABLE statement
        if ($this->builder->isAltering()) {
            // Verifica se a coluna já existe
            $pdo = $this->builder->getPdo();
            $table = $this->builder->getTable();
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$this->name}'");

            if (!$stmt || $stmt->fetch() === false) {
                // Coluna não existe, adiciona
                $alterSql = "ALTER TABLE `{$table}` ADD COLUMN {$columnDefinition}";

                if ($this->after) {
                    $alterSql .= " AFTER `{$this->after}`";
                }

                $this->builder->addAlteration($alterSql);
            }
        } else {
            // Modo CREATE TABLE, adiciona normalmente
            $this->builder->addColumn($columnDefinition);
        }
    }
}

/**
 * Foreign Key - Fluent API para definir foreign keys
 *
 * Permite criar constraints de foreign key com sintaxe fluente
 */
class ForeignKey
{
    private string $column;
    private ?string $referencedTable = null;
    private ?string $referencedColumn = null;
    private string $onDelete = 'RESTRICT';
    private string $onUpdate = 'RESTRICT';
    private ?string $name = null;
    private ?SchemaBuilder $builder = null;

    public function __construct(string $column, ?SchemaBuilder $builder = null)
    {
        $this->column = $column;
        $this->builder = $builder;

        // Registra este objeto para finalização posterior
        if ($builder) {
            $builder->registerPending($this);
        }
    }

    /**
     * Define a tabela referenciada
     */
    public function on(string $table): self
    {
        $this->referencedTable = $table;
        return $this;
    }

    /**
     * Define a coluna referenciada
     */
    public function references(string $column): self
    {
        $this->referencedColumn = $column;
        return $this;
    }

    /**
     * Define o comportamento ON DELETE
     */
    public function onDelete(string $action): self
    {
        $this->onDelete = strtoupper($action);
        return $this;
    }

    /**
     * Define o comportamento ON UPDATE
     */
    public function onUpdate(string $action): self
    {
        $this->onUpdate = strtoupper($action);
        return $this;
    }

    /**
     * Atalho para ON DELETE CASCADE
     */
    public function cascadeOnDelete(): self
    {
        $this->onDelete = 'CASCADE';
        return $this;
    }

    /**
     * Atalho para ON DELETE SET NULL
     */
    public function nullOnDelete(): self
    {
        $this->onDelete = 'SET NULL';
        return $this;
    }

    /**
     * Atalho para ON DELETE RESTRICT
     */
    public function restrictOnDelete(): self
    {
        $this->onDelete = 'RESTRICT';
        return $this;
    }

    /**
     * Atalho para ON UPDATE CASCADE
     */
    public function cascadeOnUpdate(): self
    {
        $this->onUpdate = 'CASCADE';
        return $this;
    }

    /**
     * Atalho para ON UPDATE SET NULL
     */
    public function nullOnUpdate(): self
    {
        $this->onUpdate = 'SET NULL';
        return $this;
    }

    /**
     * Define um nome customizado para a FK
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Obtém o nome da coluna
     */
    public function getColumn(): string
    {
        return $this->column;
    }

    /**
     * Obtém o nome da foreign key (auto-gerado se não definido)
     */
    public function getName(string $table): string
    {
        if ($this->name) {
            return $this->name;
        }

        return "fk_{$table}_{$this->column}";
    }

    /**
     * Gera a definição SQL da foreign key para CREATE TABLE
     */
    public function toCreateSql(string $table): string
    {
        if (!$this->referencedTable || !$this->referencedColumn) {
            throw new \RuntimeException("Foreign key must define both table and column references");
        }

        $constraintName = $this->getName($table);

        return "CONSTRAINT `{$constraintName}` FOREIGN KEY (`{$this->column}`) " .
               "REFERENCES `{$this->referencedTable}` (`{$this->referencedColumn}`) " .
               "ON DELETE {$this->onDelete} ON UPDATE {$this->onUpdate}";
    }

    /**
     * Gera a definição SQL da foreign key para ALTER TABLE
     */
    public function toAlterSql(string $table): string
    {
        if (!$this->referencedTable || !$this->referencedColumn) {
            throw new \RuntimeException("Foreign key must define both table and column references");
        }

        $constraintName = $this->getName($table);

        return "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraintName}` " .
               "FOREIGN KEY (`{$this->column}`) " .
               "REFERENCES `{$this->referencedTable}` (`{$this->referencedColumn}`) " .
               "ON DELETE {$this->onDelete} ON UPDATE {$this->onUpdate}";
    }

    /**
     * Finaliza a definição da foreign key (chamado pelo SchemaBuilder)
     */
    public function finalize(): void
    {
        // Se tem builder e está em modo ALTER, adiciona automaticamente
        if ($this->builder && $this->builder->isAltering()) {
            if (!$this->referencedTable || !$this->referencedColumn) {
                return; // FK incompleta, não faz nada
            }

            $table = $this->builder->getTable();
            $pdo = $this->builder->getPdo();
            $fkName = $this->getName($table);

            // Verifica se a FK já existe
            $database = $pdo->query("SELECT DATABASE()")->fetchColumn();
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = ?
                AND TABLE_NAME = ?
                AND CONSTRAINT_NAME = ?
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            ");
            $stmt->execute([$database, $table, $fkName]);

            if ($stmt->fetchColumn() == 0) {
                // FK não existe, adiciona
                $this->builder->addAlteration($this->toAlterSql($table));
            }
        }
    }
}
