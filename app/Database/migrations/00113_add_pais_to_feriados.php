<?php

use App\Database\Migration;

/**
 * Migration: Adicionar coluna pais na tabela feriados
 *
 * Permite filtrar feriados por pais, suportando multi-pais no sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Adicionar coluna pais apos tipo
        $this->execute("ALTER TABLE feriados ADD COLUMN pais VARCHAR(20) DEFAULT 'Brasil' AFTER tipo");

        // Atualizar feriados existentes (todos sao brasileiros)
        $this->execute("UPDATE feriados SET pais = 'Brasil' WHERE pais IS NULL");

        // Adicionar indice
        $this->execute("CREATE INDEX idx_fer_pais ON feriados (pais)");
    }

    public function down(): void
    {
        $this->execute("DROP INDEX idx_fer_pais ON feriados");
        $this->execute("ALTER TABLE feriados DROP COLUMN pais");
    }
};
