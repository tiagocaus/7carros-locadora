<?php

use App\Database\Migration;

/**
 * Migration: Adicionar canal SMS aos tipos de template faltantes
 *
 * Atualiza a coluna channels para incluir "sms" nos tipos:
 * - welcome
 * - contract_confirmation
 * - invoice_generated
 * - cnh_expiring
 */
return new class extends Migration
{
    public function up(): void
    {
        $tipos = ['welcome', 'contract_confirmation', 'invoice_generated', 'cnh_expiring'];

        foreach ($tipos as $slug) {
            $this->execute("
                UPDATE message_template_types
                SET channels = JSON_ARRAY_APPEND(channels, '$', 'sms')
                WHERE slug = '$slug'
                AND NOT JSON_CONTAINS(channels, '\"sms\"')
            ");
            echo "  - Canal SMS adicionado ao tipo $slug.\n";
        }
    }

    public function down(): void
    {
        $tipos = ['welcome', 'contract_confirmation', 'invoice_generated', 'cnh_expiring'];

        foreach ($tipos as $slug) {
            $this->execute("
                UPDATE message_template_types
                SET channels = JSON_REMOVE(channels, JSON_UNQUOTE(JSON_SEARCH(channels, 'one', 'sms')))
                WHERE slug = '$slug'
                AND JSON_CONTAINS(channels, '\"sms\"')
            ");
            echo "  - Canal SMS removido do tipo $slug.\n";
        }
    }
};
