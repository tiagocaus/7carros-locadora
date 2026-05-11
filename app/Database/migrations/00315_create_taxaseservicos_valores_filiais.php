<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela taxaseservicos_valores_filiais
 *
 * Para taxas/servicos com tipo_valor=MON (monetario), cada filial cadastra
 * o valor na propria moeda. Taxas com tipo_valor=POR (%) continuam usando
 * o valor unico em `taxaseservicos.valor` (porcentagem e universal).
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->tableExists('taxaseservicos_valores_filiais')) {
            return;
        }

        $this->execute("
            CREATE TABLE taxaseservicos_valores_filiais (
                id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave               VARCHAR(45) NOT NULL,
                id_taxaservico      INT UNSIGNED NOT NULL,
                id_matriz_filial    INT UNSIGNED NOT NULL,
                valor               DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at          DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uk_taxaservico_filial (id_taxaservico, id_matriz_filial),
                INDEX idx_tsvf_chave (chave),

                CONSTRAINT fk_tsvf_taxa FOREIGN KEY (id_taxaservico)
                    REFERENCES taxaseservicos(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_tsvf_filial FOREIGN KEY (id_matriz_filial)
                    REFERENCES matrizes_filiais(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Valor monetario de taxas/servicos por filial (so para tipo_valor=MON)'
        ");
    }

    public function down(): void
    {
        $this->drop('taxaseservicos_valores_filiais');
    }
};
