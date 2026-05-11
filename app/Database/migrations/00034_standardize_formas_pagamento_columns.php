<?php

/**
 * Migration 00034: Padronizar Colunas da Tabela formas_pagamento
 *
 * Renomeia 2 colunas para seguir o padrão de nomenclatura snake_case.
 * Tabela com 2.6k registros.
 *
 * NOTA: Esta migration assume que a tabela já foi renomeada de 'formas'
 * para 'formas_pagamento' na migration 00028.
 *
 * Colunas renomeadas:
 * - nomeForma → nome
 * - lancarPago → lancar_pago
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Mapeamento DE → PARA das colunas
     */
    private array $columnRenames = [
        'nomeForma'  => 'nome',
        'lancarPago' => 'lancar_pago',
    ];

    public function up(): void
    {
        // Tenta primeiro com o nome novo da tabela, depois com o antigo
        $tableName = $this->tableExists('formas_pagamento') ? 'formas_pagamento' : 'formas';

        foreach ($this->columnRenames as $oldName => $newName) {
            if ($this->columnExists($tableName, $oldName) && !$this->columnExists($tableName, $newName)) {
                $this->renameColumnPreservingType($tableName, $oldName, $newName);
            }
        }
    }

    public function down(): void
    {
        $tableName = $this->tableExists('formas_pagamento') ? 'formas_pagamento' : 'formas';

        // Reverte na ordem inversa (PARA → DE)
        $reverseRenames = array_flip($this->columnRenames);

        foreach ($reverseRenames as $newName => $oldName) {
            if ($this->columnExists($tableName, $newName) && !$this->columnExists($tableName, $oldName)) {
                $this->renameColumnPreservingType($tableName, $newName, $oldName);
            }
        }
    }
};
