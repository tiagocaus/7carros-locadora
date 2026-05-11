<?php

/**
 * Migration: adiciona dois tipos de template para fluxo de reserva vinda do site publico.
 *
 *  - pedido_reserva:       enviado ao cliente quando o pedido de reserva eh registrado no site
 *                          (email + whatsapp). Informa que o pedido foi recebido; quando
 *                          site_config.reserva_requer_confirmacao=1, o cliente ainda aguarda
 *                          a locadora aprovar.
 *  - confirmacao_reserva:  enviado ao cliente quando a locadora confirma um pedido pendente no
 *                          painel (email + whatsapp + sms). Tambem disparado automaticamente
 *                          pela criacao da reserva se reserva_requer_confirmacao=0.
 *
 * Templates padrao (chave='0') sao inseridos apenas em pt_BR. Outros idiomas podem ser
 * adicionados em migrations futuras ou customizados por tenant.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $types = [
            [
                'slug' => 'pedido_reserva',
                'name_key' => 'templates.types.pedido_reserva',
                'description_key' => 'templates.types.pedido_reserva_desc',
                'category' => 'rental',
                'channels' => '["email", "whatsapp"]',
                'available_variables' => '["cliente", "empresa", "locacao", "veiculo", "outros"]',
                'sort_order' => 9,
            ],
            [
                'slug' => 'confirmacao_reserva',
                'name_key' => 'templates.types.confirmacao_reserva',
                'description_key' => 'templates.types.confirmacao_reserva_desc',
                'category' => 'rental',
                'channels' => '["email", "whatsapp", "sms"]',
                'available_variables' => '["cliente", "empresa", "locacao", "veiculo", "outros"]',
                'sort_order' => 10,
            ],
        ];

        foreach ($types as $t) {
            $sql = "INSERT INTO message_template_types
                    (slug, name_key, description_key, category, channels, available_variables, sort_order, is_active)
                    VALUES (:slug, :name_key, :description_key, :category, :channels, :available_variables, :sort_order, 1)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($t);
        }

        $typeIds = $this->getTypeIds(['pedido_reserva', 'confirmacao_reserva']);

        // Templates pt_BR
        $templates = [
            // pedido_reserva — email
            [
                'type' => 'pedido_reserva',
                'channel' => 'email',
                'subject' => 'Recebemos seu pedido de reserva #{{locacao.numero}}',
                'content' => "<h2 style=\"color:#1a56db;\">Pedido de reserva recebido</h2>"
                    . "<p>Olá, {{cliente.primeiro_nome}}!</p>"
                    . "<p>Recebemos seu pedido de reserva. Em breve entraremos em contato para confirmar os detalhes.</p>"
                    . "<p><strong>Código:</strong> {{locacao.numero}}<br>"
                    . "<strong>Retirada:</strong> {{locacao.data_retirada}} {{locacao.hora_retirada}} — {{locacao.local_retirada}}<br>"
                    . "<strong>Devolução:</strong> {{locacao.data_devolucao}} {{locacao.hora_devolucao}} — {{locacao.local_devolucao}}</p>"
                    . "<p>Dúvidas? Entre em contato com a <strong>{{empresa.nome_fantasia}}</strong>.</p>",
            ],
            // pedido_reserva — whatsapp
            [
                'type' => 'pedido_reserva',
                'channel' => 'whatsapp',
                'subject' => null,
                'content' => "📋 *Pedido de reserva recebido*\n\nOlá, {{cliente.primeiro_nome}}!\n\nRecebemos seu pedido e em breve entraremos em contato.\n\n🔖 Código: *{{locacao.numero}}*\n📅 Retirada: {{locacao.data_retirada}} {{locacao.hora_retirada}}\n📍 {{locacao.local_retirada}}\n📅 Devolução: {{locacao.data_devolucao}} {{locacao.hora_devolucao}}\n📍 {{locacao.local_devolucao}}\n\n*{{empresa.nome_fantasia}}*",
            ],
            // confirmacao_reserva — email
            [
                'type' => 'confirmacao_reserva',
                'channel' => 'email',
                'subject' => 'Reserva confirmada #{{locacao.numero}}',
                'content' => "<h2 style=\"color:#1a56db;\">Reserva confirmada!</h2>"
                    . "<p>Olá, {{cliente.primeiro_nome}}!</p>"
                    . "<p>Sua reserva foi confirmada pela <strong>{{empresa.nome_fantasia}}</strong>.</p>"
                    . "<p><strong>Código:</strong> {{locacao.numero}}<br>"
                    . "<strong>Retirada:</strong> {{locacao.data_retirada}} {{locacao.hora_retirada}} — {{locacao.local_retirada}}<br>"
                    . "<strong>Devolução:</strong> {{locacao.data_devolucao}} {{locacao.hora_devolucao}} — {{locacao.local_devolucao}}</p>"
                    . "<p>Nos vemos em breve!</p>",
            ],
            // confirmacao_reserva — whatsapp
            [
                'type' => 'confirmacao_reserva',
                'channel' => 'whatsapp',
                'subject' => null,
                'content' => "✅ *Reserva confirmada!*\n\nOlá, {{cliente.primeiro_nome}}!\n\nSua reserva foi confirmada.\n\n🔖 Código: *{{locacao.numero}}*\n📅 Retirada: {{locacao.data_retirada}} {{locacao.hora_retirada}}\n📍 {{locacao.local_retirada}}\n📅 Devolução: {{locacao.data_devolucao}} {{locacao.hora_devolucao}}\n📍 {{locacao.local_devolucao}}\n\nNos vemos em breve! *{{empresa.nome_fantasia}}*",
            ],
            // confirmacao_reserva — sms
            [
                'type' => 'confirmacao_reserva',
                'channel' => 'sms',
                'subject' => null,
                'content' => 'Reserva #{{locacao.numero}} confirmada! Retirada {{locacao.data_retirada}} {{locacao.hora_retirada}} em {{locacao.local_retirada}}. {{empresa.nome_fantasia}}',
            ],
        ];

        $insertSql = "INSERT INTO message_templates
                      (chave, template_type_id, locale, channel, subject, content, is_active, created_at)
                      VALUES ('0', :type_id, 'pt_BR', :channel, :subject, :content, 1, NOW())
                      ON DUPLICATE KEY UPDATE content = VALUES(content), subject = VALUES(subject)";
        $insertStmt = $this->pdo->prepare($insertSql);

        foreach ($templates as $t) {
            if (!isset($typeIds[$t['type']])) {
                echo "  - tipo {$t['type']} nao encontrado, pulando.\n";
                continue;
            }
            $insertStmt->execute([
                'type_id' => $typeIds[$t['type']],
                'channel' => $t['channel'],
                'subject' => $t['subject'],
                'content' => $t['content'],
            ]);
            echo "  - template {$t['type']} ({$t['channel']}) criado.\n";
        }
    }

    public function down(): void
    {
        $this->execute("
            DELETE mt FROM message_templates mt
            JOIN message_template_types mtt ON mtt.id = mt.template_type_id
            WHERE mtt.slug IN ('pedido_reserva','confirmacao_reserva')
        ");
        $this->execute("DELETE FROM message_template_types WHERE slug IN ('pedido_reserva','confirmacao_reserva')");
    }

    private function getTypeIds(array $slugs): array
    {
        $placeholders = implode(',', array_fill(0, count($slugs), '?'));
        $stmt = $this->pdo->prepare("SELECT id, slug FROM message_template_types WHERE slug IN ($placeholders)");
        $stmt->execute($slugs);
        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $out[$row['slug']] = (int) $row['id'];
        }
        return $out;
    }
};
