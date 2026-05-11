<?php

use App\Database\Migration;

/**
 * Migration: Migrar dados de funcao (texto) para role_id (FK)
 *
 * Converte o campo funcao (VARCHAR) para role_id (FK para roles.id)
 * Mapeia cada valor de texto para o ID correspondente na tabela roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Buscar todas as roles
        $roles = $this->db()->table('roles')->select(['id', 'LOWER(name) as name_lower'])->get();

        // Criar mapeamento funcao → role_id
        $roleMap = [];
        foreach ($roles as $role) {
            $roleMap[$role['name_lower']] = $role['id'];
        }

        // Buscar ID da role "Sem Função"
        $defaultRole = $this->db()->table('roles')->select(['id'])->whereRaw('name = ?', ['Sem Função'])->first();
        $defaultRoleId = $defaultRole ? $defaultRole['id'] : null;

        // Buscar todos os funcionários
        $funcionarios = $this->db()->table('funcionarios')->select(['id', 'funcao'])->get();

        foreach ($funcionarios as $funcionario) {
            $funcId = $funcionario['id'];
            $funcao = trim($funcionario['funcao'] ?? '');
            $funcaoNormalizada = strtolower($funcao);

            // Determinar role_id
            $roleId = null;

            if (!empty($funcao) && isset($roleMap[$funcaoNormalizada])) {
                // Função existe na tabela roles
                $roleId = $roleMap[$funcaoNormalizada];
            } elseif ($defaultRoleId) {
                // Atribuir role padrão "Sem Função"
                $roleId = $defaultRoleId;
            }

            // Atualizar role_id se foi determinado
            if ($roleId) {
                $this->db()->table('funcionarios')->whereRaw('id = ?', [$funcId])->update(['role_id' => $roleId]);
            }
        }
    }

    public function down(): void
    {
        // Limpar role_id de todos os funcionários
        $this->db()->table('funcionarios')->whereRaw('1=1')->update(['role_id' => null]);
    }
};
