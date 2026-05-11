<?php

use App\Database\Migration;

/**
 * Migration: Adicionar coluna chave em horarios_excecoes
 *
 * Adiciona a coluna chave para isolamento multi-tenant e migra os dados
 * existentes buscando a chave da matriz_filial associada.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Adicionar coluna chave
        $this->addColumnIfNotExists('horarios_excecoes', 'chave', 'VARCHAR(45)', [
            'null' => false,
            'default' => '',
            'after' => 'id'
        ]);

        // 2. Migrar dados: copiar chave da matriz_filial
        $this->execute("
            UPDATE horarios_excecoes h
            SET h.chave = (
                SELECT m.chave
                FROM matrizes_filiais m
                WHERE m.id = h.matriz_filial_id
            )
            WHERE h.chave = ''
        ");

        // 3. Adicionar índice
        $this->addIndexIfNotExists('horarios_excecoes', 'chave', 'idx_he_chave');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('horarios_excecoes', 'idx_he_chave');
        $this->dropColumnIfExists('horarios_excecoes', 'chave');
    }
};
