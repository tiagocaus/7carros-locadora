<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela permissions
 *
 * Tabela para armazenar todas as permissões disponíveis no sistema.
 * Permissões são associadas a roles (funções) através da tabela role_permissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->create('permissions', function ($table) {
            // Chave primária
            $table->id();

            // Identificador único da permissão (ex: "clientes.excluir")
            $table->string('key', 100)->unique();

            // Nome legível da permissão (ex: "Excluir Clientes")
            $table->string('name', 150);

            // Descrição detalhada da permissão
            $table->text('description')->nullable();

            // Módulo/recurso ao qual a permissão pertence (ex: "clientes", "veiculos")
            $table->string('module', 50);

            // Timestamps
            $table->timestamp('created_at')->default('CURRENT_TIMESTAMP');
            $table->datetime('updated_at')->nullable();

            // Índices
            $table->index('module', 'idx_permissions_module');
        });
    }

    public function down(): void
    {
        $this->drop('permissions');
    }
};
