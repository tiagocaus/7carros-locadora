<?php

/**
 * Migration 00074: Adicionar Índices em Colunas de Data
 *
 * Esta migration adiciona índices em colunas de data frequentemente
 * usadas em filtros e ordenação em tabelas grandes.
 *
 * Total: 8 índices
 *
 * Tabelas: financeiro, locacoes, clientes, manutencoes, contratos, multas
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Índices a criar
     *
     * Formato: 'tabela' => [
     *     ['nome_indice', 'coluna'],
     * ]
     */
    private array $indexes = [
        'financeiro' => [
            ['idx_financeiro_data_criada', 'data_criada'],
            ['idx_financeiro_data_pago', 'data_pago'],
        ],
        'locacoes' => [
            ['idx_locacoes_data_prevista', 'data_prevista'],
        ],
        'clientes' => [
            ['idx_clientes_data_cadastro', 'data_cadastro'],
        ],
        'manutencoes' => [
            ['idx_manutencoes_data_enviado', 'data_enviado'],
            ['idx_manutencoes_data_retorno', 'data_retorno'],
        ],
        'contratos' => [
            ['idx_contratos_data_fim', 'data_fim'],
        ],
        'multas' => [
            ['idx_multas_data_vencimento', 'data_vencimento'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $tableIndexes) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($tableIndexes as [$indexName, $column]) {
                if (!$this->columnExists($table, $column)) {
                    continue;
                }

                if ($this->indexExists($table, $indexName)) {
                    continue;
                }

                try {
                    $this->execute("
                        CREATE INDEX `{$indexName}` ON `{$table}` (`{$column}`)
                    ");
                } catch (\Exception $e) {
                    // Ignora erros (índice pode já existir com outro nome)
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $tableIndexes) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($tableIndexes as [$indexName, $column]) {
                $this->dropIndexIfExists($table, $indexName);
            }
        }
    }
};
