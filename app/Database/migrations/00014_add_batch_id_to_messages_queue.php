<?php

use App\Database\Migration;

/**
 * Migration: Adicionar coluna batch_id à tabela messages_queue
 *
 * A coluna `chave` é exclusiva para multi-tenancy (chave do tenant).
 * A nova coluna `batch_id` armazena identificadores de batch/teste/lote.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->table('messages_queue', function ($table) {
            // Adiciona coluna batch_id após chave
            $table->string('batch_id', 64)->nullable()->after('chave');

            // Índice para consultas por batch
            $table->index('batch_id', 'idx_messages_queue_batch_id');
        });
    }

    public function down(): void
    {
        $this->table('messages_queue', function ($table) {
            $table->dropIndex('idx_messages_queue_batch_id');
            $table->dropColumn('batch_id');
        });
    }
};
