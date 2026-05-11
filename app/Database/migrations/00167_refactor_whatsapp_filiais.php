<?php

/**
 * Migration: Refatorar WhatsApp para N:N com filiais
 *
 * - Remove coluna `global` da tabela whatsapp (conceito errado)
 * - Cria tabela pivot `whatsapp_filiais` para relacionamento N:N
 * - Migra dados existentes vinculando cada whatsapp a sua(s) filial(is)
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Executa a migration
     */
    public function up(): void
    {
        // 1. Criar tabela pivot whatsapp_filiais
        $this->execute("
            CREATE TABLE IF NOT EXISTS `whatsapp_filiais` (
                `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_whatsapp` INT(100) UNSIGNED NOT NULL,
                `id_matriz_filial` INT(100) UNSIGNED NOT NULL,
                `chave` VARCHAR(45) NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_whatsapp_filial` (`id_whatsapp`, `id_matriz_filial`),
                KEY `idx_wf_whatsapp` (`id_whatsapp`),
                KEY `idx_wf_filial` (`id_matriz_filial`),
                KEY `idx_wf_chave` (`chave`),
                CONSTRAINT `fk_wf_whatsapp` FOREIGN KEY (`id_whatsapp`)
                    REFERENCES `whatsapp` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_wf_matriz_filial` FOREIGN KEY (`id_matriz_filial`)
                    REFERENCES `matrizes_filiais` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 2. Migrar dados existentes:
        // Para cada whatsapp existente, vincular a TODAS as filiais da sua chave
        // (mantendo comportamento legado onde uma conexao era usada por todo o tenant)
        $this->execute("
            INSERT IGNORE INTO `whatsapp_filiais` (`id_whatsapp`, `id_matriz_filial`, `chave`)
            SELECT w.id, mf.id, w.chave
            FROM `whatsapp` w
            INNER JOIN `matrizes_filiais` mf ON mf.chave = w.chave
        ");

        // 3. Remover indice que usa a coluna global
        $this->dropIndexIfExists('whatsapp', 'idx_whatsapp_global_status');

        // 4. Remover coluna global (conceito errado - agora usamos whatsapp_filiais)
        $this->dropColumnIfExists('whatsapp', 'global');
    }

    /**
     * Reverte a migration
     */
    public function down(): void
    {
        // Recriar coluna global
        $this->addColumnIfNotExists('whatsapp', 'global', "ENUM('S', 'N')", [
            'null' => false,
            'default' => 'N',
            'after' => 'status'
        ]);

        // Recriar indice
        $this->addIndexIfNotExists('whatsapp', ['global', 'status'], 'idx_whatsapp_global_status');

        // Remover tabela pivot
        $this->execute("DROP TABLE IF EXISTS `whatsapp_filiais`");
    }
};
