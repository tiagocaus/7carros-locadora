<?php

use App\Core\Cache;
use App\Database\Migration;

/**
 * Permissao do relatorio Veicular > Evolucao da Quilometragem.
 */
return new class extends Migration
{
    private const PERMISSION_KEY = 'relatorios.veicular.evolucao_quilometragem';

    public function up(): void
    {
        $permission = $this->db()
            ->table('permissions')
            ->select(['id'])
            ->whereRaw('`key` = ?', [self::PERMISSION_KEY])
            ->first();

        if (!$permission) {
            $permissionId = $this->db()->table('permissions')->insert([
                'key' => self::PERMISSION_KEY,
                'name' => 'Relatorio Evolucao da Quilometragem',
                'description' => 'Visualizar a quilometragem reconhecida por dia, semana, mes ou ano',
                'module' => 'relatorios',
            ]);
        } else {
            $permissionId = (int) $permission['id'];
        }

        $roles = $this->db()
            ->table('funcionarios_roles')
            ->select(['id'])
            ->whereIn('name', ['Proprietário', 'Gerente'])
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

        try { Cache::flush(); } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        $permission = $this->db()
            ->table('permissions')
            ->select(['id'])
            ->whereRaw('`key` = ?', [self::PERMISSION_KEY])
            ->first();

        if (!$permission) return;

        $permissionId = (int) $permission['id'];
        $this->db()
            ->table('funcionarios_role_permissions')
            ->where('permission_id', '=', $permissionId)
            ->delete();
        $this->db()
            ->table('permissions')
            ->where('id', '=', $permissionId)
            ->delete();

        try { Cache::flush(); } catch (\Throwable $e) {}
    }
};
