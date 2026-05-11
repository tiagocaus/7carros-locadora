<?php

use App\Database\Migration;

/**
 * Migration: Adicionar coluna id_tipo_pagamento em formas_pagamento
 *
 * Adiciona a FK para tipos_pagamento, permitindo categorizar
 * formas de pagamento por tipo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->table('formas_pagamento', function ($table) {
            $table->addColumn('`id_tipo_pagamento` INT UNSIGNED NULL AFTER `chave`');
            $table->index('id_tipo_pagamento', 'idx_formas_pagamento_tipo');
        });
    }

    public function down(): void
    {
        $this->table('formas_pagamento', function ($table) {
            $table->dropIndex('idx_formas_pagamento_tipo');
            $table->dropColumn('id_tipo_pagamento');
        });
    }
};
