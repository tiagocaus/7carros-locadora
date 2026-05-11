<?php

namespace App\Models;

/**
 * Model ContratoTaxaServico
 *
 * Gerencia taxas e servicos adicionais vinculados a um contrato.
 * Cada taxa pode ter quantidade e valor unitario.
 *
 * Campos de calculo:
 * - base_calculo: FIX (fixo), PER (por periodo), VLT (valor total)
 * - tipo_valor: MON (moeda), POR (porcentagem)
 */
class ContratoTaxaServico extends Model
{
    /**
     * Lista todas as taxas de um contrato
     *
     * @param int $contratoId ID do contrato
     * @return array Lista de taxas
     */
    public function listarPorContrato(int $contratoId): array
    {
        return $this->qb
            ->table('contratos_taxaseservicos', 'cts')
            ->select([
                'cts.*',
                't.nome AS taxa_nome_original',
                't.base_calculo AS taxa_base_calculo_original',
                't.tipo_valor AS taxa_tipo_valor_original'
            ])
            ->leftJoin('taxaseservicos', 't', 'cts.id_taxa', '=', 't.id')
            ->where('cts.id_contrato', '=', $contratoId)
            ->orderBy('cts.nome', 'ASC')
            ->get();
    }

    /**
     * Busca uma taxa do contrato por ID
     *
     * @param int $id ID do registro
     * @return array|null Dados ou null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('contratos_taxaseservicos', 'cts')
            ->select([
                'cts.*',
                't.nome AS taxa_nome_original',
                't.base_calculo AS taxa_base_calculo_original',
                't.tipo_valor AS taxa_tipo_valor_original'
            ])
            ->leftJoin('taxaseservicos', 't', 'cts.id_taxa', '=', 't.id')
            ->where('cts.id', '=', $id)
            ->first();
    }

    /**
     * Adiciona uma taxa ao contrato
     *
     * @param int $contratoId ID do contrato
     * @param array $dados Dados da taxa
     * @param string $chave Chave do tenant
     * @return int ID criado
     */
    public function adicionar(int $contratoId, array $dados, string $chave): int
    {
        $quantidade = (int) ($dados['quantidade'] ?? 1);
        $valorUnitario = currency_parse($dados['valor_unitario'] ?? 0);
        $valorTotal = $quantidade * $valorUnitario;

        return $this->qb
            ->table('contratos_taxaseservicos')
            ->insert([
                'chave' => $chave,
                'id_contrato' => $contratoId,
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
     * Atualiza uma taxa do contrato
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
            ->table('contratos_taxaseservicos')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Remove uma taxa do contrato
     *
     * @param int $id ID do registro
     * @return int Linhas afetadas
     */
    public function remover(int $id): int
    {
        return $this->qb
            ->table('contratos_taxaseservicos')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Remove todas as taxas de um contrato
     *
     * @param int $contratoId ID do contrato
     * @return int Linhas afetadas
     */
    public function removerTodas(int $contratoId): int
    {
        return $this->qb
            ->table('contratos_taxaseservicos')
            ->where('id_contrato', '=', $contratoId)
            ->delete();
    }

    /**
     * Sincroniza taxas do contrato (remove antigas e adiciona novas)
     *
     * @param int $contratoId ID do contrato
     * @param array $taxas Lista de taxas com dados
     * @param string $chave Chave do tenant
     * @return array IDs das taxas criadas
     */
    public function sincronizar(int $contratoId, array $taxas, string $chave): array
    {
        // Remover taxas existentes
        $this->removerTodas($contratoId);

        // Adicionar novas taxas
        $idsGerados = [];

        foreach ($taxas as $taxa) {
            if (empty($taxa['nome']) && empty($taxa['id_taxa'])) {
                continue;
            }

            $id = $this->adicionar($contratoId, $taxa, $chave);
            $idsGerados[] = $id;
        }

        return $idsGerados;
    }

    /**
     * Calcula o valor total de uma taxa baseado nas regras de calculo
     *
     * @param array $taxa Dados da taxa (base_calculo, tipo_valor, valor_unitario, quantidade)
     * @param int $qtdPeriodos Quantidade de periodos do contrato
     * @param float $valorTotalVeiculos Valor total dos veiculos no periodo
     * @return float Valor total calculado
     */
    public function calcularValorTotalTaxa(array $taxa, int $qtdPeriodos, float $valorTotalVeiculos): float
    {
        $valor = currency_parse($taxa['valor_unitario'] ?? 0);
        $baseCalculo = $taxa['base_calculo'] ?? 'FIX';
        $tipoValor = $taxa['tipo_valor'] ?? 'MON';
        $quantidade = (int) ($taxa['quantidade'] ?? 1);

        // Determinar valor base
        if ($tipoValor === 'POR') {
            // Porcentagem - calcular sobre a base
            if ($baseCalculo === 'VLT') {
                // Porcentagem do valor total
                $valorBase = $valorTotalVeiculos * ($valor / 100);
            } else {
                // PER - porcentagem do valor do periodo
                $valorPeriodo = $qtdPeriodos > 0 ? $valorTotalVeiculos / $qtdPeriodos : 0;
                $valorBase = $valorPeriodo * ($valor / 100);
            }
        } else {
            // MON - valor em moeda
            $valorBase = $valor;
        }

        // Aplicar multiplicador
        if ($baseCalculo === 'PER') {
            // Por periodo - multiplica pela quantidade de periodos
            return $valorBase * $quantidade * $qtdPeriodos;
        } else {
            // FIX ou VLT - valor unico (ou ja calculado sobre total)
            return $valorBase * $quantidade;
        }
    }

    /**
     * Recalcula todas as taxas de um contrato
     *
     * @param int $contratoId ID do contrato
     * @param int $qtdPeriodos Quantidade de periodos
     * @param float $valorTotalVeiculos Valor total dos veiculos
     * @return float Total das taxas recalculadas
     */
    public function recalcularTaxas(int $contratoId, int $qtdPeriodos, float $valorTotalVeiculos): float
    {
        $taxas = $this->listarPorContrato($contratoId);
        $total = 0;

        foreach ($taxas as $taxa) {
            $valorTotal = $this->calcularValorTotalTaxa($taxa, $qtdPeriodos, $valorTotalVeiculos);

            // Atualizar valor_total no banco
            $this->qb
                ->table('contratos_taxaseservicos')
                ->where('id', '=', $taxa['id'])
                ->update(['valor_total' => $valorTotal]);

            $total += $valorTotal;
        }

        return $total;
    }

    /**
     * Atualiza o valor_total de uma taxa do contrato
     *
     * @param int $id ID do registro
     * @param float $valorTotal Novo valor total
     * @return int Linhas afetadas
     */
    public function atualizarValorTotal(int $id, float $valorTotal): int
    {
        return $this->qb
            ->table('contratos_taxaseservicos')
            ->where('id', '=', $id)
            ->update(['valor_total' => $valorTotal]);
    }

    /**
     * Calcula o total de taxas de um contrato
     *
     * @param int $contratoId ID do contrato
     * @return float Total das taxas
     */
    public function calcularTotal(int $contratoId): float
    {
        $result = $this->qb
            ->table('contratos_taxaseservicos')
            ->selectRaw('SUM(valor_total) AS total')
            ->where('id_contrato', '=', $contratoId)
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
