<?php

/**
 * Migration: Adiciona coluna deleted_at à tabela matriz_filial
 *
 * Adiciona suporte a soft deletes na tabela matriz_filial
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Executa a migration
     */
    public function up(): void
    {
        $this->table('matriz_filial', function ($table) {
            // Adiciona coluna deleted_at com fluent API
            $table->datetime('deleted_at')->nullable()->after('updated_at');

            // Adiciona índice composto para melhor performance em queries com soft delete
            $table->index(['chave', 'deleted_at'], 'idx_chave_deleted');
        });
    }

    /**
     * Reverte a migration
     */
    public function down(): void
    {
        $this->table('matriz_filial', function ($table) {
            // Remove o índice
            $table->dropIndex('idx_chave_deleted');

            // Remove a coluna
            $table->dropColumn('deleted_at');
        });
    }
};
