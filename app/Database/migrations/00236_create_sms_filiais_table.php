<?php

/**
 * Migration: Criar tabela pivot sms_filiais
 *
 * - Relacionamento N:N entre SMS e filiais
 * - Uma filial so pode ter 1 conexao SMS (UNIQUE constraint)
 * - Permite que uma conexao SMS seja usada por multiplas filiais
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Executa a migration
     */
    public function up(): void
    {
        $this->execute("
            CREATE TABLE IF NOT EXISTS `sms_filiais` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_sms` BIGINT UNSIGNED NOT NULL,
                `id_matriz_filial` INT(100) UNSIGNED NOT NULL,
                `chave` VARCHAR(100) NOT NULL COMMENT 'Tenant identifier copy',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_sms_filial` (`id_sms`, `id_matriz_filial`),
                INDEX `idx_sf_sms` (`id_sms`),
                INDEX `idx_sf_filial` (`id_matriz_filial`),
                INDEX `idx_sf_chave` (`chave`),
                CONSTRAINT `fk_sf_sms` FOREIGN KEY (`id_sms`)
                    REFERENCES `sms` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_sf_matriz_filial` FOREIGN KEY (`id_matriz_filial`)
                    REFERENCES `matrizes_filiais` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Reverte a migration
     */
    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS `sms_filiais`");
    }
};
