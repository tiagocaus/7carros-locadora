<?php

/**
 * Migration: Permissoes do Lote C1 da categoria Comparativos (relatorios 11.1, 11.2).
 *   - relatorios.comparativos.mensal_anual
 *   - relatorios.comparativos.filiais
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
            ['key' => 'relatorios.comparativos.mensal_anual', 'name' => 'Relatorio Comparativo Mensal/Anual', 'description' => 'Visualizar comparativo de indicadores (faturamento, locacoes, ticket medio, lucro) entre periodo atual e anterior', 'module' => 'relatorios'],
            ['key' => 'relatorios.comparativos.filiais', 'name' => 'Relatorio Comparativo entre Filiais', 'description' => 'Visualizar comparativo de performance (locacoes, faturamento, ticket medio) entre filiais', 'module' => 'relatorios'],
        ];
    }
};
