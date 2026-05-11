<?php

/**
 * Migration 00357: Adicionar permissao de dashboard completo
 *
 * Separa o acesso basico ao dashboard (dashboard.visualizar) da escolha da
 * versao completa do dashboard (dashboard.completo).
 */

use App\Core\Cache;
use App\Database\Migration;

return new class extends Migration
{
    private const PERMISSION_KEY = 'dashboard.completo';

    public function up(): void
    {
        $permissionId = $this->ensurePermission();
        $this->assignPermissionToDefaultRoles($permissionId);
        $this->flushCache();
    }

    public function down(): void
    {
        $permission = $this->db()
            ->table('permissions')
            ->select(['id'])
            ->whereRaw('`key` = ?', [self::PERMISSION_KEY])
            ->first();

        if ($permission) {
            $permissionId = (int) $permission['id'];

            $this->db()
                ->table('funcionarios_role_permissions')
                ->whereRaw('permission_id = ?', [$permissionId])
                ->delete();

            $this->db()
                ->table('permissions')
                ->whereRaw('id = ?', [$permissionId])
                ->delete();
        }

        $this->flushCache();
    }

    private function ensurePermission(): int
    {
        $permission = $this->db()
            ->table('permissions')
            ->select(['id'])
            ->whereRaw('`key` = ?', [self::PERMISSION_KEY])
            ->first();

        $data = [
            'key' => self::PERMISSION_KEY,
            'name' => 'Dashboard completo',
            'description' => 'Acesso ao dashboard completo',
            'module' => 'dashboard',
        ];

        if ($permission) {
            $permissionId = (int) $permission['id'];

            $this->db()
                ->table('permissions')
                ->whereRaw('id = ?', [$permissionId])
                ->update($data);

            return $permissionId;
        }

        return (int) $this->db()->table('permissions')->insert($data);
    }

    private function assignPermissionToDefaultRoles(int $permissionId): void
    {
        $roles = $this->db()
            ->table('funcionarios_roles')
            ->select(['id'])
            ->whereRaw('name IN (?, ?)', ['Proprietário', 'Gerente'])
            ->get();

        foreach ($roles as $role) {
            $roleId = (int) $role['id'];

            $exists = $this->db()
                ->table('funcionarios_role_permissions')
                ->select(['id'])
                ->whereRaw('role_id = ? AND permission_id = ?', [$roleId, $permissionId])
                ->first();

            if (!$exists) {
                $this->db()->table('funcionarios_role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    private function flushCache(): void
    {
        try {
            Cache::flush();
        } catch (\Throwable $e) {
            // Cache pode estar indisponivel; nao deve bloquear migration.
        }
    }
};
