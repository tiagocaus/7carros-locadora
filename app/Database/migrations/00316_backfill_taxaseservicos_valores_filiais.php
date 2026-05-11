<?php

use App\Database\Migration;

/**
 * Migration: Backfill taxaseservicos_valores_filiais
 *
 * Para cada taxa com tipo_valor=MON vinculada a filiais em taxaseservicos_filiais,
 * cria uma entry em taxaseservicos_valores_filiais copiando o valor atual.
 * Idempotente via LEFT JOIN + WHERE IS NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('taxaseservicos_valores_filiais')) {
            return;
        }

        $this->execute("
            INSERT INTO taxaseservicos_valores_filiais (
                chave, id_taxaservico, id_matriz_filial, valor
            )
            SELECT
                t.chave, t.id, tsf.id_matriz_filial, COALESCE(t.valor, 0)
            FROM taxaseservicos t
            INNER JOIN taxaseservicos_filiais tsf ON tsf.id_taxaservico = t.id
            LEFT JOIN taxaseservicos_valores_filiais tsvf
                ON tsvf.id_taxaservico = t.id AND tsvf.id_matriz_filial = tsf.id_matriz_filial
            WHERE t.tipo_valor = 'MON'
              AND tsvf.id IS NULL
        ");
    }

    public function down(): void
    {
        // Backfill nao tem rollback seguro. Use down() de 00315.
    }
};
