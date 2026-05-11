<?php

/**
 * Migration 00036: Padronizar Colunas da Tabela clientes
 *
 * Renomeia 1 coluna para seguir o padrão de nomenclatura.
 * Tabela com 69k registros.
 *
 * Colunas renomeadas:
 * - matriz_filial → id_matriz_filial
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Mapeamento DE → PARA das colunas
     */
    private array $columnRenames = [
        'matriz_filial' => 'id_matriz_filial',
    ];

    public function up(): void
    {
        foreach ($this->columnRenames as $oldName => $newName) {
            if ($this->columnExists('clientes', $oldName) && !$this->columnExists('clientes', $newName)) {
                $this->renameColumnPreservingType('clientes', $oldName, $newName);
            }
        }
    }

    public function down(): void
    {
        // Reverte na ordem inversa (PARA → DE)
        $reverseRenames = array_flip($this->columnRenames);

        foreach ($reverseRenames as $newName => $oldName) {
            if ($this->columnExists('clientes', $newName) && !$this->columnExists('clientes', $oldName)) {
                $this->renameColumnPreservingType('clientes', $newName, $oldName);
            }
        }
    }
};
