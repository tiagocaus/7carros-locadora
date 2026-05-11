<?php

/**
 * Migration: Permissao do relatorio 7.2 Faturas Por Veiculo.
 *
 * Cria a permissao, atribui a Proprietario/Gerente, e invalida o cache de
 * permissoes no Redis (`Cache::flush()`) — assim usuarios logados pegam a
 * nova permissao sem precisar fazer logout/login.
 */

use App\Core\Cache;
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

        // 3. Invalidar cache de permissoes no Redis para usuarios pegarem a
        //    nova permissao sem precisar de logout/login.
        try {
            Cache::flush();
        } catch (\Throwable $e) {
            // Cache pode estar indisponivel (Redis offline) — nao bloquear migration.
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

        try {
            Cache::flush();
        } catch (\Throwable $e) {
            // Idem.
        }
    }

    private function getPermissions(): array
    {
        return [
            [
                'key' => 'relatorios.faturas.por_veiculo',
                'name' => 'Relatorio Faturas Por Veiculo',
                'description' => 'Visualizar relatorio de faturas agrupadas por veiculo',
                'module' => 'relatorios',
            ],
        ];
    }
};
