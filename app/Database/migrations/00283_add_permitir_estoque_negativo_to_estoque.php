<?php

/**
 * Migration 00283: Adicionar coluna permitir_estoque_negativo ao estoque
 *
 * Permite configurar por produto se o estoque pode ficar negativo.
 * Quando desativado (N), impede selecionar produto com estoque 0
 * e limita a quantidade ao estoque disponivel.
 *
 * Valores:
 * - S = Permite estoque negativo
 * - N = Nao permite (padrao)
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfNotExists('estoque', 'permitir_estoque_negativo', 'CHAR(1)', [
            'null' => false,
            'default' => 'N',
            'after' => 'baixa_automatica'
        ]);
    }

    public function down(): void
    {
        $this->dropColumnIfExists('estoque', 'permitir_estoque_negativo');
    }
};
