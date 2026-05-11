<?php

use App\Database\Migration;

/**
 * Migration: Adicionar coluna chave em contatos_telefones
 *
 * Adiciona a coluna chave para isolamento multi-tenant e migra os dados
 * existentes buscando a chave da entidade associada (matriz_filial ou cliente).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Adicionar coluna chave
        $this->addColumnIfNotExists('contatos_telefones', 'chave', 'VARCHAR(45)', [
            'null' => false,
            'default' => '',
            'after' => 'id'
        ]);

        // 2. Migrar dados de matriz_filial
        $this->execute("
            UPDATE contatos_telefones ct
            SET ct.chave = (
                SELECT m.chave
                FROM matrizes_filiais m
                WHERE m.id = ct.entidade_id
            )
            WHERE ct.entidade_tipo = 'matriz_filial' AND ct.chave = ''
        ");

        // 3. Migrar dados de cliente
        $this->execute("
            UPDATE contatos_telefones ct
            SET ct.chave = (
                SELECT c.chave
                FROM clientes c
                WHERE c.id = ct.entidade_id
            )
            WHERE ct.entidade_tipo = 'cliente' AND ct.chave = ''
        ");

        // 4. Adicionar índice
        $this->addIndexIfNotExists('contatos_telefones', 'chave', 'idx_ct_chave');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('contatos_telefones', 'idx_ct_chave');
        $this->dropColumnIfExists('contatos_telefones', 'chave');
    }
};
