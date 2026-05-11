<?php

/**
 * Migration: Cria tabela `metas_funcionarios`.
 *
 * Justificativa: spec 10.4 (Metas vs Realizado) exige Meta Receita e Meta
 * Locacoes por funcionario para calcular % atingido. Sem fonte de dado,
 * nao havia como popular esses campos.
 *
 * Esta migration cria apenas o schema; o CRUD de UI para cadastro de metas
 * fica fora de escopo. O relatorio le o que estiver gravado e exibe um
 * aviso se nao houver metas cadastradas.
 *
 * Indice unico em (chave, id_funcionario, data_referencia) garante uma
 * meta por funcionario por mes.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('metas_funcionarios', function ($table) {
            $table->id();
            $table->string('chave', 45);
            $table->integer('id_matriz_filial')->unsigned()->nullable();
            $table->integer('id_funcionario')->unsigned();
            $table->date('data_referencia');
            $table->decimal('meta_receita', 12, 2)->default('0');
            $table->integer('meta_locacoes')->unsigned()->default('0');
            $table->text('obs')->nullable();
            $table->timestamps();

            $table->unique(['chave', 'id_funcionario', 'data_referencia'], 'uniq_meta_func_periodo');
            $table->index('chave', 'idx_mf_chave');
            $table->index('id_funcionario', 'idx_mf_funcionario');
            $table->index('data_referencia', 'idx_mf_data_ref');
        });
    }

    public function down(): void
    {
        $this->drop('metas_funcionarios');
    }
};
