<?php

use App\Database\Migration;

/**
 * Migration: Adicionar coluna role_id em funcionarios
 *
 * Adiciona a coluna role_id que será usada para relacionar
 * funcionários às suas funções (roles).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->table('funcionarios', function ($table) {
            // Adicionar coluna role_id após a coluna funcao
            $table->integer('role_id')->unsigned()->nullable()->after('funcao');

            // Adicionar índice para performance
            $table->index('role_id', 'idx_funcionarios_role_id');
        });
    }

    public function down(): void
    {
        $this->table('funcionarios', function ($table) {
            $table->dropIndex('idx_funcionarios_role_id');
            $table->dropColumn('role_id');
        });
    }
};
