<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela veiculos_acessorios_vinculados
 *
 * Tabela pivot para relação N:N entre veículos e acessórios.
 * Permite vincular múltiplos acessórios a cada veículo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->create('veiculos_acessorios_vinculados', function ($table) {
            $table->id();
            $table->addColumn('`id_veiculo` INT UNSIGNED NOT NULL');
            $table->addColumn('`id_acessorio` INT UNSIGNED NOT NULL');
            $table->string('chave', 100);
            $table->datetime('created_at')->default('CURRENT_TIMESTAMP');

            // Índices para performance
            $table->index('id_veiculo', 'idx_veic_acess_vinc_veiculo');
            $table->index('id_acessorio', 'idx_veic_acess_vinc_acessorio');
            $table->index('chave', 'idx_veic_acess_vinc_chave');

            // Unique para evitar duplicatas
            $table->unique(['id_veiculo', 'id_acessorio'], 'uk_veiculo_acessorio');

            // Foreign keys
            $table->foreign('id_veiculo')
                ->on('veiculos')
                ->references('id')
                ->onDelete('CASCADE')
                ->onUpdate('CASCADE');

            $table->foreign('id_acessorio')
                ->on('veiculos_acessorios')
                ->references('id')
                ->onDelete('CASCADE')
                ->onUpdate('CASCADE');
        });
    }

    public function down(): void
    {
        $this->drop('veiculos_acessorios_vinculados');
    }
};
