<?php

use App\Database\Migration;

/**
 * Migration: Corrigir permissoes de templates de promissoria
 *
 * A migration anterior (00221) nao incluia a verificacao de deleted_at
 * ao buscar as roles, o que pode ter impedido a associacao correta.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rolesToUpdate = [
            'Proprietário' => ['promissorias_templates.visualizar', 'promissorias_templates.editar'],
            'Gerente' => ['promissorias_templates.visualizar'],
        ];

        $inserted = 0;

        foreach ($rolesToUpdate as $roleName => $rolePermissions) {
            // Buscar role com verificacao de deleted_at
            $role = $this->db()
                ->table('funcionarios_roles')
                ->select(['id'])
                ->whereRaw("name = ? AND deleted_at IS NULL", [$roleName])
                ->get();

            if (!empty($role)) {
                $roleId = $role[0]['id'];

                foreach ($rolePermissions as $key) {
                    $permission = $this->db()
                        ->table('permissions')
                        ->select(['id'])
                        ->whereRaw('`key` = ?', [$key])
                        ->get();

                    if (!empty($permission)) {
                        $permissionId = $permission[0]['id'];

                        // Verificar se ja existe a associacao
                        $existing = $this->db()
                            ->table('funcionarios_role_permissions')
                            ->select(['id'])
                            ->whereRaw('role_id = ? AND permission_id = ?', [$roleId, $permissionId])
                            ->get();

                        if (empty($existing)) {
                            $this->db()->table('funcionarios_role_permissions')->insert([
                                'role_id' => $roleId,
                                'permission_id' => $permissionId,
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                            $inserted++;
                        }
                    }
                }
            }
        }

        echo "  - Permissoes de templates de promissoria corrigidas ($inserted novas associacoes).\n";
    }

    public function down(): void
    {
        // Nao e necessario reverter, pois a migration 00221 ja tem o down()
        echo "  - Nenhuma acao necessaria no rollback.\n";
    }
};
