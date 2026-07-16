<?php

use App\Core\Cache;
use App\Database\Migration;

/**
 * Separa o acesso ao sistema web do acesso ao aplicativo de vistoria.
 *
 * A role global Vistoriador continua com app_vistoria.visualizar, mas deixa
 * de acessar o sistema administrativo. Customizacoes dos tenants nao mudam.
 */
return new class extends Migration
{
    private const ROLE_NAME = 'Vistoriador';
    private const ROLE_CHAVE = '0';
    private const PERMISSION_KEY = 'dashboard.visualizar';

    public function up(): void
    {
        $this->removeDashboardPermissionFromDefaultRole();
        $this->flushCache();
    }

    public function down(): void
    {
        $roleId = $this->defaultRoleId();
        $permissionId = $this->permissionId();

        if ($roleId === null || $permissionId === null) {
            $this->flushCache();
            return;
        }

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

        $this->flushCache();
    }

    private function removeDashboardPermissionFromDefaultRole(): void
    {
        $roleId = $this->defaultRoleId();
        $permissionId = $this->permissionId();

        if ($roleId === null || $permissionId === null) {
            return;
        }

        $this->db()
            ->table('funcionarios_role_permissions')
            ->whereRaw('role_id = ? AND permission_id = ?', [$roleId, $permissionId])
            ->delete();
    }

    private function defaultRoleId(): ?int
    {
        $role = $this->db()
            ->table('funcionarios_roles')
            ->select(['id'])
            ->whereRaw('chave = ? AND name = ?', [self::ROLE_CHAVE, self::ROLE_NAME])
            ->first();

        return $role ? (int) $role['id'] : null;
    }

    private function permissionId(): ?int
    {
        $permission = $this->db()
            ->table('permissions')
            ->select(['id'])
            ->whereRaw('`key` = ?', [self::PERMISSION_KEY])
            ->first();

        return $permission ? (int) $permission['id'] : null;
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
