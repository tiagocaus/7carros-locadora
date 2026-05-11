<?php

/**
 * Migration: Atribuir permissão "Listar Todas as Empresas" ao Proprietário
 *
 * Adiciona automaticamente a permissão empresas.listar_todas à role "Proprietário",
 * permitindo que proprietários vejam todas as filiais no dropdown de funcionários.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Buscar ID da permissão
        $permission = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', ['empresas.listar_todas'])->first();

        if (!$permission) {
            return;
        }

        $permissionId = $permission['id'];

        // Buscar todas as roles "Proprietário" (sistema e customizadas)
        $roles = $this->db()->table('funcionarios_roles')->select(['id'])->whereRaw("name = 'Proprietário' AND deleted_at IS NULL")->get();

        // Atribuir permissao a cada role (idempotente: pula se ja existe)
        foreach ($roles as $role) {
            $exists = $this->db()->table('funcionarios_role_permissions')
                ->whereRaw('role_id = ? AND permission_id = ?', [$role['id'], $permissionId])
                ->exists();

            if (!$exists) {
                $this->db()->table('funcionarios_role_permissions')->insert([
                    'role_id' => $role['id'],
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Buscar ID da permissão
        $permission = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', ['empresas.listar_todas'])->first();

        if (!$permission) {
            return;
        }

        // Buscar roles "Proprietário"
        $roles = $this->db()->table('funcionarios_roles')->select(['id'])->whereRaw("name = 'Proprietário'")->get();

        // Remover permissão de cada role
        foreach ($roles as $role) {
            $this->db()->table('funcionarios_role_permissions')
                ->whereRaw('role_id = ? AND permission_id = ?', [$role['id'], $permission['id']])
                ->delete();
        }
    }
};
