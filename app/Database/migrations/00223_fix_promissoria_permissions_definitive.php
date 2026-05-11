<?php

use App\Database\Migration;

/**
 * Migration: Correcao definitiva das permissoes de templates de promissoria
 *
 * Usa INSERT IGNORE com subquery para garantir que as associacoes sejam criadas
 * mesmo se as migrations anteriores (00221 e 00222) falharam silenciosamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Associacoes a garantir
        $associations = [
            ['Proprietário', 'promissorias_templates.visualizar'],
            ['Proprietário', 'promissorias_templates.editar'],
            ['Gerente', 'promissorias_templates.visualizar'],
        ];

        $inserted = 0;

        foreach ($associations as [$roleName, $permissionKey]) {
            // Buscar role_id
            $role = $this->db()
                ->table('funcionarios_roles')
                ->select(['id'])
                ->whereRaw("name = ? AND deleted_at IS NULL", [$roleName])
                ->get();

            if (empty($role)) {
                echo "    - Role '$roleName' nao encontrada.\n";
                continue;
            }

            $roleId = $role[0]['id'];

            // Buscar permission_id
            $permission = $this->db()
                ->table('permissions')
                ->select(['id'])
                ->whereRaw("`key` = ?", [$permissionKey])
                ->get();

            if (empty($permission)) {
                echo "    - Permissao '$permissionKey' nao encontrada.\n";
                continue;
            }

            $permissionId = $permission[0]['id'];

            // Verificar se associacao ja existe
            $existing = $this->db()
                ->table('funcionarios_role_permissions')
                ->select(['id'])
                ->whereRaw("role_id = ? AND permission_id = ?", [$roleId, $permissionId])
                ->get();

            if (empty($existing)) {
                // Inserir nova associacao
                $this->db()->table('funcionarios_role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $inserted++;
                echo "    - Associacao criada: $roleName -> $permissionKey\n";
            }
        }

        echo "  - Permissoes de templates de promissoria garantidas ($inserted novas associacoes).\n";
    }

    public function down(): void
    {
        echo "  - Nenhuma acao no rollback.\n";
    }
};
