<?php

use App\Database\Migration;

/**
 * Migration: Backfill id_veiculo em financeiro
 *
 * Preenche o id_veiculo nos registros financeiros existentes de contratos e locacoes,
 * correlacionando a data de criacao (created_at) com o periodo do veiculo em
 * contratos_veiculos/locacoes_veiculos (data_saida <= created_at < data_entrada).
 *
 * Fallback: se nenhum periodo casar, usa o primeiro veiculo do contrato/locacao.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->columnExists('financeiro', 'id_veiculo')) {
            return;
        }

        // Contratos: correlacionar por periodo
        $this->execute("
            UPDATE financeiro f
            SET f.id_veiculo = (
                SELECT cv.id_veiculo
                FROM contratos_veiculos cv
                WHERE cv.id_contrato = f.id_contrato
                  AND cv.data_saida <= f.created_at
                  AND (cv.data_entrada IS NULL OR cv.data_entrada > f.created_at)
                ORDER BY cv.data_saida DESC
                LIMIT 1
            )
            WHERE f.id_veiculo IS NULL
              AND f.id_contrato IS NOT NULL
        ");

        // Locacoes: correlacionar por periodo
        $this->execute("
            UPDATE financeiro f
            SET f.id_veiculo = (
                SELECT lv.id_veiculo
                FROM locacoes_veiculos lv
                WHERE lv.id_locacao = f.id_locacao
                  AND lv.data_saida <= f.created_at
                  AND (lv.data_entrada IS NULL OR lv.data_entrada > f.created_at)
                ORDER BY lv.data_saida DESC
                LIMIT 1
            )
            WHERE f.id_veiculo IS NULL
              AND f.id_locacao IS NOT NULL
        ");

        // Fallback contratos: usar primeiro veiculo
        $this->execute("
            UPDATE financeiro f
            SET f.id_veiculo = (
                SELECT cv.id_veiculo
                FROM contratos_veiculos cv
                WHERE cv.id_contrato = f.id_contrato
                ORDER BY cv.data_saida ASC
                LIMIT 1
            )
            WHERE f.id_veiculo IS NULL
              AND f.id_contrato IS NOT NULL
        ");

        // Fallback locacoes: usar primeiro veiculo
        $this->execute("
            UPDATE financeiro f
            SET f.id_veiculo = (
                SELECT lv.id_veiculo
                FROM locacoes_veiculos lv
                WHERE lv.id_locacao = f.id_locacao
                ORDER BY lv.data_saida ASC
                LIMIT 1
            )
            WHERE f.id_veiculo IS NULL
              AND f.id_locacao IS NOT NULL
        ");
    }

    public function down(): void
    {
        // Limpar id_veiculo apenas dos registros de contratos e locacoes
        if ($this->columnExists('financeiro', 'id_veiculo')) {
            $this->execute("
                UPDATE financeiro
                SET id_veiculo = NULL
                WHERE id_contrato IS NOT NULL OR id_locacao IS NOT NULL
            ");
        }
    }
};
