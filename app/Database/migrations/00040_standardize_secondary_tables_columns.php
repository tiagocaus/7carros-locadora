<?php

/**
 * Migration 00040: Padronizar Colunas de Tabelas Secundárias
 *
 * Renomeia colunas em várias tabelas menores para seguir o padrão de nomenclatura.
 *
 * Tabelas afetadas:
 * - manutencoes: id_matrizfilial → id_matriz_filial
 * - multas: id_matrizfilial → id_matriz_filial
 * - formas_gateway: id_matrizfilial → id_matriz_filial
 * - contas: conta_bancaria → e_conta_bancaria
 * - taxas_servicos: matriz_filial → id_matriz_filial
 * - fornecedores: decarro → de_carro
 * - promocoes: onde_usar → onde_exibir
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Configuração de renomeações por tabela
     * Formato: 'tabela' => ['coluna_antiga' => 'coluna_nova']
     */
    private array $tableRenames = [
        'manutencoes' => [
            'id_matrizfilial' => 'id_matriz_filial',
        ],
        'multas' => [
            'id_matrizfilial' => 'id_matriz_filial',
        ],
        'formas_gateway' => [
            'id_matrizfilial' => 'id_matriz_filial',
        ],
        'contas' => [
            'conta_bancaria' => 'e_conta_bancaria',
        ],
        'fornecedores' => [
            'decarro' => 'de_carro',
        ],
        'promocoes' => [
            'onde_usar' => 'onde_exibir',
        ],
    ];

    /**
     * Tabelas que podem ter sido renomeadas
     * Formato: 'nome_antigo' => 'nome_novo'
     */
    private array $renamedTables = [
        'taxaseservicos' => 'taxas_servicos',
    ];

    /**
     * Renomeações para tabelas que podem ter nomes diferentes
     */
    private array $conditionalRenames = [
        'taxaseservicos|taxas_servicos' => [
            'matriz_filial' => 'id_matriz_filial',
        ],
    ];

    public function up(): void
    {
        // Processa renomeações diretas
        foreach ($this->tableRenames as $table => $columns) {
            if ($this->tableExists($table)) {
                foreach ($columns as $oldName => $newName) {
                    if ($this->columnExists($table, $oldName) && !$this->columnExists($table, $newName)) {
                        $this->renameColumnPreservingType($table, $oldName, $newName);
                    }
                }
            }
        }

        // Processa tabelas que podem ter sido renomeadas
        foreach ($this->conditionalRenames as $tableOptions => $columns) {
            $tables = explode('|', $tableOptions);
            $targetTable = null;

            foreach ($tables as $table) {
                if ($this->tableExists($table)) {
                    $targetTable = $table;
                    break;
                }
            }

            if ($targetTable) {
                foreach ($columns as $oldName => $newName) {
                    if ($this->columnExists($targetTable, $oldName) && !$this->columnExists($targetTable, $newName)) {
                        $this->renameColumnPreservingType($targetTable, $oldName, $newName);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Reverte renomeações diretas
        foreach ($this->tableRenames as $table => $columns) {
            if ($this->tableExists($table)) {
                $reverseColumns = array_flip($columns);
                foreach ($reverseColumns as $newName => $oldName) {
                    if ($this->columnExists($table, $newName) && !$this->columnExists($table, $oldName)) {
                        $this->renameColumnPreservingType($table, $newName, $oldName);
                    }
                }
            }
        }

        // Reverte tabelas condicionais
        foreach ($this->conditionalRenames as $tableOptions => $columns) {
            $tables = explode('|', $tableOptions);
            $targetTable = null;

            foreach ($tables as $table) {
                if ($this->tableExists($table)) {
                    $targetTable = $table;
                    break;
                }
            }

            if ($targetTable) {
                $reverseColumns = array_flip($columns);
                foreach ($reverseColumns as $newName => $oldName) {
                    if ($this->columnExists($targetTable, $newName) && !$this->columnExists($targetTable, $oldName)) {
                        $this->renameColumnPreservingType($targetTable, $newName, $oldName);
                    }
                }
            }
        }
    }
};
