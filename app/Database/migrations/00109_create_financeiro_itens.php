<?php

/**
 * Migration 00109: Criar Tabela financeiro_itens
 *
 * Cria a tabela de itens do financeiro para suportar
 * multiplos itens por fatura/lancamento.
 *
 * Estrutura:
 * - id (PK)
 * - chave (tenant)
 * - id_financeiro (FK para financeiro - sera adicionada na migration 00112)
 * - id_veiculo (opcional, para rastreabilidade)
 * - id_plano_de_conta (classificacao do item)
 * - descricao (descricao do item)
 * - valor (valor do item)
 * - ordem (ordenacao dos itens)
 * - created_at, updated_at
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('financeiro_itens')) {
            $this->create('financeiro_itens', function ($table) {
                // Chave primaria
                $table->id();

                // Tenant
                $table->string('chave', 45);

                // FK para financeiro (constraint sera adicionada na migration 00112)
                $table->integer('id_financeiro')->unsigned();

                // Veiculo relacionado (opcional)
                $table->integer('id_veiculo')->unsigned()->nullable();

                // Plano de contas do item
                $table->integer('id_plano_de_conta')->unsigned()->nullable();

                // Descricao do item
                $table->string('descricao', 500)->nullable();

                // Valor do item
                $table->decimal('valor', 15, 2)->default(0);

                // Ordem de exibicao
                $table->integer('ordem')->unsigned()->default(1);

                // Timestamps
                $table->timestamp('created_at')->default('CURRENT_TIMESTAMP');
                $table->datetime('updated_at')->nullable();

                // Indices
                $table->index('chave', 'idx_fi_chave');
                $table->index('id_financeiro', 'idx_fi_id_financeiro');
                $table->index(['chave', 'id_financeiro'], 'idx_fi_chave_financeiro');
            });
        }
    }

    public function down(): void
    {
        $this->drop('financeiro_itens');
    }
};
