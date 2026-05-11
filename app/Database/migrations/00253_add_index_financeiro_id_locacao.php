<?php

use App\Database\Migration;

/**
 * Migration: Adicionar coluna id_locacao na tabela financeiro
 *
 * Similar a id_contrato que já existe, permite vincular
 * registros financeiros diretamente a locações.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfNotExists('financeiro', 'id_locacao', 'INT UNSIGNED', [
            'nullable' => true,
            'after' => 'id_contrato',
        ]);

        $this->addIndexIfNotExists('financeiro', 'id_locacao', 'idx_fin_locacao');

        echo "Coluna id_locacao + índice adicionados em financeiro\n";
    }

    public function down(): void
    {
        $this->dropColumnIfExists('financeiro', 'id_locacao');
    }
};
