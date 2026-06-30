<?php

use App\Database\Migration;

/**
 * Migration: Adicionar timezone às configurações de matriz/filial.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfNotExists('matrizes_filiais', 'timezone', 'VARCHAR(64)', [
            'null' => false,
            'default' => 'America/Sao_Paulo',
            'after' => 'datetime_format',
        ]);

        $this->execute("
            UPDATE matrizes_filiais
            SET timezone = 'America/Sao_Paulo'
            WHERE timezone IS NULL OR timezone = ''
        ");
    }

    public function down(): void
    {
        $this->dropColumnIfExists('matrizes_filiais', 'timezone');
    }
};
