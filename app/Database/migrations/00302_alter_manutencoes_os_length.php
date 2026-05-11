<?php

/**
 * Migration 00302: Aumentar tamanho da coluna manutencoes.os
 *
 * O CRON CheckPreventiveMaintenanceJob gera código no formato:
 *   "MA" + rand(10000,99999) + id_matriz_filial
 * Para filiais com id de 4 dígitos (>=1000), o código tem 11 chars
 * e estoura o VARCHAR(10) original, causando erro
 * "Data too long for column 'os'". Aumentando para VARCHAR(20)
 * dá folga para qualquer combinação futura.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->execute("
            ALTER TABLE manutencoes
            MODIFY os VARCHAR(20) NOT NULL
        ");
    }

    public function down(): void
    {
        $this->execute("
            ALTER TABLE manutencoes
            MODIFY os VARCHAR(10) NOT NULL
        ");
    }
};
