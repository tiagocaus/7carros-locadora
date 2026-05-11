<?php

/**
 * Migration: Atribui permissões de planos_contas às roles do sistema
 *
 * As permissões foram criadas na migration 00228 mas não foram atribuídas às roles.
 * Esta migration corrige isso atribuindo as permissões às roles apropriadas.
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Executa a migration
     */
    public function up(): void
    {
        // Buscar IDs das permissões de planos_contas
        $permissions = $this->db()->table('permissions')
            ->select(['id', '`key` as permission_key'])
            ->whereRaw("`key` LIKE 'planos_contas.%'")
            ->get();

        if (empty($permissions)) {
            return; // Permissões não existem ainda
        }

        // Mapear permissões por key
        $permissionMap = [];
        foreach ($permissions as $p) {
            $permissionMap[$p['permission_key']] = $p['id'];
        }

        // Buscar roles do sistema (chave = '0')
        $roles = $this->db()->table('funcionarios_roles')
            ->select(['id', 'name'])
            ->whereRaw("chave = '0' AND deleted_at IS NULL")
            ->get();

        // Definir quais roles recebem quais permissões
        $rolePermissions = [
            'Proprietário' => ['visualizar', 'criar', 'editar', 'excluir'],
            'Gerente' => ['visualizar', 'criar', 'editar', 'excluir'],
            'Coordenador Administrativo' => ['visualizar'],
        ];

        foreach ($roles as $role) {
            $roleName = $role['name'];

            if (!isset($rolePermissions[$roleName])) {
                continue; // Esta role não recebe permissões de planos_contas
            }

            foreach ($rolePermissions[$roleName] as $action) {
                $permKey = "planos_contas.{$action}";

                if (!isset($permissionMap[$permKey])) {
                    continue; // Permissão não existe
                }

                $permissionId = $permissionMap[$permKey];

                // Verificar se já existe
                $existing = $this->db()->table('funcionarios_role_permissions')
                    ->select(['id'])
                    ->whereRaw('role_id = ? AND permission_id = ?', [$role['id'], $permissionId])
                    ->first();

                if (!$existing) {
                    $this->db()->table('funcionarios_role_permissions')->insert([
                        'role_id' => $role['id'],
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }
    }

    /**
     * Reverte a migration
     */
    public function down(): void
    {
        // Buscar IDs das permissões de planos_contas
        $permissions = $this->db()->table('permissions')
            ->select(['id'])
            ->whereRaw("`key` LIKE 'planos_contas.%'")
            ->get();

        if (empty($permissions)) {
            return;
        }

        $permissionIds = array_column($permissions, 'id');
        $placeholders = implode(',', array_fill(0, count($permissionIds), '?'));

        // Remover atribuições
        $this->db()->table('funcionarios_role_permissions')
            ->whereRaw("permission_id IN ({$placeholders})", $permissionIds)
            ->delete();
    }
};
