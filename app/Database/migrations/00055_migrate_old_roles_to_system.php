<?php

use App\Database\Migration;

/**
 * Migration: Migrar funcionários das roles antigas para as roles de sistema
 *
 * Mapeamento:
 * - Proprietário → Proprietário (sistema)
 * - Atendente → Atendente (sistema)
 * - Atend_gerente → Gerente (sistema)
 * - Atend_subgere → Coordenador Administrativo (sistema)
 * - Coord_admin → Coordenador Administrativo (sistema)
 * - Assist_admin → Assistente Administrativo (sistema)
 * - Vistoriador → Vistoriador (sistema)
 * - Manobrador → Manobrador (sistema)
 * - Serv_geral → Serviços Gerais (sistema)
 * - Sem Função → Serviços Gerais (sistema)
 */
return new class extends Migration
{
    /**
     * Mapeamento de roles antigas para novas (sistema)
     */
    private function getRoleMapping(): array
    {
        return [
            'Proprietário' => 'Proprietário',
            'Atendente' => 'Atendente',
            'Atend_gerente' => 'Gerente',
            'Atend_subgere' => 'Coordenador Administrativo',
            'Coord_admin' => 'Coordenador Administrativo',
            'Assist_admin' => 'Assistente Administrativo',
            'Vistoriador' => 'Vistoriador',
            'Manobrador' => 'Manobrador',
            'Serv_geral' => 'Serviços Gerais',
            'Sem Função' => 'Serviços Gerais',
        ];
    }

    public function up(): void
    {
        $mapping = $this->getRoleMapping();

        // Buscar roles de sistema
        $systemRoles = $this->db()->table('roles')->select(['id', 'name'])->whereRaw('chave = ? AND is_system = 1', ['system'])->get();

        $systemRoleMap = [];
        foreach ($systemRoles as $role) {
            $systemRoleMap[$role['name']] = $role['id'];
        }

        // Buscar roles antigas (não são de sistema)
        $oldRoles = $this->db()->table('roles')->select(['id', 'name', 'chave'])->whereRaw('is_system = 0 AND chave != ?', ['system'])->get();

        foreach ($oldRoles as $oldRole) {
            $oldRoleName = $oldRole['name'];
            $oldRoleId = $oldRole['id'];

            // Verificar se existe mapeamento
            if (!isset($mapping[$oldRoleName])) {
                // Role customizada que não está no mapeamento - manter
                continue;
            }

            $newRoleName = $mapping[$oldRoleName];

            if (!isset($systemRoleMap[$newRoleName])) {
                // Role de sistema não encontrada - pular
                continue;
            }

            $newRoleId = $systemRoleMap[$newRoleName];

            // Atualizar funcionários que usam a role antiga
            $this->db()->table('funcionarios')
                ->whereRaw('id_role = ?', [$oldRoleId])
                ->update(['id_role' => $newRoleId]);

            // Remover permissões da role antiga
            $this->db()->table('role_permissions')->whereRaw('role_id = ?', [$oldRoleId])->delete();

            // Remover role antiga
            $this->db()->table('roles')->whereRaw('id = ?', [$oldRoleId])->delete();
        }
    }

    public function down(): void
    {
        // Esta migration não pode ser revertida de forma simples
        // pois as roles antigas foram excluídas.
        // Para reverter, seria necessário recriar as roles antigas
        // e remapear os funcionários, o que não é prático.

        // Não fazer nada no rollback
    }
};
