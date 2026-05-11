<?php

/**
 * Migration 00113: Remover coluna consulta_multas de matrizes_filiais
 *
 * Campo legado que não é mais utilizado pelo sistema.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropColumnIfExists('matrizes_filiais', 'consulta_multas');
    }

    public function down(): void
    {
        $this->addColumnIfNotExists('matrizes_filiais', 'consulta_multas', 'CHAR', [
            'length' => 1,
            'default' => 'N'
        ]);
    }
};
