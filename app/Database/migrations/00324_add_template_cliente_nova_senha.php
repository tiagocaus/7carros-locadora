<?php

/**
 * Migration: adiciona tipo de template `cliente_nova_senha`.
 *
 * Enviado ao cliente quando ele usa "Esqueci minha senha" no site publico.
 * Contem a senha em texto claro (10 chars seguros) para o cliente poder
 * logar; o cliente pode trocar depois.
 *
 * Canais: apenas email (nao enviar senha por SMS/WhatsApp).
 * Variaveis: cliente (nome, primeiro_nome, email), empresa, outros.nova_senha.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $sql = "INSERT INTO message_template_types
                (slug, name_key, description_key, category, channels, available_variables, sort_order, is_active)
                VALUES (:slug, :name_key, :description_key, :category, :channels, :available_variables, :sort_order, 1)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'slug' => 'cliente_nova_senha',
            'name_key' => 'templates.types.cliente_nova_senha',
            'description_key' => 'templates.types.cliente_nova_senha_desc',
            'category' => 'onboarding',
            'channels' => '["email"]',
            'available_variables' => '["cliente", "empresa", "outros"]',
            'sort_order' => 11,
        ]);

        $row = $this->pdo->query("SELECT id FROM message_template_types WHERE slug = 'cliente_nova_senha'")->fetch(\PDO::FETCH_ASSOC);
        $typeId = (int) ($row['id'] ?? 0);
        if (!$typeId) return;

        $subject = 'Sua nova senha de acesso — {{empresa.nome_fantasia}}';
        $content = '<h2 style="color:#1a56db;">Nova senha gerada</h2>'
            . '<p>Ola, {{cliente.primeiro_nome}}!</p>'
            . '<p>Voce solicitou uma nova senha no site da <strong>{{empresa.nome_fantasia}}</strong>.</p>'
            . '<p>Sua nova senha de acesso eh: <strong style="font-family:monospace; font-size:16px;">{{outros.nova_senha}}</strong></p>'
            . '<p>Recomendamos trocar a senha apos o primeiro acesso.</p>'
            . '<p>Se voce nao solicitou essa alteracao, entre em contato com a {{empresa.nome_fantasia}} imediatamente.</p>';

        $insSql = "INSERT INTO message_templates
                   (chave, template_type_id, locale, channel, subject, content, is_active, created_at)
                   VALUES ('0', :type_id, 'pt_BR', 'email', :subject, :content, 1, NOW())
                   ON DUPLICATE KEY UPDATE content = VALUES(content), subject = VALUES(subject)";
        $ins = $this->pdo->prepare($insSql);
        $ins->execute(['type_id' => $typeId, 'subject' => $subject, 'content' => $content]);
        echo "  - template cliente_nova_senha (email) criado.\n";
    }

    public function down(): void
    {
        $this->execute("
            DELETE mt FROM message_templates mt
            JOIN message_template_types mtt ON mtt.id = mt.template_type_id
            WHERE mtt.slug = 'cliente_nova_senha'
        ");
        $this->execute("DELETE FROM message_template_types WHERE slug = 'cliente_nova_senha'");
    }
};
