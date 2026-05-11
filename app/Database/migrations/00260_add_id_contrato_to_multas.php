<?php

/**
 * Migration 00260: Adicionar id_contrato na tabela multas
 *
 * A tabela multas ja possui id_locacao para vincular a locacoes,
 * mas nao tinha id_contrato para vincular a contratos.
 * O campo tipo [C/L] indica se a multa veio de contrato ou locacao.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfNotExists('multas', 'id_contrato', 'INT', [
            'unsigned' => true,
            'null' => true,
            'after' => 'id_locacao'
        ]);

        $this->addIndexIfNotExists('multas', 'id_contrato', 'idx_multas_id_contrato');
    }

    public function down(): void
    {
        $this->dropColumnIfExists('multas', 'id_contrato');
    }
};
