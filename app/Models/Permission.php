<?php

namespace App\Models;

/**
 * Model para gerenciamento de Permissions (Permissões)
 *
 * Tabela: permissions
 * Multi-tenancy: NÃO tem coluna chave (tabela global do sistema)
 */
class Permission extends Model
{
    /**
     * Lista todas as permissões
     */
    public function listarTodas(): array
    {
        return $this->qb
            ->table('permissions')
            ->select(['id', '`key`', 'name', 'description', 'module'])
            ->withoutChave()
            ->orderBy('module', 'ASC')
            ->orderBy('name', 'ASC')
            ->get();
    }

    /**
     * Lista permissões de uma role específica
     */
    public function listarPorRole(int $roleId): array
    {
        return $this->qb
            ->table('permissions', 'p')
            ->select(['p.id', 'p.`key`', 'p.name', 'p.description', 'p.module'])
            ->innerJoin('funcionarios_role_permissions', 'rp', 'p.id', '=', 'rp.permission_id')
            ->withoutChave()
            ->where('rp.role_id', '=', $roleId)
            ->orderBy('p.module', 'ASC')
            ->orderBy('p.name', 'ASC')
            ->get();
    }

    /**
     * Lista permissões agrupadas por módulo
     */
    public function listarAgrupadasPorModulo(): array
    {
        $permissions = $this->listarTodas();

        $grouped = [];
        foreach ($permissions as $permission) {
            $module = $permission['module'];
            if (!isset($grouped[$module])) {
                $grouped[$module] = [
                    'module' => $module,
                    'permissions' => []
                ];
            }
            $grouped[$module]['permissions'][] = $permission;
        }

        return array_values($grouped);
    }
}
