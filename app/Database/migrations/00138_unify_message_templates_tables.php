<?php

use App\Database\Migration;

/**
 * Migration: Unificar tabelas de templates de mensagem
 *
 * Migra os dados de `message_template_defaults` para `message_templates`
 * usando `chave = '0'` para identificar templates padrão do sistema.
 *
 * Após a migração:
 * - message_templates com chave = '0' -> Templates padrão do sistema
 * - message_templates com chave = {tenant} -> Templates customizados
 *
 * A tabela `message_template_defaults` é removida.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Verificar se a tabela message_template_defaults existe
        $tableExists = $this->tableExists('message_template_defaults');

        if ($tableExists) {
            // 2. Migrar dados de message_template_defaults para message_templates
            echo "  - Migrando templates padrão para message_templates...\n";

            $this->execute("
                INSERT INTO message_templates (
                    chave,
                    template_type_id,
                    locale,
                    channel,
                    subject,
                    content,
                    content_plain,
                    is_active,
                    created_at,
                    updated_at,
                    created_by,
                    updated_by
                )
                SELECT
                    '0' as chave,
                    template_type_id,
                    locale,
                    channel,
                    subject,
                    content,
                    content_plain,
                    1 as is_active,
                    created_at,
                    updated_at,
                    NULL as created_by,
                    NULL as updated_by
                FROM message_template_defaults
                ON DUPLICATE KEY UPDATE
                    subject = VALUES(subject),
                    content = VALUES(content),
                    content_plain = VALUES(content_plain),
                    updated_at = NOW()
            ");

            echo "  - Templates migrados.\n";

            // 3. Limpar HTML de templates de email em idiomas não-pt_BR
            echo "  - Limpando HTML de templates de email i18n...\n";
            $this->cleanHtmlFromI18nEmailTemplates();

            // 4. Dropar a tabela message_template_defaults
            echo "  - Removendo tabela message_template_defaults...\n";
            $this->execute("DROP TABLE IF EXISTS message_template_defaults");

            echo "  - Migração concluída com sucesso!\n";
        } else {
            echo "  - Tabela message_template_defaults não existe. Nada a fazer.\n";
        }
    }

    public function down(): void
    {
        // 1. Recriar tabela message_template_defaults
        echo "  - Recriando tabela message_template_defaults...\n";

        $this->execute("
            CREATE TABLE IF NOT EXISTS message_template_defaults (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                template_type_id INT UNSIGNED NOT NULL COMMENT 'FK para message_template_types',
                locale VARCHAR(10) NOT NULL COMMENT 'Código do idioma (ex: pt_BR, en_US)',
                channel ENUM('email', 'sms', 'whatsapp') NOT NULL COMMENT 'Canal de envio',
                subject VARCHAR(255) DEFAULT NULL COMMENT 'Assunto (para email)',
                content LONGTEXT NOT NULL COMMENT 'Conteúdo do template',
                content_plain TEXT DEFAULT NULL COMMENT 'Versão texto puro',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_type_locale_channel (template_type_id, locale, channel),
                INDEX idx_locale (locale),
                INDEX idx_channel (channel),
                CONSTRAINT fk_defaults_type FOREIGN KEY (template_type_id)
                    REFERENCES message_template_types(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Templates padrão do sistema por idioma'
        ");

        // 2. Copiar dados de volta
        echo "  - Copiando templates padrão de volta...\n";

        $this->execute("
            INSERT INTO message_template_defaults (
                template_type_id,
                locale,
                channel,
                subject,
                content,
                content_plain,
                created_at,
                updated_at
            )
            SELECT
                template_type_id,
                locale,
                channel,
                subject,
                content,
                content_plain,
                created_at,
                updated_at
            FROM message_templates
            WHERE chave = '0'
        ");

        // 3. Remover registros com chave = '0' de message_templates
        echo "  - Removendo templates com chave='0' de message_templates...\n";
        $this->execute("DELETE FROM message_templates WHERE chave = '0'");

        echo "  - Rollback concluído.\n";
    }

    /**
     * Limpa HTML de templates de email em idiomas não-pt_BR
     *
     * Remove estrutura HTML completa, mantendo apenas o conteúdo
     * que será envolvido pelo layout base.
     */
    private function cleanHtmlFromI18nEmailTemplates(): void
    {
        $locales = ['en_US', 'es_ES', 'it_IT', 'pt_PT'];

        foreach ($locales as $locale) {
            // Buscar templates de email deste locale
            $stmt = $this->pdo->prepare("
                SELECT mt.id, mt.content, mtt.slug
                FROM message_templates mt
                JOIN message_template_types mtt ON mt.template_type_id = mtt.id
                WHERE mt.chave = '0'
                  AND mt.locale = ?
                  AND mt.channel = 'email'
            ");
            $stmt->execute([$locale]);

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $cleanContent = $this->extractContentFromHtml($row['content']);

                if ($cleanContent !== $row['content']) {
                    $updateStmt = $this->pdo->prepare("
                        UPDATE message_templates
                        SET content = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$cleanContent, $row['id']]);
                }
            }
        }
    }

    /**
     * Extrai conteúdo limpo de um template HTML
     *
     * Remove DOCTYPE, html, head, body tags e estruturas de container/header/footer
     * Mantém apenas o conteúdo principal do email
     */
    private function extractContentFromHtml(string $html): string
    {
        // Se não parece ser HTML completo, retorna original
        if (strpos($html, '<!DOCTYPE') === false && strpos($html, '<html') === false) {
            return $html;
        }

        // Tenta extrair conteúdo da div.content
        if (preg_match('/<div class="content"[^>]*>(.*?)<\/div>\s*<div class="footer"/s', $html, $matches)) {
            return trim($matches[1]);
        }

        // Alternativa: extrair conteúdo entre </style> e <div class="footer">
        if (preg_match('/<\/style>\s*<\/head>\s*<body>\s*<div class="container">\s*<div class="header">.*?<\/div>\s*<div class="content"[^>]*>(.*?)<\/div>/s', $html, $matches)) {
            return trim($matches[1]);
        }

        // Outra alternativa: pegar tudo dentro do body e remover header/footer
        if (preg_match('/<body[^>]*>(.*?)<\/body>/s', $html, $matches)) {
            $body = $matches[1];

            // Remove container wrapper
            $body = preg_replace('/<div class="container"[^>]*>\s*/s', '', $body);
            $body = preg_replace('/\s*<\/div>\s*$/s', '', $body);

            // Remove header
            $body = preg_replace('/<div class="header"[^>]*>.*?<\/div>\s*/s', '', $body);

            // Remove footer
            $body = preg_replace('/\s*<div class="footer"[^>]*>.*?<\/div>/s', '', $body);

            // Remove content wrapper mantendo conteúdo
            $body = preg_replace('/<div class="content"[^>]*>\s*/s', '', $body);
            $body = preg_replace('/\s*<\/div>\s*$/s', '', $body);

            return trim($body);
        }

        // Se nada funcionar, retorna original
        return $html;
    }
};
