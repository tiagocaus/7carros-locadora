<?php

use App\Database\Migration;

/**
 * Migration: Popular templates padrão de mensagem
 *
 * Cria templates padrão (chave = '0') para todos os tipos em pt_BR
 * Templates para outros idiomas podem ser adicionados posteriormente
 */
return new class extends Migration
{
    public function up(): void
    {
        // Buscar IDs dos tipos de template
        $types = $this->getTemplateTypes();

        // Templates de email para cada tipo
        $this->seedEmailTemplates($types);

        // Templates de WhatsApp para cada tipo
        $this->seedWhatsAppTemplates($types);

        // Templates de SMS para cada tipo
        $this->seedSmsTemplates($types);

        echo "  - Templates padrão populados.\n";
    }

    public function down(): void
    {
        $this->execute("DELETE FROM message_templates WHERE chave = '0' AND locale = 'pt_BR'");
        echo "  - Templates padrão pt_BR removidos.\n";
    }

    /**
     * Busca tipos de template do banco
     */
    private function getTemplateTypes(): array
    {
        $stmt = $this->pdo->query("SELECT id, slug FROM message_template_types");
        $types = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $types[$row['slug']] = (int) $row['id'];
        }
        return $types;
    }

    /**
     * Popula templates de email
     */
    private function seedEmailTemplates(array $types): void
    {
        $templates = [
            'welcome' => [
                'subject' => 'Bem-vindo à {{empresa.nome_fantasia}}!',
                'content' => $this->getWelcomeEmailTemplate(),
            ],
            'rental_confirmation' => [
                'subject' => 'Confirmação de Locação #{{locacao.numero}}',
                'content' => $this->getRentalConfirmationEmailTemplate(),
            ],
            'contract_confirmation' => [
                'subject' => 'Contrato #{{contrato.numero}} - {{empresa.nome_fantasia}}',
                'content' => $this->getContractConfirmationEmailTemplate(),
            ],
            'return_reminder' => [
                'subject' => 'Lembrete: Devolução do veículo {{veiculo.placa}}',
                'content' => $this->getReturnReminderEmailTemplate(),
            ],
            'payment_reminder' => [
                'subject' => 'Lembrete de Pagamento - Fatura #{{fatura.numero}}',
                'content' => $this->getPaymentReminderEmailTemplate(),
            ],
            'invoice_generated' => [
                'subject' => 'Nova Fatura #{{fatura.numero}} - {{empresa.nome_fantasia}}',
                'content' => $this->getInvoiceGeneratedEmailTemplate(),
            ],
            'overdue_notice' => [
                'subject' => 'Aviso: Fatura #{{fatura.numero}} em atraso',
                'content' => $this->getOverdueNoticeEmailTemplate(),
            ],
            'cnh_expiring' => [
                'subject' => 'Atenção: Sua CNH está próxima do vencimento',
                'content' => $this->getCnhExpiringEmailTemplate(),
            ],
        ];

        foreach ($templates as $slug => $data) {
            if (!isset($types[$slug])) {
                continue;
            }

            $this->insertTemplate($types[$slug], 'pt_BR', 'email', $data['subject'], $data['content']);
        }
    }

    /**
     * Popula templates de WhatsApp
     */
    private function seedWhatsAppTemplates(array $types): void
    {
        $templates = [
            'welcome' => "Olá, {{cliente.primeiro_nome}}! 👋\n\nSeja bem-vindo(a) à *{{empresa.nome_fantasia}}*!\n\nEstamos felizes em tê-lo como cliente. Se precisar de qualquer ajuda, estamos à disposição.\n\n📞 {{empresa.telefone}}\n📧 {{empresa.email}}",

            'rental_confirmation' => "✅ *Locação Confirmada!*\n\n📋 Número: {{locacao.numero}}\n🚗 Veículo: {{veiculo.descricao_completa}}\n\n📅 Retirada: {{locacao.data_retirada}} às {{locacao.hora_retirada}}\n📍 Local: {{locacao.local_retirada}}\n\n📅 Devolução: {{locacao.data_devolucao}} às {{locacao.hora_devolucao}}\n📍 Local: {{locacao.local_devolucao}}\n\n💰 Valor Total: {{locacao.valor_total}}\n\n*{{empresa.nome_fantasia}}*",

            'return_reminder' => "⏰ *Lembrete de Devolução*\n\nOlá, {{cliente.primeiro_nome}}!\n\nLembramos que a devolução do veículo *{{veiculo.placa}}* está agendada para:\n\n📅 Data: {{locacao.data_devolucao}}\n🕐 Horário: {{locacao.hora_devolucao}}\n📍 Local: {{locacao.local_devolucao}}\n\nDúvidas? Entre em contato!\n*{{empresa.nome_fantasia}}*",

            'payment_reminder' => "💳 *Lembrete de Pagamento*\n\nOlá, {{cliente.primeiro_nome}}!\n\nSua fatura #{{fatura.numero}} no valor de {{fatura.valor}} vence em {{fatura.data_vencimento}}.\n\n🔗 Link para pagamento:\n{{fatura.link_boleto}}\n\n*{{empresa.nome_fantasia}}*",

            'invoice_generated' => "📄 *Nova Fatura Gerada*\n\nOlá, {{cliente.primeiro_nome}}!\n\nFatura #{{fatura.numero}}\n💰 Valor: {{fatura.valor}}\n📅 Vencimento: {{fatura.data_vencimento}}\n\n🔗 Pague aqui:\n{{fatura.link_boleto}}\n\n*{{empresa.nome_fantasia}}*",

            'overdue_notice' => "⚠️ *Fatura em Atraso*\n\nOlá, {{cliente.primeiro_nome}}.\n\nIdentificamos que a fatura #{{fatura.numero}} no valor de {{fatura.valor}} encontra-se em atraso.\n\nRegularize sua situação para evitar juros e multas.\n\n🔗 Link para pagamento:\n{{fatura.link_boleto}}\n\n*{{empresa.nome_fantasia}}*",

            'cnh_expiring' => "⚠️ *CNH Próxima do Vencimento*\n\nOlá, {{cliente.primeiro_nome}}!\n\nSua CNH vence em {{cliente.cnh_validade}}.\n\nLembre-se de renovar para continuar alugando conosco!\n\n*{{empresa.nome_fantasia}}*",
        ];

        foreach ($templates as $slug => $content) {
            if (!isset($types[$slug])) {
                continue;
            }

            $this->insertTemplate($types[$slug], 'pt_BR', 'whatsapp', null, $content);
        }
    }

    /**
     * Popula templates de SMS
     */
    private function seedSmsTemplates(array $types): void
    {
        $templates = [
            'rental_confirmation' => "Locacao #{{locacao.numero}} confirmada! Retirada: {{locacao.data_retirada}} {{locacao.hora_retirada}}. Veiculo: {{veiculo.placa}}. {{empresa.nome_fantasia}}",

            'return_reminder' => "Lembrete: Devolucao do veiculo {{veiculo.placa}} em {{locacao.data_devolucao}} as {{locacao.hora_devolucao}}. {{empresa.nome_fantasia}}",

            'payment_reminder' => "Fatura #{{fatura.numero}} de {{fatura.valor}} vence em {{fatura.data_vencimento}}. {{empresa.nome_fantasia}}",

            'overdue_notice' => "Fatura #{{fatura.numero}} em atraso. Valor: {{fatura.valor}}. Regularize para evitar juros. {{empresa.nome_fantasia}}",
        ];

        foreach ($templates as $slug => $content) {
            if (!isset($types[$slug])) {
                continue;
            }

            $this->insertTemplate($types[$slug], 'pt_BR', 'sms', null, $content);
        }
    }

    /**
     * Insere um template no banco
     */
    private function insertTemplate(int $typeId, string $locale, string $channel, ?string $subject, string $content): void
    {
        $subject = $subject ? addslashes($subject) : 'NULL';
        $subjectSql = $subject === 'NULL' ? 'NULL' : "'{$subject}'";

        $sql = sprintf(
            "INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, is_active, created_at)
             VALUES ('0', %d, '%s', '%s', %s, '%s', 1, NOW())",
            $typeId,
            addslashes($locale),
            addslashes($channel),
            $subjectSql,
            addslashes($content)
        );

        $this->execute($sql);
    }

    // ========== Templates de Email ==========

    private function getWelcomeEmailTemplate(): string
    {
        return <<<'HTML'
<h2 style="color: #1a56db; margin: 0 0 20px 0;">Bem-vindo(a)!</h2>

<p>Olá, <strong>{{cliente.nome}}</strong>!</p>

<p>Seja bem-vindo(a) à <strong>{{empresa.nome_fantasia}}</strong>!</p>

<p>Estamos muito felizes em tê-lo como nosso cliente. A partir de agora, você terá acesso aos melhores veículos e ao atendimento de qualidade que você merece.</p>

<p>Se tiver qualquer dúvida, nossa equipe está à disposição para ajudá-lo.</p>
HTML;
    }

    private function getRentalConfirmationEmailTemplate(): string
    {
        return <<<'HTML'
<h2 style="color: #059669; margin: 0 0 20px 0;">✅ Locação Confirmada!</h2>

<p>Olá, <strong>{{cliente.primeiro_nome}}</strong>!</p>

<p>Sua locação foi confirmada com sucesso. Confira os detalhes abaixo:</p>

<div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0;">
    <h3 style="margin: 0 0 10px 0; color: #059669;">📋 Dados da Locação</h3>
    <p style="margin: 5px 0;"><strong>Número:</strong> {{locacao.numero}}</p>
    <p style="margin: 5px 0;"><strong>Valor Total:</strong> {{locacao.valor_total}}</p>
</div>

<div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0;">
    <h3 style="margin: 0 0 10px 0; color: #059669;">🚗 Veículo</h3>
    <p style="margin: 5px 0;"><strong>Veículo:</strong> {{veiculo.descricao_completa}}</p>
    <p style="margin: 5px 0;"><strong>Placa:</strong> {{veiculo.placa}}</p>
</div>

<div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0;">
    <h3 style="margin: 0 0 10px 0; color: #059669;">📅 Retirada</h3>
    <p style="margin: 5px 0;"><strong>Data:</strong> {{locacao.data_retirada}} às {{locacao.hora_retirada}}</p>
    <p style="margin: 5px 0;"><strong>Local:</strong> {{locacao.local_retirada}}</p>
</div>

<div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0;">
    <h3 style="margin: 0 0 10px 0; color: #059669;">📅 Devolução</h3>
    <p style="margin: 5px 0;"><strong>Data:</strong> {{locacao.data_devolucao}} às {{locacao.hora_devolucao}}</p>
    <p style="margin: 5px 0;"><strong>Local:</strong> {{locacao.local_devolucao}}</p>
</div>

<p><strong>Documentos necessários:</strong> CNH válida e documento com foto.</p>
HTML;
    }

    private function getContractConfirmationEmailTemplate(): string
    {
        return <<<'HTML'
<h2 style="color: #1a56db; margin: 0 0 20px 0;">Contrato #{{contrato.numero}}</h2>

<p>Olá, <strong>{{cliente.nome}}</strong>!</p>

<p>Seu contrato foi gerado com sucesso. Seguem os detalhes:</p>

<div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0;">
    <p style="margin: 5px 0;"><strong>Número do Contrato:</strong> {{contrato.numero}}</p>
    <p style="margin: 5px 0;"><strong>Período:</strong> {{contrato.data_inicio}} a {{contrato.data_fim}}</p>
    <p style="margin: 5px 0;"><strong>Veículo:</strong> {{veiculo.descricao_completa}}</p>
    <p style="margin: 5px 0;"><strong>Valor Total:</strong> {{contrato.valor_total}}</p>
</div>
HTML;
    }

    private function getReturnReminderEmailTemplate(): string
    {
        return <<<'HTML'
<h2 style="color: #f59e0b; margin: 0 0 20px 0;">⏰ Lembrete de Devolução</h2>

<p>Olá, <strong>{{cliente.primeiro_nome}}</strong>!</p>

<p>Este é um lembrete amigável sobre a devolução do veículo:</p>

<div style="background: #fffbeb; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b;">
    <p style="margin: 5px 0;"><strong>🚗 Veículo:</strong> {{veiculo.descricao_completa}}</p>
    <p style="margin: 5px 0;"><strong>📅 Data:</strong> {{locacao.data_devolucao}}</p>
    <p style="margin: 5px 0;"><strong>🕐 Horário:</strong> {{locacao.hora_devolucao}}</p>
    <p style="margin: 5px 0;"><strong>📍 Local:</strong> {{locacao.local_devolucao}}</p>
</div>

<p>Lembre-se de devolver o veículo com o mesmo nível de combustível da retirada.</p>

<p>Dúvidas? Entre em contato conosco!</p>
HTML;
    }

    private function getPaymentReminderEmailTemplate(): string
    {
        return <<<'HTML'
<h2 style="color: #3b82f6; margin: 0 0 20px 0;">💳 Lembrete de Pagamento</h2>

<p>Olá, <strong>{{cliente.primeiro_nome}}</strong>!</p>

<p>Lembramos que sua fatura está próxima do vencimento:</p>

<div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0;">
    <p style="margin: 5px 0;"><strong>Fatura:</strong> #{{fatura.numero}}</p>
    <p style="margin: 5px 0;"><strong>Valor:</strong> {{fatura.valor}}</p>
    <p style="margin: 5px 0;"><strong>Vencimento:</strong> {{fatura.data_vencimento}}</p>
</div>

<p style="text-align: center; margin: 25px 0;">
    <a href="{{fatura.link_boleto}}" style="display: inline-block; background: #3b82f6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px;">Pagar Agora</a>
</p>
HTML;
    }

    private function getInvoiceGeneratedEmailTemplate(): string
    {
        return <<<'HTML'
<h2 style="color: #1a56db; margin: 0 0 20px 0;">📄 Nova Fatura</h2>

<p>Olá, <strong>{{cliente.nome}}</strong>!</p>

<p>Uma nova fatura foi gerada para você:</p>

<div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0;">
    <p style="margin: 5px 0;"><strong>Número:</strong> #{{fatura.numero}}</p>
    <p style="margin: 5px 0;"><strong>Valor:</strong> {{fatura.valor}}</p>
    <p style="margin: 5px 0;"><strong>Vencimento:</strong> {{fatura.data_vencimento}}</p>
</div>

<p style="text-align: center; margin: 25px 0;">
    <a href="{{fatura.link_boleto}}" style="display: inline-block; background: #059669; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px;">Visualizar Fatura</a>
</p>
HTML;
    }

    private function getOverdueNoticeEmailTemplate(): string
    {
        return <<<'HTML'
<h2 style="color: #dc2626; margin: 0 0 20px 0;">⚠️ Fatura em Atraso</h2>

<p>Olá, <strong>{{cliente.nome}}</strong>.</p>

<p>Identificamos que a fatura abaixo encontra-se em atraso:</p>

<div style="background: #fef2f2; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #dc2626;">
    <p style="margin: 5px 0;"><strong>Fatura:</strong> #{{fatura.numero}}</p>
    <p style="margin: 5px 0;"><strong>Valor:</strong> {{fatura.valor}}</p>
    <p style="margin: 5px 0;"><strong>Vencimento:</strong> {{fatura.data_vencimento}}</p>
    <p style="margin: 5px 0;"><strong>Dias em atraso:</strong> {{fatura.dias_atraso}}</p>
</div>

<p>Regularize sua situação para evitar a incidência de juros e multas, além de possíveis restrições em futuras locações.</p>

<p style="text-align: center; margin: 25px 0;">
    <a href="{{fatura.link_boleto}}" style="display: inline-block; background: #dc2626; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px;">Pagar Agora</a>
</p>

<p style="font-size: 13px; color: #64748b;">Se você já efetuou o pagamento, por favor desconsidere este aviso.</p>
HTML;
    }

    private function getCnhExpiringEmailTemplate(): string
    {
        return <<<'HTML'
<h2 style="color: #f59e0b; margin: 0 0 20px 0;">⚠️ CNH Próxima do Vencimento</h2>

<p>Olá, <strong>{{cliente.primeiro_nome}}</strong>!</p>

<p>Identificamos que sua CNH está próxima da data de vencimento:</p>

<div style="background: #fffbeb; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b;">
    <p style="margin: 5px 0;"><strong>Número da CNH:</strong> {{cliente.cnh_numero}}</p>
    <p style="margin: 5px 0;"><strong>Data de Vencimento:</strong> {{cliente.cnh_validade}}</p>
</div>

<p>Lembre-se de renovar sua habilitação para continuar alugando veículos conosco sem interrupções.</p>

<p>Após a renovação, não se esqueça de atualizar seus dados cadastrais!</p>
HTML;
    }
};
