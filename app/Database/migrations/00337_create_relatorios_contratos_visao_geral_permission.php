<?php

/**
 * Migration: Permissao do relatorio 5.1 Visao Geral de Locacoes.
 *
 * Cria a permissao, atribui a Proprietario/Gerente, e invalida o cache de
 * permissoes no Redis (`Cache::flush()`).
 */

use App\Core\Cache;
use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = $this->getPermissions();

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

        try { Cache::flush(); } catch (\Throwable $e) {}
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

        try { Cache::flush(); } catch (\Throwable $e) {}
    }

    private function getPermissions(): array
    {
        return [
            [
                'key' => 'relatorios.contratos.visao_geral',
                'name' => 'Relatorio Visao Geral de Locacoes',
                'description' => 'Visualizar relatorio de visao geral de contratos/locacoes',
                'module' => 'relatorios',
            ],
        ];
    }
};
