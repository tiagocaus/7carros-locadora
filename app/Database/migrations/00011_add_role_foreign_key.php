<?php

use App\Database\Migration;

/**
 * Migration: Adicionar foreign key em funcionarios.role_id
 *
 * Adiciona constraint de foreign key para garantir integridade referencial
 * entre funcionarios.role_id e roles.id.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->table('funcionarios', function ($table) {
            // Adicionar foreign key constraint
            $table->foreign('role_id')
                ->on('roles')
                ->references('id')
                ->onDelete('SET NULL')  // Se role for deletada, setar NULL
                ->onUpdate('CASCADE');  // Se ID da role mudar, atualizar
        });
    }

    public function down(): void
    {
        $this->table('funcionarios', function ($table) {
            $table->dropForeignKey('role_id');
        });
    }
};
