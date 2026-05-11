<?php

use App\Database\Migration;

/**
 * Migration: Adicionar coluna chave em contatos_emails
 *
 * Adiciona a coluna chave para isolamento multi-tenant e migra os dados
 * existentes buscando a chave da entidade associada (matriz_filial ou cliente).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Adicionar coluna chave
        $this->addColumnIfNotExists('contatos_emails', 'chave', 'VARCHAR(45)', [
            'null' => false,
            'default' => '',
            'after' => 'id'
        ]);

        // 2. Migrar dados de matriz_filial
        $this->execute("
            UPDATE contatos_emails ce
            SET ce.chave = (
                SELECT m.chave
                FROM matrizes_filiais m
                WHERE m.id = ce.entidade_id
            )
            WHERE ce.entidade_tipo = 'matriz_filial' AND ce.chave = ''
        ");

        // 3. Migrar dados de cliente
        $this->execute("
            UPDATE contatos_emails ce
            SET ce.chave = (
                SELECT c.chave
                FROM clientes c
                WHERE c.id = ce.entidade_id
            )
            WHERE ce.entidade_tipo = 'cliente' AND ce.chave = ''
        ");

        // 4. Adicionar índice
        $this->addIndexIfNotExists('contatos_emails', 'chave', 'idx_ce_chave');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('contatos_emails', 'idx_ce_chave');
        $this->dropColumnIfExists('contatos_emails', 'chave');
    }
};
