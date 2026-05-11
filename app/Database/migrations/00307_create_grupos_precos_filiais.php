<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela grupos_precos_filiais
 *
 * Tabela de precos de grupo por filial. Resolve o problema multi-moeda:
 * cada filial tem seus proprios valores na moeda dela (EUR, USD, BRL, etc).
 *
 * As colunas de valor em `grupos` permanecem como fallback durante a transicao.
 * Remover apenas na Fase 4 apos valida\u00e7\u00e3o.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->tableExists('grupos_precos_filiais')) {
            return;
        }

        $this->execute("
            CREATE TABLE grupos_precos_filiais (
                id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave                       VARCHAR(45) NOT NULL,
                id_grupo                    INT UNSIGNED NOT NULL,
                id_matriz_filial            INT UNSIGNED NOT NULL,

                -- Planos de locacao
                valor_plano_km_pago         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                valor_plano_km_controlado   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                valor_plano_km_livre        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                valor_km_excedente          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                km_franquia                 INT UNSIGNED NOT NULL DEFAULT 0,

                -- Seguros
                valor_seguro_carro          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                valor_seguro_terceiros      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                cobertura_carro             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                cobertura_terceiros         DECIMAL(12,2) NOT NULL DEFAULT 0.00,

                -- Tolerancia e extras
                minutos_tolerancia          INT UNSIGNED NOT NULL DEFAULT 0,
                valor_tolerancia            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                valor_km_retorno            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                valor_condutor_adicional    DECIMAL(10,2) NOT NULL DEFAULT 0.00,

                created_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at                  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uk_grupo_filial (id_grupo, id_matriz_filial),
                INDEX idx_gpf_chave (chave),
                INDEX idx_gpf_grupo (id_grupo),
                INDEX idx_gpf_filial (id_matriz_filial),

                CONSTRAINT fk_gpf_grupo FOREIGN KEY (id_grupo)
                    REFERENCES grupos(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_gpf_filial FOREIGN KEY (id_matriz_filial)
                    REFERENCES matrizes_filiais(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Precos de grupo por filial — multi-moeda'
        ");
    }

    public function down(): void
    {
        $this->drop('grupos_precos_filiais');
    }
};
