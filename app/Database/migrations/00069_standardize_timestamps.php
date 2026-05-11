<?php

/**
 * Migration 00069: Padronizar TIMESTAMP
 *
 * Esta migration corrige inconsistências entre TIMESTAMP e DATETIME
 * nas colunas created_at e updated_at.
 *
 * Padrão definido:
 * - created_at: TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 * - updated_at: TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
 *
 * Tabelas afetadas:
 * - message_template_defaults
 * - message_templates
 * - Outras com inconsistência detectada
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Tabelas que precisam ter timestamps padronizados
     */
    private array $tablesToFix = [
        'message_template_defaults',
        'message_templates',
    ];

    public function up(): void
    {
        foreach ($this->tablesToFix as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }

            $this->standardizeTimestamps($table);
        }
    }

    public function down(): void
    {
        // Não reverte - o padrão antigo era inconsistente
        // e não há benefício em reverter para ele
    }

    /**
     * Padroniza as colunas de timestamp de uma tabela
     */
    private function standardizeTimestamps(string $table): void
    {
        // Padroniza created_at
        if ($this->columnExists($table, 'created_at')) {
            $currentType = $this->getColumnType($table, 'created_at');

            // Se não é TIMESTAMP, converte
            if ($currentType && stripos($currentType, 'timestamp') === false) {
                $this->modifyColumn($table, 'created_at', 'TIMESTAMP', [
                    'null' => true,
                    'default' => 'CURRENT_TIMESTAMP',
                ]);
            }
        }

        // Padroniza updated_at
        if ($this->columnExists($table, 'updated_at')) {
            $currentType = $this->getColumnType($table, 'updated_at');

            // Se não é TIMESTAMP, converte
            if ($currentType && stripos($currentType, 'timestamp') === false) {
                // Para updated_at com ON UPDATE, precisamos usar SQL direto
                $this->execute("
                    ALTER TABLE `{$table}`
                    MODIFY COLUMN `updated_at` TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP
                ");
            }
        }
    }
};
