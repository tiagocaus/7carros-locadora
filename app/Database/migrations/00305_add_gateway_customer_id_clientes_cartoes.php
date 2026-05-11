<?php

/**
 * Migration 00305: Adicionar gateway_customer_id em clientes_cartoes
 *
 * Stripe (e outros gateways) exigem que um PaymentMethod seja vinculado
 * a um Customer para reutilizacao. Este campo armazena o ID do customer
 * no gateway (cus_xxx no Stripe).
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfNotExists('clientes_cartoes', 'gateway_customer_id', 'VARCHAR(255)', [
            'nullable' => true,
            'after' => 'gateway',
        ]);
    }

    public function down(): void
    {
        $this->dropColumnIfExists('clientes_cartoes', 'gateway_customer_id');
    }
};
