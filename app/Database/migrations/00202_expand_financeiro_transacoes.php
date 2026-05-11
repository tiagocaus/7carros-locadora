<?php

/**
 * Migration 00202: Expandir Tabela financeiro_transacoes
 *
 * Adiciona colunas para suportar informações detalhadas de pagamento,
 * incluindo URLs, códigos PIX, códigos de barras e timestamps de eventos.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('financeiro_transacoes')) {
            return;
        }

        // Adicionar coluna id_gateway se não existir
        if (!$this->columnExists('financeiro_transacoes', 'id_gateway')) {
            $this->execute("
                ALTER TABLE financeiro_transacoes
                ADD COLUMN id_gateway INT UNSIGNED NULL
                COMMENT 'FK para gateways_pagamento'
                AFTER id_financeiro
            ");
        }

        // Adicionar coluna payment_method se não existir
        if (!$this->columnExists('financeiro_transacoes', 'payment_method')) {
            $this->execute("
                ALTER TABLE financeiro_transacoes
                ADD COLUMN payment_method VARCHAR(50) NULL
                COMMENT 'Método: pix, boleto, credit_card, debit_card'
                AFTER type
            ");
        }

        // Adicionar coluna payment_url se não existir
        if (!$this->columnExists('financeiro_transacoes', 'payment_url')) {
            $this->execute("
                ALTER TABLE financeiro_transacoes
                ADD COLUMN payment_url TEXT NULL
                COMMENT 'URL de pagamento retornada pelo gateway'
                AFTER status
            ");
        }

        // Adicionar coluna pix_code se não existir
        if (!$this->columnExists('financeiro_transacoes', 'pix_code')) {
            $this->execute("
                ALTER TABLE financeiro_transacoes
                ADD COLUMN pix_code TEXT NULL
                COMMENT 'Código PIX copia e cola'
                AFTER payment_url
            ");
        }

        // Adicionar coluna barcode se não existir
        if (!$this->columnExists('financeiro_transacoes', 'barcode')) {
            $this->execute("
                ALTER TABLE financeiro_transacoes
                ADD COLUMN barcode VARCHAR(255) NULL
                COMMENT 'Código de barras do boleto'
                AFTER pix_code
            ");
        }

        // Adicionar coluna expires_at se não existir
        if (!$this->columnExists('financeiro_transacoes', 'expires_at')) {
            $this->execute("
                ALTER TABLE financeiro_transacoes
                ADD COLUMN expires_at DATETIME NULL
                COMMENT 'Data de expiração da cobrança'
                AFTER amount
            ");
        }

        // Adicionar coluna paid_at se não existir
        if (!$this->columnExists('financeiro_transacoes', 'paid_at')) {
            $this->execute("
                ALTER TABLE financeiro_transacoes
                ADD COLUMN paid_at DATETIME NULL
                COMMENT 'Data/hora do pagamento confirmado'
                AFTER expires_at
            ");
        }

        // Adicionar coluna refunded_at se não existir
        if (!$this->columnExists('financeiro_transacoes', 'refunded_at')) {
            $this->execute("
                ALTER TABLE financeiro_transacoes
                ADD COLUMN refunded_at DATETIME NULL
                COMMENT 'Data/hora do reembolso'
                AFTER paid_at
            ");
        }

        // Adicionar coluna webhook_received_at se não existir
        if (!$this->columnExists('financeiro_transacoes', 'webhook_received_at')) {
            $this->execute("
                ALTER TABLE financeiro_transacoes
                ADD COLUMN webhook_received_at DATETIME NULL
                COMMENT 'Data/hora do último webhook recebido'
                AFTER refunded_at
            ");
        }

        // Adicionar índice para id_gateway se não existir
        $this->addIndexIfNotExists('financeiro_transacoes', 'id_gateway', 'idx_ft_id_gateway');

        // Adicionar FK para gateways_pagamento se a tabela existir
        if ($this->tableExists('gateways_pagamento')) {
            $this->addForeignKeyIfNotExists(
                'financeiro_transacoes',
                'id_gateway',
                'gateways_pagamento',
                'id',
                'SET NULL',
                'CASCADE',
                'fk_ft_gateway'
            );
        }
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('financeiro_transacoes', 'fk_ft_gateway');
        $this->dropIndexIfExists('financeiro_transacoes', 'idx_ft_id_gateway');

        $columns = [
            'id_gateway',
            'payment_method',
            'payment_url',
            'pix_code',
            'barcode',
            'expires_at',
            'paid_at',
            'refunded_at',
            'webhook_received_at',
        ];

        foreach ($columns as $column) {
            if ($this->columnExists('financeiro_transacoes', $column)) {
                $this->execute("ALTER TABLE financeiro_transacoes DROP COLUMN {$column}");
            }
        }
    }
};
