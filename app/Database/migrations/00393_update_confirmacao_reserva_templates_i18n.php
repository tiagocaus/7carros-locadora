<?php

use App\Database\Migration;

/**
 * Garante confirmacao_reserva em todos os idiomas e canais padrao.
 */
return new class extends Migration
{
    public function up(): void
    {
        $typeId = $this->getTypeId('confirmacao_reserva');
        if ($typeId === null) {
            echo "  - tipo confirmacao_reserva nao encontrado, pulando.\n";
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO message_templates
                (chave, template_type_id, locale, channel, subject, content, content_plain, is_active, created_at, updated_at)
            VALUES
                ('0', :type_id, :locale, :channel, :subject, :content, NULL, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                subject = VALUES(subject),
                content = VALUES(content),
                content_plain = NULL,
                is_active = 1,
                updated_at = NOW()
        ");

        foreach ($this->templates() as $locale => $channels) {
            foreach ($channels as $channel => $template) {
                $stmt->execute([
                    'type_id' => $typeId,
                    'locale' => $locale,
                    'channel' => $channel,
                    'subject' => $template['subject'],
                    'content' => $template['content'],
                ]);
            }
        }

        echo "  - templates confirmacao_reserva i18n atualizados.\n";
    }

    public function down(): void
    {
        $typeId = $this->getTypeId('confirmacao_reserva');
        if ($typeId === null) {
            return;
        }

        $locales = ['en_US', 'es_ES', 'it_IT', 'pt_PT'];
        $placeholders = implode(',', array_fill(0, count($locales), '?'));

        $stmt = $this->pdo->prepare("
            DELETE FROM message_templates
            WHERE chave = '0'
              AND template_type_id = ?
              AND locale IN ({$placeholders})
        ");
        $stmt->execute(array_merge([$typeId], $locales));
    }

    private function getTypeId(string $slug): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM message_template_types WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private function templates(): array
    {
        return [
            'pt_BR' => [
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
            ],
            'en_US' => [
                'email' => [
                    'subject' => 'Reservation confirmed #{{locacao.numero}}',
                    'content' => <<<'HTML'
<h2 style="color:#1a56db;">Reservation confirmed!</h2>
<p>Hello, {{cliente.primeiro_nome}}!</p>
<p>Your reservation has been confirmed by <strong>{{empresa.nome_fantasia}}</strong>.</p>
<p><strong>Code:</strong> {{locacao.numero}}<br>
<strong>Selected group:</strong> {{locacao.grupo}}<br>
<strong>Total amount:</strong> {{locacao.valor_total}}<br>
<strong>Pickup:</strong> {{locacao.data_retirada}} {{locacao.hora_retirada}} - {{locacao.local_retirada}}<br>
<strong>Return:</strong> {{locacao.data_devolucao}} {{locacao.hora_devolucao}} - {{locacao.local_devolucao}}</p>
<p>See you soon!</p>
HTML,
                ],
                'whatsapp' => [
                    'subject' => null,
                    'content' => <<<'TEXT'
✅ *Reservation confirmed!*

Hello, {{cliente.primeiro_nome}}!

Your reservation has been confirmed.

🔖 Code: *{{locacao.numero}}*
🚗 Group: {{locacao.grupo}}
💰 Amount: {{locacao.valor_total}}
📅 Pickup: {{locacao.data_retirada}} {{locacao.hora_retirada}}
📍 {{locacao.local_retirada}}
📅 Return: {{locacao.data_devolucao}} {{locacao.hora_devolucao}}
📍 {{locacao.local_devolucao}}

See you soon! *{{empresa.nome_fantasia}}*
TEXT,
                ],
                'sms' => [
                    'subject' => null,
                    'content' => 'Reservation #{{locacao.numero}} confirmed. Pickup {{locacao.data_retirada}} {{locacao.hora_retirada}}. Group: {{locacao.grupo}}. Amount: {{locacao.valor_total}}. {{empresa.nome_fantasia}}',
                ],
            ],
            'es_ES' => [
                'email' => [
                    'subject' => 'Reserva confirmada #{{locacao.numero}}',
                    'content' => <<<'HTML'
<h2 style="color:#1a56db;">¡Reserva confirmada!</h2>
<p>Hola, {{cliente.primeiro_nome}}!</p>
<p>Su reserva fue confirmada por <strong>{{empresa.nome_fantasia}}</strong>.</p>
<p><strong>Código:</strong> {{locacao.numero}}<br>
<strong>Grupo elegido:</strong> {{locacao.grupo}}<br>
<strong>Valor total:</strong> {{locacao.valor_total}}<br>
<strong>Recogida:</strong> {{locacao.data_retirada}} {{locacao.hora_retirada}} - {{locacao.local_retirada}}<br>
<strong>Devolución:</strong> {{locacao.data_devolucao}} {{locacao.hora_devolucao}} - {{locacao.local_devolucao}}</p>
<p>¡Nos vemos pronto!</p>
HTML,
                ],
                'whatsapp' => [
                    'subject' => null,
                    'content' => <<<'TEXT'
✅ *¡Reserva confirmada!*

Hola, {{cliente.primeiro_nome}}!

Su reserva fue confirmada.

🔖 Código: *{{locacao.numero}}*
🚗 Grupo: {{locacao.grupo}}
💰 Valor: {{locacao.valor_total}}
📅 Recogida: {{locacao.data_retirada}} {{locacao.hora_retirada}}
📍 {{locacao.local_retirada}}
📅 Devolución: {{locacao.data_devolucao}} {{locacao.hora_devolucao}}
📍 {{locacao.local_devolucao}}

¡Nos vemos pronto! *{{empresa.nome_fantasia}}*
TEXT,
                ],
                'sms' => [
                    'subject' => null,
                    'content' => 'Reserva #{{locacao.numero}} confirmada. Recogida {{locacao.data_retirada}} {{locacao.hora_retirada}}. Grupo: {{locacao.grupo}}. Valor: {{locacao.valor_total}}. {{empresa.nome_fantasia}}',
                ],
            ],
            'pt_PT' => [
                'email' => [
                    'subject' => 'Reserva confirmada #{{locacao.numero}}',
                    'content' => <<<'HTML'
<h2 style="color:#1a56db;">Reserva confirmada!</h2>
<p>Olá, {{cliente.primeiro_nome}}!</p>
<p>A sua reserva foi confirmada pela <strong>{{empresa.nome_fantasia}}</strong>.</p>
<p><strong>Código:</strong> {{locacao.numero}}<br>
<strong>Grupo escolhido:</strong> {{locacao.grupo}}<br>
<strong>Valor total:</strong> {{locacao.valor_total}}<br>
<strong>Levantamento:</strong> {{locacao.data_retirada}} {{locacao.hora_retirada}} - {{locacao.local_retirada}}<br>
<strong>Devolução:</strong> {{locacao.data_devolucao}} {{locacao.hora_devolucao}} - {{locacao.local_devolucao}}</p>
<p>Até breve!</p>
HTML,
                ],
                'whatsapp' => [
                    'subject' => null,
                    'content' => <<<'TEXT'
✅ *Reserva confirmada!*

Olá, {{cliente.primeiro_nome}}!

A sua reserva foi confirmada.

🔖 Código: *{{locacao.numero}}*
🚗 Grupo: {{locacao.grupo}}
💰 Valor: {{locacao.valor_total}}
📅 Levantamento: {{locacao.data_retirada}} {{locacao.hora_retirada}}
📍 {{locacao.local_retirada}}
📅 Devolução: {{locacao.data_devolucao}} {{locacao.hora_devolucao}}
📍 {{locacao.local_devolucao}}

Até breve! *{{empresa.nome_fantasia}}*
TEXT,
                ],
                'sms' => [
                    'subject' => null,
                    'content' => 'Reserva #{{locacao.numero}} confirmada. Levantamento {{locacao.data_retirada}} {{locacao.hora_retirada}}. Grupo: {{locacao.grupo}}. Valor: {{locacao.valor_total}}. {{empresa.nome_fantasia}}',
                ],
            ],
            'it_IT' => [
                'email' => [
                    'subject' => 'Prenotazione confermata #{{locacao.numero}}',
                    'content' => <<<'HTML'
<h2 style="color:#1a56db;">Prenotazione confermata!</h2>
<p>Ciao, {{cliente.primeiro_nome}}!</p>
<p>La tua prenotazione è stata confermata da <strong>{{empresa.nome_fantasia}}</strong>.</p>
<p><strong>Codice:</strong> {{locacao.numero}}<br>
<strong>Gruppo scelto:</strong> {{locacao.grupo}}<br>
<strong>Importo totale:</strong> {{locacao.valor_total}}<br>
<strong>Ritiro:</strong> {{locacao.data_retirada}} {{locacao.hora_retirada}} - {{locacao.local_retirada}}<br>
<strong>Restituzione:</strong> {{locacao.data_devolucao}} {{locacao.hora_devolucao}} - {{locacao.local_devolucao}}</p>
<p>A presto!</p>
HTML,
                ],
                'whatsapp' => [
                    'subject' => null,
                    'content' => <<<'TEXT'
✅ *Prenotazione confermata!*

Ciao, {{cliente.primeiro_nome}}!

La tua prenotazione è stata confermata.

🔖 Codice: *{{locacao.numero}}*
🚗 Gruppo: {{locacao.grupo}}
💰 Importo: {{locacao.valor_total}}
📅 Ritiro: {{locacao.data_retirada}} {{locacao.hora_retirada}}
📍 {{locacao.local_retirada}}
📅 Restituzione: {{locacao.data_devolucao}} {{locacao.hora_devolucao}}
📍 {{locacao.local_devolucao}}

A presto! *{{empresa.nome_fantasia}}*
TEXT,
                ],
                'sms' => [
                    'subject' => null,
                    'content' => 'Prenotazione #{{locacao.numero}} confermata. Ritiro {{locacao.data_retirada}} {{locacao.hora_retirada}}. Gruppo: {{locacao.grupo}}. Importo: {{locacao.valor_total}}. {{empresa.nome_fantasia}}',
                ],
            ],
        ];
    }
};
