<?php

use App\Database\Migration;

/**
 * Migration: Remover coluna veiculos.acessorios
 *
 * A coluna TEXT acessorios foi substituída pela tabela pivot
 * veiculos_acessorios_vinculados para relação N:N normalizada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropColumnIfExists('veiculos', 'acessorios');
    }

    public function down(): void
    {
        $this->addColumnIfNotExists('veiculos', 'acessorios', 'TEXT', [
            'null' => true
        ]);
    }
};
