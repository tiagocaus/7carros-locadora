<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela role_permissions
 *
 * Tabela pivot para relacionamento N:N entre roles e permissions.
 * Define quais permissões cada função possui.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->create('role_permissions', function ($table) {
            // Chave primária
            $table->id();

            // Foreign keys
            $table->integer('role_id')->unsigned();
            $table->integer('permission_id')->unsigned();

            // Timestamp de criação
            $table->timestamp('created_at')->default('CURRENT_TIMESTAMP');

            // Índices
            $table->index('role_id', 'idx_role_permissions_role');
            $table->index('permission_id', 'idx_role_permissions_permission');

            // Índice composto único para evitar duplicatas
            $table->unique(['role_id', 'permission_id'], 'uk_role_permission');

            // Foreign keys constraints
            $table->foreign('role_id')
                ->on('roles')
                ->references('id')
                ->onDelete('CASCADE')
                ->onUpdate('CASCADE');

            $table->foreign('permission_id')
                ->on('permissions')
                ->references('id')
                ->onDelete('CASCADE')
                ->onUpdate('CASCADE');
        });
    }

    public function down(): void
    {
        $this->drop('role_permissions');
    }
};
