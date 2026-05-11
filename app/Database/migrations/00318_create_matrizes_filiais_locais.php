<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela matrizes_filiais_locais
 *
 * Locais de atendimento (aliases) de uma matriz/filial. Uma filial pode ter
 * N locais onde tambem atende — no site, cada local aparece como uma opcao
 * no select de retirada/devolucao, mas todos resolvem pra mesma filial dona
 * (mesma moeda, precos, contratos). O endereco e persistido pra uso futuro
 * em calculo de km.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->tableExists('matrizes_filiais_locais')) {
            return;
        }

        $this->execute("
            CREATE TABLE matrizes_filiais_locais (
                id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave               VARCHAR(45) NOT NULL,
                id_matriz_filial    INT UNSIGNED NOT NULL,
                nome                VARCHAR(100) NULL,
                cep                 VARCHAR(9) NULL,
                rua                 VARCHAR(150) NULL,
                numero              VARCHAR(15) NULL,
                complemento         VARCHAR(100) NULL,
                bairro              VARCHAR(80) NOT NULL,
                cidade              VARCHAR(80) NOT NULL,
                estado              VARCHAR(2) NOT NULL,
                pais                VARCHAR(3) NOT NULL DEFAULT 'BR',
                created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at          DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_mfl_chave (chave),
                INDEX idx_mfl_filial (id_matriz_filial),

                CONSTRAINT fk_mfl_filial FOREIGN KEY (id_matriz_filial)
                    REFERENCES matrizes_filiais(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Locais de atendimento (aliases) de uma matriz/filial'
        ");
    }

    public function down(): void
    {
        $this->drop('matrizes_filiais_locais');
    }
};
