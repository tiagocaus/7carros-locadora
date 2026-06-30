<?php

namespace App\Models;

/**
 * Model ContratoVeiculo
 *
 * Gerencia veiculos vinculados a um contrato de locacao.
 * Permite multiplos veiculos por contrato e historico de substituicoes.
 *
 * Planos:
 * - KMC = Km Controlado (franquia + excedente)
 * - KL = Km Livre (ilimitado)
 * - KP = Km Pago
 */
class ContratoVeiculo extends Model
{
    /**
     * Lista todos os veiculos de um contrato
     *
     * @param int $contratoId ID do contrato
     * @return array Lista de veiculos
     */
    public function listarPorContrato(int $contratoId): array
    {
        return $this->qb
            ->table('contratos_veiculos', 'cv')
            ->select([
                'cv.*',
                'v.placa AS veiculo_placa',
                'v.modelo AS veiculo_modelo',
                'v.marca AS veiculo_marca',
                'v.ano AS veiculo_ano',
                'v.cor AS veiculo_cor',
                'v.renavam AS veiculo_renavam',
                'v.chassi AS veiculo_chassi',
                'v.id_fornecedor',
                'v.odometro AS veiculo_odometro',
                'v.diagrama AS veiculo_diagrama',
                'v.tipo_combustivel AS veiculo_tipo_combustivel',
                'v.valor_compra AS veiculo_valor_compra',
                'f.nome_rsocial AS fornecedor_nome',
                'f.cpf_cnpj AS fornecedor_cpf_cnpj',
                'f.investidor AS fornecedor_investidor',
                'g.nome AS grupo_nome'
            ])
            ->leftJoin('veiculos', 'v', 'cv.id_veiculo', '=', 'v.id')
            ->leftJoinRaw('fornecedores', 'f', 'v.id_fornecedor = f.id AND f.chave = cv.chave')
            ->leftJoin('grupos', 'g', 'cv.id_grupo', '=', 'g.id')
            ->where('cv.id_contrato', '=', $contratoId)
            ->orderBy('cv.data_saida', 'ASC')
            ->get();
    }

    /**
     * Lista apenas veiculos ativos do contrato (sem data_entrada)
     *
     * @param int $contratoId ID do contrato
     * @return array Lista de veiculos ativos
     */
    public function listarAtivos(int $contratoId): array
    {
        return $this->qb
            ->table('contratos_veiculos', 'cv')
            ->select([
                'cv.*',
                'v.placa AS veiculo_placa',
                'v.modelo AS veiculo_modelo',
                'v.marca AS veiculo_marca',
                'v.ano AS veiculo_ano',
                'v.cor AS veiculo_cor',
                'v.renavam AS veiculo_renavam',
                'v.chassi AS veiculo_chassi',
                'v.id_fornecedor',
                'v.odometro AS veiculo_odometro',
                'v.diagrama AS veiculo_diagrama',
                'v.tipo_combustivel AS veiculo_tipo_combustivel',
                'v.valor_por_fracao AS veiculo_valor_por_fracao',
                'v.valor_compra AS veiculo_valor_compra',
                'f.nome_rsocial AS fornecedor_nome',
                'f.cpf_cnpj AS fornecedor_cpf_cnpj',
                'f.investidor AS fornecedor_investidor',
                'g.nome AS grupo_nome'
            ])
            ->leftJoin('veiculos', 'v', 'cv.id_veiculo', '=', 'v.id')
            ->leftJoinRaw('fornecedores', 'f', 'v.id_fornecedor = f.id AND f.chave = cv.chave')
            ->leftJoin('grupos', 'g', 'cv.id_grupo', '=', 'g.id')
            ->where('cv.id_contrato', '=', $contratoId)
            ->whereNull('cv.data_entrada')
            ->orderBy('cv.data_saida', 'ASC')
            ->get();
    }

