<?php

/**
 * Migration 00207: Adicionar moeda aos gateways de pagamento
 *
 * Adiciona campo currency_code para definir a moeda operacional de cada gateway.
 * Isso permite filtrar gateways compatíveis com a moeda do tenant.
 *
 * Ex: Asaas só funciona com BRL, Stripe pode ser configurado para EUR, USD, etc.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Adicionar coluna currency_code
        $this->addColumnIfNotExists('gateways_pagamento', 'currency_code', 'VARCHAR(3)', [
            'null' => false,
            'default' => 'BRL',
            'after' => 'gateway_code'
        ]);

        // Adicionar índice para filtrar por moeda
        $this->addIndexIfNotExists('gateways_pagamento', 'currency_code');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('gateways_pagamento', 'idx_gateways_pagamento_currency_code');
        $this->dropColumnIfExists('gateways_pagamento', 'currency_code');
    }
};
