<?php

/**
 * Migration: Permissoes do Lote M-Comercial (relatorios 8.1, 8.2, 8.3, 8.4, 8.5).
 *   - relatorios.comercial.taxa_conversao
 *   - relatorios.comercial.origem_locacoes
 *   - relatorios.comercial.promocoes
 *   - relatorios.comercial.descontos
 *   - relatorios.comercial.temporada
 *
 * Fecha a categoria Comercial. Atribui a Proprietario/Gerente e invalida cache do Redis.
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
            ['key' => 'relatorios.comercial.taxa_conversao', 'name' => 'Relatorio Taxa de Conversao', 'description' => 'Visualizar locacoes por status (R/A/F/C) com taxa de conversao e cancelamento', 'module' => 'relatorios'],
            ['key' => 'relatorios.comercial.origem_locacoes', 'name' => 'Relatorio Origem das Locacoes', 'description' => 'Visualizar agregacao por canal de origem (balcao, telefone, website, etc.)', 'module' => 'relatorios'],
            ['key' => 'relatorios.comercial.promocoes', 'name' => 'Relatorio Promocoes Utilizadas', 'description' => 'Visualizar uso de promocoes/cupons: usos, desconto total, receita gerada', 'module' => 'relatorios'],
            ['key' => 'relatorios.comercial.descontos', 'name' => 'Relatorio Descontos Concedidos', 'description' => 'Visualizar descontos concedidos por funcionario com qtd, total e percentual', 'module' => 'relatorios'],
            ['key' => 'relatorios.comercial.temporada', 'name' => 'Relatorio Analise de Temporada', 'description' => 'Visualizar performance de locacoes por temporada cadastrada', 'module' => 'relatorios'],
        ];
    }
};
