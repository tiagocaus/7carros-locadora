<?php

namespace App\Models;

class SiteIdioma extends Model
{
    /**
     * Lista todos os idiomas do tenant
     */
    public function listar(): array
    {
        return $this->qb
            ->table('site_idiomas')
            ->select(['*'])
            ->orderBy('ordem', 'ASC')
            ->get();
    }

    /**
     * Lista apenas idiomas ativos
     */
    public function listarAtivos(): array
    {
        return $this->qb
            ->table('site_idiomas')
            ->select(['*'])
            ->where('ativo', '=', 1)
            ->orderBy('ordem', 'ASC')
            ->get();
    }

    /**
     * Salva lista de idiomas (substitui todos do tenant)
     */
    public function salvar(array $idiomas): void
    {
        $chave = $_SESSION['chave'];

        // Remove todos os existentes
        $this->qb
            ->table('site_idiomas')
            ->delete();

        // Insere os novos
        foreach ($idiomas as $ordem => $item) {
            $this->qb
                ->table('site_idiomas')
                ->insert([
                    'chave'  => $chave,
                    'idioma' => $item['idioma'],
                    'ativo'  => $item['ativo'] ?? 1,
                    'ordem'  => $ordem,
                ]);
        }
    }
}
