<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela contratos_odometros
 *
 * Histórico de leituras de odômetro (ex-coluna odometro_array).
 * Permite rastrear km rodado por veículo específico em cada contrato.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->create('contratos_odometros', function ($table) {
            $table->id();
            $table->string('chave', 45);
            $table->addColumn('`id_contrato` INT UNSIGNED NOT NULL');
            $table->addColumn('`id_contrato_veiculo` INT UNSIGNED NOT NULL COMMENT "Veículo específico do registro"');
            $table->addColumn('`data` DATE NOT NULL');
            $table->addColumn('`odometro` INT UNSIGNED NOT NULL');
            $table->addColumn('`diferenca` INT UNSIGNED NULL COMMENT "KM rodados desde entrada ou último registro"');
            $table->addColumn('`obs` VARCHAR(255) NULL');
            $table->addColumn('`id_funcionario` INT UNSIGNED NULL COMMENT "Quem registrou"');
            $table->datetime('created_at')->default('CURRENT_TIMESTAMP');

            // Índices
            $table->unique(['id_contrato', 'id_contrato_veiculo', 'data'], 'idx_co_unique');
            $table->index('chave', 'idx_co_chave');
            $table->index(['chave', 'id_contrato'], 'idx_co_contrato');
            $table->index(['chave', 'id_contrato_veiculo'], 'idx_co_veiculo');
            $table->index(['chave', 'id_contrato', 'data'], 'idx_co_data');

            // Foreign keys
            $table->foreign('id_contrato')
                ->on('contratos')
                ->references('id')
                ->onDelete('CASCADE')
                ->onUpdate('CASCADE');

            $table->foreign('id_contrato_veiculo')
                ->on('contratos_veiculos')
                ->references('id')
                ->onDelete('CASCADE')
                ->onUpdate('CASCADE');

            $table->foreign('id_funcionario')
                ->on('funcionarios')
                ->references('id')
                ->onDelete('SET NULL')
                ->onUpdate('CASCADE');
        });
    }

    public function down(): void
    {
        $this->drop('contratos_odometros');
    }
};
