<?php

/**
 * Migration 00041: Padronizar Tipos de Colunas ID/FK
 *
 * Altera todas as colunas de ID e FK para o tipo padrão: INT(100) UNSIGNED
 *
 * Esta migration é executada por último para garantir que todas as
 * renomeações de colunas já foram feitas.
 *
 * NOTA: Esta migration pode demorar em tabelas grandes pois altera
 * a estrutura das colunas.
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Configuração de colunas por tabela que precisam ser INT(100) UNSIGNED
     * Formato: 'tabela' => ['coluna1', 'coluna2', ...]
     */
    private array $columnsToStandardize = [
        // Tabela financeiro - colunas que já tinham nomes corretos
        'financeiro' => [
            'id_cliente',
            'id_veiculo',
            'id_multa',
            'id_oficina',
            'id_funcionario',
            'id_conta',
            'id_promissoria',
            'id_matriz_filial',
            'id_forma_pagamento',
            'id_fornecedor',
        ],

        // Tabela clientes_arquivos
        'clientes_arquivos' => [
            'id_cliente',
        ],

        // Tabela clientes_cartoes
        'clientes_cartoes' => [
            'id_cliente',
        ],

        // Tabela contratos
        'contratos' => [
            'id_cliente',
            'id_grupo',
            'id_veiculo',
            'id_forma_pagamento',
            'id_matriz_filial_retirada',
            'id_conta_bloqueio',
            'id_conta_deposito',
            'id_financeiro_deposito',
            'id_funcionario',
            'id_conta',
        ],

        // Tabela locacoes
        'locacoes' => [
            'id_cliente',
            'id_grupo',
            'id_veiculo',
            'id_forma_pagamento',
            'id_matriz_filial_retirada',
            'id_matriz_filial_devolucao',
            'id_conta_bloqueio',
            'id_financeiro_deposito',
            'id_funcionario',
            'id_conta',
        ],

        // Tabela veiculos
        'veiculos' => [
            'id_matriz_filial',
            'id_fornecedor',
            'id_grupo',
            'id_matriz_filial_localizacao',
            'id_plano_manutencao',
        ],

        // Tabela manutencoes
        'manutencoes' => [
            'id_veiculo',
            'id_oficina',
            'id_matriz_filial',
        ],

        // Tabela multas
        'multas' => [
            'id_cliente',
            'id_veiculo',
            'id_financeiro',
            'id_locacao',
            'id_matriz_filial',
        ],

        // Tabela promissorias
        'promissorias' => [
            'id_cliente',
            'id_financeiro',
            'id_matriz_filial',
        ],

        // Tabela checklist
        'checklist' => [
            'id_veiculo',
            'id_modelo',
        ],

        // Tabela estoque
        'estoque' => [
            'id_matriz_filial',
            'id_fornecedor',
        ],

        // Tabela funcionarios
        'funcionarios' => [
            'id_matriz_filial',
            'id_role',
        ],

        // Tabela clientes
        'clientes' => [
            'id_matriz_filial',
        ],
    ];

    /**
     * Tipos onde comparar com '' é seguro antes de MODIFY (varchar legado etc.).
     * Em INT/BIGINT/DECIMAL/DATE, WHERE coluna = '' dispara erro 1292 no strict SQL mode.
     */
    private function columnTypeStoresStringsWithEmptyQuotes(string $type): bool
    {
        return (bool) preg_match(
            '/\b(varchar|char|tinytext|text|mediumtext|longtext|blob|tinyblob|mediumblob|longblob|binary|varbinary|enum|set)\b/i',
            $type
        );
    }

    public function up(): void
    {
        // Tratamento N:N para estoque.id_fornecedor (1 produto pode ter N fornecedores)
        // Migrar para tabela pivot estoque_fornecedores antes de converter id_fornecedor para INT
        if ($this->tableExists('estoque') && $this->columnExists('estoque', 'id_fornecedor')) {
            if (!$this->tableExists('estoque_fornecedores')) {
                $this->execute("
                    CREATE TABLE estoque_fornecedores (
                        id INT(100) UNSIGNED NOT NULL AUTO_INCREMENT,
                        chave VARCHAR(45) NOT NULL,
                        id_estoque INT(100) UNSIGNED NOT NULL,
                        id_fornecedor INT(100) UNSIGNED NOT NULL,
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        UNIQUE KEY uk_estoque_fornecedor (id_estoque, id_fornecedor),
                        KEY idx_chave (chave),
                        KEY idx_id_estoque (id_estoque),
                        KEY idx_id_fornecedor (id_fornecedor)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            }

            $rowsCsv = $this->db()->table('estoque')
                ->select(['id', 'chave', 'id_fornecedor'])
                ->whereRaw("id_fornecedor LIKE '%,%'")
                ->get();
            foreach ($rowsCsv as $row) {
                $ids = array_filter(array_map('trim', explode(',', $row['id_fornecedor'])), 'is_numeric');
                foreach ($ids as $idForn) {
                    $this->execute(sprintf(
                        "INSERT IGNORE INTO estoque_fornecedores (chave, id_estoque, id_fornecedor) VALUES (%s, %d, %d)",
                        "'" . addslashes($row['chave']) . "'",
                        (int)$row['id'],
                        (int)$idForn
                    ));
                }
            }

            $this->execute("
                INSERT IGNORE INTO estoque_fornecedores (chave, id_estoque, id_fornecedor)
                SELECT chave, id, CAST(id_fornecedor AS UNSIGNED)
                FROM estoque
                WHERE id_fornecedor IS NOT NULL
                  AND id_fornecedor != ''
                  AND id_fornecedor REGEXP '^[0-9]+$'
            ");

            $this->execute("UPDATE estoque SET id_fornecedor = SUBSTRING_INDEX(id_fornecedor, ',', 1) WHERE id_fornecedor LIKE '%,%'");
        }

        foreach ($this->columnsToStandardize as $table => $columns) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (!$this->columnExists($table, $column)) {
                    continue;
                }

                // Verifica se a coluna já é INT(100) UNSIGNED
                $currentType = $this->getColumnType($table, $column);
                if ($currentType && stripos($currentType, 'int(100)') !== false && stripos($currentType, 'unsigned') !== false) {
                    continue; // Já está no tipo correto
                }

                // Verifica se a coluna permite NULL
                $isNullable = $this->columnIsNullable($table, $column);

                // Normaliza dados legacy ('' -> NULL ou 0) só em colunas texto/enum —
                // Em INT já numéricas, WHERE coluna = '' gera erro 1292 no strict SQL mode.
                $typeStr = $currentType ?: '';
                if ($this->columnTypeStoresStringsWithEmptyQuotes($typeStr)) {
                    if ($isNullable) {
                        $this->execute("UPDATE `$table` SET `$column` = NULL WHERE `$column` = ''");
                    } else {
                        $this->execute("UPDATE `$table` SET `$column` = 0 WHERE `$column` = ''");
                    }
                }

                // Altera para INT(100) UNSIGNED
                $this->modifyColumn($table, $column, 'INT(100)', [
                    'unsigned' => true,
                    'null' => $isNullable,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Não é possível reverter para os tipos originais pois não foram armazenados
        // Esta migration é considerada "one-way" - os tipos anteriores eram inconsistentes
        // e não há benefício em reverter para eles
    }
};
