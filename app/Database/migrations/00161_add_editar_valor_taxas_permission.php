<?php

/**
 * Migration 00161: Adicionar permissao para editar valor de taxas e servicos
 *
 * Nova permissao:
 * - contratos.editar_valor_taxas: Permite editar o valor das taxas nos contratos
 *
 * Role Proprietario recebe essa permissao por padrao.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permission = $this->getPermission();

        // Inserir permissao (verificando duplicata)
        $existing = $this->db()
            ->table('permissions')
            ->select(['id'])
            ->whereRaw('`key` = ?', [$permission['key']])
            ->first();

        if (!$existing) {
            $this->db()->table('permissions')->insert($permission);
        }

        // Buscar ID da permissao
        $perm = $this->db()
            ->table('permissions')
            ->select(['id'])
            ->whereRaw('`key` = ?', [$permission['key']])
            ->first();

        if (!$perm) {
            return;
        }

        // Atribuir a todas as roles "Proprietario"
        $roles = $this->db()
            ->table('funcionarios_roles')
            ->select(['id'])
            ->whereRaw("name = 'Proprietário' AND deleted_at IS NULL")
            ->get();

        foreach ($roles as $role) {
            // Verificar se ja existe a associacao
            $exists = $this->db()
                ->table('funcionarios_role_permissions')
                ->select(['id'])
                ->whereRaw("role_id = ? AND permission_id = ?", [$role['id'], $perm['id']])
                ->first();

            if (!$exists) {
                $this->db()->table('funcionarios_role_permissions')->insert([
                    'role_id' => $role['id'],
                    'permission_id' => $perm['id']
                ]);
            }
        }
    }

    public function down(): void
    {
        $permission = $this->getPermission();

        $perm = $this->db()
            ->table('permissions')
            ->select(['id'])
            ->whereRaw('`key` = ?', [$permission['key']])
            ->first();

        if ($perm) {
            // Remover associacoes com roles
            $this->db()
                ->table('funcionarios_role_permissions')
                ->whereRaw("permission_id = ?", [$perm['id']])
                ->delete();

            // Remover permissao
            $this->db()
                ->table('permissions')
                ->whereRaw("id = ?", [$perm['id']])
                ->delete();
        }
    }

    private function getPermission(): array
    {
        return [
            'key' => 'contratos.editar_valor_taxas',
            'name' => 'Editar valor de taxas e servicos',
            'description' => 'Permite editar o valor das taxas e servicos nos contratos',
            'module' => 'contratos'
        ];
    }
};
