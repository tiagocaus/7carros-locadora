<?php

namespace App\Models;

class SiteLink extends Model
{
    /**
     * Lista todos os links do tenant
     */
    public function listar(): array
    {
        return $this->qb
            ->table('site_links')
            ->select(['*'])
            ->orderBy('ordem', 'ASC')
            ->get();
    }

    /**
     * Lista apenas links ativos
     */
    public function listarAtivos(): array
    {
        return $this->qb
            ->table('site_links')
            ->select(['*'])
            ->where('ativo', '=', 1)
            ->orderBy('ordem', 'ASC')
            ->get();
    }

    /**
     * Salva todos os links (substitui os existentes)
     */
    public function salvarTodos(array $links): void
    {
        $chave = $_SESSION['chave'];

        // Remove todos os existentes
        $this->qb
            ->table('site_links')
            ->delete();

        // Insere os novos
        foreach ($links as $ordem => $link) {
            if (empty($link['url'])) {
                continue;
            }

            $this->qb
                ->table('site_links')
                ->insert([
                    'chave' => $chave,
                    'tipo'  => $link['tipo'],
                    'url'   => $link['url'],
                    'ativo' => $link['ativo'] ?? 1,
                    'ordem' => $ordem,
                ]);
        }
    }
}
