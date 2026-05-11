<?php

/**
 * Migration 00038: Padronizar Colunas da Tabela estoque
 *
 * Renomeia 2 colunas para seguir o padrão de nomenclatura.
 * Tabela com 929 registros.
 *
 * Colunas renomeadas:
 * - id_matrizfilial → id_matriz_filial
 * - id_fornecedores → id_fornecedor (singular)
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Mapeamento DE → PARA das colunas
     */
    private array $columnRenames = [
        'id_matrizfilial' => 'id_matriz_filial',
        'id_fornecedores' => 'id_fornecedor',
    ];

    public function up(): void
    {
        foreach ($this->columnRenames as $oldName => $newName) {
            if ($this->columnExists('estoque', $oldName) && !$this->columnExists('estoque', $newName)) {
                $this->renameColumnPreservingType('estoque', $oldName, $newName);
            }
        }
    }

    public function down(): void
    {
        // Reverte na ordem inversa (PARA → DE)
        $reverseRenames = array_flip($this->columnRenames);

        foreach ($reverseRenames as $newName => $oldName) {
            if ($this->columnExists('estoque', $newName) && !$this->columnExists('estoque', $oldName)) {
                $this->renameColumnPreservingType('estoque', $newName, $oldName);
            }
        }
    }
};
