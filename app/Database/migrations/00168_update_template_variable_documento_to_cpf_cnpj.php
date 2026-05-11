<?php

use App\Database\Migration;

/**
 * Migration: Atualizar variável de template {{cliente.documento}} para {{cliente.cpf_cnpj}}
 *
 * Após reverter a coluna clientes.documentos para clientes.cpf_cnpj,
 * os templates de mensagem existentes ainda podem usar a variável antiga.
 * Esta migration atualiza todos os templates para usar a nova variável.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Atualizar campo content
        $this->execute("
            UPDATE message_templates
            SET content = REPLACE(content, '{{cliente.documento}}', '{{cliente.cpf_cnpj}}')
            WHERE content LIKE '%{{cliente.documento}}%'
        ");
        echo "  - Templates atualizados (content).\n";

        // Atualizar campo subject (se houver variável no assunto)
        $this->execute("
            UPDATE message_templates
            SET subject = REPLACE(subject, '{{cliente.documento}}', '{{cliente.cpf_cnpj}}')
            WHERE subject LIKE '%{{cliente.documento}}%'
        ");
        echo "  - Templates atualizados (subject).\n";

        // Atualizar campo content_plain (versão texto puro)
        $this->execute("
            UPDATE message_templates
            SET content_plain = REPLACE(content_plain, '{{cliente.documento}}', '{{cliente.cpf_cnpj}}')
            WHERE content_plain LIKE '%{{cliente.documento}}%'
        ");
        echo "  - Templates atualizados (content_plain).\n";
    }

    public function down(): void
    {
        // Reverter: trocar {{cliente.cpf_cnpj}} de volta para {{cliente.documento}}
        $this->execute("
            UPDATE message_templates
            SET content = REPLACE(content, '{{cliente.cpf_cnpj}}', '{{cliente.documento}}')
            WHERE content LIKE '%{{cliente.cpf_cnpj}}%'
        ");

        $this->execute("
            UPDATE message_templates
            SET subject = REPLACE(subject, '{{cliente.cpf_cnpj}}', '{{cliente.documento}}')
            WHERE subject LIKE '%{{cliente.cpf_cnpj}}%'
        ");

        $this->execute("
            UPDATE message_templates
            SET content_plain = REPLACE(content_plain, '{{cliente.cpf_cnpj}}', '{{cliente.documento}}')
            WHERE content_plain LIKE '%{{cliente.cpf_cnpj}}%'
        ");

        echo "  - Templates revertidos para {{cliente.documento}}.\n";
    }
};
