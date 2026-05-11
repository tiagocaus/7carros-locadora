<?php

/**
 * Migration: Permissoes do Lote V3 da categoria Veicular (relatorios 3.5, 3.6, 3.7).
 *   - relatorios.veicular.licenciamento
 *   - relatorios.veicular.disponibilidade
 *   - relatorios.veicular.ocupacao_grupo
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
            ['key' => 'relatorios.veicular.licenciamento', 'name' => 'Relatorio Licenciamento', 'description' => 'Visualizar relatorio de licenciamento (IPVA, Seguro, Licenciamento) com status de vencimento', 'module' => 'relatorios'],
            ['key' => 'relatorios.veicular.disponibilidade', 'name' => 'Relatorio Disponibilidade', 'description' => 'Visualizar snapshot atual da disponibilidade da frota (Disponivel/Locado/Reservado/Manutencao)', 'module' => 'relatorios'],
            ['key' => 'relatorios.veicular.ocupacao_grupo', 'name' => 'Relatorio Taxa Ocupacao por Grupo', 'description' => 'Visualizar taxa de ocupacao e receita por grupo de veiculos', 'module' => 'relatorios'],
        ];
    }
};
