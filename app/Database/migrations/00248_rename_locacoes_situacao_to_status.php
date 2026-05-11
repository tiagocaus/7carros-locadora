<?php

use App\Database\Migration;

/**
 * Migration: Renomear coluna situacao para status na tabela locacoes
 *
 * - Renomeia a coluna situacao -> status
 * - Atualiza o comentario para refletir os novos valores
 * - Atualiza indice idx_locacoes_chave_situacao -> idx_locacoes_chave_status
 */
return new class extends Migration
{
    public function up(): void
    {
        // Renomear coluna preservando tipo
        if ($this->columnExists('locacoes', 'situacao')) {
            $this->renameColumnPreservingType('locacoes', 'situacao', 'status');
        }

        // Atualizar comentario da coluna
        if ($this->columnExists('locacoes', 'status')) {
            $this->modifyColumn('locacoes', 'status', 'VARCHAR(1)', [
                'null' => false,
                'default' => '',
                'comment' => '[R] Reserva [A]Aberto [F]Fechado'
            ]);
        }

        // Atualizar indice
        $this->dropIndexIfExists('locacoes', 'idx_locacoes_chave_situacao');
        $this->addIndexIfNotExists('locacoes', ['chave', 'status'], 'idx_locacoes_chave_status');
    }

    public function down(): void
    {
        // Reverter nome da coluna
        if ($this->columnExists('locacoes', 'status')) {
            $this->renameColumnPreservingType('locacoes', 'status', 'situacao');
        }

        // Reverter comentario
        if ($this->columnExists('locacoes', 'situacao')) {
            $this->modifyColumn('locacoes', 'situacao', 'VARCHAR(1)', [
                'null' => false,
                'default' => '',
                'comment' => '[R] Reserva [S]Saida [C]Chegada'
            ]);
        }

        // Reverter indice
        $this->dropIndexIfExists('locacoes', 'idx_locacoes_chave_status');
        $this->addIndexIfNotExists('locacoes', ['chave', 'situacao'], 'idx_locacoes_chave_situacao');
    }
};
