<?php

/**
 * Migration 00369: Adiciona desconto por item em manutencoes.
 *
 * O campo valor_total continua representando o valor liquido do item:
 * quantidade * valor_unitario - desconto.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfNotExists('manutencoes_itens', 'desconto', 'DECIMAL(15,2)', [
            'null' => false,
            'default' => '0.00',
            'after' => 'valor_unitario',
        ]);
    }

    public function down(): void
    {
        $this->dropColumnIfExists('manutencoes_itens', 'desconto');
    }
};
