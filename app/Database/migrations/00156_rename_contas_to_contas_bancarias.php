<?php

/**
 * Migration: Renomear tabela contas para contas_bancarias
 *
 * Esta migration renomeia a tabela 'contas' para 'contas_bancarias'
 * para melhor clareza e evitar confusao com 'planos_de_contas'.
 *
 * As foreign keys sao atualizadas automaticamente pelo MySQL.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Renomear tabela contas -> contas_bancarias
        $this->execute("RENAME TABLE contas TO contas_bancarias");
    }

    public function down(): void
    {
        // Reverter: contas_bancarias -> contas
        $this->execute("RENAME TABLE contas_bancarias TO contas");
    }
};
