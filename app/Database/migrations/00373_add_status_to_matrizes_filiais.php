<?php

use App\Database\Migration;

/**
 * Migration 00373: adiciona status ativo/inativo em matrizes e filiais.
 *
 * Permite desativar uma filial com historico vinculado sem remover os registros
 * ligados a ela.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('matrizes_filiais')) {
            return;
        }

        $this->addColumnIfNotExists('matrizes_filiais', 'status', 'CHAR(1)', [
            'null' => false,
            'default' => 'A',
            'after' => 'tipo',
        ]);

        $this->addIndexIfNotExists('matrizes_filiais', ['chave', 'status'], 'idx_matrizes_filiais_chave_status');
    }

    public function down(): void
    {
        if (!$this->tableExists('matrizes_filiais')) {
            return;
        }

        $this->dropIndexIfExists('matrizes_filiais', 'idx_matrizes_filiais_chave_status');
        $this->dropColumnIfExists('matrizes_filiais', 'status');
    }
};
