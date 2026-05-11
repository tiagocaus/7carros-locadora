<?php

/**
 * Migration 00022: Índices para tabela contratos
 *
 * Adiciona índices para acelerar buscas na tabela de contratos (12k registros).
 * Contratos são consultados frequentemente para verificar vínculos e gerar renovações.
 *
 * Índices criados:
 * - idx_contratos_chave: Filtro por tenant
 * - idx_contratos_chave_cliente: JOIN com clientes
 * - idx_contratos_chave_veiculo: JOIN com veículos
 * - idx_contratos_chave_status: Filtro por status (Aberto/Fechado)
 * - idx_contratos_chave_dataini: Filtro por período
 *
 * Nota: Colunas clienteID, veiculo, dataIni serão renomeadas em migration futura
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Índice para filtro por tenant
        $this->addIndexIfNotExists('contratos', 'chave', 'idx_contratos_chave');

        // Índice composto: JOIN com clientes dentro do tenant
        $this->addIndexIfNotExists('contratos', ['chave', 'clienteID'], 'idx_contratos_chave_cliente');

        // Índice composto: JOIN com veículos dentro do tenant
        $this->addIndexIfNotExists('contratos', ['chave', 'veiculo'], 'idx_contratos_chave_veiculo');

        // Índice composto: filtro por status dentro do tenant
        $this->addIndexIfNotExists('contratos', ['chave', 'status'], 'idx_contratos_chave_status');

        // Índice composto: filtro por data de início dentro do tenant
        $this->addIndexIfNotExists('contratos', ['chave', 'dataIni'], 'idx_contratos_chave_dataini');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('contratos', 'idx_contratos_chave_dataini');
        $this->dropIndexIfExists('contratos', 'idx_contratos_chave_status');
        $this->dropIndexIfExists('contratos', 'idx_contratos_chave_veiculo');
        $this->dropIndexIfExists('contratos', 'idx_contratos_chave_cliente');
        $this->dropIndexIfExists('contratos', 'idx_contratos_chave');
    }
};
