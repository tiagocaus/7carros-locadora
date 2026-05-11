<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela contatos_emails
 *
 * Armazena múltiplos emails para matrizes_filiais e clientes.
 * Usa relacionamento polimórfico através de entidade_tipo + entidade_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->create('contatos_emails', function ($table) {
            $table->id();
            $table->string('chave', 45);
            $table->enum('entidade_tipo', ['matriz_filial', 'cliente']);
            $table->integer('entidade_id')->unsigned();
            $table->string('email', 255);
            $table->string('descricao', 100)->nullable();
            $table->enum('principal', ['S', 'N'])->default('N');
            $table->datetime('created_at')->default('CURRENT_TIMESTAMP');
            $table->datetime('updated_at')->default('CURRENT_TIMESTAMP');

            $table->index('chave', 'idx_ce_chave');
            $table->index(['entidade_tipo', 'entidade_id'], 'idx_ce_entidade');
            $table->index(['entidade_tipo', 'entidade_id', 'principal'], 'idx_ce_entidade_principal');
            $table->index(['email'], 'idx_ce_email');
        });
    }

    public function down(): void
    {
        $this->drop('contatos_emails');
    }
};
