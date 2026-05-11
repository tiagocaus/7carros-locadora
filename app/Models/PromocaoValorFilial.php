<?php

namespace App\Models;

/**
 * Model PromocaoValorFilial
 *
 * Valor monetario de promocoes fixas (tipo FIX) por filial. Cada filial
 * cadastra o valor na sua moeda. Promocoes percentuais (tipo PER) nao usam
 * esta tabela — continuam com o valor unico em `promocoes.valor`.
 */
class PromocaoValorFilial extends Model
{
    /**
     * Busca valor de uma promocao em uma filial
     */
    public function buscarPorPromocaoFilial(int $promocaoId, int $filialId): ?array
    {
        return $this->qb
            ->table('promocoes_valores_filiais')
            ->where('id_promocao', '=', $promocaoId)
            ->where('id_matriz_filial', '=', $filialId)
            ->first();
    }

    /**
     * Lista todas as filiais (com valor) de uma promocao
     */
    public function listarPorPromocao(int $promocaoId): array
    {
        return $this->qb
            ->table('promocoes_valores_filiais')
            ->where('id_promocao', '=', $promocaoId)
            ->orderBy('id_matriz_filial', 'ASC')
            ->get();
    }

    /**
     * Insere ou atualiza o valor de uma promocao em uma filial
     */
    public function upsert(array $dados): int
    {
        $promocaoId = (int) $dados['id_promocao'];
        $filialId = (int) $dados['id_matriz_filial'];
        $chave = $dados['chave'] ?? ($_SESSION['chave'] ?? '');
        $valor = currency_parse($dados['valor'] ?? 0);

        $existente = $this->buscarPorPromocaoFilial($promocaoId, $filialId);

        if ($existente) {
            $this->qb
                ->table('promocoes_valores_filiais')
                ->where('id', '=', (int) $existente['id'])
                ->update(['valor' => $valor]);
            return (int) $existente['id'];
        }

        return $this->qb
            ->table('promocoes_valores_filiais')
            ->insert([
                'chave' => $chave,
                'id_promocao' => $promocaoId,
                'id_matriz_filial' => $filialId,
                'valor' => $valor,
            ]);
    }

    /**
     * Remove registro (raramente usado — FK CASCADE trata deletes)
     */
    public function excluir(int $id): int
    {
        return $this->qb
            ->table('promocoes_valores_filiais')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Remove todas as entries de uma promocao.
     * Usado quando tipo muda de DFIX pra DPOR.
     */
    public function excluirPorPromocao(int $promocaoId): int
    {
        return $this->qb
            ->table('promocoes_valores_filiais')
            ->withoutChave()
            ->where('id_promocao', '=', $promocaoId)
            ->delete();
    }

    /**
     * Remove entries de filiais que saíram do pivot de participantes.
     */
    public function excluirFiliaisRemovidas(int $promocaoId, array $filiaisParticipantes): int
    {
        if (empty($filiaisParticipantes)) {
            return $this->excluirPorPromocao($promocaoId);
        }
        return $this->qb
            ->table('promocoes_valores_filiais')
            ->withoutChave()
            ->where('id_promocao', '=', $promocaoId)
            ->whereNotIn('id_matriz_filial', array_map('intval', $filiaisParticipantes))
            ->delete();
    }

}
