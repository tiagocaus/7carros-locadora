<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela funcionarios_filiais
 *
 * Tabela pivot para relação N:N entre funcionários e filiais.
 * Permite que um funcionário tenha acesso a múltiplas filiais.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->create('funcionarios_filiais', function ($table) {
            $table->id();
            $table->addColumn('`id_funcionario` INT UNSIGNED NOT NULL');
            $table->addColumn('`id_matriz_filial` INT UNSIGNED NOT NULL');
            $table->string('chave', 100);
            $table->datetime('created_at')->default('CURRENT_TIMESTAMP');

            // Índices para performance
            $table->index('id_funcionario', 'idx_func_filiais_funcionario');
            $table->index('id_matriz_filial', 'idx_func_filiais_filial');
            $table->index('chave', 'idx_func_filiais_chave');

            // Unique para evitar duplicatas
            $table->unique(['id_funcionario', 'id_matriz_filial'], 'uk_funcionario_filial');

            // Foreign keys
            $table->foreign('id_funcionario')
                ->on('funcionarios')
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
        $this->drop('funcionarios_filiais');
    }
};
