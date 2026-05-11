<?php

/**
 * Migration: Adiciona campo descricao_i18n à tabela planos_de_contas
 *
 * Adiciona suporte a internacionalização nas descrições do plano de contas
 * usando campo JSON para armazenar traduções em múltiplos idiomas.
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Executa a migration
     */
    public function up(): void
    {
        $this->table('planos_de_contas', function ($table) {
            $table->json('descricao_i18n')->nullable()->after('descricao');
        });
    }

    /**
     * Reverte a migration
     */
    public function down(): void
    {
        $this->table('planos_de_contas', function ($table) {
            $table->dropColumn('descricao_i18n');
        });
    }
};
