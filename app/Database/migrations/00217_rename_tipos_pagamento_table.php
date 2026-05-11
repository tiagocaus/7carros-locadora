<?php

use App\Database\Migration;

/**
 * Migration: Renomear tabela tipos_pagamento e remover coluna icone
 *
 * - Renomeia tipos_pagamento → formas_pagamento_tipos (padronizacao)
 * - Remove coluna icone (nao utilizada)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Remover FK constraint existente
        $this->table('formas_pagamento', function ($table) {
            $table->dropForeign('fk_formas_pagamento_id_tipo_pagamento');
        });

        // 2. Renomear tabela
        if ($this->tableExists('tipos_pagamento') && !$this->tableExists('formas_pagamento_tipos')) {
            $this->renameTable('tipos_pagamento', 'formas_pagamento_tipos');
        }

        // 3. Remover coluna icone
        $this->table('formas_pagamento_tipos', function ($table) {
            $table->dropColumn('icone');
        });

        // 4. Recriar FK constraint apontando para nova tabela
        $this->table('formas_pagamento', function ($table) {
            $table->foreign('id_tipo_pagamento')
                ->on('formas_pagamento_tipos')
                ->references('id')
                ->onDelete('SET NULL')
                ->onUpdate('CASCADE');
        });
    }

    public function down(): void
    {
        // 1. Remover FK constraint
        $this->table('formas_pagamento', function ($table) {
            $table->dropForeign('fk_formas_pagamento_id_tipo_pagamento');
        });

        // 2. Adicionar coluna icone de volta
        $this->table('formas_pagamento_tipos', function ($table) {
            $table->string('icone', 50)->nullable()->after('nome');
        });

        // 3. Renomear tabela de volta
        if ($this->tableExists('formas_pagamento_tipos') && !$this->tableExists('tipos_pagamento')) {
            $this->renameTable('formas_pagamento_tipos', 'tipos_pagamento');
        }

        // 4. Recriar FK constraint apontando para tabela original
        $this->table('formas_pagamento', function ($table) {
            $table->foreign('id_tipo_pagamento')
                ->on('tipos_pagamento')
                ->references('id')
                ->onDelete('SET NULL')
                ->onUpdate('CASCADE');
        });
    }
};
