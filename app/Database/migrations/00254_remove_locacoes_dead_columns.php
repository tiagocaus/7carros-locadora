<?php

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'historico',
            'array_avaria',
            'tolerancia',
            'entregue_por_nome',
            'entregue_por_cnh',
            'status_checklist',
        ];

        foreach ($columns as $col) {
            $this->dropColumnIfExists('locacoes', $col);
        }

        echo "Removidas " . count($columns) . " colunas mortas de locacoes\n";
    }

    public function down(): void
    {
        // Colunas mortas sem uso ativo - nao restaurar
        echo "Down: colunas mortas nao restauradas (sem uso ativo)\n";
    }
};
