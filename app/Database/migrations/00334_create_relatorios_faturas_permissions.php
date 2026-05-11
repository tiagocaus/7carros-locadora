<?php

/**
 * Migration: Permissoes dos relatorios de Faturas (grupo 7).
 *
 * Cria a permissao para 7.1 Vencidas/A Vencer (primeira do grupo)
 * e atribui a Proprietario e Gerente.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = $this->getPermissions();

        // 1. Inserir permissoes (idempotente)
        foreach ($permissions as $permission) {
            $existing = $this->db()
                ->table('permissions')
                ->select(['id'])
                ->whereRaw('`key` = ?', [$permission['key']])
                ->first();

            if (!$existing) {
                $this->db()->table('permissions')->insert($permission);
            }
        }

        // 2. Atribuir a Proprietario e Gerente
        foreach (['Proprietário', 'Gerente'] as $roleName) {
            $roles = $this->db()
                ->table('funcionarios_roles')
                ->select(['id'])
                ->where('name', '=', $roleName)
                ->get();

            foreach ($roles as $role) {
                foreach ($permissions as $permission) {
                    $permRecord = $this->db()
                        ->table('permissions')
                        ->select(['id'])
                        ->whereRaw('`key` = ?', [$permission['key']])
                        ->first();

                    if ($permRecord) {
                        $stmt = $this->pdo->prepare(
                            "INSERT IGNORE INTO funcionarios_role_permissions (role_id, permission_id, created_at)
                             VALUES (?, ?, NOW())"
                        );
                        $stmt->execute([$role['id'], $permRecord['id']]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $permissions = $this->getPermissions();

        foreach ($permissions as $permission) {
            $permRecord = $this->db()
                ->table('permissions')
                ->select(['id'])
                ->whereRaw('`key` = ?', [$permission['key']])
                ->first();

            if ($permRecord) {
                $stmt = $this->pdo->prepare("DELETE FROM funcionarios_role_permissions WHERE permission_id = ?");
                $stmt->execute([$permRecord['id']]);

                $stmt = $this->pdo->prepare("DELETE FROM permissions WHERE id = ?");
                $stmt->execute([$permRecord['id']]);
            }
        }
    }

    private function getPermissions(): array
    {
        return [
            [
                'key' => 'relatorios.faturas.vencidas_a_vencer',
                'name' => 'Relatorio Faturas Vencidas/A Vencer',
                'description' => 'Visualizar relatorio de faturas vencidas e a vencer',
                'module' => 'relatorios',
            ],
        ];
    }
};
