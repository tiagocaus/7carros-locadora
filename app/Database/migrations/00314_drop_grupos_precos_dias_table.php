<?php

use App\Database\Migration;

/**
 * Migration: Drop tabela `grupos_precos_dias`
 *
 * Fase 4 do refactor multi-moeda. Substituida por `grupos_precos_dias_filiais`
 * (1 conjunto de faixas por filial, ja refletindo multi-moeda).
 *
 * Backfill em 00310 ja replicou os dados pra tabela nova.
 * Model antigo GrupoPrecoDia removido; endpoints antigos (precos/salvarPrecos)
 * removidos do GruposController.
 *
 * O termino de tenants descobre as tabelas existentes no schema, portanto
 * passa a considerar apenas `grupos_precos_dias_filiais` apos este drop.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->drop('grupos_precos_dias');
    }

    public function down(): void
    {
        if ($this->tableExists('grupos_precos_dias')) {
            return;
        }

        $this->execute("
            CREATE TABLE grupos_precos_dias (
                id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave       VARCHAR(45) NOT NULL,
                id_grupo    INT UNSIGNED NOT NULL,
                tipo_plano  ENUM('diaria','km_controlado','km_livre') NOT NULL,
                dia_inicio  INT UNSIGNED NOT NULL,
                dia_fim     INT UNSIGNED NULL,
                valor       DECIMAL(10,2) NOT NULL,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_chave (chave),
                INDEX idx_grupo (id_grupo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
};
