<?php

/**
 * Migration: Permissoes do Lote V4 da categoria Veicular (relatorios 3.8, 3.9, 3.10, 3.11).
 *   - relatorios.veicular.depreciacao
 *   - relatorios.veicular.tempo_parado
 *   - relatorios.veicular.quilometragem_media
 *   - relatorios.veicular.tco
 *
 * Fecha a categoria Veicular. Atribui a Proprietario/Gerente e invalida cache do Redis.
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
            ['key' => 'relatorios.veicular.depreciacao', 'name' => 'Relatorio Depreciacao de Frota', 'description' => 'Visualizar relatorio de depreciacao linear (5 anos / 20% residual) por veiculo', 'module' => 'relatorios'],
            ['key' => 'relatorios.veicular.tempo_parado', 'name' => 'Relatorio Tempo Medio Parado', 'description' => 'Visualizar ociosidade dos veiculos (dias parados vs dias locados) no periodo', 'module' => 'relatorios'],
            ['key' => 'relatorios.veicular.quilometragem_media', 'name' => 'Relatorio Quilometragem Media', 'description' => 'Visualizar km rodado por veiculo no periodo, com media por dia e por locacao', 'module' => 'relatorios'],
            ['key' => 'relatorios.veicular.tco', 'name' => 'Relatorio Custo Total de Propriedade (TCO)', 'description' => 'Visualizar TCO por veiculo: depreciacao + manutencao + multas + encargos no periodo', 'module' => 'relatorios'],
        ];
    }
};
