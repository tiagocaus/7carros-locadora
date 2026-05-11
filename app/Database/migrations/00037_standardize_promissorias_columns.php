<?php

/**
 * Migration 00037: Padronizar Colunas da Tabela promissorias
 *
 * Renomeia 3 colunas para seguir o padrão de nomenclatura.
 * Tabela com 416 registros.
 *
 * Colunas renomeadas:
 * - matriz_filial → id_matriz_filial
 * - id_fatura → id_financeiro (referência para tabela financeiro)
 * - codigo_cl → codigo_contrato_locacao
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Mapeamento DE → PARA das colunas
     */
    private array $columnRenames = [
        'matriz_filial' => 'id_matriz_filial',
        'id_fatura'     => 'id_financeiro',
        'codigo_cl'     => 'codigo_contrato_locacao',
    ];

    public function up(): void
    {
        foreach ($this->columnRenames as $oldName => $newName) {
            if ($this->columnExists('promissorias', $oldName) && !$this->columnExists('promissorias', $newName)) {
                $this->renameColumnPreservingType('promissorias', $oldName, $newName);
            }
        }
    }

    public function down(): void
    {
        // Reverte na ordem inversa (PARA → DE)
        $reverseRenames = array_flip($this->columnRenames);

        foreach ($reverseRenames as $newName => $oldName) {
            if ($this->columnExists('promissorias', $newName) && !$this->columnExists('promissorias', $oldName)) {
                $this->renameColumnPreservingType('promissorias', $newName, $oldName);
            }
        }
    }
};
