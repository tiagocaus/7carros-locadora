<?php

use App\Core\Cache;
use App\Database\Migration;

/**
 * Adiciona a permissao e o indice do relatorio Resultado Gerencial por Caixa.
 */
return new class extends Migration
{
    private const PERMISSION_KEY = 'relatorios.financeiro.resultado_caixa';
    private const INDEX_NAME = 'idx_fin_chave_pago_data_pago';

    public function up(): void
    {
        $this->addIndexIfNotExists(
            'financeiro',
            ['chave', 'pago', 'data_pago'],
            self::INDEX_NAME
        );

        $permission = $this->db()
            ->table('permissions')
            ->select(['id'])
            ->whereRaw('`key` = ?', [self::PERMISSION_KEY])
            ->first();

        $permissionId = $permission
            ? (int) $permission['id']
            : $this->db()->table('permissions')->insert([
                'key' => self::PERMISSION_KEY,
                'name' => 'Relatorio Resultado Gerencial por Caixa',
                'description' => 'Visualizar receitas recebidas e despesas pagas por periodo',
                'module' => 'relatorios',
            ]);

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

        if ($permission) {
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

        $this->dropIndexIfExists('financeiro', self::INDEX_NAME);
        try { Cache::flush(); } catch (\Throwable $e) {}
    }
};
