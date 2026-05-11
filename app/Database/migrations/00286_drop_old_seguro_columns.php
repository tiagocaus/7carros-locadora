<?php

/**
 * Migration 00286: Remover colunas antigas de seguro/licenciamento da tabela veiculos
 *
 * Remove as colunas fixas que foram substituidas pela tabela veiculos_encargos:
 * - venc_licenciamento
 * - companhia_seguro
 * - tipo_seguro
 * - venc_seguro
 *
 * IMPORTANTE: Executar APOS a migration 00285 que migra os dados existentes.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->table('veiculos', function ($table) {
            $table->dropColumn('venc_licenciamento');
            $table->dropColumn('companhia_seguro');
            $table->dropColumn('tipo_seguro');
            $table->dropColumn('venc_seguro');
        });
    }

    public function down(): void
    {
        $this->table('veiculos', function ($table) {
            $table->addColumn('venc_licenciamento', 'date', ['null' => true]);
            $table->addColumn('companhia_seguro', 'string', ['length' => 255, 'null' => true]);
            $table->addColumn('tipo_seguro', 'string', ['length' => 100, 'null' => true]);
            $table->addColumn('venc_seguro', 'date', ['null' => true]);
        });
    }
};
