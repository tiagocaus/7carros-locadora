<?php

use App\Database\Migration;

/**
 * Migration: Adicionar permissoes para o modulo Templates de Promissoria
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            [
                'key' => 'promissorias_templates.visualizar',
                'name' => 'Visualizar Templates de Promissoria',
                'description' => 'Visualizar modelos de texto de promissorias',
                'module' => 'promissorias'
            ],
            [
                'key' => 'promissorias_templates.editar',
                'name' => 'Editar Templates de Promissoria',
                'description' => 'Editar e customizar modelos de texto de promissorias',
                'module' => 'promissorias'
            ],
        ];

        foreach ($permissions as $permission) {
            // Verificar se ja existe antes de inserir
            $existing = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [$permission['key']])->get();

            if (empty($existing)) {
                $this->db()->table('permissions')->insert($permission);
            }
        }

        // Buscar roles que devem ter acesso
        $rolesToUpdate = [
            'Proprietário' => ['promissorias_templates.visualizar', 'promissorias_templates.editar'],
            'Gerente' => ['promissorias_templates.visualizar'],
        ];

        foreach ($rolesToUpdate as $roleName => $rolePermissions) {
            $role = $this->db()->table('funcionarios_roles')->select(['id'])->whereRaw('name = ?', [$roleName])->get();

            if (!empty($role)) {
                $roleId = $role[0]['id'];

                foreach ($rolePermissions as $key) {
                    $permission = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [$key])->get();

                    if (!empty($permission)) {
                        $permissionId = $permission[0]['id'];

                        // Verificar se ja existe a associacao
                        $existing = $this->db()->table('funcionarios_role_permissions')->select(['id'])->whereRaw('role_id = ? AND permission_id = ?', [$roleId, $permissionId])->get();

                        if (empty($existing)) {
                            $this->db()->table('funcionarios_role_permissions')->insert([
                                'role_id' => $roleId,
                                'permission_id' => $permissionId
                            ]);
                        }
                    }
                }
            }
        }

        echo "  - Permissoes de templates de promissoria criadas.\n";
    }

    public function down(): void
    {
        $permissionKeys = [
            'promissorias_templates.visualizar',
            'promissorias_templates.editar',
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

        echo "  - Permissoes de templates de promissoria removidas.\n";
    }
};
