<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela roles
 *
 * Tabela para armazenar as funções/papéis do sistema (ex: Gerente, Atendente).
 * Cada funcionário terá uma role associada através de usuarios.role_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->create('roles', function ($table) {
            // Chave primária
            $table->id();

            // Chave do tenant (multi-tenancy)
            $table->string('chave', 45);

            // Nome da função (ex: "Gerente", "Atendente", "Vendedor")
            $table->string('name', 100)->unique();

            // Descrição detalhada da função
            $table->text('description')->nullable();

            // Timestamps
            $table->timestamp('created_at')->default('CURRENT_TIMESTAMP');
            $table->datetime('updated_at')->nullable();

            // Soft delete
            $table->datetime('deleted_at')->nullable();

            // Índices
            $table->index('chave', 'idx_roles_chave');
            $table->index('deleted_at', 'idx_roles_deleted');
        });
    }

    public function down(): void
    {
        $this->drop('roles');
    }
};
