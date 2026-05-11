<?php

/**
 * Migration: Criar permissões dos relatórios Financeiros
 *
 * Cria 10 permissões para os relatórios financeiros e atribui
 * automaticamente às roles "Proprietário" e "Gerente".
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = $this->getPermissions();

        // 1. Inserir permissões (com check de duplicata)
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

        // 2. Atribuir a Proprietário e Gerente
        $roleNames = ['Proprietário', 'Gerente'];

        foreach ($roleNames as $roleName) {
            $roles = $this->db()
                ->table('funcionarios_roles')
                ->select(['id'])
                ->whereRaw("name = ? AND deleted_at IS NULL", [$roleName])
                ->get();

            foreach ($roles as $role) {
                foreach ($permissions as $permission) {
                    $permRecord = $this->db()
                        ->table('permissions')
                        ->select(['id'])
                        ->whereRaw('`key` = ?', [$permission['key']])
                        ->first();

                    if ($permRecord) {
                        $stmt = $this->pdo->prepare(
                            "INSERT IGNORE INTO funcionarios_role_permissions (role_id, permission_id, created_at)
                             VALUES (?, ?, NOW())"
                        );
                        $stmt->execute([$role['id'], $permRecord['id']]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $permissions = $this->getPermissions();

        foreach ($permissions as $permission) {
            $permRecord = $this->db()
                ->table('permissions')
                ->select(['id'])
                ->whereRaw('`key` = ?', [$permission['key']])
                ->first();

            if ($permRecord) {
                $stmt = $this->pdo->prepare("DELETE FROM funcionarios_role_permissions WHERE permission_id = ?");
                $stmt->execute([$permRecord['id']]);

                $stmt = $this->pdo->prepare("DELETE FROM permissions WHERE id = ?");
                $stmt->execute([$permRecord['id']]);
            }
        }
    }

    private function getPermissions(): array
    {
        return [
            [
                'key' => 'relatorios.financeiro.movimentacoes',
                'name' => 'Relatório Movimentações Financeiras',
                'description' => 'Visualizar relatório de movimentações financeiras',
                'module' => 'relatorios',
            ],
            [
                'key' => 'relatorios.financeiro.faturamento',
                'name' => 'Relatório Faturamento',
                'description' => 'Visualizar relatório de faturamento',
                'module' => 'relatorios',
            ],
            [
                'key' => 'relatorios.financeiro.dre',
                'name' => 'Relatório DRE',
                'description' => 'Visualizar demonstrativo de resultados',
                'module' => 'relatorios',
            ],
            [
                'key' => 'relatorios.financeiro.livro_caixa',
                'name' => 'Relatório Livro de Caixa',
                'description' => 'Visualizar relatório de livro de caixa',
                'module' => 'relatorios',
            ],
            [
                'key' => 'relatorios.financeiro.contas_bancarias',
                'name' => 'Relatório Contas Bancárias',
                'description' => 'Visualizar relatório de contas bancárias e caixas',
                'module' => 'relatorios',
            ],
            [
                'key' => 'relatorios.financeiro.plano_contas',
                'name' => 'Relatório Plano de Contas',
                'description' => 'Visualizar relatório por plano de contas',
                'module' => 'relatorios',
            ],
            [
                'key' => 'relatorios.financeiro.projecao_receitas',
                'name' => 'Relatório Projeção de Receitas',
                'description' => 'Visualizar projeção de receitas futuras',
                'module' => 'relatorios',
            ],
            [
                'key' => 'relatorios.financeiro.rentabilidade',
                'name' => 'Relatório Rentabilidade',
                'description' => 'Visualizar análise de rentabilidade',
                'module' => 'relatorios',
            ],
            [
                'key' => 'relatorios.financeiro.inadimplencia',
                'name' => 'Relatório Inadimplência',
                'description' => 'Visualizar panorama de inadimplência',
                'module' => 'relatorios',
            ],
            [
                'key' => 'relatorios.financeiro.taxas_servicos',
                'name' => 'Relatório Taxas e Serviços',
                'description' => 'Visualizar relatório de taxas e serviços cobrados',
                'module' => 'relatorios',
            ],
        ];
    }
};
