<?php

use App\Database\Migration;

/**
 * Migration: Criar tabelas para sistema de Feature Requests
 *
 * Cria as seguintes tabelas:
 * - feature_request_modules: Categorias/módulos pré-definidos
 * - feature_requests: Pedidos de novos recursos (principal)
 * - feature_request_votes: Votos em pedidos (1 por email por pedido)
 * - feature_request_followers: Seguidores para notificação
 *
 * Este sistema é CROSS-TENANT: todos podem ver e votar em todos os pedidos.
 * Apenas admin 7Carros pode alterar status.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabela de Módulos (categorias)
        $this->create('feature_request_modules', function ($table) {
            $table->id();
            $table->string('nome', 100);
            $table->string('translation_key', 100);
            $table->string('icone', 50)->nullable();
            $table->integer('ordem')->default(0);
            $table->boolean('ativo')->default(1);
            $table->datetime('created_at')->default('CURRENT_TIMESTAMP');
        });

        // Inserir os 30 módulos do sistema (29 + Outros)
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

        // 2. Tabela Principal: feature_requests
        $this->create('feature_requests', function ($table) {
            $table->id();
            $table->string('chave', 45);

            // Dados do pedido
            $table->string('titulo', 255);
            $table->addColumn('`descricao` MEDIUMTEXT NOT NULL');
            $table->integer('modulo_id')->unsigned()->nullable();

            // Solicitante
            $table->integer('usuario_id')->unsigned()->nullable();
            $table->string('nome_solicitante', 255)->nullable();
            $table->string('email_solicitante', 255);
            $table->string('telefone_solicitante', 50)->nullable();

            // Status e prioridade
            $table->addColumn("`status` ENUM('pendente', 'em_analise', 'em_desenvolvimento', 'concluido', 'recusado', 'aguardando_info') NOT NULL DEFAULT 'pendente'");
            $table->addColumn("`prioridade` ENUM('baixa', 'normal', 'alta', 'critica') NOT NULL DEFAULT 'normal'");

            // Contadores desnormalizados
            $table->integer('total_votos')->unsigned()->default(0);
            $table->integer('total_seguidores')->unsigned()->default(0);

            // Resposta do admin
            $table->text('resposta_admin')->nullable();
            $table->integer('respondido_por')->unsigned()->nullable();
            $table->datetime('respondido_em')->nullable();

            // Timestamps
            $table->datetime('created_at')->default('CURRENT_TIMESTAMP');
            $table->addColumn('`updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP');

            // Índices
            $table->index('status', 'idx_feature_requests_status');
            $table->index('modulo_id', 'idx_feature_requests_modulo');
            $table->index('total_votos', 'idx_feature_requests_votos');
            $table->index('created_at', 'idx_feature_requests_created');
            $table->index('chave', 'idx_feature_requests_chave');

            // Foreign key para módulo
            $table->foreign('modulo_id')
                ->references('id')
                ->on('feature_request_modules')
                ->nullOnDelete();
        });

        // Adicionar índice FULLTEXT para busca inteligente (separado pois não suporta na API fluent)
        $this->execute("ALTER TABLE feature_requests ADD FULLTEXT INDEX idx_feature_requests_busca (titulo, descricao)");

        // 3. Tabela de Votos
        $this->create('feature_request_votes', function ($table) {
            $table->id();
            $table->integer('feature_request_id')->unsigned();
            $table->string('chave', 45);
            $table->integer('usuario_id')->unsigned()->nullable();
            $table->string('email_votante', 255);
            $table->datetime('created_at')->default('CURRENT_TIMESTAMP');

            // Índice único: 1 voto por email por pedido
            $table->unique(['feature_request_id', 'email_votante'], 'uk_voto_unico');

            // Índice para consultas
            $table->index('feature_request_id', 'idx_votes_feature');

            // Foreign key
            $table->foreign('feature_request_id')
                ->references('id')
                ->on('feature_requests')
                ->cascadeOnDelete();
        });

        // 4. Tabela de Seguidores
        $this->create('feature_request_followers', function ($table) {
            $table->id();
            $table->integer('feature_request_id')->unsigned();
            $table->string('chave', 45);
            $table->integer('usuario_id')->unsigned()->nullable();
            $table->string('email', 255);
            $table->string('telefone', 50)->nullable();
            $table->boolean('notificar_email')->default(1);
            $table->boolean('notificar_whatsapp')->default(1);
            $table->datetime('created_at')->default('CURRENT_TIMESTAMP');

            // Índice único: 1 seguidor por email por pedido
            $table->unique(['feature_request_id', 'email'], 'uk_seguidor_unico');

            // Índice para consultas
            $table->index('feature_request_id', 'idx_followers_feature');

            // Foreign key
            $table->foreign('feature_request_id')
                ->references('id')
                ->on('feature_requests')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Remover na ordem inversa por causa das FKs
        $this->drop('feature_request_followers');
        $this->drop('feature_request_votes');
        $this->drop('feature_requests');
        $this->drop('feature_request_modules');
    }
};
