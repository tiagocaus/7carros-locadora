<?php

/**
 * Migration: Criar permissões dos relatórios KPIs
 *
 * Cria 8 permissões para os relatórios de KPIs e atribui
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
                // Remover associações
                $stmt = $this->pdo->prepare("DELETE FROM funcionarios_role_permissions WHERE permission_id = ?");
                $stmt->execute([$permRecord['id']]);

                // Remover permissão
                $stmt = $this->pdo->prepare("DELETE FROM permissions WHERE id = ?");
                $stmt->execute([$permRecord['id']]);
            }
        }
    }

    private function getPermissions(): array
    {
        return [
            [
                'key' => 'relatorios.kpis.taxa_ocupacao',
                'name' => 'Relatório Taxa de Ocupação',
                'description' => 'Visualizar relatório de taxa de ocupação da frota',
                'module' => 'relatorios',
            ],
            [
                'key' => 'relatorios.kpis.revpar',
                'name' => 'Relatório RevPAR',
                'description' => 'Visualizar relatório de receita por veículo disponível/dia',
                'module' => 'relatorios',
            ],
            [
                'key' => 'relatorios.kpis.adr',
                'name' => 'Relatório Diária Média (ADR)',
                'description' => 'Visualizar relatório de diária média praticada',
                'module' => 'relatorios',
            ],
            [
                'key' => 'relatorios.kpis.ticket_medio',
                'name' => 'Relatório Ticket Médio',
                'description' => 'Visualizar relatório de ticket médio por operação',
                'module' => 'relatorios',
            ],
            [
                'key' => 'relatorios.kpis.tempo_medio_locacao',
                'name' => 'Relatório Tempo Médio de Locação',
                'description' => 'Visualizar relatório de tempo médio das locações',
                'module' => 'relatorios',
            ],
            [
                'key' => 'relatorios.kpis.receitas_adicionais',
                'name' => 'Relatório Receitas Adicionais',
                'description' => 'Visualizar relatório de percentual de receitas adicionais',
                'module' => 'relatorios',
            ],
            [
                'key' => 'relatorios.kpis.margem_bruta',
                'name' => 'Relatório Margem Bruta',
                'description' => 'Visualizar relatório de margem bruta por dia',
                'module' => 'relatorios',
            ],
            [
                'key' => 'relatorios.kpis.roi_veiculo',
                'name' => 'Relatório ROI por Veículo',
                'description' => 'Visualizar relatório de retorno sobre investimento por veículo',
                'module' => 'relatorios',
            ],
        ];
    }
};
