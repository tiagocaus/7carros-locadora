<?php

/**
 * Migration: Permissoes do Lote C2 da categoria Comparativos (relatorios 11.3, 11.4).
 *   - relatorios.comparativos.ranking_veiculos
 *   - relatorios.comparativos.tendencias
 *
 * Fecha a categoria Comparativos. Atribui a Proprietario/Gerente e invalida cache do Redis.
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
            ['key' => 'relatorios.comparativos.ranking_veiculos', 'name' => 'Relatorio Ranking de Veiculos', 'description' => 'Visualizar ranking de veiculos por receita, qtd locacoes ou taxa de ocupacao (Top 10 e Bottom 10)', 'module' => 'relatorios'],
            ['key' => 'relatorios.comparativos.tendencias', 'name' => 'Relatorio Analise de Tendencias', 'description' => 'Visualizar serie temporal (dia/semana/mes) de receita, locacoes e ticket medio com media movel', 'module' => 'relatorios'],
        ];
    }
};
