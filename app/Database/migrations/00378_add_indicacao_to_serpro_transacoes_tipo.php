<?php

/**
 * Migration 00378: Adicionar tipo indicacao em serpro_transacoes
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->execute("
            ALTER TABLE serpro_transacoes
            MODIFY tipo ENUM('recarga_pix', 'recarga_cartao', 'recarga_manual', 'consulta', 'evento', 'indicacao')
            NOT NULL COMMENT 'Tipo da transacao'
        ");
    }

    public function down(): void
    {
        $this->execute("
            ALTER TABLE serpro_transacoes
            MODIFY tipo ENUM('recarga_pix', 'recarga_cartao', 'recarga_manual', 'consulta', 'evento')
            NOT NULL COMMENT 'Tipo da transacao'
        ");
    }
};
