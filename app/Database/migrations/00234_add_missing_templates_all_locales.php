<?php

use App\Database\Migration;

/**
 * Migration: Adicionar templates faltantes para todos os idiomas
 *
 * Completa os templates SMS e WhatsApp que estavam faltando para:
 * en_US, es_ES, it_IT, pt_PT
 *
 * Templates adicionados:
 * - welcome (SMS) - 4 idiomas
 * - contract_confirmation (WhatsApp) - 4 idiomas
 * - contract_confirmation (SMS) - 4 idiomas
 * - invoice_generated (SMS) - 4 idiomas
 * - cnh_expiring (SMS) - 4 idiomas
 */
return new class extends Migration
{
    public function up(): void
    {
        $types = $this->getTemplateTypes();

        $templates = [
            // ==================== welcome - SMS ====================
            [
                'slug' => 'welcome',
                'channel' => 'sms',
                'locale' => 'en_US',
                'content' => "Welcome to {{empresa.nome_fantasia}}! We're happy to have you as a customer. Questions? {{empresa.telefone}}"
            ],
            [
                'slug' => 'welcome',
                'channel' => 'sms',
                'locale' => 'es_ES',
                'content' => "Bienvenido(a) a {{empresa.nome_fantasia}}! Estamos felices de tenerte como cliente. Dudas? {{empresa.telefone}}"
            ],
            [
                'slug' => 'welcome',
                'channel' => 'sms',
                'locale' => 'it_IT',
                'content' => "Benvenuto/a in {{empresa.nome_fantasia}}! Siamo felici di averti come cliente. Domande? {{empresa.telefone}}"
            ],
            [
                'slug' => 'welcome',
                'channel' => 'sms',
                'locale' => 'pt_PT',
                'content' => "Bem-vindo(a) a {{empresa.nome_fantasia}}! Estamos felizes em te-lo como cliente. Duvidas? {{empresa.telefone}}"
            ],

            // ==================== contract_confirmation - WhatsApp ====================
            [
                'slug' => 'contract_confirmation',
                'channel' => 'whatsapp',
                'locale' => 'en_US',
                'content' => "📋 *Contract Confirmed!*\n\nHello, {{cliente.primeiro_nome}}!\n\nYour contract has been generated.\n\n📝 Contract: #{{contrato.numero}}\n🚗 Vehicle: {{veiculo.descricao_completa}}\n📅 Period: {{contrato.data_inicio}} to {{contrato.data_fim}}\n💰 Total: {{contrato.valor_total}}\n\nQuestions? Contact us!\n*{{empresa.nome_fantasia}}*"
            ],
            [
                'slug' => 'contract_confirmation',
                'channel' => 'whatsapp',
                'locale' => 'es_ES',
                'content' => "📋 *Contrato Confirmado!*\n\nHola, {{cliente.primeiro_nome}}!\n\nSu contrato ha sido generado.\n\n📝 Contrato: #{{contrato.numero}}\n🚗 Vehículo: {{veiculo.descricao_completa}}\n📅 Período: {{contrato.data_inicio}} a {{contrato.data_fim}}\n💰 Total: {{contrato.valor_total}}\n\nDudas? Contáctenos!\n*{{empresa.nome_fantasia}}*"
            ],
            [
                'slug' => 'contract_confirmation',
                'channel' => 'whatsapp',
                'locale' => 'it_IT',
                'content' => "📋 *Contratto Confermato!*\n\nCiao, {{cliente.primeiro_nome}}!\n\nIl tuo contratto è stato generato.\n\n📝 Contratto: #{{contrato.numero}}\n🚗 Veicolo: {{veiculo.descricao_completa}}\n📅 Periodo: {{contrato.data_inicio}} a {{contrato.data_fim}}\n💰 Totale: {{contrato.valor_total}}\n\nDomande? Contattaci!\n*{{empresa.nome_fantasia}}*"
            ],
            [
                'slug' => 'contract_confirmation',
                'channel' => 'whatsapp',
                'locale' => 'pt_PT',
                'content' => "📋 *Contrato Confirmado!*\n\nOlá, {{cliente.primeiro_nome}}!\n\nO seu contrato foi gerado com sucesso.\n\n📝 Contrato: #{{contrato.numero}}\n🚗 Veículo: {{veiculo.descricao_completa}}\n📅 Período: {{contrato.data_inicio}} a {{contrato.data_fim}}\n💰 Valor: {{contrato.valor_total}}\n\nDúvidas? Contacte-nos!\n*{{empresa.nome_fantasia}}*"
            ],

            // ==================== contract_confirmation - SMS ====================
            [
                'slug' => 'contract_confirmation',
                'channel' => 'sms',
                'locale' => 'en_US',
                'content' => "Contract #{{contrato.numero}} confirmed! Vehicle: {{veiculo.placa}}. Period: {{contrato.data_inicio}} to {{contrato.data_fim}}. {{empresa.nome_fantasia}}"
            ],
            [
                'slug' => 'contract_confirmation',
                'channel' => 'sms',
                'locale' => 'es_ES',
                'content' => "Contrato #{{contrato.numero}} confirmado! Vehiculo: {{veiculo.placa}}. Periodo: {{contrato.data_inicio}} a {{contrato.data_fim}}. {{empresa.nome_fantasia}}"
            ],
            [
                'slug' => 'contract_confirmation',
                'channel' => 'sms',
                'locale' => 'it_IT',
                'content' => "Contratto #{{contrato.numero}} confermato! Veicolo: {{veiculo.placa}}. Periodo: {{contrato.data_inicio}} a {{contrato.data_fim}}. {{empresa.nome_fantasia}}"
            ],
            [
                'slug' => 'contract_confirmation',
                'channel' => 'sms',
                'locale' => 'pt_PT',
                'content' => "Contrato #{{contrato.numero}} confirmado! Veiculo: {{veiculo.placa}}. Periodo: {{contrato.data_inicio}} a {{contrato.data_fim}}. {{empresa.nome_fantasia}}"
            ],

            // ==================== invoice_generated - SMS ====================
            [
                'slug' => 'invoice_generated',
                'channel' => 'sms',
                'locale' => 'en_US',
                'content' => "Invoice #{{fatura.numero}} generated. Amount: {{fatura.valor}}. Due: {{fatura.data_vencimento}}. {{empresa.nome_fantasia}}"
            ],
            [
                'slug' => 'invoice_generated',
                'channel' => 'sms',
                'locale' => 'es_ES',
                'content' => "Factura #{{fatura.numero}} generada. Valor: {{fatura.valor}}. Vencimiento: {{fatura.data_vencimento}}. {{empresa.nome_fantasia}}"
            ],
            [
                'slug' => 'invoice_generated',
                'channel' => 'sms',
                'locale' => 'it_IT',
                'content' => "Fattura #{{fatura.numero}} generata. Importo: {{fatura.valor}}. Scadenza: {{fatura.data_vencimento}}. {{empresa.nome_fantasia}}"
            ],
            [
                'slug' => 'invoice_generated',
                'channel' => 'sms',
                'locale' => 'pt_PT',
                'content' => "Fatura #{{fatura.numero}} gerada. Valor: {{fatura.valor}}. Vencimento: {{fatura.data_vencimento}}. {{empresa.nome_fantasia}}"
            ],

            // ==================== cnh_expiring - SMS ====================
            [
                'slug' => 'cnh_expiring',
                'channel' => 'sms',
                'locale' => 'en_US',
                'content' => "Attention: Your license expires on {{cliente.cnh_validade}}. Renew to keep renting. {{empresa.nome_fantasia}}"
            ],
            [
                'slug' => 'cnh_expiring',
                'channel' => 'sms',
                'locale' => 'es_ES',
                'content' => "Atencion: Su licencia vence el {{cliente.cnh_validade}}. Renueve para seguir alquilando. {{empresa.nome_fantasia}}"
            ],
            [
                'slug' => 'cnh_expiring',
                'channel' => 'sms',
                'locale' => 'it_IT',
                'content' => "Attenzione: La tua patente scade il {{cliente.cnh_validade}}. Rinnova per continuare a noleggiare. {{empresa.nome_fantasia}}"
            ],
            [
                'slug' => 'cnh_expiring',
                'channel' => 'sms',
                'locale' => 'pt_PT',
                'content' => "Atencao: A sua carta de conducao expira em {{cliente.cnh_validade}}. Renove para continuar a alugar. {{empresa.nome_fantasia}}"
            ],
        ];

        $count = 0;
        foreach ($templates as $template) {
            if (!isset($types[$template['slug']])) {
                echo "  - Tipo {$template['slug']} não encontrado, pulando.\n";
                continue;
            }

            $this->insertTemplate(
                $types[$template['slug']],
                $template['locale'],
                $template['channel'],
                $template['content']
            );
            $count++;
        }

        echo "  - {$count} templates adicionados.\n";
    }

    public function down(): void
    {
        $templatesRemover = [
            // welcome SMS
            ['slug' => 'welcome', 'channel' => 'sms', 'locale' => 'en_US'],
            ['slug' => 'welcome', 'channel' => 'sms', 'locale' => 'es_ES'],
            ['slug' => 'welcome', 'channel' => 'sms', 'locale' => 'it_IT'],
            ['slug' => 'welcome', 'channel' => 'sms', 'locale' => 'pt_PT'],
            // contract_confirmation WhatsApp
            ['slug' => 'contract_confirmation', 'channel' => 'whatsapp', 'locale' => 'en_US'],
            ['slug' => 'contract_confirmation', 'channel' => 'whatsapp', 'locale' => 'es_ES'],
            ['slug' => 'contract_confirmation', 'channel' => 'whatsapp', 'locale' => 'it_IT'],
            ['slug' => 'contract_confirmation', 'channel' => 'whatsapp', 'locale' => 'pt_PT'],
            // contract_confirmation SMS
            ['slug' => 'contract_confirmation', 'channel' => 'sms', 'locale' => 'en_US'],
            ['slug' => 'contract_confirmation', 'channel' => 'sms', 'locale' => 'es_ES'],
            ['slug' => 'contract_confirmation', 'channel' => 'sms', 'locale' => 'it_IT'],
            ['slug' => 'contract_confirmation', 'channel' => 'sms', 'locale' => 'pt_PT'],
            // invoice_generated SMS
            ['slug' => 'invoice_generated', 'channel' => 'sms', 'locale' => 'en_US'],
            ['slug' => 'invoice_generated', 'channel' => 'sms', 'locale' => 'es_ES'],
            ['slug' => 'invoice_generated', 'channel' => 'sms', 'locale' => 'it_IT'],
            ['slug' => 'invoice_generated', 'channel' => 'sms', 'locale' => 'pt_PT'],
            // cnh_expiring SMS
            ['slug' => 'cnh_expiring', 'channel' => 'sms', 'locale' => 'en_US'],
            ['slug' => 'cnh_expiring', 'channel' => 'sms', 'locale' => 'es_ES'],
            ['slug' => 'cnh_expiring', 'channel' => 'sms', 'locale' => 'it_IT'],
            ['slug' => 'cnh_expiring', 'channel' => 'sms', 'locale' => 'pt_PT'],
        ];

        foreach ($templatesRemover as $t) {
            $this->execute("
                DELETE mt FROM message_templates mt
                JOIN message_template_types mtt ON mt.template_type_id = mtt.id
                WHERE mtt.slug = '{$t['slug']}'
                AND mt.channel = '{$t['channel']}'
                AND mt.locale = '{$t['locale']}'
                AND mt.chave = '0'
            ");
        }
        echo "  - " . count($templatesRemover) . " templates removidos.\n";
    }

    private function getTemplateTypes(): array
    {
        $stmt = $this->pdo->query("SELECT id, slug FROM message_template_types");
        $types = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $types[$row['slug']] = (int) $row['id'];
        }
        return $types;
    }

    private function insertTemplate(int $typeId, string $locale, string $channel, string $content): void
    {
        $sql = "INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, is_active, created_at)
                VALUES ('0', :type_id, :locale, :channel, NULL, :content, 1, NOW())
                ON DUPLICATE KEY UPDATE content = VALUES(content)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'type_id' => $typeId,
            'locale' => $locale,
            'channel' => $channel,
            'content' => $content
        ]);
    }
};
