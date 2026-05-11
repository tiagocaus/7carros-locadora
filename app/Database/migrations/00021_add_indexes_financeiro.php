<?php

/**
 * Migration 00021: Índices para tabela financeiro
 *
 * Adiciona índices para acelerar buscas na tabela financeira (419k registros).
 * Esta é a maior tabela de dados do sistema.
 *
 * Query mais comum: "faturas em aberto do cliente X"
 * Sem índice: full table scan em 419k registros
 * Com índice: index seek instantâneo
 *
 * Índices criados:
 * - idx_financeiro_chave: Filtro por tenant
 * - idx_financeiro_chave_cliente: Busca faturas do cliente
 * - idx_financeiro_chave_pago: Filtro por status de pagamento
 * - idx_financeiro_chave_vencimento: Filtro por data de vencimento
 * - idx_financeiro_chave_cliente_pago: Busca "faturas em aberto do cliente X"
 * - idx_financeiro_codigo: Busca por código do contrato/locação
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Índice para filtro por tenant
        $this->addIndexIfNotExists('financeiro', 'chave', 'idx_financeiro_chave');

        // Índice composto: busca faturas do cliente
        $this->addIndexIfNotExists('financeiro', ['chave', 'id_cliente'], 'idx_financeiro_chave_cliente');

        // Índice composto: filtro por status de pagamento
        $this->addIndexIfNotExists('financeiro', ['chave', 'pago'], 'idx_financeiro_chave_pago');

        // Índice composto: filtro por data de vencimento
        $this->addIndexIfNotExists('financeiro', ['chave', 'data_venci'], 'idx_financeiro_chave_vencimento');

        // Índice composto triplo: busca "faturas em aberto do cliente X"
        // Otimiza a query mais comum do sistema financeiro
        $this->addIndexIfNotExists('financeiro', ['chave', 'id_cliente', 'pago'], 'idx_financeiro_chave_cliente_pago');

        // Índice para busca por código do contrato/locação
        $this->addIndexIfNotExists('financeiro', 'codigo', 'idx_financeiro_codigo');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('financeiro', 'idx_financeiro_codigo');
        $this->dropIndexIfExists('financeiro', 'idx_financeiro_chave_cliente_pago');
        $this->dropIndexIfExists('financeiro', 'idx_financeiro_chave_vencimento');
        $this->dropIndexIfExists('financeiro', 'idx_financeiro_chave_pago');
        $this->dropIndexIfExists('financeiro', 'idx_financeiro_chave_cliente');
        $this->dropIndexIfExists('financeiro', 'idx_financeiro_chave');
    }
};
