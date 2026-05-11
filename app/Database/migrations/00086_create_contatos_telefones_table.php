<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela contatos_telefones
 *
 * Armazena múltiplos telefones para matrizes_filiais e clientes.
 * Usa relacionamento polimórfico através de entidade_tipo + entidade_id.
 * Suporta flags para WhatsApp, Telegram e SMS.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->create('contatos_telefones', function ($table) {
            $table->id();
            $table->string('chave', 45);
            $table->enum('entidade_tipo', ['matriz_filial', 'cliente']);
            $table->integer('entidade_id')->unsigned();
            $table->string('telefone', 30);
            $table->string('descricao', 100)->nullable();
            $table->enum('whatsapp', ['S', 'N'])->default('N');
            $table->enum('telegram', ['S', 'N'])->default('N');
            $table->enum('sms', ['S', 'N'])->default('N');
            $table->enum('principal', ['S', 'N'])->default('N');
            $table->datetime('created_at')->default('CURRENT_TIMESTAMP');
            $table->datetime('updated_at')->default('CURRENT_TIMESTAMP');

            $table->index('chave', 'idx_ct_chave');
            $table->index(['entidade_tipo', 'entidade_id'], 'idx_ct_entidade');
            $table->index(['entidade_tipo', 'entidade_id', 'principal'], 'idx_ct_entidade_principal');
            $table->index(['telefone'], 'idx_ct_telefone');
            $table->index(['entidade_tipo', 'entidade_id', 'whatsapp'], 'idx_ct_whatsapp');
        });
    }

    public function down(): void
    {
        $this->drop('contatos_telefones');
    }
};
