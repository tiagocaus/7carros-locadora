<?php

/**
 * Migration 00206: Normalizar tabela clientes_cartoes
 *
 * Remove dados sensíveis de cartão e adiciona suporte a tokens de autorização
 * para débitos automáticos via gateways de pagamento.
 *
 * Removidos: numero, validade, cv (dados sensíveis)
 * Adicionados: ultimos_digitos, token, gateway, ativo
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Remover colunas sensíveis
        $this->dropColumnIfExists('clientes_cartoes', 'numero');
        $this->dropColumnIfExists('clientes_cartoes', 'validade');
        $this->dropColumnIfExists('clientes_cartoes', 'cv');

        // 2. Adicionar novas colunas
        $this->addColumnIfNotExists('clientes_cartoes', 'ultimos_digitos', 'VARCHAR(4)', [
            'null' => true,
            'after' => 'bandeira'
        ]);

        $this->addColumnIfNotExists('clientes_cartoes', 'token', 'VARCHAR(255)', [
            'null' => false,
            'after' => 'ultimos_digitos'
        ]);

        $this->addColumnIfNotExists('clientes_cartoes', 'gateway', 'VARCHAR(50)', [
            'null' => false,
            'after' => 'token'
        ]);

        $this->addColumnIfNotExists('clientes_cartoes', 'ativo', 'CHAR(1)', [
            'null' => false,
            'default' => 'S',
            'after' => 'padrao'
        ]);

        // 3. Modificar colunas existentes
        $this->modifyColumn('clientes_cartoes', 'id_cliente', 'INT(10)', [
            'unsigned' => true,
            'null' => true
        ]);

        $this->modifyColumn('clientes_cartoes', 'padrao', 'CHAR(1)', [
            'null' => false,
            'default' => 'N'
        ]);

        // 4. Adicionar índices
        $this->addIndexIfNotExists('clientes_cartoes', 'token');
        $this->addIndexIfNotExists('clientes_cartoes', 'gateway');
    }

    public function down(): void
    {
        // 1. Remover índices
        $this->dropIndexIfExists('clientes_cartoes', 'idx_clientes_cartoes_token');
        $this->dropIndexIfExists('clientes_cartoes', 'idx_clientes_cartoes_gateway');

        // 2. Remover colunas novas
        $this->dropColumnIfExists('clientes_cartoes', 'ultimos_digitos');
        $this->dropColumnIfExists('clientes_cartoes', 'token');
        $this->dropColumnIfExists('clientes_cartoes', 'gateway');
        $this->dropColumnIfExists('clientes_cartoes', 'ativo');

        // 3. Restaurar colunas antigas (sem dados - apenas estrutura)
        $this->addColumnIfNotExists('clientes_cartoes', 'numero', 'VARCHAR(25)', [
            'null' => false,
            'after' => 'bandeira'
        ]);

        $this->addColumnIfNotExists('clientes_cartoes', 'validade', 'VARCHAR(10)', [
            'null' => false,
            'after' => 'numero'
        ]);

        $this->addColumnIfNotExists('clientes_cartoes', 'cv', 'INT(4)', [
            'null' => false,
            'after' => 'validade'
        ]);
    }
};
