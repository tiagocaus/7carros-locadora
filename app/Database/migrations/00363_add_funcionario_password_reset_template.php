<?php

/**
 * Migration: normaliza templates de redefinicao de senha.
 *
 * - Mantem cliente_nova_senha para clientes do site publico.
 * - Cria funcionario_nova_senha para usuarios do painel.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizarClienteNovaSenha();
        $this->criarFuncionarioNovaSenha();
    }

    private function normalizarClienteNovaSenha(): void
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

        $stmt = $this->pdo->prepare($typeSql);
        $stmt->execute([
            'slug' => 'cliente_nova_senha',
            'name_key' => 'templates.types.cliente_nova_senha',
            'description_key' => 'templates.types.cliente_nova_senha_link_desc',
            'category' => 'onboarding',
            'channels' => '["email"]',
            'available_variables' => '["cliente", "empresa", "outros"]',
            'sort_order' => 11,
        ]);

        $typeId = $this->getTypeId('cliente_nova_senha');
        if (!$typeId) {
            echo "  - tipo cliente_nova_senha nao encontrado.\n";
            return;
        }

        $this->upsertTemplate(
            $typeId,
            'pt_BR',
            'email',
            'Redefinicao de senha - {{empresa.nome_fantasia}}',
            '<h2 style="color:#1a56db;">Redefinir senha</h2>'
            . '<p>Ola, {{cliente.primeiro_nome}}!</p>'
            . '<p>Voce solicitou redefinicao de senha no site da <strong>{{empresa.nome_fantasia}}</strong>.</p>'
            . '<p>Para definir uma nova senha, clique no link abaixo:</p>'
            . '<p style="margin:20px 0;"><a href="{{outros.reset_url}}" style="background:#1a56db;color:#fff;padding:10px 18px;text-decoration:none;border-radius:4px;display:inline-block;">Redefinir minha senha</a></p>'
            . '<p style="font-size:13px;color:#555;">Ou copie e cole este link no navegador:<br>{{outros.reset_url}}</p>'
            . '<p>Este link expira em {{outros.reset_expira_em}}.</p>'
            . '<p>Se voce nao solicitou essa alteracao, ignore este email.</p>'
        );

        echo "  - cliente_nova_senha normalizado.\n";
    }

    private function criarFuncionarioNovaSenha(): void
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

        $stmt = $this->pdo->prepare($typeSql);
        $stmt->execute([
            'slug' => 'funcionario_nova_senha',
            'name_key' => 'templates.types.funcionario_nova_senha',
            'description_key' => 'templates.types.funcionario_nova_senha_link_desc',
            'category' => 'onboarding',
            'channels' => '["email"]',
            'available_variables' => '["funcionario", "empresa", "outros"]',
            'sort_order' => 12,
        ]);

        $typeId = $this->getTypeId('funcionario_nova_senha');
        if (!$typeId) {
            echo "  - tipo funcionario_nova_senha nao encontrado.\n";
            return;
        }

        $this->upsertTemplate(
            $typeId,
            'pt_BR',
            'email',
            'Redefinicao de senha - {{empresa.nome_fantasia}}',
            '<h2 style="color:#1a56db;">Redefinir senha</h2>'
            . '<p>Ola, {{funcionario.nome}}!</p>'
            . '<p>Foi solicitada uma redefinicao de senha para o seu acesso ao painel da <strong>{{empresa.nome_fantasia}}</strong>.</p>'
            . '<p>Para definir uma nova senha, clique no link abaixo:</p>'
            . '<p style="margin:20px 0;"><a href="{{outros.reset_url}}" style="background:#1a56db;color:#fff;padding:10px 18px;text-decoration:none;border-radius:4px;display:inline-block;">Redefinir minha senha</a></p>'
            . '<p style="font-size:13px;color:#555;">Ou copie e cole este link no navegador:<br>{{outros.reset_url}}</p>'
            . '<p>Este link expira em {{outros.reset_expira_em}} e pode ser usado apenas uma vez.</p>'
            . '<p>Se voce nao solicitou essa alteracao, ignore este email. Sua senha atual continua valida.</p>'
        );

        echo "  - funcionario_nova_senha criado.\n";
    }

    private function getTypeId(string $slug): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM message_template_types WHERE slug = ?');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) ($row['id'] ?? 0);
    }

    private function upsertTemplate(
        int $typeId,
        string $locale,
        string $channel,
        ?string $subject,
        string $content
    ): void {
        $sql = "INSERT INTO message_templates
                (chave, template_type_id, locale, channel, subject, content, is_active, created_at)
                VALUES ('0', :type_id, :locale, :channel, :subject, :content, 1, NOW())
                ON DUPLICATE KEY UPDATE
                    subject = VALUES(subject),
                    content = VALUES(content),
                    is_active = 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'type_id' => $typeId,
            'locale' => $locale,
            'channel' => $channel,
            'subject' => $subject,
            'content' => $content,
        ]);
    }

    public function down(): void
    {
        $this->execute("
            DELETE mt FROM message_templates mt
            JOIN message_template_types mtt ON mtt.id = mt.template_type_id
            WHERE mtt.slug = 'funcionario_nova_senha'
        ");
        $this->execute("DELETE FROM message_template_types WHERE slug = 'funcionario_nova_senha'");
    }
};
