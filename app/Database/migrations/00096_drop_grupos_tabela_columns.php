<?php

use App\Database\Migration;

/**
 * Migration: Remover colunas legadas de tabela de preços da tabela grupos
 *
 * Remove as colunas usar_tabela_* e tabela_* que foram substituídas
 * pela tabela normalizada grupos_precos_dias.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->table('grupos', function ($table) {
            $table->dropColumn('usar_tabela_diarias');
            $table->dropColumn('tabela_diarias');
            $table->dropColumn('usar_tabela_km_controlado');
            $table->dropColumn('tabela_km_controlado');
            $table->dropColumn('usar_tabela_km_livre');
            $table->dropColumn('tabela_km_livre');
        });
    }

    public function down(): void
    {
        $this->table('grupos', function ($table) {
            $table->addColumn('usar_tabela_diarias', 'tinyint', [
                'length' => 1, 'null' => false, 'default' => 0
            ]);
            $table->addColumn('tabela_diarias', 'mediumtext', ['null' => true]);
            $table->addColumn('usar_tabela_km_controlado', 'tinyint', [
                'length' => 1, 'null' => false, 'default' => 0
            ]);
            $table->addColumn('tabela_km_controlado', 'mediumtext', ['null' => true]);
            $table->addColumn('usar_tabela_km_livre', 'tinyint', [
                'length' => 1, 'null' => false, 'default' => 0
            ]);
            $table->addColumn('tabela_km_livre', 'mediumtext', ['null' => true]);
        });
    }
};
