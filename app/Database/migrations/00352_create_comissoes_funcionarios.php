<?php

/**
 * Migration: Cria tabela `comissoes_funcionarios`.
 *
 * Justificativa: spec 10.2 (Comissoes) exige Receita Base, % Comissao,
 * Valor Comissao, Bonus, Status Pgto. Nao havia tabela de comissoes
 * para funcionarios (so havia `comissoes_investidores`).
 *
 * Esta migration cria apenas o schema; a logica de geracao automatica
 * de comissoes (a partir de locacoes/contratos fechados) e o CRUD de UI
 * ficam fora de escopo. O relatorio le o que estiver gravado.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('comissoes_funcionarios', function ($table) {
            $table->id();
            $table->string('chave', 45);
            $table->integer('id_matriz_filial')->unsigned()->nullable();
            $table->integer('id_funcionario')->unsigned();
            $table->enum('tipo_origem', ['locacao', 'contrato', 'manual']);
            $table->integer('id_locacao')->unsigned()->nullable();
            $table->integer('id_contrato')->unsigned()->nullable();
            $table->integer('id_financeiro_origem')->unsigned()->nullable();
            $table->decimal('valor_base', 12, 2)->default('0');
            $table->enum('comissao_tipo', ['percentual', 'fixo']);
            $table->decimal('comissao_percentual', 5, 2)->nullable();
            $table->decimal('comissao_valor_fixo', 10, 2)->nullable();
            $table->decimal('valor_comissao', 12, 2)->default('0');
            $table->decimal('bonus', 10, 2)->default('0');
            $table->decimal('valor_total', 12, 2)->default('0');
            $table->enum('status', ['pendente', 'pago', 'cancelado'])->default('pendente');
            $table->date('data_referencia');
            $table->date('data_pagamento')->nullable();
            $table->integer('id_financeiro')->unsigned()->nullable();
            $table->text('obs')->nullable();
            $table->timestamps();

            $table->index('chave', 'idx_cf_chave');
            $table->index('id_funcionario', 'idx_cf_funcionario');
            $table->index('id_locacao', 'idx_cf_locacao');
            $table->index('id_contrato', 'idx_cf_contrato');
            $table->index('status', 'idx_cf_status');
            $table->index('data_referencia', 'idx_cf_data_ref');
        });
    }

    public function down(): void
    {
        $this->drop('comissoes_funcionarios');
    }
};
