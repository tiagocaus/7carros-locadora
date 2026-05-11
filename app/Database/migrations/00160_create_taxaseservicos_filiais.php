<?php

/**
 * Migração: Criar tabela taxaseservicos_filiais
 *
 * Implementa relacionamento N:N entre taxas/serviços e filiais,
 * permitindo que uma taxa esteja disponível em múltiplas filiais.
 */

use App\Database\Migration;

return new class extends Migration {
    public function up(): void
    {
        // Criar tabela de relacionamento N:N
        $this->execute("
            CREATE TABLE IF NOT EXISTS taxaseservicos_filiais (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                id_taxaservico INT(100) UNSIGNED NOT NULL,
                id_matriz_filial INT(100) UNSIGNED NOT NULL,
                chave VARCHAR(45) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_taxaservico_filial (id_taxaservico, id_matriz_filial),
                KEY idx_tsf_chave (chave),
                KEY idx_tsf_filial (id_matriz_filial),
                CONSTRAINT fk_tsf_taxaservico FOREIGN KEY (id_taxaservico)
                    REFERENCES taxaseservicos (id) ON DELETE CASCADE,
                CONSTRAINT fk_tsf_matriz_filial FOREIGN KEY (id_matriz_filial)
                    REFERENCES matrizes_filiais (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Migrar dados existentes de id_matriz_filial para a nova tabela
        // Usando INSERT IGNORE para ignorar duplicatas
        $this->execute("
            INSERT IGNORE INTO taxaseservicos_filiais (id_taxaservico, id_matriz_filial, chave)
            SELECT id, id_matriz_filial, chave
            FROM taxaseservicos
            WHERE id_matriz_filial IS NOT NULL
        ");

        // Tabela taxaseservicos_filiais criada e dados migrados com sucesso
    }

    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS taxaseservicos_filiais");
        // Tabela taxaseservicos_filiais removida
    }
};
