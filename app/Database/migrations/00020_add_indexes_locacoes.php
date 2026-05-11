<?php

/**
 * Migration 00020: Índices para tabela locacoes
 *
 * Adiciona índices para acelerar buscas na tabela de locações (87k registros).
 * Esta é a tabela mais consultada do sistema (dashboard, relatórios, listagens).
 *
 * Índices criados:
 * - idx_locacoes_chave: Filtro por tenant
 * - idx_locacoes_chave_situacao: Filtro por status (Reserva/Saída/Chegada)
 * - idx_locacoes_chave_cliente: JOIN com clientes dentro do tenant
 * - idx_locacoes_chave_veiculo: JOIN com veículos dentro do tenant
 * - idx_locacoes_chave_datasaida: Filtro por período
 * - idx_locacoes_clienteID: FK para clientes (acelera JOINs)
 * - idx_locacoes_veiculo: FK para veículos (acelera JOINs)
 *
 * Nota: Colunas clienteID e veiculo serão renomeadas em migration futura
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Índice para filtro por tenant
        $this->addIndexIfNotExists('locacoes', 'chave', 'idx_locacoes_chave');

        // Índice composto: filtro por status dentro do tenant
        $this->addIndexIfNotExists('locacoes', ['chave', 'situacao'], 'idx_locacoes_chave_situacao');

        // Índice composto: JOIN com clientes dentro do tenant
        $this->addIndexIfNotExists('locacoes', ['chave', 'clienteID'], 'idx_locacoes_chave_cliente');

        // Índice composto: JOIN com veículos dentro do tenant
        $this->addIndexIfNotExists('locacoes', ['chave', 'veiculo'], 'idx_locacoes_chave_veiculo');

        // Índice composto: filtro por data de saída dentro do tenant
        $this->addIndexIfNotExists('locacoes', ['chave', 'dataSaida'], 'idx_locacoes_chave_datasaida');

        // Índice para FK clientes (acelera JOINs diretos)
        $this->addIndexIfNotExists('locacoes', 'clienteID', 'idx_locacoes_clienteID');

        // Índice para FK veículos (acelera JOINs diretos)
        $this->addIndexIfNotExists('locacoes', 'veiculo', 'idx_locacoes_veiculo');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('locacoes', 'idx_locacoes_veiculo');
        $this->dropIndexIfExists('locacoes', 'idx_locacoes_clienteID');
        $this->dropIndexIfExists('locacoes', 'idx_locacoes_chave_datasaida');
        $this->dropIndexIfExists('locacoes', 'idx_locacoes_chave_veiculo');
        $this->dropIndexIfExists('locacoes', 'idx_locacoes_chave_cliente');
        $this->dropIndexIfExists('locacoes', 'idx_locacoes_chave_situacao');
        $this->dropIndexIfExists('locacoes', 'idx_locacoes_chave');
    }
};
