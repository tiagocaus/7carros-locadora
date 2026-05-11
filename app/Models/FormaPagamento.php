<?php

namespace App\Models;

/**
 * Model FormaPagamento
 *
 * Gerencia formas de pagamento do sistema financeiro.
 * Inclui configuracoes de taxas e descontos por antecipacao.
 * Logica de parsing de comandos de parcelas foi movida para ComandoParcela.
 */
class FormaPagamento extends Model
{
    /**
     * Lista todas as formas de pagamento do tenant
     *
     * @return array Lista de formas de pagamento
     */
    public function listar(): array
    {
        return $this->qb
            ->table('formas_pagamento')
            ->orderBy('nome', 'ASC')
            ->get();
    }

    /**
     * Lista formas de pagamento para select (ativas)
     *
     * @param string $search Termo de busca (opcional)
     * @return array Lista com id e nome
     */
    public function listarParaSelect(string $search = ''): array
    {
        $query = $this->qb
            ->table('formas_pagamento')
            ->select(['id', 'nome'])
            ->where('status', '=', 'A');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->where('nome', 'LIKE', $searchTerm);
        }

        return $query->orderBy('nome', 'ASC')->limit(50)->get();
    }

    /**
     * Lista formas de pagamento com paginacao e busca
     *
     * @param int $page Pagina atual
     * @param int $perPage Registros por pagina
     * @param string $search Termo de busca (opcional)
     * @return array Lista de formas de pagamento
     */
    public function listarPaginado(int $page, int $perPage, string $search = ''): array
    {
        $query = $this->qb
            ->table('formas_pagamento');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->where('nome', 'LIKE', $searchTerm);
        }

        return $query
            ->orderBy('nome', 'ASC')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta o total de formas de pagamento do tenant
     *
     * @param string $search Termo de busca (opcional)
     * @return int Total de registros
     */
    public function contar(string $search = ''): int
    {
        $query = $this->qb
            ->table('formas_pagamento');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->where('nome', 'LIKE', $searchTerm);
        }

        return $query->count();
    }

    /**
     * Busca uma forma de pagamento por ID
     *
     * @param int $id ID da forma de pagamento
     * @return array|null Dados ou null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('formas_pagamento')
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Cria uma nova forma de pagamento
     *
     * @param array $dados Dados da forma de pagamento
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('formas_pagamento')
            ->insert([
                'chave' => $dados['chave'],
                'nome' => $dados['nome'],
                'lancar_pago' => $dados['lancar_pago'] ?? 'N',
                'onde_exibir' => $dados['onde_exibir'] ?? '2',
                'status' => $dados['status'] ?? 'A',
                'multa' => $dados['multa'] ?? 0.00,
                'juros_por_dia' => $dados['juros_por_dia'] ?? 0.000,
                'taxa_fixa' => $dados['taxa_fixa'] ?? 0.00,
                'taxa_fixa_parcela' => $dados['taxa_fixa_parcela'] ?? 0.00,
                'taxa_percentual_parcela' => $dados['taxa_percentual_parcela'] ?? 0.00,
                'desconto_antecipacao_dias' => $dados['desconto_antecipacao_dias'] ?? 0,
                'desconto_antecipacao_percentual' => $dados['desconto_antecipacao_percentual'] ?? 0.00,
            ]);
    }

    /**
     * Atualiza uma forma de pagamento existente
     *
     * @param int $id ID da forma de pagamento
     * @param array $dados Dados para atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $forma = $this->buscarPorId($id);
        if (!$forma) {
            throw new \InvalidArgumentException('Forma de pagamento não encontrada');
        }

        $dadosUpdate = [];

        if (isset($dados['nome'])) {
            $dadosUpdate['nome'] = $dados['nome'];
        }
        if (isset($dados['lancar_pago'])) {
            $dadosUpdate['lancar_pago'] = $dados['lancar_pago'];
        }
        if (isset($dados['onde_exibir'])) {
            $dadosUpdate['onde_exibir'] = $dados['onde_exibir'];
        }
        if (isset($dados['status'])) {
            $dadosUpdate['status'] = $dados['status'];
        }
        if (array_key_exists('multa', $dados)) {
            $dadosUpdate['multa'] = (float) ($dados['multa'] ?? 0);
        }
        if (array_key_exists('juros_por_dia', $dados)) {
            $dadosUpdate['juros_por_dia'] = (float) ($dados['juros_por_dia'] ?? 0);
        }
        if (array_key_exists('taxa_fixa', $dados)) {
            $dadosUpdate['taxa_fixa'] = (float) ($dados['taxa_fixa'] ?? 0);
        }
        if (array_key_exists('taxa_fixa_parcela', $dados)) {
            $dadosUpdate['taxa_fixa_parcela'] = (float) ($dados['taxa_fixa_parcela'] ?? 0);
        }
        if (array_key_exists('taxa_percentual_parcela', $dados)) {
            $dadosUpdate['taxa_percentual_parcela'] = (float) ($dados['taxa_percentual_parcela'] ?? 0);
        }
        if (array_key_exists('desconto_antecipacao_dias', $dados)) {
            $dadosUpdate['desconto_antecipacao_dias'] = (int) ($dados['desconto_antecipacao_dias'] ?? 0);
        }
        if (array_key_exists('desconto_antecipacao_percentual', $dados)) {
            $dadosUpdate['desconto_antecipacao_percentual'] = (float) ($dados['desconto_antecipacao_percentual'] ?? 0);
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        return $this->qb
            ->table('formas_pagamento')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Exclui uma forma de pagamento
     *
     * @param int $id ID da forma de pagamento
     * @return int Linhas afetadas
     */
    public function excluir(int $id): int
    {
        $forma = $this->buscarPorId($id);
        if (!$forma) {
            throw new \InvalidArgumentException('Forma de pagamento não encontrada');
        }

        return $this->qb
            ->table('formas_pagamento')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Calcula a taxa total para um valor e quantidade de parcelas
     *
     * @param int $id ID da forma de pagamento
     * @param float $valor Valor total
     * @param int $parcelas Numero de parcelas
     * @return array Detalhes das taxas calculadas
     */
    public function calcularTaxas(int $id, float $valor, int $parcelas = 1): array
    {
        $forma = $this->buscarPorId($id);
        if (!$forma) {
            return [
                'taxa_total' => 0,
                'valor_parcela' => $valor / max(1, $parcelas),
                'valor_final' => $valor,
            ];
        }

        $parcelas = max(1, $parcelas);
        $valorParcela = $valor / $parcelas;

        // Taxa fixa total diluida
        $taxaFixaDiluida = (float) $forma['taxa_fixa'];

        // Taxa fixa por parcela (multiplicada pelo numero de parcelas)
        $taxaFixaParcela = (float) $forma['taxa_fixa_parcela'] * $parcelas;

        // Taxa percentual sobre cada parcela
        $taxaPercentual = ($valorParcela * ((float) $forma['taxa_percentual_parcela'] / 100)) * $parcelas;

        $taxaTotal = $taxaFixaDiluida + $taxaFixaParcela + $taxaPercentual;

        return [
            'taxa_fixa_diluida' => $taxaFixaDiluida,
            'taxa_fixa_parcela_total' => $taxaFixaParcela,
            'taxa_percentual_total' => round($taxaPercentual, 2),
            'taxa_total' => round($taxaTotal, 2),
            'valor_parcela_original' => round($valorParcela, 2),
            'valor_parcela_com_taxa' => round(($valor + $taxaTotal) / $parcelas, 2),
            'valor_final' => round($valor + $taxaTotal, 2),
        ];
    }

    /**
     * Calcula o desconto por antecipacao
     *
     * @param int $id ID da forma de pagamento
     * @param float $valor Valor da parcela
     * @param string $dataVencimento Data de vencimento (Y-m-d)
     * @param string|null $dataPagamento Data do pagamento (Y-m-d), default hoje
     * @return array Detalhes do desconto
     */
    public function calcularDescontoAntecipacao(
        int $id,
        float $valor,
        string $dataVencimento,
        ?string $dataPagamento = null
    ): array {
        $forma = $this->buscarPorId($id);
        if (!$forma) {
            return [
                'desconto' => 0,
                'valor_com_desconto' => $valor,
                'aplicavel' => false,
            ];
        }

        $diasAntecipacao = (int) $forma['desconto_antecipacao_dias'];
        $percentualDesconto = (float) $forma['desconto_antecipacao_percentual'];

        if ($diasAntecipacao <= 0 || $percentualDesconto <= 0) {
            return [
                'desconto' => 0,
                'valor_com_desconto' => $valor,
                'aplicavel' => false,
                'motivo' => 'Desconto por antecipação não configurado',
            ];
        }

        $dataPagamento = $dataPagamento ?? date('Y-m-d');
        $vencimento = new \DateTime($dataVencimento);
        $pagamento = new \DateTime($dataPagamento);

        $diferenca = $vencimento->diff($pagamento);
        $diasAntesVencimento = $diferenca->invert ? $diferenca->days : -$diferenca->days;

        if ($diasAntesVencimento >= $diasAntecipacao) {
            $desconto = $valor * ($percentualDesconto / 100);
            return [
                'desconto' => round($desconto, 2),
                'valor_com_desconto' => round($valor - $desconto, 2),
                'aplicavel' => true,
                'dias_antecipados' => $diasAntesVencimento,
                'dias_minimos' => $diasAntecipacao,
                'percentual_desconto' => $percentualDesconto,
            ];
        }

        return [
            'desconto' => 0,
            'valor_com_desconto' => $valor,
            'aplicavel' => false,
            'dias_antecipados' => $diasAntesVencimento,
            'dias_minimos' => $diasAntecipacao,
            'motivo' => "Necessário pagar {$diasAntecipacao} dias antes do vencimento",
        ];
    }

    /**
     * Busca as filiais vinculadas a uma forma de pagamento
     *
     * @param int $id ID da forma de pagamento
     * @return array Lista de filiais com id e nome
     */
    public function buscarFiliais(int $id): array
    {
        return $this->qb
            ->table('formas_pagamento_filiais AS fpf')
            ->select(['fpf.id_matriz_filial AS id', 'mf.razao_social AS nome'])
            ->join('matrizes_filiais', 'mf', 'mf.id', '=', 'fpf.id_matriz_filial')
            ->where('fpf.id_forma_pagamento', '=', $id)
            ->withoutChave()
            ->orderBy('mf.razao_social', 'ASC')
            ->get();
    }

    /**
     * Sincroniza as filiais vinculadas a uma forma de pagamento
     *
     * @param int $id ID da forma de pagamento
     * @param array $filiaisIds Array de IDs das filiais
     * @param string $chave Chave do tenant
     * @return void
     */
    public function sincronizarFiliais(int $id, array $filiaisIds, string $chave): void
    {
        // Remover vinculos antigos
        $this->qb
            ->table('formas_pagamento_filiais')
            ->where('id_forma_pagamento', '=', $id)
            ->delete();

        // Inserir novos vinculos
        foreach ($filiaisIds as $filialId) {
            $this->qb
                ->table('formas_pagamento_filiais')
                ->insert([
                    'id_forma_pagamento' => $id,
                    'id_matriz_filial' => (int) $filialId,
                    'chave' => $chave
                ]);
        }
    }

    /**
     * Busca os gateways vinculados a uma forma de pagamento
     *
     * @param int $id ID da forma de pagamento
     * @return array Lista de gateways com id e nome
     */
    public function buscarGateways(int $id): array
    {
        return $this->qb
            ->table('formas_pagamento_gateways AS fpg')
            ->select(['fpg.id_gateway AS id', 'gp.nome'])
            ->join('gateways_pagamento', 'gp', 'gp.id', '=', 'fpg.id_gateway')
            ->where('fpg.id_forma_pagamento', '=', $id)
            ->withoutChave()
            ->orderBy('gp.nome', 'ASC')
            ->get();
    }

    /**
     * Sincroniza os gateways vinculados a uma forma de pagamento
     *
     * @param int $id ID da forma de pagamento
     * @param array $gatewaysIds Array de IDs dos gateways
     * @param string $chave Chave do tenant
     * @return void
     */
    public function sincronizarGateways(int $id, array $gatewaysIds, string $chave): void
    {
        // Remover vinculos antigos
        $this->qb
            ->table('formas_pagamento_gateways')
            ->where('id_forma_pagamento', '=', $id)
            ->withoutChave()
            ->delete();

        // Inserir novos vinculos
        foreach ($gatewaysIds as $gatewayId) {
            $this->qb
                ->table('formas_pagamento_gateways')
                ->insert([
                    'id_forma_pagamento' => $id,
                    'id_gateway' => (int) $gatewayId,
                    'chave' => $chave
                ]);
        }
    }
}
