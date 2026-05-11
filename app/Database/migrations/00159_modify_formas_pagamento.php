<?php

use App\Database\Migration;

/**
 * Migration para modificar tabela formas_pagamento:
 * - Remove coluna id_conta (e sua FK)
 * - Adiciona coluna descricao após nome
 * - Altera onde_exibir para suportar múltiplos valores (1=Site, 2=Sistema, 3=Aplicativo)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Remover FK de id_conta se existir
        $this->dropForeignKeyIfExists('formas_pagamento', 'fk_formas_pagamento_id_conta');

        // 2. Remover coluna id_conta
        $this->dropColumnIfExists('formas_pagamento', 'id_conta');

        // 3. Adicionar coluna descricao após nome
        if (!$this->columnExists('formas_pagamento', 'descricao')) {
            $this->execute("
                ALTER TABLE formas_pagamento
                ADD COLUMN descricao TEXT NULL AFTER nome
            ");
        }

        // 4. Copiar dados de nome para descricao (para não ficar vazio)
        $this->execute("
            UPDATE formas_pagamento
            SET descricao = nome
            WHERE descricao IS NULL OR descricao = ''
        ");

        // 5. Alterar onde_exibir para VARCHAR(50) para suportar múltiplos valores
        $this->execute("
            ALTER TABLE formas_pagamento
            MODIFY COLUMN onde_exibir VARCHAR(50) NOT NULL DEFAULT '2'
        ");

        // 6. Migrar valores antigos para novo formato
        // ambos -> 1,2,3 (Site, Sistema, Aplicativo)
        // receita -> 2 (Sistema - receitas são gerenciadas no sistema)
        // despesa -> 2 (Sistema - despesas são gerenciadas no sistema)
        $this->execute("UPDATE formas_pagamento SET onde_exibir = '1,2,3' WHERE onde_exibir = 'ambos'");
        $this->execute("UPDATE formas_pagamento SET onde_exibir = '2' WHERE onde_exibir = 'receita'");
        $this->execute("UPDATE formas_pagamento SET onde_exibir = '2' WHERE onde_exibir = 'despesa'");
    }

    public function down(): void
    {
        // Reverter onde_exibir para formato antigo
        $this->execute("UPDATE formas_pagamento SET onde_exibir = 'ambos' WHERE onde_exibir = '1,2,3'");
        $this->execute("UPDATE formas_pagamento SET onde_exibir = 'receita' WHERE onde_exibir = '2'");

        // Alterar coluna onde_exibir de volta
        $this->execute("
            ALTER TABLE formas_pagamento
            MODIFY COLUMN onde_exibir VARCHAR(20) NOT NULL DEFAULT 'ambos'
        ");

        // Remover coluna descricao
        $this->dropColumnIfExists('formas_pagamento', 'descricao');

        // Recriar coluna id_conta
        if (!$this->columnExists('formas_pagamento', 'id_conta')) {
            $this->execute("
                ALTER TABLE formas_pagamento
                ADD COLUMN id_conta INT UNSIGNED NULL AFTER chave
            ");
        }
    }
};
