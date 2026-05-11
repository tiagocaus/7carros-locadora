<?php

/**
 * Migration 00091: Normalizar VARCHAR na tabela grupos
 *
 * Converte campos VARCHAR para tipos apropriados:
 * - Campos monetários: VARCHAR → DECIMAL(12,2)
 * - Campos booleanos: VARCHAR/CHAR → TINYINT(1)
 *
 * Campos afetados:
 * - val_seguro_carro, val_seguro_terceiros, cobertura_carro, cobertura_terceiros
 * - array_diarias_ativo, array_diarias_controladas_ativo, array_km_livre_ativo, visivel_no_site
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Colunas monetárias a converter para DECIMAL(12,2)
     */
    private array $decimalColumns = [
        'val_seguro_carro',
        'val_seguro_terceiros',
        'cobertura_carro',
        'cobertura_terceiros',
    ];

    /**
     * Colunas booleanas a converter para TINYINT(1)
     * Chave = nome da coluna, valor = default
     */
    private array $booleanColumns = [
        'array_diarias_ativo' => 0,
        'array_diarias_controladas_ativo' => 0,
        'array_km_livre_ativo' => 0,
        'visivel_no_site' => 1,
    ];

    public function up(): void
    {
        // 1. Converter campos monetários
        foreach ($this->decimalColumns as $column) {
            if (!$this->columnExists('grupos', $column)) {
                continue;
            }

            $this->convertToDecimal($column);
        }

        // 2. Converter campos booleanos
        foreach ($this->booleanColumns as $column => $default) {
            if (!$this->columnExists('grupos', $column)) {
                continue;
            }

            $this->convertToBoolean($column, $default);
        }
    }

    public function down(): void
    {
        // 1. Reverter campos monetários para VARCHAR
        foreach ($this->decimalColumns as $column) {
            if (!$this->columnExists('grupos', $column)) {
                continue;
            }

            $this->modifyColumn('grupos', $column, 'VARCHAR(15)', [
                'null' => true,
            ]);
        }

        // 2. Reverter campos booleanos para VARCHAR/CHAR
        foreach ($this->booleanColumns as $column => $default) {
            if (!$this->columnExists('grupos', $column)) {
                continue;
            }

            // Primeiro converte os valores de volta para S/N
            $this->execute("
                UPDATE `grupos`
                SET `{$column}` = CASE
                    WHEN `{$column}` = 1 THEN 'S'
                    ELSE 'N'
                END
            ");

            // Determina o tipo original
            $type = ($column === 'visivel_no_site') ? 'CHAR(1)' : 'VARCHAR(1)';
            $defaultChar = ($default === 1) ? 'S' : null;

            $this->modifyColumn('grupos', $column, $type, [
                'null' => ($column !== 'visivel_no_site'),
                'default' => $defaultChar,
            ]);
        }
    }

    /**
     * Converte uma coluna VARCHAR para DECIMAL(12,2)
     */
    private function convertToDecimal(string $column): void
    {
        // 1. Limpar valores inválidos
        try {
            // Substituir vírgula por ponto
            $this->execute("
                UPDATE `grupos`
                SET `{$column}` = REPLACE(`{$column}`, ',', '.')
                WHERE `{$column}` LIKE '%,%'
            ");

            // Remover caracteres não numéricos
            $this->execute("
                UPDATE `grupos`
                SET `{$column}` = NULL
                WHERE `{$column}` IS NOT NULL
                AND `{$column}` != ''
                AND `{$column}` NOT REGEXP '^-?[0-9]*\\.?[0-9]+$'
            ");

            // Converter strings vazias para NULL
            $this->execute("
                UPDATE `grupos`
                SET `{$column}` = NULL
                WHERE `{$column}` = ''
            ");
        } catch (\Exception $e) {
            // Ignora erros de limpeza
        }

        // 2. Alterar o tipo da coluna
        $this->modifyColumn('grupos', $column, 'DECIMAL(12,2)', [
            'null' => true,
            'default' => '0.00',
        ]);
    }

    /**
     * Converte uma coluna VARCHAR/CHAR para TINYINT(1)
     */
    private function convertToBoolean(string $column, int $default): void
    {
        // 1. Converter valores 'S'/'N' para 1/0
        $this->execute("
            UPDATE `grupos`
            SET `{$column}` = CASE
                WHEN UPPER(`{$column}`) = 'S' THEN '1'
                ELSE '0'
            END
        ");

        // 2. Alterar o tipo da coluna
        $this->modifyColumn('grupos', $column, 'TINYINT(1)', [
            'null' => false,
            'default' => $default,
        ]);
    }
};
