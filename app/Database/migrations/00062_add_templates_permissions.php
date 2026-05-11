<?php

use App\Database\Migration;

/**
 * Migration: Adicionar permissões para o módulo Templates de Mensagem
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            [
                'key' => 'templates.visualizar',
                'name' => 'Visualizar Templates',
                'description' => 'Listar e visualizar templates de mensagem',
                'module' => 'templates'
            ],
            [
                'key' => 'templates.editar',
                'name' => 'Editar Templates',
                'description' => 'Editar e customizar templates de mensagem',
                'module' => 'templates'
            ],
        ];

        foreach ($permissions as $permission) {
            // Verificar se já existe antes de inserir
            $existing = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [$permission['key']])->get();

            if (empty($existing)) {
                $this->db()->table('permissions')->insert($permission);
            }
        }

        // Buscar role "Proprietário" (role principal da empresa)
        $adminRole = $this->db()->table('roles')->select(['id'])->whereRaw('name = ?', ['Proprietário'])->get();

        if (!empty($adminRole)) {
            $adminRoleId = $adminRole[0]['id'];

            // Buscar IDs das permissões recém criadas
            $permissionKeys = array_column($permissions, 'key');

            foreach ($permissionKeys as $key) {
                $permission = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [$key])->get();

                if (!empty($permission)) {
                    $permissionId = $permission[0]['id'];

                    // Verificar se já existe a associação
                    $existing = $this->db()->table('role_permissions')->select(['id'])->whereRaw('role_id = ? AND permission_id = ?', [$adminRoleId, $permissionId])->get();

                    if (empty($existing)) {
                        $this->db()->table('role_permissions')->insert([
                            'role_id' => $adminRoleId,
                            'permission_id' => $permissionId
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $permissionKeys = [
            'templates.visualizar',
            'templates.editar',
        ];

        foreach ($permissionKeys as $key) {
            // Buscar ID da permissão
            $permission = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [$key])->get();

            if (!empty($permission)) {
                $permissionId = $permission[0]['id'];

                // Remover associações
                $this->db()->table('role_permissions')->whereRaw('permission_id = ?', [$permissionId])->delete();

                // Remover permissão
                $this->db()->table('permissions')->whereRaw('id = ?', [$permissionId])->delete();
            }
        }
    }
};
