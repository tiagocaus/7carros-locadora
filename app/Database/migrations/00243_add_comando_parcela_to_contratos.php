<?php

use App\Database\Migration;

/**
 * Migration: Adiciona coluna id_comando_parcela na tabela contratos
 *
 * Necessaria para persistir o comando de parcelas selecionado no contrato,
 * permitindo que a renovacao automatica gere parcelas corretamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfNotExists('contratos', 'id_comando_parcela', 'INT(10) UNSIGNED', [
            'null' => true,
            'default' => null,
            'after' => 'id_forma_pagamento',
        ]);

        $this->addIndexIfNotExists('contratos', 'id_comando_parcela', 'idx_contratos_id_comando_parcela');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('contratos', 'idx_contratos_id_comando_parcela');
        $this->dropColumnIfExists('contratos', 'id_comando_parcela');
    }
};
