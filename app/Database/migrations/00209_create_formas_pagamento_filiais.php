<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela formas_pagamento_filiais
 *
 * Tabela pivot para relação N:N entre formas de pagamento e filiais.
 * Permite que uma forma de pagamento seja vinculada a múltiplas filiais.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Criar tabela de relacionamento N:N
        $this->create('formas_pagamento_filiais', function ($table) {
            $table->id();
            $table->addColumn('`id_forma_pagamento` INT UNSIGNED NOT NULL');
            $table->addColumn('`id_matriz_filial` INT UNSIGNED NOT NULL');
            $table->string('chave', 45);
            $table->datetime('created_at')->default('CURRENT_TIMESTAMP');

            // Índices para performance
            $table->index('id_forma_pagamento', 'idx_fpf_forma_pagamento');
            $table->index('id_matriz_filial', 'idx_fpf_filial');
            $table->index('chave', 'idx_fpf_chave');

            // Unique para evitar duplicatas
            $table->unique(['id_forma_pagamento', 'id_matriz_filial'], 'uk_forma_pagamento_filial');

            // Foreign keys
            $table->foreign('id_forma_pagamento')
                ->on('formas_pagamento')
                ->references('id')
                ->onDelete('CASCADE')
                ->onUpdate('CASCADE');

            $table->foreign('id_matriz_filial')
                ->on('matrizes_filiais')
                ->references('id')
                ->onDelete('CASCADE')
                ->onUpdate('CASCADE');
        });

        // Migrar dados: vincular todas as formas existentes a todas as filiais do tenant
        $this->execute("
            INSERT INTO formas_pagamento_filiais (id_forma_pagamento, id_matriz_filial, chave)
            SELECT fp.id, mf.id, fp.chave
            FROM formas_pagamento fp
            INNER JOIN matrizes_filiais mf ON mf.chave = fp.chave
        ");
    }

    public function down(): void
    {
        $this->drop('formas_pagamento_filiais');
    }
};
