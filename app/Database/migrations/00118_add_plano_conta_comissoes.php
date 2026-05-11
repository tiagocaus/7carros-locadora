<?php

/**
 * Migration 00118: Criar plano de conta para comissoes de investidores
 *
 * Adiciona "Comissoes Investidores" como plano de conta de despesa
 * para os tenants existentes no sistema.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Inserir plano de conta para cada tenant que ainda nao tem
        // Usa hierarquia padrao para despesas (2.x)
        $this->execute("
            INSERT INTO planos_de_contas (chave, hierarquia, descricao, tipo)
            SELECT DISTINCT
                pc.chave,
                CONCAT('2.', (
                    SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(p2.hierarquia, '.', -1) AS UNSIGNED)), 0) + 1
                    FROM planos_de_contas p2
                    WHERE p2.chave = pc.chave
                    AND p2.hierarquia LIKE '2.%'
                    AND p2.hierarquia NOT LIKE '2.%.%'
                )),
                'Comissoes Investidores',
                'D'
            FROM planos_de_contas pc
            WHERE pc.chave IS NOT NULL
            AND pc.chave != ''
            AND pc.chave != '0'
            AND NOT EXISTS (
                SELECT 1 FROM planos_de_contas p3
                WHERE p3.chave = pc.chave
                AND p3.descricao = 'Comissoes Investidores'
            )
            GROUP BY pc.chave
        ");
    }

    public function down(): void
    {
        // Remover os planos de conta criados
        $this->execute("
            DELETE FROM planos_de_contas
            WHERE descricao = 'Comissoes Investidores'
            AND tipo = 'D'
        ");
    }
};
