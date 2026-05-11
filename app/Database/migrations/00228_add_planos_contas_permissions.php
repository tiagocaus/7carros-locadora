<?php

/**
 * Migration: Adiciona permissões do módulo Planos de Contas
 *
 * Cria as permissões necessárias para o CRUD de planos de contas.
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Executa a migration
     */
    public function up(): void
    {
        $permissions = [
            [
                'key' => 'planos_contas.visualizar',
                'name' => 'Visualizar Planos de Contas',
                'description' => 'Permite visualizar a lista e detalhes dos planos de contas',
                'module' => 'planos_contas',
            ],
            [
                'key' => 'planos_contas.criar',
                'name' => 'Criar Planos de Contas',
                'description' => 'Permite criar novos planos de contas',
                'module' => 'planos_contas',
            ],
            [
                'key' => 'planos_contas.editar',
                'name' => 'Editar Planos de Contas',
                'description' => 'Permite editar planos de contas existentes',
                'module' => 'planos_contas',
            ],
            [
                'key' => 'planos_contas.excluir',
                'name' => 'Excluir Planos de Contas',
                'description' => 'Permite excluir planos de contas',
                'module' => 'planos_contas',
            ],
        ];

        foreach ($permissions as $permission) {
            // Verificar se já existe
            $existing = $this->db()->table('permissions')
                ->select(['id'])
                ->whereRaw('`key` = ?', [$permission['key']])
                ->first();

            if (!$existing) {
                $this->db()->table('permissions')->insert($permission);
            }
        }
    }

    /**
     * Reverte a migration
     */
    public function down(): void
    {
        $keys = [
            'planos_contas.visualizar',
            'planos_contas.criar',
            'planos_contas.editar',
            'planos_contas.excluir',
        ];

        foreach ($keys as $key) {
            $this->db()->table('permissions')
                ->whereRaw('`key` = ?', [$key])
                ->delete();
        }
    }
};
