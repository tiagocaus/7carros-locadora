<?php

/**
 * Migration 00075: Converter VARCHAR → DATE
 *
 * Esta migration converte colunas de data que usam VARCHAR para DATE,
 * permitindo ordenação e comparação correta.
 *
 * Tabela: veiculos
 * Colunas: data_compra, data_venda
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Colunas a converter
     */
    private array $dateColumns = [
        'veiculos' => [
            'data_compra',
            'data_venda',
        ],
    ];

    public function up(): void
    {
        foreach ($this->dateColumns as $table => $columns) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (!$this->columnExists($table, $column)) {
                    continue;
                }

                $this->convertToDate($table, $column);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->dateColumns as $table => $columns) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (!$this->columnExists($table, $column)) {
                    continue;
                }

                // Reverte para VARCHAR
                $this->modifyColumn($table, $column, 'VARCHAR(10)', [
                    'null' => true,
                ]);
            }
        }
    }

    /**
     * Converte uma coluna VARCHAR para DATE
     */
    private function convertToDate(string $table, string $column): void
    {
        // 1. Limpar valores vazios
        try {
            $this->execute("
                UPDATE `{$table}`
                SET `{$column}` = NULL
                WHERE `{$column}` = ''
                OR `{$column}` = '0000-00-00'
            ");
        } catch (\Exception $e) {
            // Ignora erros
        }

        // 2. Tentar converter formatos BR (dd/mm/yyyy) para ISO (yyyy-mm-dd)
        try {
            $this->execute("
                UPDATE `{$table}`
                SET `{$column}` = CONCAT(
                    SUBSTRING(`{$column}`, 7, 4), '-',
                    SUBSTRING(`{$column}`, 4, 2), '-',
                    SUBSTRING(`{$column}`, 1, 2)
                )
                WHERE `{$column}` LIKE '__/__/____'
            ");
        } catch (\Exception $e) {
            // Ignora erros
        }

        // 3. Limpar valores inválidos que não podem ser convertidos
        try {
            $this->execute("
                UPDATE `{$table}`
                SET `{$column}` = NULL
                WHERE `{$column}` IS NOT NULL
                AND `{$column}` NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
            ");
        } catch (\Exception $e) {
            // Ignora erros
        }

        // 4. Alterar o tipo da coluna
        $this->modifyColumn($table, $column, 'DATE', [
            'null' => true,
        ]);
    }
};
