<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\DetectsCrossTenant;
use App\Helpers\FilialHelper;
use App\Helpers\SequenciaHelper;

/**
 * Model Locacao
 *
 * Gerencia locacoes/reservas de veiculos (curto prazo).
 * Cada locacao possui um unico veiculo.
 *
 * Status:
 * - R = Reserva
 * - A = Aberto (veiculo em uso)
 * - F = Fechado (devolvido)
 *
 * Planos:
 * - KL = Km Livre
 * - KMC = Km Controlado
 * - DI/DIA = Diaria (Km Cobrado)
 */
class Locacao extends Model
{
    public const PLANO_CONTA_DEVOLUCAO_LOCACAO = '3.4.1.22';
    public const PLANO_CONTA_AVARIAS = '4.2.2.01';

    use Auditable;
    use DetectsCrossTenant;

    protected function getEntidadeAuditoria(): string
    {
        return 'a locacao';
    }

    protected function getCampoIdentificador(): string
    {
        return 'codigo';
    }

    /**
     * Lista locacoes do tenant com paginacao e busca
     */
    public function listarPaginado(
        string $chave,
        int $page,
        int $perPage,
        string $search = '',
        string $filialWhere = '',
        array $filialParams = [],
        string $status = ''
    ): array {
        $query = $this->qb
            ->table('locacoes', 'l')
            ->select([
                'l.*',
                'cl.nome_rsocial AS cliente_nome_completo',
                'cl.cpf_cnpj AS cliente_cpf_cnpj',
                'cl.cep AS cliente_cep',
                'cl.rua AS cliente_rua',
                'cl.numero AS cliente_numero',
                'cl.complemento AS cliente_complemento',
                'cl.bairro AS cliente_bairro',
                'cl.cidade AS cliente_cidade',
                'cl.estado AS cliente_estado',
                'cl.pais AS cliente_pais',
                'mf_ret.nome_fantasia AS filial_retirada_nome',
                'mf_dev.nome_fantasia AS filial_devolucao_nome',
                'fp.nome AS forma_pagamento_descricao',
            ])
            ->selectSubquery(function ($q) {
                $q->table('locacoes_veiculos', 'lv')
                  ->selectRaw("(SELECT CONCAT(v.placa, ' - ', v.modelo) FROM veiculos v WHERE v.id = lv.id_veiculo)")
                  ->whereRaw('lv.id_locacao = l.id AND lv.chave = l.chave AND lv.id_veiculo IS NOT NULL')
                  ->orderByRaw('CASE WHEN lv.data_entrada IS NULL THEN 0 ELSE 1 END')
                  ->orderByDesc('lv.data_saida')
                  ->orderByDesc('lv.id')
                  ->limit(1);
            }, 'veiculo_info')
            ->selectSubquery(function ($q) {
                $q->table('assinaturas', 'asn')
                  ->selectRaw('asn.id')
                  ->whereRaw('asn.id_locacao = l.id')
                  ->limit(1);
            }, 'id_assinatura')
            ->leftJoin('clientes', 'cl', 'l.id_cliente', '=', 'cl.id')
            ->leftJoin('matrizes_filiais', 'mf_ret', 'l.id_matriz_filial_retirada', '=', 'mf_ret.id')
            ->leftJoin('matrizes_filiais', 'mf_dev', 'l.id_matriz_filial_devolucao', '=', 'mf_dev.id')
            ->leftJoin('formas_pagamento', 'fp', 'l.id_forma_pagamento', '=', 'fp.id');

        // Filtro de busca
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $dataBusca = $this->normalizarDataBusca($search);
            $query->whereNested(function ($q) use ($searchTerm, $dataBusca) {
                $q->where('l.codigo', 'LIKE', $searchTerm)
                  ->orWhere('cl.nome_rsocial', 'LIKE', $searchTerm)
                  ->orWhere('cl.cpf_cnpj', 'LIKE', $searchTerm)
                  ->orWhere('l.cliente_nome', 'LIKE', $searchTerm)
                  ->orWhereRaw(
                      "EXISTS (
                          SELECT 1
                          FROM locacoes_veiculos lv_busca
                          INNER JOIN veiculos v_busca
                              ON v_busca.id = lv_busca.id_veiculo
                              AND v_busca.chave = lv_busca.chave
                          WHERE lv_busca.id_locacao = l.id
                            AND lv_busca.chave = l.chave
                            AND (
                                v_busca.placa LIKE ?
                                OR v_busca.modelo LIKE ?
                                OR v_busca.marca LIKE ?
                            )
                      )",
                      [$searchTerm, $searchTerm, $searchTerm]
                  );

                if ($dataBusca !== null) {
                    $inicioDia = $dataBusca . ' 00:00:00';
                    $fimDia = $dataBusca . ' 23:59:59';
                    $q->orWhereRaw(
                        '(l.data_saida BETWEEN ? AND ? OR l.data_prevista BETWEEN ? AND ? OR l.data_chegada BETWEEN ? AND ?)',
                        [$inicioDia, $fimDia, $inicioDia, $fimDia, $inicioDia, $fimDia]
                    );
                }
            });
        }

        // Filtro de filial
        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        // Filtro de status
        if (!empty($status)) {
            if (strpos($status, ',') !== false) {
                $query->whereIn('l.status', explode(',', $status));
            } else {
                $query->where('l.status', '=', $status);
            }
        }

        return $query
            ->orderByRaw("FIELD(l.status, 'R', 'A', 'F')")
            ->orderByDesc('l.id')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta total de locacoes com filtros
     */
    public function contar(
        string $chave,
        string $search = '',
        string $filialWhere = '',
        array $filialParams = [],
        string $status = ''
    ): int {
        $query = $this->qb
            ->table('locacoes', 'l')
            ->leftJoin('clientes', 'cl', 'l.id_cliente', '=', 'cl.id');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $dataBusca = $this->normalizarDataBusca($search);
            $query->whereNested(function ($q) use ($searchTerm, $dataBusca) {
                $q->where('l.codigo', 'LIKE', $searchTerm)
                  ->orWhere('cl.nome_rsocial', 'LIKE', $searchTerm)
                  ->orWhere('cl.cpf_cnpj', 'LIKE', $searchTerm)
                  ->orWhere('l.cliente_nome', 'LIKE', $searchTerm)
                  ->orWhereRaw(
                      "EXISTS (
                          SELECT 1
                          FROM locacoes_veiculos lv_busca
                          INNER JOIN veiculos v_busca
                              ON v_busca.id = lv_busca.id_veiculo
                              AND v_busca.chave = lv_busca.chave
                          WHERE lv_busca.id_locacao = l.id
                            AND lv_busca.chave = l.chave
                            AND (
                                v_busca.placa LIKE ?
                                OR v_busca.modelo LIKE ?
                                OR v_busca.marca LIKE ?
                            )
                      )",
                      [$searchTerm, $searchTerm, $searchTerm]
                  );

                if ($dataBusca !== null) {
                    $inicioDia = $dataBusca . ' 00:00:00';
                    $fimDia = $dataBusca . ' 23:59:59';
                    $q->orWhereRaw(
                        '(l.data_saida BETWEEN ? AND ? OR l.data_prevista BETWEEN ? AND ? OR l.data_chegada BETWEEN ? AND ?)',
                        [$inicioDia, $fimDia, $inicioDia, $fimDia, $inicioDia, $fimDia]
                    );
                }
            });
        }

        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        if (!empty($status)) {
            if (strpos($status, ',') !== false) {
                $query->whereIn('l.status', explode(',', $status));
            } else {
                $query->where('l.status', '=', $status);
            }
        }

        return $query->count();
    }

    /**
     * Lista locacoes (reservas R e abertas A) cujo intervalo [data_saida, COALESCE(data_chegada, data_prevista)]
     * intersecta com [$inicio, $fim]. Usado na tela de Agenda.
     *
     * Para cada locacao, inclui id_veiculo/id_grupo do veiculo ativo em locacoes_veiculos.
     * Reservas sem veiculo atribuido retornam id_veiculo=null mas mantem id_grupo.
     */
    public function listarEventosAgenda(
        string $chave,
        string $inicio,
        string $fim,
        string $filialWhere = '',
        array $filialParams = []
    ): array {
        $query = $this->qb
            ->table('locacoes', 'l')
            ->select([
                'l.id',
                'l.codigo',
                'l.status',
                'l.data_saida',
                'l.data_prevista',
                'l.data_chegada',
                'l.obs',
                'lv.id_veiculo',
                'lv.id_grupo',
            ])
            ->selectRaw('COALESCE(cl.nome_rsocial, l.cliente_nome) AS cliente_nome')
            ->leftJoinRaw('locacoes_veiculos', 'lv', 'lv.id_locacao = l.id AND lv.data_entrada IS NULL')
            ->leftJoin('clientes', 'cl', 'l.id_cliente', '=', 'cl.id')
            ->whereIn('l.status', ['R', 'A'])
            ->where('l.data_saida', '<=', $fim)
            // Em aberto (data_chegada=NULL) e ongoing: usa max(data_prevista, NOW())
            // — garante que locacoes/reservas com previsao vencida ainda apareçam.
            ->whereRaw(
                'COALESCE(l.data_chegada, GREATEST(COALESCE(l.data_prevista, l.data_saida), NOW())) >= ?',
                [$inicio]
            );

        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        return $query
            ->orderBy('l.data_saida', 'ASC')
            ->get();
    }

    /**
     * Busca uma locacao por ID com dados relacionados
     *
     * Dados de veiculo vem de locacoes_veiculos (veiculo ativo).
     * Dados de taxas vem de locacoes_taxaseservicos via Model separado.
     */
    public function buscarPorId(int $id): ?array
    {
        $locacao = $this->qb
            ->table('locacoes', 'l')
            ->select([
                'l.*',
                'cl.nome_rsocial AS cliente_nome_completo',
                'cl.cpf_cnpj AS cliente_cpf_cnpj',
                'cl.cep AS cliente_cep',
                'cl.rua AS cliente_rua',
                'cl.numero AS cliente_numero',
                'cl.complemento AS cliente_complemento',
                'cl.bairro AS cliente_bairro',
                'cl.cidade AS cliente_cidade',
                'cl.estado AS cliente_estado',
                'cl.pais AS cliente_pais',
                'mf_ret.nome_fantasia AS filial_retirada_nome',
                'mf_dev.nome_fantasia AS filial_devolucao_nome',
                'ct.nome AS conta_descricao',
                'fp.nome AS forma_pagamento_descricao',
                'func.nome AS funcionario_nome',
                'ct_bloq.nome AS conta_bloqueio_descricao',
                'ct_cauc.nome AS conta_caucao_descricao',
                'lb.status AS bloqueio_status',
                'lb.valor AS bloqueio_hold_valor',
                'lb.valor_capturado AS bloqueio_valor_capturado',
                'lb.external_id AS bloqueio_external_id',
                'lb.expira_em AS bloqueio_expira_em',
                'lb.autorizado_em AS bloqueio_autorizado_em',
                'lb.gateway_code AS bloqueio_gateway_code',
                'cc_bloq.bandeira AS bloqueio_cartao_bandeira',
                'cc_bloq.ultimos_digitos AS bloqueio_cartao_ultimos_digitos',
            ])
            ->leftJoin('clientes', 'cl', 'l.id_cliente', '=', 'cl.id')
            ->leftJoin('matrizes_filiais', 'mf_ret', 'l.id_matriz_filial_retirada', '=', 'mf_ret.id')
            ->leftJoin('matrizes_filiais', 'mf_dev', 'l.id_matriz_filial_devolucao', '=', 'mf_dev.id')
            ->leftJoin('contas_bancarias', 'ct', 'l.id_conta', '=', 'ct.id')
            ->leftJoin('contas_bancarias', 'ct_bloq', 'l.id_conta_bloqueio', '=', 'ct_bloq.id')
            ->leftJoin('contas_bancarias', 'ct_cauc', 'l.id_conta_caucao', '=', 'ct_cauc.id')
            ->leftJoin('locacoes_bloqueios', 'lb', 'l.id_bloqueio_ativo', '=', 'lb.id')
            ->leftJoin('clientes_cartoes', 'cc_bloq', 'lb.id_cartao', '=', 'cc_bloq.id')
            ->leftJoin('formas_pagamento', 'fp', 'l.id_forma_pagamento', '=', 'fp.id')
            ->leftJoin('funcionarios', 'func', 'l.id_funcionario', '=', 'func.id')
            ->where('l.id', '=', $id)
            ->first();

        if (!$locacao) {
            return null;
        }

        // Compatibilidade com templates/contexts antigos de impressao.
        // A coluna real da tabela locacoes eh `dias`.
        $locacao['quantidade_dias'] = (int) ($locacao['dias'] ?? 0);

        // Carregar veiculo atual/ultimo de locacoes_veiculos.
        // Locacoes fechadas nao possuem veiculo ativo, mas ainda devem exibir
        // o ultimo veiculo vinculado em telas de detalhe e impressao.
        $veiculoModel = new LocacaoVeiculo();
        $veiculo = $veiculoModel->buscarAtualOuUltimo($id);

        if ($veiculo) {
            $locacao['id_veiculo'] = $veiculo['id_veiculo'];
            $locacao['id_grupo'] = $veiculo['id_grupo'];
            $locacao['veiculo_info'] = !empty($veiculo['id_veiculo'])
                ? trim(($veiculo['veiculo_placa'] ?? '') . ' - ' . ($veiculo['veiculo_marca'] ?? '') . ' ' . ($veiculo['veiculo_modelo'] ?? ''))
                : null;
            $locacao['veiculo_placa'] = $veiculo['veiculo_placa'];
            $locacao['grupo_nome'] = $veiculo['grupo_nome'];
            $locacao['plano'] = $veiculo['plano'] === 'KP' ? 'DI' : $veiculo['plano'];
            // diaria_valor reflete o valor do plano ativo:
            // - KL (Km Livre)        => valor_plano_km_livre
            // - DI / KP (diária paga)=> valor_plano_km_pago
            // - KMC (Km Controlado)  => valor_plano_km_controlado
            switch ($veiculo['plano']) {
                case 'KL':
                    $locacao['diaria_valor'] = $veiculo['valor_plano_km_livre'];
                    break;
                case 'KMC':
                    $locacao['diaria_valor'] = $veiculo['valor_plano_km_controlado'];
                    break;
                default: // DI, KP
                    $locacao['diaria_valor'] = $veiculo['valor_plano_km_pago'];
                    break;
            }
            $locacao['km_livre_valor'] = $veiculo['valor_plano_km_livre'];
            $locacao['km_valor'] = $veiculo['valor_km_excedente'];
            $locacao['km_controlado_valor'] = $veiculo['valor_plano_km_controlado'];
            $locacao['km_controlado_franquia'] = $veiculo['km_franquia'];
            $locacao['minuto_tolerancia'] = $veiculo['minutos_tolerancia'];
            $locacao['valor_tolerancia'] = $veiculo['valor_tolerancia'];
            $locacao['valor_km_retorno'] = $veiculo['valor_km_retorno'];
            $locacao['valor_condutor_adicional'] = $veiculo['valor_condutor_adicional'];
            $locacao['seguro_carro'] = $veiculo['seguro_carro'] ? 'S' : 'N';
            $locacao['seguro_carro_valor'] = $veiculo['valor_seguro_carro'];
            $locacao['cobertura_carro_valor'] = $veiculo['cobertura_carro'];
            $locacao['seguro_terceiros'] = $veiculo['seguro_terceiros'] ? 'S' : 'N';
            $locacao['seguro_terceiros_valor'] = $veiculo['valor_seguro_terceiros'];
            $locacao['cobertura_terceiros_valor'] = $veiculo['cobertura_terceiros'];
            $locacao['odometro_ini'] = $veiculo['odometro_saida'];
            $locacao['odometro_fim'] = $veiculo['odometro_entrada'];
            $locacao['odometro_usado'] = $veiculo['odometro_usado'];
            $locacao['combustivel_ini'] = $veiculo['combustivel_saida'];
            $locacao['combustivel_fim'] = $veiculo['combustivel_entrada'];
            $locacao['combustivel_usado'] = $veiculo['combustivel_usado'];
            $locacao['combustivel_valor'] = $veiculo['combustivel_valor'];
            $locacao['veiculo_valor_por_fracao'] = $veiculo['veiculo_valor_por_fracao'];
            $locacao['kmlExcedente'] = $veiculo['km_excedente'];
            $locacao['veiculo_tipo_combustivel'] = $veiculo['veiculo_tipo_combustivel'];
            // ID do registro em locacoes_veiculos (para operacoes de devolucao/substituicao)
            $locacao['_id_locacao_veiculo'] = $veiculo['id'];
        } else {
            $locacao['veiculo_info'] = null;
            $locacao['veiculo_placa'] = null;
            $locacao['grupo_nome'] = null;
        }

        // Contatos do cliente
        if (!empty($locacao['id_cliente'])) {
            $emailModel = new ContatoEmail();
            $telefoneModel = new ContatoTelefone();

            $emailPrincipal = $emailModel->getPrincipal('cliente', (int) $locacao['id_cliente']);
            $telefonePrincipal = $telefoneModel->getPrincipal('cliente', (int) $locacao['id_cliente']);

            $locacao['cliente_email'] = $emailPrincipal['email'] ?? '';
            $locacao['cliente_telefone'] = $telefonePrincipal['telefone'] ?? '';
        }

        return $locacao;
    }

    /**
     * Busca uma locacao por codigo
     */
    public function buscarPorCodigo(string $codigo): ?array
    {
        $id = $this->qb
            ->table('locacoes')
            ->select(['id'])
            ->where('codigo', '=', $codigo)
            ->first();

        if (!$id) {
            return null;
        }

        return $this->buscarPorId($id['id']);
    }

    /**
     * Busca locacao por codigo em contexto publico, sem depender da sessao atual.
     *
     * Usado em links publicos como /assinar/{codigo}, onde o visitante pode nao
     * ter sessao ou pode estar logado em outro tenant no mesmo navegador.
     */
    public function buscarPublicoPorCodigo(string $codigo): ?array
    {
        $row = $this->qb
            ->table('locacoes')
            ->withoutChave()
            ->select(['id', 'chave'])
            ->where('codigo', '=', $codigo)
            ->first();

        if (!$row) {
            return null;
        }

        return $this->buscarComChaveTemporaria((int) $row['id'], (string) $row['chave']);
    }

    /**
     * Executa buscarPorId usando a chave do registro publico e restaura a sessao.
     */
    private function buscarComChaveTemporaria(int $id, string $chave): ?array
    {
        $hadChave = isset($_SESSION['chave']);
        $previousChave = $_SESSION['chave'] ?? null;
        $_SESSION['chave'] = $chave;

        try {
            return $this->buscarPorId($id);
        } finally {
            if ($hadChave) {
                $_SESSION['chave'] = $previousChave;
            } else {
                unset($_SESSION['chave']);
            }
        }
    }

    /**
     * Cria uma nova locacao
     *
     * Campos de veiculo e taxas sao gerenciados por LocacaoVeiculo e LocacaoTaxaServico.
     * O Controller orquestra a criacao: locacao -> veiculo -> taxas.
     */
    public function criar(array $dados): int
    {
        $codigo = $dados['codigo'] ?? $this->gerarCodigo($dados['chave']);
        $sequencia = $this->gerarSequencia($dados['chave']);

        return $this->qb
            ->table('locacoes')
            ->insert([
                'chave' => $dados['chave'],
                'sequencia' => $sequencia,
                'codigo' => $codigo,
                'status' => $dados['status'] ?? 'R',
                'id_matriz_filial_retirada' => !empty($dados['id_matriz_filial_retirada']) ? (int) $dados['id_matriz_filial_retirada'] : null,
                'id_matriz_filial_devolucao' => !empty($dados['id_matriz_filial_devolucao']) ? (int) $dados['id_matriz_filial_devolucao'] : null,
                'data_saida' => !empty($dados['data_saida']) ? $dados['data_saida'] : null,
                'data_prevista' => !empty($dados['data_prevista']) ? $dados['data_prevista'] : null,
                'data_chegada' => !empty($dados['data_chegada']) ? $dados['data_chegada'] : null,
                'dias' => (int) ($dados['dias'] ?? 0),
                'cliente_nome' => $dados['cliente_nome'] ?? '',
                'id_cliente' => !empty($dados['id_cliente']) ? (int) $dados['id_cliente'] : null,
                'promocao_codigo' => $dados['promocao_codigo'] ?? null,
                'valor_desconto' => $this->toDecimal($dados['valor_desconto'] ?? 0),
                'condutor_adicional' => $dados['condutor_adicional'] ?? null,
                'array_fiadores' => $dados['array_fiadores'] ?? null,
                'array_avalistas' => $dados['array_avalistas'] ?? null,
                'array_testemunhas' => $dados['array_testemunhas'] ?? null,
                'id_conta_bloqueio' => !empty($dados['id_conta_bloqueio']) ? (int) $dados['id_conta_bloqueio'] : null,
                'bloqueio_tipo' => $dados['bloqueio_tipo'] ?? null,
                'bloqueio_valor' => $this->toDecimal($dados['bloqueio_valor'] ?? 0),
                'bloqueio_prazo_devolucao' => isset($dados['bloqueio_prazo_devolucao']) && $dados['bloqueio_prazo_devolucao'] !== '' ? (int) $dados['bloqueio_prazo_devolucao'] : null,
                'bloqueio_data_devolucao' => !empty($dados['bloqueio_data_devolucao']) ? $dados['bloqueio_data_devolucao'] : null,
                'caucao_valor' => $this->toDecimal($dados['caucao_valor'] ?? 0),
                'caucao_tipo' => $dados['caucao_tipo'] ?? null,
                'id_conta_caucao' => !empty($dados['id_conta_caucao']) ? (int) $dados['id_conta_caucao'] : null,
                'caucao_prazo_devolucao' => isset($dados['caucao_prazo_devolucao']) && $dados['caucao_prazo_devolucao'] !== '' ? (int) $dados['caucao_prazo_devolucao'] : null,
                'caucao_data_devolucao' => !empty($dados['caucao_data_devolucao']) ? $dados['caucao_data_devolucao'] : null,
                'id_cartao_caucao' => !empty($dados['id_cartao_caucao']) ? (int) $dados['id_cartao_caucao'] : null,
                'id_bloqueio_ativo' => !empty($dados['id_bloqueio_ativo']) ? (int) $dados['id_bloqueio_ativo'] : null,
                'id_conta' => !empty($dados['id_conta']) ? (int) $dados['id_conta'] : null,
                'id_forma_pagamento' => !empty($dados['id_forma_pagamento']) ? (int) $dados['id_forma_pagamento'] : null,
                'obs' => $dados['obs'] ?? null,
                'total_fatura' => $this->toDecimal($dados['total_fatura'] ?? 0),
                'total_pagar' => $this->toDecimal($dados['total_pagar'] ?? 0),
                'id_funcionario' => !empty($dados['id_funcionario']) ? (int) $dados['id_funcionario'] : null,
            ]);
    }

    /**
     * Atualiza uma locacao existente
     *
     * Campos de veiculo sao gerenciados por LocacaoVeiculo (Controller orquestra).
     * Campos de taxas sao gerenciados por LocacaoTaxaServico (Controller orquestra).
     */
    public function atualizar(int $id, array $dados): int
    {
        $dadosUpdate = [];

        // Filiais
        if (isset($dados['id_matriz_filial_retirada'])) {
            $dadosUpdate['id_matriz_filial_retirada'] = !empty($dados['id_matriz_filial_retirada']) ? (int) $dados['id_matriz_filial_retirada'] : null;
        }
        if (isset($dados['id_matriz_filial_devolucao'])) {
            $dadosUpdate['id_matriz_filial_devolucao'] = !empty($dados['id_matriz_filial_devolucao']) ? (int) $dados['id_matriz_filial_devolucao'] : null;
        }

        // Cliente
        if (isset($dados['id_cliente'])) {
            $dadosUpdate['id_cliente'] = !empty($dados['id_cliente']) ? (int) $dados['id_cliente'] : null;
        }
        if (isset($dados['cliente_nome'])) {
            $dadosUpdate['cliente_nome'] = $dados['cliente_nome'];
        }

        // Datas e periodo
        if (isset($dados['data_saida'])) {
            $dadosUpdate['data_saida'] = $dados['data_saida'];
        }
        if (isset($dados['data_prevista'])) {
            $dadosUpdate['data_prevista'] = $dados['data_prevista'];
        }
        if (isset($dados['data_chegada'])) {
            $dadosUpdate['data_chegada'] = !empty($dados['data_chegada']) ? $dados['data_chegada'] : null;
        }
        if (isset($dados['dias'])) {
            $dadosUpdate['dias'] = (int) $dados['dias'];
        }

        // Bloqueio/caucao (campos legados)
        if (isset($dados['id_conta_bloqueio'])) {
            $dadosUpdate['id_conta_bloqueio'] = !empty($dados['id_conta_bloqueio']) ? (int) $dados['id_conta_bloqueio'] : null;
        }
        if (isset($dados['bloqueio_tipo'])) {
            $dadosUpdate['bloqueio_tipo'] = $dados['bloqueio_tipo'];
        }
        if (isset($dados['bloqueio_valor'])) {
            $dadosUpdate['bloqueio_valor'] = $this->toDecimal($dados['bloqueio_valor']);
        }
        if (isset($dados['bloqueio_prazo_devolucao'])) {
            $dadosUpdate['bloqueio_prazo_devolucao'] = $dados['bloqueio_prazo_devolucao'] !== '' ? (int) $dados['bloqueio_prazo_devolucao'] : null;
        }

        // Caucao (deposito de garantia)
        if (isset($dados['caucao_valor'])) {
            $dadosUpdate['caucao_valor'] = $this->toDecimal($dados['caucao_valor']);
        }
        if (isset($dados['caucao_tipo'])) {
            $dadosUpdate['caucao_tipo'] = $dados['caucao_tipo'];
        }
        if (isset($dados['id_conta_caucao'])) {
            $dadosUpdate['id_conta_caucao'] = !empty($dados['id_conta_caucao']) ? (int) $dados['id_conta_caucao'] : null;
        }
        if (isset($dados['caucao_prazo_devolucao'])) {
            $dadosUpdate['caucao_prazo_devolucao'] = $dados['caucao_prazo_devolucao'] !== '' ? (int) $dados['caucao_prazo_devolucao'] : null;
        }
        if (isset($dados['caucao_data_devolucao'])) {
            $dadosUpdate['caucao_data_devolucao'] = $dados['caucao_data_devolucao'];
        }
        if (isset($dados['id_cartao_caucao'])) {
            $dadosUpdate['id_cartao_caucao'] = !empty($dados['id_cartao_caucao']) ? (int) $dados['id_cartao_caucao'] : null;
        }
        if (isset($dados['id_bloqueio_ativo'])) {
            $dadosUpdate['id_bloqueio_ativo'] = !empty($dados['id_bloqueio_ativo']) ? (int) $dados['id_bloqueio_ativo'] : null;
        }

        // Financeiro
        if (isset($dados['id_conta'])) {
            $dadosUpdate['id_conta'] = !empty($dados['id_conta']) ? (int) $dados['id_conta'] : null;
        }
        if (isset($dados['id_forma_pagamento'])) {
            $dadosUpdate['id_forma_pagamento'] = !empty($dados['id_forma_pagamento']) ? (int) $dados['id_forma_pagamento'] : null;
        }
        if (isset($dados['valor_desconto'])) {
            $dadosUpdate['valor_desconto'] = $this->toDecimal($dados['valor_desconto']);
        }
        if (isset($dados['promocao_codigo'])) {
            $dadosUpdate['promocao_codigo'] = $dados['promocao_codigo'];
        }
        if (isset($dados['total_fatura'])) {
            $dadosUpdate['total_fatura'] = $this->toDecimal($dados['total_fatura']);
        }
        if (isset($dados['total_pagar'])) {
            $dadosUpdate['total_pagar'] = $this->toDecimal($dados['total_pagar']);
        }

        // Arrays JSON (intervenientes)
        if (array_key_exists('condutor_adicional', $dados)) {
            $dadosUpdate['condutor_adicional'] = $dados['condutor_adicional'];
        }
        if (array_key_exists('array_fiadores', $dados)) {
            $dadosUpdate['array_fiadores'] = $dados['array_fiadores'];
        }
        if (array_key_exists('array_avalistas', $dados)) {
            $dadosUpdate['array_avalistas'] = $dados['array_avalistas'];
        }
        if (array_key_exists('array_testemunhas', $dados)) {
            $dadosUpdate['array_testemunhas'] = $dados['array_testemunhas'];
        }

        // Status e observacoes
        if (isset($dados['status'])) {
            $dadosUpdate['status'] = $dados['status'];
        }
        if (array_key_exists('obs', $dados)) {
            $dadosUpdate['obs'] = $dados['obs'];
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        $dadosUpdate['updated_at'] = date('Y-m-d H:i:s');

        return $this->qb
            ->table('locacoes')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Exclui uma locacao
     *
     * Libera veiculo ativo se locacao nao esta fechada.
     * CASCADE nas FKs remove automaticamente locacoes_veiculos e locacoes_taxaseservicos.
     */
    public function deletar(int $id): int
    {
        $locacao = $this->buscarPorId($id);
        if (!$locacao) {
            return 0;
        }

        $idVeiculoLiberar = (!empty($locacao['id_veiculo']) && $locacao['status'] !== 'F')
            ? (int) $locacao['id_veiculo']
            : 0;

        // Excluir checklists vinculados e seus arquivos
        $checklistModel = new \App\Models\Checklist();
        $checklistModel->excluirPorLocacao($id, $locacao['chave']);

        // Desvincular lancamentos financeiros
        $this->qb
            ->table('financeiro')
            ->where('id_locacao', '=', $id)
            ->update(['id_locacao' => null]);

        // Deletar locacao (CASCADE remove locacoes_veiculos e locacoes_taxaseservicos)
        $apagados = $this->qb
            ->table('locacoes')
            ->where('id', '=', $id)
            ->delete();

        if ($apagados > 0 && $idVeiculoLiberar > 0) {
            (new VeiculoDisponibilidadeSync())->liberarSeSemVinculoAtivo($idVeiculoLiberar, 'D', $locacao['chave']);
        }

        return $apagados;
    }

    /**
     * Registra saida do veiculo (R -> A)
     *
     * Atualiza odometro/combustivel de entrada no veiculo ativo (locacoes_veiculos).
     * Marca veiculo como locado.
     */
    public function registrarSaida(int $id, array $dados): int
    {
        $locacao = $this->buscarPorId($id);
        if (!$locacao) {
            throw new \InvalidArgumentException('Locacao nao encontrada');
        }

        if ($locacao['status'] !== 'R') {
            throw new \InvalidArgumentException('Somente reservas podem ter saida registrada');
        }

        // Atualizar dados do veiculo ativo em locacoes_veiculos
        $veiculoModel = new LocacaoVeiculo();
        $idLocacaoVeiculo = $locacao['_id_locacao_veiculo'] ?? null;

        if ($idLocacaoVeiculo) {
            $veiculoModel->atualizar($idLocacaoVeiculo, [
                'data_saida' => $dados['data_saida'] ?? date('Y-m-d H:i:s'),
                'odometro_saida' => (int) ($dados['odometro_ini'] ?? 0),
                'combustivel_saida' => $dados['combustivel_ini'] ?? null,
            ]);
        }

        // Marcar veiculo como locado
        $idVeiculo = $locacao['id_veiculo'] ?? null;
        if ($idVeiculo) {
            (new VeiculoDisponibilidadeSync())->marcarLocado((int) $idVeiculo, $locacao['chave']);
        }

        // Atualizar locacao
        $dadosUpdate = [
            'status' => 'A',
            'data_saida' => $dados['data_saida'] ?? date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (isset($dados['obs'])) {
            $dadosUpdate['obs'] = $dados['obs'];
        }

        // Bloqueio/caucao
        if (!empty($dados['id_conta_bloqueio'])) {
            $dadosUpdate['id_conta_bloqueio'] = (int) $dados['id_conta_bloqueio'];
        }
        if (isset($dados['bloqueio_tipo'])) {
            $dadosUpdate['bloqueio_tipo'] = $dados['bloqueio_tipo'];
        }
        if (isset($dados['bloqueio_valor'])) {
            $dadosUpdate['bloqueio_valor'] = $this->toDecimal($dados['bloqueio_valor']);
        }

        return $this->qb
            ->table('locacoes')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Registra devolucao do veiculo (A -> F)
     *
     * Registra devolucao no veiculo ativo (locacoes_veiculos) com calculo automatico
     * de odometro_usado, km_excedente e combustivel_usado.
     * Marca veiculo como disponivel.
     */
    public function registrarDevolucao(int $id, array $dados): int
    {
        $locacao = $this->buscarPorId($id);
        if (!$locacao) {
            throw new \InvalidArgumentException('Locacao nao encontrada');
        }

        if ($locacao['status'] !== 'A') {
            throw new \InvalidArgumentException('Somente locacoes abertas podem ter devolucao registrada');
        }

        // Registrar devolucao do veiculo ativo em locacoes_veiculos
        $veiculoModel = new LocacaoVeiculo();
        $idLocacaoVeiculo = $locacao['_id_locacao_veiculo'] ?? null;

        if ($idLocacaoVeiculo) {
            $odometroEntrada = (int) ($dados['odometro_fim'] ?? 0);
            $veiculoModel->devolver($idLocacaoVeiculo, [
                'data_entrada' => $dados['data_chegada'] ?? date('Y-m-d H:i:s'),
                'odometro_entrada' => $odometroEntrada,
                'combustivel_entrada' => $dados['combustivel_fim'] ?? null,
                'combustivel_valor' => $dados['combustivel_valor'] ?? null,
                'dias' => $dados['dias'] ?? $locacao['dias'] ?? 1,
            ]);

            if (!empty($locacao['id_veiculo'])) {
                (new Veiculo())->atualizarOdometro((int) $locacao['id_veiculo'], $odometroEntrada);
            }
        }

        // Marcar veiculo como disponivel
        if (!empty($locacao['id_veiculo'])) {
            (new VeiculoDisponibilidadeSync())->liberarSeSemVinculoAtivo((int) $locacao['id_veiculo'], 'D', $locacao['chave']);
        }

        // Atualizar locacao
        $dadosUpdate = [
            'status' => 'F',
            'data_chegada' => $dados['data_chegada'] ?? date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Filial de devolucao
        if (!empty($dados['id_matriz_filial_devolucao'])) {
            $dadosUpdate['id_matriz_filial_devolucao'] = (int) $dados['id_matriz_filial_devolucao'];
        }

        // Totais finais
        if (isset($dados['total_fatura'])) {
            $dadosUpdate['total_fatura'] = $this->toDecimal($dados['total_fatura']);
        }
        if (isset($dados['total_pagar'])) {
            $dadosUpdate['total_pagar'] = $this->toDecimal($dados['total_pagar']);
        }

        if (isset($dados['obs'])) {
            $dadosUpdate['obs'] = $dados['obs'];
        }

        return $this->qb
            ->table('locacoes')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    // ========================================
    // METODOS FINANCEIROS (Parcelas)
    // ========================================

    /**
     * Gera parcelas automaticas distribuindo total_pagar
     *
     * @param int $locacaoId ID da locacao
     * @param array $dados [quantidade, data_primeiro_vencimento, id_conta, id_forma_pagamento]
     * @param string $chave Tenant key
     * @return array IDs das parcelas criadas
     */
    public function gerarParcelas(int $locacaoId, array $dados, string $chave): array
    {
        $locacao = $this->buscarPorId($locacaoId);
        if (!$locacao) {
            throw new \InvalidArgumentException('Locação não encontrada');
        }

        $quantidade = (int) ($dados['quantidade'] ?? 1);
        if ($quantidade < 1) {
            throw new \InvalidArgumentException('Quantidade de parcelas deve ser maior que zero');
        }

        $totalPagarFinal = isset($dados['total_pagar_final'])
            ? (float) $dados['total_pagar_final']
            : (float) ($locacao['total_pagar'] ?? 0);
        $totalLancado = (float) ($this->qb
            ->table('financeiro')
            ->where('id_locacao', '=', $locacaoId)
            ->sum('valor_total') ?? 0);
        $totalPagar = round($totalPagarFinal - $totalLancado, 2);
        if ($totalPagar <= 0) {
            throw new \InvalidArgumentException('Nao ha saldo restante para gerar parcelas');
        }

        $valorParcela = round($totalPagar / $quantidade, 2);
        $dataVencimento = $dados['data_primeiro_vencimento'] ?? date('Y-m-d');

        // Buscar proximo numero de parcela
        $maxParcela = $this->qb
            ->table('financeiro')
            ->where('id_locacao', '=', $locacaoId)
            ->max('parcela');

        $proximaParcela = ($maxParcela ?? 0) + 1;

        $financeiroModel = new Financeiro();
        $ids = [];

        $locacaoVeiculoModel = new LocacaoVeiculo();
        $veiculoAtivo = $locacaoVeiculoModel->buscarAtivo($locacaoId);
        $idVeiculoAtivo = $veiculoAtivo ? (int) $veiculoAtivo['id_veiculo'] : null;

        $idMatrizFilial = (int) ($locacao['id_matriz_filial_retirada'] ?? 0);
        $sequencias = $idMatrizFilial > 0
            ? \App\Helpers\SequenciaHelper::proximasSequencias($chave, $idMatrizFilial, 'financeiro', $quantidade)
            : [];

        for ($i = 0; $i < $quantidade; $i++) {
            $valor = $valorParcela;

            // Ultima parcela absorve diferenca de arredondamento
            if ($i === $quantidade - 1) {
                $valorJaGerado = $valorParcela * ($quantidade - 1);
                $valor = round($totalPagar - $valorJaGerado, 2);
            }

            $numParcela = $proximaParcela + $i;

            $sequencia = $sequencias[$i] ?? null;

            $ids[] = $financeiroModel->criar([
                'chave' => $chave,
                'sequencia' => $sequencia,
                'id_locacao' => $locacaoId,
                'id_veiculo' => $idVeiculoAtivo,
                'id_cliente' => $locacao['id_cliente'],
                'id_matriz_filial' => $idMatrizFilial,
                'id_conta' => !empty($dados['id_conta']) ? (int) $dados['id_conta'] : ($locacao['id_conta'] ?? null),
                'id_forma_pagamento' => !empty($dados['id_forma_pagamento']) ? (int) $dados['id_forma_pagamento'] : ($locacao['id_forma_pagamento'] ?? null),
                'tipo' => 'R',
                'pago' => 'N',
                'parcela' => $numParcela,
                'total_parcelas' => ($proximaParcela - 1) + $quantidade,
                'descricao' => "Locação #{$locacao['codigo']} - Parcela {$numParcela}/" . (($proximaParcela - 1) + $quantidade),
                'data_venci' => $dataVencimento,
                'valor_subtotal' => $valor,
                'valor_total' => $valor,
                'data_criada' => date('Y-m-d'),
            ]);

            // Proximo vencimento: +1 mes
            $dataVencimento = date('Y-m-d', strtotime($dataVencimento . ' +1 month'));
        }

        // Atualizar total_parcelas em todas as parcelas existentes
        $novoTotal = ($proximaParcela - 1) + $quantidade;
        $this->qb
            ->table('financeiro')
            ->where('id_locacao', '=', $locacaoId)
            ->update(['total_parcelas' => $novoTotal]);

        return $ids;
    }

    /**
     * Adiciona parcela avulsa a locacao
     *
     * @param int $locacaoId ID da locacao
     * @param array $dados [valor, data_venci, id_conta, id_forma_pagamento, descricao]
     * @param string $chave Tenant key
     * @return int ID da parcela criada
     */
    public function adicionarParcela(int $locacaoId, array $dados, string $chave): int
    {
        $locacao = $this->buscarPorId($locacaoId);
        if (!$locacao) {
            throw new \InvalidArgumentException('Locação não encontrada');
        }

        // Buscar proximo numero de parcela
        $maxParcela = $this->qb
            ->table('financeiro')
            ->where('id_locacao', '=', $locacaoId)
            ->max('parcela');

        $proximaParcela = ($maxParcela ?? 0) + 1;

        // Buscar total_parcelas atual
        $totalParcelas = $this->qb
            ->table('financeiro')
            ->select(['total_parcelas'])
            ->where('id_locacao', '=', $locacaoId)
            ->orderBy('id', 'DESC')
            ->first();

        $totalAtual = (int) ($totalParcelas['total_parcelas'] ?? 0);
        $novoTotal = max($totalAtual, $proximaParcela);

        // Atualizar total_parcelas de todas as parcelas existentes
        if ($totalAtual > 0) {
            $this->qb
                ->table('financeiro')
                ->where('id_locacao', '=', $locacaoId)
                ->update(['total_parcelas' => $novoTotal]);
        }

        $financeiroModel = new Financeiro();

        $locacaoVeiculoModel = new LocacaoVeiculo();
        $veiculoAtivo = $locacaoVeiculoModel->buscarAtivo($locacaoId);
        $idVeiculoAtivo = $veiculoAtivo ? (int) $veiculoAtivo['id_veiculo'] : null;

        return $financeiroModel->criar([
            'chave' => $chave,
            'id_locacao' => $locacaoId,
            'id_veiculo' => $idVeiculoAtivo,
            'id_cliente' => $locacao['id_cliente'],
            'id_matriz_filial' => $locacao['id_matriz_filial_retirada'],
            'id_conta' => $dados['id_conta'] ?? ($locacao['id_conta'] ?? null),
            'id_forma_pagamento' => $dados['id_forma_pagamento'] ?? ($locacao['id_forma_pagamento'] ?? null),
            'id_plano_de_conta' => $dados['id_plano_de_conta'] ?? null,
            'tipo' => 'R',
            'pago' => 'N',
            'parcela' => $proximaParcela,
            'total_parcelas' => $novoTotal,
            'descricao' => $dados['descricao'] ?? "Locação #{$locacao['codigo']} - Parcela {$proximaParcela}/{$novoTotal}",
            'data_venci' => $dados['data_venci'],
            'valor_subtotal' => $this->toDecimal($dados['valor']),
            'valor_total' => $this->toDecimal($dados['valor']),
            'data_criada' => date('Y-m-d'),
        ]);
    }

    /**
     * Lista parcelas da locacao
     *
     * @param int $locacaoId ID da locacao
     * @return array Lista de parcelas
     */
    public function listarParcelas(int $locacaoId, bool $apenasReceitas = false): array
    {
        $query = $this->qb
            ->table('financeiro', 'f')
            ->select([
                'f.id',
                'f.tipo',
                'f.parcela',
                'f.total_parcelas',
                'f.descricao',
                'f.data_venci',
                'f.data_pago',
                'f.valor_subtotal',
                'f.valor_total',
                'f.pago',
                'f.id_conta',
                'f.id_forma_pagamento',
                'ct.nome AS conta_descricao',
                'fp.nome AS forma_pagamento_descricao',
            ])
            ->leftJoin('contas_bancarias', 'ct', 'f.id_conta', '=', 'ct.id')
            ->leftJoin('formas_pagamento', 'fp', 'f.id_forma_pagamento', '=', 'fp.id')
            ->where('f.id_locacao', '=', $locacaoId);

        if ($apenasReceitas) {
            $query->where('f.tipo', '=', 'R');
        }

        return $query
            ->orderBy('f.parcela', 'ASC')
            ->get();
    }

    /**
     * Atualiza parcela pendente
     *
     * @param int $locacaoId ID da locacao
     * @param int $idParcela ID do financeiro
     * @param array $dados Dados a atualizar
     * @return int Linhas afetadas
     */
    public function atualizarParcela(int $locacaoId, int $idParcela, array $dados): int
    {
        // Verificar se parcela pertence a locacao e nao esta paga
        $parcela = $this->qb
            ->table('financeiro')
            ->where('id', '=', $idParcela)
            ->where('id_locacao', '=', $locacaoId)
            ->where('pago', '=', 'N')
            ->first();

        if (!$parcela) {
            throw new \InvalidArgumentException('Parcela não encontrada ou já paga');
        }

        $dadosUpdate = ['updated_at' => date('Y-m-d H:i:s')];

        if (isset($dados['valor'])) {
            $dadosUpdate['valor_subtotal'] = $this->toDecimal($dados['valor']);
            $dadosUpdate['valor_total'] = $this->toDecimal($dados['valor']);
        }
        if (isset($dados['data_venci'])) {
            $dadosUpdate['data_venci'] = $dados['data_venci'];
        }
        if (isset($dados['id_conta'])) {
            $dadosUpdate['id_conta'] = !empty($dados['id_conta']) ? (int) $dados['id_conta'] : null;
        }
        if (isset($dados['id_forma_pagamento'])) {
            $dadosUpdate['id_forma_pagamento'] = !empty($dados['id_forma_pagamento']) ? (int) $dados['id_forma_pagamento'] : null;
        }
        if (isset($dados['descricao'])) {
            $dadosUpdate['descricao'] = $dados['descricao'];
        }

        return $this->qb
            ->table('financeiro')
            ->where('id', '=', $idParcela)
            ->update($dadosUpdate);
    }

    /**
     * Remove parcela pendente
     *
     * @param int $locacaoId ID da locacao
     * @param int $idParcela ID do financeiro
     * @return int Linhas afetadas
     */
    public function removerParcela(int $locacaoId, int $idParcela): int
    {
        // Verificar se parcela pertence a locacao e nao esta paga
        $parcela = $this->qb
            ->table('financeiro')
            ->where('id', '=', $idParcela)
            ->where('id_locacao', '=', $locacaoId)
            ->where('pago', '=', 'N')
            ->first();

        if (!$parcela) {
            throw new \InvalidArgumentException('Parcela não encontrada ou já paga');
        }

        return $this->qb
            ->table('financeiro')
            ->where('id', '=', $idParcela)
            ->delete();
    }

    /**
     * Marca uma parcela como paga (pagamento unico/cheio).
     *
     * @param int $locacaoId ID da locacao
     * @param int $idParcela ID do financeiro
     * @param array $dados [data_pago, id_forma_pagamento, id_conta]
     */
    public function marcarParcelaPaga(int $locacaoId, int $idParcela, array $dados): int
    {
        $parcela = $this->qb
            ->table('financeiro')
            ->where('id', '=', $idParcela)
            ->where('id_locacao', '=', $locacaoId)
            ->where('pago', '=', 'N')
            ->first();

        if (!$parcela) {
            throw new \InvalidArgumentException('Parcela não encontrada ou já paga');
        }

        $update = [
            'pago' => 'S',
            'data_pago' => !empty($dados['data_pago']) ? $dados['data_pago'] : date('Y-m-d'),
        ];
        if (!empty($dados['id_forma_pagamento'])) {
            $update['id_forma_pagamento'] = (int) $dados['id_forma_pagamento'];
        }
        if (!empty($dados['id_conta'])) {
            $update['id_conta'] = (int) $dados['id_conta'];
        }

        return $this->qb
            ->table('financeiro')
            ->where('id', '=', $idParcela)
            ->update($update);
    }

    /**
     * Estorna o pagamento de uma parcela (volta a Pendente).
     */
    public function estornarParcelaPagamento(int $locacaoId, int $idParcela): int
    {
        $parcela = $this->qb
            ->table('financeiro')
            ->where('id', '=', $idParcela)
            ->where('id_locacao', '=', $locacaoId)
            ->where('pago', '=', 'S')
            ->first();

        if (!$parcela) {
            throw new \InvalidArgumentException('Parcela não encontrada ou não está paga');
        }

        return $this->qb
            ->table('financeiro')
            ->where('id', '=', $idParcela)
            ->update([
                'pago' => 'N',
                'data_pago' => null,
            ]);
    }

    /**
     * Cria lancamento financeiro de devolucao/reembolso por diferenca na locacao.
     */
    public function criarCreditoDevolucao(int $locacaoId, float $valor, string $chave): int
    {
        $valor = round($valor, 2);
        if ($valor <= 0) {
            throw new \InvalidArgumentException('Valor de devolucao deve ser maior que zero');
        }

        $locacao = $this->buscarPorId($locacaoId);
        if (!$locacao) {
            throw new \InvalidArgumentException('Locação não encontrada');
        }

        $planoModel = new PlanoDeContas();
        $planoDevolucao = $planoModel->buscarPorHierarquia(self::PLANO_CONTA_DEVOLUCAO_LOCACAO);
        if (!$planoDevolucao) {
            throw new \InvalidArgumentException('Plano de contas de devolução de locação não encontrado');
        }

        $idMatrizFilial = (int) ($locacao['id_matriz_filial_retirada'] ?? 0);
        if ($idMatrizFilial <= 0) {
            throw new \InvalidArgumentException('Matriz/filial da locacao nao encontrada para gerar sequencia financeira');
        }

        $sequencia = SequenciaHelper::proximaSequencia($chave, $idMatrizFilial, 'financeiro');
        $financeiroModel = new Financeiro();

        return $financeiroModel->criar([
            'chave' => $chave,
            'sequencia' => $sequencia,
            'id_locacao' => $locacaoId,
            'id_veiculo' => $locacao['id_veiculo'] ?? null,
            'id_cliente' => $locacao['id_cliente'] ?? null,
            'id_matriz_filial' => $idMatrizFilial,
            'id_conta' => $locacao['id_conta'] ?? null,
            'id_forma_pagamento' => $locacao['id_forma_pagamento'] ?? null,
            'id_plano_de_conta' => (int) $planoDevolucao['id'],
            'tipo' => 'D',
            'pago' => 'N',
            'parcela' => 1,
            'total_parcelas' => 1,
            'descricao' => 'Devolucao/Reembolso - Locacao ' . ($locacao['codigo'] ?? $locacaoId),
            'data_criada' => date('Y-m-d'),
            'data_venci' => date('Y-m-d'),
            'valor_subtotal' => $valor,
        ]);
    }

    /**
     * Registra devolucao e, opcionalmente, cria credito financeiro na mesma transacao.
     */
    public function registrarDevolucaoComCredito(int $id, array $dados, float $valorCredito, string $chave): int
    {
        $mysqli = $this->getMysqli();
        $mysqli->begin_transaction();

        try {
            if (round($valorCredito, 2) > 0) {
                $this->criarCreditoDevolucao($id, $valorCredito, $chave);
            }

            $resultado = $this->registrarDevolucao($id, $dados);
            $mysqli->commit();

            return $resultado;
        } catch (\Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }
    }

    /**
     * Resumo financeiro da locacao
     *
     * @param int $locacaoId ID da locacao
     * @return array Totais financeiros
     */
    public function resumoFinanceiro(int $locacaoId, bool $apenasReceitas = false): array
    {
        $locacao = $this->qb
            ->table('locacoes')
            ->select(['total_pagar'])
            ->where('id', '=', $locacaoId)
            ->first();

        $queryTotais = $this->qb
            ->table('financeiro', 'f')
            ->selectRaw('
                SUM(CASE WHEN f.tipo = "R" THEN 1 ELSE 0 END) AS total_parcelas,
                SUM(CASE WHEN f.tipo = "R" THEN f.valor_total ELSE 0 END) AS total_receitas,
                SUM(CASE WHEN f.tipo = "D" AND pc.hierarquia = "' . self::PLANO_CONTA_DEVOLUCAO_LOCACAO . '" THEN f.valor_total ELSE 0 END) AS total_credito_devolucao,
                SUM(CASE WHEN f.tipo = "R" AND f.pago = "S" THEN f.valor_total ELSE 0 END) AS total_pago,
                SUM(CASE WHEN f.tipo = "R" AND f.pago = "N" THEN f.valor_total ELSE 0 END) AS total_pendente,
                SUM(CASE WHEN f.tipo = "R" AND f.pago = "N" AND f.data_venci < CURDATE() THEN f.valor_total ELSE 0 END) AS total_atrasado
            ')
            ->leftJoin('planos_de_contas', 'pc', 'f.id_plano_de_conta', '=', 'pc.id')
            ->where('f.id_locacao', '=', $locacaoId);

        if ($apenasReceitas) {
            $queryTotais->where('f.tipo', '=', 'R');
        }

        $totais = $queryTotais->first();

        $totalPagar = (float) ($locacao['total_pagar'] ?? 0);
        $totalReceitas = (float) ($totais['total_receitas'] ?? 0);
        $totalCreditoDevolucao = (float) ($totais['total_credito_devolucao'] ?? 0);
        $totalLancado = round($totalReceitas - $totalCreditoDevolucao, 2);

        return [
            'total_locacao' => $totalPagar,
            'total_lancado' => $totalLancado,
            'total_receitas' => $totalReceitas,
            'total_credito_devolucao' => $totalCreditoDevolucao,
            'total_pago' => (float) ($totais['total_pago'] ?? 0),
            'total_pendente' => (float) ($totais['total_pendente'] ?? 0),
            'total_atrasado' => (float) ($totais['total_atrasado'] ?? 0),
            'total_parcelas' => (int) ($totais['total_parcelas'] ?? 0),
            'diferenca' => round($totalPagar - $totalLancado, 2),
        ];
    }

    /**
     * Calcula os totais exibidos no Resumo da Locacao.
     *
     * Para locacoes fechadas, usa o ultimo veiculo do historico porque nao existe
     * mais veiculo ativo apos a devolucao.
     */
    public function calcularTotaisResumo(
        int $locacaoId,
        array $dados = [],
        bool $usarTaxasEnviadas = false,
        bool $atualizarTaxas = false
    ): array {
        $locacao = $this->buscarPorId($locacaoId);
        if (!$locacao) {
            throw new \InvalidArgumentException('Locacao nao encontrada');
        }

        $dias = max(1, (int) ($dados['dias'] ?? $locacao['dias'] ?? 1));
        $status = $dados['status'] ?? $locacao['status'] ?? 'R';

        $veiculoModel = new LocacaoVeiculo();
        $veiculo = $status === 'F'
            ? $veiculoModel->buscarAtualOuUltimo($locacaoId)
            : $veiculoModel->buscarAtivo($locacaoId);

        if ($status === 'F' && !$veiculo) {
            throw new \InvalidArgumentException('Locacao fechada sem historico de veiculo para recalcular totais');
        }

        $valorTotalVeiculos = 0.0;
        if ($veiculo) {
            $plano = $dados['plano'] ?? $locacao['plano'] ?? $veiculo['plano'] ?? 'KL';
            $valorPlano = match ($plano) {
                'KL' => (float) ($veiculo['valor_plano_km_livre'] ?? 0),
                'KMC' => (float) ($veiculo['valor_plano_km_controlado'] ?? 0),
                'DI', 'KP' => (float) ($veiculo['valor_plano_km_pago'] ?? 0),
                default => 0.0,
            };
            $seguroCarro = ($dados['seguro_carro'] ?? ($veiculo['seguro_carro'] ? 'S' : 'N')) === 'S';
            $seguroTerceiros = ($dados['seguro_terceiros'] ?? ($veiculo['seguro_terceiros'] ? 'S' : 'N')) === 'S';
            $valorSeguroCarro = $seguroCarro ? currency_parse($dados['seguro_carro_valor'] ?? $veiculo['valor_seguro_carro'] ?? 0) : 0;
            $valorSeguroTerceiros = $seguroTerceiros ? currency_parse($dados['seguro_terceiros_valor'] ?? $veiculo['valor_seguro_terceiros'] ?? 0) : 0;

            $valorTotalVeiculos = ($valorPlano + $valorSeguroCarro + $valorSeguroTerceiros) * $dias;
        }

        $taxaModel = new LocacaoTaxaServico();
        $totalTaxas = 0.0;
        $taxas = null;
        if ($usarTaxasEnviadas && isset($dados['taxas'])) {
            $taxas = is_string($dados['taxas']) ? json_decode($dados['taxas'], true) : $dados['taxas'];
        }
        if (is_array($taxas)) {
            foreach ($taxas as $taxa) {
                $totalTaxas += $taxaModel->calcularValorTotalTaxa($taxa, $dias, $valorTotalVeiculos);
            }
        } else {
            $taxasPersistidas = $taxaModel->listarPorLocacao($locacaoId);
            foreach ($taxasPersistidas as $taxa) {
                $valorTotal = $taxaModel->calcularValorTotalTaxa($taxa, $dias, $valorTotalVeiculos);
                $totalTaxas += $valorTotal;

                if ($atualizarTaxas && abs($valorTotal - (float) ($taxa['valor_total'] ?? 0)) > 0.009) {
                    $taxaModel->atualizarTotalCalculado((int) $taxa['id'], $valorTotal);
                }
            }
        }

        $condutores = $dados['condutor_adicional'] ?? $locacao['condutor_adicional'] ?? null;
        $condutores = is_string($condutores) ? json_decode($condutores, true) : $condutores;
        $qtdCondutores = is_array($condutores) ? count($condutores) : 0;
        $valorCondutor = $veiculo ? (float) ($veiculo['valor_condutor_adicional'] ?? 0) : 0;

        $totalKmExcedente = 0.0;
        $kmExcedente = 0;
        $valorKmExcedente = 0.0;
        if ($status === 'F' && $veiculo && ($dados['plano'] ?? $locacao['plano'] ?? $veiculo['plano'] ?? '') === 'KMC') {
            $odometroSaida = (int) ($dados['odometro_ini'] ?? $veiculo['odometro_saida'] ?? $locacao['odometro_ini'] ?? 0);
            $odometroEntrada = $this->normalizarOdometroResumo(
                $dados['odometro_fim'] ?? $veiculo['odometro_entrada'] ?? $locacao['odometro_fim'] ?? 0,
                true
            );
            $odometroUsado = $odometroEntrada > $odometroSaida ? $odometroEntrada - $odometroSaida : 0;
            $franquiaDiaria = (int) ($dados['km_controlado_franquia'] ?? $veiculo['km_franquia'] ?? 0);
            $kmPermitido = $franquiaDiaria * $dias;
            $kmExcedente = max(0, $odometroUsado - $kmPermitido);
            $valorKmExcedente = currency_parse($dados['km_valor'] ?? $veiculo['valor_km_excedente'] ?? 0);
            $totalKmExcedente = $kmExcedente * $valorKmExcedente;
        }

        $totalCombustivel = 0.0;
        $combustivelUsado = 0;
        $valorCombustivelUnitario = 0.0;
        if ($status === 'F' && $veiculo) {
            $combustivelSaida = isset($veiculo['combustivel_saida']) ? (int) $veiculo['combustivel_saida'] : null;
            $combustivelEntrada = $dados['combustivel_fim'] ?? $veiculo['combustivel_entrada'] ?? null;
            $combustivelEntrada = ($combustivelEntrada === '' || $combustivelEntrada === null) ? null : (int) $combustivelEntrada;
            $valorCombustivelUnitario = (float) ($veiculo['veiculo_valor_por_fracao'] ?? 0);
            $combustivelUsado = ($combustivelSaida !== null && $combustivelEntrada !== null)
                ? max(0, $combustivelSaida - $combustivelEntrada)
                : 0;
            $totalCombustivel = $combustivelUsado * $valorCombustivelUnitario;
        }

        $totalFatura = $valorTotalVeiculos + $totalTaxas + ($qtdCondutores * $valorCondutor) + $totalKmExcedente + $totalCombustivel;
        $desconto = currency_parse($dados['valor_desconto'] ?? $locacao['valor_desconto'] ?? 0);

        return [
            'total_fatura' => round($totalFatura, 2),
            'total_pagar' => round(max(0, $totalFatura - $desconto), 2),
            'total_veiculos' => round($valorTotalVeiculos, 2),
            'total_taxas' => round($totalTaxas, 2),
            'total_condutores' => round($qtdCondutores * $valorCondutor, 2),
            'total_km_excedente' => round($totalKmExcedente, 2),
            'total_combustivel' => round($totalCombustivel, 2),
            'km_excedente' => $kmExcedente,
            'valor_km_excedente' => round($valorKmExcedente, 2),
            'combustivel_usado' => $combustivelUsado,
            'valor_combustivel_unitario' => round($valorCombustivelUnitario, 2),
            'id_locacao_veiculo' => $veiculo['id'] ?? null,
        ];
    }

    public function sincronizarTotaisResumo(int $locacaoId, array $dados = [], bool $usarTaxasEnviadas = false): array
    {
        $totais = $this->calcularTotaisResumo($locacaoId, $dados, $usarTaxasEnviadas, true);

        $this->qb
            ->table('locacoes')
            ->where('id', '=', $locacaoId)
            ->update([
                'total_fatura' => $totais['total_fatura'],
                'total_pagar' => $totais['total_pagar'],
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return $totais;
    }

    /**
     * Atualiza status da locacao
     */
    public function atualizarStatus(int $id, string $status): int
    {
        return $this->qb
            ->table('locacoes')
            ->where('id', '=', $id)
            ->update([
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
    }

    /**
     * Gera codigo unico para a locacao
     */
    public function gerarCodigo(string $chave): string
    {
        $maxId = $this->qb
            ->table('locacoes')
            ->max('id');

        $proximoId = ($maxId ?? 0) + 1;
        $letras = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 2);

        return 'L' . str_pad($proximoId, 5, '0', STR_PAD_LEFT) . $letras;
    }

    /**
     * Gera proxima sequencia para o tenant
     */
    public function gerarSequencia(string $chave): int
    {
        $maxSequencia = $this->qb
            ->table('locacoes')
            ->max('sequencia');

        return ($maxSequencia ?? 0) + 1;
    }

    /**
     * Converte valor para decimal arredondado em 2 casas.
     * Wrapper sobre currency_parse() — mantido por causa do round() exigido
     * pelas regras de arredondamento monetário aplicadas em totais de locação.
     */
    private function toDecimal($value): float
    {
        return round(currency_parse($value), 2);
    }

    private function normalizarDataBusca(string $search): ?string
    {
        $search = trim($search);
        if (!preg_match('/^\d{1,4}[\/\-.]\d{1,2}[\/\-.]\d{1,4}$/', $search)) {
            return null;
        }

        return parse_date($search);
    }

    private function normalizarOdometroResumo($valor, bool $preferirUltimo = false): int
    {
        if (is_int($valor) || is_float($valor)) {
            return (int) $valor;
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return 0;
        }

        if (preg_match_all('/\d+/', $texto, $matches) && !empty($matches[0])) {
            $partes = $matches[0];
            $texto = $preferirUltimo ? end($partes) : reset($partes);
        }

        return (int) preg_replace('/\D/', '', $texto);
    }

    /**
     * Operacoes do dia para o dashboard.
     *
     * Retorna contadores de saidas hoje, devolucoes previstas hoje e locacoes em atraso.
     * Usa intervalos do dia para aproveitar indices em data_saida/data_prevista.
     */
    public function dashboardOperations(string $chave): array
    {
        $inicioDia = date('Y-m-d 00:00:00');
        $fimDia = date('Y-m-d 23:59:59');

        $departures = (int) $this->qb
            ->table('locacoes')
            ->where('status', '=', 'A')
            ->where('data_saida', '>=', $inicioDia)
            ->where('data_saida', '<=', $fimDia)
            ->count();

        $returns = (int) $this->qb
            ->table('locacoes')
            ->where('status', '=', 'A')
            ->whereNull('data_chegada')
            ->where('data_prevista', '>=', $inicioDia)
            ->where('data_prevista', '<=', $fimDia)
            ->count();

        $overdue = (int) $this->qb
            ->table('locacoes')
            ->where('status', '=', 'A')
            ->whereRaw('data_prevista < NOW()')
            ->whereNull('data_chegada')
            ->count();

        return [
            'departures_today' => $departures,
            'returns_today' => $returns,
            'overdue' => $overdue,
        ];
    }

    /**
     * Proximas reservas confirmadas (status R) para o dashboard.
     *
     * Retorna [codigo, cliente, veiculo, data] com data formatada dd/mm.
     * O veiculo eh resolvido via subquery em locacoes_veiculos para evitar
     * duplicacao em locacoes com multiplos veiculos.
     */
    public function dashboardUpcomingReservations(string $chave, int $limit = 5): array
    {
        $rows = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw("
                l.codigo,
                COALESCE(cl.nome_rsocial, l.cliente_nome) AS cliente,
                (
                    SELECT TRIM(CONCAT(COALESCE(v.marca,''), ' ', COALESCE(v.modelo,'')))
                    FROM locacoes_veiculos lv
                    LEFT JOIN veiculos v ON v.id = lv.id_veiculo
                    WHERE lv.id_locacao = l.id
                    ORDER BY lv.id ASC
                    LIMIT 1
                ) AS veiculo,
                l.data_saida
            ")
            ->leftJoin('clientes', 'cl', 'l.id_cliente', '=', 'cl.id')
            ->where('l.status', '=', 'R')
            ->where('l.data_saida', '>=', date('Y-m-d 00:00:00'))
            ->orderBy('l.data_saida', 'ASC')
            ->limit($limit)
            ->get();

        return array_map(function ($r) {
            return [
                'codigo' => $r['codigo'],
                'cliente' => $r['cliente'] ?? '',
                'veiculo' => trim((string) ($r['veiculo'] ?? '')),
                'data' => $r['data_saida'] ? date('d/m', strtotime($r['data_saida'])) : '',
            ];
        }, $rows);
    }

    /**
     * Ultimas reservas criadas (qualquer status) para o feed do dashboard.
     */
    public function dashboardLatestReservations(string $chave, int $limit = 5): array
    {
        $rows = $this->qb
            ->table('locacoes', 'l')
            ->selectRaw("
                l.codigo,
                l.status,
                l.created_at,
                COALESCE(cl.nome_rsocial, l.cliente_nome) AS cliente,
                (
                    SELECT TRIM(CONCAT(COALESCE(v.marca,''), ' ', COALESCE(v.modelo,'')))
                    FROM locacoes_veiculos lv
                    LEFT JOIN veiculos v ON v.id = lv.id_veiculo
                    WHERE lv.id_locacao = l.id
                    ORDER BY lv.id ASC
                    LIMIT 1
                ) AS veiculo
            ")
            ->leftJoin('clientes', 'cl', 'l.id_cliente', '=', 'cl.id')
            ->orderBy('l.created_at', 'DESC')
            ->limit($limit)
            ->get();

        $statusMap = ['R' => 'new', 'A' => 'confirmed', 'F' => 'confirmed'];

        return array_map(function ($r) use ($statusMap) {
            return [
                'hora' => $r['created_at'] ? date('H:i', strtotime($r['created_at'])) : '',
                'codigo' => $r['codigo'],
                'cliente' => $r['cliente'] ?? '',
                'veiculo' => trim((string) ($r['veiculo'] ?? '')),
                'status' => $statusMap[$r['status']] ?? 'new',
            ];
        }, $rows);
    }

    /**
     * Reservas exibidas nas subtabs do dashboard simples.
     */
    public function dashboardSimpleReservations(
        string $chave,
        int $limit = 20,
        string $filialWhere = '',
        array $filialParams = []
    ): array {
        $query = $this->dashboardSimpleLocacoesBaseQuery()
            ->where('l.status', '=', 'R')
            ->orderByRaw("CASE WHEN l.data_saida >= CURDATE() THEN 0 WHEN l.data_saida IS NULL OR l.data_saida = '0000-00-00 00:00:00' THEN 2 ELSE 1 END")
            ->orderBy('l.data_saida', 'ASC')
            ->orderByDesc('l.id')
            ->limit($limit);

        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        return array_map(
            fn ($row) => $this->formatDashboardSimpleLocacaoRow($row, 'data_saida'),
            $query->get()
        );
    }

    /**
     * Locações abertas exibidas nas subtabs do dashboard simples.
     */
    public function dashboardSimpleRented(
        string $chave,
        int $limit = 20,
        string $filialWhere = '',
        array $filialParams = []
    ): array {
        $query = $this->dashboardSimpleLocacoesBaseQuery()
            ->where('l.status', '=', 'A')
            ->whereNull('l.data_chegada')
            ->orderBy('l.data_prevista', 'ASC')
            ->limit($limit);

        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        return array_map(
            fn ($row) => $this->formatDashboardSimpleLocacaoRow($row, 'data_prevista'),
            $query->get()
        );
    }

    /**
     * Locações abertas com devolução prevista já vencida.
     */
    public function dashboardSimplePendingArrival(
        string $chave,
        int $limit = 20,
        string $filialWhere = '',
        array $filialParams = []
    ): array {
        $query = $this->dashboardSimpleLocacoesBaseQuery()
            ->where('l.status', '=', 'A')
            ->whereNull('l.data_chegada')
            ->whereRaw('l.data_prevista < NOW()')
            ->orderBy('l.data_prevista', 'ASC')
            ->limit($limit);

        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        return array_map(
            fn ($row) => $this->formatDashboardSimpleLocacaoRow($row, 'data_prevista'),
            $query->get()
        );
    }

    /**
     * Locações abertas com devolução prevista a partir de agora.
     */
    public function dashboardSimpleUpcomingReturns(
        string $chave,
        int $limit = 20,
        string $filialWhere = '',
        array $filialParams = []
    ): array {
        $query = $this->dashboardSimpleLocacoesBaseQuery()
            ->where('l.status', '=', 'A')
            ->whereNull('l.data_chegada')
            ->whereRaw('l.data_prevista >= NOW()')
            ->orderBy('l.data_prevista', 'ASC')
            ->limit($limit);

        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        return array_map(
            fn ($row) => $this->formatDashboardSimpleLocacaoRow($row, 'data_prevista'),
            $query->get()
        );
    }

    /**
     * Query base para listas compactas do dashboard simples.
     */
    private function dashboardSimpleLocacoesBaseQuery()
    {
        return $this->qb
            ->table('locacoes', 'l')
            ->select([
                'l.id',
                'l.codigo',
                'l.status',
                'l.data_saida',
                'l.data_prevista',
                'COALESCE(cl.nome_rsocial, l.cliente_nome) AS cliente',
                'v.placa',
                'v.marca',
                'v.modelo',
                'mf_ret.nome_fantasia AS filial_retirada',
                'mf_dev.nome_fantasia AS filial_devolucao',
            ])
            ->leftJoin('clientes', 'cl', 'l.id_cliente', '=', 'cl.id')
            ->leftJoinRaw('locacoes_veiculos', 'lv', 'lv.id_locacao = l.id AND lv.chave = l.chave AND lv.data_entrada IS NULL')
            ->leftJoin('veiculos', 'v', 'lv.id_veiculo', '=', 'v.id')
            ->leftJoin('matrizes_filiais', 'mf_ret', 'l.id_matriz_filial_retirada', '=', 'mf_ret.id')
            ->leftJoin('matrizes_filiais', 'mf_dev', 'l.id_matriz_filial_devolucao', '=', 'mf_dev.id');
    }

    /**
     * Formata uma locação para consumo direto pelo JS do dashboard simples.
     */
    private function formatDashboardSimpleLocacaoRow(array $row, string $dateField): array
    {
        $vehicleParts = array_filter([
            trim((string) ($row['marca'] ?? '')),
            trim((string) ($row['modelo'] ?? '')),
        ]);
        $vehicleName = trim(implode(' ', $vehicleParts));
        $plate = trim((string) ($row['placa'] ?? ''));

        $prazo = $this->formatDashboardDueInfo($row[$dateField] ?? null, (string) ($row['status'] ?? ''));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'codigo' => (string) ($row['codigo'] ?? ''),
            'tipo' => 'locacao',
            'tipo_label' => t('modules.dashboard.subtabs.rental'),
            'action' => 'locacao',
            'cliente' => (string) ($row['cliente'] ?? ''),
            'veiculo' => $vehicleName !== '' ? $vehicleName : t('modules.dashboard.subtabs.no_vehicle'),
            'placa' => $plate,
            'filial_retirada' => (string) ($row['filial_retirada'] ?? ''),
            'filial_devolucao' => (string) ($row['filial_devolucao'] ?? ''),
            'data_saida' => $this->formatDashboardDateTime($row['data_saida'] ?? null),
            'data_prevista' => $this->formatDashboardDateTime($row['data_prevista'] ?? null),
            'data_referencia' => $this->formatDashboardDateTime($row[$dateField] ?? null),
            'sort_at' => (string) ($row[$dateField] ?? ''),
            'prazo_label' => $prazo['label'],
            'prazo_tipo' => $prazo['tipo'],
        ];
    }

    private function formatDashboardDateTime(?string $value): string
    {
        if (empty($value) || str_starts_with($value, '0000-00-00')) {
            return '';
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('d/m/Y H:i', $timestamp) : '';
    }

    private function formatDashboardDueInfo(?string $value, string $status = ''): array
    {
        if (empty($value) || str_starts_with($value, '0000-00-00')) {
            return ['label' => '', 'tipo' => ''];
        }

        $timestamp = strtotime($value);
        if (!$timestamp) {
            return ['label' => '', 'tipo' => ''];
        }

        $now = time();
        $today = date('Y-m-d');
        $date = date('Y-m-d', $timestamp);

        if ($timestamp < $now) {
            if ($status === 'R') {
                return [
                    'label' => t('modules.dashboard.subtabs.pending_pickup'),
                    'tipo' => 'pending_pickup',
                ];
            }

            $secondsLate = max(60, $now - $timestamp);

            if ($secondsLate < 3600) {
                $minutes = max(1, (int) floor($secondsLate / 60));
                return [
                    'label' => t_choice('modules.dashboard.subtabs.overdue_minutes', $minutes),
                    'tipo' => 'overdue',
                ];
            }

            if ($secondsLate < 86400) {
                $hours = max(1, (int) floor($secondsLate / 3600));
                return [
                    'label' => t_choice('modules.dashboard.subtabs.overdue_hours', $hours),
                    'tipo' => 'overdue',
                ];
            }

            $days = max(1, (int) floor($secondsLate / 86400));
            return [
                'label' => t_choice('modules.dashboard.subtabs.overdue_days', $days),
                'tipo' => 'overdue',
            ];
        }

        if ($date === $today) {
            return [
                'label' => t('modules.dashboard.subtabs.today'),
                'tipo' => 'today',
            ];
        }

        if ($date === date('Y-m-d', strtotime('tomorrow'))) {
            return [
                'label' => t('modules.dashboard.subtabs.tomorrow'),
                'tipo' => 'tomorrow',
            ];
        }

        return [
            'label' => date('d/m', $timestamp),
            'tipo' => 'date',
        ];
    }

    /**
     * Soma do total_fatura das locacoes com saida prevista para hoje
     * (status R = reserva, A = aberto). Usado no KPI "Receita Prevista Hoje".
     */
    public function dashboardExpectedRevenueToday(string $chave): float
    {
        $row = $this->qb
            ->table('locacoes')
            ->selectRaw('COALESCE(SUM(total_fatura), 0) AS total')
            ->whereIn('status', ['R', 'A'])
            ->where('data_saida', '>=', date('Y-m-d 00:00:00'))
            ->where('data_saida', '<=', date('Y-m-d 23:59:59'))
            ->first();

        return (float) ($row['total'] ?? 0);
    }
}
