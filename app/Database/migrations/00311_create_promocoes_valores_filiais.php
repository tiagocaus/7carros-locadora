<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela promocoes_valores_filiais
 *
 * Para promocoes com tipo FIX (valor fixo monetario), cada filial
 * cadastra o valor na sua propria moeda. Promocoes PER (percentual)
 * continuam usando o valor unico em `promocoes.valor`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->tableExists('promocoes_valores_filiais')) {
            return;
        }

        $this->execute("
            CREATE TABLE promocoes_valores_filiais (
                id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave               VARCHAR(45) NOT NULL,
                id_promocao         INT UNSIGNED NOT NULL,
                id_matriz_filial    INT UNSIGNED NOT NULL,
                valor               DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at          DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uk_promo_filial (id_promocao, id_matriz_filial),
                INDEX idx_pvf_chave (chave),

                CONSTRAINT fk_pvf_promo FOREIGN KEY (id_promocao)
                    REFERENCES promocoes(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_pvf_filial FOREIGN KEY (id_matriz_filial)
                    REFERENCES matrizes_filiais(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Valor fixo de promocao por filial (so para tipo FIX)'
        ");
    }

    public function down(): void
    {
        $this->drop('promocoes_valores_filiais');
    }
};
