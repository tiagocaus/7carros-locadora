<?php

/**
 * Migracao: Criar tabela gateways_filiais
 *
 * N:N entre gateways de pagamento e filiais.
 *
 * - CREATE TABLE IF NOT EXISTS nao corrige tabela legada; por isso DROP + CREATE limpo.
 * - FKs inline no CREATE podem falhar (1072) em alguns ambientes; FKs sao adicionadas via Migration::addForeignKeyIfNotExists.
 */

use App\Database\Migration;

return new class extends Migration {
    public function up(): void
    {
        $this->execute('SET FOREIGN_KEY_CHECKS=0');
        $this->execute('DROP TABLE IF EXISTS gateways_filiais');
        $this->execute('SET FOREIGN_KEY_CHECKS=1');

        $this->execute("
            CREATE TABLE gateways_filiais (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                id_gateway INT UNSIGNED NOT NULL,
                id_matriz_filial INT UNSIGNED NOT NULL,
                chave VARCHAR(45) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_gateway_filial (id_gateway, id_matriz_filial),
                KEY idx_gf_chave (chave)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        if ($this->tableExists('gateways_pagamento') && $this->tableExists('matrizes_filiais')) {
            $this->addForeignKeyIfNotExists(
                'gateways_filiais',
                'id_gateway',
                'gateways_pagamento',
                'id',
                'CASCADE',
                'RESTRICT',
                'fk_gf_gateway'
            );
            $this->addForeignKeyIfNotExists(
                'gateways_filiais',
                'id_matriz_filial',
                'matrizes_filiais',
                'id',
                'CASCADE',
                'RESTRICT',
                'fk_gf_matriz_filial'
            );
        }

        if (!$this->columnExists('gateways_pagamento', 'id_matriz_filial')) {
            return;
        }

        $this->execute("
            INSERT IGNORE INTO gateways_filiais (id_gateway, id_matriz_filial, chave)
            SELECT id, id_matriz_filial, chave
            FROM gateways_pagamento
            WHERE id_matriz_filial IS NOT NULL
        ");

        $this->dropForeignKeyIfExists('gateways_pagamento', 'fk_gp_matriz_filial');

        // UNIQUE criado na 00200 inclui id_matriz_filial; remover antes do DROP COLUMN (evita 1072 em alguns MariaDB/MySQL)
        $this->dropIndexIfExists('gateways_pagamento', 'idx_gp_chave_gateway_filial');

        $this->execute('ALTER TABLE gateways_pagamento DROP COLUMN id_matriz_filial');
    }

    public function down(): void
    {
        if (!$this->columnExists('gateways_pagamento', 'id_matriz_filial')) {
            $this->execute("
                ALTER TABLE gateways_pagamento
                ADD COLUMN id_matriz_filial INT UNSIGNED NULL AFTER chave
            ");
        }

        $this->execute("
            UPDATE gateways_pagamento gp
            INNER JOIN (
                SELECT id_gateway, MIN(id_matriz_filial) AS id_matriz_filial
                FROM gateways_filiais
                GROUP BY id_gateway
            ) gf ON gp.id = gf.id_gateway
            SET gp.id_matriz_filial = gf.id_matriz_filial
        ");

        $this->execute('SET FOREIGN_KEY_CHECKS=0');
        $this->execute('DROP TABLE IF EXISTS gateways_filiais');
        $this->execute('SET FOREIGN_KEY_CHECKS=1');

        if ($this->tableExists('matrizes_filiais')) {
            $this->addForeignKeyIfNotExists(
                'gateways_pagamento',
                'id_matriz_filial',
                'matrizes_filiais',
                'id',
                'SET NULL',
                'CASCADE',
                'fk_gp_matriz_filial'
            );
        }
    }
};
