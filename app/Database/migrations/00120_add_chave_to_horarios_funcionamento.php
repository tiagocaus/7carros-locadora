<?php

use App\Database\Migration;

/**
 * Migration: Adicionar coluna chave em horarios_funcionamento
 *
 * Adiciona a coluna chave para isolamento multi-tenant e migra os dados
 * existentes buscando a chave da matriz_filial associada.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Adicionar coluna chave
        $this->addColumnIfNotExists('horarios_funcionamento', 'chave', 'VARCHAR(45)', [
            'null' => false,
            'default' => '',
            'after' => 'id'
        ]);

        // 2. Migrar dados: copiar chave da matriz_filial
        $this->execute("
            UPDATE horarios_funcionamento h
            SET h.chave = (
                SELECT m.chave
                FROM matrizes_filiais m
                WHERE m.id = h.matriz_filial_id
            )
            WHERE h.chave = ''
        ");

        // 3. Adicionar índice
        $this->addIndexIfNotExists('horarios_funcionamento', 'chave', 'idx_hf_chave');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('horarios_funcionamento', 'idx_hf_chave');
        $this->dropColumnIfExists('horarios_funcionamento', 'chave');
    }
};
