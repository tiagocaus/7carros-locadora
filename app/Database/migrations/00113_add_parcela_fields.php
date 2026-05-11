<?php

/**
 * Migration 00113: Adicionar campos de parcelamento
 *
 * Adiciona campos para suportar parcelamento de lancamentos financeiros:
 * 1. total_parcelas - Numero total de parcelas (ex: 12 para 12x)
 * 2. id_financeiro_origem - FK para a primeira parcela (parcela pai)
 *
 * NOTA: A coluna `parcela` (VARCHAR) permanece inalterada para manter
 * compatibilidade com registros legados.
 *
 * Estrutura esperada:
 * - Parcela 1: parcela=1, total_parcelas=3, id_financeiro_origem=NULL (eh o pai)
 * - Parcela 2: parcela=2, total_parcelas=3, id_financeiro_origem=ID_parcela_1
 * - Parcela 3: parcela=3, total_parcelas=3, id_financeiro_origem=ID_parcela_1
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Adicionar coluna total_parcelas
        $this->addColumnIfNotExists('financeiro', 'total_parcelas', 'INT UNSIGNED', [
            'null' => true,
            'default' => null,
            'after' => 'parcela'
        ]);

        // 2. Adicionar coluna id_financeiro_origem (auto-referencia)
        $this->addColumnIfNotExists('financeiro', 'id_financeiro_origem', 'INT(100) UNSIGNED', [
            'null' => true,
            'default' => null,
            'after' => 'total_parcelas'
        ]);

        // 3. Adicionar indice para id_financeiro_origem
        $this->addIndexIfNotExists('financeiro', 'id_financeiro_origem', 'idx_fin_id_origem');

        // 4. Adicionar indice composto para buscar parcelas
        $this->addIndexIfNotExists('financeiro', ['chave', 'id_financeiro_origem'], 'idx_fin_chave_origem');

        // 5. Adicionar FK para id_financeiro_origem (auto-referencia)
        $this->addForeignKeyIfNotExists(
            'financeiro',
            'id_financeiro_origem',
            'financeiro',
            'id',
            'SET NULL',  // onDelete
            'CASCADE',   // onUpdate
            'fk_financeiro_origem'
        );
    }

    public function down(): void
    {
        // 1. Remover FK
        $this->dropForeignKeyIfExists('financeiro', 'fk_financeiro_origem');

        // 2. Remover indices
        $this->dropIndexIfExists('financeiro', 'idx_fin_chave_origem');
        $this->dropIndexIfExists('financeiro', 'idx_fin_id_origem');

        // 3. Remover coluna id_financeiro_origem
        $this->dropColumnIfExists('financeiro', 'id_financeiro_origem');

        // 4. Remover coluna total_parcelas
        $this->dropColumnIfExists('financeiro', 'total_parcelas');
    }
};
