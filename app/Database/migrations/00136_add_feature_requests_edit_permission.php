<?php

/**
 * Migration: Adicionar permissao para editar pedidos de recursos
 *
 * Essa permissao permite que usuarios do mesmo tenant possam editar
 * pedidos de recursos criados por qualquer usuario do tenant.
 * A role "Proprietario" recebe essa permissao por padrao.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Verificar se a permissao ja existe
        $existing = $this->db()
            ->table('permissions')
            ->select(['id'])
            ->whereRaw('`key` = ?', ['feature_requests.edit_own'])
            ->first();

        // Inserir permissao apenas se nao existir
        if (!$existing) {
            $this->db()->table('permissions')->insert([
                'key' => 'feature_requests.edit_own',
                'name' => 'Editar Pedidos de Recursos',
                'description' => 'Permite editar pedidos de recursos do proprio tenant.',
                'module' => 'feature_requests'
            ]);
        }

        // Buscar ID da permissao
        $permission = $this->db()
            ->table('permissions')
            ->select(['id'])
            ->whereRaw('`key` = ?', ['feature_requests.edit_own'])
            ->first();

        if (!$permission) {
            return;
        }

        // Buscar todas as roles "Proprietario" (sistema e customizadas)
        $roles = $this->db()
            ->table('funcionarios_roles')
            ->select(['id'])
            ->whereRaw("name = 'Proprietário' AND deleted_at IS NULL")
            ->get();

        // Atribuir permissao a cada role Proprietario
        foreach ($roles as $role) {
            // Verificar se ja tem essa permissao
            $existingPerm = $this->db()
                ->table('funcionarios_role_permissions')
                ->select(['id'])
                ->whereRaw('role_id = ? AND permission_id = ?', [$role['id'], $permission['id']])
                ->first();

            if (!$existingPerm) {
                $this->db()->table('funcionarios_role_permissions')->insert([
                    'role_id' => $role['id'],
                    'permission_id' => $permission['id']
                ]);
            }
        }
    }

    public function down(): void
    {
        $permission = $this->db()
            ->table('permissions')
            ->select(['id'])
            ->whereRaw('`key` = ?', ['feature_requests.edit_own'])
            ->first();

        if ($permission) {
            // Remover associacoes com roles
            $this->db()
                ->table('funcionarios_role_permissions')
                ->whereRaw('permission_id = ?', [$permission['id']])
                ->delete();

            // Remover permissao
            $this->db()
                ->table('permissions')
                ->whereRaw('id = ?', [$permission['id']])
                ->delete();
        }
    }
};
