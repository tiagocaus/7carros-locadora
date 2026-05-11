<?php

/**
 * Migration 00023: Índices para tabela veiculos
 *
 * Adiciona índices para acelerar buscas na tabela de veículos (7k registros).
 * Veículos são consultados em toda locação para verificar disponibilidade.
 *
 * Índices criados:
 * - idx_veiculos_chave: Filtro por tenant
 * - idx_veiculos_chave_disponibilidade: Filtro por status (Disponível/Locado/Oficina)
 * - idx_veiculos_chave_grupo: Filtro por grupo de veículos
 * - idx_veiculos_placa: Busca por placa (único por tenant)
 * - idx_veiculos_matriz_filial: Filtro por filial
 *
 * Nota: Colunas grupo, matriz_filial serão renomeadas em migration futura
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Índice para filtro por tenant
        $this->addIndexIfNotExists('veiculos', 'chave', 'idx_veiculos_chave');

        // Índice composto: filtro por disponibilidade dentro do tenant
        $this->addIndexIfNotExists('veiculos', ['chave', 'disponibilidade'], 'idx_veiculos_chave_disponibilidade');

        // Índice composto: filtro por grupo dentro do tenant
        $this->addIndexIfNotExists('veiculos', ['chave', 'grupo'], 'idx_veiculos_chave_grupo');

        // Índice para busca por placa
        $this->addIndexIfNotExists('veiculos', 'placa', 'idx_veiculos_placa');

        // Índice para filtro por filial
        $this->addIndexIfNotExists('veiculos', 'matriz_filial', 'idx_veiculos_matriz_filial');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('veiculos', 'idx_veiculos_matriz_filial');
        $this->dropIndexIfExists('veiculos', 'idx_veiculos_placa');
        $this->dropIndexIfExists('veiculos', 'idx_veiculos_chave_grupo');
        $this->dropIndexIfExists('veiculos', 'idx_veiculos_chave_disponibilidade');
        $this->dropIndexIfExists('veiculos', 'idx_veiculos_chave');
    }
};
