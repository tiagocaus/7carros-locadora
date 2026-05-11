<?php

namespace App\Models;

/**
 * Model FinanceiroItem
 *
 * Gerencia os itens de um lancamento financeiro.
 * Cada lancamento pode ter multiplos itens com plano de contas especifico.
 *
 * Relacionamentos:
 * - financeiro_itens.id_financeiro -> financeiro.id
 * - financeiro_itens.id_veiculo -> veiculos.id
 * - financeiro_itens.id_plano_de_conta -> planos_de_contas.id
 */
class FinanceiroItem extends Model
{
    /**
     * Lista todos os itens de um lancamento financeiro
     *
     * @param int $idFinanceiro ID do lancamento
     * @return array Lista de itens ordenados por ordem
     */
    public function listarPorFinanceiro(int $idFinanceiro): array
    {
        return $this->qb
            ->table('financeiro_itens')
            ->where('id_financeiro', '=', $idFinanceiro)
            ->orderBy('ordem', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();
    }

    /**
     * Lista itens com dados do plano de contas e veiculo
     *
     * @param int $idFinanceiro ID do lancamento
     * @return array Lista de itens com relacionamentos
     */
    public function listarComRelacionamentos(int $idFinanceiro): array
    {
        return $this->qb
            ->table('financeiro_itens', 'fi')
            ->select([
                'fi.*',
                'pc.descricao_i18n AS plano_conta_descricao_i18n',
                'pc.hierarquia AS plano_conta_hierarquia',
                'v.placa AS veiculo_placa',
                'v.modelo AS veiculo_modelo'
            ])
            ->leftJoin('planos_de_contas', 'pc', 'fi.id_plano_de_conta', '=', 'pc.id')
            ->leftJoin('veiculos', 'v', 'fi.id_veiculo', '=', 'v.id')
            ->where('fi.id_financeiro', '=', $idFinanceiro)
            ->orderBy('fi.ordem', 'ASC')
            ->orderBy('fi.id', 'ASC')
            ->get();
    }

    /**
     * Busca um item por ID
     *
     * @param int $id ID do item
     * @return array|null Dados do item ou null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('financeiro_itens')
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Cria um novo item
     *
     * @param array $dados Dados do item
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('financeiro_itens')
            ->insert([
                'chave' => $dados['chave'],
                'id_financeiro' => (int) $dados['id_financeiro'],
                'id_veiculo' => !empty($dados['id_veiculo']) ? (int) $dados['id_veiculo'] : null,
                'id_plano_de_conta' => !empty($dados['id_plano_de_conta']) ? (int) $dados['id_plano_de_conta'] : null,
                'descricao' => $dados['descricao'] ?? null,
                'valor' => currency_parse($dados['valor'] ?? 0),
                'ordem' => (int) ($dados['ordem'] ?? 1),
            ]);
    }

    /**
     * Atualiza um item existente
     *
     * @param int $id ID do item
     * @param array $dados Dados a atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $item = $this->buscarPorId($id);
        if (!$item) {
            throw new \InvalidArgumentException('Item nao encontrado');
        }

        $dadosUpdate = [];

        if (isset($dados['id_veiculo'])) {
            $dadosUpdate['id_veiculo'] = !empty($dados['id_veiculo']) ? (int) $dados['id_veiculo'] : null;
        }

        if (isset($dados['id_plano_de_conta'])) {
            $dadosUpdate['id_plano_de_conta'] = !empty($dados['id_plano_de_conta']) ? (int) $dados['id_plano_de_conta'] : null;
        }

        if (array_key_exists('descricao', $dados)) {
            $dadosUpdate['descricao'] = $dados['descricao'];
        }

        if (isset($dados['valor'])) {
            $dadosUpdate['valor'] = currency_parse($dados['valor']);
        }

        if (isset($dados['ordem'])) {
            $dadosUpdate['ordem'] = (int) $dados['ordem'];
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        $dadosUpdate['updated_at'] = date('Y-m-d H:i:s');

        return $this->qb
            ->table('financeiro_itens')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Exclui um item
     *
     * @param int $id ID do item
     * @return int Linhas afetadas
     */
    public function deletar(int $id): int
    {
        return $this->qb
            ->table('financeiro_itens')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Exclui todos os itens de um lancamento
     *
     * @param int $idFinanceiro ID do lancamento
     * @return int Linhas afetadas
     */
    public function deletarPorFinanceiro(int $idFinanceiro): int
    {
        return $this->qb
            ->table('financeiro_itens')
            ->where('id_financeiro', '=', $idFinanceiro)
            ->delete();
    }

    /**
     * Salva todos os itens de um lancamento
     *
     * Remove itens existentes e insere os novos.
     * Similar ao padrao usado em GrupoPrecoDia::salvarTodos
     *
     * @param int $idFinanceiro ID do lancamento
     * @param string $chave Chave do tenant
     * @param array $itens Array de itens
     * @return int Quantidade de itens inseridos
     */
    public function salvarTodos(int $idFinanceiro, string $chave, array $itens): int
    {
        // Filtrar itens validos
        $itensValidos = [];
        $ordem = 1;

        foreach ($itens as $item) {
            $valor = isset($item['valor']) ? currency_parse($item['valor']) : 0;

            // Item deve ter valor ou descricao
            if ($valor <= 0 && empty($item['descricao'])) {
                continue;
            }

            $itensValidos[] = [
                'id_veiculo' => !empty($item['id_veiculo']) ? (int) $item['id_veiculo'] : null,
                'id_plano_de_conta' => !empty($item['id_plano_de_conta']) ? (int) $item['id_plano_de_conta'] : null,
                'descricao' => isset($item['descricao']) ? mb_substr($item['descricao'], 0, 500) : null,
                'valor' => $valor,
                'ordem' => $ordem++,
            ];
        }

        // Remover itens existentes
        $this->deletarPorFinanceiro($idFinanceiro);

        // Inserir novos itens
        $count = 0;
        foreach ($itensValidos as $item) {
            $this->qb
                ->table('financeiro_itens')
                ->insert([
                    'chave' => $chave,
                    'id_financeiro' => $idFinanceiro,
                    'id_veiculo' => $item['id_veiculo'],
                    'id_plano_de_conta' => $item['id_plano_de_conta'],
                    'descricao' => $item['descricao'],
                    'valor' => $item['valor'],
                    'ordem' => $item['ordem'],
                ]);
            $count++;
        }

        return $count;
    }

    /**
     * Conta itens de um lancamento
     *
     * @param int $idFinanceiro ID do lancamento
     * @return int Total de itens
     */
    public function contarPorFinanceiro(int $idFinanceiro): int
    {
        return $this->qb
            ->table('financeiro_itens')
            ->where('id_financeiro', '=', $idFinanceiro)
            ->count();
    }

    /**
     * Calcula soma dos valores dos itens
     *
     * @param int $idFinanceiro ID do lancamento
     * @return float Soma dos valores
     */
    public function somarValores(int $idFinanceiro): float
    {
        return $this->qb
            ->table('financeiro_itens')
            ->where('id_financeiro', '=', $idFinanceiro)
            ->sum('valor');
    }

}