    /**
     * Busca veiculo ativo principal do contrato
     *
     * @param int $contratoId ID do contrato
     * @return array|null Dados do veiculo ou null
     */
    public function buscarAtivo(int $contratoId): ?array
    {
        return $this->qb
            ->table('contratos_veiculos', 'cv')
            ->select([
                'cv.*',
                'v.placa AS veiculo_placa',
                'v.modelo AS veiculo_modelo',
                'v.marca AS veiculo_marca',
                'v.ano AS veiculo_ano',
                'v.cor AS veiculo_cor',
                'v.renavam AS veiculo_renavam',
                'v.chassi AS veiculo_chassi',
                'v.id_fornecedor',
                'v.odometro AS veiculo_odometro',
                'v.diagrama AS veiculo_diagrama',
                'v.tipo_combustivel AS veiculo_tipo_combustivel',
                'v.valor_compra AS veiculo_valor_compra',
                'f.nome_rsocial AS fornecedor_nome',
                'f.cpf_cnpj AS fornecedor_cpf_cnpj',
                'f.investidor AS fornecedor_investidor',
                'g.nome AS grupo_nome'
            ])
            ->leftJoin('veiculos', 'v', 'cv.id_veiculo', '=', 'v.id')
            ->leftJoinRaw('fornecedores', 'f', 'v.id_fornecedor = f.id AND f.chave = cv.chave')
            ->leftJoin('grupos', 'g', 'cv.id_grupo', '=', 'g.id')
            ->where('cv.id_contrato', '=', $contratoId)
            ->whereNull('cv.data_entrada')
            ->first();
    }

    /**
     * Busca um veiculo do contrato por ID
     *
     * @param int $id ID do registro contratos_veiculos
     * @return array|null Dados ou null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('contratos_veiculos', 'cv')
            ->select([
                'cv.*',
                'v.placa AS veiculo_placa',
                'v.modelo AS veiculo_modelo',
                'v.marca AS veiculo_marca',
                'v.ano AS veiculo_ano',
                'v.cor AS veiculo_cor',
                'v.renavam AS veiculo_renavam',
                'v.chassi AS veiculo_chassi',
                'v.id_fornecedor',
                'v.odometro AS veiculo_odometro',
                'v.diagrama AS veiculo_diagrama',
                'v.valor_compra AS veiculo_valor_compra',
                'f.nome_rsocial AS fornecedor_nome',
                'f.cpf_cnpj AS fornecedor_cpf_cnpj',
                'f.investidor AS fornecedor_investidor',
                'g.nome AS grupo_nome'
            ])
            ->leftJoin('veiculos', 'v', 'cv.id_veiculo', '=', 'v.id')
            ->leftJoinRaw('fornecedores', 'f', 'v.id_fornecedor = f.id AND f.chave = cv.chave')
            ->leftJoin('grupos', 'g', 'cv.id_grupo', '=', 'g.id')
            ->where('cv.id', '=', $id)
            ->first();
    }

    /**
     * Adiciona um veiculo ao contrato
     *
     * @param array $dados Dados do veiculo
     * @return int ID criado
     */
    public function adicionar(array $dados): int
    {
        $dados = $this->normalizarValoresPlano($dados);

        return $this->qb
            ->table('contratos_veiculos')
            ->insert([
                'chave' => $dados['chave'],
                'id_contrato' => (int) $dados['id_contrato'],
                'id_veiculo' => (int) $dados['id_veiculo'],
                'id_grupo' => !empty($dados['id_grupo']) ? (int) $dados['id_grupo'] : null,
                'data_saida' => $dados['data_saida'] ?? now(),
                'data_entrada' => $dados['data_entrada'] ?? null,
                'plano' => $dados['plano'] ?? 'KL',
                'valor_plano_km_pago' => \currency_parse($dados['valor_plano_km_pago'] ?? 0),
                'valor_plano_km_livre' => \currency_parse($dados['valor_plano_km_livre'] ?? 0),
                'valor_plano_km_controlado' => \currency_parse($dados['valor_plano_km_controlado'] ?? 0),
                'km_franquia' => $this->parseIntegerInput($dados['km_franquia'] ?? 0),
                'valor_km_excedente' => \currency_parse($dados['valor_km_excedente'] ?? 0),
                'minutos_tolerancia' => (int) ($dados['minutos_tolerancia'] ?? 0),
                'valor_tolerancia' => \currency_parse($dados['valor_tolerancia'] ?? 0),
                'valor_km_retorno' => \currency_parse($dados['valor_km_retorno'] ?? 0),
                'valor_condutor_adicional' => \currency_parse($dados['valor_condutor_adicional'] ?? 0),
                'seguro_carro' => isset($dados['seguro_carro']) ? (int) $dados['seguro_carro'] : 0,
                'valor_seguro_carro' => \currency_parse($dados['valor_seguro_carro'] ?? 0),
                'cobertura_carro' => \currency_parse($dados['cobertura_carro'] ?? 0),
                'seguro_terceiros' => isset($dados['seguro_terceiros']) ? (int) $dados['seguro_terceiros'] : 0,
                'valor_seguro_terceiros' => \currency_parse($dados['valor_seguro_terceiros'] ?? 0),
                'cobertura_terceiros' => \currency_parse($dados['cobertura_terceiros'] ?? 0),
                'odometro_saida' => $this->parseIntegerInput($dados['odometro_saida'] ?? 0),
                'odometro_entrada' => array_key_exists('odometro_entrada', $dados) && $dados['odometro_entrada'] !== null
                    ? $this->parseIntegerInput($dados['odometro_entrada'])
                    : null,
                'combustivel_saida' => $dados['combustivel_saida'] ?? null,
                'combustivel_entrada' => $dados['combustivel_entrada'] ?? null,
                'motivo_saida' => $dados['motivo_saida'] ?? null,
                'acao_valores' => $dados['acao_valores'] ?? null,
            ]);
    }

