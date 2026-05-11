<?php

/**
 * Migration 00271: Criar tabela nfse_eventos
 *
 * Log de eventos/auditoria das NFS-e.
 * Cada operacao (emissao, cancelamento, consulta, erro) gera um registro.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->tableExists('nfse_eventos')) {
            return;
        }

        $this->execute("
            CREATE TABLE nfse_eventos (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                id_nfse INT UNSIGNED NOT NULL COMMENT 'ID da NFS-e relacionada',

                -- Dados do evento
                tipo_evento VARCHAR(50) NOT NULL COMMENT 'Tipo: emissao, cancelamento, consulta, erro, reenvio',
                codigo_retorno VARCHAR(10) NULL COMMENT 'Codigo de retorno da SEFIN/prefeitura',
                mensagem TEXT NULL COMMENT 'Mensagem de retorno ou descricao do evento',
                xml_evento LONGTEXT NULL COMMENT 'XML completo do evento',

                -- Auditoria
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                -- Indices
                INDEX idx_nfse_ev_nfse (id_nfse),
                INDEX idx_nfse_ev_tipo (tipo_evento),
                INDEX idx_nfse_ev_codigo (codigo_retorno),
                INDEX idx_nfse_ev_data (created_at),

                -- FK CASCADE
                CONSTRAINT fk_nfse_eventos_nfse
                    FOREIGN KEY (id_nfse)
                    REFERENCES nfse(id)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE

            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Log de eventos das NFS-e'
        ");
    }

    public function down(): void
    {
        $this->drop('nfse_eventos');
    }
};
