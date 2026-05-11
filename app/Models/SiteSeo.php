<?php

namespace App\Models;

class SiteSeo extends Model
{
    /**
     * Busca SEO de uma pagina em um idioma
     */
    public function buscarPorPaginaIdioma(string $pagina, string $idioma = 'pt_BR'): ?array
    {
        $row = $this->qb
            ->table('site_seo')
            ->select(['*'])
            ->where('pagina', '=', $pagina)
            ->where('idioma', '=', $idioma)
            ->first();

        if ($row && !empty($row['dados_estruturados'])) {
            $row['dados_estruturados'] = json_decode($row['dados_estruturados'], true);
        }

        return $row;
    }

    /**
     * Lista SEO de todas as paginas por idioma
     */
    public function listarPorIdioma(string $idioma = 'pt_BR'): array
    {
        $rows = $this->qb
            ->table('site_seo')
            ->select(['*'])
            ->where('idioma', '=', $idioma)
            ->orderBy('pagina', 'ASC')
            ->get();

        foreach ($rows as &$row) {
            if (!empty($row['dados_estruturados'])) {
                $row['dados_estruturados'] = json_decode($row['dados_estruturados'], true);
            }
        }

        return $rows;
    }

    /**
     * Salva SEO de uma pagina (cria ou atualiza)
     */
    public function salvar(string $pagina, string $idioma, array $dados): int
    {
        if (isset($dados['dados_estruturados']) && is_array($dados['dados_estruturados'])) {
            $dados['dados_estruturados'] = json_encode($dados['dados_estruturados']);
        }

        $existing = $this->qb
            ->table('site_seo')
            ->select(['id'])
            ->where('pagina', '=', $pagina)
            ->where('idioma', '=', $idioma)
            ->first();

        if ($existing) {
            $dados['updated_at'] = date('Y-m-d H:i:s');
            return $this->qb
                ->table('site_seo')
                ->where('id', '=', $existing['id'])
                ->update($dados);
        }

        $dados['chave'] = $_SESSION['chave'];
        $dados['pagina'] = $pagina;
        $dados['idioma'] = $idioma;
        return $this->qb
            ->table('site_seo')
            ->insert($dados);
    }
}