    /**
     * Atualiza um veiculo do contrato
     *
     * @param int $id ID do registro
     * @param array $dados Dados a atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $dados = $this->normalizarValoresPlano($dados);
        $dadosUpdate = [];

        if (isset($dados['id_veiculo'])) {
            $dadosUpdate['id_veiculo'] = (int) $dados['id_veiculo'];
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

        // Valores
        if (isset($dados['valor_plano_km_pago'])) {
            $dadosUpdate['valor_plano_km_pago'] = \currency_parse($dados['valor_plano_km_pago']);
        }
        if (isset($dados['valor_plano_km_livre'])) {
            $dadosUpdate['valor_plano_km_livre'] = \currency_parse($dados['valor_plano_km_livre']);
        }
        if (isset($dados['valor_plano_km_controlado'])) {
            $dadosUpdate['valor_plano_km_controlado'] = \currency_parse($dados['valor_plano_km_controlado']);
        }
        if (isset($dados['km_franquia'])) {
            $dadosUpdate['km_franquia'] = $this->parseIntegerInput($dados['km_franquia']);
        }
        if (isset($dados['valor_km_excedente'])) {
            $dadosUpdate['valor_km_excedente'] = \currency_parse($dados['valor_km_excedente']);
        }
        if (isset($dados['minutos_tolerancia'])) {
            $dadosUpdate['minutos_tolerancia'] = (int) $dados['minutos_tolerancia'];
        }
        if (isset($dados['valor_tolerancia'])) {
            $dadosUpdate['valor_tolerancia'] = \currency_parse($dados['valor_tolerancia']);
        }
        if (isset($dados['valor_km_retorno'])) {
            $dadosUpdate['valor_km_retorno'] = \currency_parse($dados['valor_km_retorno']);
        }
        if (isset($dados['valor_condutor_adicional'])) {
            $dadosUpdate['valor_condutor_adicional'] = \currency_parse($dados['valor_condutor_adicional']);
        }

        // Seguros
        if (isset($dados['seguro_carro'])) {
            $dadosUpdate['seguro_carro'] = (int) $dados['seguro_carro'];
        }
        if (isset($dados['valor_seguro_carro'])) {
            $dadosUpdate['valor_seguro_carro'] = \currency_parse($dados['valor_seguro_carro']);
        }
        if (isset($dados['cobertura_carro'])) {
            $dadosUpdate['cobertura_carro'] = \currency_parse($dados['cobertura_carro']);
        }
        if (isset($dados['seguro_terceiros'])) {
            $dadosUpdate['seguro_terceiros'] = (int) $dados['seguro_terceiros'];
        }
        if (isset($dados['valor_seguro_terceiros'])) {
            $dadosUpdate['valor_seguro_terceiros'] = \currency_parse($dados['valor_seguro_terceiros']);
        }
        if (isset($dados['cobertura_terceiros'])) {
            $dadosUpdate['cobertura_terceiros'] = \currency_parse($dados['cobertura_terceiros']);
        }

        // Odometro e combustivel
        if (isset($dados['odometro_saida'])) {
            $dadosUpdate['odometro_saida'] = $this->parseIntegerInput($dados['odometro_saida']);
        }
        if (array_key_exists('odometro_entrada', $dados)) {
            $dadosUpdate['odometro_entrada'] = $dados['odometro_entrada'] !== null ? $this->parseIntegerInput($dados['odometro_entrada']) : null;
        }
        if (array_key_exists('combustivel_saida', $dados)) {
            $dadosUpdate['combustivel_saida'] = $dados['combustivel_saida'];
        }
        if (array_key_exists('combustivel_entrada', $dados)) {
            $dadosUpdate['combustivel_entrada'] = $dados['combustivel_entrada'];
        }
        if (array_key_exists('motivo_saida', $dados)) {
            $dadosUpdate['motivo_saida'] = $dados['motivo_saida'];
        }
        if (array_key_exists('acao_valores', $dados)) {
            $dadosUpdate['acao_valores'] = $dados['acao_valores'];
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        $dadosUpdate['updated_at'] = now();

        return $this->qb
            ->table('contratos_veiculos')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Registra devolucao de um veiculo
     *
     * @param int $id ID do registro
     * @param int $odometroEntrada Km de entrada (veiculo volta a empresa)
     * @param int|null $combustivelEntrada Nivel de combustivel
     * @param string|null $motivo Motivo da devolucao
     * @param string|null $dataEntrada Data/hora da devolucao (Y-m-d H:i:s)
     * @return int Linhas afetadas
     */
    public function devolver(int $id, int $odometroEntrada, ?int $combustivelEntrada = null, ?string $motivo = null, ?string $dataEntrada = null): int
    {
        $agora = now();

        return $this->qb
            ->table('contratos_veiculos')
            ->where('id', '=', $id)
            ->update([
                'data_entrada' => $dataEntrada ?: $agora,
                'odometro_entrada' => $odometroEntrada,
                'combustivel_entrada' => $combustivelEntrada,
                'motivo_saida' => $motivo,
                'updated_at' => $agora
            ]);
    }

