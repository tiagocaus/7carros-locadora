<?php

namespace App\Models;

class SiteIntegracao extends Model
{
    /**
     * Lista todas as integracoes do tenant
     */
    public function listar(): array
    {
        return $this->qb
            ->table('site_integracoes')
            ->select(['*'])
            ->orderBy('tipo', 'ASC')
            ->orderBy('ordem', 'ASC')
            ->get();
    }

    /**
     * Lista integracoes ativas agrupadas por tipo
     */
    public function listarAtivos(): array
    {
        return $this->qb
            ->table('site_integracoes')
            ->select(['*'])
            ->where('ativo', '=', 1)
            ->orderBy('tipo', 'ASC')
            ->orderBy('ordem', 'ASC')
            ->get();
    }

    /**
     * Busca integracao por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('site_integracoes')
            ->select(['*'])
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Cria nova integracao
     */
    public function criar(array $dados): int
    {
        $dados['chave'] = $_SESSION['chave'];
        return $this->qb
            ->table('site_integracoes')
            ->insert($dados);
    }

    /**
     * Atualiza integracao existente
     */
    public function atualizar(int $id, array $dados): int
    {
        $dados['updated_at'] = now();
        return $this->qb
            ->table('site_integracoes')
            ->where('id', '=', $id)
            ->update($dados);
    }

    /**
     * Exclui integracao
     */
    public function excluir(int $id): int
    {
        return $this->qb
            ->table('site_integracoes')
            ->where('id', '=', $id)
            ->delete();
    }
}
