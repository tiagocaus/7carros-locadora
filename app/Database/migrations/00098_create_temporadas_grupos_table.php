<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela temporadas_grupos
 *
 * Define o ajuste percentual de preço por grupo de veículos
 * em cada temporada. Ex: +30% para SUV no Carnaval.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->create('temporadas_grupos', function ($table) {
            $table->id();
            $table->string('chave', 45);
            $table->integer('id_temporada')->unsigned();
            $table->integer('id_grupo')->unsigned();
            $table->decimal('ajuste_percentual', 5, 2);
            $table->datetime('created_at')->default('CURRENT_TIMESTAMP');
            $table->datetime('updated_at')->nullable();

            $table->index('chave', 'idx_tg_chave');
            $table->index('id_temporada', 'idx_tg_temporada');
            $table->index('id_grupo', 'idx_tg_grupo');
            $table->unique(['id_temporada', 'id_grupo'], 'uk_temporada_grupo');
            $table->foreign('id_temporada')
                ->references('id')
                ->on('temporadas')
                ->cascadeOnDelete();
            $table->foreign('id_grupo')
                ->references('id')
                ->on('grupos')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $this->drop('temporadas_grupos');
    }
};
