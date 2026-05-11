<?php

use App\Database\Migration;

/**
 * Migration: Adicionar permissoes para o modulo Matrizes/Filiais
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            [
                'key' => 'matrizes_filiais.visualizar',
                'name' => 'Visualizar Matrizes/Filiais',
                'description' => 'Listar e visualizar matrizes e filiais',
                'module' => 'matrizes_filiais'
            ],
            [
                'key' => 'matrizes_filiais.criar',
                'name' => 'Criar Matrizes/Filiais',
                'description' => 'Adicionar novas matrizes e filiais',
                'module' => 'matrizes_filiais'
            ],
            [
                'key' => 'matrizes_filiais.editar',
                'name' => 'Editar Matrizes/Filiais',
                'description' => 'Modificar matrizes e filiais existentes',
                'module' => 'matrizes_filiais'
            ],
            [
                'key' => 'matrizes_filiais.excluir',
                'name' => 'Excluir Matrizes/Filiais',
                'description' => 'Remover matrizes e filiais do sistema',
                'module' => 'matrizes_filiais'
            ],
        ];

        foreach ($permissions as $permission) {
            // Verificar se ja existe antes de inserir
            $existing = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [$permission['key']])->get();

            if (empty($existing)) {
                $this->db()->table('permissions')->insert($permission);
            }
        }

        // Adicionar permissoes ao role de admin (role_id = 1)
        $adminRoleId = 1;

        // Buscar IDs das permissoes recem criadas
        $permissionKeys = array_column($permissions, 'key');

        foreach ($permissionKeys as $key) {
            $permission = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [$key])->get();

            if (!empty($permission)) {
                $permissionId = $permission[0]['id'];

                // Verificar se ja existe a associacao
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

    public function down(): void
    {
        $permissionKeys = [
            'matrizes_filiais.visualizar',
            'matrizes_filiais.criar',
            'matrizes_filiais.editar',
            'matrizes_filiais.excluir',
        ];

        foreach ($permissionKeys as $key) {
            // Buscar ID da permissao
            $permission = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [$key])->get();

            if (!empty($permission)) {
                $permissionId = $permission[0]['id'];

                // Remover associacoes
                $this->db()->table('role_permissions')->whereRaw('permission_id = ?', [$permissionId])->delete();

                // Remover permissao
                $this->db()->table('permissions')->whereRaw('id = ?', [$permissionId])->delete();
            }
        }
    }
};
