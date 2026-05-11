<?php

/**
 * Migration: Permissoes do Lote M-Funcionarios (relatorios 10.1, 10.2, 10.3, 10.4).
 *   - relatorios.funcionarios.vendas
 *   - relatorios.funcionarios.comissoes
 *   - relatorios.funcionarios.produtividade
 *   - relatorios.funcionarios.metas
 *
 * Fecha a categoria Funcionarios e a implementacao dos 26 relatorios pendentes.
 * Atribui a Proprietario/Gerente e invalida cache do Redis.
 */

use App\Core\Cache;
use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = $this->getPermissions();

        foreach ($permissions as $permission) {
            $existing = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [$permission['key']])->first();
            if (!$existing) $this->db()->table('permissions')->insert($permission);
        }

        foreach (['Proprietário', 'Gerente'] as $roleName) {
            $roles = $this->db()->table('funcionarios_roles')->select(['id'])->where('name', '=', $roleName)->get();
            foreach ($roles as $role) {
                foreach ($permissions as $permission) {
                    $permRecord = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [$permission['key']])->first();
                    if ($permRecord) {
                        $stmt = $this->pdo->prepare("INSERT IGNORE INTO funcionarios_role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())");
                        $stmt->execute([$role['id'], $permRecord['id']]);
                    }
                }
            }
        }

        try { Cache::flush(); } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        $permissions = $this->getPermissions();
        foreach ($permissions as $permission) {
            $permRecord = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [$permission['key']])->first();
            if ($permRecord) {
                $this->pdo->prepare("DELETE FROM funcionarios_role_permissions WHERE permission_id = ?")->execute([$permRecord['id']]);
                $this->pdo->prepare("DELETE FROM permissions WHERE id = ?")->execute([$permRecord['id']]);
            }
        }
        try { Cache::flush(); } catch (\Throwable $e) {}
    }

    private function getPermissions(): array
    {
        return [
            ['key' => 'relatorios.funcionarios.vendas', 'name' => 'Relatorio Vendas por Funcionario', 'description' => 'Visualizar locacoes e contratos vendidos por cada funcionario com ranking', 'module' => 'relatorios'],
            ['key' => 'relatorios.funcionarios.comissoes', 'name' => 'Relatorio Comissoes', 'description' => 'Visualizar comissoes (tabela comissoes_funcionarios) por funcionario com filtro por status', 'module' => 'relatorios'],
            ['key' => 'relatorios.funcionarios.produtividade', 'name' => 'Relatorio Produtividade', 'description' => 'Visualizar metricas de produtividade: loc/dia, fat/dia, qtd checklists realizados', 'module' => 'relatorios'],
            ['key' => 'relatorios.funcionarios.metas', 'name' => 'Relatorio Metas vs Realizado', 'description' => 'Comparar metas cadastradas (metas_funcionarios) com realizado por funcionario', 'module' => 'relatorios'],
        ];
    }
};
