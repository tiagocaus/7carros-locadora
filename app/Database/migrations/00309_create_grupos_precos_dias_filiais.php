<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela grupos_precos_dias_filiais
 *
 * Escala progressiva de precos por faixa de dias, agora por filial.
 * Substitui grupos_precos_dias (que fica como fallback ate Fase 4).
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->tableExists('grupos_precos_dias_filiais')) {
            return;
        }

        $this->execute("
            CREATE TABLE grupos_precos_dias_filiais (
                id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave               VARCHAR(45) NOT NULL,
                id_grupo            INT UNSIGNED NOT NULL,
                id_matriz_filial    INT UNSIGNED NOT NULL,
                tipo_plano          ENUM('diaria','km_controlado','km_livre') NOT NULL,
                dia_inicio          INT UNSIGNED NOT NULL,
                dia_fim             INT UNSIGNED NULL,
                valor               DECIMAL(10,2) NOT NULL,
                created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at          DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_gpdf_chave (chave),
                INDEX idx_gpdf_grupo (id_grupo),
                INDEX idx_gpdf_filial (id_matriz_filial),
                INDEX idx_gpdf_grupo_filial_tipo (id_grupo, id_matriz_filial, tipo_plano),

                CONSTRAINT fk_gpdf_grupo FOREIGN KEY (id_grupo)
                    REFERENCES grupos(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_gpdf_filial FOREIGN KEY (id_matriz_filial)
                    REFERENCES matrizes_filiais(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Escala progressiva de precos por grupo, filial e tipo de plano'
        ");
    }

    public function down(): void
    {
        $this->drop('grupos_precos_dias_filiais');
    }
};
