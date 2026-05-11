<?php

namespace App\Models;

/**
 * Model LocacaoVeiculo
 *
 * Gerencia veiculos vinculados a uma locacao.
 * Permite historico de substituicoes e rastreamento para multas.
 *
 * Planos:
 * - KMC = Km Controlado (franquia + excedente)
 * - KL = Km Livre (ilimitado)
 * - KP = Km Pago (diaria)
 */
class LocacaoVeiculo extends Model
{
    /**
     * Lista todos os veiculos de uma locacao (historico completo)
     *
     * @param int $locacaoId ID da locacao
     * @return array Lista de veiculos
     */
    public function listarPorLocacao(int $locacaoId): array
    {
        return $this->qb
            ->table('locacoes_veiculos', 'lv')
            ->select([
                'lv.*',
                'v.placa AS veiculo_placa',
                'v.modelo AS veiculo_modelo',
                'v.marca AS veiculo_marca',
                'v.ano AS veiculo_ano',
                'v.cor AS veiculo_cor',
                'v.diagrama AS veiculo_diagrama',
                'v.tipo_combustivel AS veiculo_tipo_combustivel',
                'g.nome AS grupo_nome'
            ])
            ->leftJoin('veiculos', 'v', 'lv.id_veiculo', '=', 'v.id')
            ->leftJoin('grupos', 'g', 'lv.id_grupo', '=', 'g.id')
            ->where('lv.id_locacao', '=', $locacaoId)
            ->orderBy('lv.data_saida', 'ASC')
            ->get();
    }

    /**
     * Busca veiculo ativo da locacao (sem data_entrada = ainda nao devolvido)
     *
     * @param int $locacaoId ID da locacao
     * @return array|null Dados do veiculo ou null
     */
    public function buscarAtivo(int $locacaoId): ?array
    {
        return $this->qb
            ->table('locacoes_veiculos', 'lv')
            ->select([
                'lv.*',
                'v.placa AS veiculo_placa',
                'v.modelo AS veiculo_modelo',
                'v.marca AS veiculo_marca',
                'v.ano AS veiculo_ano',
                'v.cor AS veiculo_cor',
                'v.diagrama AS veiculo_diagrama',
                'v.tipo_combustivel AS veiculo_tipo_combustivel',
                'v.valor_por_fracao AS veiculo_valor_por_fracao',
                'g.nome AS grupo_nome'
            ])
            ->leftJoin('veiculos', 'v', 'lv.id_veiculo', '=', 'v.id')
            ->leftJoin('grupos', 'g', 'lv.id_grupo', '=', 'g.id')
            ->where('lv.id_locacao', '=', $locacaoId)
            ->whereNull('lv.data_entrada')
            ->first();
    }

    /**
     * Busca o veiculo atual da locacao; se nao houver ativo, retorna o ultimo vinculado.
     *
     * Usado em contextos de exibicao/impressao, onde locacoes fechadas continuam
     * precisando mostrar o veiculo mesmo apos `data_entrada` estar preenchida.
     */
    public function buscarAtualOuUltimo(int $locacaoId): ?array
    {
        $veiculo = $this->buscarAtivo($locacaoId);
        if ($veiculo) {
            return $veiculo;
        }

        return $this->qb
            ->table('locacoes_veiculos', 'lv')
            ->select([
                'lv.*',
                'v.placa AS veiculo_placa',
                'v.modelo AS veiculo_modelo',
                'v.marca AS veiculo_marca',
                'v.ano AS veiculo_ano',
                'v.cor AS veiculo_cor',
                'v.diagrama AS veiculo_diagrama',
                'v.tipo_combustivel AS veiculo_tipo_combustivel',
                'v.valor_por_fracao AS veiculo_valor_por_fracao',
                'g.nome AS grupo_nome'
            ])
            ->leftJoin('veiculos', 'v', 'lv.id_veiculo', '=', 'v.id')
            ->leftJoin('grupos', 'g', 'lv.id_grupo', '=', 'g.id')
            ->where('lv.id_locacao', '=', $locacaoId)
            ->orderBy('lv.data_saida', 'DESC')
            ->orderBy('lv.id', 'DESC')
            ->first();
    }

