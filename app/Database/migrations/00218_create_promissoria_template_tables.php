<?php

use App\Database\Migration;

/**
 * Migration: Criar tabelas do sistema de templates de promissoria
 *
 * Cria:
 * - promissoria_template_types: Tipos de template (definidos pelo sistema)
 * - promissoria_templates: Templates (padrao com chave='0' e customizados por empresa)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabela de tipos de template (definidos pelo sistema)
        $this->execute("
            CREATE TABLE IF NOT EXISTS promissoria_template_types (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(50) NOT NULL UNIQUE COMMENT 'Identificador unico (ex: promissoria_texto_quitada)',
                name_key VARCHAR(100) NOT NULL COMMENT 'Chave de traducao para nome',
                description_key VARCHAR(100) DEFAULT NULL COMMENT 'Chave de traducao para descricao',
                category ENUM('promissoria', 'parcela') NOT NULL COMMENT 'Categoria do template',
                available_variables JSON NOT NULL COMMENT 'Entidades disponiveis: [\"cliente\", \"empresa\", \"promissoria\"]',
                is_active TINYINT(1) DEFAULT 1 COMMENT 'Se o tipo esta ativo',
                sort_order INT DEFAULT 0 COMMENT 'Ordem de exibicao',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_category (category),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Tipos de templates de promissoria (definidos pelo sistema)'
        ");

        echo "  - Tabela promissoria_template_types criada.\n";

        // 2. Tabela de templates (padrao e customizados)
        // chave = '0' -> Template padrao do sistema
        // chave = {tenant} -> Template customizado por empresa
        $this->execute("
            CREATE TABLE IF NOT EXISTS promissoria_templates (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave VARCHAR(45) NOT NULL COMMENT 'Chave do tenant (0 = padrao do sistema)',
                template_type_id INT UNSIGNED NOT NULL COMMENT 'FK para promissoria_template_types',
                locale VARCHAR(10) NOT NULL DEFAULT 'pt_BR' COMMENT 'Codigo do idioma',
                content LONGTEXT NOT NULL COMMENT 'Conteudo do template com variaveis',
                is_active TINYINT(1) DEFAULT 1 COMMENT 'Se o template esta ativo',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                created_by INT UNSIGNED DEFAULT NULL COMMENT 'ID do funcionario que criou',
                updated_by INT UNSIGNED DEFAULT NULL COMMENT 'ID do funcionario que atualizou',
                UNIQUE KEY uk_tenant_type_locale (chave, template_type_id, locale),
                INDEX idx_chave (chave),
                INDEX idx_type (template_type_id),
                INDEX idx_locale (locale),
                INDEX idx_active (is_active),
                CONSTRAINT fk_prom_templates_type FOREIGN KEY (template_type_id)
                    REFERENCES promissoria_template_types(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Templates de promissoria (padrao e customizados por empresa)'
        ");

        echo "  - Tabela promissoria_templates criada.\n";

        // 3. Popular tipos de template padrao
        $this->seedTemplateTypes();

        echo "  - Tipos de template de promissoria populados.\n";
    }

    public function down(): void
    {
        // Remover tabelas na ordem correta (por causa das FKs)
        $this->execute("DROP TABLE IF EXISTS promissoria_templates");
        $this->execute("DROP TABLE IF EXISTS promissoria_template_types");

        echo "  - Tabelas de templates de promissoria removidas.\n";
    }

    /**
     * Popular tipos de template padrao do sistema
     */
    private function seedTemplateTypes(): void
    {
        $types = [
            [
                'slug' => 'promissoria_texto_quitada',
                'name_key' => 'promissorias.templates.types.texto_quitada',
                'description_key' => 'promissorias.templates.types.texto_quitada_desc',
                'category' => 'promissoria',
                'available_variables' => '["cliente", "empresa", "promissoria"]',
                'sort_order' => 1
            ],
            [
                'slug' => 'promissoria_texto_pendente',
                'name_key' => 'promissorias.templates.types.texto_pendente',
                'description_key' => 'promissorias.templates.types.texto_pendente_desc',
                'category' => 'promissoria',
                'available_variables' => '["cliente", "empresa", "promissoria"]',
                'sort_order' => 2
            ],
            [
                'slug' => 'parcela_texto_paga',
                'name_key' => 'promissorias.templates.types.parcela_paga',
                'description_key' => 'promissorias.templates.types.parcela_paga_desc',
                'category' => 'parcela',
                'available_variables' => '["cliente", "empresa", "promissoria", "parcela"]',
                'sort_order' => 3
            ],
            [
                'slug' => 'parcela_texto_pendente',
                'name_key' => 'promissorias.templates.types.parcela_pendente',
                'description_key' => 'promissorias.templates.types.parcela_pendente_desc',
                'category' => 'parcela',
                'available_variables' => '["cliente", "empresa", "promissoria", "parcela"]',
                'sort_order' => 4
            ],
        ];

        foreach ($types as $type) {
            $sql = sprintf(
                "INSERT INTO promissoria_template_types
                (slug, name_key, description_key, category, available_variables, sort_order)
                VALUES ('%s', '%s', '%s', '%s', '%s', %d)",
                addslashes($type['slug']),
                addslashes($type['name_key']),
                addslashes($type['description_key']),
                addslashes($type['category']),
                addslashes($type['available_variables']),
                $type['sort_order']
            );
            $this->execute($sql);
        }
    }
};
