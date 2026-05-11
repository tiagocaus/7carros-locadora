<?php

namespace App\Models;

use App\Traits\DetectsCrossTenant;

/**
 * Model VeiculoEncargo
 *
 * Gerencia encargos financeiros de veiculos (IPVA, Seguro, Licenciamento, etc.)
 * Cada encargo pode ter recorrencia automatica e gerar lancamentos no financeiro.
 */
class VeiculoEncargo extends Model
{
    use DetectsCrossTenant;

    /**
     * Lista encargos de um veiculo
     *
     * @param int $idVeiculo ID do veiculo
     * @return array Lista de encargos
     */
    public function listarPorVeiculo(int $idVeiculo): array
    {
        return $this->qb
            ->table('veiculos_encargos', 've')
            ->select([
                've.*',
            ])
            ->where('ve.id_veiculo', '=', $idVeiculo)
            ->where('ve.ativo', '=', 1)
            ->orderBy('ve.vencimento', 'ASC')
            ->get();
    }

    /**
     * Busca encargo por ID
     *
     * @param int $id ID do encargo
     * @return array|null Dados do encargo ou null
     */
    public function buscarPorId(int $id): ?array
    {
        $resultado = $this->qb
            ->table('veiculos_encargos')
            ->where('id', '=', $id)
            ->first();

        return $resultado ?: null;
    }

    /**
     * Cria um novo encargo
     *
     * @param array $dados Dados do encargo
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('veiculos_encargos')
            ->insert([
                'chave' => $dados['chave'],
                'id_veiculo' => (int) $dados['id_veiculo'],
                'nome' => $dados['nome'],
                'descricao' => $dados['descricao'] ?? null,
                'valor' => isset($dados['valor']) ? currency_parse($dados['valor']) : null,
                'vencimento' => $dados['vencimento'] ?? null,
                'recorrencia' => $dados['recorrencia'] ?? 'nenhuma',
                'dias_antecedencia' => (int) ($dados['dias_antecedencia'] ?? 30),
                'id_financeiro' => !empty($dados['id_financeiro']) ? (int) $dados['id_financeiro'] : null,
                'ativo' => (int) ($dados['ativo'] ?? 1),
            ]);
    }

    /**
     * Atualiza um encargo existente
     *
     * @param int $id ID do encargo
     * @param array $dados Dados a atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $dadosUpdate = [];

        if (isset($dados['nome'])) {
            $dadosUpdate['nome'] = $dados['nome'];
        }
        if (array_key_exists('descricao', $dados)) {
            $dadosUpdate['descricao'] = $dados['descricao'] ?: null;
        }
        if (array_key_exists('valor', $dados)) {
            $dadosUpdate['valor'] = isset($dados['valor']) && $dados['valor'] !== '' ? currency_parse($dados['valor']) : null;
        }
        if (array_key_exists('vencimento', $dados)) {
            $dadosUpdate['vencimento'] = $dados['vencimento'] ?: null;
        }
        if (isset($dados['recorrencia'])) {
            $dadosUpdate['recorrencia'] = $dados['recorrencia'];
        }
        if (isset($dados['dias_antecedencia'])) {
            $dadosUpdate['dias_antecedencia'] = (int) $dados['dias_antecedencia'];
        }
        if (isset($dados['id_financeiro'])) {
            $dadosUpdate['id_financeiro'] = !empty($dados['id_financeiro']) ? (int) $dados['id_financeiro'] : null;
        }
        if (isset($dados['ativo'])) {
            $dadosUpdate['ativo'] = (int) $dados['ativo'];
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        return $this->qb
            ->table('veiculos_encargos')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Exclui (desativa) um encargo
     *
     * @param int $id ID do encargo
     * @return int Linhas afetadas
     */
    public function excluir(int $id): int
    {
        return $this->qb
            ->table('veiculos_encargos')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Lista encargos pendentes para geracao de lancamento financeiro
     * Busca encargos ativos, sem lancamento vinculado, com valor e vencimento definidos,
     * cuja data de vencimento esta dentro da janela de antecedencia
     *
     * @return array Lista de encargos pendentes
     */
    public function listarPendentesParaFinanceiro(): array
    {
        return $this->qb
            ->table('veiculos_encargos', 've')
            ->select([
                've.*',
                'v.placa',
                'v.marca',
                'v.modelo',
                'v.id_matriz_filial',
            ])
            ->innerJoin('veiculos', 'v', 've.id_veiculo', '=', 'v.id')
            ->where('ve.ativo', '=', 1)
            ->whereNull('ve.id_financeiro')
            ->whereNotNull('ve.valor')
            ->whereRaw('ve.valor > 0')
            ->whereNotNull('ve.vencimento')
            ->whereRaw('ve.vencimento >= CURDATE()')
            ->whereRaw('DATEDIFF(ve.vencimento, CURDATE()) <= ve.dias_antecedencia')
            ->get();
    }

    /**
     * Lista encargos vencidos com recorrencia ativa para renovacao
     *
     * @return array Lista de encargos para renovar
     */
    public function listarParaRenovacao(): array
    {
        return $this->qb
            ->table('veiculos_encargos', 've')
            ->select(['ve.*'])
            ->where('ve.ativo', '=', 1)
            ->whereRaw("ve.recorrencia != 'nenhuma'")
            ->whereRaw('ve.vencimento < CURDATE()')
            ->get();
    }

    /**
     * Conta encargos vencidos ou proximos do vencimento
     * (dentro do periodo de dias_antecedencia)
     */
    public function contarVencidosOuProximos(): int
    {
        return $this->qb
            ->table('veiculos_encargos')
            ->where('ativo', '=', 1)
            ->whereNotNull('vencimento')
            ->whereRaw('vencimento <= DATE_ADD(CURDATE(), INTERVAL dias_antecedencia DAY)')
            ->count();
    }

    /**
     * Lista encargos vencidos ou proximos do vencimento para a tela de notificacoes.
     */
    public function listarParaNotificacoes(int $limit = 25, int $offset = 0): array
    {
        return $this->qb
            ->table('veiculos_encargos', 'e')
            ->select([
                'e.id', 'e.nome', 'e.descricao', 'e.vencimento', 'e.valor',
                'e.id_veiculo', 'v.placa AS veiculo_placa', 'v.modelo AS veiculo_modelo',
            ])
            ->leftJoin('veiculos', 'v', 'e.id_veiculo', '=', 'v.id')
            ->where('e.ativo', '=', 1)
            ->whereNotNull('e.vencimento')
            ->whereRaw('e.vencimento <= DATE_ADD(CURDATE(), INTERVAL e.dias_antecedencia DAY)')
            ->orderBy('e.vencimento', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }
}
