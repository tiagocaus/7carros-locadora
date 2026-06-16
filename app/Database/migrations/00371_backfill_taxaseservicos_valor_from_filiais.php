<?php

/**
 * Migration 00371: Backfill do valor fallback de taxas monetarias.
 *
 * Para taxas tipo MON, o valor oficial fica em taxaseservicos_valores_filiais.
 * A coluna taxaseservicos.valor e mantida como fallback/display e nao deve
 * permanecer zerada quando existem valores positivos por filial.
 */

use App\Core\Cache;
use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !$this->tableExists('taxaseservicos') ||
            !$this->tableExists('taxaseservicos_valores_filiais')
        ) {
            return;
        }

        $this->execute("
            UPDATE taxaseservicos t
            INNER JOIN (
                SELECT
                    chave,
                    id_taxaservico,
                    CAST(
                        SUBSTRING_INDEX(
                            GROUP_CONCAT(valor ORDER BY id_matriz_filial SEPARATOR ','),
                            ',',
                            1
                        ) AS DECIMAL(10,2)
                    ) AS valor_fallback
                FROM taxaseservicos_valores_filiais
                WHERE valor > 0
                GROUP BY chave, id_taxaservico
            ) vf ON vf.chave = t.chave AND vf.id_taxaservico = t.id
            SET
                t.valor = vf.valor_fallback,
                t.updated_at = NOW()
            WHERE t.tipo_valor = 'MON'
              AND t.valor = 0
        ");

        try {
            Cache::flush();
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        // No-op: nao zeramos fallback restaurado a partir dos valores por filial.
    }
};
