<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela formas_pagamento_gateways
 *
 * Tabela pivot para relação N:N entre formas de pagamento e gateways.
 * Permite que uma forma de pagamento seja vinculada a múltiplos gateways,
 * habilitando processamento de pagamento online automático.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Criar tabela de relacionamento N:N
        $this->create('formas_pagamento_gateways', function ($table) {
            $table->id();
            $table->addColumn('`id_forma_pagamento` INT UNSIGNED NOT NULL');
            $table->addColumn('`id_gateway` INT UNSIGNED NOT NULL');
            $table->string('chave', 45);
            $table->datetime('created_at')->default('CURRENT_TIMESTAMP');

            // Índices para performance
            $table->index('id_forma_pagamento', 'idx_fpg_forma_pagamento');
            $table->index('id_gateway', 'idx_fpg_gateway');
            $table->index('chave', 'idx_fpg_chave');

            // Unique para evitar duplicatas
            $table->unique(['id_forma_pagamento', 'id_gateway'], 'uk_forma_pagamento_gateway');

            // Foreign keys
            $table->foreign('id_forma_pagamento')
                ->on('formas_pagamento')
                ->references('id')
                ->onDelete('CASCADE')
                ->onUpdate('CASCADE');

            $table->foreign('id_gateway')
                ->on('gateways_pagamento')
                ->references('id')
                ->onDelete('CASCADE')
                ->onUpdate('CASCADE');
        });
    }

    public function down(): void
    {
        $this->drop('formas_pagamento_gateways');
    }
};
