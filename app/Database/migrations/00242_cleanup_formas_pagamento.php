<?php

use App\Database\Migration;

/**
 * Migration: Limpeza pos-consolidacao de formas de pagamento
 *
 * Remove FK constraint, colunas obsoletas (id_tipo_pagamento, descricao, parcelas)
 * e dropa a tabela formas_pagamento_tipos.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Remover FK constraint para tipos
        $this->dropForeignKeyIfExists('formas_pagamento', 'fk_formas_pagamento_id_tipo_pagamento');

        // 2. Remover indice da coluna id_tipo_pagamento se existir
        $this->dropIndexIfExists('formas_pagamento', 'idx_formas_pagamento_id_tipo_pagamento');

        // 3. Remover colunas obsoletas
        $this->dropColumnIfExists('formas_pagamento', 'id_tipo_pagamento');
        $this->dropColumnIfExists('formas_pagamento', 'descricao');
        $this->dropColumnIfExists('formas_pagamento', 'parcelas');

        // 4. Dropar tabela de tipos
        $this->drop('formas_pagamento_tipos');
    }

    public function down(): void
    {
        // 1. Recriar tabela de tipos
        if (!$this->tableExists('formas_pagamento_tipos')) {
            $this->create('formas_pagamento_tipos', function ($table) {
                $table->id();
                $table->string('chave', 45);
                $table->string('nome', 100);
                $table->integer('ordem')->default(0);
                $table->string('status', 1)->default('A');
                $table->timestamps();

                $table->index('chave', 'idx_formas_pagamento_tipos_chave');
                $table->index(['chave', 'status'], 'idx_formas_pagamento_tipos_chave_status');
                $table->unique(['chave', 'nome'], 'uk_formas_pagamento_tipos_nome');
            });
        }

        // 2. Recriar colunas removidas
        $this->addColumnIfNotExists('formas_pagamento', 'id_tipo_pagamento', 'INT(10) UNSIGNED', [
            'null' => true,
            'default' => null,
            'after' => 'chave',
        ]);
        $this->addColumnIfNotExists('formas_pagamento', 'descricao', 'TEXT', [
            'null' => true,
            'after' => 'nome',
        ]);
        $this->addColumnIfNotExists('formas_pagamento', 'parcelas', 'MEDIUMTEXT', [
            'null' => false,
            'after' => 'descricao',
        ]);
    }
};
