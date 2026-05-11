<?php

/**
 * Migration: Criar tabelas SMTP para integracao com provedores de email
 *
 * - Tabela principal para armazenar conexoes SMTP (Gmail, Outlook, SendGrid, etc)
 * - Tabela pivot para vincular conexoes a filiais
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
        // Tabela principal smtp
        $this->execute("
            CREATE TABLE IF NOT EXISTS `smtp` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `chave` VARCHAR(45) NOT NULL COMMENT 'Tenant identifier',
                `provider` VARCHAR(50) NOT NULL DEFAULT 'smtp_custom' COMMENT 'gmail, outlook, aws_ses, sendgrid, mailgun, smtp_custom',
                `nome` VARCHAR(100) NOT NULL COMMENT 'Nome identificador da conexao',
                `host` VARCHAR(255) NOT NULL COMMENT 'Servidor SMTP',
                `port` SMALLINT UNSIGNED NOT NULL DEFAULT 587 COMMENT 'Porta SMTP',
                `encryption` ENUM('none', 'ssl', 'tls') NOT NULL DEFAULT 'tls',
                `username` VARCHAR(255) NOT NULL COMMENT 'Email/usuario autenticacao',
                `password` VARCHAR(512) NOT NULL COMMENT 'Senha criptografada',
                `from_email` VARCHAR(255) NOT NULL COMMENT 'Email remetente',
                `from_name` VARCHAR(255) NOT NULL COMMENT 'Nome remetente',
                `reply_to_email` VARCHAR(255) NULL COMMENT 'Email resposta (opcional)',
                `reply_to_name` VARCHAR(255) NULL COMMENT 'Nome resposta (opcional)',
                `daily_limit` INT UNSIGNED NULL COMMENT 'Limite diario de envios (opcional)',
                `status` ENUM('pending', 'validated', 'invalid') NOT NULL DEFAULT 'pending',
                `validated_at` DATETIME NULL COMMENT 'Data da ultima validacao bem-sucedida',
                `last_error` TEXT NULL COMMENT 'Ultimo erro de validacao',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_smtp_chave` (`chave`),
                INDEX `idx_smtp_status` (`status`),
                INDEX `idx_smtp_chave_status` (`chave`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Tabela pivot smtp_filiais
        $this->execute("
            CREATE TABLE IF NOT EXISTS `smtp_filiais` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_smtp` BIGINT UNSIGNED NOT NULL,
                `id_matriz_filial` INT UNSIGNED NOT NULL,
                `chave` VARCHAR(100) NOT NULL COMMENT 'Tenant identifier',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_smtp_filial` (`id_smtp`, `id_matriz_filial`),
                INDEX `idx_smtp_filiais_smtp` (`id_smtp`),
                INDEX `idx_smtp_filiais_filial` (`id_matriz_filial`),
                INDEX `idx_smtp_filiais_chave` (`chave`),
                CONSTRAINT `fk_smtp_filiais_smtp` FOREIGN KEY (`id_smtp`) REFERENCES `smtp` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_smtp_filiais_filial` FOREIGN KEY (`id_matriz_filial`) REFERENCES `matrizes_filiais` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Reverte a migration
     */
    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS `smtp_filiais`");
        $this->execute("DROP TABLE IF EXISTS `smtp`");
    }
};
