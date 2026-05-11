<?php

/**
 * Migration 00284: Criar tabela veiculos_encargos
 *
 * Substitui os campos fixos de documentacao/seguro na tabela veiculos
 * por uma tabela flexivel onde o cliente pode cadastrar qualquer
 * obrigacao financeira do veiculo (IPVA, Seguro, Licenciamento, etc.)
 * com suporte a recorrencia automatica e geracao de lancamentos financeiros.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('veiculos_encargos', function ($table) {
            $table->id();

            $table->string('chave', 45);
            $table->integer('id_veiculo')->unsigned();
            $table->string('nome', 100);
            $table->string('descricao', 500)->nullable();
            $table->decimal('valor', 15, 2)->nullable();
            $table->date('vencimento')->nullable();
            $table->enum('recorrencia', ['nenhuma', 'mensal', 'trimestral', 'semestral', 'anual'])->default('nenhuma');
            $table->integer('dias_antecedencia')->unsigned()->default(30);
            $table->integer('id_financeiro')->unsigned()->nullable();
            $table->boolean('ativo')->default(true);

            $table->timestamps();

            // Indexes
            $table->index(['chave', 'id_veiculo']);
            $table->index(['chave', 'vencimento', 'ativo']);

            // Foreign keys
            $table->foreign('id_veiculo')
                ->references('id')
                ->on('veiculos')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('id_financeiro')
                ->references('id')
                ->on('financeiro')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        $this->drop('veiculos_encargos');
    }
};
