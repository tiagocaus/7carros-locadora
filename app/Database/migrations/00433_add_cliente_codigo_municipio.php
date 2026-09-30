<?php

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfNotExists('clientes', 'codigo_municipio', 'VARCHAR(7)', [
            'null' => true,
            'after' => 'cidade',
            'comment' => 'Codigo IBGE do municipio brasileiro do cliente',
        ]);
    }

    public function down(): void
    {
        $this->dropColumnIfExists('clientes', 'codigo_municipio');
    }
};
