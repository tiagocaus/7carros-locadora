<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela horarios_excecoes
 *
 * Armazena exceções de horário para datas específicas (feriados, Black Friday, etc).
 * Permite definir se a filial está fechada ou com horário especial.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->create('horarios_excecoes', function ($table) {
            $table->id();
            $table->string('chave', 45);
            $table->integer('matriz_filial_id')->unsigned();
            $table->date('data');
            $table->enum('tipo', ['fechado', 'especial']);
            $table->time('abertura')->nullable();     // NULL se fechado
            $table->time('fechamento')->nullable();   // NULL se fechado
            $table->string('descricao', 100)->nullable();  // "Black Friday", "Inventário"
            $table->datetime('created_at')->default('CURRENT_TIMESTAMP');
            $table->datetime('updated_at')->default('CURRENT_TIMESTAMP');

            $table->index('chave', 'idx_he_chave');
            $table->index(['matriz_filial_id', 'data'], 'idx_he_matriz_data');
            $table->unique(['matriz_filial_id', 'data'], 'uk_he_matriz_data');

            $table->foreign('matriz_filial_id')
                ->references('id')
                ->on('matrizes_filiais')
                ->cascadeOnDelete()
                ->name('fk_he_matriz_filial');
        });
    }

    public function down(): void
    {
        $this->drop('horarios_excecoes');
    }
};
