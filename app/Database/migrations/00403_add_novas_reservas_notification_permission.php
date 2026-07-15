<?php

use App\Database\Migration;

/**
 * Permite que roles recebam por email novos pedidos de reserva do site.
 */
return new class extends Migration
{
    private const PERMISSION_KEY = 'notificacoes.novas_reservas';

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
                'name' => 'Novas Reservas do Site',
                'description' => 'Receber por e-mail novos pedidos de reserva feitos pelo site',
                'module' => 'notificacoes',
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
    }
};
