<?php

/**
 * Migration 00372: Liberar operacoes de contrato e locacao para Atendente
 *
 * Normaliza as roles Atendente/Antendente para permitir devolucao, substituicao
 * e edicao operacional sem liberar exclusao nem edicao de valores.
 */

use App\Core\Cache;
use App\Database\Migration;

return new class extends Migration
{
    private const ROLE_NAMES = ['Atendente', 'Antendente'];

    private const PERMISSIONS = [
        [
            'key' => 'contratos.editar',
            'name' => 'Editar Contratos',
            'description' => 'Editar contratos existentes e adicionar veiculos',
            'module' => 'contratos',
        ],
        [
            'key' => 'contratos.devolver',
            'name' => 'Devolver Veiculo do Contrato',
            'description' => 'Registrar devolucao de veiculo e finalizar contrato',
            'module' => 'contratos',
        ],
        [
            'key' => 'contratos.substituir',
            'name' => 'Substituir Veiculo do Contrato',
            'description' => 'Substituir veiculo vinculado ao contrato',
            'module' => 'contratos',
        ],
        [
            'key' => 'locacoes.editar',
            'name' => 'Editar Locacoes',
            'description' => 'Editar locacoes existentes e substituir veiculo',
            'module' => 'locacoes',
        ],
        [
            'key' => 'locacoes.devolucao',
            'name' => 'Registrar Devolucao',
            'description' => 'Registrar devolucao do veiculo na locacao',
            'module' => 'locacoes',
        ],
    ];

    public function up(): void
    {
        $this->ensurePermissionsExist();

        $roleIds = $this->loadAtendenteRoleIds();
        if (empty($roleIds)) {
            $this->flushCache();
            return;
        }

        $permissionIds = $this->loadPermissionIds();

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $this->ensureRolePermission($roleId, $permissionId);
            }
        }

        $this->flushCache();
    }

    public function down(): void
    {
        // No-op: normalizacao de dados sem snapshot anterior.
    }

    private function ensurePermissionsExist(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            $exists = $this->db()
                ->table('permissions')
                ->select(['id'])
                ->whereRaw('`key` = ?', [$permission['key']])
                ->first();

            if (!$exists) {
                $this->db()->table('permissions')->insert($permission);
            }
        }
    }

    private function loadAtendenteRoleIds(): array
    {
        $placeholders = implode(', ', array_fill(0, count(self::ROLE_NAMES), '?'));

        $roles = $this->db()
            ->table('funcionarios_roles')
            ->select(['id'])
            ->whereRaw("name IN ({$placeholders})", self::ROLE_NAMES)
            ->get();

        return array_map(static fn(array $role): int => (int) $role['id'], $roles);
    }

    private function loadPermissionIds(): array
    {
        $keys = array_column(self::PERMISSIONS, 'key');
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));

        $permissions = $this->db()
            ->table('permissions')
            ->select(['id'])
            ->whereRaw("`key` IN ({$placeholders})", $keys)
            ->get();

        return array_map(static fn(array $permission): int => (int) $permission['id'], $permissions);
    }

    private function ensureRolePermission(int $roleId, int $permissionId): void
    {
        $exists = $this->db()
            ->table('funcionarios_role_permissions')
            ->select(['id'])
            ->whereRaw('role_id = ? AND permission_id = ?', [$roleId, $permissionId])
            ->first();

        if ($exists) {
            return;
        }

        $this->db()->table('funcionarios_role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function flushCache(): void
    {
        try {
            Cache::flush();
        } catch (\Throwable $e) {
            // Cache indisponivel nao deve bloquear a migration.
        }
    }
};
