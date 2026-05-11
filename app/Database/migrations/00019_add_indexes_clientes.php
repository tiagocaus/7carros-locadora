<?php

/**
 * Migration 00019: Índices para tabela clientes
 *
 * Adiciona índices para acelerar buscas na tabela de clientes (69k registros).
 * Sistema multi-tenant: todas as queries filtram por `chave`.
 *
 * Índices criados:
 * - idx_clientes_chave: Filtro por tenant (multi-tenancy)
 * - idx_clientes_chave_cpf: Busca por CPF/CNPJ dentro do tenant
 * - idx_clientes_chave_situacao: Filtro por status (ativo/inativo)
 * - idx_clientes_email: Busca por email (login, recuperação senha)
 * - idx_clientes_tel_cel: Busca por telefone celular
 *
 * Impacto esperado: 10x-100x em queries filtradas por chave + outro campo
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Índice para filtro por tenant (multi-tenancy)
        $this->addIndexIfNotExists('clientes', 'chave', 'idx_clientes_chave');

        // Índice composto: busca por CPF/CNPJ dentro do tenant
        $this->addIndexIfNotExists('clientes', ['chave', 'cpf_cnpj'], 'idx_clientes_chave_cpf');

        // Índice composto: filtro por status dentro do tenant
        $this->addIndexIfNotExists('clientes', ['chave', 'situacao'], 'idx_clientes_chave_situacao');

        // Índice para busca por email (login, recuperação de senha)
        $this->addIndexIfNotExists('clientes', 'email', 'idx_clientes_email');

        // Índice para busca por telefone celular
        $this->addIndexIfNotExists('clientes', 'tel_cel', 'idx_clientes_tel_cel');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('clientes', 'idx_clientes_tel_cel');
        $this->dropIndexIfExists('clientes', 'idx_clientes_email');
        $this->dropIndexIfExists('clientes', 'idx_clientes_chave_situacao');
        $this->dropIndexIfExists('clientes', 'idx_clientes_chave_cpf');
        $this->dropIndexIfExists('clientes', 'idx_clientes_chave');
    }
};
