<?php

/**
 * Migration 00092: Renomear colunas da tabela grupos
 *
 * Padroniza nomenclatura das colunas para consistência e clareza.
 *
 * Renomeações:
 * - val_seguro_* → valor_seguro_* (padronizar prefixo)
 * - valor_diaria* → valor_plano_* (clareza sobre planos)
 * - km_gratis_dia → km_franquia (termo do domínio)
 * - array_* → tabela_* / usar_tabela_* (remove prefixo confuso)
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Mapeamento: coluna_antiga => coluna_nova
     */
    private array $renames = [
        // Padronizar prefixo valor_
        'val_seguro_carro' => 'valor_seguro_carro',
        'val_seguro_terceiros' => 'valor_seguro_terceiros',

        // Clareza nos nomes dos planos
        'valor_diaria' => 'valor_plano_diaria',
        'valor_diaria_controlada' => 'valor_plano_km_controlado',
        'valor_dia_kmlivre' => 'valor_plano_km_livre',
        'valor_km' => 'valor_km_excedente',

        // Termo do domínio
        'km_gratis_dia' => 'km_franquia',

        // Plural correto
        'minuto_tolerancia' => 'minutos_tolerancia',

        // Remover prefixo array_ confuso
        'array_diarias_ativo' => 'usar_tabela_diarias',
        'array_diarias' => 'tabela_diarias',
        'array_diarias_controladas_ativo' => 'usar_tabela_km_controlado',
        'array_diarias_controladas' => 'tabela_km_controlado',
        'array_km_livre_ativo' => 'usar_tabela_km_livre',
        'array_km_livre' => 'tabela_km_livre',
    ];

    public function up(): void
    {
        foreach ($this->renames as $oldName => $newName) {
            if (!$this->columnExists('grupos', $oldName)) {
                continue;
            }

            $this->renameColumnPreservingType('grupos', $oldName, $newName);
        }
    }

    public function down(): void
    {
        // Inverter o mapeamento
        $reversed = array_flip($this->renames);

        foreach ($reversed as $oldName => $newName) {
            if (!$this->columnExists('grupos', $oldName)) {
                continue;
            }

            $this->renameColumnPreservingType('grupos', $oldName, $newName);
        }
    }
};
