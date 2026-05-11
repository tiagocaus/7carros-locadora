<?php

/**
 * Migration 00358: Normalizar permissoes da role Proprietario
 *
 * Garante que toda role "Proprietário" tenha todas as permissoes existentes.
 * Esta migration e uma normalizacao de dados: o rollback nao remove permissoes,
 * pois isso poderia retirar acessos concedidos intencionalmente depois.
 */

use App\Core\Cache;
use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $roles = $this->db()
            ->table('funcionarios_roles')
            ->select(['id'])
            ->whereRaw('name IN (?, ?)', ['Proprietário', 'Proprietario'])
            ->get();

        if (empty($roles)) {
            return;
        }

        $permissions = $this->db()
            ->table('permissions')
            ->select(['id'])
            ->get();

        foreach ($roles as $role) {
            $roleId = (int) $role['id'];

            foreach ($permissions as $permission) {
                $permissionId = (int) $permission['id'];

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

        $this->flushCache();
    }

    public function down(): void
    {
        // No-op: normalizacao irreversivel com seguranca sem snapshot anterior.
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
