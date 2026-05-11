<?php

/**
 * Migration 00304: Adicionar tipo 'authorization_hold' em financeiro_transacoes
 *
 * O campo type precisa suportar o novo tipo de transacao para
 * bloqueios (pre-autorizacao) no cartao de credito.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->execute("
            ALTER TABLE financeiro_transacoes
            MODIFY COLUMN type ENUM('charge','refund','webhook','callback','authorization_hold') NOT NULL
        ");
    }

    public function down(): void
    {
        $this->execute("
            ALTER TABLE financeiro_transacoes
            MODIFY COLUMN type ENUM('charge','refund','webhook','callback') NOT NULL
        ");
    }
};
