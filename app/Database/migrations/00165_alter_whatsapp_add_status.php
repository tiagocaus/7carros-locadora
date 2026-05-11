<?php

/**
 * Migration: Adiciona campos de status na tabela whatsapp
 *
 * Adiciona campos para gerenciamento de conexoes WhatsApp via Evolution API
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Executa a migration
     */
    public function up(): void
    {
        // Status da conexao
        $this->addColumnIfNotExists('whatsapp', 'status', "ENUM('disconnected', 'connecting', 'connected')", [
            'null' => false,
            'default' => 'disconnected',
            'after' => 'remoteJid'
        ]);

        // Conexao global (para todas as empresas)
        $this->addColumnIfNotExists('whatsapp', 'global', "ENUM('S', 'N')", [
            'null' => false,
            'default' => 'N',
            'after' => 'status'
        ]);

        // Data/hora da conexao
        $this->addColumnIfNotExists('whatsapp', 'connected_at', 'DATETIME', [
            'null' => true,
            'after' => 'global'
        ]);

        // Timestamps
        $this->addColumnIfNotExists('whatsapp', 'created_at', 'TIMESTAMP', [
            'null' => false,
            'default' => 'CURRENT_TIMESTAMP',
            'after' => 'connected_at'
        ]);

        $this->addColumnIfNotExists('whatsapp', 'updated_at', 'TIMESTAMP', [
            'null' => false,
            'default' => 'CURRENT_TIMESTAMP',
            'after' => 'created_at'
        ]);

        // Adicionar ON UPDATE CURRENT_TIMESTAMP manualmente
        if ($this->columnExists('whatsapp', 'updated_at')) {
            $this->execute("ALTER TABLE `whatsapp` MODIFY COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }

        // Indices
        $this->addIndexIfNotExists('whatsapp', ['chave', 'status'], 'idx_whatsapp_chave_status');
        $this->addIndexIfNotExists('whatsapp', ['global', 'status'], 'idx_whatsapp_global_status');
    }

    /**
     * Reverte a migration
     */
    public function down(): void
    {
        // Remove indices
        $this->dropIndexIfExists('whatsapp', 'idx_whatsapp_chave_status');
        $this->dropIndexIfExists('whatsapp', 'idx_whatsapp_global_status');

        // Remove colunas
        $this->dropColumnIfExists('whatsapp', 'updated_at');
        $this->dropColumnIfExists('whatsapp', 'created_at');
        $this->dropColumnIfExists('whatsapp', 'connected_at');
        $this->dropColumnIfExists('whatsapp', 'global');
        $this->dropColumnIfExists('whatsapp', 'status');
    }
};
