<?php

/**
 * Migration 00079: Renomear coluna logs.usuario para logs.id_funcionario
 *
 * Padroniza nomenclatura de foreign key seguindo convenção do sistema.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->columnExists('logs', 'usuario') && !$this->columnExists('logs', 'id_funcionario')) {
            $this->renameColumnPreservingType('logs', 'usuario', 'id_funcionario');
        }
    }

    public function down(): void
    {
        if ($this->columnExists('logs', 'id_funcionario') && !$this->columnExists('logs', 'usuario')) {
            $this->renameColumnPreservingType('logs', 'id_funcionario', 'usuario');
        }
    }
};
