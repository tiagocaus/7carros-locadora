<?php

/**
 * Migration 00278: Adicionar colunas de fee ao financeiro_transacoes
 *
 * Preparacao para conciliacao futura: quando gateways (Stripe, Asaas)
 * retornarem a taxa real nos webhooks, sera gravada aqui.
 * Permitira comparar taxa esperada (financeiro.valor_taxa) vs taxa real (fee).
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfNotExists('financeiro_transacoes', 'fee', 'DECIMAL(10,2)', [
            'null' => true,
            'after' => 'amount'
        ]);

        $this->addColumnIfNotExists('financeiro_transacoes', 'net_amount', 'DECIMAL(10,2)', [
            'null' => true,
            'after' => 'fee'
        ]);
    }

    public function down(): void
    {
        $this->dropColumnIfExists('financeiro_transacoes', 'net_amount');
        $this->dropColumnIfExists('financeiro_transacoes', 'fee');
    }
};
