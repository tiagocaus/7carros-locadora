<?php

/**
 * Migration: Criar permissao checklists.criar
 *
 * Permite controlar quem pode criar novos checklists digitais.
 * Atribui automaticamente a roles Proprietario e Gerente.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permission = $this->getPermission();

        // 1. Inserir permissao (com check de duplicata)
        $existing = $this->db()
            ->table('permissions')
            ->select(['id'])
            ->whereRaw('`key` = ?', [$permission['key']])
            ->first();

        if (!$existing) {
            $this->db()->table('permissions')->insert($permission);
        }

        // 2. Atribuir a Proprietario e Gerente
        $roleNames = ['Proprietário', 'Gerente'];

        foreach ($roleNames as $roleName) {
            $roles = $this->db()
                ->table('funcionarios_roles')
                ->select(['id'])
                ->whereRaw("name = ? AND deleted_at IS NULL", [$roleName])
                ->get();

            foreach ($roles as $role) {
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

    public function down(): void
    {
        $permission = $this->getPermission();

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

    private function getPermission(): array
    {
        return [
            'key' => 'checklists.criar',
            'name' => 'Criar Checklist Digital',
            'description' => 'Criar novos checklists digitais via dispositivo móvel',
            'module' => 'checklists',
        ];
    }
};
