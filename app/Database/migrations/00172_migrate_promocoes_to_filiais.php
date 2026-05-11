<?php

use App\Database\Migration;

/**
 * Migration: Vincular promoções existentes às filiais
 *
 * Para cada promoção que não tem vínculos em promocoes_filiais,
 * cria registros vinculando a todas as filiais do mesmo tenant (chave).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Buscar promoções que não têm vínculos em promocoes_filiais
        $promocoesSemVinculos = $this->db()
            ->table('promocoes', 'p')
            ->select(['p.id', 'p.chave'])
            ->leftJoin('promocoes_filiais', 'pf', 'p.id', '=', 'pf.id_promocao')
            ->withoutChave()
            ->whereRaw('pf.id IS NULL')
            ->get();

        if (empty($promocoesSemVinculos)) {
            return;
        }

        // Agrupar promoções por chave (tenant)
        $promocoesPorChave = [];
        foreach ($promocoesSemVinculos as $promocao) {
            $chave = $promocao['chave'];
            if (!isset($promocoesPorChave[$chave])) {
                $promocoesPorChave[$chave] = [];
            }
            $promocoesPorChave[$chave][] = $promocao['id'];
        }

        // Para cada tenant, buscar suas filiais e vincular às promoções
        foreach ($promocoesPorChave as $chave => $promocaoIds) {
            // Buscar todas as filiais deste tenant
            $filiais = $this->db()
                ->table('matrizes_filiais')
                ->select(['id'])
                ->withoutChave()
                ->whereRaw('chave = ?', [$chave])
                ->get();

            if (empty($filiais)) {
                continue;
            }

            // Criar vínculos para cada combinação promoção x filial
            foreach ($promocaoIds as $promocaoId) {
                foreach ($filiais as $filial) {
                    // Verificar se já não existe (segurança)
                    $existe = $this->db()
                        ->table('promocoes_filiais')
                        ->select(['id'])
                        ->withoutChave()
                        ->whereRaw('id_promocao = ? AND id_matriz_filial = ?', [$promocaoId, $filial['id']])
                        ->first();

                    if (!$existe) {
                        $this->db()->table('promocoes_filiais')->insert([
                            'id_promocao' => $promocaoId,
                            'id_matriz_filial' => $filial['id'],
                            'chave' => $chave,
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Não é possível reverter automaticamente pois não sabemos
        // quais registros foram criados por esta migração vs. manualmente
    }
};
