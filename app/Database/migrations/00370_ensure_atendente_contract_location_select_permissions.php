<?php

/**
 * Migration 00370: Garantir permissoes auxiliares do Atendente
 *
 * Normaliza as roles Atendente para permitir criar contratos e locacoes com
 * os selects auxiliares necessarios: filiais, clientes, grupos, veiculos,
 * taxas e funcionarios.
 */

use App\Core\Cache;
use App\Database\Migration;

return new class extends Migration
{
    private const ROLE_NAMES = ['Atendente', 'Antendente'];

    private const PERMISSIONS = [
        [
            'key' => 'contratos.visualizar',
            'name' => 'Visualizar Contratos',
            'description' => 'Listar e visualizar contratos de locacao',
            'module' => 'contratos',
        ],
        [
            'key' => 'contratos.criar',
            'name' => 'Criar Contratos',
            'description' => 'Criar novos contratos de locacao',
            'module' => 'contratos',
        ],
        [
            'key' => 'locacoes.visualizar',
            'name' => 'Visualizar Locacoes',
            'description' => 'Listar e visualizar locacoes/reservas',
            'module' => 'locacoes',
        ],
        [
            'key' => 'locacoes.criar',
            'name' => 'Criar Locacoes',
            'description' => 'Criar novas locacoes e reservas',
            'module' => 'locacoes',
        ],
        [
            'key' => 'matrizes_filiais.visualizar',
            'name' => 'Visualizar Matrizes/Filiais',
            'description' => 'Listar e visualizar matrizes e filiais',
            'module' => 'matrizes_filiais',
        ],
        [
            'key' => 'clientes.visualizar',
            'name' => 'Visualizar Clientes',
            'description' => 'Listar e visualizar clientes',
            'module' => 'clientes',
        ],
        [
            'key' => 'clientes.criar',
            'name' => 'Criar Clientes',
            'description' => 'Adicionar novos clientes',
            'module' => 'clientes',
        ],
        [
            'key' => 'clientes.editar',
            'name' => 'Editar Clientes',
            'description' => 'Editar clientes existentes',
            'module' => 'clientes',
        ],
        [
            'key' => 'grupos.visualizar',
            'name' => 'Visualizar Grupos',
            'description' => 'Listar e visualizar grupos',
            'module' => 'grupos',
        ],
        [
            'key' => 'veiculos.visualizar',
            'name' => 'Visualizar Veiculos',
            'description' => 'Listar e visualizar veiculos',
            'module' => 'veiculos',
        ],
        [
            'key' => 'taxas_servicos.visualizar',
            'name' => 'Visualizar Taxas e Serviços',
            'description' => 'Listar e visualizar taxas e servicos',
            'module' => 'taxas_servicos',
        ],
        [
            'key' => 'funcionarios.visualizar',
            'name' => 'Visualizar Funcionários',
            'description' => 'Listar e visualizar funcionários',
            'module' => 'funcionarios',
        ],
    ];

    public function up(): void
    {
        $this->ensurePermissionsExist();

        $roleIds = $this->loadAtendenteRoleIds();
        if (empty($roleIds)) {
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