    /**
     * Busca um veiculo da locacao por ID
     *
     * @param int $id ID do registro locacoes_veiculos
     * @return array|null Dados ou null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('locacoes_veiculos', 'lv')
            ->select([
                'lv.*',
                'v.placa AS veiculo_placa',
                'v.modelo AS veiculo_modelo',
                'v.marca AS veiculo_marca',
                'v.ano AS veiculo_ano',
                'v.cor AS veiculo_cor',
                'v.diagrama AS veiculo_diagrama',
                'v.tipo_combustivel AS veiculo_tipo_combustivel',
                'g.nome AS grupo_nome'
            ])
            ->leftJoin('veiculos', 'v', 'lv.id_veiculo', '=', 'v.id')
            ->leftJoin('grupos', 'g', 'lv.id_grupo', '=', 'g.id')
            ->where('lv.id', '=', $id)
            ->first();
    }

    /**
     * Adiciona um veiculo a locacao
     *
     * @param array $dados Dados do veiculo
     * @return int ID criado
     */
    public function adicionar(array $dados): int
    {
        return $this->qb
            ->table('locacoes_veiculos')
            ->insert([
                'chave' => $dados['chave'],
                'id_locacao' => (int) $dados['id_locacao'],
                'id_veiculo' => !empty($dados['id_veiculo']) ? (int) $dados['id_veiculo'] : null,
                'id_grupo' => !empty($dados['id_grupo']) ? (int) $dados['id_grupo'] : null,
                'data_saida' => $dados['data_saida'] ?? date('Y-m-d H:i:s'),
                'data_entrada' => $dados['data_entrada'] ?? null,
                'plano' => $dados['plano'] ?? 'KL',
                'valor_plano_km_pago' => currency_parse($dados['valor_plano_km_pago'] ?? 0),
                'valor_plano_km_livre' => currency_parse($dados['valor_plano_km_livre'] ?? 0),
                'valor_plano_km_controlado' => currency_parse($dados['valor_plano_km_controlado'] ?? 0),
                'km_franquia' => $this->toNullableInt($dados['km_franquia'] ?? null),
                'valor_km_excedente' => currency_parse($dados['valor_km_excedente'] ?? 0),
                'minutos_tolerancia' => $this->toInt($dados['minutos_tolerancia'] ?? 0),
                'valor_tolerancia' => currency_parse($dados['valor_tolerancia'] ?? 0),
                'valor_km_retorno' => currency_parse($dados['valor_km_retorno'] ?? 0),
                'valor_condutor_adicional' => currency_parse($dados['valor_condutor_adicional'] ?? 0),
                'seguro_carro' => isset($dados['seguro_carro']) ? (int) $dados['seguro_carro'] : 0,
                'valor_seguro_carro' => currency_parse($dados['valor_seguro_carro'] ?? 0),
                'cobertura_carro' => currency_parse($dados['cobertura_carro'] ?? 0),
                'seguro_terceiros' => isset($dados['seguro_terceiros']) ? (int) $dados['seguro_terceiros'] : 0,
                'valor_seguro_terceiros' => currency_parse($dados['valor_seguro_terceiros'] ?? 0),
                'cobertura_terceiros' => currency_parse($dados['cobertura_terceiros'] ?? 0),
                'odometro_saida' => $this->toNullableInt($dados['odometro_saida'] ?? null) ?? 0,
                'odometro_entrada' => $this->toNullableInt($dados['odometro_entrada'] ?? null),
                'combustivel_saida' => $this->toNullableInt($dados['combustivel_saida'] ?? null),
                'combustivel_entrada' => $this->toNullableInt($dados['combustivel_entrada'] ?? null),
                'odometro_usado' => $this->toNullableInt($dados['odometro_usado'] ?? null),
                'km_excedente' => $this->toNullableInt($dados['km_excedente'] ?? null),
                'combustivel_usado' => $this->toNullableInt($dados['combustivel_usado'] ?? null),
                'combustivel_valor' => isset($dados['combustivel_valor']) ? currency_parse($dados['combustivel_valor']) : null,
                'motivo_saida' => $dados['motivo_saida'] ?? null,
                'acao_valores' => $dados['acao_valores'] ?? null,
            ]);
    }

