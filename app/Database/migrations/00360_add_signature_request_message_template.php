<?php

/**
 * Migration: adiciona template de pedido de assinatura digital.
 *
 * Usado para enviar o link publico de assinatura de contratos e locacoes.
 * Canais: email, WhatsApp e SMS.
 * Variaveis: cliente, empresa, contrato, locacao e outros.link_assinatura.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $typeSql = "INSERT INTO message_template_types
                    (slug, name_key, description_key, category, channels, available_variables, sort_order, is_active)
                    VALUES (:slug, :name_key, :description_key, :category, :channels, :available_variables, :sort_order, 1)
                    ON DUPLICATE KEY UPDATE
                        name_key = VALUES(name_key),
                        description_key = VALUES(description_key),
                        category = VALUES(category),
                        channels = VALUES(channels),
                        available_variables = VALUES(available_variables),
                        sort_order = VALUES(sort_order),
                        is_active = 1";

        $typeStmt = $this->pdo->prepare($typeSql);
        $typeStmt->execute([
            'slug' => 'signature_request',
            'name_key' => 'templates.types.signature_request',
            'description_key' => 'templates.types.signature_request_desc',
            'category' => 'rental',
            'channels' => '["email", "whatsapp", "sms"]',
            'available_variables' => '["cliente", "empresa", "contrato", "locacao", "outros"]',
            'sort_order' => 12,
        ]);

        $row = $this->pdo
            ->query("SELECT id FROM message_template_types WHERE slug = 'signature_request'")
            ->fetch(\PDO::FETCH_ASSOC);
        $typeId = (int) ($row['id'] ?? 0);

        if (!$typeId) {
            echo "  - tipo signature_request nao encontrado, pulando templates.\n";
            return;
        }

        $templates = [
            [
                'channel' => 'email',
                'subject' => 'Assinatura digital pendente - {{empresa.nome_fantasia}}',
                'content' => '<p>Olá, {{cliente.primeiro_nome}}.</p>'
                    . '<p>Seu documento da {{empresa.nome_fantasia}} está pronto para assinatura digital.</p>'
                    . '<p>Acesse o link abaixo para revisar e assinar com segurança:<br>'
                    . '<a href="{{outros.link_assinatura}}">{{outros.link_assinatura}}</a></p>'
                    . '<p>Se você não reconhece esta solicitação, entre em contato com a locadora antes de prosseguir.</p>'
                    . '<p>Atenciosamente,<br>{{empresa.nome_fantasia}}</p>',
            ],
            [
                'channel' => 'whatsapp',
                'subject' => null,
                'content' => "Olá, {{cliente.primeiro_nome}}.\n\n"
                    . "Seu documento da {{empresa.nome_fantasia}} está pronto para assinatura digital.\n\n"
                    . "Acesse o link para revisar e assinar:\n"
                    . "{{outros.link_assinatura}}\n\n"
                    . "Se você não reconhece esta solicitação, entre em contato com a locadora antes de prosseguir.",
            ],
            [
                'channel' => 'sms',
                'subject' => null,
                'content' => '{{empresa.nome_fantasia}}: seu documento está pronto para assinatura digital. Acesse: {{outros.link_assinatura}}',
            ],
        ];

        $templateSql = "INSERT INTO message_templates
                        (chave, template_type_id, locale, channel, subject, content, is_active, created_at)
                        VALUES ('0', :type_id, 'pt_BR', :channel, :subject, :content, 1, NOW())
                        ON DUPLICATE KEY UPDATE
                            subject = VALUES(subject),
                            content = VALUES(content),
                            is_active = 1";
        $templateStmt = $this->pdo->prepare($templateSql);

        foreach ($templates as $template) {
            $templateStmt->execute([
                'type_id' => $typeId,
                'channel' => $template['channel'],
                'subject' => $template['subject'],
                'content' => $template['content'],
            ]);
            echo "  - template signature_request ({$template['channel']}) criado.\n";
        }
    }

    public function down(): void
    {
        $this->execute("
            DELETE mt FROM message_templates mt
            JOIN message_template_types mtt ON mtt.id = mt.template_type_id
            WHERE mtt.slug = 'signature_request'
        ");
        $this->execute("DELETE FROM message_template_types WHERE slug = 'signature_request'");
    }
};
