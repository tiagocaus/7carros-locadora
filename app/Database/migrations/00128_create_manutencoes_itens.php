<?php

/**
 * Migration 00128: Criar Tabela manutencoes_itens
 *
 * Normaliza a coluna array_servicos da tabela manutencoes
 * para uma tabela separada de itens.
 *
 * Estrutura:
 * - id (PK)
 * - chave (tenant)
 * - id_manutencao (FK para manutencoes)
 * - id_estoque (FK para estoque - obrigatorio)
 * - id_financeiro (FK para financeiro - quando pago)
 * - descricao, quantidade, valor_unitario, valor_total
 * - pago, data_pagamento
 * - ordem
 * - created_at, updated_at
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('manutencoes_itens')) {
            $this->create('manutencoes_itens', function ($table) {
                // Chave primaria
                $table->id();

                // Tenant
                $table->string('chave', 45);

                // FK para manutencao
                $table->integer('id_manutencao')->unsigned();

                // FK para estoque (nullable para dados legados, obrigatorio via controller para novos)
                $table->integer('id_estoque')->unsigned()->nullable();

                // FK para financeiro (quando o item foi pago)
                $table->integer('id_financeiro')->unsigned()->nullable();

                // Dados do item
                $table->string('descricao', 500);
                $table->decimal('quantidade', 10, 3)->default(1);
                $table->decimal('valor_unitario', 15, 2)->default(0);
                $table->decimal('valor_total', 15, 2)->default(0);

                // Controle de pagamento
                $table->string('pago', 1)->default('N'); // S/N
                $table->datetime('data_pagamento')->nullable();

                // Ordem de exibicao
                $table->integer('ordem')->unsigned()->default(1);

                // Timestamps
                $table->timestamp('created_at')->default('CURRENT_TIMESTAMP');
                $table->datetime('updated_at')->nullable();

                // Indices
                $table->index('chave', 'idx_mi_chave');
                $table->index('id_manutencao', 'idx_mi_id_manutencao');
                $table->index('id_estoque', 'idx_mi_id_estoque');
                $table->index('id_financeiro', 'idx_mi_id_financeiro');
                $table->index(['chave', 'pago'], 'idx_mi_chave_pago');
                $table->index(['chave', 'id_manutencao'], 'idx_mi_chave_manutencao');

                // Foreign Keys
                $table->foreign('id_manutencao')
                    ->references('id')
                    ->on('manutencoes')
                    ->onDelete('CASCADE')
                    ->onUpdate('CASCADE');

                $table->foreign('id_estoque')
                    ->references('id')
                    ->on('estoque')
                    ->onDelete('RESTRICT')
                    ->onUpdate('CASCADE');

                $table->foreign('id_financeiro')
                    ->references('id')
                    ->on('financeiro')
                    ->onDelete('SET NULL')
                    ->onUpdate('CASCADE');
            });
        }
    }

    public function down(): void
    {
        $this->drop('manutencoes_itens');
    }
};
