<?php

/**
 * Migration 00090: Remover coluna moeda de financeiro
 *
 * A coluna `moeda` está 100% NULL e não é utilizada.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropColumnIfExists('financeiro', 'moeda');
    }

    public function down(): void
    {
        $this->addColumnIfNotExists('financeiro', 'moeda', 'VARCHAR', [
            'length' => 4,
            'null' => true
        ]);
    }
};
