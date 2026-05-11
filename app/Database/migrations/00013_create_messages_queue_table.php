<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela messages_queue
 *
 * Tabela para rastrear mensagens na fila RabbitMQ.
 * Armazena status, tentativas e payload de cada mensagem (email, SMS, WhatsApp).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->create('messages_queue', function ($table) {
            // Chave primária
            $table->id();

            // Chave do tenant (multi-tenancy)
            $table->string('chave', 45);
            
            // Tipo de mensagem: email, sms, whatsapp
            $table->enum('type', ['email', 'sms', 'whatsapp']);

            // Status: pending, processing, sent, failed
            $table->enum('status', ['pending', 'processing', 'sent', 'failed'])->default('pending');

            // Payload JSON com dados da mensagem
            $table->json('payload');

            // Número de tentativas de processamento
            $table->integer('attempts')->default(0);

            // Mensagem de erro (se houver)
            $table->text('error_message')->nullable();

            // Timestamps
            $table->timestamp('created_at')->default('CURRENT_TIMESTAMP');
            $table->datetime('updated_at')->nullable();
            $table->datetime('processed_at')->nullable();

            // Índices
            $table->index('chave', 'idx_messages_queue_chave');
            $table->index('status', 'idx_messages_queue_status');
            $table->index('type', 'idx_messages_queue_type');
            $table->index('created_at', 'idx_messages_queue_created_at');
        });
    }

    public function down(): void
    {
        $this->drop('messages_queue');
    }
};
