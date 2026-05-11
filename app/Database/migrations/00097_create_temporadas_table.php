<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela temporadas
 *
 * Define os períodos de temporada (alta/baixa) para ajuste de preços.
 * Registros com chave='0' são templates do sistema (somente leitura).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->create('temporadas', function ($table) {
            $table->id();
            $table->string('chave', 45);
            $table->string('pais', 2)->default('BR');
            $table->string('nome', 100);
            $table->addColumn('`mes_inicio` TINYINT UNSIGNED NOT NULL');
            $table->addColumn('`dia_inicio` TINYINT UNSIGNED NOT NULL');
            $table->addColumn('`mes_fim` TINYINT UNSIGNED NOT NULL');
            $table->addColumn('`dia_fim` TINYINT UNSIGNED NOT NULL');
            $table->boolean('ativo')->default(0);
            $table->datetime('created_at')->default('CURRENT_TIMESTAMP');
            $table->datetime('updated_at')->nullable();

            $table->index('chave', 'idx_temporadas_chave');
            $table->index('pais', 'idx_temporadas_pais');
            $table->index(['mes_inicio', 'dia_inicio', 'mes_fim', 'dia_fim'], 'idx_temporadas_periodo');
        });
    }

    public function down(): void
    {
        $this->drop('temporadas');
    }
};
