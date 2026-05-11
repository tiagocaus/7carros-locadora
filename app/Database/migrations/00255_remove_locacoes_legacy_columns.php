<?php

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Dropar FK antes de remover coluna
        $this->dropForeignKeyIfExists('locacoes', 'fk_locacoes_id_financeiro_deposito');

        $columns = [
            'deposito_tipo',
            'deposito_valor',
            'id_financeiro_deposito',
        ];

        foreach ($columns as $col) {
            $this->dropColumnIfExists('locacoes', $col);
        }

        echo "Removidas " . count($columns) . " colunas legado (deposito) de locacoes\n";
    }

    public function down(): void
    {
        $this->addColumnIfNotExists('locacoes', 'deposito_tipo', 'VARCHAR', [
            'length' => 20,
            'nullable' => true,
        ]);
        $this->addColumnIfNotExists('locacoes', 'deposito_valor', 'DECIMAL(10,2)', [
            'nullable' => true,
        ]);
        $this->addColumnIfNotExists('locacoes', 'id_financeiro_deposito', 'INT', [
            'nullable' => true,
            'unsigned' => true,
        ]);

        echo "Down: colunas legado (deposito) restauradas\n";
    }
};
