<?php

namespace App\Models;

use App\Helpers\DateHelper;

/**
 * Orçamentos comerciais. O registro mantém snapshots dos valores apresentados.
 */
class Orcamento extends Model
{
    public function listarPaginado(
        int $page,
        int $perPage,
        string $search = '',
        string $filialWhere = '',
        array $filialParams = [],
        string $status = ''
    ): array {
        $query = $this->baseQuery();

        if ($search !== '') {
            $term = '%' . $search . '%';
            $query->whereNested(function ($q) use ($term) {
                $q->where('o.codigo', 'LIKE', $term)
                    ->orWhere('o.cliente_nome', 'LIKE', $term)
                    ->orWhere('g.nome', 'LIKE', $term);
            });
        }
        if ($status !== '') {
            $query->where('o.status', '=', $status);
        }
        if ($filialWhere !== '') {
            $query->whereRaw($filialWhere, $filialParams);
        }

        return $query->orderByDesc('o.id')->paginate($page, $perPage)->get();
    }

    public function contar(
        string $search = '',
        string $filialWhere = '',
        array $filialParams = [],
        string $status = ''
    ): int {
        $query = $this->qb->table('orcamentos', 'o')->leftJoin('grupos', 'g', 'o.id_grupo', '=', 'g.id');
        if ($search !== '') {
            $term = '%' . $search . '%';
            $query->whereNested(function ($q) use ($term) {
                $q->where('o.codigo', 'LIKE', $term)
                    ->orWhere('o.cliente_nome', 'LIKE', $term)
                    ->orWhere('g.nome', 'LIKE', $term);
            });
        }
        if ($status !== '') {
            $query->where('o.status', '=', $status);
        }
        if ($filialWhere !== '') {
            $query->whereRaw($filialWhere, $filialParams);
        }
        return $query->count();
    }

    public function buscarPorId(int $id): ?array
    {
        $orcamento = $this->baseQuery()->where('o.id', '=', $id)->first();
        if ($orcamento) {
            $orcamento['taxas'] = $this->decodeTaxas($orcamento['taxas'] ?? null);
        }
        return $orcamento;
    }

    public function criar(array $dados): int
    {
        $dados['codigo'] = $dados['codigo'] ?? $this->gerarCodigo();
        return $this->qb->table('orcamentos')->insert($this->normalizarPersistencia($dados, true));
    }

    public function atualizar(int $id, array $dados): int
    {
        return $this->qb->table('orcamentos')->where('id', '=', $id)->update($this->normalizarPersistencia($dados, false));
    }

    public function alterarStatus(int $id, string $status): int
    {
        $dados = ['status' => $status];
        if ($status === 'E') {
            $dados['enviado_at'] = DateHelper::nowForDatabase();
        }
        return $this->qb->table('orcamentos')->where('id', '=', $id)->update($dados);
    }

    /**
     * Converte uma única vez o snapshot em reserva, na mesma transação.
     */
    public function converterEmReserva(int $id, int $funcionarioId): int
    {
        $mysqli = $this->getMysqli();
        $mysqli->begin_transaction();

        try {
            $orcamento = $this->buscarPorId($id);
            if (!$orcamento) {
                throw new \InvalidArgumentException('Orçamento não encontrado.');
            }
            if (!empty($orcamento['id_locacao_convertida']) || ($orcamento['status'] ?? '') === 'C') {
                throw new \InvalidArgumentException('Este orçamento já foi convertido.');
            }
            if (in_array($orcamento['status'] ?? '', ['N', 'X'], true)) {
                throw new \InvalidArgumentException('Orçamentos recusados ou expirados não podem ser convertidos.');
            }
            if (($orcamento['validade'] ?? '') < DateHelper::todayForDatabase()) {
                throw new \InvalidArgumentException('O orçamento está vencido. Duplique ou atualize a validade antes de converter.');
            }
            if (empty($orcamento['id_conta']) || empty($orcamento['id_forma_pagamento'])) {
                throw new \InvalidArgumentException('Informe a conta e a forma de pagamento antes da conversão.');
            }

            $locacao = new Locacao();
            $locacaoId = $locacao->criar([
                'chave' => $orcamento['chave'],
                'status' => 'R',
                'id_matriz_filial_retirada' => $orcamento['id_matriz_filial_retirada'],
                'id_matriz_filial_devolucao' => $orcamento['id_matriz_filial_devolucao'],
                'data_saida' => $orcamento['data_saida'],
                'data_prevista' => $orcamento['data_prevista'],
                'dias' => $orcamento['dias'],
                'cliente_nome' => $orcamento['cliente_nome'],
                'id_cliente' => $orcamento['id_cliente'],
                'promocao_codigo' => $orcamento['promocao_codigo'],
                'valor_desconto' => $orcamento['valor_desconto'],
                'id_conta' => $orcamento['id_conta'],
                'id_forma_pagamento' => $orcamento['id_forma_pagamento'],
                'obs' => $orcamento['observacoes_cliente'],
                'total_fatura' => $orcamento['total_fatura'],
                'total_pagar' => $orcamento['total_pagar'],
                'id_funcionario' => $funcionarioId,
            ]);

            $plano = (string) $orcamento['plano'];
            (new LocacaoVeiculo())->adicionar([
                'chave' => $orcamento['chave'],
                'id_locacao' => $locacaoId,
                'id_veiculo' => $orcamento['id_veiculo'],
                'id_grupo' => $orcamento['id_grupo'],
                'data_saida' => $orcamento['data_saida'],
                'plano' => $plano,
                'valor_plano_km_pago' => in_array($plano, ['DI', 'KP'], true) ? $orcamento['diaria_valor'] : 0,
                'valor_plano_km_livre' => $plano === 'KL' ? $orcamento['diaria_valor'] : 0,
                'valor_plano_km_controlado' => $plano === 'KMC' ? $orcamento['diaria_valor'] : 0,
                'km_franquia' => $orcamento['km_franquia'],
                'valor_km_excedente' => $orcamento['valor_km_excedente'],
                'seguro_carro' => $orcamento['seguro_carro'],
                'valor_seguro_carro' => $orcamento['valor_seguro_carro'],
                'seguro_terceiros' => $orcamento['seguro_terceiros'],
                'valor_seguro_terceiros' => $orcamento['valor_seguro_terceiros'],
            ]);

            $taxas = is_array($orcamento['taxas']) ? $orcamento['taxas'] : [];
            if ($taxas) {
                (new LocacaoTaxaServico())->sincronizar($locacaoId, $taxas, (string) $orcamento['chave']);
            }

            $alterados = $this->qb->table('orcamentos')
                ->where('id', '=', $id)
                ->whereNull('id_locacao_convertida')
                ->update([
                    'status' => 'C',
                    'id_locacao_convertida' => $locacaoId,
                    'convertido_at' => DateHelper::nowForDatabase(),
                ]);
            if ($alterados !== 1) {
                throw new \RuntimeException('O orçamento foi convertido por outro usuário.');
            }

            $mysqli->commit();
            return $locacaoId;
        } catch (\Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }
    }

