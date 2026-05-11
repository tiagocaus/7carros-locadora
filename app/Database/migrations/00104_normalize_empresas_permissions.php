<?php

/**
 * Migration: Normalizar permissões - Remover módulo "empresas"
 *
 * O módulo "empresas" era duplicado/legado. O correto é "matrizes_filiais".
 *
 * Esta migration:
 * 1. Renomeia empresas.listar_todas → matrizes_filiais.listar_todas
 * 2. Remove permissões órfãs do módulo "empresas"
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Renomear empresas.listar_todas para matrizes_filiais.listar_todas
        $this->db()->table('permissions')
            ->whereRaw('`key` = ?', ['empresas.listar_todas'])
            ->update([
                'key' => 'matrizes_filiais.listar_todas',
                'name' => 'Listar Todas as Matrizes/Filiais',
                'description' => 'Permite visualizar todas as matrizes/filiais independente das filiais vinculadas ao usuário. Usado no dropdown de seleção de filiais.',
                'module' => 'matrizes_filiais',
            ]);

        // 2. Remover permissões órfãs do módulo "empresas"
        $orphanKeys = [
            'empresas.visualizar',
            'empresas.criar',
            'empresas.editar',
            'empresas.excluir',
        ];

        foreach ($orphanKeys as $key) {
            // Buscar ID da permissão
            $permission = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [$key])->first();

            if ($permission) {
                // Remover das roles primeiro
                $this->db()->table('funcionarios_role_permissions')
                    ->whereRaw('permission_id = ?', [$permission['id']])
                    ->delete();

                // Remover a permissão
                $this->db()->table('permissions')
                    ->whereRaw('id = ?', [$permission['id']])
                    ->delete();
            }
        }
    }

    public function down(): void
    {
        // 1. Reverter renomeação
        $this->db()->table('permissions')
            ->whereRaw('`key` = ?', ['matrizes_filiais.listar_todas'])
            ->update([
                'key' => 'empresas.listar_todas',
                'name' => 'Listar Todas as Empresas',
                'description' => 'Permite visualizar todas as matrizes/filiais independente das filiais vinculadas ao usuário.',
                'module' => 'empresas',
            ]);

        // 2. Recriar permissões removidas (idempotente: pula se ja existe)
        $permissions = [
            ['key' => 'empresas.visualizar', 'name' => 'Visualizar Empresas', 'description' => 'Listar e visualizar empresas', 'module' => 'empresas'],
            ['key' => 'empresas.criar', 'name' => 'Criar Empresas', 'description' => 'Adicionar novas empresas', 'module' => 'empresas'],
            ['key' => 'empresas.editar', 'name' => 'Editar Empresas', 'description' => 'Modificar empresas existentes', 'module' => 'empresas'],
            ['key' => 'empresas.excluir', 'name' => 'Excluir Empresas', 'description' => 'Remover empresas do sistema', 'module' => 'empresas'],
        ];

        foreach ($permissions as $perm) {
            $exists = $this->db()->table('permissions')
                ->whereRaw('`key` = ?', [$perm['key']])
                ->exists();
            if (!$exists) {
                $this->db()->table('permissions')->insert($perm);
            }
        }
    }
};
