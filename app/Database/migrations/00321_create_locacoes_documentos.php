<?php

/**
 * Migration: tabela de documentos anexados a uma locacao/reserva.
 *
 * Enviados pelo cliente no passo 4 do site quando site_config.envio_documentos=1.
 * Armazena os nomes de arquivo retornados por ImageHelper::save (ou FileHelper::save para PDFs).
 * Acesso aos arquivos via FileHelper::url($arquivo, $chave) — token HMAC assinado.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE IF NOT EXISTS locacoes_documentos (
                id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave       VARCHAR(45) NOT NULL,
                id_locacao  INT UNSIGNED NOT NULL,
                tipo        ENUM('cnh','cpf','rg','comprovante') NOT NULL,
                arquivo     VARCHAR(255) NOT NULL COMMENT 'Nome do arquivo salvo em storage/uploads/{chave}/',
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_locacao_tipo (id_locacao, tipo),
                INDEX idx_chave (chave),
                INDEX idx_locacao (id_locacao),
                CONSTRAINT fk_ld_locacao FOREIGN KEY (id_locacao) REFERENCES locacoes(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS locacoes_documentos");
    }
};
