<?php

namespace App\Models;

class SiteConteudo extends Model
{
    /**
     * Busca conteudos de uma pagina em um idioma
     */
    public function buscarPorPaginaIdioma(string $pagina, string $idioma = 'pt_BR'): array
    {
        return $this->qb
            ->table('site_conteudos')
            ->select(['*'])
            ->where('pagina', '=', $pagina)
            ->where('idioma', '=', $idioma)
            ->orderBy('secao', 'ASC')
            ->get();
    }

    /**
     * Lista todos os conteudos por idioma
     */
    public function listarPorIdioma(string $idioma = 'pt_BR'): array
    {
        return $this->qb
            ->table('site_conteudos')
            ->select(['*'])
            ->where('idioma', '=', $idioma)
            ->orderBy('pagina', 'ASC')
            ->orderBy('secao', 'ASC')
            ->get();
    }

    /**
     * Salva conteudo de uma secao (cria ou atualiza)
     */
    public function salvar(string $pagina, string $secao, string $idioma, ?string $conteudo): int
    {
        $existing = $this->qb
            ->table('site_conteudos')
            ->select(['id'])
            ->where('pagina', '=', $pagina)
            ->where('secao', '=', $secao)
            ->where('idioma', '=', $idioma)
            ->first();

        if ($existing) {
            return $this->qb
                ->table('site_conteudos')
                ->where('id', '=', $existing['id'])
                ->update([
                    'conteudo'   => $conteudo,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }

        return $this->qb
            ->table('site_conteudos')
            ->insert([
                'chave'    => $_SESSION['chave'],
                'idioma'   => $idioma,
                'pagina'   => $pagina,
                'secao'    => $secao,
                'conteudo' => $conteudo,
            ]);
    }
}
