<?php

/**
 * Migration 00163: Adicionar campos de calculo em contratos_taxaseservicos
 *
 * Adiciona campos para armazenar a logica de calculo de cada taxa no contrato:
 * - base_calculo: FIX, PER, VLT
 * - tipo_valor: MON, POR
 *
 * Remove coluna 'calculo' antiga que nao estava sendo usada.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Adicionar novas colunas
        $this->addColumnIfNotExists('contratos_taxaseservicos', 'base_calculo', 'VARCHAR(3)', [
            'null' => false,
            'default' => 'FIX',
            'after' => 'id_taxa'
        ]);

        $this->addColumnIfNotExists('contratos_taxaseservicos', 'tipo_valor', 'VARCHAR(3)', [
            'null' => false,
            'default' => 'MON',
            'after' => 'base_calculo'
        ]);

        // 2. Preencher dados existentes baseado na taxa original (se existir)
        $this->execute("
            UPDATE contratos_taxaseservicos ct
            INNER JOIN taxaseservicos t ON ct.id_taxa = t.id
            SET ct.base_calculo = t.base_calculo,
                ct.tipo_valor = t.tipo_valor
            WHERE ct.id_taxa IS NOT NULL
        ");

        // 3. Para registros sem id_taxa, assumir FIX/MON (ja e o default)

        // 4. Remover coluna 'calculo' antiga (se existir)
        $this->dropColumnIfExists('contratos_taxaseservicos', 'calculo');
    }

    public function down(): void
    {
        // 1. Recriar coluna 'calculo'
        $this->addColumnIfNotExists('contratos_taxaseservicos', 'calculo', 'VARCHAR(3)', [
            'default' => null,
            'null' => true,
            'after' => 'id_taxa'
        ]);

        // 2. Remover novas colunas
        $this->dropColumnIfExists('contratos_taxaseservicos', 'base_calculo');
        $this->dropColumnIfExists('contratos_taxaseservicos', 'tipo_valor');
    }
};
