<?php

/**
 * Migration 00208: Converter currency_code para currencies (JSON)
 *
 * Permite que cada gateway suporte multiplas moedas.
 * Ex: Stripe pode aceitar BRL, USD, EUR simultaneamente.
 *
 * Converte dados existentes de VARCHAR(3) para JSON array.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Adicionar nova coluna currencies (JSON)
        $this->addColumnIfNotExists('gateways_pagamento', 'currencies', 'JSON', [
            'null' => true,
            'after' => 'gateway_code'
        ]);

        // 2. Migrar dados existentes de currency_code para currencies
        $this->execute("
            UPDATE gateways_pagamento
            SET currencies = JSON_ARRAY(COALESCE(currency_code, 'BRL'))
            WHERE currencies IS NULL
        ");

        // 3. Definir NOT NULL (JSON columns cannot have literal defaults in older MySQL versions)
        $this->execute("
            ALTER TABLE gateways_pagamento
            MODIFY COLUMN currencies JSON NOT NULL
        ");

        // 4. Remover coluna antiga currency_code
        $this->dropIndexIfExists('gateways_pagamento', 'idx_gateways_pagamento_currency_code');
        $this->dropColumnIfExists('gateways_pagamento', 'currency_code');
    }

    public function down(): void
    {
        // 1. Recriar coluna currency_code
        $this->addColumnIfNotExists('gateways_pagamento', 'currency_code', 'VARCHAR(3)', [
            'null' => false,
            'default' => 'BRL',
            'after' => 'gateway_code'
        ]);

        // 2. Migrar dados de volta (pega primeira moeda do array)
        $this->execute("
            UPDATE gateways_pagamento
            SET currency_code = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(currencies, '$[0]')), 'BRL')
            WHERE currency_code IS NULL OR currency_code = ''
        ");

        // 3. Recriar indice
        $this->addIndexIfNotExists('gateways_pagamento', 'currency_code');

        // 4. Remover coluna currencies
        $this->dropColumnIfExists('gateways_pagamento', 'currencies');
    }
};
