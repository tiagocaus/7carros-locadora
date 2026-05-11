<?php

/**
 * Migration: Permissoes do Lote V2 da categoria Veicular (relatorios 3.2, 3.3, 3.4).
 *   - relatorios.veicular.lucro_veiculo
 *   - relatorios.veicular.despesas
 *   - relatorios.veicular.veiculo_cliente
 *
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
            ['key' => 'relatorios.veicular.lucro_veiculo', 'name' => 'Relatorio Lucro por Veiculo', 'description' => 'Visualizar relatorio de lucro liquido por veiculo (receita - despesa)', 'module' => 'relatorios'],
            ['key' => 'relatorios.veicular.despesas', 'name' => 'Relatorio Despesas Veicular', 'description' => 'Visualizar relatorio de despesas por veiculo (manutencao, multas, encargos)', 'module' => 'relatorios'],
            ['key' => 'relatorios.veicular.veiculo_cliente', 'name' => 'Relatorio Veiculo/Cliente', 'description' => 'Visualizar historico de locacoes e contratos por veiculo mostrando clientes', 'module' => 'relatorios'],
        ];
    }
};
