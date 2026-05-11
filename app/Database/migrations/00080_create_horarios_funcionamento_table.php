<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela horarios_funcionamento
 *
 * Armazena os horários de funcionamento por dia da semana de cada matriz/filial.
 * Suporta múltiplos períodos por dia (ex: manhã e tarde com intervalo).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->create('horarios_funcionamento', function ($table) {
            $table->id();
            $table->string('chave', 45);
            $table->integer('matriz_filial_id')->unsigned();
            $table->addColumn('`dia_semana` TINYINT UNSIGNED NOT NULL');  // 0=dom, 1=seg ... 6=sab
            $table->time('abertura');
            $table->time('fechamento');
            $table->addColumn('`periodo` TINYINT UNSIGNED NOT NULL DEFAULT 1');  // 1=primeiro, 2=segundo período
            $table->datetime('created_at')->default('CURRENT_TIMESTAMP');
            $table->datetime('updated_at')->default('CURRENT_TIMESTAMP');

            $table->index('chave', 'idx_hf_chave');
            $table->index(['matriz_filial_id', 'dia_semana'], 'idx_hf_matriz_dia');
            $table->index(['matriz_filial_id', 'dia_semana', 'periodo'], 'idx_hf_matriz_dia_periodo');

            $table->foreign('matriz_filial_id')
                ->references('id')
                ->on('matrizes_filiais')
                ->cascadeOnDelete()
                ->name('fk_hf_matriz_filial');
        });
    }

    public function down(): void
    {
        $this->drop('horarios_funcionamento');
    }
};
