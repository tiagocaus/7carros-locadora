<?php

use App\Database\Migration;

/**
 * Migration: Backfill grupos_precos_dias_filiais
 *
 * Replica cada linha de grupos_precos_dias para cada filial do mesmo tenant.
 * Idempotente via LEFT JOIN + WHERE IS NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('grupos_precos_dias_filiais')) {
            return;
        }
        if (!$this->tableExists('grupos_precos_dias')) {
            return;
        }

        $this->execute("
            INSERT INTO grupos_precos_dias_filiais (
                chave, id_grupo, id_matriz_filial,
                tipo_plano, dia_inicio, dia_fim, valor
            )
            SELECT
                gpd.chave, gpd.id_grupo, mf.id,
                gpd.tipo_plano, gpd.dia_inicio, gpd.dia_fim, gpd.valor
            FROM grupos_precos_dias gpd
            INNER JOIN matrizes_filiais mf ON mf.chave = gpd.chave
            LEFT JOIN grupos_precos_dias_filiais gpdf
                ON gpdf.id_grupo = gpd.id_grupo
               AND gpdf.id_matriz_filial = mf.id
               AND gpdf.tipo_plano = gpd.tipo_plano
               AND gpdf.dia_inicio = gpd.dia_inicio
               AND (gpdf.dia_fim <=> gpd.dia_fim)
            WHERE gpdf.id IS NULL
        ");
    }

    public function down(): void
    {
        // Backfill nao tem rollback seguro. Use down() de 00309.
    }
};
