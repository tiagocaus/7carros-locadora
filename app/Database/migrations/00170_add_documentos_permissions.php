<?php

use App\Database\Migration;

/**
 * Migration: Adicionar permissoes para o modulo Documentos
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            [
                'key' => 'documentos.visualizar',
                'name' => 'Visualizar Documentos',
                'description' => 'Visualizar modelos de documentos',
                'module' => 'documentos'
            ],
            [
                'key' => 'documentos.criar',
                'name' => 'Criar Documentos',
                'description' => 'Criar novos modelos de documentos',
                'module' => 'documentos'
            ],
            [
                'key' => 'documentos.editar',
                'name' => 'Editar Documentos',
                'description' => 'Editar modelos de documentos existentes',
                'module' => 'documentos'
            ],
            [
                'key' => 'documentos.excluir',
                'name' => 'Excluir Documentos',
                'description' => 'Excluir modelos de documentos',
                'module' => 'documentos'
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
            'documentos.visualizar',
            'documentos.criar',
            'documentos.editar',
            'documentos.excluir',
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
