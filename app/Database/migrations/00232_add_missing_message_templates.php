<?php

use App\Database\Migration;

/**
 * Migration: Adicionar templates faltantes
 *
 * Corrige bug onde alguns templates não foram criados para todos os canais
 * Templates adicionados:
 * - welcome (SMS)
 * - contract_confirmation (WhatsApp, SMS)
 * - invoice_generated (SMS)
 * - cnh_expiring (SMS)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Buscar IDs dos tipos de template
        $types = $this->getTemplateTypes();

        $templates = [
            // welcome - SMS
            [
                'slug' => 'welcome',
                'channel' => 'sms',
                'content' => 'Bem-vindo(a) a {{empresa.nome_fantasia}}! Estamos felizes em te-lo como cliente. Duvidas? {{empresa.telefone}}'
            ],

            // contract_confirmation - WhatsApp
            [
                'slug' => 'contract_confirmation',
                'channel' => 'whatsapp',
                'content' => "📋 *Contrato Confirmado!*\n\nOlá, {{cliente.primeiro_nome}}!\n\nSeu contrato foi gerado com sucesso.\n\n📝 Contrato: #{{contrato.numero}}\n🚗 Veículo: {{veiculo.descricao_completa}}\n📅 Período: {{contrato.data_inicio}} a {{contrato.data_fim}}\n💰 Valor: {{contrato.valor_total}}\n\nDúvidas? Entre em contato!\n*{{empresa.nome_fantasia}}*"
            ],

            // contract_confirmation - SMS
            [
                'slug' => 'contract_confirmation',
                'channel' => 'sms',
                'content' => 'Contrato #{{contrato.numero}} confirmado! Veiculo: {{veiculo.placa}}. Periodo: {{contrato.data_inicio}} a {{contrato.data_fim}}. {{empresa.nome_fantasia}}'
            ],

            // invoice_generated - SMS
            [
                'slug' => 'invoice_generated',
                'channel' => 'sms',
                'content' => 'Fatura #{{fatura.numero}} gerada. Valor: {{fatura.valor}}. Vencimento: {{fatura.data_vencimento}}. {{empresa.nome_fantasia}}'
            ],

            // cnh_expiring - SMS
            [
                'slug' => 'cnh_expiring',
                'channel' => 'sms',
                'content' => 'Atencao: Sua CNH vence em {{cliente.cnh_validade}}. Renove para continuar alugando. {{empresa.nome_fantasia}}'
            ],
        ];

        foreach ($templates as $template) {
            if (!isset($types[$template['slug']])) {
                echo "  - Tipo {$template['slug']} não encontrado, pulando.\n";
                continue;
            }

            $this->insertTemplate(
                $types[$template['slug']],
                'pt_BR',
                $template['channel'],
                $template['content']
            );

            echo "  - Template {$template['slug']} ({$template['channel']}) adicionado.\n";
        }
    }

    public function down(): void
    {
        $templatesRemover = [
            ['slug' => 'welcome', 'channel' => 'sms'],
            ['slug' => 'contract_confirmation', 'channel' => 'whatsapp'],
            ['slug' => 'contract_confirmation', 'channel' => 'sms'],
            ['slug' => 'invoice_generated', 'channel' => 'sms'],
            ['slug' => 'cnh_expiring', 'channel' => 'sms'],
        ];

        foreach ($templatesRemover as $t) {
            $this->execute("
                DELETE mt FROM message_templates mt
                JOIN message_template_types mtt ON mt.template_type_id = mtt.id
                WHERE mtt.slug = '{$t['slug']}'
                AND mt.channel = '{$t['channel']}'
                AND mt.locale = 'pt_BR'
                AND mt.chave = '0'
            ");
            echo "  - Template {$t['slug']} ({$t['channel']}) removido.\n";
        }
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