    /**
     * Atualiza um veiculo da locacao
     *
     * @param int $id ID do registro
     * @param array $dados Dados a atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $dadosUpdate = [];

        if (array_key_exists('id_veiculo', $dados)) {
            $dadosUpdate['id_veiculo'] = !empty($dados['id_veiculo']) ? (int) $dados['id_veiculo'] : null;
        }
        if (isset($dados['id_grupo'])) {
            $dadosUpdate['id_grupo'] = !empty($dados['id_grupo']) ? (int) $dados['id_grupo'] : null;
        }
        if (isset($dados['data_saida'])) {
            $dadosUpdate['data_saida'] = $dados['data_saida'];
        }
        if (array_key_exists('data_entrada', $dados)) {
            $dadosUpdate['data_entrada'] = $dados['data_entrada'];
        }
        if (isset($dados['plano'])) {
            $dadosUpdate['plano'] = $dados['plano'];
        }

        // Valores de plano
        $camposDecimal = [
            'valor_plano_km_pago', 'valor_plano_km_livre', 'valor_plano_km_controlado',
            'valor_km_excedente', 'valor_tolerancia', 'valor_km_retorno',
            'valor_condutor_adicional', 'valor_seguro_carro', 'cobertura_carro',
            'valor_seguro_terceiros', 'cobertura_terceiros', 'combustivel_valor'
        ];
        foreach ($camposDecimal as $campo) {
            if (isset($dados[$campo])) {
                $dadosUpdate[$campo] = currency_parse($dados[$campo]);
            }
        }

        $camposInt = ['minutos_tolerancia', 'odometro_saida'];
        foreach ($camposInt as $campo) {
            if (isset($dados[$campo])) {
                $dadosUpdate[$campo] = $this->toInt($dados[$campo]);
            }
        }

        $camposNullableInt = [
            'km_franquia', 'odometro_entrada', 'odometro_usado',
            'km_excedente', 'combustivel_saida', 'combustivel_entrada',
            'combustivel_usado',
        ];
        foreach ($camposNullableInt as $campo) {
            if (array_key_exists($campo, $dados)) {
                $dadosUpdate[$campo] = $this->toNullableInt($dados[$campo]);
            }
        }

        // Seguros
        if (isset($dados['seguro_carro'])) {
            $dadosUpdate['seguro_carro'] = (int) $dados['seguro_carro'];
        }
        if (isset($dados['seguro_terceiros'])) {
            $dadosUpdate['seguro_terceiros'] = (int) $dados['seguro_terceiros'];
        }

        // Substituicao
        if (array_key_exists('motivo_saida', $dados)) {
            $dadosUpdate['motivo_saida'] = $dados['motivo_saida'];
        }
        if (array_key_exists('acao_valores', $dados)) {
            $dadosUpdate['acao_valores'] = $dados['acao_valores'];
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        $dadosUpdate['updated_at'] = date('Y-m-d H:i:s');

        return $this->qb
            ->table('locacoes_veiculos')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Registra devolucao (entrada) de um veiculo da locacao
     *
     * @param int $id ID do registro
     * @param array $dadosDevolucao Dados de devolucao (odometro_entrada, combustivel_entrada, etc)
     * @return int Linhas afetadas
     */
    public function devolver(int $id, array $dadosDevolucao): int
    {
        $veiculo = $this->buscarPorId($id);
        if (!$veiculo) {
            return 0;
        }

        // odometro_entrada = odometro na devolucao (veiculo entra na empresa)
        // odometro_saida = odometro na saida (veiculo saiu da empresa)
        $odometroEntrada = $this->toInt($dadosDevolucao['odometro_entrada'] ?? 0);
        $odometroSaida = (int) ($veiculo['odometro_saida'] ?? 0);
        if ($odometroEntrada > 0 && $odometroEntrada < $odometroSaida) {
            throw new \InvalidArgumentException('Odometro de devolucao nao pode ser menor que o odometro de saida');
        }

        $odometroUsado = $odometroEntrada > 0 ? $odometroEntrada - $odometroSaida : null;

        // Calcular km excedente se plano KMC
        $kmExcedente = null;
        if ($veiculo['plano'] === 'KMC' && $odometroUsado !== null && !empty($veiculo['km_franquia'])) {
            $kmExcedente = max(0, $odometroUsado - (int) $veiculo['km_franquia']);
        }

        // combustivel_entrada = combustivel na devolucao (veiculo entra na empresa)
        // combustivel_saida = combustivel na saida (veiculo saiu da empresa)
        $combustivelEntrada = $dadosDevolucao['combustivel_entrada'] ?? null;
        $combustivelSaida = $veiculo['combustivel_saida'];
        $combustivelUsado = ($combustivelEntrada !== null && $combustivelSaida !== null)
            ? (int) $combustivelSaida - (int) $combustivelEntrada
            : null;

        return $this->qb
            ->table('locacoes_veiculos')
            ->where('id', '=', $id)
            ->update([
                'data_entrada' => $dadosDevolucao['data_entrada'] ?? date('Y-m-d H:i:s'),
                'odometro_entrada' => $odometroEntrada ?: null,
                'combustivel_entrada' => $combustivelEntrada,
                'odometro_usado' => $odometroUsado,
                'km_excedente' => $kmExcedente,
                'combustivel_usado' => $combustivelUsado,
                'combustivel_valor' => isset($dadosDevolucao['combustivel_valor'])
                    ? currency_parse($dadosDevolucao['combustivel_valor'])
                    : null,
                'motivo_saida' => $dadosDevolucao['motivo_saida'] ?? null,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
    }

    /**
     * Substitui um veiculo na locacao
     *
     * @param int $idVeiculoAntigo ID do registro do veiculo a substituir
     * @param array $dadosSaida Dados de devolucao (odometro_entrada, combustivel_entrada, motivo)
     * @param array $dadosNovo Dados do novo veiculo
     * @param bool $manterValores Manter valores do veiculo antigo
     * @return int ID do novo registro criado
     */
    public function substituir(int $idVeiculoAntigo, array $dadosSaida, array $dadosNovo, bool $manterValores = false): int
    {
        $veiculoAntigo = $this->buscarPorId($idVeiculoAntigo);
        if (!$veiculoAntigo) {
            throw new \InvalidArgumentException('Veículo da locação não encontrado');
        }

        // Registrar saida do veiculo antigo
        $this->devolver($idVeiculoAntigo, array_merge($dadosSaida, [
            'motivo_saida' => $dadosSaida['motivo_saida'] ?? 'Substituição de veículo'
        ]));

        // Preparar dados do novo veiculo
        $dadosInsert = $dadosNovo;
        $dadosInsert['chave'] = $veiculoAntigo['chave'];
        $dadosInsert['id_locacao'] = $veiculoAntigo['id_locacao'];
        $dadosInsert['data_saida'] = date('Y-m-d H:i:s');
        $dadosInsert['acao_valores'] = $manterValores ? 'manter' : 'grupo';

        // Se manter valores, copiar do veiculo antigo
        if ($manterValores) {
            $camposValores = [
                'plano', 'valor_plano_km_pago', 'valor_plano_km_livre', 'valor_plano_km_controlado',
                'km_franquia', 'valor_km_excedente', 'minutos_tolerancia', 'valor_tolerancia',
                'valor_km_retorno', 'valor_condutor_adicional', 'seguro_carro', 'valor_seguro_carro',
                'cobertura_carro', 'seguro_terceiros', 'valor_seguro_terceiros', 'cobertura_terceiros'
            ];

            foreach ($camposValores as $campo) {
                if (!isset($dadosInsert[$campo])) {
                    $dadosInsert[$campo] = $veiculoAntigo[$campo];
                }
            }
        }

        return $this->adicionar($dadosInsert);
    }

    /**
     * Remove um veiculo da locacao
     *
     * @param int $id ID do registro
     * @return int Linhas afetadas
     */
    public function remover(int $id): int
    {
        return $this->qb
            ->table('locacoes_veiculos')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Conta veiculos ativos em uma locacao
     *
     * @param int $locacaoId ID da locacao
     * @return int Quantidade de veiculos ativos
     */
    public function contarAtivos(int $locacaoId): int
    {
        return $this->qb
            ->table('locacoes_veiculos')
            ->where('id_locacao', '=', $locacaoId)
            ->whereNull('data_entrada')
            ->count();
    }

    /**
     * Verifica se um veiculo esta locado em alguma locacao ativa
     *
     * @param int $veiculoId ID do veiculo
     * @param int|null $excluirLocacaoId Locacao a ignorar na busca
     * @return array|null Dados da locacao ou null se disponivel
     */
    public function veiculoEstaLocado(int $veiculoId, ?int $excluirLocacaoId = null): ?array
    {
        $query = $this->qb
            ->table('locacoes_veiculos', 'lv')
            ->select([
                'lv.id_locacao',
                'l.codigo AS locacao_codigo',
                'l.data_saida',
                'l.data_prevista'
            ])
            ->leftJoin('locacoes', 'l', 'lv.id_locacao', '=', 'l.id')
            ->where('lv.id_veiculo', '=', $veiculoId)
            ->whereNull('lv.data_entrada')
            ->whereIn('l.status', ['R', 'A']);

        if ($excluirLocacaoId !== null) {
            $query->where('lv.id_locacao', '!=', $excluirLocacaoId);
        }

        return $query->first();
    }

    /**
     * Busca responsavel por multa em um periodo
     * Encontra a locacao que estava com o veiculo na data/hora da infracao
     *
     * @param int $veiculoId ID do veiculo
     * @param string $dataHoraMulta Data/hora da infracao (Y-m-d H:i:s)
     * @return array|null Dados da locacao/cliente responsavel ou null
     */
    public function findResponsavelByMulta(int $veiculoId, string $dataHoraMulta): ?array
    {
        return $this->qb
            ->table('locacoes_veiculos', 'lv')
            ->select([
                'l.id AS locacao_id',
                'l.codigo AS locacao_codigo',
                'l.id_cliente',
                'cl.nome_rsocial AS cliente_nome',
                'cl.cpf_cnpj AS cliente_cpf_cnpj'
            ])
            ->leftJoin('locacoes', 'l', 'lv.id_locacao', '=', 'l.id')
            ->leftJoin('clientes', 'cl', 'l.id_cliente', '=', 'cl.id')
            ->where('lv.id_veiculo', '=', $veiculoId)
            ->where('lv.data_saida', '<=', $dataHoraMulta)
            ->whereNested(function ($q) use ($dataHoraMulta) {
                $q->whereNull('lv.data_entrada')
                  ->orWhere('lv.data_entrada', '>=', $dataHoraMulta);
            })
            ->first();
    }

    /**
     * Carrega valores do grupo na moeda da filial de retirada.
     * Fonte unica: `grupos_precos_filiais` (Fase 1+4 do refactor multi-moeda).
     *
     * @param int $grupoId ID do grupo
     * @param int|null $filialId ID da filial de retirada (onde o pagamento ocorre).
     *                            Obrigatorio pra retornar valores — sem filial, retorna [].
     * @return array Valores do grupo para a filial
     */
    public function carregarValoresGrupo(int $grupoId, ?int $filialId = null): array
    {
        if ($filialId === null) {
            return [];
        }

        $valores = (new GrupoPrecoFilial())->buscarPorGrupoFilial($grupoId, $filialId);
        if (!$valores) {
            return [];
        }

        return [
            'valor_plano_km_pago' => $valores['valor_plano_km_pago'] ?? 0,
            'valor_plano_km_livre' => $valores['valor_plano_km_livre'] ?? 0,
            'valor_plano_km_controlado' => $valores['valor_plano_km_controlado'] ?? 0,
            'km_franquia' => $valores['km_franquia'] ?? 0,
            'valor_km_excedente' => $valores['valor_km_excedente'] ?? 0,
            'minutos_tolerancia' => $valores['minutos_tolerancia'] ?? 0,
            'valor_tolerancia' => $valores['valor_tolerancia'] ?? 0,
            'valor_km_retorno' => $valores['valor_km_retorno'] ?? 0,
            'valor_condutor_adicional' => $valores['valor_condutor_adicional'] ?? 0,
            'valor_seguro_carro' => $valores['valor_seguro_carro'] ?? 0,
            'cobertura_carro' => $valores['cobertura_carro'] ?? 0,
            'valor_seguro_terceiros' => $valores['valor_seguro_terceiros'] ?? 0,
            'cobertura_terceiros' => $valores['cobertura_terceiros'] ?? 0,
        ];
    }

    /**
     * Converte valor para inteiro com suporte ao separador de milhar PT-BR.
     * Ex.: "41.450" -> 41450 (em vez de (int) cast direto que truncaria para 41).
     */
    private function toInt($valor): int
    {
        if (is_int($valor)) {
            return $valor;
        }
        if (is_string($valor)) {
            // Remove tudo que nao for digito (ponto/virgula sao separadores).
            $valor = preg_replace('/\D/', '', $valor);
        }
        return (int) $valor;
    }

    private function toNullableInt($valor): ?int
    {
        if ($valor === null) {
            return null;
        }

        if (is_string($valor) && trim($valor) === '') {
            return null;
        }

        return $this->toInt($valor);
    }
}
