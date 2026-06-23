<?php

/**
 * Migration 00382: Adicionar permissao dedicada para substituicao de veiculo em locacoes.
 */

use App\Core\Cache;
use App\Database\Migration;

return new class extends Migration
{
    private const PERMISSION = [
        'key' => 'locacoes.substituir',
        'name' => 'Substituir Veiculo da Locacao',
        'description' => 'Substituir veiculo vinculado a uma locacao aberta',
        'module' => 'locacoes',
    ];

    private const ROLE_NAMES = [
        'Proprietario',
        'Proprietário',
        'Gerente',
        'Coordenador Administrativo',
        'Atendente',
        'Antendente',
    ];

    public function up(): void
    {
        $permissionId = $this->ensurePermission();

        foreach ($this->loadRoleIds() as $roleId) {
            $exists = $this->db()
                ->table('funcionarios_role_permissions')
                ->select(['id'])
                ->whereRaw('role_id = ? AND permission_id = ?', [$roleId, $permissionId])
                ->first();

            if (!$exists) {
                $this->db()->table('funcionarios_role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        $this->flushCache();
    }

    public function down(): void
    {
        $permission = $this->db()
            ->table('permissions')
            ->select(['id'])
            ->whereRaw('`key` = ?', [self::PERMISSION['key']])
            ->first();

        if (!$permission) {
            return;
        }

        $this->db()
            ->table('funcionarios_role_permissions')
            ->whereRaw('permission_id = ?', [$permission['id']])
            ->delete();

        $this->db()
            ->table('permissions')
            ->whereRaw('id = ?', [$permission['id']])
            ->delete();

        $this->flushCache();
    }

    private function ensurePermission(): int
    {
        $permission = $this->db()
            ->table('permissions')
            ->select(['id'])
            ->whereRaw('`key` = ?', [self::PERMISSION['key']])
            ->first();

        if ($permission) {
            return (int) $permission['id'];
        }

        return (int) $this->db()->table('permissions')->insert(self::PERMISSION);
    }

    private function loadRoleIds(): array
    {
        $placeholders = implode(', ', array_fill(0, count(self::ROLE_NAMES), '?'));

        $roles = $this->db()
            ->table('funcionarios_roles')
            ->select(['id'])
            ->whereRaw("name IN ({$placeholders})", self::ROLE_NAMES)
            ->get();

        return array_map(static fn(array $role): int => (int) $role['id'], $roles);
    }

    private function flushCache(): void
    {
        try {
            Cache::flush();
        } catch (\Throwable $e) {
            // Cache indisponivel nao deve impedir a migration de permissao.
        }
    }
};
