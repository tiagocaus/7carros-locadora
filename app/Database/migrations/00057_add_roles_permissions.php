<?php

use App\Database\Migration;

/**
 * Migration: Adicionar permissões para o módulo Roles/Funções
 *
 * Corrige o problema onde usuários Proprietários não conseguem acessar
 * a funcionalidade de gerenciar funções porque as permissões roles.*
 * não existiam na tabela permissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            [
                'key' => 'roles.visualizar',
                'name' => 'Visualizar Funções',
                'description' => 'Listar e visualizar funções/roles',
                'module' => 'roles'
            ],
            [
                'key' => 'roles.criar',
                'name' => 'Criar Funções',
                'description' => 'Adicionar novas funções/roles',
                'module' => 'roles'
            ],
            [
                'key' => 'roles.editar',
                'name' => 'Editar Funções',
                'description' => 'Modificar funções/roles existentes',
                'module' => 'roles'
            ],
            [
                'key' => 'roles.excluir',
                'name' => 'Excluir Funções',
                'description' => 'Remover funções/roles do sistema',
                'module' => 'roles'
            ],
        ];

        // 1. Inserir permissões
        foreach ($permissions as $permission) {
            $existing = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [$permission['key']])->get();

            if (empty($existing)) {
                $this->db()->table('permissions')->insert($permission);
            }
        }

        // 2. Associar apenas à role Proprietário
        $this->assignToProprietario($permissions);
    }

    private function assignToProprietario(array $permissions): void
    {
        // Buscar role Proprietário do sistema (chave = '0' indica role de sistema)
        $proprietario = $this->db()->table('roles')->select(['id'])->whereRaw("chave = '0' AND name = ?", ['Proprietário'])->get();

        if (empty($proprietario)) {
            return;
        }

        $roleId = $proprietario[0]['id'];
        $permissionKeys = array_column($permissions, 'key');

        foreach ($permissionKeys as $key) {
            $permission = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [$key])->get();

            if (!empty($permission)) {
                $permissionId = $permission[0]['id'];

                $existing = $this->db()->table('role_permissions')->select(['id'])->whereRaw('role_id = ? AND permission_id = ?', [$roleId, $permissionId])->get();

                if (empty($existing)) {
                    $this->db()->table('role_permissions')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $permissionKeys = [
            'roles.visualizar',
            'roles.criar',
            'roles.editar',
            'roles.excluir',
        ];

        foreach ($permissionKeys as $key) {
            $permission = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [$key])->get();

            if (!empty($permission)) {
                $permissionId = $permission[0]['id'];
                $this->db()->table('role_permissions')->whereRaw('permission_id = ?', [$permissionId])->delete();
                $this->db()->table('permissions')->whereRaw('id = ?', [$permissionId])->delete();
            }
        }
    }
};
