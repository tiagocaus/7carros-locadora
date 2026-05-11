<?php

/**
 * Migration 00068: Adicionar Índices UNIQUE
 *
 * Esta migration adiciona constraints UNIQUE para garantir unicidade
 * de dados importantes por tenant (chave).
 *
 * IMPORTANTE: Antes de executar, verificar se existem duplicatas:
 *
 * SELECT chave, cpf_cnpj, COUNT(*) FROM clientes
 * GROUP BY chave, cpf_cnpj HAVING COUNT(*) > 1;
 *
 * Índices criados:
 * - clientes: (chave, cpf_cnpj) - CPF/CNPJ único por tenant
 * - funcionarios: (chave, email) - Email único por tenant
 * - veiculos: (chave, placa) - Placa única por tenant
 * - formas_pagamento: (chave, nome) - Nome único por tenant
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Índices UNIQUE a serem criados
     *
     * Formato: 'tabela' => [
     *     ['nome_indice', ['coluna1', 'coluna2']],
     * ]
     */
    private array $uniqueIndexes = [
        'clientes' => [
            ['uniq_clientes_chave_cpf', ['chave', 'cpf_cnpj']],
        ],
        'funcionarios' => [
            ['uniq_funcionarios_chave_email', ['chave', 'email']],
        ],
        'veiculos' => [
            ['uniq_veiculos_chave_placa', ['chave', 'placa']],
        ],
        'formas_pagamento' => [
            ['uniq_formas_pagamento_chave_nome', ['chave', 'nome']],
        ],
    ];

    public function up(): void
    {
        foreach ($this->uniqueIndexes as $table => $indexes) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($indexes as [$indexName, $columns]) {
                // Verifica se todas as colunas existem
                $allColumnsExist = true;
                foreach ($columns as $column) {
                    if (!$this->columnExists($table, $column)) {
                        $allColumnsExist = false;
                        break;
                    }
                }

                if (!$allColumnsExist) {
                    continue;
                }

                // Verifica se existem duplicatas antes de criar o índice
                if ($this->hasDuplicates($table, $columns)) {
                    // Log ou aviso - não cria o índice se houver duplicatas
                    continue;
                }

                // Adiciona o índice UNIQUE se não existir
                $this->addUniqueIndexIfNotExists($table, $columns, $indexName);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->uniqueIndexes as $table => $indexes) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($indexes as [$indexName]) {
                $this->dropIndexIfExists($table, $indexName);
            }
        }
    }

    /**
     * Verifica se existem duplicatas nas colunas especificadas
     */
    private function hasDuplicates(string $table, array $columns): bool
    {
        $columnsList = implode('`, `', $columns);

        $sql = "
            SELECT COUNT(*) as total
            FROM (
                SELECT `{$columnsList}`, COUNT(*) as cnt
                FROM `{$table}`
                WHERE " . $this->buildNotNullCondition($columns) . "
                GROUP BY `{$columnsList}`
                HAVING cnt > 1
            ) as duplicates
        ";

        $stmt = $this->pdo->query($sql);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return ($result['total'] ?? 0) > 0;
    }

    /**
     * Constrói condição WHERE para excluir valores NULL
     */
    private function buildNotNullCondition(array $columns): string
    {
        $conditions = [];
        foreach ($columns as $column) {
            $conditions[] = "`{$column}` IS NOT NULL";
            $conditions[] = "`{$column}` != ''";
        }
        return implode(' AND ', $conditions);
    }

    /**
     * Adiciona índice UNIQUE se não existir
     */
    private function addUniqueIndexIfNotExists(string $table, array $columns, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        $columnsList = implode('`, `', $columns);
        $sql = "ALTER TABLE `{$table}` ADD UNIQUE INDEX `{$indexName}` (`{$columnsList}`)";

        try {
            $this->execute($sql);
        } catch (\Exception $e) {
            // Se falhar por duplicatas, ignora silenciosamente
            // O DBA deve resolver manualmente
        }
    }
};
