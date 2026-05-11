<?php

/**
 * Migration 00272: Criar permissoes do modulo NFS-e
 *
 * Permissoes:
 * - nfse.visualizar - Listar e visualizar NFS-e
 * - nfse.criar - Emitir novas NFS-e
 * - nfse.excluir - Cancelar NFS-e
 * - nfse.configurar - Acessar configuracoes de NFS-e
 *
 * Atribuicao:
 * - Proprietario: todas
 * - Gerente: visualizar, criar, excluir
 * - Coordenador Administrativo: visualizar, criar
 * - Assistente Administrativo: visualizar
 * - Atendente: nenhuma
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = $this->getPermissions();

        // Inserir permissoes (verificando duplicatas)
        foreach ($permissions as $permission) {
            $existing = $this->db()
                ->table('permissions')
                ->select(['id'])
                ->whereRaw('`key` = ?', [$permission['key']])
                ->first();

            if (!$existing) {
                $this->db()->table('permissions')->insert($permission);
            }
        }

        // Mapeamento de roles -> permissoes
        $rolePermissions = [
            'Proprietario' => [
                'nfse.visualizar',
                'nfse.criar',
                'nfse.excluir',
                'nfse.configurar',
            ],
            'Gerente' => [
                'nfse.visualizar',
                'nfse.criar',
                'nfse.excluir',
            ],
            'Coordenador Administrativo' => [
                'nfse.visualizar',
                'nfse.criar',
            ],
            'Assistente Administrativo' => [
                'nfse.visualizar',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permKeys) {
            $roles = $this->db()
                ->table('funcionarios_roles')
                ->select(['id'])
                ->whereRaw("name = ? AND deleted_at IS NULL", [$roleName])
                ->get();

            foreach ($roles as $role) {
                foreach ($permKeys as $permKey) {
                    $permission = $this->db()
                        ->table('permissions')
                        ->select(['id'])
                        ->whereRaw('`key` = ?', [$permKey])
                        ->first();

                    if ($permission) {
                        $exists = $this->db()
                            ->table('funcionarios_role_permissions')
                            ->select(['id'])
                            ->whereRaw("role_id = ? AND permission_id = ?",
                                [$role['id'], $permission['id']])
                            ->first();

                        if (!$exists) {
                            $this->db()->table('funcionarios_role_permissions')->insert([
                                'role_id' => $role['id'],
                                'permission_id' => $permission['id'],
                            ]);
                        }
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $permissions = $this->getPermissions();

        foreach ($permissions as $permission) {
            $perm = $this->db()
                ->table('permissions')
                ->select(['id'])
                ->whereRaw('`key` = ?', [$permission['key']])
                ->first();

            if ($perm) {
                $this->db()
                    ->table('funcionarios_role_permissions')
                    ->whereRaw("permission_id = ?", [$perm['id']])
                    ->delete();

                $this->db()
                    ->table('permissions')
                    ->whereRaw("id = ?", [$perm['id']])
                    ->delete();
            }
        }
    }

    private function getPermissions(): array
    {
        return [
            [
                'key' => 'nfse.visualizar',
                'name' => 'Visualizar NFS-e',
                'description' => 'Listar e visualizar notas fiscais de servico',
                'module' => 'nfse',
            ],
            [
                'key' => 'nfse.criar',
                'name' => 'Emitir NFS-e',
                'description' => 'Emitir novas notas fiscais de servico',
                'module' => 'nfse',
            ],
            [
                'key' => 'nfse.excluir',
                'name' => 'Cancelar NFS-e',
                'description' => 'Cancelar notas fiscais de servico emitidas',
                'module' => 'nfse',
            ],
            [
                'key' => 'nfse.configurar',
                'name' => 'Configurar NFS-e',
                'description' => 'Acessar e alterar configuracoes de NFS-e',
                'module' => 'nfse',
            ],
        ];
    }
};
