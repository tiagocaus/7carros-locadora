<?php

namespace App\Models;

/**
 * Model ManutencaoItem
 *
 * Gerencia itens (pecas/servicos) de uma ordem de manutencao.
 *
 * Campos:
 * - id_estoque: FK opcional para produto no estoque
 * - descricao: texto obrigatorio quando nao houver id_estoque
 * - id_financeiro: FK para lancamento financeiro (quando pago)
 * - pago: S/N - status de pagamento
 *
 * Triggers automaticamente recalculam totais em manutencoes.
 */
class ManutencaoItem extends Model
{
    /**
     * Lista todos os itens de uma manutencao
     */
    public function listarPorManutencao(int $idManutencao): array
    {
        return $this->qb
            ->table('manutencoes_itens', 'mi')
            ->select([
                'mi.*',
                'e.produto_codigo AS estoque_codigo',
                'e.produto_nome AS estoque_nome',
                'e.produto_unidade AS estoque_unidade'
            ])
            ->leftJoin('estoque', 'e', 'mi.id_estoque', '=', 'e.id')
            ->where('mi.id_manutencao', '=', $idManutencao)
            ->orderBy('mi.ordem', 'ASC')
            ->get();
    }

    /**
     * Lista itens pendentes de pagamento
     */
    public function listarPendentes(int $idManutencao): array
    {
        return $this->qb
            ->table('manutencoes_itens', 'mi')
            ->select([
                'mi.*',
                'e.produto_codigo AS estoque_codigo',
                'e.produto_nome AS estoque_nome',
                'e.produto_unidade AS estoque_unidade'
            ])
            ->leftJoin('estoque', 'e', 'mi.id_estoque', '=', 'e.id')
            ->where('mi.id_manutencao', '=', $idManutencao)
            ->where('mi.pago', '=', 'N')
            ->orderBy('mi.ordem', 'ASC')
            ->get();
    }

    /**
     * Lista itens pagos
     */
    public function listarPagos(int $idManutencao): array
    {
        return $this->qb
            ->table('manutencoes_itens', 'mi')
            ->select([
                'mi.*',
                'e.produto_codigo AS estoque_codigo',
                'e.produto_nome AS estoque_nome',
                'f.descricao AS financeiro_descricao'
            ])
            ->leftJoin('estoque', 'e', 'mi.id_estoque', '=', 'e.id')
            ->leftJoin('financeiro', 'f', 'mi.id_financeiro', '=', 'f.id')
            ->where('mi.id_manutencao', '=', $idManutencao)
            ->where('mi.pago', '=', 'S')
            ->orderBy('mi.ordem', 'ASC')
            ->get();
    }

