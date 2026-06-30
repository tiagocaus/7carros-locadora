<?php

use App\Database\Migration;

/**
 * Atualiza os templates padrao de confirmacao de reserva com valor e grupo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $templates = [
            'email' => [
                'subject' => 'Reserva confirmada #{{locacao.numero}}',
                'content' => <<<'HTML'
<h2 style="color:#1a56db;">Reserva confirmada!</h2>
<p>Olá, {{cliente.primeiro_nome}}!</p>
<p>Sua reserva foi confirmada pela <strong>{{empresa.nome_fantasia}}</strong>.</p>
<p><strong>Código:</strong> {{locacao.numero}}<br>
<strong>Grupo escolhido:</strong> {{locacao.grupo}}<br>
<strong>Valor total:</strong> {{locacao.valor_total}}<br>
<strong>Retirada:</strong> {{locacao.data_retirada}} {{locacao.hora_retirada}} - {{locacao.local_retirada}}<br>
<strong>Devolução:</strong> {{locacao.data_devolucao}} {{locacao.hora_devolucao}} - {{locacao.local_devolucao}}</p>
<p>Nos vemos em breve!</p>
HTML,
            ],
            'whatsapp' => [
                'subject' => null,
                'content' => <<<'TEXT'
✅ *Reserva confirmada!*

Olá, {{cliente.primeiro_nome}}!

Sua reserva foi confirmada.

🔖 Código: *{{locacao.numero}}*
🚗 Grupo: {{locacao.grupo}}
💰 Valor: {{locacao.valor_total}}
📅 Retirada: {{locacao.data_retirada}} {{locacao.hora_retirada}}
📍 {{locacao.local_retirada}}
📅 Devolução: {{locacao.data_devolucao}} {{locacao.hora_devolucao}}
📍 {{locacao.local_devolucao}}

Nos vemos em breve! *{{empresa.nome_fantasia}}*
TEXT,
            ],
            'sms' => [
                'subject' => null,
                'content' => 'Reserva #{{locacao.numero}} confirmada. Retirada {{locacao.data_retirada}} {{locacao.hora_retirada}}. Grupo: {{locacao.grupo}}. Valor: {{locacao.valor_total}}. {{empresa.nome_fantasia}}',
            ],
        ];

        $typeId = $this->getTypeId('confirmacao_reserva');
        if ($typeId === null) {
            echo "  - tipo confirmacao_reserva nao encontrado, pulando.\n";
            return;
        }

        $stmt = $this->pdo->prepare("
            UPDATE message_templates
            SET subject = :subject,
                content = :content,
                content_plain = NULL,
                updated_at = NOW()
            WHERE chave = '0'
              AND template_type_id = :type_id
              AND locale = 'pt_BR'
              AND channel = :channel
        ");

        foreach ($templates as $channel => $template) {
            $stmt->execute([
                'subject' => $template['subject'],
                'content' => $template['content'],
                'type_id' => $typeId,
                'channel' => $channel,
            ]);
        }

        echo "  - templates confirmacao_reserva atualizados.\n";
    }

    public function down(): void
    {
        $templates = [
            'email' => [
                'subject' => 'Reserva confirmada #{{locacao.numero}}',
                'content' => "<h2 style=\"color:#1a56db;\">Reserva confirmada!</h2>"
                    . "<p>Olá, {{cliente.primeiro_nome}}!</p>"
                    . "<p>Sua reserva foi confirmada pela <strong>{{empresa.nome_fantasia}}</strong>.</p>"
                    . "<p><strong>Código:</strong> {{locacao.numero}}<br>"
                    . "<strong>Retirada:</strong> {{locacao.data_retirada}} {{locacao.hora_retirada}} — {{locacao.local_retirada}}<br>"
                    . "<strong>Devolução:</strong> {{locacao.data_devolucao}} {{locacao.hora_devolucao}} — {{locacao.local_devolucao}}</p>"
                    . "<p>Nos vemos em breve!</p>",
            ],
            'whatsapp' => [
                'subject' => null,
                'content' => "✅ *Reserva confirmada!*\n\nOlá, {{cliente.primeiro_nome}}!\n\nSua reserva foi confirmada.\n\n🔖 Código: *{{locacao.numero}}*\n📅 Retirada: {{locacao.data_retirada}} {{locacao.hora_retirada}}\n📍 {{locacao.local_retirada}}\n📅 Devolução: {{locacao.data_devolucao}} {{locacao.hora_devolucao}}\n📍 {{locacao.local_devolucao}}\n\nNos vemos em breve! *{{empresa.nome_fantasia}}*",
            ],
            'sms' => [
                'subject' => null,
                'content' => 'Reserva #{{locacao.numero}} confirmada! Retirada {{locacao.data_retirada}} {{locacao.hora_retirada}} em {{locacao.local_retirada}}. {{empresa.nome_fantasia}}',
            ],
        ];

        $typeId = $this->getTypeId('confirmacao_reserva');
        if ($typeId === null) {
            return;
        }

        $stmt = $this->pdo->prepare("
            UPDATE message_templates
            SET subject = :subject,
                content = :content,
                content_plain = NULL,
                updated_at = NOW()
            WHERE chave = '0'
              AND template_type_id = :type_id
              AND locale = 'pt_BR'
              AND channel = :channel
        ");

        foreach ($templates as $channel => $template) {
            $stmt->execute([
                'subject' => $template['subject'],
                'content' => $template['content'],
                'type_id' => $typeId,
                'channel' => $channel,
            ]);
        }
    }

    private function getTypeId(string $slug): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM message_template_types WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }
};
