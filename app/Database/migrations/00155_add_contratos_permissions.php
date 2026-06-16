<?php

/**
 * Migration 00155: Adicionar permissoes para modulo de contratos
 *
 * Adiciona as permissoes necessarias para gerenciar contratos de locacao:
 * - contratos.visualizar: Ver lista de contratos
 * - contratos.criar: Criar novos contratos
 * - contratos.editar: Editar contratos existentes
 * - contratos.editar_valores: Editar valores do grupo (permissao especial)
 * - contratos.excluir: Excluir contratos
 * - contratos.devolver: Registrar devolucao de veiculo
 * - contratos.substituir: Substituir veiculo do contrato
 * - contratos.assinatura: Gerenciar assinatura digital
 * - contratos.imprimir: Imprimir documentos do contrato
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
                'contratos.visualizar',
                'contratos.criar',
                'contratos.editar',
                'contratos.editar_valores',
                'contratos.excluir',
                'contratos.devolver',
                'contratos.substituir',
                'contratos.assinatura',
                'contratos.imprimir'
            ],
            'Gerente' => [
                'contratos.visualizar',
                'contratos.criar',
                'contratos.editar',
                'contratos.devolver',
                'contratos.substituir',
                'contratos.assinatura',
                'contratos.imprimir'
            ],
            'Atendente' => [
                'contratos.visualizar',
                'contratos.criar',
                'contratos.editar',
                'contratos.devolver',
                'contratos.substituir',
                'contratos.assinatura',
                'contratos.imprimir'
            ],
            'Assistente Administrativo' => [
                'contratos.visualizar',
                'contratos.criar',
                'contratos.editar',
                'contratos.assinatura',
                'contratos.imprimir'
            ],
            'Coordenador Administrativo' => [
                'contratos.visualizar',
                'contratos.criar',
                'contratos.editar',
                'contratos.devolver',
                'contratos.substituir',
                'contratos.assinatura',
                'contratos.imprimir'
            ]
        ];

        foreach ($rolePermissions as $roleName => $permKeys) {
            // Buscar roles com esse nome (pode haver multiplos por tenant)
            $roles = $this->db()
                ->table('funcionarios_roles')
                ->select(['id'])
                ->whereRaw("name = ? AND deleted_at IS NULL", [$roleName])
                ->get();

            foreach ($roles as $role) {
                foreach ($permKeys as $permKey) {
                    // Buscar ID da permissao
                    $permission = $this->db()
                        ->table('permissions')
                        ->select(['id'])
                        ->whereRaw('`key` = ?', [$permKey])
                        ->first();

                    if ($permission) {
                        // Verificar se ja existe a associacao
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
                // Remover associacoes com roles
                $this->db()
                    ->table('funcionarios_role_permissions')
                    ->whereRaw("permission_id = ?", [$perm['id']])
                    ->delete();

                // Remover permissao
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
                'key' => 'contratos.visualizar',
                'name' => 'Visualizar Contratos',
                'description' => 'Listar e visualizar contratos de locacao',
                'module' => 'contratos'
            ],
            [
                'key' => 'contratos.criar',
                'name' => 'Criar Contratos',
                'description' => 'Criar novos contratos de locacao',
                'module' => 'contratos'
            ],
            [
                'key' => 'contratos.editar',
                'name' => 'Editar Contratos',
                'description' => 'Editar contratos existentes',
                'module' => 'contratos'
            ],
            [
                'key' => 'contratos.editar_valores',
                'name' => 'Editar Valores do Grupo',
                'description' => 'Alterar valores originais do grupo no contrato',
                'module' => 'contratos'
            ],
            [
                'key' => 'contratos.excluir',
                'name' => 'Excluir Contratos',
                'description' => 'Excluir contratos (remove dados relacionados)',
                'module' => 'contratos'
            ],
            [
                'key' => 'contratos.devolver',
                'name' => 'Devolver Veiculo',
                'description' => 'Registrar devolucao de veiculo no contrato',
                'module' => 'contratos'
            ],
            [
                'key' => 'contratos.substituir',
                'name' => 'Substituir Veiculo',
                'description' => 'Substituir veiculo durante o contrato',
                'module' => 'contratos'
            ],
            [
                'key' => 'contratos.assinatura',
                'name' => 'Gerenciar Assinatura',
                'description' => 'Gerar link de assinatura e limpar assinaturas',
                'module' => 'contratos'
            ],
            [
                'key' => 'contratos.imprimir',
                'name' => 'Imprimir Contratos',
                'description' => 'Imprimir fatura, contrato, checklist e recibo',
                'module' => 'contratos'
            ]
        ];
    }
};
