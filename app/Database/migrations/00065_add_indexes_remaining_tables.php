<?php

/**
 * Migration 00065: Índices para Tabelas Restantes
 *
 * Adiciona índices na coluna `chave` em 22 tabelas que ainda não possuem,
 * garantindo que todas as queries multi-tenant usem index seek ao invés de
 * full table scan.
 *
 * Tabelas organizadas por prioridade (número de registros):
 *
 * Alta (> 800):
 * - fornecedores (1.928)
 * - taxaseservicos (1.419)
 * - oficinas (1.165)
 * - estoque (929)
 * - funcionarios (828)
 *
 * Média (500-800):
 * - manutencoes_plano (778)
 * - checklist_modelos (723)
 * - notificacoes (708)
 * - documentos (637)
 * - site_banners (536)
 *
 * Baixa (< 500):
 * - codigos_indicacao, configuracoes, atualizacoes, promocoes
 * - planos_contas, formas_gateway, site, sistema_gravacoes
 * - whatsapp, agenda, clientes_cartoes, security_logs
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // === PRIORIDADE ALTA (> 800 registros) ===
        $this->addIndexIfNotExists('fornecedores', 'chave', 'idx_fornecedores_chave');
        $this->addIndexIfNotExists('taxaseservicos', 'chave', 'idx_taxaseservicos_chave');
        $this->addIndexIfNotExists('oficinas', 'chave', 'idx_oficinas_chave');
        $this->addIndexIfNotExists('estoque', 'chave', 'idx_estoque_chave');
        $this->addIndexIfNotExists('funcionarios', 'chave', 'idx_funcionarios_chave');

        // === PRIORIDADE MÉDIA (500-800 registros) ===
        $this->addIndexIfNotExists('manutencoes_plano', 'chave', 'idx_manutencoes_plano_chave');
        $this->addIndexIfNotExists('checklist_modelos', 'chave', 'idx_checklist_modelos_chave');
        $this->addIndexIfNotExists('notificacoes', 'chave', 'idx_notificacoes_chave');
        $this->addIndexIfNotExists('documentos', 'chave', 'idx_documentos_chave');
        $this->addIndexIfNotExists('site_banners', 'chave', 'idx_site_banners_chave');

        // === PRIORIDADE BAIXA (< 500 registros) ===
        $this->addIndexIfNotExists('codigos_indicacao', 'chave', 'idx_codigos_indicacao_chave');
        $this->addIndexIfNotExists('configuracoes', 'chave', 'idx_configuracoes_chave');
        $this->addIndexIfNotExists('atualizacoes', 'chave', 'idx_atualizacoes_chave');
        $this->addIndexIfNotExists('promocoes', 'chave', 'idx_promocoes_chave');
        $this->addIndexIfNotExists('planos_contas', 'chave', 'idx_planos_contas_chave');
        $this->addIndexIfNotExists('formas_gateway', 'chave', 'idx_formas_gateway_chave');
        $this->addIndexIfNotExists('site', 'chave', 'idx_site_chave');
        $this->addIndexIfNotExists('sistema_gravacoes', 'chave', 'idx_sistema_gravacoes_chave');
        $this->addIndexIfNotExists('whatsapp', 'chave', 'idx_whatsapp_chave');
        $this->addIndexIfNotExists('agenda', 'chave', 'idx_agenda_chave');
        $this->addIndexIfNotExists('clientes_cartoes', 'chave', 'idx_clientes_cartoes_chave');
        $this->addIndexIfNotExists('security_logs', 'chave', 'idx_security_logs_chave');
    }

    public function down(): void
    {
        // Remove em ordem inversa (baixa → alta prioridade)

        // Baixa
        $this->dropIndexIfExists('security_logs', 'idx_security_logs_chave');
        $this->dropIndexIfExists('clientes_cartoes', 'idx_clientes_cartoes_chave');
        $this->dropIndexIfExists('agenda', 'idx_agenda_chave');
        $this->dropIndexIfExists('whatsapp', 'idx_whatsapp_chave');
        $this->dropIndexIfExists('sistema_gravacoes', 'idx_sistema_gravacoes_chave');
        $this->dropIndexIfExists('site', 'idx_site_chave');
        $this->dropIndexIfExists('formas_gateway', 'idx_formas_gateway_chave');
        $this->dropIndexIfExists('planos_contas', 'idx_planos_contas_chave');
        $this->dropIndexIfExists('promocoes', 'idx_promocoes_chave');
        $this->dropIndexIfExists('atualizacoes', 'idx_atualizacoes_chave');
        $this->dropIndexIfExists('configuracoes', 'idx_configuracoes_chave');
        $this->dropIndexIfExists('codigos_indicacao', 'idx_codigos_indicacao_chave');

        // Média
        $this->dropIndexIfExists('site_banners', 'idx_site_banners_chave');
        $this->dropIndexIfExists('documentos', 'idx_documentos_chave');
        $this->dropIndexIfExists('notificacoes', 'idx_notificacoes_chave');
        $this->dropIndexIfExists('checklist_modelos', 'idx_checklist_modelos_chave');
        $this->dropIndexIfExists('manutencoes_plano', 'idx_manutencoes_plano_chave');

        // Alta
        $this->dropIndexIfExists('funcionarios', 'idx_funcionarios_chave');
        $this->dropIndexIfExists('estoque', 'idx_estoque_chave');
        $this->dropIndexIfExists('oficinas', 'idx_oficinas_chave');
        $this->dropIndexIfExists('taxaseservicos', 'idx_taxaseservicos_chave');
        $this->dropIndexIfExists('fornecedores', 'idx_fornecedores_chave');
    }
};
