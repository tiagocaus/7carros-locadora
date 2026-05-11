<?php

/**
 * Migration: Adicionar permissão "Listar Todas as Empresas"
 *
 * Essa permissão permite que usuários vejam TODAS as matrizes/filiais
 * no dropdown de seleção, independente das filiais vinculadas ao seu cadastro.
 *
 * Uso: Tela de Adicionar/Editar Funcionário - dropdown de filiais
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Adicionar nova permissao ao modulo "empresas" (idempotente: pula se ja existe)
        $exists = $this->db()->table('permissions')
            ->whereRaw('`key` = ?', ['empresas.listar_todas'])
            ->exists();

        if (!$exists) {
            $this->db()->table('permissions')->insert([
                'key' => 'empresas.listar_todas',
                'name' => 'Listar Todas as Empresas',
                'description' => 'Permite visualizar todas as matrizes/filiais independente das filiais vinculadas ao usuário. Usado no dropdown de seleção de filiais em telas como Funcionários.',
                'module' => 'empresas',
            ]);
        }
    }

    public function down(): void
    {
        // Primeiro, remover a permissão das roles que a possuem
        $permission = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', ['empresas.listar_todas'])->first();

        if ($permission) {
            $this->db()->table('funcionarios_role_permissions')
                ->whereRaw('permission_id = ?', [$permission['id']])
                ->delete();
        }

        // Depois, remover a permissão
        $this->db()->table('permissions')
            ->whereRaw('`key` = ?', ['empresas.listar_todas'])
            ->delete();
    }
};
