<?php

/**
 * Migration 00067: Adicionar Foreign Keys
 *
 * Esta migration adiciona constraints de foreign key para garantir
 * integridade referencial no banco de dados.
 *
 * IMPORTANTE:
 * - Executar APÓS as migrations de renomeação (00029-00040)
 * - Verificar dados órfãos antes de executar
 * - FKs usam SET NULL para não bloquear exclusões em dados legados
 *
 * Total: ~23 Foreign Keys
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Definição das Foreign Keys a serem criadas
     *
     * Formato:
     * 'tabela' => [
     *     ['coluna', 'tabela_referenciada', 'coluna_referenciada', 'on_delete', 'on_update'],
     * ]
     */
    private array $foreignKeys = [
        // Tabela locacoes
        'locacoes' => [
            ['id_cliente', 'clientes', 'id', 'SET NULL', 'CASCADE'],
            ['id_veiculo', 'veiculos', 'id', 'SET NULL', 'CASCADE'],
            ['id_forma_pagamento', 'formas_pagamento', 'id', 'SET NULL', 'CASCADE'],
            ['id_matriz_filial_retirada', 'matrizes_filiais', 'id', 'SET NULL', 'CASCADE'],
            ['id_matriz_filial_devolucao', 'matrizes_filiais', 'id', 'SET NULL', 'CASCADE'],
            ['id_conta_bloqueio', 'contas', 'id', 'SET NULL', 'CASCADE'],
            ['id_funcionario', 'funcionarios', 'id', 'SET NULL', 'CASCADE'],
        ],

        // Tabela contratos
        'contratos' => [
            ['id_cliente', 'clientes', 'id', 'SET NULL', 'CASCADE'],
            ['id_veiculo', 'veiculos', 'id', 'SET NULL', 'CASCADE'],
            ['id_forma_pagamento', 'formas_pagamento', 'id', 'SET NULL', 'CASCADE'],
            ['id_matriz_filial_retirada', 'matrizes_filiais', 'id', 'SET NULL', 'CASCADE'],
            ['id_conta_bloqueio', 'contas', 'id', 'SET NULL', 'CASCADE'],
            ['id_conta_deposito', 'contas', 'id', 'SET NULL', 'CASCADE'],
            ['id_funcionario', 'funcionarios', 'id', 'SET NULL', 'CASCADE'],
        ],

        // Tabela financeiro
        'financeiro' => [
            ['id_cliente', 'clientes', 'id', 'SET NULL', 'CASCADE'],
            ['id_veiculo', 'veiculos', 'id', 'SET NULL', 'CASCADE'],
            ['id_forma_pagamento', 'formas_pagamento', 'id', 'SET NULL', 'CASCADE'],
            ['id_matriz_filial', 'matrizes_filiais', 'id', 'SET NULL', 'CASCADE'],
            ['id_conta', 'contas', 'id', 'SET NULL', 'CASCADE'],
            ['id_funcionario', 'funcionarios', 'id', 'SET NULL', 'CASCADE'],
        ],

        // Tabela veiculos
        'veiculos' => [
            ['id_matriz_filial', 'matrizes_filiais', 'id', 'SET NULL', 'CASCADE'],
            ['id_grupo', 'grupos', 'id', 'SET NULL', 'CASCADE'],
            ['id_fornecedor', 'fornecedores', 'id', 'SET NULL', 'CASCADE'],
        ],

        // Tabela checklist
        'checklist' => [
            ['id_veiculo', 'veiculos', 'id', 'SET NULL', 'CASCADE'],
        ],

        // Tabela multas
        'multas' => [
            ['id_cliente', 'clientes', 'id', 'SET NULL', 'CASCADE'],
            ['id_veiculo', 'veiculos', 'id', 'SET NULL', 'CASCADE'],
            ['id_locacao', 'locacoes', 'id', 'SET NULL', 'CASCADE'],
        ],

        // Tabela manutencoes
        'manutencoes' => [
            ['id_veiculo', 'veiculos', 'id', 'SET NULL', 'CASCADE'],
            ['id_oficina', 'oficinas', 'id', 'SET NULL', 'CASCADE'],
            ['id_matriz_filial', 'matrizes_filiais', 'id', 'SET NULL', 'CASCADE'],
        ],

        // Tabela promissorias
        'promissorias' => [
            ['id_cliente', 'clientes', 'id', 'SET NULL', 'CASCADE'],
            ['id_financeiro', 'financeiro', 'id', 'SET NULL', 'CASCADE'],
            ['id_matriz_filial', 'matrizes_filiais', 'id', 'SET NULL', 'CASCADE'],
        ],

        // Tabela clientes
        'clientes' => [
            ['id_matriz_filial', 'matrizes_filiais', 'id', 'SET NULL', 'CASCADE'],
        ],

        // Tabela funcionarios
        'funcionarios' => [
            ['id_matriz_filial', 'matrizes_filiais', 'id', 'SET NULL', 'CASCADE'],
        ],

        // Tabela estoque
        'estoque' => [
            ['id_matriz_filial', 'matrizes_filiais', 'id', 'SET NULL', 'CASCADE'],
            ['id_fornecedor', 'fornecedores', 'id', 'SET NULL', 'CASCADE'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->foreignKeys as $table => $fks) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($fks as $fk) {
                [$column, $refTable, $refColumn, $onDelete, $onUpdate] = $fk;

                // Verifica se a coluna existe
                if (!$this->columnExists($table, $column)) {
                    continue;
                }

                // Verifica se a tabela referenciada existe
                if (!$this->tableExists($refTable)) {
                    continue;
                }

                // Para FK SET NULL, a coluna PRECISA ser NULLABLE — torna nullable antes
                if (strtoupper($onDelete) === 'SET NULL' && !$this->columnIsNullable($table, $column)) {
                    $currentType = $this->getColumnType($table, $column) ?: 'INT(100)';
                    // Remove flag UNSIGNED para passar separado em options
                    $isUnsigned = stripos($currentType, 'unsigned') !== false;
                    $baseType = trim(preg_replace('/\bunsigned\b/i', '', $currentType));
                    $this->modifyColumn($table, $column, $baseType, [
                        'unsigned' => $isUnsigned,
                        'null' => true,
                    ]);
                }

                // Limpa referências órfãs (registros apontando para IDs inexistentes)
                $canCreateFk = $this->cleanOrphanedReferences($table, $column, $refTable, $refColumn);

                if (!$canCreateFk) {
                    // Não foi possível limpar órfãos - pula esta FK
                    continue;
                }

                // Adiciona a FK se não existir
                $fkName = "fk_{$table}_{$column}";
                $this->addForeignKeyIfNotExists(
                    $table,
                    $column,
                    $refTable,
                    $refColumn,
                    $onDelete,
                    $onUpdate,
                    $fkName
                );
            }
        }
    }

    public function down(): void
    {
        foreach ($this->foreignKeys as $table => $fks) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($fks as $fk) {
                [$column] = $fk;
                $fkName = "fk_{$table}_{$column}";
                $this->dropForeignKeyIfExists($table, $fkName);
            }
        }
    }

    /**
     * Limpa referências órfãs antes de criar a FK
     *
     * Define como NULL os registros que apontam para IDs que não existem
     * na tabela referenciada (incluindo valor 0).
     *
     * @return bool true se a limpeza foi bem-sucedida, false se não foi possível
     */
    private function cleanOrphanedReferences(
        string $table,
        string $column,
        string $refTable,
        string $refColumn
    ): bool {
        // Verifica se a coluna permite NULL
        $isNullable = $this->columnIsNullable($table, $column);

        if (!$isNullable) {
            // Se não permite NULL, verifica se há órfãos
            // Se houver, não podemos criar a FK
            $sql = "
                SELECT COUNT(*) as total FROM `{$table}` t
                LEFT JOIN `{$refTable}` r ON t.`{$column}` = r.`{$refColumn}`
                WHERE t.`{$column}` IS NOT NULL
                AND r.`{$refColumn}` IS NULL
            ";
            $stmt = $this->pdo->query($sql);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (($result['total'] ?? 0) > 0) {
                // Há órfãos e a coluna não permite NULL - não podemos criar FK
                return false;
            }
            return true;
        }

        // Coluna permite NULL - converte valores órfãos para NULL
        // Primeiro, converte valor 0 para NULL (0 geralmente significa "não definido")
        try {
            $this->execute("
                UPDATE `{$table}`
                SET `{$column}` = NULL
                WHERE `{$column}` = 0
            ");
        } catch (\Exception $e) {
            // Ignora erros
        }

        // Agora converte referências órfãs para NULL
        try {
            $this->execute("
                UPDATE `{$table}` t
                LEFT JOIN `{$refTable}` r ON t.`{$column}` = r.`{$refColumn}`
                SET t.`{$column}` = NULL
                WHERE t.`{$column}` IS NOT NULL
                AND r.`{$refColumn}` IS NULL
            ");
        } catch (\Exception $e) {
            // Se falhar, ignora
        }

        return true;
    }
};
