<?php

use App\Database\Migration;

/**
 * Migration: Atualizar templates de email para conter apenas conteúdo
 *
 * Remove estrutura HTML completa dos templates de email padrão,
 * deixando apenas o conteúdo que será envolvido pelo layout base.
 *
 * O layout base (app/Views/emails/layout.php) é aplicado automaticamente
 * pelo MessageTemplateService::render() no momento do envio.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Buscar IDs dos tipos de template
        $types = $this->getTemplateTypes();

        // Atualizar cada template de email
        $this->updateEmailTemplates($types);

        echo "  - Templates de email atualizados para usar layout base.\n";
    }

    public function down(): void
    {
        // Não é prático reverter pois os templates antigos eram muito verbosos
        echo "  - Rollback não implementado (templates precisariam ser recriados manualmente).\n";
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
     * Atualiza templates de email existentes
     */
    private function updateEmailTemplates(array $types): void
    {
        $templates = [
            'welcome' => $this->getWelcomeEmailTemplate(),
            'rental_confirmation' => $this->getRentalConfirmationEmailTemplate(),
            'contract_confirmation' => $this->getContractConfirmationEmailTemplate(),
            'return_reminder' => $this->getReturnReminderEmailTemplate(),
            'payment_reminder' => $this->getPaymentReminderEmailTemplate(),
            'invoice_generated' => $this->getInvoiceGeneratedEmailTemplate(),
            'overdue_notice' => $this->getOverdueNoticeEmailTemplate(),
            'cnh_expiring' => $this->getCnhExpiringEmailTemplate(),
        ];

        foreach ($templates as $slug => $content) {
            if (!isset($types[$slug])) {
                continue;
            }

            $this->updateTemplate($types[$slug], 'email', $content);
        }
    }

    /**
     * Atualiza um template específico
     */
    private function updateTemplate(int $typeId, string $channel, string $content): void
    {
        $sql = "UPDATE message_templates
                SET content = :content, updated_at = NOW()
                WHERE chave = '0' AND template_type_id = :type_id AND channel = :channel AND locale = 'pt_BR'";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'content' => $content,
            'type_id' => $typeId,
            'channel' => $channel,
        ]);
    }

    // ========== Templates de Email (apenas conteúdo) ==========

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
