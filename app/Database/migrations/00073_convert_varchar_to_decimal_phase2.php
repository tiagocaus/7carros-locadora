<?php

/**
 * Migration 00073: Converter VARCHAR → DECIMAL (Fase 2)
 *
 * Esta migration converte colunas monetárias de VARCHAR para DECIMAL
 * em tabelas que não foram incluídas na migration 00066.
 *
 * Total: 19 colunas em 9 tabelas
 *
 * Tabelas: formas_pagamento, grupos, locacoes, manutencoes,
 *          multas, promissorias, promocoes, taxaseservicos, veiculos
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Colunas a converter para DECIMAL(10,2)
     */
    private array $decimalColumns = [
        'formas_pagamento' => [
            'multa' => 'DECIMAL(10,2)',
        ],
        'grupos' => [
            'valor_condutor_adicional' => 'DECIMAL(10,2)',
            'valor_diaria' => 'DECIMAL(10,2)',
            'valor_diaria_controlada' => 'DECIMAL(10,2)',
            'valor_dia_kmlivre' => 'DECIMAL(10,2)',
            'valor_km' => 'DECIMAL(10,2)',
            'valor_km_retorno' => 'DECIMAL(10,2)',
            'valor_tolerancia' => 'DECIMAL(10,2)',
        ],
        'locacoes' => [
            'valor_condutor_adicional' => 'DECIMAL(10,2)',
            'valor_km_retorno' => 'DECIMAL(10,2)',
        ],
        'manutencoes' => [
            'total_servicos' => 'DECIMAL(10,2)',
        ],
        'multas' => [
            'valor' => 'DECIMAL(10,2)',
        ],
        'promissorias' => [
            'valor' => 'DECIMAL(10,2)',
        ],
        'promocoes' => [
            'valor' => 'DECIMAL(10,2)',
        ],
        'taxaseservicos' => [
            'valor' => 'DECIMAL(10,2)',
        ],
        'veiculos' => [
            'valor_compra' => 'DECIMAL(12,2)',
            'valor_por_fracao' => 'DECIMAL(10,2)',
            'valor_venda' => 'DECIMAL(12,2)',
        ],
    ];

    /**
     * Colunas que precisam de precisão especial (3 casas decimais)
     */
    private array $decimalColumns3 = [
        'formas_pagamento' => [
            'juros_por_dia' => 'DECIMAL(10,3)',
        ],
    ];

    public function up(): void
    {
        // Converter colunas com 2 casas decimais
        foreach ($this->decimalColumns as $table => $columns) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($columns as $column => $type) {
                if (!$this->columnExists($table, $column)) {
                    continue;
                }

                $this->convertToDecimal($table, $column, $type);
            }
        }

        // Converter colunas com 3 casas decimais
        foreach ($this->decimalColumns3 as $table => $columns) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($columns as $column => $type) {
                if (!$this->columnExists($table, $column)) {
                    continue;
                }

                $this->convertToDecimal($table, $column, $type);
            }
        }
    }

    public function down(): void
    {
        // Reverter para VARCHAR
        foreach ($this->decimalColumns as $table => $columns) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($columns as $column => $type) {
                if (!$this->columnExists($table, $column)) {
                    continue;
                }

                $this->modifyColumn($table, $column, 'VARCHAR(15)', [
                    'null' => true,
                ]);
            }
        }

        foreach ($this->decimalColumns3 as $table => $columns) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($columns as $column => $type) {
                if (!$this->columnExists($table, $column)) {
                    continue;
                }

                $this->modifyColumn($table, $column, 'VARCHAR(15)', [
                    'null' => true,
                ]);
            }
        }
    }

    /**
     * Converte uma coluna VARCHAR para DECIMAL
     */
    private function convertToDecimal(string $table, string $column, string $type): void
    {
        // Pular se ja e DECIMAL
        $currentType = $this->getColumnType($table, $column);
        if ($currentType && stripos($currentType, 'decimal') !== false) {
            return;
        }

        // Para colunas NOT NULL, fallback de invalidos e '0' (NULL daria erro em strict mode)
        $isNullable = $this->columnIsNullable($table, $column);
        $fallback = $isNullable ? 'NULL' : "'0'";

        try {
            // 1a. Formato BR completo com separador de milhar (35.000,00 -> 35000.00)
            $this->execute("
                UPDATE `{$table}`
                SET `{$column}` = REPLACE(REPLACE(`{$column}`, '.', ''), ',', '.')
                WHERE `{$column}` LIKE '%,%' AND `{$column}` LIKE '%.%'
            ");

            // 1b. Formato BR simples (50,00 -> 50.00)
            $this->execute("
                UPDATE `{$table}`
                SET `{$column}` = REPLACE(`{$column}`, ',', '.')
                WHERE `{$column}` LIKE '%,%'
            ");

            // 1c. Remove valores que nao casam decimal valido (escape correto: \\\\ em PHP -> \\ em SQL -> \ no regex)
            $this->execute("
                UPDATE `{$table}`
                SET `{$column}` = {$fallback}
                WHERE `{$column}` IS NOT NULL
                AND `{$column}` != ''
                AND `{$column}` NOT REGEXP '^-?[0-9]*\\\\.?[0-9]+$'
            ");

            // 1d. Strings vazias
            $this->execute("
                UPDATE `{$table}`
                SET `{$column}` = {$fallback}
                WHERE `{$column}` = ''
            ");
        } catch (\Exception $e) {
            // Ignora erros de limpeza
        }

        // 2. Alterar o tipo da coluna (mantendo nullability original)
        $this->modifyColumn($table, $column, $type, [
            'null' => $isNullable,
            'default' => '0.00',
        ]);
    }
};
