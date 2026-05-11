<?php

/**
 * Migration 00032: Padronizar Colunas da Tabela veiculos
 *
 * Renomeia 5 colunas para seguir o padrão de nomenclatura.
 * Tabela com 7k registros.
 *
 * Colunas renomeadas:
 * - matriz_filial → id_matriz_filial (filial proprietária)
 * - fornecedor → id_fornecedor
 * - grupo → id_grupo
 * - localizacao → id_matriz_filial_localizacao (filial onde está o veículo)
 * - plano_manutencao_id → id_plano_manutencao
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Mapeamento DE → PARA das colunas
     */
    private array $columnRenames = [
        'matriz_filial'       => 'id_matriz_filial',
        'fornecedor'          => 'id_fornecedor',
        'grupo'               => 'id_grupo',
        'localizacao'         => 'id_matriz_filial_localizacao',
        'plano_manutencao_id' => 'id_plano_manutencao',
    ];

    public function up(): void
    {
        foreach ($this->columnRenames as $oldName => $newName) {
            if ($this->columnExists('veiculos', $oldName) && !$this->columnExists('veiculos', $newName)) {
                $this->renameColumnPreservingType('veiculos', $oldName, $newName);
            }
        }
    }

    public function down(): void
    {
        // Reverte na ordem inversa (PARA → DE)
        $reverseRenames = array_flip($this->columnRenames);

        foreach ($reverseRenames as $newName => $oldName) {
            if ($this->columnExists('veiculos', $newName) && !$this->columnExists('veiculos', $oldName)) {
                $this->renameColumnPreservingType('veiculos', $newName, $oldName);
            }
        }
    }
};
