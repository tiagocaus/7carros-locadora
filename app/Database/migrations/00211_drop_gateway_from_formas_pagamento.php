<?php

use App\Database\Migration;

/**
 * Migration: Remover coluna gateway da tabela formas_pagamento
 *
 * A coluna `gateway` era um campo legado (INT, padrão 0) que não é mais utilizado.
 * O vínculo entre formas de pagamento e gateways agora é feito via tabela N:N
 * `formas_pagamento_gateways`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Remover coluna apenas se existir
        $this->dropColumnIfExists('formas_pagamento', 'gateway');
    }

    public function down(): void
    {
        // Recriar coluna legada
        $this->addColumnIfNotExists('formas_pagamento', 'gateway', 'INT(10)', [
            'null' => false,
            'default' => 0,
            'after' => 'descricao'
        ]);
    }
};
