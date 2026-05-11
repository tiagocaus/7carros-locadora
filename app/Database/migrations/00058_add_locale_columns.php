<?php

use App\Database\Migration;

/**
 * Migration: Adicionar colunas de idioma (locale) para suporte a i18n
 *
 * Adiciona:
 * - clientes.preferred_locale: Idioma preferido para comunicações (email, SMS, WhatsApp)
 * - funcionarios.ui_locale: Idioma da interface preferido pelo funcionário
 * - matrizes_filiais.default_client_locale: Idioma padrão para novos clientes da empresa
 *
 * Locales suportados: pt_BR, pt_PT, en_US, es_ES, it_IT
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Adicionar preferred_locale em clientes
        $this->addColumnIfNotExists(
            'clientes',
            'preferred_locale',
            'VARCHAR(10)',
            [
                'null' => true,
                'default' => null,
                'after' => 'email'
            ]
        );

        // 2. Adicionar ui_locale em funcionarios
        $this->addColumnIfNotExists(
            'funcionarios',
            'ui_locale',
            'VARCHAR(10)',
            [
                'null' => true,
                'default' => null,
                'after' => 'email'
            ]
        );

        // 3. Adicionar default_client_locale em matrizes_filiais
        $this->addColumnIfNotExists(
            'matrizes_filiais',
            'default_client_locale',
            'VARCHAR(10)',
            [
                'null' => true,
                'default' => null,
                'after' => 'locale'
            ]
        );

        // 4. Criar índices para otimização de queries
        $this->addIndexIfNotExists('clientes', 'preferred_locale', 'idx_clientes_preferred_locale');
        $this->addIndexIfNotExists('funcionarios', 'ui_locale', 'idx_funcionarios_ui_locale');

        echo "  - Colunas de locale adicionadas com sucesso.\n";
    }

    public function down(): void
    {
        // Remover índices
        $this->dropIndexIfExists('clientes', 'idx_clientes_preferred_locale');
        $this->dropIndexIfExists('funcionarios', 'idx_funcionarios_ui_locale');

        // Remover colunas
        $this->dropColumnIfExists('clientes', 'preferred_locale');
        $this->dropColumnIfExists('funcionarios', 'ui_locale');
        $this->dropColumnIfExists('matrizes_filiais', 'default_client_locale');

        echo "  - Colunas de locale removidas.\n";
    }
};
