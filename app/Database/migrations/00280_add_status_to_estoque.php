<?php

/**
 * Migration 00280: Adicionar coluna status ao estoque
 *
 * Adiciona coluna de status para controlar disponibilidade de produtos.
 * Produtos vinculados a manutenções não podem ser excluídos,
 * apenas inativados.
 *
 * Valores:
 * - A = Ativo (disponível)
 * - I = Inativo (indisponível)
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfNotExists('estoque', 'status', 'CHAR(1)', [
            'null' => false,
            'default' => 'A',
            'after' => 'valor_venda'
        ]);

        $this->addIndexIfNotExists('estoque', ['chave', 'status'], 'idx_estoque_chave_status');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('estoque', 'idx_estoque_chave_status');
        $this->dropColumnIfExists('estoque', 'status');
    }
};
