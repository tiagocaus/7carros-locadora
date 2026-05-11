<?php

/**
 * Migration 00039: Padronizar Colunas da Tabela agenda
 *
 * Renomeia 2 colunas para seguir o padrão de nomenclatura snake_case.
 *
 * Colunas renomeadas:
 * - dataIni → data_ini
 * - dataFim → data_fim
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Mapeamento DE → PARA das colunas
     */
    private array $columnRenames = [
        'dataIni' => 'data_ini',
        'dataFim' => 'data_fim',
    ];

    public function up(): void
    {
        foreach ($this->columnRenames as $oldName => $newName) {
            if ($this->columnExists('agenda', $oldName) && !$this->columnExists('agenda', $newName)) {
                $this->renameColumnPreservingType('agenda', $oldName, $newName);
            }
        }
    }

    public function down(): void
    {
        // Reverte na ordem inversa (PARA → DE)
        $reverseRenames = array_flip($this->columnRenames);

        foreach ($reverseRenames as $newName => $oldName) {
            if ($this->columnExists('agenda', $newName) && !$this->columnExists('agenda', $oldName)) {
                $this->renameColumnPreservingType('agenda', $newName, $oldName);
            }
        }
    }
};
