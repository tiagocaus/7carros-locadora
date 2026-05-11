<?php

use App\Database\Migration;

/**
 * Migration: Backfill grupos_precos_filiais
 *
 * Para cada grupo existente, cria 1 linha em grupos_precos_filiais
 * por filial do mesmo tenant, copiando os valores atuais de `grupos`.
 *
 * Idempotente: usa LEFT JOIN + WHERE IS NULL pra pular entries ja existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('grupos_precos_filiais')) {
            return;
        }

        $this->execute("
            INSERT INTO grupos_precos_filiais (
                chave, id_grupo, id_matriz_filial,
                valor_plano_km_pago, valor_plano_km_controlado, valor_plano_km_livre,
                valor_km_excedente, km_franquia,
                valor_seguro_carro, valor_seguro_terceiros,
                cobertura_carro, cobertura_terceiros,
                minutos_tolerancia, valor_tolerancia,
                valor_km_retorno, valor_condutor_adicional
            )
            SELECT
                g.chave, g.id, mf.id,
                COALESCE(g.valor_plano_km_pago, 0), COALESCE(g.valor_plano_km_controlado, 0), COALESCE(g.valor_plano_km_livre, 0),
                COALESCE(g.valor_km_excedente, 0), COALESCE(g.km_franquia, 0),
                COALESCE(g.valor_seguro_carro, 0), COALESCE(g.valor_seguro_terceiros, 0),
                COALESCE(g.cobertura_carro, 0), COALESCE(g.cobertura_terceiros, 0),
                COALESCE(g.minutos_tolerancia, 0), COALESCE(g.valor_tolerancia, 0),
                COALESCE(g.valor_km_retorno, 0), COALESCE(g.valor_condutor_adicional, 0)
            FROM grupos g
            INNER JOIN matrizes_filiais mf ON mf.chave = g.chave
            LEFT JOIN grupos_precos_filiais gpf
                ON gpf.id_grupo = g.id AND gpf.id_matriz_filial = mf.id
            WHERE gpf.id IS NULL
        ");
    }

    public function down(): void
    {
        // Backfill nao tem rollback seguro — down() no-op.
        // Se precisar reverter, rode down() da migration 00307 (DROP TABLE).
    }
};
