<?php

/**
 * Migration 00033: Padronizar Colunas da Tabela grupos
 *
 * Renomeia 9 colunas para seguir o padrão de nomenclatura snake_case.
 * Tabela com 1.4k registros.
 *
 * Colunas renomeadas:
 * - grupo → nome (evita redundância com nome da tabela)
 * - minutoTolerancia → minuto_tolerancia
 * - valorTolerancia → valor_tolerancia
 * - arrayDiariasAtivo → array_diarias_ativo
 * - arrayDiarias → array_diarias
 * - arrayDiariasControladasAtivo → array_diarias_controladas_ativo
 * - arrayDiariasControladas → array_diarias_controladas
 * - arrayKmlivreAtivo → array_km_livre_ativo
 * - arrayKmlivre → array_km_livre
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Mapeamento DE → PARA das colunas
     */
    private array $columnRenames = [
        'grupo'                        => 'nome',
        'minutoTolerancia'             => 'minuto_tolerancia',
        'valorTolerancia'              => 'valor_tolerancia',
        'arrayDiariasAtivo'            => 'array_diarias_ativo',
        'arrayDiarias'                 => 'array_diarias',
        'arrayDiariasControladasAtivo' => 'array_diarias_controladas_ativo',
        'arrayDiariasControladas'      => 'array_diarias_controladas',
        'arrayKmlivreAtivo'            => 'array_km_livre_ativo',
        'arrayKmlivre'                 => 'array_km_livre',
    ];

    public function up(): void
    {
        foreach ($this->columnRenames as $oldName => $newName) {
            if ($this->columnExists('grupos', $oldName) && !$this->columnExists('grupos', $newName)) {
                $this->renameColumnPreservingType('grupos', $oldName, $newName);
            }
        }
    }

    public function down(): void
    {
        // Reverte na ordem inversa (PARA → DE)
        $reverseRenames = array_flip($this->columnRenames);

        foreach ($reverseRenames as $newName => $oldName) {
            if ($this->columnExists('grupos', $newName) && !$this->columnExists('grupos', $oldName)) {
                $this->renameColumnPreservingType('grupos', $newName, $oldName);
            }
        }
    }
};
