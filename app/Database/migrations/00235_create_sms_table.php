<?php

/**
 * Migration: Criar tabela SMS para integracao com provedores de SMS
 *
 * - Tabela principal para armazenar conexoes SMS (ClickSend, etc)
 * - Preparada para multiplos provedores no futuro
 * - Credenciais criptografadas
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
            CREATE TABLE IF NOT EXISTS `sms` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `chave` VARCHAR(45) NOT NULL COMMENT 'Tenant identifier',
                `provider` VARCHAR(50) NOT NULL DEFAULT 'clicksend' COMMENT 'SMS provider (clicksend, twilio, etc)',
                `sender_id` VARCHAR(11) NOT NULL COMMENT 'Sender ID (max 11 alphanumeric chars)',
                `username` VARCHAR(255) NOT NULL COMMENT 'Provider username/email',
                `api_key` VARCHAR(512) NOT NULL COMMENT 'Encrypted API key',
                `status` ENUM('pending', 'validated', 'invalid') NOT NULL DEFAULT 'pending' COMMENT 'Credentials validation status',
                `validated_at` DATETIME NULL COMMENT 'Last successful validation timestamp',
                `last_error` TEXT NULL COMMENT 'Last validation error message',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_sms_chave` (`chave`),
                INDEX `idx_sms_status` (`status`),
                INDEX `idx_sms_provider` (`provider`),
                INDEX `idx_sms_chave_status` (`chave`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Reverte a migration
     */
    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS `sms`");
    }
};