    private function baseQuery(): \App\Classes\QueryBuilder
    {
        return $this->qb->table('orcamentos', 'o')
            ->select([
                'o.*',
                'c.cpf_cnpj AS cliente_documento',
                'c.rua AS cliente_rua',
                'c.numero AS cliente_numero',
                'c.bairro AS cliente_bairro',
                'c.cidade AS cliente_cidade',
                'c.estado AS cliente_estado',
                'fr.nome_fantasia AS filial_retirada_nome',
                'fd.nome_fantasia AS filial_devolucao_nome',
                'g.nome AS grupo_nome_atual',
                'v.placa AS veiculo_placa',
                'v.marca AS veiculo_marca',
                'v.modelo AS veiculo_modelo',
                'f.nome AS funcionario_nome',
                'fp.nome AS forma_pagamento_nome',
                'ct.nome AS conta_nome',
                'l.codigo AS locacao_codigo',
            ])
            ->leftJoin('clientes', 'c', 'o.id_cliente', '=', 'c.id')
            ->leftJoin('matrizes_filiais', 'fr', 'o.id_matriz_filial_retirada', '=', 'fr.id')
            ->leftJoin('matrizes_filiais', 'fd', 'o.id_matriz_filial_devolucao', '=', 'fd.id')
            ->leftJoin('grupos', 'g', 'o.id_grupo', '=', 'g.id')
            ->leftJoin('veiculos', 'v', 'o.id_veiculo', '=', 'v.id')
            ->leftJoin('funcionarios', 'f', 'o.id_funcionario', '=', 'f.id')
            ->leftJoin('formas_pagamento', 'fp', 'o.id_forma_pagamento', '=', 'fp.id')
            ->leftJoin('contas_bancarias', 'ct', 'o.id_conta', '=', 'ct.id')
            ->leftJoin('locacoes', 'l', 'o.id_locacao_convertida', '=', 'l.id');
    }

    private function normalizarPersistencia(array $dados, bool $criando): array
    {
        $campos = [
            'chave', 'codigo', 'status', 'validade', 'origem', 'id_cliente', 'cliente_nome',
            'id_matriz_filial_retirada', 'id_matriz_filial_devolucao', 'id_funcionario',
            'data_saida', 'data_prevista', 'dias', 'id_grupo', 'grupo_nome', 'id_veiculo',
            'plano', 'diaria_valor', 'km_franquia', 'valor_km_excedente', 'seguro_carro',
            'valor_seguro_carro', 'seguro_terceiros', 'valor_seguro_terceiros', 'id_conta',
            'id_forma_pagamento', 'condicao_pagamento', 'promocao_codigo', 'valor_desconto',
            'taxas', 'observacoes_cliente', 'observacoes_internas', 'subtotal_diarias',
            'subtotal_adicionais', 'total_fatura', 'total_pagar',
        ];
        $result = array_intersect_key($dados, array_flip($campos));
        if (isset($result['taxas']) && is_array($result['taxas'])) {
            $result['taxas'] = json_encode($result['taxas'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        foreach (['id_veiculo', 'id_conta', 'id_forma_pagamento'] as $campo) {
            if (array_key_exists($campo, $result) && empty($result[$campo])) {
                $result[$campo] = null;
            }
        }
        if (!$criando) {
            unset($result['chave'], $result['codigo']);
        }
        return $result;
    }

    private function gerarCodigo(): string
    {
        do {
            $codigo = 'O' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 7));
            $existe = $this->qb->table('orcamentos')->where('codigo', '=', $codigo)->exists();
        } while ($existe);
        return $codigo;
    }

    private function decodeTaxas(mixed $taxas): array
    {
        if (is_array($taxas)) {
            return $taxas;
        }
        $decoded = json_decode((string) $taxas, true);
        return is_array($decoded) ? $decoded : [];
    }
}
