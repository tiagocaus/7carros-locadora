<?php

namespace App\Models;

/**
 * Model TaxaServicoValorFilial
 *
 * Valor monetario de taxas/servicos (tipo_valor=MON) por filial. Cada filial
 * cadastra o valor na sua moeda. Taxas percentuais (tipo_valor=POR) nao usam
 * esta tabela — continuam com o valor unico em `taxaseservicos.valor`.
 */
class TaxaServicoValorFilial extends Model
{
    public function buscarPorTaxaFilial(int $taxaId, int $filialId): ?array
    {
        return $this->qb
            ->table('taxaseservicos_valores_filiais')
            ->where('id_taxaservico', '=', $taxaId)
            ->where('id_matriz_filial', '=', $filialId)
            ->first();
    }

    public function listarPorTaxa(int $taxaId): array
    {
        return $this->qb
            ->table('taxaseservicos_valores_filiais')
            ->where('id_taxaservico', '=', $taxaId)
            ->orderBy('id_matriz_filial', 'ASC')
            ->get();
    }

    public function listarPorFilial(int $filialId): array
    {
        return $this->qb
            ->table('taxaseservicos_valores_filiais')
            ->where('id_matriz_filial', '=', $filialId)
            ->get();
    }

    public function upsert(array $dados): int
    {
        $taxaId = (int) $dados['id_taxaservico'];
        $filialId = (int) $dados['id_matriz_filial'];
        $chave = $dados['chave'] ?? ($_SESSION['chave'] ?? '');
        $valor = currency_parse($dados['valor'] ?? 0);

        $existente = $this->buscarPorTaxaFilial($taxaId, $filialId);

        if ($existente) {
            $this->qb
                ->table('taxaseservicos_valores_filiais')
                ->where('id', '=', (int) $existente['id'])
                ->update(['valor' => $valor]);
            return (int) $existente['id'];
        }

        return $this->qb
            ->table('taxaseservicos_valores_filiais')
            ->insert([
                'chave' => $chave,
                'id_taxaservico' => $taxaId,
                'id_matriz_filial' => $filialId,
                'valor' => $valor,
            ]);
    }

    /**
     * Remove todas as entries de uma taxa. Usado quando tipo_valor muda de MON pra POR.
     */
    public function excluirPorTaxa(int $taxaId): int
    {
        return $this->qb
            ->table('taxaseservicos_valores_filiais')
            ->withoutChave()
            ->where('id_taxaservico', '=', $taxaId)
            ->delete();
    }

    /**
     * Remove entries de filiais que sairam da lista de participantes.
     */
    public function excluirFiliaisRemovidas(int $taxaId, array $filiaisParticipantes): int
    {
        if (empty($filiaisParticipantes)) {
            return $this->excluirPorTaxa($taxaId);
        }
        return $this->qb
            ->table('taxaseservicos_valores_filiais')
            ->withoutChave()
            ->where('id_taxaservico', '=', $taxaId)
            ->whereNotIn('id_matriz_filial', array_map('intval', $filiaisParticipantes))
            ->delete();
    }

}
