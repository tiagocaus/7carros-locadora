<?php

/**
 * Migration 00025: Índices para tabela manutencoes
 *
 * Adiciona índices para acelerar buscas na tabela de manutenções (26k registros).
 * Consultas frequentes: "veículos em manutenção" e "histórico do veículo X".
 *
 * Índices criados:
 * - idx_manutencoes_chave: Filtro por tenant
 * - idx_manutencoes_chave_veiculo: Histórico de manutenção do veículo
 * - idx_manutencoes_chave_status: Filtro por status (Criada/Aberta/Fechada)
 * - idx_manutencoes_id_oficina: JOIN com oficinas
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Índice para filtro por tenant
        $this->addIndexIfNotExists('manutencoes', 'chave', 'idx_manutencoes_chave');

        // Índice composto: histórico de manutenção do veículo
        $this->addIndexIfNotExists('manutencoes', ['chave', 'id_veiculo'], 'idx_manutencoes_chave_veiculo');

        // Índice composto: filtro por status dentro do tenant
        $this->addIndexIfNotExists('manutencoes', ['chave', 'status'], 'idx_manutencoes_chave_status');

        // Índice para JOIN com oficinas
        $this->addIndexIfNotExists('manutencoes', 'id_oficina', 'idx_manutencoes_id_oficina');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('manutencoes', 'idx_manutencoes_id_oficina');
        $this->dropIndexIfExists('manutencoes', 'idx_manutencoes_chave_status');
        $this->dropIndexIfExists('manutencoes', 'idx_manutencoes_chave_veiculo');
        $this->dropIndexIfExists('manutencoes', 'idx_manutencoes_chave');
    }
};