    /**
     * Substitui um veiculo no contrato
     *
     * @param int $idVeiculoAntigo ID do registro do veiculo a substituir
     * @param array $dadosSaida Dados de devolucao (odometro, combustivel, motivo)
     * @param array $dadosNovo Dados do novo veiculo
     * @param bool $manterValores Manter valores do veiculo antigo
     * @return int ID do novo registro criado
     */
    public function substituir(int $idVeiculoAntigo, array $dadosSaida, array $dadosNovo, bool $manterValores = false): int
    {
        // Buscar veiculo antigo
        $veiculoAntigo = $this->buscarPorId($idVeiculoAntigo);
        if (!$veiculoAntigo) {
            throw new \InvalidArgumentException('Veiculo do contrato nao encontrado');
        }

        $agora = now();

        // Registrar devolucao do veiculo antigo (veiculo entra na empresa)
        $this->qb
            ->table('contratos_veiculos')
            ->where('id', '=', $idVeiculoAntigo)
            ->update([
                'data_entrada' => $dadosSaida['data_entrada'] ?? $agora,
                'odometro_entrada' => $dadosSaida['odometro_entrada'] ?? null,
                'combustivel_entrada' => $dadosSaida['combustivel_entrada'] ?? null,
                'motivo_saida' => $dadosSaida['motivo_saida'] ?? 'Substituicao de veiculo',
                'updated_at' => $agora
            ]);

        // Preparar dados do novo veiculo (veiculo sai da empresa)
        $dadosInsert = $dadosNovo;
        $dadosInsert['chave'] = $veiculoAntigo['chave'];
        $dadosInsert['id_contrato'] = $veiculoAntigo['id_contrato'];
        $dadosInsert['data_saida'] = $dadosNovo['data_saida'] ?? $agora;
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

        // Criar novo registro
        return $this->adicionar($dadosInsert);
    }

