<?php

/**
 * Migration 00076: Converter atualizacoes.data VARCHAR → DATE
 *
 * Esta migration converte a coluna de data que usa VARCHAR para DATE,
 * permitindo ordenação e comparação correta.
 *
 * Tabela: atualizacoes (393 registros)
 * Coluna: data (formato BR: "19/09/2012")
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'atualizacoes';
        $column = 'data';

        if (!$this->tableExists($table)) {
            return;
        }

        if (!$this->columnExists($table, $column)) {
            return;
        }

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

        // 2. Converter formato BR (dd/mm/yyyy) para ISO (yyyy-mm-dd)
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

    public function down(): void
    {
        $table = 'atualizacoes';
        $column = 'data';

        if (!$this->tableExists($table)) {
            return;
        }

        if (!$this->columnExists($table, $column)) {
            return;
        }

        // Reverte para VARCHAR
        $this->modifyColumn($table, $column, 'VARCHAR(10)', [
            'null' => true,
        ]);
    }
};
