<?php

use App\Database\Migration;

/**
 * Migration: Atualizar permissões de acessorios para veiculos_acessorios
 *
 * Renomeia as permissões do módulo acessorios para veiculos_acessorios
 * e adiciona a permissão visualizar que estava faltando.
 *
 * Nota: A tabela funcionarios_role_permissions usa permission_id (FK),
 * então as associações são mantidas automaticamente ao atualizar permissions.
 */
return new class extends Migration
{
    private array $permissionRenames = [
        'acessorios.criar' => 'veiculos_acessorios.criar',
        'acessorios.editar' => 'veiculos_acessorios.editar',
        'acessorios.excluir' => 'veiculos_acessorios.excluir',
    ];

    public function up(): void
    {
        // 1. Renomear permissões existentes
        foreach ($this->permissionRenames as $oldKey => $newKey) {
            $this->db()->table('permissions')->whereRaw('`key` = ?', [$oldKey])->update([
                'key' => $newKey,
                'module' => 'veiculos_acessorios',
                'name' => $this->getNewPermissionName($oldKey),
                'description' => $this->getNewPermissionDescription($oldKey),
            ]);
        }

        // 2. Adicionar permissão visualizar (estava faltando)
        $exists = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', ['veiculos_acessorios.visualizar'])->get();

        if (empty($exists)) {
            $this->db()->table('permissions')->insert([
                'key' => 'veiculos_acessorios.visualizar',
                'name' => 'Visualizar Acessórios de Veículos',
                'description' => 'Listar e visualizar acessórios de veículos',
                'module' => 'veiculos_acessorios',
            ]);
        }
    }

    public function down(): void
    {
        // 1. Reverter renomeação das permissões
        $reverseRenames = array_flip($this->permissionRenames);
        foreach ($reverseRenames as $newKey => $oldKey) {
            $this->db()->table('permissions')->whereRaw('`key` = ?', [$newKey])->update([
                'key' => $oldKey,
                'module' => 'acessorios',
                'name' => $this->getOldPermissionName($newKey),
                'description' => $this->getOldPermissionDescription($newKey),
            ]);
        }

        // 2. Remover permissão visualizar
        $this->db()->table('permissions')->whereRaw('`key` = ?', ['veiculos_acessorios.visualizar'])->delete();
    }

    private function getNewPermissionName(string $oldKey): string
    {
        $names = [
            'acessorios.criar' => 'Criar Acessórios de Veículos',
            'acessorios.editar' => 'Editar Acessórios de Veículos',
            'acessorios.excluir' => 'Excluir Acessórios de Veículos',
        ];
        return $names[$oldKey] ?? $oldKey;
    }

    private function getNewPermissionDescription(string $oldKey): string
    {
        $descriptions = [
            'acessorios.criar' => 'Adicionar novos acessórios de veículos',
            'acessorios.editar' => 'Modificar acessórios de veículos existentes',
            'acessorios.excluir' => 'Remover acessórios de veículos do sistema',
        ];
        return $descriptions[$oldKey] ?? $oldKey;
    }

    private function getOldPermissionName(string $newKey): string
    {
        $names = [
            'veiculos_acessorios.criar' => 'Criar Acessórios',
            'veiculos_acessorios.editar' => 'Editar Acessórios',
            'veiculos_acessorios.excluir' => 'Excluir Acessórios',
        ];
        return $names[$newKey] ?? $newKey;
    }

    private function getOldPermissionDescription(string $newKey): string
    {
        $descriptions = [
            'veiculos_acessorios.criar' => 'Adicionar novos acessórios',
            'veiculos_acessorios.editar' => 'Modificar acessórios existentes',
            'veiculos_acessorios.excluir' => 'Remover acessórios do sistema',
        ];
        return $descriptions[$newKey] ?? $newKey;
    }
};