    /**
     * Busca um item por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('manutencoes_itens', 'mi')
            ->select([
                'mi.*',
                'e.produto_codigo AS estoque_codigo',
                'e.produto_nome AS estoque_nome',
                'e.produto_unidade AS estoque_unidade'
            ])
            ->leftJoin('estoque', 'e', 'mi.id_estoque', '=', 'e.id')
            ->where('mi.id', '=', $id)
            ->first();
    }

    /**
     * Busca itens por IDs
     */
    public function buscarPorIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->qb
            ->table('manutencoes_itens', 'mi')
            ->select([
                'mi.*',
                'e.produto_codigo AS estoque_codigo',
                'e.produto_nome AS estoque_nome'
            ])
            ->leftJoin('estoque', 'e', 'mi.id_estoque', '=', 'e.id')
            ->whereIn('mi.id', $ids)
            ->get();
    }

    /**
     * Valida se o produto existe no estoque
     */
    public function validarProdutoEstoque(int $idEstoque, string $chave): ?array
    {
        return $this->qb
            ->table('estoque')
            ->where('id', '=', $idEstoque)
            ->first();
    }

    /**
     * Cria um novo item
     */
    public function criar(array $dados): int
    {
        // Validar: deve ter id_estoque OU descricao
        if (empty($dados['id_estoque']) && empty($dados['descricao'])) {
            throw new \InvalidArgumentException('Produto ou descricao e obrigatorio');
        }

        // Validar produto no estoque (se fornecido)
        if (!empty($dados['id_estoque'])) {
            $produto = $this->validarProdutoEstoque((int) $dados['id_estoque'], $dados['chave']);
            if (!$produto) {
                throw new \InvalidArgumentException('Produto nao encontrado no estoque');
            }
        }

        // Calcular valor_total
        $quantidade = (float) ($dados['quantidade'] ?? 1);
        $valorUnitario = (float) ($dados['valor_unitario'] ?? 0);
        $dados['valor_total'] = round($quantidade * $valorUnitario, 2);

        // Determinar ordem
        if (empty($dados['ordem'])) {
            $ultimaOrdem = $this->qb
                ->table('manutencoes_itens')
                ->selectRaw('MAX(ordem) AS max_ordem')
                ->where('id_manutencao', '=', $dados['id_manutencao'])
                ->first();
            $dados['ordem'] = ($ultimaOrdem['max_ordem'] ?? 0) + 1;
        }

        // Campos permitidos
        $camposPermitidos = [
            'chave', 'id_manutencao', 'id_estoque', 'id_financeiro',
            'descricao', 'quantidade', 'valor_unitario', 'valor_total',
            'pago', 'data_pagamento', 'ordem'
        ];

        $dadosInsert = [];
        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $dados)) {
                $dadosInsert[$campo] = $dados[$campo] === '' ? null : $dados[$campo];
            }
        }

        $novoId = $this->qb->table('manutencoes_itens')->insert($dadosInsert);

        // Baixa automatica de estoque
        if (!empty($dadosInsert['id_estoque'])) {
            $this->ajustarEstoque((int) $dadosInsert['id_estoque'], (float) ($dadosInsert['quantidade'] ?? 1), 'baixa');
        }

        return $novoId;
    }

    /**
     * Atualiza um item
     */
    public function atualizar(int $id, array $dados): int
    {
        // Buscar item atual
        $item = $this->buscarPorId($id);
        if (!$item) {
            throw new \InvalidArgumentException('Item nao encontrado');
        }

        // Nao permitir alterar itens pagos
        if ($item['pago'] === 'S' && !isset($dados['pago'])) {
            throw new \InvalidArgumentException('Nao e possivel alterar itens ja pagos');
        }

        // Recalcular valor_total se quantidade ou valor_unitario mudaram
        if (isset($dados['quantidade']) || isset($dados['valor_unitario'])) {
            $quantidade = (float) ($dados['quantidade'] ?? $item['quantidade']);
            $valorUnitario = (float) ($dados['valor_unitario'] ?? $item['valor_unitario']);
            $dados['valor_total'] = round($quantidade * $valorUnitario, 2);
        }

        $camposPermitidos = [
            'id_estoque', 'descricao', 'quantidade', 'valor_unitario', 'valor_total',
            'pago', 'data_pagamento', 'id_financeiro', 'ordem'
        ];

        $dadosUpdate = [];
        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $dados)) {
                $dadosUpdate[$campo] = $dados[$campo] === '' ? null : $dados[$campo];
            }
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        // Ajustar estoque se quantidade mudou
        if (isset($dados['quantidade']) && !empty($item['id_estoque'])) {
            $quantidadeAntiga = (float) $item['quantidade'];
            $quantidadeNova = (float) $dados['quantidade'];
            $diferenca = $quantidadeNova - $quantidadeAntiga;

            if ($diferenca > 0) {
                // Aumentou quantidade: dar baixa da diferenca
                $this->ajustarEstoque((int) $item['id_estoque'], $diferenca, 'baixa');
            } elseif ($diferenca < 0) {
                // Diminuiu quantidade: repor a diferenca
                $this->ajustarEstoque((int) $item['id_estoque'], abs($diferenca), 'repor');
            }
        }

        return $this->qb
            ->table('manutencoes_itens')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Deleta um item
     */
    public function deletar(int $id): int
    {
        // Buscar item
        $item = $this->buscarPorId($id);
        if (!$item) {
            return 0;
        }

        // Nao permitir deletar itens pagos
        if ($item['pago'] === 'S') {
            throw new \InvalidArgumentException('Nao e possivel excluir itens ja pagos');
        }

        // Repor estoque antes de deletar
        if (!empty($item['id_estoque'])) {
            $this->ajustarEstoque((int) $item['id_estoque'], (float) $item['quantidade'], 'repor');
        }

        return $this->qb
            ->table('manutencoes_itens')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Deleta todos os itens de uma manutencao (apenas nao pagos)
     */
    public function deletarPorManutencao(int $idManutencao): int
    {
        return $this->qb
            ->table('manutencoes_itens')
            ->where('id_manutencao', '=', $idManutencao)
            ->where('pago', '=', 'N')
            ->delete();
    }

    /**
     * Salva multiplos itens (bulk insert/update) e remove os que nao estao mais na lista
     */
    public function salvarTodos(int $idManutencao, string $chave, array $itens): int
    {
        $inseridos = 0;
        $idsEnviados = [];

        foreach ($itens as $item) {
            $item['id_manutencao'] = $idManutencao;
            $item['chave'] = $chave;

            if (!empty($item['id'])) {
                // Update
                $this->atualizar((int) $item['id'], $item);
                $idsEnviados[] = (int) $item['id'];
            } else {
                // Insert
                $novoId = $this->criar($item);
                $idsEnviados[] = $novoId;
                $inseridos++;
            }
        }

        // Remover itens que nao estao mais na lista (apenas nao pagos)
        $itensExistentes = $this->listarPorManutencao($idManutencao);
        foreach ($itensExistentes as $itemExistente) {
            if (!in_array((int) $itemExistente['id'], $idsEnviados)) {
                // Item foi removido no front-end, deletar se nao estiver pago
                if ($itemExistente['pago'] !== 'S') {
                    $this->deletar((int) $itemExistente['id']);
                }
            }
        }

        return $inseridos;
    }

    /**
     * Marca itens como pagos
     */
    public function marcarComosPagos(array $ids, int $idFinanceiro): int
    {
        if (empty($ids)) {
            return 0;
        }

        return $this->qb
            ->table('manutencoes_itens')
            ->whereIn('id', $ids)
            ->update([
                'pago' => 'S',
                'id_financeiro' => $idFinanceiro,
                'data_pagamento' => date('Y-m-d H:i:s')
            ]);
    }

    /**
     * Conta itens de uma manutencao
     */
    public function contarPorManutencao(int $idManutencao): int
    {
        return $this->qb
            ->table('manutencoes_itens')
            ->where('id_manutencao', '=', $idManutencao)
            ->count();
    }

    /**
     * Soma valores de uma manutencao
     */
    public function somarValores(int $idManutencao): float
    {
        $result = $this->qb
            ->table('manutencoes_itens')
            ->selectRaw('COALESCE(SUM(valor_total), 0) AS total')
            ->where('id_manutencao', '=', $idManutencao)
            ->first();

        return (float) ($result['total'] ?? 0);
    }

    /**
     * Soma valores pagos de uma manutencao
     */
    public function somarValoresPagos(int $idManutencao): float
    {
        $result = $this->qb
            ->table('manutencoes_itens')
            ->selectRaw('COALESCE(SUM(valor_total), 0) AS total')
            ->where('id_manutencao', '=', $idManutencao)
            ->where('pago', '=', 'S')
            ->first();

        return (float) ($result['total'] ?? 0);
    }

    /**
     * Soma valores pendentes de uma manutencao
     */
    public function somarValoresPendentes(int $idManutencao): float
    {
        $result = $this->qb
            ->table('manutencoes_itens')
            ->selectRaw('COALESCE(SUM(valor_total), 0) AS total')
            ->where('id_manutencao', '=', $idManutencao)
            ->where('pago', '=', 'N')
            ->first();

        return (float) ($result['total'] ?? 0);
    }

    /**
     * Ajusta estoque automaticamente (baixa ou reposicao)
     *
     * Verifica se o produto tem baixa_automatica = 'S' antes de ajustar.
     * Usa UPDATE atomico para evitar race conditions.
     *
     * @param int $idEstoque ID do produto no estoque
     * @param float $quantidade Quantidade a ajustar
     * @param string $operacao 'baixa' (subtrai) ou 'repor' (soma)
     */
    private function ajustarEstoque(int $idEstoque, float $quantidade, string $operacao): void
    {
        if ($quantidade <= 0) {
            return;
        }

        // Buscar produto e verificar se tem baixa automatica ativa
        $produto = $this->qb
            ->table('estoque')
            ->select(['id', 'baixa_automatica'])
            ->where('id', '=', $idEstoque)
            ->first();

        if (!$produto || $produto['baixa_automatica'] !== 'S') {
            return;
        }

        // UPDATE atomico no estoque via prepared statement
        $mysqli = $this->qb->getMysqli();
        if ($operacao === 'baixa') {
            $sql = 'UPDATE estoque SET produto_estoque_atual = produto_estoque_atual - ? WHERE id = ?';
        } else {
            $sql = 'UPDATE estoque SET produto_estoque_atual = produto_estoque_atual + ? WHERE id = ?';
        }
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('di', $quantidade, $idEstoque);
        $stmt->execute();
        $stmt->close();
    }
}
