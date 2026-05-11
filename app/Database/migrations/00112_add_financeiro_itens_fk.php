<?php

/**
 * Migration 00112: Adicionar Foreign Keys em financeiro_itens
 *
 * Cria as constraints de integridade referencial:
 * - id_financeiro -> financeiro.id (CASCADE DELETE)
 * - id_veiculo -> veiculos.id (SET NULL)
 * - id_plano_de_conta -> planos_de_contas.id (SET NULL)
 *
 * Executada apos a migracao de dados para evitar erros de FK
 * durante o INSERT em lote.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Antes de criar FKs, limpar registros orfaos que podem existir

        // Remover itens com id_financeiro invalido
        $this->execute("
            DELETE fi FROM financeiro_itens fi
            LEFT JOIN financeiro f ON fi.id_financeiro = f.id
            WHERE f.id IS NULL
        ");

        // Limpar id_veiculo invalidos
        if ($this->tableExists('veiculos')) {
            $this->execute("
                UPDATE financeiro_itens fi
                LEFT JOIN veiculos v ON fi.id_veiculo = v.id
                SET fi.id_veiculo = NULL
                WHERE fi.id_veiculo IS NOT NULL AND v.id IS NULL
            ");
        }

        // Limpar id_plano_de_conta invalidos
        if ($this->tableExists('planos_de_contas')) {
            $this->execute("
                UPDATE financeiro_itens fi
                LEFT JOIN planos_de_contas pc ON fi.id_plano_de_conta = pc.id
                SET fi.id_plano_de_conta = NULL
                WHERE fi.id_plano_de_conta IS NOT NULL AND pc.id IS NULL
            ");
        }

        // FK para financeiro (CASCADE DELETE - quando apagar fatura, apaga itens)
        $this->addForeignKeyIfNotExists(
            'financeiro_itens',
            'id_financeiro',
            'financeiro',
            'id',
            'CASCADE',  // ON DELETE
            'CASCADE',  // ON UPDATE
            'fk_financeiro_itens_financeiro'
        );

        // FK para veiculos (SET NULL - se apagar veiculo, mantem item)
        if ($this->tableExists('veiculos')) {
            $this->addForeignKeyIfNotExists(
                'financeiro_itens',
                'id_veiculo',
                'veiculos',
                'id',
                'SET NULL',  // ON DELETE
                'CASCADE',   // ON UPDATE
                'fk_financeiro_itens_veiculo'
            );
        }

        // FK para planos_de_contas (SET NULL - se apagar plano, mantem item)
        if ($this->tableExists('planos_de_contas')) {
            $this->addForeignKeyIfNotExists(
                'financeiro_itens',
                'id_plano_de_conta',
                'planos_de_contas',
                'id',
                'SET NULL',  // ON DELETE
                'CASCADE',   // ON UPDATE
                'fk_financeiro_itens_plano_de_conta'
            );
        }

        // Indices adicionais para JOINs frequentes
        $this->addIndexIfNotExists('financeiro_itens', 'id_veiculo', 'idx_fi_id_veiculo');
        $this->addIndexIfNotExists('financeiro_itens', 'id_plano_de_conta', 'idx_fi_id_plano_de_conta');
    }

    public function down(): void
    {
        // Remover FKs
        $this->dropForeignKeyIfExists('financeiro_itens', 'fk_financeiro_itens_financeiro');
        $this->dropForeignKeyIfExists('financeiro_itens', 'fk_financeiro_itens_veiculo');
        $this->dropForeignKeyIfExists('financeiro_itens', 'fk_financeiro_itens_plano_de_conta');

        // Remover indices
        $this->dropIndexIfExists('financeiro_itens', 'idx_fi_id_veiculo');
        $this->dropIndexIfExists('financeiro_itens', 'idx_fi_id_plano_de_conta');
    }
};
