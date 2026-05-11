<?php

/**
 * Migration 00077: Renomear tabela funcionarios_remember_tokens
 *
 * Renomeia:
 * - Tabela: funcionarios_remember_tokens → funcionarios_tokens
 * - FK: fk_funcionarios_remember_tokens_usuario_id → fk_funcionarios_tokens_usuario_id
 */

use App\Database\Migration;

return new class extends Migration
{
    private string $oldTable = 'funcionarios_remember_tokens';
    private string $newTable = 'funcionarios_tokens';

    public function up(): void
    {
        if (!$this->tableExists($this->oldTable)) {
            return;
        }

        // Remove FK antiga antes de renomear
        $oldFk = "fk_{$this->oldTable}_usuario_id";
        $this->dropForeignKeyIfExists($this->oldTable, $oldFk);

        // Renomeia a tabela
        $this->renameTable($this->oldTable, $this->newTable);

        // Recria FK com novo nome
        $newFk = "fk_{$this->newTable}_usuario_id";
        if (!$this->foreignKeyExists($this->newTable, $newFk)) {
            $this->addForeignKey(
                $this->newTable,
                'usuario_id',
                'funcionarios',
                'id',
                'CASCADE',
                'CASCADE',
                $newFk
            );
        }
    }

    public function down(): void
    {
        if (!$this->tableExists($this->newTable)) {
            return;
        }

        // Remove FK nova
        $newFk = "fk_{$this->newTable}_usuario_id";
        $this->dropForeignKeyIfExists($this->newTable, $newFk);

        // Renomeia a tabela de volta
        $this->renameTable($this->newTable, $this->oldTable);

        // Recria FK com nome original
        $oldFk = "fk_{$this->oldTable}_usuario_id";
        if (!$this->foreignKeyExists($this->oldTable, $oldFk)) {
            $this->addForeignKey(
                $this->oldTable,
                'usuario_id',
                'funcionarios',
                'id',
                'CASCADE',
                'CASCADE',
                $oldFk
            );
        }
    }
};
