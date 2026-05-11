<?php

/**
 * Migration 00263: Criar tabela serpro_transacoes
 *
 * Historico completo de todas as transacoes de saldo SERPRO (recargas e debitos).
 * Armazena valores SERPRO e valores com markup para auditoria interna.
 * O tenant ve apenas o valor_total (com markup).
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->tableExists('serpro_transacoes')) {
            return;
        }

        $this->execute("
            CREATE TABLE serpro_transacoes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave VARCHAR(45) NOT NULL COMMENT 'Chave do tenant',

                tipo ENUM('recarga_pix', 'recarga_cartao', 'recarga_manual', 'consulta', 'evento')
                    NOT NULL COMMENT 'Tipo da transacao',

                valor_serpro DECIMAL(10,4) NOT NULL DEFAULT 0.0000 COMMENT 'Custo real SERPRO (interno)',
                valor_markup DECIMAL(10,4) NOT NULL DEFAULT 0.0000 COMMENT 'Valor do markup (interno)',
                valor_total DECIMAL(10,2) NOT NULL COMMENT 'Valor final cobrado/creditado',

                saldo_anterior DECIMAL(10,2) NOT NULL COMMENT 'Saldo antes da transacao',
                saldo_posterior DECIMAL(10,2) NOT NULL COMMENT 'Saldo apos a transacao',

                descricao VARCHAR(255) NULL COMMENT 'Descricao legivel da transacao',
                referencia VARCHAR(100) NULL COMMENT 'Referencia: placa, id_multa, etc.',

                external_id VARCHAR(255) NULL COMMENT 'ID externo: txid PIX ou pi_XXXXX Stripe',
                payment_method VARCHAR(50) NULL COMMENT 'Metodo: pix, credit_card',
                payment_url VARCHAR(500) NULL COMMENT 'URL de checkout Stripe',
                pix_code TEXT NULL COMMENT 'Copia e cola PIX',
                pix_qrcode TEXT NULL COMMENT 'QR Code PIX em base64',

                status ENUM('pendente', 'confirmado', 'falha', 'cancelado', 'estornado')
                    NOT NULL DEFAULT 'pendente' COMMENT 'Status da transacao',

                confirmado_em DATETIME NULL COMMENT 'Data/hora de confirmacao do pagamento',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                INDEX idx_st_chave (chave),
                INDEX idx_st_tipo (tipo),
                INDEX idx_st_status (status),
                INDEX idx_st_created (created_at),
                INDEX idx_st_external (external_id),
                INDEX idx_st_chave_tipo (chave, tipo),
                INDEX idx_st_chave_status (chave, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Historico de transacoes de saldo SERPRO eFrotas'
        ");
    }

    public function down(): void
    {
        $this->drop('serpro_transacoes');
    }
};
