<?php

namespace App\Models;

/**
 * Model LocacaoTaxaServico
 *
 * Gerencia taxas e servicos adicionais vinculados a uma locacao.
 * Cada taxa pode ter quantidade e valor unitario.
 *
 * Campos de calculo:
 * - base_calculo: FIX (fixo), PER (por periodo), VLT (valor total)
 * - tipo_valor: MON (moeda), POR (porcentagem)
 */
class LocacaoTaxaServico extends Model
{
    /**
     * Lista todas as taxas de uma locacao
     *
     * @param int $locacaoId ID da locacao
     * @return array Lista de taxas
     */
    public function listarPorLocacao(int $locacaoId): array
    {
        return $this->qb
            ->table('locacoes_taxaseservicos', 'lts')
            ->select([
                'lts.*',
                't.nome AS taxa_nome_original',
                't.base_calculo AS taxa_base_calculo_original',
                't.tipo_valor AS taxa_tipo_valor_original'
            ])
            ->leftJoin('taxaseservicos', 't', 'lts.id_taxa', '=', 't.id')
            ->where('lts.id_locacao', '=', $locacaoId)
            ->orderBy('lts.nome', 'ASC')
            ->get();
    }

    /**
     * Busca uma taxa da locacao por ID
     *
     * @param int $id ID do registro
     * @return array|null Dados ou null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('locacoes_taxaseservicos', 'lts')
            ->select([
                'lts.*',
                't.nome AS taxa_nome_original',
                't.base_calculo AS taxa_base_calculo_original',
                't.tipo_valor AS taxa_tipo_valor_original'
            ])
            ->leftJoin('taxaseservicos', 't', 'lts.id_taxa', '=', 't.id')
            ->where('lts.id', '=', $id)
            ->first();
    }

    /**
     * Adiciona uma taxa a locacao
     *
     * @param int $locacaoId ID da locacao
     * @param array $dados Dados da taxa
     * @param string $chave Chave do tenant
     * @return int ID criado
     */
    public function adicionar(int $locacaoId, array $dados, string $chave): int
    {
        $quantidade = (int) ($dados['quantidade'] ?? 1);
        $valorUnitario = currency_parse($dados['valor_unitario'] ?? 0);
        $valorTotal = isset($dados['valor_total'])
            ? currency_parse($dados['valor_total'])
            : $quantidade * $valorUnitario;

        return $this->qb
            ->table('locacoes_taxaseservicos')
            ->insert([
                'chave' => $chave,
                'id_locacao' => $locacaoId,
                'id_taxa' => !empty($dados['id_taxa']) ? (int) $dados['id_taxa'] : null,
                'base_calculo' => $dados['base_calculo'] ?? 'FIX',
                'tipo_valor' => $dados['tipo_valor'] ?? 'MON',
                'nome' => $dados['nome'] ?? '',
                'quantidade' => $quantidade,
                'valor_unitario' => $valorUnitario,
                'valor_total' => $valorTotal,
            ]);
    }

