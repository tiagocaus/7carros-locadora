<?php

/**
 * Migration: Permissoes dos relatorios da categoria CLIENTES (grupo 4).
 *   - relatorios.clientes.por_cliente
 *   - relatorios.clientes.aniversariantes
 *   - relatorios.clientes.cnh_vencidas
 *   - relatorios.clientes.top_clientes
 *   - relatorios.clientes.frequencia
 *   - relatorios.clientes.tempo_relacionamento
 *   - relatorios.clientes.ocorrencias
 *   - relatorios.clientes.inativos
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
            ['key' => 'relatorios.clientes.por_cliente', 'name' => 'Relatorio Locacoes Por Cliente', 'description' => 'Visualizar relatorio de locacoes agregadas por cliente', 'module' => 'relatorios'],
            ['key' => 'relatorios.clientes.aniversariantes', 'name' => 'Relatorio Aniversariantes', 'description' => 'Visualizar relatorio de clientes aniversariantes', 'module' => 'relatorios'],
            ['key' => 'relatorios.clientes.cnh_vencidas', 'name' => 'Relatorio CNH Vencidas', 'description' => 'Visualizar relatorio de clientes com CNH vencida ou prestes a vencer', 'module' => 'relatorios'],
            ['key' => 'relatorios.clientes.top_clientes', 'name' => 'Relatorio Top Clientes', 'description' => 'Visualizar ranking dos melhores clientes', 'module' => 'relatorios'],
            ['key' => 'relatorios.clientes.frequencia', 'name' => 'Relatorio Frequencia de Locacao', 'description' => 'Visualizar relatorio de frequencia de locacao por cliente', 'module' => 'relatorios'],
            ['key' => 'relatorios.clientes.tempo_relacionamento', 'name' => 'Relatorio Tempo de Relacionamento', 'description' => 'Visualizar lifetime do cliente (tempo desde a primeira locacao)', 'module' => 'relatorios'],
            ['key' => 'relatorios.clientes.ocorrencias', 'name' => 'Relatorio Ocorrencias do Cliente', 'description' => 'Visualizar historico de ocorrencias do cliente (atrasos, inadimplencia)', 'module' => 'relatorios'],
            ['key' => 'relatorios.clientes.inativos', 'name' => 'Relatorio Clientes Inativos', 'description' => 'Visualizar clientes sem locacao ha um periodo minimo configuravel', 'module' => 'relatorios'],
        ];
    }
};
