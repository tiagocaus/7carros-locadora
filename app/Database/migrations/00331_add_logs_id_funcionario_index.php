<?php

/**
 * Migration 00331: Adiciona indice em logs.id_funcionario.
 *
 * Tabela logs tem ~10M linhas. O JOIN com funcionarios em Log::listar() (Log.php:38)
 * fazia full-scan sem este indice. Coluna tem 973 valores distintos e zero nulls.
 *
 * Idempotente: addIndexIfNotExists pula se ja existir.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfNotExists('logs', 'id_funcionario', 'idx_logs_id_funcionario');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('logs', 'idx_logs_id_funcionario');
    }
};
