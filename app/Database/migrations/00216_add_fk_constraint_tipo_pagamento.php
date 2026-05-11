<?php

use App\Database\Migration;

/**
 * Migration: Adicionar FK constraint para id_tipo_pagamento
 *
 * Adiciona a foreign key constraint apos os dados serem migrados.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->table('formas_pagamento', function ($table) {
            $table->foreign('id_tipo_pagamento')
                ->on('tipos_pagamento')
                ->references('id')
                ->onDelete('SET NULL')
                ->onUpdate('CASCADE');
        });
    }

    public function down(): void
    {
        $this->table('formas_pagamento', function ($table) {
            $table->dropForeign('fk_formas_pagamento_id_tipo_pagamento');
        });
    }
};
