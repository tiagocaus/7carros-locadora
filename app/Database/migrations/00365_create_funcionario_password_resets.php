<?php

/**
 * Migration: cria tabela funcionario_password_resets para redefinicao segura
 * de senha dos usuarios do painel.
 *
 * O fluxo publico passa a enviar link com token de uso unico em vez de trocar
 * a senha imediatamente e enviar senha temporaria por email.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('funcionario_password_resets', function ($table) {
            $table->id();
            $table->string('chave', 100);
            $table->bigInteger('id_funcionario')->unsigned();
            $table->string('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->timestamps();

            $table->index('token_hash');
            $table->index('chave');
            $table->index('id_funcionario');
            $table->index('expires_at');
        });

        $subject = 'Redefinicao de senha - {{empresa.nome_fantasia}}';
        $content = '<h2 style="color:#1a56db;">Redefinir senha</h2>'
            . '<p>Ola, {{funcionario.nome}}!</p>'
            . '<p>Foi solicitada uma redefinicao de senha para o seu acesso ao painel da <strong>{{empresa.nome_fantasia}}</strong>.</p>'
            . '<p>Para definir uma nova senha, clique no link abaixo:</p>'
            . '<p style="margin:20px 0;"><a href="{{outros.reset_url}}" style="background:#1a56db;color:#fff;padding:10px 18px;text-decoration:none;border-radius:4px;display:inline-block;">Redefinir minha senha</a></p>'
            . '<p style="font-size:13px;color:#555;">Ou copie e cole este link no navegador:<br>{{outros.reset_url}}</p>'
            . '<p>Este link expira em {{outros.reset_expira_em}} e pode ser usado apenas uma vez.</p>'
            . '<p>Se voce nao solicitou essa alteracao, ignore este email. Sua senha atual continua valida.</p>';

        $sql = "UPDATE message_templates mt
                JOIN message_template_types mtt ON mtt.id = mt.template_type_id
                SET mt.subject = :subject, mt.content = :content
                WHERE mtt.slug = 'funcionario_nova_senha'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['subject' => $subject, 'content' => $content]);

        $this->execute("
            UPDATE message_template_types
            SET available_variables = '[\"funcionario\", \"empresa\", \"outros\"]',
                description_key = 'templates.types.funcionario_nova_senha_link_desc'
            WHERE slug = 'funcionario_nova_senha'
        ");

        echo "  - funcionario_password_resets criada; template funcionario_nova_senha atualizado para link.\n";
    }

    public function down(): void
    {
        $this->drop('funcionario_password_resets');
        // Nao reverte template para evitar reintroduzir envio de senha por email.
    }
};
