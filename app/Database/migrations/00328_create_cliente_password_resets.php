<?php

/**
 * Migration: cria tabela cliente_password_resets (token one-time para reset
 * de senha de clientes do site publico) e atualiza o template
 * `cliente_nova_senha` para enviar link seguro em vez de senha em texto plano.
 *
 * Motivacao: antes a rota clienteSenhaReset gerava senha aleatoria e mandava
 * por email. Se o email fosse interceptado, a conta ficava comprometida (sem
 * expiracao). Agora o email contem apenas um link com token de uso unico,
 * valido por 60 minutos.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('cliente_password_resets', function ($table) {
            $table->id();
            $table->string('chave', 100);
            $table->bigInteger('id_cliente')->unsigned();
            $table->string('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->timestamps();

            $table->index('token_hash');
            $table->index('chave');
            $table->index('id_cliente');
            $table->index('expires_at');
        });

        // Atualiza template cliente_nova_senha: troca {{outros.nova_senha}} por link
        $subject = 'Redefinicao de senha — {{empresa.nome_fantasia}}';
        $content = '<h2 style="color:#1a56db;">Redefinir senha</h2>'
            . '<p>Ola, {{cliente.primeiro_nome}}!</p>'
            . '<p>Voce solicitou redefinicao de senha no site da <strong>{{empresa.nome_fantasia}}</strong>.</p>'
            . '<p>Para definir uma nova senha, clique no link abaixo:</p>'
            . '<p style="margin:20px 0;"><a href="{{outros.reset_url}}" style="background:#1a56db;color:#fff;padding:10px 18px;text-decoration:none;border-radius:4px;display:inline-block;">Redefinir minha senha</a></p>'
            . '<p style="font-size:13px;color:#555;">Ou copie e cole este endereco no navegador:<br><span style="font-family:monospace;">{{outros.reset_url}}</span></p>'
            . '<p style="font-size:13px;color:#555;">Este link expira em {{outros.reset_expira_em}} e pode ser usado apenas uma vez.</p>'
            . '<p>Se voce nao solicitou essa alteracao, ignore este email — sua senha atual continua valida.</p>';

        $sql = "UPDATE message_templates mt
                JOIN message_template_types mtt ON mtt.id = mt.template_type_id
                SET mt.subject = :subject, mt.content = :content
                WHERE mtt.slug = 'cliente_nova_senha'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['subject' => $subject, 'content' => $content]);

        // Atualiza variaveis disponiveis no tipo
        $this->execute("
            UPDATE message_template_types
            SET available_variables = '[\"cliente\", \"empresa\", \"outros\"]',
                description_key = 'templates.types.cliente_nova_senha_link_desc'
            WHERE slug = 'cliente_nova_senha'
        ");

        echo "  - cliente_password_resets criada; template cliente_nova_senha atualizado para link.\n";
    }

    public function down(): void
    {
        $this->drop('cliente_password_resets');
        // Nao reverte template para manter consistencia com o fluxo atual.
    }
};
