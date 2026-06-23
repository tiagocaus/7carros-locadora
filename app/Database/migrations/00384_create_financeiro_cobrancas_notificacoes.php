<?php

/**
 * Migration 00384: Controle de notificacoes automaticas de cobranca.
 *
 * Registra os lembretes pre-vencimento e avisos de atraso enviados pelo CRON
 * para evitar reenvio indevido por canal.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->tableExists('financeiro_cobrancas_notificacoes')) {
            return;
        }

        $this->execute("
            CREATE TABLE financeiro_cobrancas_notificacoes (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                chave VARCHAR(45) NOT NULL,
                id_financeiro INT UNSIGNED NOT NULL,
                tipo ENUM('pre_due', 'overdue') NOT NULL,
                canal ENUM('email', 'whatsapp', 'sms') NOT NULL,
                data_referencia DATE NOT NULL,
                last_sent_at DATETIME NOT NULL,
                message_id INT UNSIGNED NULL,
                status ENUM('queued', 'skipped', 'failed') NOT NULL DEFAULT 'queued',
                error_message TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_fin_cobranca_ref (chave, id_financeiro, tipo, canal, data_referencia),
                KEY idx_fin_cobranca_lookup (chave, id_financeiro, tipo, canal, last_sent_at),
                KEY idx_fin_cobranca_chave_tipo (chave, tipo, data_referencia)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->drop('financeiro_cobrancas_notificacoes');
    }
};
