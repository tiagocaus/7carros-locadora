<?php

/**
 * Migration: Adicionar permissões de Notificações
 *
 * Cria uma nova seção "Notificações" no sistema de permissões com 6 tipos de notificação.
 * A role "Proprietário" recebe todas as permissões ativas por padrão.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = $this->getPermissions();

        // Inserir permissões (verificando duplicatas)
        foreach ($permissions as $permission) {
            $existing = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [$permission['key']])->first();

            if (!$existing) {
                $this->db()->table('permissions')->insert($permission);
            }
        }

        // Buscar IDs das permissões criadas
        $permissionIds = [];
        foreach ($permissions as $permission) {
            $row = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [$permission['key']])->first();
            if ($row) {
                $permissionIds[] = $row['id'];
            }
        }

        // Buscar todas as roles "Proprietário" (sistema e customizadas)
        $roles = $this->db()->table('funcionarios_roles')->select(['id'])->whereRaw("name = 'Proprietário' AND deleted_at IS NULL")->get();

        // Atribuir permissoes a cada role Proprietario (idempotente: pula se ja existe)
        foreach ($roles as $role) {
            foreach ($permissionIds as $permissionId) {
                $exists = $this->db()->table('funcionarios_role_permissions')
                    ->whereRaw('role_id = ? AND permission_id = ?', [$role['id'], $permissionId])
                    ->exists();
                if (!$exists) {
                    $this->db()->table('funcionarios_role_permissions')->insert([
                        'role_id' => $role['id'],
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $permissions = $this->getPermissions();
        $permissionKeys = array_column($permissions, 'key');

        foreach ($permissionKeys as $key) {
            $permission = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [$key])->first();

            if ($permission) {
                // Remover associações com roles
                $this->db()->table('funcionarios_role_permissions')
                    ->whereRaw('permission_id = ?', [$permission['id']])
                    ->delete();

                // Remover permissão
                $this->db()->table('permissions')
                    ->whereRaw('id = ?', [$permission['id']])
                    ->delete();
            }
        }
    }

    private function getPermissions(): array
    {
        return [
            [
                'key' => 'notificacoes.manutencoes_preventivas',
                'name' => 'Manutenções Preventivas',
                'description' => 'Receber notificações de manutenções preventivas',
                'module' => 'notificacoes'
            ],
            [
                'key' => 'notificacoes.autorenovacoes',
                'name' => 'Autorenovações',
                'description' => 'Receber notificações de autorenovações',
                'module' => 'notificacoes'
            ],
            [
                'key' => 'notificacoes.cobranca_clientes',
                'name' => 'Cobrança a Clientes',
                'description' => 'Receber notificações de cobrança a clientes',
                'module' => 'notificacoes'
            ],
            [
                'key' => 'notificacoes.multas_registradas',
                'name' => 'Multas Registradas',
                'description' => 'Receber notificações de multas registradas',
                'module' => 'notificacoes'
            ],
            [
                'key' => 'notificacoes.pagamento_funcionarios',
                'name' => 'Pagamento de Funcionários',
                'description' => 'Receber notificações de pagamento de funcionários',
                'module' => 'notificacoes'
            ],
            [
                'key' => 'notificacoes.taxas_juros_faturas',
                'name' => 'Taxas e Juros em Faturas',
                'description' => 'Receber notificações de taxas e juros em faturas',
                'module' => 'notificacoes'
            ],
        ];
    }
};
