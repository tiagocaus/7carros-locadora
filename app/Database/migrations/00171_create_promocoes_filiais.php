<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela promocoes_filiais
 *
 * Tabela pivot para relação N:N entre promoções e filiais.
 * Permite que uma promoção seja vinculada a múltiplas filiais.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->create('promocoes_filiais', function ($table) {
            $table->id();
            $table->addColumn('`id_promocao` INT UNSIGNED NOT NULL');
            $table->addColumn('`id_matriz_filial` INT UNSIGNED NOT NULL');
            $table->string('chave', 45);
            $table->datetime('created_at')->default('CURRENT_TIMESTAMP');

            // Índices para performance
            $table->index('id_promocao', 'idx_promo_filiais_promocao');
            $table->index('id_matriz_filial', 'idx_promo_filiais_filial');
            $table->index('chave', 'idx_promo_filiais_chave');

            // Unique para evitar duplicatas
            $table->unique(['id_promocao', 'id_matriz_filial'], 'uk_promocao_filial');

            // Foreign keys
            $table->foreign('id_promocao')
                ->on('promocoes')
                ->references('id')
                ->onDelete('CASCADE')
                ->onUpdate('CASCADE');

            $table->foreign('id_matriz_filial')
                ->on('matrizes_filiais')
                ->references('id')
                ->onDelete('CASCADE')
                ->onUpdate('CASCADE');
        });
    }

    public function down(): void
    {
        $this->drop('promocoes_filiais');
    }
};
