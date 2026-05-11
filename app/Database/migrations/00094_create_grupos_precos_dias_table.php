<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela grupos_precos_dias
 *
 * Normaliza os preços progressivos por dias que antes eram
 * armazenados como JSON nas colunas tabela_diarias,
 * tabela_km_controlado e tabela_km_livre da tabela grupos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->create('grupos_precos_dias', function ($table) {
            $table->id();
            $table->string('chave', 45);
            $table->integer('id_grupo')->unsigned();
            $table->enum('tipo_plano', ['diaria', 'km_controlado', 'km_livre']);
            $table->integer('dia_inicio')->unsigned();
            $table->integer('dia_fim')->unsigned()->nullable();
            $table->decimal('valor', 10, 2);
            $table->datetime('created_at')->default('CURRENT_TIMESTAMP');
            $table->datetime('updated_at')->default('CURRENT_TIMESTAMP');

            $table->index('chave', 'idx_gpd_chave');
            $table->index(['id_grupo', 'tipo_plano'], 'idx_gpd_grupo_tipo');
            $table->foreign('id_grupo')
                ->references('id')
                ->on('grupos')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $this->drop('grupos_precos_dias');
    }
};
