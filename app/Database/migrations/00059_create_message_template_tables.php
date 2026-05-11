<?php

use App\Database\Migration;

/**
 * Migration: Criar tabelas do sistema de templates de mensagem
 *
 * Cria:
 * - message_template_types: Tipos de template (definidos pelo sistema)
 * - message_templates: Templates (padrão com chave='0' e customizados por empresa)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabela de tipos de template (definidos pelo sistema)
        $this->execute("
            CREATE TABLE IF NOT EXISTS message_template_types (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(50) NOT NULL UNIQUE COMMENT 'Identificador único (ex: welcome, rental_confirmation)',
                name_key VARCHAR(100) NOT NULL COMMENT 'Chave de tradução para nome',
                description_key VARCHAR(100) DEFAULT NULL COMMENT 'Chave de tradução para descrição',
                category ENUM('onboarding', 'rental', 'reminder', 'billing') NOT NULL COMMENT 'Categoria do template',
                channels JSON NOT NULL COMMENT 'Canais suportados: [\"email\", \"whatsapp\", \"sms\"]',
                available_variables JSON NOT NULL COMMENT 'Entidades disponíveis: [\"cliente\", \"empresa\", \"locacao\"]',
                is_active TINYINT(1) DEFAULT 1 COMMENT 'Se o tipo está ativo',
                sort_order INT DEFAULT 0 COMMENT 'Ordem de exibição',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_category (category),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Tipos de templates de mensagem (definidos pelo sistema)'
        ");

        echo "  - Tabela message_template_types criada.\n";

        // 2. Tabela de templates (padrão e customizados)
        // chave = '0' -> Template padrão do sistema
        // chave = {tenant} -> Template customizado por empresa
        $this->execute("
            CREATE TABLE IF NOT EXISTS message_templates (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave VARCHAR(45) NOT NULL COMMENT 'Chave do tenant (0 = padrão do sistema)',
                template_type_id INT UNSIGNED NOT NULL COMMENT 'FK para message_template_types',
                locale VARCHAR(10) NOT NULL DEFAULT 'pt_BR' COMMENT 'Código do idioma',
                channel ENUM('email', 'sms', 'whatsapp') NOT NULL COMMENT 'Canal de envio',
                subject VARCHAR(255) DEFAULT NULL COMMENT 'Assunto personalizado',
                content LONGTEXT NOT NULL COMMENT 'Conteúdo personalizado',
                content_plain TEXT DEFAULT NULL COMMENT 'Versão texto puro',
                is_active TINYINT(1) DEFAULT 1 COMMENT 'Se o template está ativo',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                created_by INT UNSIGNED DEFAULT NULL COMMENT 'ID do funcionário que criou',
                updated_by INT UNSIGNED DEFAULT NULL COMMENT 'ID do funcionário que atualizou',
                UNIQUE KEY uk_tenant_type_locale_channel (chave, template_type_id, locale, channel),
                INDEX idx_chave (chave),
                INDEX idx_type (template_type_id),
                INDEX idx_locale (locale),
                INDEX idx_active (is_active),
                CONSTRAINT fk_templates_type FOREIGN KEY (template_type_id)
                    REFERENCES message_template_types(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Templates de mensagem (padrão e customizados por empresa)'
        ");

        echo "  - Tabela message_templates criada.\n";

        // 3. Popular tipos de template padrão
        $this->seedTemplateTypes();

        echo "  - Tipos de template populados.\n";
    }

    public function down(): void
    {
        // Remover tabelas na ordem correta (por causa das FKs)
        $this->execute("DROP TABLE IF EXISTS message_templates");
        $this->execute("DROP TABLE IF EXISTS message_template_types");

        echo "  - Tabelas de templates removidas.\n";
    }

    /**
     * Popular tipos de template padrão do sistema
     */
    private function seedTemplateTypes(): void
    {
        $types = [
            [
                'slug' => 'welcome',
                'name_key' => 'templates.types.welcome',
                'description_key' => 'templates.types.welcome_desc',
                'category' => 'onboarding',
                'channels' => '["email", "whatsapp"]',
                'available_variables' => '["cliente", "empresa", "outros"]',
                'sort_order' => 1
            ],
            [
                'slug' => 'rental_confirmation',
                'name_key' => 'templates.types.rental_confirmation',
                'description_key' => 'templates.types.rental_confirmation_desc',
                'category' => 'rental',
                'channels' => '["email", "whatsapp", "sms"]',
                'available_variables' => '["cliente", "empresa", "locacao", "veiculo", "outros"]',
                'sort_order' => 2
            ],
            [
                'slug' => 'contract_confirmation',
                'name_key' => 'templates.types.contract_confirmation',
                'description_key' => 'templates.types.contract_confirmation_desc',
                'category' => 'rental',
                'channels' => '["email", "whatsapp"]',
                'available_variables' => '["cliente", "empresa", "contrato", "veiculo", "outros"]',
                'sort_order' => 3
            ],
            [
                'slug' => 'return_reminder',
                'name_key' => 'templates.types.return_reminder',
                'description_key' => 'templates.types.return_reminder_desc',
                'category' => 'reminder',
                'channels' => '["email", "whatsapp", "sms"]',
                'available_variables' => '["cliente", "empresa", "locacao", "veiculo", "outros"]',
                'sort_order' => 4
            ],
            [
                'slug' => 'payment_reminder',
                'name_key' => 'templates.types.payment_reminder',
                'description_key' => 'templates.types.payment_reminder_desc',
                'category' => 'billing',
                'channels' => '["email", "whatsapp", "sms"]',
                'available_variables' => '["cliente", "empresa", "fatura", "outros"]',
                'sort_order' => 5
            ],
            [
                'slug' => 'invoice_generated',
                'name_key' => 'templates.types.invoice_generated',
                'description_key' => 'templates.types.invoice_generated_desc',
                'category' => 'billing',
                'channels' => '["email", "whatsapp"]',
                'available_variables' => '["cliente", "empresa", "fatura", "locacao", "outros"]',
                'sort_order' => 6
            ],
            [
                'slug' => 'overdue_notice',
                'name_key' => 'templates.types.overdue_notice',
                'description_key' => 'templates.types.overdue_notice_desc',
                'category' => 'billing',
                'channels' => '["email", "whatsapp", "sms"]',
                'available_variables' => '["cliente", "empresa", "fatura", "outros"]',
                'sort_order' => 7
            ],
            [
                'slug' => 'cnh_expiring',
                'name_key' => 'templates.types.cnh_expiring',
                'description_key' => 'templates.types.cnh_expiring_desc',
                'category' => 'reminder',
                'channels' => '["email", "whatsapp"]',
                'available_variables' => '["cliente", "empresa", "outros"]',
                'sort_order' => 8
            ],
        ];

        foreach ($types as $type) {
            $sql = sprintf(
                "INSERT INTO message_template_types
                (slug, name_key, description_key, category, channels, available_variables, sort_order)
                VALUES ('%s', '%s', '%s', '%s', '%s', '%s', %d)",
                addslashes($type['slug']),
                addslashes($type['name_key']),
                addslashes($type['description_key']),
                addslashes($type['category']),
                addslashes($type['channels']),
                addslashes($type['available_variables']),
                $type['sort_order']
            );
            $this->execute($sql);
        }
    }
};
