<?php

namespace App\Models;

class SiteAparencia extends Model
{
    /**
     * Busca aparencia do tenant
     */
    public function buscarPorChave(): ?array
    {
        $row = $this->qb
            ->table('site_aparencia')
            ->select(['*'])
            ->first();

        if ($row && !empty($row['cores_customizadas'])) {
            $row['cores_customizadas'] = json_decode($row['cores_customizadas'], true);
        }

        return $row;
    }

    /**
     * Cria ou atualiza aparencia
     */
    public function criarOuAtualizar(array $dados): int
    {
        if (isset($dados['cores_customizadas']) && is_array($dados['cores_customizadas'])) {
            $dados['cores_customizadas'] = json_encode($dados['cores_customizadas']);
        }

        $existing = $this->buscarPorChave();

        if ($existing) {
            $dados['updated_at'] = now();
            return $this->qb
                ->table('site_aparencia')
                ->where('id', '=', $existing['id'])
                ->update($dados);
        }

        $dados['chave'] = $_SESSION['chave'];
        return $this->qb
            ->table('site_aparencia')
            ->insert($dados);
    }

    /**
     * Atualiza campos especificos
     */
    public function atualizar(array $dados): int
    {
        if (isset($dados['cores_customizadas']) && is_array($dados['cores_customizadas'])) {
            $dados['cores_customizadas'] = json_encode($dados['cores_customizadas']);
        }

        $dados['updated_at'] = now();
        return $this->qb
            ->table('site_aparencia')
            ->update($dados);
    }

    /**
     * Reset CSS: salva backup e limpa customizado
     */
    public function resetCss(): int
    {
        $current = $this->buscarPorChave();
        if (!$current) {
            return 0;
        }

        return $this->qb
            ->table('site_aparencia')
            ->where('id', '=', $current['id'])
            ->update([
                'css_customizado_backup' => $current['css_customizado'],
                'css_customizado'        => null,
                'updated_at'             => now(),
            ]);
    }

    /**
     * Undo reset: restaura CSS do backup
     */
    public function undoCssReset(): int
    {
        $current = $this->buscarPorChave();
        if (!$current || empty($current['css_customizado_backup'])) {
            return 0;
        }

        return $this->qb
            ->table('site_aparencia')
            ->where('id', '=', $current['id'])
            ->update([
                'css_customizado'        => $current['css_customizado_backup'],
                'css_customizado_backup' => null,
                'updated_at'             => now(),
            ]);
    }
}
