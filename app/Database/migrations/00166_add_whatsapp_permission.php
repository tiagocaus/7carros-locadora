<?php

use App\Database\Migration;

/**
 * Migration: Adicionar permissao para o modulo WhatsApp
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            [
                'key' => 'whatsapp.gerenciar',
                'name' => 'Gerenciar WhatsApp',
                'description' => 'Gerenciar conexoes e instancias WhatsApp',
                'module' => 'whatsapp'
            ],
        ];

        foreach ($permissions as $permission) {
            // Verificar se ja existe antes de inserir
            $existing = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [$permission['key']])->get();

            if (empty($existing)) {
                $this->db()->table('permissions')->insert($permission);
            }
        }

        // Buscar role "Proprietario" (role principal da empresa)
        $adminRole = $this->db()->table('funcionarios_roles')->select(['id'])->whereRaw('name = ?', ['Proprietário'])->get();

        if (!empty($adminRole)) {
            $adminRoleId = $adminRole[0]['id'];

            // Buscar IDs das permissoes recem criadas
            $permissionKeys = array_column($permissions, 'key');

            foreach ($permissionKeys as $key) {
                $permission = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [$key])->get();

                if (!empty($permission)) {
                    $permissionId = $permission[0]['id'];

                    // Verificar se ja existe a associacao
                    $existing = $this->db()->table('funcionarios_role_permissions')->select(['id'])->whereRaw('role_id = ? AND permission_id = ?', [$adminRoleId, $permissionId])->get();

                    if (empty($existing)) {
                        $this->db()->table('funcionarios_role_permissions')->insert([
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
            'whatsapp.gerenciar',
        ];

        foreach ($permissionKeys as $key) {
            // Buscar ID da permissao
            $permission = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [$key])->get();

            if (!empty($permission)) {
                $permissionId = $permission[0]['id'];

                // Remover associacoes
                $this->db()->table('funcionarios_role_permissions')->whereRaw('permission_id = ?', [$permissionId])->delete();

                // Remover permissao
                $this->db()->table('permissions')->whereRaw('id = ?', [$permissionId])->delete();
            }
        }
    }
};
