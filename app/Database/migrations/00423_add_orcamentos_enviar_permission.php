<?php

use App\Core\Cache;
use App\Database\Migration;

/**
 * Compatibilidade para ambientes que executaram a criação do módulo antes da
 * permissão de envio ser separada da impressão.
 */
return new class extends Migration
{
    private const KEY = 'orcamentos.enviar';

    public function up(): void
    {
        $permission = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [self::KEY])->first();
        $permissionId = $permission ? (int) $permission['id'] : $this->db()->table('permissions')->insert([
            'key' => self::KEY,
            'name' => 'Enviar orçamentos',
            'description' => 'Enviar orçamentos por e-mail, WhatsApp ou SMS',
            'module' => 'orcamentos',
        ]);

        $roles = $this->db()->table('funcionarios_roles')->select(['id'])->whereIn('name', ['Proprietário', 'Gerente'])->get();
        foreach ($roles as $role) {
            $exists = $this->db()->table('funcionarios_role_permissions')
                ->whereRaw('role_id = ? AND permission_id = ?', [(int) $role['id'], $permissionId])->exists();
            if (!$exists) {
                $this->db()->table('funcionarios_role_permissions')->insert([
                    'role_id' => (int) $role['id'],
                    'permission_id' => $permissionId,
                ]);
            }
        }
        try { Cache::flush(); } catch (\Throwable) {}
    }

    public function down(): void
    {
        $permission = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [self::KEY])->first();
        if ($permission) {
            $this->db()->table('funcionarios_role_permissions')->where('permission_id', '=', (int) $permission['id'])->delete();
            $this->db()->table('permissions')->where('id', '=', (int) $permission['id'])->delete();
        }
        try { Cache::flush(); } catch (\Throwable) {}
    }
};
