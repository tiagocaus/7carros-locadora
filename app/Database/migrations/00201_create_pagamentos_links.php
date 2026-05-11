<?php

/**
 * Migration 00201: Criar Tabela pagamentos_links
 *
 * Tabela para armazenar links de pagamento públicos.
 * Permite que clientes paguem via URL única sem autenticação.
 *
 * URL pública: /pagar/{codigo}
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->tableExists('pagamentos_links')) {
            return;
        }

        $this->execute("
            CREATE TABLE pagamentos_links (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave VARCHAR(45) NOT NULL COMMENT 'Chave do tenant',
                codigo VARCHAR(32) NOT NULL COMMENT 'Código único público do link (32 hex chars)',
                id_financeiro INT UNSIGNED NOT NULL COMMENT 'FK para tabela financeiro',
                id_cliente INT UNSIGNED NULL COMMENT 'FK para tabela clientes (opcional)',
                valor DECIMAL(10,2) NOT NULL COMMENT 'Valor a ser pago',
                descricao TEXT NULL COMMENT 'Descrição exibida na página de pagamento',
                expires_at DATETIME NULL COMMENT 'Data de expiração do link',
                status ENUM('pending', 'paid', 'expired', 'cancelled') NOT NULL DEFAULT 'pending' COMMENT 'Status do link',
                id_transacao_paga INT UNSIGNED NULL COMMENT 'FK para financeiro_transacoes quando pago',
                ip_pagamento VARCHAR(45) NULL COMMENT 'IP do cliente que realizou o pagamento',
                user_agent_pagamento TEXT NULL COMMENT 'User-Agent do cliente',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE INDEX idx_pl_codigo (codigo),
                INDEX idx_pl_chave (chave),
                INDEX idx_pl_chave_status (chave, status),
                INDEX idx_pl_financeiro (id_financeiro),
                INDEX idx_pl_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Links públicos de pagamento'
        ");

        // Adicionar FKs
        if ($this->tableExists('financeiro')) {
            $this->addForeignKeyIfNotExists(
                'pagamentos_links',
                'id_financeiro',
                'financeiro',
                'id',
                'CASCADE',
                'CASCADE',
                'fk_pl_financeiro'
            );
        }

        if ($this->tableExists('clientes')) {
            $this->addForeignKeyIfNotExists(
                'pagamentos_links',
                'id_cliente',
                'clientes',
                'id',
                'SET NULL',
                'CASCADE',
                'fk_pl_cliente'
            );
        }
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('pagamentos_links', 'fk_pl_financeiro');
        $this->dropForeignKeyIfExists('pagamentos_links', 'fk_pl_cliente');
        $this->drop('pagamentos_links');
    }
};
