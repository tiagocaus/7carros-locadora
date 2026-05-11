<?php

/**
 * Migration 00247: Adicionar permissoes para modulo de locacoes/reservas
 *
 * Adiciona as permissoes necessarias para gerenciar locacoes:
 * - locacoes.visualizar: Ver lista de locacoes
 * - locacoes.criar: Criar novas locacoes/reservas
 * - locacoes.editar: Editar locacoes existentes
 * - locacoes.cancelar: Cancelar/excluir locacoes
 * - locacoes.saida: Registrar saida do veiculo (R -> A)
 * - locacoes.devolucao: Registrar devolucao do veiculo (A -> F)
 * - locacoes.imprimir: Imprimir documentos da locacao
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

        // Definir mapeamento de roles -> permissoes
        $rolePermissions = [
            'Proprietário' => [
                'locacoes.visualizar',
                'locacoes.criar',
                'locacoes.editar',
                'locacoes.cancelar',
                'locacoes.saida',
                'locacoes.devolucao',
                'locacoes.imprimir'
            ],
            'Gerente' => [
                'locacoes.visualizar',
                'locacoes.criar',
                'locacoes.editar',
                'locacoes.saida',
                'locacoes.devolucao',
                'locacoes.imprimir'
            ],
            'Atendente' => [
                'locacoes.visualizar',
                'locacoes.criar',
                'locacoes.saida',
                'locacoes.imprimir'
            ],
            'Assistente Administrativo' => [
                'locacoes.visualizar',
                'locacoes.criar',
                'locacoes.editar',
                'locacoes.saida',
                'locacoes.imprimir'
            ],
            'Coordenador Administrativo' => [
                'locacoes.visualizar',
                'locacoes.criar',
                'locacoes.editar',
                'locacoes.saida',
                'locacoes.devolucao',
                'locacoes.imprimir'
            ]
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
                            ->whereRaw("role_id = ? AND permission_id = ?", [$role['id'], $permission['id']])
                            ->first();

                        if (!$exists) {
                            $this->db()->table('funcionarios_role_permissions')->insert([
                                'role_id' => $role['id'],
                                'permission_id' => $permission['id']
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
                'key' => 'locacoes.visualizar',
                'name' => 'Visualizar Locacoes',
                'description' => 'Listar e visualizar locacoes/reservas',
                'module' => 'locacoes'
            ],
            [
                'key' => 'locacoes.criar',
                'name' => 'Criar Locacoes',
                'description' => 'Criar novas locacoes e reservas',
                'module' => 'locacoes'
            ],
            [
                'key' => 'locacoes.editar',
                'name' => 'Editar Locacoes',
                'description' => 'Editar locacoes existentes',
                'module' => 'locacoes'
            ],
            [
                'key' => 'locacoes.cancelar',
                'name' => 'Cancelar Locacoes',
                'description' => 'Cancelar ou excluir locacoes',
                'module' => 'locacoes'
            ],
            [
                'key' => 'locacoes.saida',
                'name' => 'Registrar Saida',
                'description' => 'Registrar saida do veiculo (reserva -> aberto)',
                'module' => 'locacoes'
            ],
            [
                'key' => 'locacoes.devolucao',
                'name' => 'Registrar Devolucao',
                'description' => 'Registrar devolucao do veiculo (aberto -> fechado)',
                'module' => 'locacoes'
            ],
            [
                'key' => 'locacoes.imprimir',
                'name' => 'Imprimir Locacoes',
                'description' => 'Imprimir documentos da locacao',
                'module' => 'locacoes'
            ]
        ];
    }
};
