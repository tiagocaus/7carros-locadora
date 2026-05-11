<?php

namespace App\Models;

/**
 * Model para gerenciamento de RolePermission (Relação Role-Permission)
 *
 * Tabela: funcionarios_role_permissions
 * Multi-tenancy: NÃO tem coluna chave (relação controlada pela role)
 */
class RolePermission extends Model
{
    /**
     * Lista IDs de permissões de uma role
     */
    public function listarIdsPorRole(int $roleId): array
    {
        $result = $this->qb
            ->table('funcionarios_role_permissions')
            ->select(['permission_id'])
            ->withoutChave()
            ->where('role_id', '=', $roleId)
            ->get();

        return array_column($result, 'permission_id');
    }

    /**
     * Lista permissões de uma role
     */
    public function listarPorRole(int $roleId): array
    {
        return $this->qb
            ->table('funcionarios_role_permissions')
            ->select(['permission_id'])
            ->withoutChave()
            ->where('role_id', '=', $roleId)
            ->get();
    }

    /**
     * Adiciona uma permissão à role
     */
    public function adicionar(int $roleId, int $permissionId): int
    {
        return $this->qb
            ->table('funcionarios_role_permissions')
            ->withoutChave()
            ->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => date('Y-m-d H:i:s')
            ]);
    }

    /**
     * Remove todas as permissões de uma role
     */
    public function deletarPorRole(int $roleId): int
    {
        return $this->qb
            ->table('funcionarios_role_permissions')
            ->withoutChave()
            ->where('role_id', '=', $roleId)
            ->delete();
    }

    /**
     * Sincroniza permissões de uma role
     * (Remove todas as antigas e insere as novas)
     */
    public function sincronizar(int $roleId, array $permissionIds): void
    {
        // Remover permissões antigas
        $this->deletarPorRole($roleId);

        // Inserir novas permissões
        foreach ($permissionIds as $permissionId) {
            $permissionId = (int) $permissionId;
            if ($permissionId > 0) {
                $this->adicionar($roleId, $permissionId);
            }
        }
    }

    /**
     * Inicia transação
     */
    public function beginTransaction(): void
    {
        $this->qb->beginTransaction();
    }

    /**
     * Confirma transação
     */
    public function commit(): void
    {
        $this->qb->commit();
    }

    /**
     * Reverte transação
     */
    public function rollback(): void
    {
        $this->qb->rollback();
    }
}
