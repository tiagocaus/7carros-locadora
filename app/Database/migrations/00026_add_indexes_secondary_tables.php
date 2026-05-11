<?php

/**
 * Migration 00026: Índices para tabelas secundárias
 *
 * Adiciona índices em tabelas menores mas frequentemente consultadas.
 * Essas tabelas são usadas em dropdowns, validações e relatórios.
 *
 * Tabelas e índices:
 * - multas: chave, chave+cliente, chave+veiculo, chave+pago
 * - promissorias: chave, chave+cliente, chave+pago
 * - clientes_arquivos: chave, id_cliente
 * - formas: chave, chave+status
 * - contas: chave, chave+status
 * - grupos: chave
 * - acessorios: chave
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // === MULTAS (6.2k registros) ===
        $this->addIndexIfNotExists('multas', 'chave', 'idx_multas_chave');
        $this->addIndexIfNotExists('multas', ['chave', 'id_cliente'], 'idx_multas_chave_cliente');
        $this->addIndexIfNotExists('multas', ['chave', 'id_veiculo'], 'idx_multas_chave_veiculo');
        $this->addIndexIfNotExists('multas', ['chave', 'pago'], 'idx_multas_chave_pago');

        // === PROMISSORIAS (416 registros) ===
        $this->addIndexIfNotExists('promissorias', 'chave', 'idx_promissorias_chave');
        $this->addIndexIfNotExists('promissorias', ['chave', 'id_cliente'], 'idx_promissorias_chave_cliente');
        $this->addIndexIfNotExists('promissorias', ['chave', 'pago'], 'idx_promissorias_chave_pago');

        // === CLIENTES_ARQUIVOS ===
        $this->addIndexIfNotExists('clientes_arquivos', 'chave', 'idx_clientes_arquivos_chave');
        $this->addIndexIfNotExists('clientes_arquivos', 'id_cliente', 'idx_clientes_arquivos_cliente');

        // === FORMAS (2.6k registros) ===
        $this->addIndexIfNotExists('formas', 'chave', 'idx_formas_chave');
        $this->addIndexIfNotExists('formas', ['chave', 'status'], 'idx_formas_chave_status');

        // === CONTAS (786 registros) ===
        $this->addIndexIfNotExists('contas', 'chave', 'idx_contas_chave');
        $this->addIndexIfNotExists('contas', ['chave', 'status'], 'idx_contas_chave_status');

        // === GRUPOS (1.4k registros) ===
        $this->addIndexIfNotExists('grupos', 'chave', 'idx_grupos_chave');

        // === ACESSORIOS ===
        $this->addIndexIfNotExists('acessorios', 'chave', 'idx_acessorios_chave');
    }

    public function down(): void
    {
        // ACESSORIOS
        $this->dropIndexIfExists('acessorios', 'idx_acessorios_chave');

        // GRUPOS
        $this->dropIndexIfExists('grupos', 'idx_grupos_chave');

        // CONTAS
        $this->dropIndexIfExists('contas', 'idx_contas_chave_status');
        $this->dropIndexIfExists('contas', 'idx_contas_chave');

        // FORMAS
        $this->dropIndexIfExists('formas', 'idx_formas_chave_status');
        $this->dropIndexIfExists('formas', 'idx_formas_chave');

        // CLIENTES_ARQUIVOS
        $this->dropIndexIfExists('clientes_arquivos', 'idx_clientes_arquivos_cliente');
        $this->dropIndexIfExists('clientes_arquivos', 'idx_clientes_arquivos_chave');

        // PROMISSORIAS
        $this->dropIndexIfExists('promissorias', 'idx_promissorias_chave_pago');
        $this->dropIndexIfExists('promissorias', 'idx_promissorias_chave_cliente');
        $this->dropIndexIfExists('promissorias', 'idx_promissorias_chave');

        // MULTAS
        $this->dropIndexIfExists('multas', 'idx_multas_chave_pago');
        $this->dropIndexIfExists('multas', 'idx_multas_chave_veiculo');
        $this->dropIndexIfExists('multas', 'idx_multas_chave_cliente');
        $this->dropIndexIfExists('multas', 'idx_multas_chave');
    }
};
