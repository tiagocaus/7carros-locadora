<?php

use App\Database\Migration;

/**
 * Permite desativar apenas os avisos automaticos de faturas vencidas por filial.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->table('matrizes_filiais', function ($table) {
            $table->string('notificacao_cobranca_vencida', 1)
                ->default('S')
                ->after('notificacao_whatsapp');
        });
    }

    public function down(): void
    {
        $this->table('matrizes_filiais', function ($table) {
            $table->dropColumn('notificacao_cobranca_vencida');
        });
    }
};
