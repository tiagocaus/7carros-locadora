<?php

use App\Database\Migration;

/**
 * Migration: Backfill promocoes_valores_filiais — correcao do tipo 'DFIX'
 *
 * A 00312 usava `tipo = 'FIX'` mas os dados reais sao `DFIX` (e 'DPOR' pra percentual),
 * entao nada foi backfilled. Esta migration refaz com o valor correto.
 * Idempotente via LEFT JOIN + IS NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('promocoes_valores_filiais')) {
            return;
        }

        $this->execute("
            INSERT INTO promocoes_valores_filiais (
                chave, id_promocao, id_matriz_filial, valor
            )
            SELECT
                p.chave, p.id, pf.id_matriz_filial, COALESCE(p.valor, 0)
            FROM promocoes p
            INNER JOIN promocoes_filiais pf ON pf.id_promocao = p.id
            LEFT JOIN promocoes_valores_filiais pvf
                ON pvf.id_promocao = p.id AND pvf.id_matriz_filial = pf.id_matriz_filial
            WHERE p.tipo = 'DFIX'
              AND pvf.id IS NULL
        ");
    }

    public function down(): void
    {
        // Backfill nao tem rollback seguro.
    }
};
