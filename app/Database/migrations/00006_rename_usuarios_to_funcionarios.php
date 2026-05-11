<?php

use App\Database\Migration;

/**
 * Migration: Renomear tabela usuarios → funcionarios
 *
 * Renomeia a tabela usuarios para funcionarios, refletindo
 * a nomenclatura já utilizada no front-end.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Renomear tabela usando método da classe Migration
        $this->renameTable('usuarios', 'funcionarios');
    }

    public function down(): void
    {
        // Reverter: renomear de volta para usuarios
        $this->renameTable('funcionarios', 'usuarios');
    }
};
