<?php

use App\Database\Migration;

/**
 * Migration: Atualizar módulos de Feature Requests
 *
 * - Adiciona coluna translation_key para integração com i18n
 * - Remove módulos genéricos e insere os 29 módulos específicos do sistema
 * - Limpa dados de teste da tabela feature_requests
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Desabilitar verificação de FK temporariamente
        $this->execute("SET FOREIGN_KEY_CHECKS = 0");

        // 2. Limpar tabelas (dados de teste)
        $this->execute("TRUNCATE TABLE feature_request_followers");
        $this->execute("TRUNCATE TABLE feature_request_votes");
        $this->execute("TRUNCATE TABLE feature_requests");
        $this->execute("TRUNCATE TABLE feature_request_modules");

        // 3. Adicionar coluna translation_key se não existir
        // Usa try/catch para verificar se coluna já existe
        try {
            $this->execute("ALTER TABLE feature_request_modules ADD COLUMN translation_key VARCHAR(100) NOT NULL AFTER nome");
        } catch (\Exception $e) {
            // Coluna já existe, ignora o erro
        }

        // 4. Inserir os 30 módulos (29 + Outros)
        $this->execute("
            INSERT INTO feature_request_modules (id, nome, translation_key, icone, ordem, ativo) VALUES
            (1, 'Sistema - Inicial', 'sistema_inicial', 'fas fa-home', 1, 1),
            (2, 'Sistema - Locações', 'sistema_locacoes', 'fas fa-car-side', 2, 1),
            (3, 'Sistema - Contratos', 'sistema_contratos', 'fas fa-file-contract', 3, 1),
            (4, 'Sistema - Matriz e filiais', 'sistema_matriz_filiais', 'fas fa-building', 4, 1),
            (5, 'Sistema - Funcionários', 'sistema_funcionarios', 'fas fa-user-tie', 5, 1),
            (6, 'Sistema - Taxas e serviços', 'sistema_taxas_servicos', 'fas fa-tags', 6, 1),
            (7, 'Sistema - Oficinas', 'sistema_oficinas', 'fas fa-wrench', 7, 1),
            (8, 'Sistema - Promoções', 'sistema_promocoes', 'fas fa-percentage', 8, 1),
            (9, 'Sistema - Multas', 'sistema_multas', 'fas fa-gavel', 9, 1),
            (10, 'Sistema - Contas bancárias/caixa', 'sistema_contas_caixa', 'fas fa-cash-register', 10, 1),
            (11, 'Sistema - Formas de pagamento', 'sistema_formas_pagamento', 'fas fa-credit-card', 11, 1),
            (12, 'Sistema - Fornecedores', 'sistema_fornecedores', 'fas fa-truck', 12, 1),
            (13, 'Sistema - Veículos', 'sistema_veiculos', 'fas fa-car', 13, 1),
            (14, 'Sistema - Grupos', 'sistema_grupos', 'fas fa-layer-group', 14, 1),
            (15, 'Sistema - Acessórios e itens', 'sistema_acessorios_itens', 'fas fa-puzzle-piece', 15, 1),
            (16, 'Sistema - Manutenções', 'sistema_manutencoes', 'fas fa-tools', 16, 1),
            (17, 'Sistema - Plano de manutenções', 'sistema_plano_manutencoes', 'fas fa-clipboard-list', 17, 1),
            (18, 'Sistema - Checklist', 'sistema_checklist', 'fas fa-tasks', 18, 1),
            (19, 'Sistema - Checklist modelos', 'sistema_checklist_modelos', 'fas fa-list-check', 19, 1),
            (20, 'Sistema - Relatórios', 'sistema_relatorios', 'fas fa-chart-bar', 20, 1),
            (21, 'Sistema - Financeiro', 'sistema_financeiro', 'fas fa-dollar-sign', 21, 1),
            (22, 'Website - Site', 'website_site', 'fas fa-globe', 22, 1),
            (23, 'Aplicativo - Checklist', 'aplicativo_checklist', 'fas fa-mobile-alt', 23, 1),
            (24, 'Sistema - Site', 'sistema_site', 'fas fa-desktop', 24, 1),
            (25, 'Sistema - Clientes', 'sistema_clientes', 'fas fa-users', 25, 1),
            (26, 'Sistema - WhatsApp', 'sistema_whatsapp', 'fab fa-whatsapp', 26, 1),
            (27, 'Sistema - Documentos', 'sistema_documentos', 'fas fa-folder-open', 27, 1),
            (28, 'Sistema - Estoque', 'sistema_estoque', 'fas fa-boxes', 28, 1),
            (29, 'Sistema - Agenda', 'sistema_agenda', 'fas fa-calendar-alt', 29, 1),
            (30, 'Outros', 'outros', 'fas fa-ellipsis-h', 99, 1)
        ");

        // 5. Reabilitar verificação de FK
        $this->execute("SET FOREIGN_KEY_CHECKS = 1");
    }

    public function down(): void
    {
        // Desabilitar verificação de FK
        $this->execute("SET FOREIGN_KEY_CHECKS = 0");

        // Limpar tabelas
        $this->execute("TRUNCATE TABLE feature_request_followers");
        $this->execute("TRUNCATE TABLE feature_request_votes");
        $this->execute("TRUNCATE TABLE feature_requests");
        $this->execute("TRUNCATE TABLE feature_request_modules");

        // Remover coluna translation_key
        $this->execute("ALTER TABLE feature_request_modules DROP COLUMN translation_key");

        // Reinserir módulos originais
        $this->execute("
            INSERT INTO feature_request_modules (nome, icone, ordem) VALUES
            ('Contratos/Locações', 'fas fa-file-contract', 1),
            ('Veículos', 'fas fa-car', 2),
            ('Clientes', 'fas fa-users', 3),
            ('Financeiro', 'fas fa-dollar-sign', 4),
            ('Relatórios', 'fas fa-chart-bar', 5),
            ('Manutenções', 'fas fa-tools', 6),
            ('Website', 'fas fa-globe', 7),
            ('Configurações', 'fas fa-cog', 8),
            ('Outro', 'fas fa-ellipsis-h', 99)
        ");

        // Reabilitar verificação de FK
        $this->execute("SET FOREIGN_KEY_CHECKS = 1");
    }
};
