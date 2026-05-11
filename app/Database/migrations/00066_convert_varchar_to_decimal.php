<?php

/**
 * Migration 00066: Converter VARCHAR para DECIMAL em colunas monetárias
 *
 * Esta migration converte colunas que armazenam valores monetários
 * de VARCHAR para DECIMAL(10,2) para permitir cálculos precisos.
 *
 * IMPORTANTE: Executar APÓS as migrations de renomeação (00029-00040)
 * para que os nomes das colunas estejam atualizados.
 *
 * Tabelas afetadas:
 * - locacoes: 15 colunas DECIMAL + 1 INT
 * - contratos: 11 colunas DECIMAL
 * - financeiro: ~4 colunas DECIMAL (verificar)
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Colunas a converter para DECIMAL(10,2) por tabela
     */
    private array $decimalColumns = [
        'locacoes' => [
            'bloqueio_valor' => 'DECIMAL(10,2)',
            'deposito_valor' => 'DECIMAL(10,2)',
            'combustivel_valor' => 'DECIMAL(8,2)',
            'seguro_carro_valor' => 'DECIMAL(10,2)',
            'cobertura_carro_valor' => 'DECIMAL(10,2)',
            'seguro_terceiros_valor' => 'DECIMAL(10,2)',
            'cobertura_terceiros_valor' => 'DECIMAL(10,2)',
            'valor_desconto' => 'DECIMAL(10,2)',
            'km_livre_valor' => 'DECIMAL(10,2)',
            'diaria_valor' => 'DECIMAL(10,2)',
            'km_valor' => 'DECIMAL(8,2)',
            'km_controlado_valor' => 'DECIMAL(10,2)',
            'valor_tolerancia' => 'DECIMAL(10,2)',
            'total_fatura' => 'DECIMAL(10,2)',
            'total_pagar' => 'DECIMAL(10,2)',
        ],
        'contratos' => [
            'bloqueio_valor' => 'DECIMAL(10,2)',
            'deposito_valor' => 'DECIMAL(10,2)',
            'seguro_carro_valor' => 'DECIMAL(8,2)',
            'cobertura_carro_valor' => 'DECIMAL(10,2)',
            'seguro_terceiros_valor' => 'DECIMAL(8,2)',
            'cobertura_terceiros_valor' => 'DECIMAL(10,2)',
            'valor_desconto' => 'DECIMAL(10,2)',
            'total_fatura' => 'DECIMAL(10,2)',
            'primeiro_pagamento' => 'DECIMAL(10,2)',
            'valor_faturas_paga' => 'DECIMAL(15,2)',
            'total_pagar' => 'DECIMAL(10,2)',
        ],
        'financeiro' => [
            'valor' => 'DECIMAL(10,2)',
            'valor_original' => 'DECIMAL(10,2)',
            'multa' => 'DECIMAL(10,2)',
            'juros' => 'DECIMAL(10,2)',
        ],
    ];

    /**
     * Colunas a converter para INT UNSIGNED (não são valores monetários)
     */
    private array $intColumns = [
        'locacoes' => [
            'km_controlado_franquia' => 'INT UNSIGNED',
        ],
    ];

    public function up(): void
    {
        // Converte colunas DECIMAL
        foreach ($this->decimalColumns as $table => $columns) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($columns as $column => $type) {
                $this->convertColumn($table, $column, $type);
            }
        }

        // Converte colunas INT
        foreach ($this->intColumns as $table => $columns) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($columns as $column => $type) {
                $this->convertColumn($table, $column, $type);
            }
        }
    }

    public function down(): void
    {
        // Reverte colunas DECIMAL para VARCHAR
        foreach ($this->decimalColumns as $table => $columns) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($columns as $column => $type) {
                if (!$this->columnExists($table, $column)) {
                    continue;
                }

                $isNullable = $this->columnIsNullable($table, $column);
                $this->modifyColumn($table, $column, 'VARCHAR(20)', [
                    'null' => $isNullable,
                ]);
            }
        }

        // Reverte colunas INT para VARCHAR
        foreach ($this->intColumns as $table => $columns) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($columns as $column => $type) {
                if (!$this->columnExists($table, $column)) {
                    continue;
                }

                $isNullable = $this->columnIsNullable($table, $column);
                $this->modifyColumn($table, $column, 'VARCHAR(10)', [
                    'null' => $isNullable,
                ]);
            }
        }
    }

    /**
     * Converte uma coluna VARCHAR para o tipo especificado
     */
    private function convertColumn(string $table, string $column, string $type): void
    {
        if (!$this->columnExists($table, $column)) {
            return;
        }

        // Verifica se já é do tipo correto
        $currentType = $this->getColumnType($table, $column);
        if ($currentType && stripos($currentType, 'decimal') !== false) {
            return; // Já é DECIMAL
        }
        if ($currentType && stripos($currentType, 'int') !== false && stripos($type, 'INT') !== false) {
            return; // Já é INT
        }

        // Verifica se permite NULL
        $isNullable = $this->columnIsNullable($table, $column);

        // Para DECIMAL, precisamos normalizar valores antes de converter
        if (stripos($type, 'DECIMAL') !== false) {
            // 1a. Formato BR completo com separador de milhar (35.000,00 -> 35000.00)
            //     Remove pontos (milhar), depois troca virgula por ponto
            $this->execute("
                UPDATE `{$table}`
                SET `{$column}` = REPLACE(REPLACE(`{$column}`, '.', ''), ',', '.')
                WHERE `{$column}` LIKE '%,%' AND `{$column}` LIKE '%.%'
            ");

            // 1b. Formato BR simples (50,00 -> 50.00)
            $this->execute("
                UPDATE `{$table}`
                SET `{$column}` = REPLACE(`{$column}`, ',', '.')
                WHERE `{$column}` REGEXP '^-?[0-9]+,[0-9]+$'
            ");

            // 2. Zera apenas valores vazios ou lixo (escape correto: \\\\ em PHP -> \\ em SQL -> \ no regex)
            $this->execute("
                UPDATE `{$table}`
                SET `{$column}` = '0'
                WHERE `{$column}` IS NOT NULL
                AND (`{$column}` = '' OR `{$column}` NOT REGEXP '^-?[0-9]+(\\\\.[0-9]+)?$')
            ");

            // 3. Trunca valores que excedem o range do tipo destino (replica comportamento do _v2 que rodou em sql_mode permissivo)
            if (preg_match('/DECIMAL\((\d+),\s*(\d+)\)/i', $type, $m)) {
                $precision = (int)$m[1];
                $scale = (int)$m[2];
                $intDigits = $precision - $scale;
                $max = str_repeat('9', $intDigits) . '.' . str_repeat('9', $scale);
                $this->execute("
                    UPDATE `{$table}`
                    SET `{$column}` = '{$max}'
                    WHERE CAST(`{$column}` AS DECIMAL(20,4)) > {$max}
                ");
            }
        }

        // Para INT, limpa valores inválidos
        if (stripos($type, 'INT') !== false) {
            $this->execute("
                UPDATE `{$table}`
                SET `{$column}` = '0'
                WHERE `{$column}` IS NOT NULL
                AND (`{$column}` = '' OR `{$column}` NOT REGEXP '^-?[0-9]+$')
            ");

            // Para INT UNSIGNED, zera negativos (replica comportamento do _v2 que rodou em sql_mode permissivo)
            if (stripos($type, 'UNSIGNED') !== false) {
                $this->execute("
                    UPDATE `{$table}`
                    SET `{$column}` = '0'
                    WHERE CAST(`{$column}` AS SIGNED) < 0
                ");
            }
        }

        // Modifica o tipo da coluna
        $this->modifyColumn($table, $column, $type, [
            'null' => $isNullable,
            'default' => $isNullable ? null : 0,
        ]);
    }
};
