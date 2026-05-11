<?php

namespace App\Models;

use App\Traits\Auditable;

/**
 * Model ComissaoInvestidor
 *
 * Gerencia comissoes de investidores geradas a partir de
 * locacoes, contratos ou cobrancas mensais fixas.
 */
class ComissaoInvestidor extends Model
{
    use Auditable;

    /**
     * Retorna o nome da entidade para auditoria
     */
    public function getEntidadeAuditoria(): string
    {
        return 'ComissaoInvestidor';
    }

    /**
     * Retorna o campo identificador para auditoria
     */
    public function getCampoIdentificador(): string
    {
        return 'id';
    }

    /**
     * Lista comissoes do tenant com paginacao e filtros
     *
     * @param string $chave Chave do tenant
     * @param int $page Pagina atual
     * @param int $perPage Registros por pagina
     * @param array $filtros Filtros opcionais
     * @return array Lista de comissoes
     */
    public function listarPaginado(string $chave, int $page, int $perPage, array $filtros = []): array
    {
        $query = $this->qb
            ->table('comissoes_investidores', 'ci')
            ->select([
                'ci.*',
                'f.nome_rsocial AS fornecedor_nome',
                'f.cpf_cnpj AS fornecedor_cpf_cnpj',
                'v.placa AS veiculo_placa',
                'v.modelo AS veiculo_modelo',
                'g.nome AS grupo_nome'
            ])
            ->withoutChave()
            ->leftJoin('fornecedores', 'f', 'f.id', '=', 'ci.id_fornecedor')
            ->leftJoin('veiculos', 'v', 'v.id', '=', 'ci.id_veiculo')
            ->leftJoin('grupos', 'g', 'g.id', '=', 'ci.id_grupo')
            ->where('ci.chave', '=', $chave);

        // Filtro por fornecedor/investidor
        if (!empty($filtros['id_fornecedor'])) {
            $query->where('ci.id_fornecedor', '=', (int) $filtros['id_fornecedor']);
        }

        // Filtro por status
        if (!empty($filtros['status'])) {
            $query->where('ci.status', '=', $filtros['status']);
        }

        // Filtro por tipo de origem
        if (!empty($filtros['tipo_origem'])) {
            $query->where('ci.tipo_origem', '=', $filtros['tipo_origem']);
        }

        // Filtro por periodo (data_referencia)
        if (!empty($filtros['data_inicio'])) {
            $query->where('ci.data_referencia', '>=', $filtros['data_inicio']);
        }
        if (!empty($filtros['data_fim'])) {
            $query->where('ci.data_referencia', '<=', $filtros['data_fim']);
        }

        // Filtro por veiculo
        if (!empty($filtros['id_veiculo'])) {
            $query->where('ci.id_veiculo', '=', (int) $filtros['id_veiculo']);
        }

        return $query
            ->orderBy('ci.data_referencia', 'DESC')
            ->orderBy('ci.id', 'DESC')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta o total de comissoes do tenant
     *
     * @param string $chave Chave do tenant
     * @param array $filtros Filtros opcionais
     * @return int Total de registros
     */
    public function contar(string $chave, array $filtros = []): int
    {
        $query = $this->qb
            ->table('comissoes_investidores')
            ->withoutChave()
            ->where('chave', '=', $chave);

        if (!empty($filtros['id_fornecedor'])) {
            $query->where('id_fornecedor', '=', (int) $filtros['id_fornecedor']);
        }
        if (!empty($filtros['status'])) {
            $query->where('status', '=', $filtros['status']);
        }
        if (!empty($filtros['tipo_origem'])) {
            $query->where('tipo_origem', '=', $filtros['tipo_origem']);
        }
        if (!empty($filtros['data_inicio'])) {
            $query->where('data_referencia', '>=', $filtros['data_inicio']);
        }
        if (!empty($filtros['data_fim'])) {
            $query->where('data_referencia', '<=', $filtros['data_fim']);
        }
        if (!empty($filtros['id_veiculo'])) {
            $query->where('id_veiculo', '=', (int) $filtros['id_veiculo']);
        }

        return $query->count();
    }

    /**
     * Busca uma comissao por ID
     *
     * @param int $id ID da comissao
     * @return array|null Dados ou null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('comissoes_investidores', 'ci')
            ->select([
                'ci.*',
                'f.nome_rsocial AS fornecedor_nome',
                'f.cpf_cnpj AS fornecedor_cpf_cnpj',
                'v.placa AS veiculo_placa',
                'v.modelo AS veiculo_modelo',
                'g.nome AS grupo_nome'
            ])
            ->withoutChave()
            ->leftJoin('fornecedores', 'f', 'f.id', '=', 'ci.id_fornecedor')
            ->leftJoin('veiculos', 'v', 'v.id', '=', 'ci.id_veiculo')
            ->leftJoin('grupos', 'g', 'g.id', '=', 'ci.id_grupo')
            ->where('ci.id', '=', $id)
            ->first();
    }

    /**
     * Cria uma nova comissao
     *
     * @param array $dados Dados da comissao
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('comissoes_investidores')
            ->insert([
                'chave' => $dados['chave'],
                'id_fornecedor' => $dados['id_fornecedor'],
                'id_veiculo' => $dados['id_veiculo'] ?? null,
                'id_grupo' => $dados['id_grupo'] ?? null,
                'tipo_origem' => $dados['tipo_origem'],
                'id_locacao' => $dados['id_locacao'] ?? null,
                'id_contrato' => $dados['id_contrato'] ?? null,
                'id_financeiro_origem' => $dados['id_financeiro_origem'] ?? null,
                'valor_base' => $dados['valor_base'],
                'comissao_tipo' => $dados['comissao_tipo'],
                'comissao_percentual' => $dados['comissao_percentual'] ?? null,
                'comissao_valor_fixo' => $dados['comissao_valor_fixo'] ?? null,
                'valor_comissao_locadora' => $dados['valor_comissao_locadora'],
                'valor_repasse_investidor' => $dados['valor_repasse_investidor'],
                'status' => $dados['status'] ?? 'pendente',
                'data_referencia' => $dados['data_referencia'],
                'data_pagamento' => $dados['data_pagamento'] ?? null,
                'split_aplicado' => $dados['split_aplicado'] ?? 0,
                'split_transaction_id' => $dados['split_transaction_id'] ?? null,
                'id_financeiro' => $dados['id_financeiro'] ?? null,
            ]);
    }

    /**
     * Atualiza uma comissao existente
     *
     * @param int $id ID da comissao
     * @param array $dados Dados para atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $comissao = $this->buscarPorId($id);
        if (!$comissao) {
            throw new \InvalidArgumentException('Comissao nao encontrada');
        }

        $dadosUpdate = [];

        $camposPermitidos = [
            'status', 'data_pagamento', 'split_aplicado',
            'split_transaction_id', 'id_financeiro'
        ];

        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $dados)) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        $dadosUpdate['updated_at'] = date('Y-m-d H:i:s');

        return $this->qb
            ->table('comissoes_investidores')
            ->withoutChave()
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Retorna totais de comissoes por status
     *
     * @param string $chave Chave do tenant
     * @param array $filtros Filtros opcionais
     * @return array Totais por status
     */
    public function totaisPorStatus(string $chave, array $filtros = []): array
    {
        $pendente = $this->calcularTotalPorStatus($chave, 'pendente', $filtros);
        $pago = $this->calcularTotalPorStatus($chave, 'pago', $filtros);
        $cancelado = $this->calcularTotalPorStatus($chave, 'cancelado', $filtros);

        return [
            'pendente' => [
                'quantidade' => $pendente['quantidade'],
                'valor_repasse' => $pendente['valor_repasse'],
                'valor_locadora' => $pendente['valor_locadora']
            ],
            'pago' => [
                'quantidade' => $pago['quantidade'],
                'valor_repasse' => $pago['valor_repasse'],
                'valor_locadora' => $pago['valor_locadora']
            ],
            'cancelado' => [
                'quantidade' => $cancelado['quantidade'],
                'valor_repasse' => $cancelado['valor_repasse'],
                'valor_locadora' => $cancelado['valor_locadora']
            ]
        ];
    }

    /**
     * Calcula total para um status especifico
     */
    private function calcularTotalPorStatus(string $chave, string $status, array $filtros): array
    {
        $query = $this->qb
            ->table('comissoes_investidores')
            ->select([
                'COUNT(*) AS quantidade',
                'COALESCE(SUM(valor_repasse_investidor), 0) AS valor_repasse',
                'COALESCE(SUM(valor_comissao_locadora), 0) AS valor_locadora'
            ])
            ->withoutChave()
            ->where('chave', '=', $chave)
            ->where('status', '=', $status);

        if (!empty($filtros['id_fornecedor'])) {
            $query->where('id_fornecedor', '=', (int) $filtros['id_fornecedor']);
        }
        if (!empty($filtros['data_inicio'])) {
            $query->where('data_referencia', '>=', $filtros['data_inicio']);
        }
        if (!empty($filtros['data_fim'])) {
            $query->where('data_referencia', '<=', $filtros['data_fim']);
        }

        $result = $query->first();

        return [
            'quantidade' => (int) ($result['quantidade'] ?? 0),
            'valor_repasse' => (float) ($result['valor_repasse'] ?? 0),
            'valor_locadora' => (float) ($result['valor_locadora'] ?? 0)
        ];
    }

    /**
     * Busca comissoes pendentes de um investidor
     *
     * @param string $chave Chave do tenant
     * @param int $idFornecedor ID do investidor
     * @return array Lista de comissoes pendentes
     */
    public function listarPendentesPorInvestidor(string $chave, int $idFornecedor): array
    {
        return $this->qb
            ->table('comissoes_investidores', 'ci')
            ->select([
                'ci.*',
                'v.placa AS veiculo_placa',
                'v.modelo AS veiculo_modelo'
            ])
            ->withoutChave()
            ->leftJoin('veiculos', 'v', 'v.id', '=', 'ci.id_veiculo')
            ->where('ci.chave', '=', $chave)
            ->where('ci.id_fornecedor', '=', $idFornecedor)
            ->where('ci.status', '=', 'pendente')
            ->orderBy('ci.data_referencia', 'ASC')
            ->get();
    }

    /**
     * Verifica se ja existe comissao para um financeiro de origem especifico
     *
     * @param string $chave Chave do tenant
     * @param int $idFinanceiroOrigem ID do lancamento financeiro que gerou a comissao
     * @return bool
     */
    public function existeParaOrigem(string $chave, int $idFinanceiroOrigem): bool
    {
        $count = $this->qb
            ->table('comissoes_investidores')
            ->withoutChave()
            ->where('chave', '=', $chave)
            ->where('id_financeiro_origem', '=', $idFinanceiroOrigem)
            ->where('status', '!=', 'cancelado')
            ->count();

        return $count > 0;
    }
}
