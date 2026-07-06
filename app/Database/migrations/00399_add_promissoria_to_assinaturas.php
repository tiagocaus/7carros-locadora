<?php

use App\Database\Migration;

/**
 * Adiciona suporte a assinaturas digitais de promissorias agrupadas por codigo_base.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfNotExists('assinaturas', 'codigo_promissoria', 'VARCHAR(20)', [
            'null' => true,
            'after' => 'id_locacao',
        ]);

        $this->addIndexIfNotExists(
            'assinaturas',
            ['chave', 'codigo_promissoria'],
            'idx_assinaturas_promissoria'
        );
    }

    public function down(): void
    {
        $this->dropIndexIfExists('assinaturas', 'idx_assinaturas_promissoria');
        $this->dropColumnIfExists('assinaturas', 'codigo_promissoria');
    }
};
