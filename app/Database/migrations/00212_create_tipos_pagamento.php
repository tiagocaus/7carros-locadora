<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela tipos_pagamento
 *
 * Tabela de dominio para categorizar formas de pagamento.
 * Permite agrupar formas de pagamento por tipo (Boleto, Cartao, PIX, etc.)
 * nos selects usando optgroup.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->create('tipos_pagamento', function ($table) {
            $table->id();
            $table->string('chave', 45);
            $table->string('nome', 100);
            $table->string('icone', 50)->nullable();
            $table->integer('ordem')->default(0);
            $table->string('status', 1)->default('A');
            $table->timestamps();

            // Indices
            $table->index('chave', 'idx_tipos_pagamento_chave');
            $table->index(['chave', 'status'], 'idx_tipos_pagamento_chave_status');

            // Unique para evitar tipos duplicados por tenant
            $table->unique(['chave', 'nome'], 'uk_tipos_pagamento_nome');
        });
    }

    public function down(): void
    {
        $this->drop('tipos_pagamento');
    }
};
