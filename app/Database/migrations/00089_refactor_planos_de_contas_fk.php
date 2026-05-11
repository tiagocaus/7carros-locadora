<?php

/**
 * Migration 00089: Refatorar Planos de Contas com FK
 *
 * Migra financeiro.id_plano_conta (VARCHAR que armazena hierarquia)
 * para financeiro.id_plano_de_conta (INT com FK para planos_de_contas.id)
 *
 * Alterações:
 * 1. Renomeia tabela: planos_contas → planos_de_contas
 * 2. Adiciona coluna id_plano_de_conta (INT UNSIGNED) em financeiro
 * 3. Popula nova coluna via JOIN na hierarquia
 * 4. Remove coluna antiga id_plano_conta
 * 5. Cria FK com ON DELETE SET NULL
 * 6. Cria índice na nova coluna
 *
 * Impacto: 50 registros órfãos terão id_plano_de_conta = NULL
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Passo 1: Renomear tabela planos_contas → planos_de_contas
        if ($this->tableExists('planos_contas') && !$this->tableExists('planos_de_contas')) {
            $this->renameTable('planos_contas', 'planos_de_contas');
        }

        // Passo 2: Adicionar coluna id_plano_de_conta em financeiro
        $this->addColumnIfNotExists('financeiro', 'id_plano_de_conta', 'INT', [
            'unsigned' => true,
            'null' => true,
            'after' => 'id_plano_conta'
        ]);

        // Passo 3: Popular nova coluna via JOIN (hierarquia → id)
        if ($this->columnExists('financeiro', 'id_plano_conta') &&
            $this->columnExists('financeiro', 'id_plano_de_conta')) {
            $this->execute("
                UPDATE financeiro f
                JOIN planos_de_contas pc ON f.id_plano_conta = pc.hierarquia
                SET f.id_plano_de_conta = pc.id
                WHERE f.id_plano_conta IS NOT NULL
                  AND f.id_plano_conta != ''
            ");
        }

        // Passo 4: Remover coluna antiga id_plano_conta
        $this->dropColumnIfExists('financeiro', 'id_plano_conta');

        // Passo 5: Adicionar FK
        $this->addForeignKeyIfNotExists(
            'financeiro',
            'id_plano_de_conta',
            'planos_de_contas',
            'id',
            'SET NULL',
            'CASCADE',
            'fk_financeiro_plano_de_conta'
        );

        // Passo 6: Adicionar índice
        $this->addIndexIfNotExists('financeiro', 'id_plano_de_conta');
    }

    public function down(): void
    {
        // Remover FK
        $this->dropForeignKeyIfExists('financeiro', 'fk_financeiro_plano_de_conta');

        // Remover índice
        $this->dropIndexIfExists('financeiro', 'idx_financeiro_id_plano_de_conta');

        // Adicionar coluna antiga id_plano_conta
        $this->addColumnIfNotExists('financeiro', 'id_plano_conta', 'VARCHAR', [
            'length' => 20,
            'null' => true,
            'after' => 'id_plano_de_conta'
        ]);

        // Popular coluna antiga via JOIN reverso (id → hierarquia)
        if ($this->columnExists('financeiro', 'id_plano_conta') &&
            $this->columnExists('financeiro', 'id_plano_de_conta')) {
            $this->execute("
                UPDATE financeiro f
                JOIN planos_de_contas pc ON f.id_plano_de_conta = pc.id
                SET f.id_plano_conta = pc.hierarquia
                WHERE f.id_plano_de_conta IS NOT NULL
            ");
        }

        // Remover coluna nova id_plano_de_conta
        $this->dropColumnIfExists('financeiro', 'id_plano_de_conta');

        // Renomear tabela de volta: planos_de_contas → planos_contas
        if ($this->tableExists('planos_de_contas') && !$this->tableExists('planos_contas')) {
            $this->renameTable('planos_de_contas', 'planos_contas');
        }
    }
};
