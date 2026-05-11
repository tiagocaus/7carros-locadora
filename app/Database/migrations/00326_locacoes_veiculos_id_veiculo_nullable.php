<?php

/**
 * Migration: torna locacoes_veiculos.id_veiculo nullable.
 *
 * Reservas (status R) podem ser criadas sem veículo específico, mas SEMPRE
 * com um grupo. Antes desta migration a coluna id_veiculo era NOT NULL e
 * impedia a criação da linha em locacoes_veiculos para reservas sem veículo
 * — fazendo com que id_grupo se perdesse e a reserva ficasse "órfã" na agenda.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->execute("
            ALTER TABLE locacoes_veiculos
                MODIFY COLUMN id_veiculo INT(10) UNSIGNED NULL COMMENT 'NULL quando a reserva ainda nao tem veiculo atribuido (so grupo)'
        ");
    }

    public function down(): void
    {
        $this->execute("
            ALTER TABLE locacoes_veiculos
                MODIFY COLUMN id_veiculo INT(10) UNSIGNED NOT NULL
        ");
    }
};
