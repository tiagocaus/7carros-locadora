<?php

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'opcoes',
            'opcoes_texto',
            'array_outros',
        ];

        foreach ($columns as $col) {
            $this->dropColumnIfExists('locacoes', $col);
        }

        echo "Removidas " . count($columns) . " colunas de taxas de locacoes (migradas para locacoes_taxaseservicos)\n";
    }

    public function down(): void
    {
        $this->addColumnIfNotExists('locacoes', 'opcoes', 'TEXT', [
            'nullable' => true,
        ]);
        $this->addColumnIfNotExists('locacoes', 'opcoes_texto', 'TEXT', [
            'nullable' => true,
        ]);
        $this->addColumnIfNotExists('locacoes', 'array_outros', 'TEXT', [
            'nullable' => true,
        ]);

        echo "Down: colunas de taxas restauradas (estrutura apenas, sem dados)\n";
    }
};
