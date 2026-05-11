<?php

/**
 * Migration 00281: Adicionar coluna tipo_veiculo ao manutencoes_plano
 *
 * Permite diferenciar planos de manutenção para carros e motos.
 * Cada tipo exibe itens de manutenção específicos.
 *
 * Valores:
 * - C = Carro
 * - M = Moto
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfNotExists('manutencoes_plano', 'tipo_veiculo', 'CHAR(1)', [
            'null' => false,
            'default' => 'C',
            'after' => 'nome'
        ]);
    }

    public function down(): void
    {
        $this->dropColumnIfExists('manutencoes_plano', 'tipo_veiculo');
    }
};
