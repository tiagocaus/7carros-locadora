<?php

namespace App\Models;

/**
 * Model para gerenciamento de Roles (Funções)
 *
 * Tabela: funcionarios_roles
 * Multi-tenancy: Usa withGlobals() para incluir roles de sistema (chave='0')
 */
class Role extends Model
{
    public const SUPPORT_ROLE_NAME = 'Suporte 7Carros';
    public const SUPPORT_ROLE_DESCRIPTION = 'Funcao temporaria para acesso do suporte tecnico';

    public static function isSupportRoleName(string $name): bool
    {
        return strtolower(trim($name)) === strtolower(self::SUPPORT_ROLE_NAME);
    }

    public static function isSupportRole(?array $role): bool
    {
        return $role !== null && isset($role['name']) && self::isSupportRoleName($role['name']);
    }

    /**
     * Lista todas as roles visíveis para o tenant
     *
     * Retorna:
     * - Roles de sistema (chave='0') que NÃO foram customizadas pelo tenant
     * - Roles do tenant (customizadas ou criadas por ele)
     */
    public function listar(string $chave): array
    {
        return $this->qb
            ->table('funcionarios_roles', 'r')
            ->select([
                'r.id',
                'r.chave',
                'r.name',
                'r.description',
                'r.created_at'
            ])
            ->selectRaw("CASE WHEN r.chave = '0' THEN 1 ELSE 0 END as is_system")
            ->selectRaw("CASE WHEN r.chave != '0' AND EXISTS (
                SELECT 1 FROM funcionarios_roles rs WHERE rs.chave = '0' AND rs.name = r.name
            ) THEN 1 ELSE 0 END as is_customization")
            ->withoutChave()
            ->where('r.name', '!=', self::SUPPORT_ROLE_NAME)
            ->whereNested(function ($q) use ($chave) {
                // Roles de sistema não customizadas pelo tenant
                $q->whereNested(function ($sub) use ($chave) {
                    $sub->where('r.chave', '=', '0')
                        ->whereRaw('r.name NOT IN (
                            SELECT name FROM funcionarios_roles WHERE chave = ?
                        )', [$chave]);
                })
                // Roles do tenant
                ->orWhere('r.chave', '=', $chave);
            })
            ->orderByRaw('is_system DESC, r.name ASC')
            ->get();
    }

    /**
     * Busca role por ID (do tenant OU de sistema)
     */
    public function buscarPorId(int $id, string $chave): ?array
    {
        return $this->qb
            ->table('funcionarios_roles')
            ->select(['id', 'chave', 'name', 'description'])
            ->withoutChave()
            ->where('id', '=', $id)
            ->whereNested(function ($q) use ($chave) {
                $q->where('chave', '=', $chave)
                  ->orWhere('chave', '=', '0');
            })
            ->first();
    }

    /**
     * Busca a role reservada de suporte no tenant
     */
    public function buscarRoleSuporte(string $chave): ?array
    {
        return $this->qb
            ->table('funcionarios_roles')
            ->select(['id', 'chave', 'name', 'description'])
            ->where('name', '=', self::SUPPORT_ROLE_NAME)
            ->first();
    }

    /**
     * Busca role por ID (qualquer chave, para verificação)
     */
    public function buscarPorIdSemRestricao(int $id): ?array
    {
        return $this->qb
            ->table('funcionarios_roles')
            ->select(['id', 'chave', 'name'])
            ->withoutChave()
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Busca role por nome no tenant
     */
    public function buscarPorNome(string $nome, string $chave): ?array
    {
        return $this->qb
            ->table('funcionarios_roles')
            ->select(['id'])
            ->where('name', '=', $nome)
            ->first();
    }

    /**
     * Busca role por nome excluindo um ID específico
     */
    public function buscarPorNomeExcluindoId(string $nome, string $chave, int $excludeId): ?array
    {
        return $this->qb
            ->table('funcionarios_roles')
            ->select(['id'])
            ->where('name', '=', $nome)
            ->where('id', '!=', $excludeId)
            ->first();
    }

    /**
     * Busca role de sistema por nome
     */
    public function buscarRoleSistema(string $nome): ?array
    {
        return $this->qb
            ->table('funcionarios_roles')
            ->select(['id'])
            ->withoutChave()
            ->where('chave', '=', '0')
            ->where('name', '=', $nome)
            ->first();
    }

    /**
     * Verifica se existe customização (role do tenant com mesmo nome de sistema)
     */
    public function existeCustomizacao(string $nome, string $chave): ?array
    {
        return $this->buscarPorNome($nome, $chave);
    }

    /**
     * Cria nova role
     */
    public function criar(string $chave, string $nome, string $descricao): int
    {
        return $this->qb
            ->table('funcionarios_roles')
            ->withoutChave()
            ->insert([
                'chave' => $chave,
                'name' => $nome,
                'description' => $descricao,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
    }

    /**
     * Atualiza role (nome e descrição)
     */
    public function atualizar(int $id, string $nome, string $descricao): int
    {
        return $this->qb
            ->table('funcionarios_roles')
            ->withoutChave()
            ->where('id', '=', $id)
            ->update([
                'name' => $nome,
                'description' => $descricao,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
    }

    /**
     * Atualiza apenas descrição da role
     */
    public function atualizarDescricao(int $id, string $descricao): int
    {
        return $this->qb
            ->table('funcionarios_roles')
            ->withoutChave()
            ->where('id', '=', $id)
            ->update([
                'description' => $descricao,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
    }

    /**
     * Exclui role
     */
    public function deletar(int $id): int
    {
        return $this->qb
            ->table('funcionarios_roles')
            ->withoutChave()
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Conta funcionários vinculados a uma role
     */
    public function contarFuncionarios(int $roleId, string $chave): int
    {
        return $this->qb
            ->table('funcionarios')
            ->where('id_role', '=', $roleId)
            ->count();
    }

    /**
     * Migra funcionários de uma role para outra
     */
    public function migrarFuncionarios(int $roleOrigem, int $roleDestino, string $chave): int
    {
        return $this->qb
            ->table('funcionarios')
            ->where('id_role', '=', $roleOrigem)
            ->update(['id_role' => $roleDestino]);
    }

    /**
     * Lista roles para select (dropdown)
     */
    public function listarParaSelect(string $chave): array
    {
        return $this->qb
            ->table('funcionarios_roles')
            ->select(['id', 'name'])
            ->withGlobals()
            ->where('name', '!=', self::SUPPORT_ROLE_NAME)
            ->orderBy('name', 'ASC')
            ->get();
    }

    /**
     * Inicia transação
     */
    public function beginTransaction(): void
    {
        $this->qb->beginTransaction();
    }

    /**
     * Confirma transação
     */
    public function commit(): void
    {
        $this->qb->commit();
    }

    /**
     * Reverte transação
     */
    public function rollback(): void
    {
        $this->qb->rollback();
    }
}
