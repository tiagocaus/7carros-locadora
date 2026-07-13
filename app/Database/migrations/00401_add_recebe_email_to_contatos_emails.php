<?php

use App\Database\Migration;

/**
 * Adiciona a preferencia de envio em cada endereco de email de contato.
 *
 * Registros existentes permanecem autorizados para preservar o comportamento
 * anterior. A preferencia e aplicada apenas a contatos de clientes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('contatos_emails')) {
            return;
        }

        $this->addColumnIfNotExists('contatos_emails', 'recebe_email', "ENUM('S','N')", [
            'null' => false,
            'default' => 'S',
            'after' => 'principal',
        ]);

        $this->addIndexIfNotExists(
            'contatos_emails',
            ['chave', 'entidade_tipo', 'entidade_id', 'recebe_email'],
            'idx_contatos_emails_envio'
        );

        if ($this->tableExists('messages_queue')) {
            $this->modifyColumn(
                'messages_queue',
                'status',
                "ENUM('pending','processing','sent','failed','skipped')",
                ['null' => false, 'default' => 'pending']
            );
        }
    }

    public function down(): void
    {
        if (!$this->tableExists('contatos_emails')) {
            return;
        }

        $this->dropIndexIfExists('contatos_emails', 'idx_contatos_emails_envio');
        $this->dropColumnIfExists('contatos_emails', 'recebe_email');

        if ($this->tableExists('messages_queue')) {
            $this->pdo->exec("UPDATE messages_queue SET status = 'failed', error_message = COALESCE(error_message, 'Envio ignorado') WHERE status = 'skipped'");
            $this->modifyColumn(
                'messages_queue',
                'status',
                "ENUM('pending','processing','sent','failed')",
                ['null' => false, 'default' => 'pending']
            );
        }
    }
};
