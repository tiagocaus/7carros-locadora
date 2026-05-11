<?php

/**
 * Migration 00070: Corrigir FKs Faltantes
 *
 * Esta migration:
 * 1. Altera colunas NOT NULL para permitir NULL
 * 2. Converte valores 0 e IDs órfãos para NULL
 * 3. Cria as Foreign Keys que faltaram na migration 00067
 *
 * FKs a criar:
 * - locacoes: id_cliente, id_veiculo, id_forma_pagamento, id_matriz_filial_retirada, id_matriz_filial_devolucao
 * - contratos: id_cliente, id_veiculo, id_forma_pagamento, id_matriz_filial_retirada
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Colunas que precisam ser alteradas para NULL e ter FKs criadas
     *
     * Formato: 'tabela' => [
     *     ['coluna', 'tabela_referenciada', 'coluna_referenciada'],
     * ]
     */
    private array $columnsToFix = [
        'locacoes' => [
            ['id_cliente', 'clientes', 'id'],
            ['id_veiculo', 'veiculos', 'id'],
            ['id_forma_pagamento', 'formas_pagamento', 'id'],
            ['id_matriz_filial_retirada', 'matrizes_filiais', 'id'],
            ['id_matriz_filial_devolucao', 'matrizes_filiais', 'id'],
        ],
        'contratos' => [
            ['id_cliente', 'clientes', 'id'],
            ['id_veiculo', 'veiculos', 'id'],
            ['id_forma_pagamento', 'formas_pagamento', 'id'],
            ['id_matriz_filial_retirada', 'matrizes_filiais', 'id'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->columnsToFix as $table => $columns) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($columns as [$column, $refTable, $refColumn]) {
                if (!$this->columnExists($table, $column)) {
                    continue;
                }

                if (!$this->tableExists($refTable)) {
                    continue;
                }

                $fkName = "fk_{$table}_{$column}";

                // Se a FK já existe, pula
                if ($this->foreignKeyExists($table, $fkName)) {
                    continue;
                }

                // 1. Altera coluna para permitir NULL (se ainda não permitir)
                if (!$this->columnIsNullable($table, $column)) {
                    $this->modifyColumn($table, $column, 'INT(100) UNSIGNED', [
                        'null' => true,
                    ]);
                }

                // 2. Converte valor 0 para NULL
                $this->execute("
                    UPDATE `{$table}`
                    SET `{$column}` = NULL
                    WHERE `{$column}` = 0
                ");

                // 3. Converte IDs órfãos para NULL
                $this->execute("
                    UPDATE `{$table}` t
                    LEFT JOIN `{$refTable}` r ON t.`{$column}` = r.`{$refColumn}`
                    SET t.`{$column}` = NULL
                    WHERE t.`{$column}` IS NOT NULL
                    AND r.`{$refColumn}` IS NULL
                ");

                // 4. Cria a FK
                try {
                    $this->addForeignKey(
                        $table,
                        $column,
                        $refTable,
                        $refColumn,
                        'SET NULL',  // ON DELETE
                        'CASCADE',   // ON UPDATE
                        $fkName
                    );
                } catch (\Exception $e) {
                    // Se falhar, ignora (pode haver outro problema)
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->columnsToFix as $table => $columns) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($columns as [$column, $refTable, $refColumn]) {
                $fkName = "fk_{$table}_{$column}";
                $this->dropForeignKeyIfExists($table, $fkName);
            }
        }

        // Nota: Não reverte colunas para NOT NULL pois pode haver NULLs legítimos
    }
};
