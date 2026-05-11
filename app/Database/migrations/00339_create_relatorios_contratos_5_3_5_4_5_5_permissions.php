<?php

/**
 * Migration: Permissoes dos relatorios 5.3, 5.4, 5.5
 *   - relatorios.contratos.por_forma_pagamento
 *   - relatorios.contratos.extensoes
 *   - relatorios.contratos.trocas_veiculo
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
            ['key' => 'relatorios.contratos.por_forma_pagamento', 'name' => 'Relatorio Locacoes Por Forma de Pagamento', 'description' => 'Visualizar relatorio de locacoes por forma de pagamento', 'module' => 'relatorios'],
            ['key' => 'relatorios.contratos.extensoes', 'name' => 'Relatorio Extensoes de Contrato', 'description' => 'Visualizar relatorio de locacoes com extensao (devolucao tardia)', 'module' => 'relatorios'],
            ['key' => 'relatorios.contratos.trocas_veiculo', 'name' => 'Relatorio Trocas de Veiculo', 'description' => 'Visualizar relatorio de trocas de veiculo durante locacoes', 'module' => 'relatorios'],
        ];
    }
};
