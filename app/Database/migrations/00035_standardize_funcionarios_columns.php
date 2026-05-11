<?php

/**
 * Migration 00035: Padronizar Colunas da Tabela funcionarios
 *
 * Renomeia 2 colunas para seguir o padrão de nomenclatura.
 * Tabela com 786 registros.
 *
 * Colunas renomeadas:
 * - matriz_filial → id_matriz_filial
 * - role_id → id_role
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Mapeamento DE → PARA das colunas
     */
    private array $columnRenames = [
        'matriz_filial' => 'id_matriz_filial',
        'role_id'       => 'id_role',
    ];

    public function up(): void
    {
        foreach ($this->columnRenames as $oldName => $newName) {
            if ($this->columnExists('funcionarios', $oldName) && !$this->columnExists('funcionarios', $newName)) {
                $this->renameColumnPreservingType('funcionarios', $oldName, $newName);
            }
        }
    }

    public function down(): void
    {
        // Reverte na ordem inversa (PARA → DE)
        $reverseRenames = array_flip($this->columnRenames);

        foreach ($reverseRenames as $newName => $oldName) {
            if ($this->columnExists('funcionarios', $newName) && !$this->columnExists('funcionarios', $oldName)) {
                $this->renameColumnPreservingType('funcionarios', $newName, $oldName);
            }
        }
    }
};
