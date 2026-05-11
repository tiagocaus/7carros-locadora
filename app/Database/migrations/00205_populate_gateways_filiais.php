<?php

/**
 * Migracao: Popular tabela gateways_filiais
 *
 * Vincula todos os gateways existentes a todas as filiais do seu tenant.
 * Isso garante que gateways que antes estavam disponiveis para "todas as filiais"
 * (id_matriz_filial = NULL) agora tenham registros explicitos na tabela de juncao.
 */

use App\Database\Migration;

return new class extends Migration {
    public function up(): void
    {
        // Vincular cada gateway a todas as filiais do mesmo tenant
        $this->execute("
            INSERT IGNORE INTO gateways_filiais (id_gateway, id_matriz_filial, chave)
            SELECT g.id, mf.id, g.chave
            FROM gateways_pagamento g
            CROSS JOIN matrizes_filiais mf
            WHERE g.chave = mf.chave
            AND NOT EXISTS (
                SELECT 1 FROM gateways_filiais gf
                WHERE gf.id_gateway = g.id AND gf.id_matriz_filial = mf.id
            )
        ");
    }

    public function down(): void
    {
        // Nao e seguro reverter - os dados originais ja foram perdidos na migracao 00204
    }
};
