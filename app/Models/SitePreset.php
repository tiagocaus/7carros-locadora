<?php

namespace App\Models;

class SitePreset extends Model
{
    /**
     * Lista presets customizados do tenant
     */
    public function listar(): array
    {
        $rows = $this->qb
            ->table('site_presets')
            ->select(['*'])
            ->orderBy('nome', 'ASC')
            ->get();

        foreach ($rows as &$row) {
            if (!empty($row['cores'])) {
                $row['cores'] = json_decode($row['cores'], true);
            }
        }

        return $rows;
    }

    /**
     * Busca preset por nome
     */
    public function buscarPorNome(string $nome): ?array
    {
        $row = $this->qb
            ->table('site_presets')
            ->select(['*'])
            ->where('nome', '=', $nome)
            ->first();

        if ($row && !empty($row['cores'])) {
            $row['cores'] = json_decode($row['cores'], true);
        }

        return $row;
    }

    /**
     * Busca preset por ID
     */
    public function buscarPorId(int $id): ?array
    {
        $row = $this->qb
            ->table('site_presets')
            ->select(['*'])
            ->where('id', '=', $id)
            ->first();

        if ($row && !empty($row['cores'])) {
            $row['cores'] = json_decode($row['cores'], true);
        }

        return $row;
    }

    /**
     * Cria um preset customizado
     */
    public function criar(string $nome, array $cores): int
    {
        return $this->qb
            ->table('site_presets')
            ->insert([
                'chave' => $_SESSION['chave'],
                'nome'  => $nome,
                'cores' => json_encode($cores),
            ]);
    }

    /**
     * Exclui um preset customizado
     */
    public function excluir(int $id): int
    {
        return $this->qb
            ->table('site_presets')
            ->where('id', '=', $id)
            ->delete();
    }
}
