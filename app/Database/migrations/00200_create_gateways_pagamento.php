<?php

/**
 * Migration 00200: Criar Tabela gateways_pagamento
 *
 * Tabela para armazenar configurações de gateways de pagamento por tenant.
 * Suporta múltiplos gateways como Asaas, Stripe, Square, Cora, Inter, etc.
 *
 * Campos de credenciais são criptografados com AES-256-CBC.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->tableExists('gateways_pagamento')) {
            return;
        }

        $this->execute("
            CREATE TABLE gateways_pagamento (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave VARCHAR(45) NOT NULL COMMENT 'Chave do tenant',
                id_matriz_filial INT UNSIGNED NULL COMMENT 'Filial específica (opcional)',
                gateway_code VARCHAR(50) NOT NULL COMMENT 'Código do gateway: asaas, stripe, square, cora, inter, bradesco, itau, bancard, pagopar',
                nome VARCHAR(100) NOT NULL COMMENT 'Nome de exibição configurado pelo usuário',
                credentials TEXT NULL COMMENT 'JSON criptografado com credenciais do gateway',
                ambiente ENUM('sandbox', 'production') NOT NULL DEFAULT 'sandbox' COMMENT 'Ambiente da API',
                status ENUM('A', 'I') NOT NULL DEFAULT 'A' COMMENT 'A=Ativo, I=Inativo',
                pix_enabled TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'PIX habilitado',
                boleto_enabled TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Boleto habilitado',
                credit_card_enabled TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Cartão de crédito habilitado',
                debit_card_enabled TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Cartão de débito habilitado',
                webhook_url VARCHAR(255) NULL COMMENT 'URL de webhook gerada pelo sistema',
                webhook_secret VARCHAR(255) NULL COMMENT 'Secret para validação de webhook',
                ordem INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_gp_chave (chave),
                INDEX idx_gp_chave_status (chave, status),
                INDEX idx_gp_chave_gateway (chave, gateway_code),
                UNIQUE INDEX idx_gp_chave_gateway_filial (chave, gateway_code, id_matriz_filial)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Configurações de gateways de pagamento por tenant'
        ");

        // Adicionar FK para matrizes_filiais se a tabela existir
        if ($this->tableExists('matrizes_filiais')) {
            $this->addForeignKeyIfNotExists(
                'gateways_pagamento',
                'id_matriz_filial',
                'matrizes_filiais',
                'id',
                'SET NULL',
                'CASCADE',
                'fk_gp_matriz_filial'
            );
        }
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('gateways_pagamento', 'fk_gp_matriz_filial');
        $this->drop('gateways_pagamento');
    }
};
