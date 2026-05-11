<?php

/**
 * Migration 00282: Adicionar coluna baixa_automatica ao estoque
 *
 * Permite configurar por produto se o estoque deve ser decrementado
 * automaticamente ao usar o item em uma OS de manutencao.
 *
 * Valores:
 * - S = Ativa baixa automatica
 * - N = Nao (padrao)
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfNotExists('estoque', 'baixa_automatica', 'CHAR(1)', [
            'null' => false,
            'default' => 'N',
            'after' => 'status'
        ]);
    }

    public function down(): void
    {
        $this->dropColumnIfExists('estoque', 'baixa_automatica');
    }
};
