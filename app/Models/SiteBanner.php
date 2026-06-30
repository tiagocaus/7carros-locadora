<?php

namespace App\Models;

class SiteBanner extends Model
{
    /**
     * Lista todos os banners do tenant
     */
    public function listar(?string $idioma = null): array
    {
        $query = $this->qb
            ->table('site_banners')
            ->select(['*']);

        if ($idioma) {
            $query->where('idioma', '=', $idioma);
        }

        return $query
            ->orderBy('ordem', 'ASC')
            ->get();
    }

    /**
     * Lista banners ativos
     */
    public function listarAtivos(?string $idioma = null): array
    {
        $query = $this->qb
            ->table('site_banners')
            ->select(['*'])
            ->where('ativo', '=', 1);

        if ($idioma) {
            $query->where('idioma', '=', $idioma);
        }

        return $query
            ->orderBy('ordem', 'ASC')
            ->get();
    }

    /**
     * Busca banner por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('site_banners')
            ->select(['*'])
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Cria novo banner
     */
    public function criar(array $dados): int
    {
        $dados['chave'] = $_SESSION['chave'];
        return $this->qb
            ->table('site_banners')
            ->insert($dados);
    }

    /**
     * Atualiza banner existente
     */
    public function atualizar(int $id, array $dados): int
    {
        $dados['updated_at'] = now();
        return $this->qb
            ->table('site_banners')
            ->where('id', '=', $id)
            ->update($dados);
    }

    /**
     * Exclui banner
     */
    public function excluir(int $id): int
    {
        return $this->qb
            ->table('site_banners')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Reordena banners
     *
     * @param array $ordens Array de [id => ordem]
     */
    public function reordenar(array $ordens): void
    {
        foreach ($ordens as $id => $ordem) {
            $this->qb
                ->table('site_banners')
                ->where('id', '=', (int) $id)
                ->update([
                    'ordem'      => (int) $ordem,
                    'updated_at' => now(),
                ]);
        }
    }
}
