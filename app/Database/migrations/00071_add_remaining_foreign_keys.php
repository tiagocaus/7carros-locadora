<?php

/**
 * Migration 00071: Adicionar FKs Restantes
 *
 * Esta migration adiciona as Foreign Keys faltantes em todas as tabelas
 * que possuem colunas de referência sem constraints.
 *
 * Total: ~30 Foreign Keys
 *
 * Tabelas afetadas:
 * - checklist, clientes, clientes_arquivos, clientes_cartoes
 * - contratos (adicionais), estoque, financeiro (adicionais)
 * - formas_gateway, formas_pagamento, funcionarios_remember_tokens
 * - locacoes (adicionais), manutencoes, multas
 * - promissorias (adicional), taxaseservicos, veiculos (adicionais)
 *
 * Nota: financeiro.id_multa não terá FK para evitar dependência circular
 * Nota: Tabelas security_* foram ignoradas por decisão do usuário
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Definição das Foreign Keys a serem criadas
     *
     * Formato: 'tabela' => [
     *     ['coluna', 'tabela_referenciada', 'coluna_referenciada'],
     * ]
     */
    private array $foreignKeys = [
        'checklist' => [
            ['id_veiculo', 'veiculos', 'id'],
            ['id_modelo', 'checklist_modelos', 'id'],
        ],
        'clientes' => [
            ['id_matriz_filial', 'matrizes_filiais', 'id'],
        ],
        'clientes_arquivos' => [
            ['id_cliente', 'clientes', 'id'],
        ],
        'clientes_cartoes' => [
            ['id_cliente', 'clientes', 'id'],
        ],
        'contratos' => [
            ['id_conta', 'contas', 'id'],
            ['id_financeiro_deposito', 'financeiro', 'id'],
            ['id_grupo', 'grupos', 'id'],
        ],
        'estoque' => [
            ['id_fornecedor', 'fornecedores', 'id'],
            ['id_matriz_filial', 'matrizes_filiais', 'id'],
        ],
        'financeiro' => [
            ['id_fornecedor', 'fornecedores', 'id'],
            ['id_matriz_filial', 'matrizes_filiais', 'id'],
            // id_multa: sem FK para evitar dependência circular
            ['id_oficina', 'oficinas', 'id'],
            ['id_promissoria', 'promissorias', 'id'],
        ],
        'formas_gateway' => [
            ['id_matriz_filial', 'matrizes_filiais', 'id'],
        ],
        'formas_pagamento' => [
            ['id_conta', 'contas', 'id'],
        ],
        'funcionarios_remember_tokens' => [
            ['usuario_id', 'funcionarios', 'id'],
        ],
        'locacoes' => [
            ['id_conta', 'contas', 'id'],
            ['id_financeiro_deposito', 'financeiro', 'id'],
            ['id_grupo', 'grupos', 'id'],
        ],
        'manutencoes' => [
            ['id_cliente', 'clientes', 'id'],
            ['id_matriz_filial', 'matrizes_filiais', 'id'],
            ['id_veiculo', 'veiculos', 'id'],
        ],
        'multas' => [
            ['id_cliente', 'clientes', 'id'],
            ['id_financeiro', 'financeiro', 'id'],
            ['id_locacao', 'locacoes', 'id'],
            ['id_matriz_filial', 'matrizes_filiais', 'id'],
            ['id_veiculo', 'veiculos', 'id'],
        ],
        'promissorias' => [
            ['id_matriz_filial', 'matrizes_filiais', 'id'],
        ],
        'taxaseservicos' => [
            ['id_matriz_filial', 'matrizes_filiais', 'id'],
        ],
        'veiculos' => [
            ['id_fornecedor', 'fornecedores', 'id'],
            ['id_matriz_filial', 'matrizes_filiais', 'id'],
            ['id_matriz_filial_localizacao', 'matrizes_filiais', 'id'],
            ['id_plano_manutencao', 'manutencoes_plano', 'id'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->foreignKeys as $table => $fks) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($fks as [$column, $refTable, $refColumn]) {
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
                    $columnType = $this->getColumnType($table, $column);
                    if ($columnType) {
                        $this->modifyColumn($table, $column, $columnType, [
                            'null' => true,
                        ]);
                    }
                }

                // 2. Converte valor 0 para NULL
                try {
                    $this->execute("
                        UPDATE `{$table}`
                        SET `{$column}` = NULL
                        WHERE `{$column}` = 0
                    ");
                } catch (\Exception $e) {
                    // Ignora erros
                }

                // 3. Converte IDs órfãos para NULL
                try {
                    $this->execute("
                        UPDATE `{$table}` t
                        LEFT JOIN `{$refTable}` r ON t.`{$column}` = r.`{$refColumn}`
                        SET t.`{$column}` = NULL
                        WHERE t.`{$column}` IS NOT NULL
                        AND r.`{$refColumn}` IS NULL
                    ");
                } catch (\Exception $e) {
                    // Ignora erros
                }

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
        foreach ($this->foreignKeys as $table => $fks) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($fks as [$column, $refTable, $refColumn]) {
                $fkName = "fk_{$table}_{$column}";
                $this->dropForeignKeyIfExists($table, $fkName);
            }
        }

        // Nota: Não reverte colunas para NOT NULL pois pode haver NULLs legítimos
    }
};
