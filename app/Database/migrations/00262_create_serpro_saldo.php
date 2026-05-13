<?php

/**
 * Migration 00262: Criar tabela serpro_saldo
 *
 * Saldo prepago de cada tenant para uso da API de consultas online.
 * Inclui configuracao de auto-recarga via Stripe.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->tableExists('serpro_saldo')) {
            return;
        }

        $this->execute("
            CREATE TABLE serpro_saldo (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave VARCHAR(45) NOT NULL COMMENT 'Chave do tenant',

                saldo DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Saldo atual em BRL',

                auto_recarga_ativo TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=desativado, 1=ativado',
                auto_recarga_valor DECIMAL(10,2) NOT NULL DEFAULT 100.00 COMMENT 'Valor da recarga automatica',
                auto_recarga_limite DECIMAL(10,2) NOT NULL DEFAULT 10.00 COMMENT 'Saldo minimo para disparar auto-recarga',

                stripe_customer_id VARCHAR(255) NULL COMMENT 'Stripe customer ID (cus_XXXXX)',
                stripe_payment_method_id VARCHAR(255) NULL COMMENT 'Stripe payment method ID (pm_XXXXX)',

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE INDEX idx_ss_chave (chave)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Saldo prepago de consultas online por tenant'
        ");
    }

    public function down(): void
    {
        $this->drop('serpro_saldo');
    }
};
