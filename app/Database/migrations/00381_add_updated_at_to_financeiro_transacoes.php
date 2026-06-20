<?php

/**
 * Migration 00381: Adicionar updated_at em financeiro_transacoes
 *
 * O fluxo de invalidacao de links/cobrancas atualiza o status de transacoes
 * financeiras e espera que a tabela possua updated_at.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfNotExists('financeiro_transacoes', 'updated_at', 'DATETIME', [
            'null' => true,
            'after' => 'created_at',
        ]);

        $this->execute("
            ALTER TABLE financeiro_transacoes
            MODIFY COLUMN updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
        ");
    }

    public function down(): void
    {
        $this->dropColumnIfExists('financeiro_transacoes', 'updated_at');
    }
};
