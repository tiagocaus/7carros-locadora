<?php

use App\Core\Cache;
use App\Database\Migration;

/**
 * Permissao do relatorio Faturas > Contas a Receber por Contrato.
 */
return new class extends Migration
{
    private const PERMISSION_KEY = 'relatorios.faturas.receber_por_contrato';

    public function up(): void
    {
        $permission = $this->db()
            ->table('permissions')
            ->select(['id'])
            ->whereRaw('`key` = ?', [self::PERMISSION_KEY])
            ->first();

        $permissionId = $permission
            ? (int) $permission['id']
            : (int) $this->db()->table('permissions')->insert([
                'key' => self::PERMISSION_KEY,
                'name' => 'Relatorio Contas a Receber por Contrato',
                'description' => 'Consultar receitas pendentes agrupadas por contrato e cliente',
                'module' => 'relatorios',
            ]);

        $roles = $this->db()
            ->table('funcionarios_roles')
            ->select(['id'])
            ->whereIn('name', ['Proprietário', 'Proprietario', 'Gerente'])
            ->get();

        foreach ($roles as $role) {
            $roleId = (int) $role['id'];
            $exists = $this->db()
                ->table('funcionarios_role_permissions')
                ->whereRaw('role_id = ? AND permission_id = ?', [$roleId, $permissionId])
                ->exists();

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
            ->whereRaw('`key` = ?', [self::PERMISSION_KEY])
            ->first();

        if (!$permission) {
            return;
        }

        $permissionId = (int) $permission['id'];
        $this->db()
            ->table('funcionarios_role_permissions')
            ->where('permission_id', '=', $permissionId)
            ->delete();
        $this->db()
            ->table('permissions')
            ->where('id', '=', $permissionId)
            ->delete();

        $this->flushCache();
    }

    private function flushCache(): void
    {
        try {
            Cache::flush();
        } catch (\Throwable $e) {
            // Cache indisponivel nao deve bloquear migration de permissao.
        }
    }
};
