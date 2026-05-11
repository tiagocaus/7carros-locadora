<?php

use App\Database\Migration;

/**
 * Migration: Corrigir encoding de emojis nos templates
 *
 * Emojis de 4 bytes (👋, 📞, 📋, etc) foram corrompidos durante inserções
 * anteriores porque a conexão não usava SET NAMES utf8mb4.
 *
 * Esta migration corrige os templates afetados.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Garantir encoding correto
        $this->pdo->exec("SET NAMES utf8mb4");

        // Buscar IDs dos tipos de template
        $types = $this->getTemplateTypes();

        // 1. Corrigir templates WhatsApp pt_BR
        echo "  - Corrigindo templates WhatsApp pt_BR...\n";
        $this->fixWhatsAppTemplatesPtBr($types);

        // 2. Corrigir templates i18n (recarregar do arquivo SQL)
        echo "  - Corrigindo templates i18n...\n";
        $this->fixI18nTemplates();

        echo "  - Emojis corrigidos com sucesso!\n";
    }

    public function down(): void
    {
        // Não é necessário reverter - os dados corretos são melhores que os corrompidos
        echo "  - Rollback não necessário.\n";
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
     * Corrige templates WhatsApp em pt_BR
     */
    private function fixWhatsAppTemplatesPtBr(array $types): void
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

            $stmt = $this->pdo->prepare("
                UPDATE message_templates
                SET content = ?, updated_at = NOW()
                WHERE chave = '0'
                  AND template_type_id = ?
                  AND locale = 'pt_BR'
                  AND channel = 'whatsapp'
            ");
            $stmt->execute([$content, $types[$slug]]);
        }
    }

    /**
     * Corrige templates i18n recarregando do arquivo SQL
     */
    private function fixI18nTemplates(): void
    {
        $seedFile = __DIR__ . '/../seeds/message_templates_i18n.sql';

        if (!file_exists($seedFile)) {
            echo "    AVISO: Arquivo de seeds i18n não encontrado.\n";
            return;
        }

        // Primeiro, deletar os templates i18n existentes (que estão corrompidos)
        $locales = ['en_US', 'es_ES', 'it_IT', 'pt_PT'];
        foreach ($locales as $locale) {
            $this->pdo->exec("DELETE FROM message_templates WHERE chave = '0' AND locale = '{$locale}'");
        }

        // Recarregar do arquivo SQL
        $sql = file_get_contents($seedFile);

        // Remove comentários
        $sql = preg_replace('/^--.*$/m', '', $sql);

        // Divide em statements
        $statements = $this->splitSqlStatements($sql);

        $count = 0;
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement)) {
                continue;
            }

            try {
                $this->pdo->exec($statement);
                $count++;
            } catch (\PDOException $e) {
                // Ignora erros de duplicidade
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    echo "    Erro: " . $e->getMessage() . "\n";
                }
            }
        }

        echo "    {$count} templates i18n recarregados.\n";
    }

    /**
     * Divide SQL em statements individuais, respeitando strings
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            // Detecta início/fim de string
            if (($char === "'" || $char === '"') && ($i === 0 || $sql[$i - 1] !== '\\')) {
                if (!$inString) {
                    $inString = true;
                    $stringChar = $char;
                } elseif ($char === $stringChar) {
                    $inString = false;
                }
            }

            // Encontrou ponto e vírgula fora de string
            if ($char === ';' && !$inString) {
                $statements[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        // Adiciona o último statement se houver
        if (trim($current) !== '') {
            $statements[] = $current;
        }

        return $statements;
    }
};