    /**
     * Atualiza uma taxa da locacao
     *
     * @param int $id ID do registro
     * @param array $dados Dados a atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $dadosUpdate = [];

        if (isset($dados['id_taxa'])) {
            $dadosUpdate['id_taxa'] = !empty($dados['id_taxa']) ? (int) $dados['id_taxa'] : null;
        }
        if (isset($dados['nome'])) {
            $dadosUpdate['nome'] = $dados['nome'];
        }
        if (isset($dados['base_calculo'])) {
            $dadosUpdate['base_calculo'] = $dados['base_calculo'];
        }
        if (isset($dados['tipo_valor'])) {
            $dadosUpdate['tipo_valor'] = $dados['tipo_valor'];
        }
        if (isset($dados['quantidade'])) {
            $dadosUpdate['quantidade'] = (int) $dados['quantidade'];
        }
        if (isset($dados['valor_unitario'])) {
            $dadosUpdate['valor_unitario'] = currency_parse($dados['valor_unitario']);
        }

        // Recalcular total se quantidade ou valor foi alterado
        if (isset($dados['quantidade']) || isset($dados['valor_unitario'])) {
            $taxa = $this->buscarPorId($id);
            $quantidade = $dados['quantidade'] ?? $taxa['quantidade'];
            $valorUnitario = isset($dados['valor_unitario']) ? currency_parse($dados['valor_unitario']) : $taxa['valor_unitario'];
            $dadosUpdate['valor_total'] = $quantidade * $valorUnitario;
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        return $this->qb
            ->table('locacoes_taxaseservicos')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Remove uma taxa da locacao
     *
     * @param int $id ID do registro
     * @return int Linhas afetadas
     */
    public function remover(int $id): int
    {
        return $this->qb
            ->table('locacoes_taxaseservicos')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Remove todas as taxas de uma locacao
     *
     * @param int $locacaoId ID da locacao
     * @return int Linhas afetadas
     */
    public function removerTodas(int $locacaoId): int
    {
        return $this->qb
            ->table('locacoes_taxaseservicos')
            ->where('id_locacao', '=', $locacaoId)
            ->delete();
    }

    /**
     * Sincroniza taxas da locacao (remove antigas e adiciona novas)
     *
     * @param int $locacaoId ID da locacao
     * @param array $taxas Lista de taxas com dados
     * @param string $chave Chave do tenant
     * @return array IDs das taxas criadas
     */
    public function sincronizar(int $locacaoId, array $taxas, string $chave): array
    {
        $this->removerTodas($locacaoId);

        $idsGerados = [];
        foreach ($taxas as $taxa) {
            if (empty($taxa['nome']) && empty($taxa['id_taxa'])) {
                continue;
            }

            $id = $this->adicionar($locacaoId, $taxa, $chave);
            $idsGerados[] = $id;
        }

        return $idsGerados;
    }

    /**
     * Calcula o valor total de uma taxa baseado nas regras de calculo
     *
     * @param array $taxa Dados da taxa (base_calculo, tipo_valor, valor_unitario, quantidade)
     * @param int $qtdPeriodos Quantidade de periodos da locacao (dias)
     * @param float $valorTotalVeiculos Valor total dos veiculos no periodo
     * @return float Valor total calculado
     */
    public function calcularValorTotalTaxa(array $taxa, int $qtdPeriodos, float $valorTotalVeiculos): float
    {
        $valor = currency_parse($taxa['valor_unitario'] ?? 0);
        $baseCalculo = $taxa['base_calculo'] ?? 'FIX';
        $tipoValor = $taxa['tipo_valor'] ?? 'MON';
        $quantidade = (int) ($taxa['quantidade'] ?? 1);

        if ($tipoValor === 'POR') {
            if ($baseCalculo === 'VLT') {
                $valorBase = $valorTotalVeiculos * ($valor / 100);
            } else {
                $valorPeriodo = $qtdPeriodos > 0 ? $valorTotalVeiculos / $qtdPeriodos : 0;
                $valorBase = $valorPeriodo * ($valor / 100);
            }
        } else {
            $valorBase = $valor;
        }

        if ($baseCalculo === 'PER') {
            return $valorBase * $quantidade * $qtdPeriodos;
        } else {
            return $valorBase * $quantidade;
        }
    }

    /**
     * Recalcula todas as taxas de uma locacao
     *
     * @param int $locacaoId ID da locacao
     * @param int $qtdPeriodos Quantidade de periodos (dias)
     * @param float $valorTotalVeiculos Valor total dos veiculos
     * @return float Total das taxas recalculadas
     */
    public function recalcularTaxas(int $locacaoId, int $qtdPeriodos, float $valorTotalVeiculos): float
    {
        $taxas = $this->listarPorLocacao($locacaoId);
        $total = 0;

        foreach ($taxas as $taxa) {
            $valorTotal = $this->calcularValorTotalTaxa($taxa, $qtdPeriodos, $valorTotalVeiculos);

            $this->qb
                ->table('locacoes_taxaseservicos')
                ->where('id', '=', $taxa['id'])
                ->update(['valor_total' => $valorTotal]);

            $total += $valorTotal;
        }

        return $total;
    }

    /**
     * Calcula o total de taxas de uma locacao
     *
     * @param int $locacaoId ID da locacao
     * @return float Total das taxas
     */
    public function calcularTotal(int $locacaoId): float
    {
        $result = $this->qb
            ->table('locacoes_taxaseservicos')
            ->selectRaw('SUM(valor_total) AS total')
            ->where('id_locacao', '=', $locacaoId)
            ->first();

        return (float) ($result['total'] ?? 0);
    }

    /**
     * Lista taxas disponiveis para select
     *
     * @param string $chave Chave do tenant
     * @param string $search Termo de busca
     * @return array Lista de taxas
     */
    public function listarTaxasDisponiveis(string $chave, string $search = ''): array
    {
        $query = $this->qb
            ->table('taxaseservicos')
            ->select(['id', 'nome', 'base_calculo', 'tipo_valor', 'valor']);

        if (!empty($search)) {
            $query->where('nome', 'LIKE', '%' . $search . '%');
        }

        return $query
            ->orderBy('nome', 'ASC')
            ->limit(50)
            ->get();
    }

}
