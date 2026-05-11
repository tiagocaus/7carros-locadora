<?php

/**
 * Migration 00042: Corrigir Charset Restante para UTF8MB4
 *
 * Converte TODAS as tabelas restantes que ainda não estão com utf8mb4_unicode_ci.
 * Complementa a migration 00017 que converteu apenas tabelas específicas.
 *
 * Tabelas afetadas:
 * - latin1_swedish_ci: planos_contas, promocoes
 * - utf8_unicode_ci: agenda, atualizacoes, checklist, checklist_modelos, clientes,
 *                    clientes_arquivos, clientes_cartoes, codigos_indicacao, configuracoes,
 *                    contas, contratos, documentos, estoque, financeiro, formas_gateway,
 *                    formas_pagamento, funcionarios, logs, manutencoes, manutencoes_plano,
 *                    matriz_filial, multas, notificacoes, site, site_banners, whatsapp
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Tabelas que estão com latin1_swedish_ci
     */
    private array $latin1Tables = [
        'planos_contas',
        'promocoes',
    ];

    /**
     * Tabelas que estão com utf8_unicode_ci
     */
    private array $utf8Tables = [
        'agenda',
        'atualizacoes',
        'checklist',
        'checklist_modelos',
        'clientes',
        'clientes_arquivos',
        'clientes_cartoes',
        'codigos_indicacao',
        'configuracoes',
        'contas',
        'contratos',
        'documentos',
        'estoque',
        'financeiro',
        'formas_gateway',
        'formas_pagamento',
        'funcionarios',
        'logs',
        'manutencoes',
        'manutencoes_plano',
        'matriz_filial',
        'multas',
        'notificacoes',
        'site',
        'site_banners',
        'whatsapp',
    ];

    public function up(): void
    {
        // Converte tabelas latin1 para utf8mb4
        foreach ($this->latin1Tables as $table) {
            if ($this->tableExists($table)) {
                $this->execute(
                    "ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
                );
            }
        }

        // Converte tabelas utf8_unicode_ci para utf8mb4_unicode_ci
        foreach ($this->utf8Tables as $table) {
            if ($this->tableExists($table)) {
                $this->execute(
                    "ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
                );
            }
        }
    }

    public function down(): void
    {
        // Reverte tabelas para latin1_swedish_ci
        foreach ($this->latin1Tables as $table) {
            if ($this->tableExists($table)) {
                $this->execute(
                    "ALTER TABLE `{$table}` CONVERT TO CHARACTER SET latin1 COLLATE latin1_swedish_ci"
                );
            }
        }

        // Reverte tabelas para utf8_unicode_ci
        foreach ($this->utf8Tables as $table) {
            if ($this->tableExists($table)) {
                $this->execute(
                    "ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci"
                );
            }
        }
    }
};