    /**
     * Mantem apenas o valor do plano selecionado no campo correspondente.
     */
    private function normalizarValoresPlano(array $dados): array
    {
        $plano = strtoupper((string) ($dados['plano'] ?? ''));
        if ($plano === '') {
            return $dados;
        }

        if ($plano === 'KC') {
            $plano = 'KP';
            $dados['plano'] = 'KP';
        }

        $valorKmPago = \currency_parse($dados['valor_plano_km_pago'] ?? 0);
        $valorKmLivre = \currency_parse($dados['valor_plano_km_livre'] ?? 0);
        $valorKmControlado = \currency_parse($dados['valor_plano_km_controlado'] ?? 0);

        $dados['valor_plano_km_pago'] = $plano === 'KP' ? $valorKmPago : 0;
        $dados['valor_plano_km_livre'] = $plano === 'KL' ? $valorKmLivre : 0;
        $dados['valor_plano_km_controlado'] = $plano === 'KMC' ? $valorKmControlado : 0;

        return $dados;
    }

    /**
     * Converte inteiros formatados por mascara (ex: "1.500") para 1500.
     */
    private function parseIntegerInput($value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        return (int) preg_replace('/\D+/', '', (string) $value);
    }

    /**
     * Remove um veiculo do contrato
     *
     * @param int $id ID do registro
     * @return int Linhas afetadas
     */
    public function remover(int $id): int
    {
        return $this->qb
            ->table('contratos_veiculos')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Conta veiculos ativos em um contrato
     *
     * @param int $contratoId ID do contrato
     * @return int Quantidade de veiculos ativos
     */
    public function contarAtivos(int $contratoId): int
    {
        return $this->qb
            ->table('contratos_veiculos')
            ->where('id_contrato', '=', $contratoId)
            ->whereNull('data_entrada')
            ->count();
    }

    /**
     * Verifica se um veiculo esta alugado em algum contrato ativo
     *
     * @param int $veiculoId ID do veiculo
     * @param int|null $excluirContratoId Contrato a ignorar na busca
     * @return array|null Dados do contrato ou null se disponivel
     */
    public function veiculoEstaAlugado(int $veiculoId, ?int $excluirContratoId = null): ?array
    {
        $query = $this->qb
            ->table('contratos_veiculos', 'cv')
            ->select([
                'cv.id_contrato',
                'c.codigo AS contrato_codigo',
                'c.data_ini',
                'c.data_fim'
            ])
            ->leftJoin('contratos', 'c', 'cv.id_contrato', '=', 'c.id')
            ->where('cv.id_veiculo', '=', $veiculoId)
            ->whereNull('cv.data_entrada')
            ->where('c.status', '=', 'A');

        if ($excluirContratoId !== null) {
            $query->where('cv.id_contrato', '!=', $excluirContratoId);
        }

        return $query->first();
    }

    /**
     * Busca responsavel por multa em um periodo
     * Encontra o contrato que estava com o veiculo na data/hora da infracao
     *
     * @param int $veiculoId ID do veiculo
     * @param string $dataHoraMulta Data/hora da infracao
     * @return array|null Dados do contrato/cliente responsavel ou null
     */
    public function findResponsavelByMulta(int $veiculoId, string $dataHoraMulta): ?array
    {
        return $this->qb
            ->table('contratos_veiculos', 'cv')
            ->select([
                'c.id AS contrato_id',
                'c.codigo AS contrato_codigo',
                'c.id_cliente',
                'cl.nome_rsocial AS cliente_nome',
                'cl.cpf_cnpj AS cliente_cpf_cnpj'
            ])
            ->leftJoin('contratos', 'c', 'cv.id_contrato', '=', 'c.id')
            ->leftJoin('clientes', 'cl', 'c.id_cliente', '=', 'cl.id')
            ->where('cv.id_veiculo', '=', $veiculoId)
            ->where('cv.data_saida', '<=', $dataHoraMulta)
            ->whereNested(function ($q) use ($dataHoraMulta) {
                $q->whereNull('cv.data_entrada')
                  ->orWhere('cv.data_entrada', '>=', $dataHoraMulta);
            })
            ->orderByDesc('cv.data_saida')
            ->orderByDesc('cv.id')
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

}
