<?php

namespace App\Services;

use App\Classes\QueryBuilder;
use App\Models\ComissaoInvestidor;
use App\Models\Financeiro;
use App\Models\Fornecedor;
use App\Models\FornecedorComissaoRegra;
use App\Models\Grupo;
use App\Models\Model;
use App\Models\PlanoDeContas;

/**
 * Service ComissaoInvestidor
 *
 * Gerencia a logica de calculo e processamento de comissoes de investidores.
 * Suporta 4 tipos de cobranca:
 * - percentual_locadora: Locadora fica com X% do valor
 * - fixo_locadora: Locadora fica com valor fixo por fatura
 * - fixo_locadora_mensal: Locadora recebe valor fixo mensal por veiculo
 * - fixo_investidor_mensal: Investidor recebe valor fixo mensal por veiculo
 */
class ComissaoInvestidorService
{
    private const PLANO_CONTA_HIERARQUIA = '3.3.1.10';

    private ComissaoInvestidor $comissaoModel;
    private Fornecedor $fornecedorModel;
    private FornecedorComissaoRegra $regraModel;
    private Grupo $grupoModel;
    private Financeiro $financeiroModel;
    private PlanoDeContas $planoDeContasModel;
    private QueryBuilder $qb;

    public function __construct()
    {
        $this->comissaoModel = new ComissaoInvestidor();
        $this->fornecedorModel = new Fornecedor();
        $this->regraModel = new FornecedorComissaoRegra();
        $this->grupoModel = new Grupo();
        $this->financeiroModel = new Financeiro();
        $this->planoDeContasModel = new PlanoDeContas();
        $this->qb = new QueryBuilder(Model::sharedMysqli());
    }

    /**
     * Calcula e cria comissao quando uma fatura e paga
     *
     * @param array $financeiro Dados do lancamento financeiro pago
     * @param array $veiculo Dados do veiculo (deve incluir id_fornecedor e id_grupo)
     * @return int|null ID da comissao criada ou null se nao aplicavel
     */
    public function calcularComissaoPorPagamento(array $financeiro, array $veiculo): ?int
    {
        $analise = $this->analisarDadosPagamento($financeiro, $veiculo, true);
        if (!$analise['aplicavel']) {
            return null;
        }

        return $this->comissaoModel->criar($analise['dados_comissao']);
    }

    /**
     * Gera comissoes mensais para todos os veiculos com investidor
     * (tipos fixo_locadora_mensal e fixo_investidor_mensal)
     *
     * @param string $mesReferencia Mes de referencia no formato Y-m
     * @return array Resultado com quantidade de comissoes geradas
     */
    public function gerarComissoesMensais(string $mesReferencia): array
    {
        // Cross-tenant: processa veiculos de todos os tenants
        $veiculos = $this->qb
            ->table('veiculos', 'v')
            ->select([
                'v.id',
                'v.chave',
                'v.id_fornecedor',
                'v.id_grupo',
                'g.comissao_investidor_tipo',
                'g.comissao_investidor_valor',
                'f.nome_rsocial AS fornecedor_nome',
            ])
            ->leftJoinRaw('grupos', 'g', 'g.id = v.id_grupo AND g.chave = v.chave')
            ->leftJoinRaw('fornecedores', 'f', 'f.id = v.id_fornecedor AND f.chave = v.chave')
            ->withoutChave()
            ->whereNotNull('v.id_fornecedor')
            ->whereNotNull('v.id_grupo')
            ->where('f.investidor', '=', 1)
            ->get();

        $geradas = 0;
        $ignoradas = 0;
        $erros = [];

        foreach ($veiculos as $veiculo) {
            try {
                $regra = $this->resolverRegraComissao(
                    $veiculo['chave'],
                    (int) $veiculo['id_fornecedor'],
                    (int) ($veiculo['id_grupo'] ?? 0),
                    [
                        'comissao_investidor_tipo' => $veiculo['comissao_investidor_tipo'] ?? null,
                        'comissao_investidor_valor' => $veiculo['comissao_investidor_valor'] ?? null,
                    ]
                );

                if (
                    !$regra
                    || !in_array($regra['comissao_tipo'], ['fixo_locadora_mensal', 'fixo_investidor_mensal'], true)
                    || (float) $regra['comissao_valor'] <= 0
                ) {
                    $ignoradas++;
                    continue;
                }

                // Verificar se ja existe comissao para este veiculo neste mes
                $existe = $this->qb
                    ->table('comissoes_investidores')
                    ->withChave($veiculo['chave'])
                    ->where('id_veiculo', '=', $veiculo['id'])
                    ->whereRaw("DATE_FORMAT(data_referencia, '%Y-%m') = ?", [$mesReferencia])
                    ->where('tipo_origem', '=', 'mensal')
                    ->where('status', '!=', 'cancelado')
                    ->exists();

                if ($existe) {
                    $ignoradas++;
                    continue;
                }

                // Calcular valores
                $calculo = $this->calcularValores(
                    $regra['comissao_tipo'],
                    $regra['comissao_valor'],
                    0 // Valor base 0 para tipos mensais
                );

                // Criar comissao
                $this->comissaoModel->criar([
                    'chave' => $veiculo['chave'],
                    'id_fornecedor' => $veiculo['id_fornecedor'],
                    'id_veiculo' => $veiculo['id'],
                    'id_grupo' => $veiculo['id_grupo'],
                    'tipo_origem' => 'mensal',
                    'valor_base' => 0,
                    'comissao_tipo' => $regra['comissao_tipo'],
                    'comissao_valor_fixo' => $regra['comissao_valor'],
                    'valor_comissao_locadora' => $calculo['locadora'],
                    'valor_repasse_investidor' => $calculo['investidor'],
                    'status' => 'pendente',
                    'data_referencia' => $mesReferencia . '-01',
                ]);

                $geradas++;

            } catch (\Exception $e) {
                $erros[] = "Veiculo {$veiculo['id']}: " . $e->getMessage();
            }
        }

        return [
            'mes_referencia' => $mesReferencia,
            'total_veiculos' => count($veiculos),
            'comissoes_geradas' => $geradas,
            'comissoes_ignoradas' => $ignoradas,
            'erros' => $erros
        ];
    }

    /**
     * Marca uma comissao como paga e cria lancamento no financeiro
     *
     * @param int $comissaoId ID da comissao
     * @param string $chave Chave do tenant (para validacao)
     * @param array $opcoes Opcoes adicionais (id_conta, observacao, etc)
     * @return array Resultado da operacao
     */
    public function marcarComoPago(int $comissaoId, string $chave, array $opcoes = []): array
    {
        $comissao = $this->comissaoModel->buscarPorId($comissaoId);

        if (!$comissao) {
            throw new \InvalidArgumentException('Comissao nao encontrada');
        }

        if ($comissao['chave'] !== $chave) {
            throw new \InvalidArgumentException('Comissao nao pertence a este tenant');
        }

        if ($comissao['status'] !== 'pendente') {
            throw new \InvalidArgumentException('Comissao nao esta pendente');
        }

        $this->qb->beginTransaction();

        try {
            $planoConta = $this->planoDeContasModel->buscarPorHierarquia(self::PLANO_CONTA_HIERARQUIA);

            if (!$planoConta || ($planoConta['tipo'] ?? null) !== 'D') {
                throw new \RuntimeException(
                    'Plano de contas Comissoes Investidores (3.3.1.10) nao configurado'
                );
            }

            // Criar lancamento no financeiro (despesa - repasse ao investidor)
            $idFinanceiro = null;
            if ($comissao['valor_repasse_investidor'] > 0) {
                $descricao = sprintf(
                    'Repasse Investidor - %s - %s',
                    $comissao['fornecedor_nome'] ?? 'Investidor',
                    \App\Helpers\DateHelper::formatTimestamp(strtotime($comissao['data_referencia']), 'm/Y')
                );

                $idFinanceiro = $this->financeiroModel->criar([
                    'chave' => $chave,
                    'tipo' => 'D',
                    'pago' => 'S',
                    'descricao' => $descricao,
                    'valor_subtotal' => $comissao['valor_repasse_investidor'],
                    'data_venci' => today(),
                    'data_pago' => today(),
                    'id_fornecedor' => $comissao['id_fornecedor'],
                    'id_plano_de_conta' => (int) $planoConta['id'],
                ]);
            }

            // Atualizar comissao
            $this->comissaoModel->atualizar($comissaoId, [
                'status' => 'pago',
                'data_pagamento' => today(),
                'id_financeiro' => $idFinanceiro
            ]);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", pagou comissao investidor ID [{$comissaoId}]"
            );

            $this->qb->commit();

            return [
                'success' => true,
                'message' => 'Comissao marcada como paga',
                'id_financeiro' => $idFinanceiro
            ];

        } catch (\Exception $e) {
            $this->qb->rollback();
            throw $e;
        }
    }

    /**
     * Cancela uma comissao
     *
     * @param int $comissaoId ID da comissao
     * @param string $chave Chave do tenant
     * @param string $motivo Motivo do cancelamento
     * @return array Resultado da operacao
     */
    public function cancelar(int $comissaoId, string $chave, string $motivo = ''): array
    {
        $comissao = $this->comissaoModel->buscarPorId($comissaoId);

        if (!$comissao) {
            throw new \InvalidArgumentException('Comissao nao encontrada');
        }

        if ($comissao['chave'] !== $chave) {
            throw new \InvalidArgumentException('Comissao nao pertence a este tenant');
        }

        if ($comissao['status'] === 'cancelado') {
            throw new \InvalidArgumentException('Comissao ja esta cancelada');
        }

        $this->qb->beginTransaction();

        try {
            // Se ja foi paga e tem lancamento financeiro, reverter pagamento
            if ($comissao['status'] === 'pago' && !empty($comissao['id_financeiro'])) {
                $this->financeiroModel->atualizar(
                    (int) $comissao['id_financeiro'],
                    ['pago' => 'N', 'data_pago' => null]
                );
            }

            // Atualizar status
            $this->comissaoModel->atualizar($comissaoId, [
                'status' => 'cancelado'
            ]);

            AuditLogService::registrarComCampos(
                ($_SESSION['user_name'] ?? 'Sistema') . ", cancelou comissao investidor ID [{$comissaoId}]" .
                ($motivo ? " - Motivo: {$motivo}" : ''),
                [
                    AuditLogService::campo('Comissao', (string) $comissaoId, 'Cancelada'),
                    AuditLogService::campo('Financeiro de origem', (string) ($comissao['id_financeiro_origem'] ?? '-'), null),
                    AuditLogService::campo('Status da comissao', (string) $comissao['status'], 'cancelado'),
                    AuditLogService::campo('Despesa de repasse', (string) ($comissao['id_financeiro'] ?? '-'),
                        $comissao['status'] === 'pago' && !empty($comissao['id_financeiro']) ? 'Pagamento estornado' : null),
                    AuditLogService::campo('Motivo', null, $motivo ?: 'Cancelamento manual'),
                ]
            );

            $this->qb->commit();

            return [
                'success' => true,
                'message' => 'Comissao cancelada com sucesso'
            ];

        } catch (\Exception $e) {
            $this->qb->rollback();
            throw $e;
        }
    }

    /**
     * Calcula os valores de comissao baseado no tipo
     *
     * @param string $tipo Tipo de comissao
     * @param float $valorConfig Valor configurado (% ou fixo)
     * @param float $valorBase Valor base da transacao
     * @return array ['locadora' => float, 'investidor' => float]
     */
    public function calcularValores(string $tipo, float $valorConfig, float $valorBase): array
    {
        switch ($tipo) {
            case 'percentual_locadora':
                // Locadora fica com X% do valor
                $locadora = $valorBase * ($valorConfig / 100);
                $investidor = $valorBase - $locadora;
                break;

            case 'fixo_locadora':
                // Locadora fica com valor fixo por fatura
                $locadora = min($valorConfig, $valorBase); // Nao pode ser maior que o valor base
                $investidor = $valorBase - $locadora;
                break;

            case 'fixo_locadora_mensal':
                // Locadora recebe valor fixo mensal (investidor paga)
                $locadora = $valorConfig;
                $investidor = 0; // Neste tipo, investidor nao recebe nada na comissao
                break;

            case 'fixo_investidor_mensal':
                // Investidor recebe valor fixo mensal
                $locadora = 0;
                $investidor = $valorConfig;
                break;

            default:
                $locadora = 0;
                $investidor = $valorBase;
        }

        return [
            'locadora' => round($locadora, 2),
            'investidor' => round($investidor, 2)
        ];
    }

    private function resolverRegraComissao(string $chave, int $idFornecedor, ?int $idGrupo, array $grupo): ?array
    {
        $regraFornecedor = $this->regraModel->buscarAplicavel($chave, $idFornecedor, $idGrupo);
        if ($regraFornecedor) {
            return [
                'comissao_tipo' => $regraFornecedor['comissao_tipo'],
                'comissao_valor' => (float) $regraFornecedor['comissao_valor'],
                'origem_regra' => $regraFornecedor['origem_regra'] ?? 'fornecedor',
            ];
        }

        if (empty($grupo['comissao_investidor_tipo'])) {
            return null;
        }

        return [
            'comissao_tipo' => $grupo['comissao_investidor_tipo'],
            'comissao_valor' => (float) ($grupo['comissao_investidor_valor'] ?? 0),
            'origem_regra' => 'grupo',
        ];
    }

    /**
     * Retorna resumo de comissoes por investidor
     *
     * @param string $chave Chave do tenant
     * @param string|null $mesReferencia Mes de referencia (opcional)
     * @return array Lista de investidores com totais
     */
    public function resumoPorInvestidor(string $chave, ?string $mesReferencia = null): array
    {
        $query = $this->qb
            ->table('comissoes_investidores', 'ci')
            ->selectRaw(
                "ci.id_fornecedor,
                 f.nome_rsocial AS nome,
                 f.cpf_cnpj,
                 COUNT(*) AS total_comissoes,
                 SUM(CASE WHEN ci.status = 'pendente' THEN 1 ELSE 0 END) AS pendentes,
                 SUM(CASE WHEN ci.status = 'pago' THEN 1 ELSE 0 END) AS pagos,
                 SUM(CASE WHEN ci.status = 'pendente' THEN ci.valor_repasse_investidor ELSE 0 END) AS valor_pendente,
                 SUM(CASE WHEN ci.status = 'pago' THEN ci.valor_repasse_investidor ELSE 0 END) AS valor_pago"
            )
            ->innerJoin('fornecedores', 'f', 'f.id', '=', 'ci.id_fornecedor')
            ->withChave($chave);

        if ($mesReferencia) {
            $query->whereRaw("DATE_FORMAT(ci.data_referencia, '%Y-%m') = ?", [$mesReferencia]);
        }

        return $query
            ->groupBy('ci.id_fornecedor')
            ->groupBy('f.nome_rsocial')
            ->groupBy('f.cpf_cnpj')
            ->orderByRaw('valor_pendente DESC')
            ->get();
    }

    /**
     * Processa comissao a partir de um ID de financeiro pago.
     * Resolve internamente o veiculo via locacao ou contrato.
     * @param int $idFinanceiro ID do lancamento financeiro
     * @param bool $silencioso Se false, relanca falhas inesperadas para o chamador
     * @param bool $permitirAposCancelamento Permite nova comissao quando a anterior foi cancelada
     * @return int|null ID da comissao criada ou null
     */
    public function processarComissaoPorFinanceiro(
        int $idFinanceiro,
        bool $silencioso = true,
        bool $permitirAposCancelamento = true
    ): ?int
    {
        try {
            $analise = $this->analisarComissaoPorFinanceiro($idFinanceiro, $permitirAposCancelamento);
            if (!$analise['aplicavel']) {
                return null;
            }

            $comissaoId = $this->comissaoModel->criar($analise['dados_comissao']);
            $this->registrarGeracao($comissaoId, $analise['dados_comissao']);
            return $comissaoId;

        } catch (\Throwable $e) {
            error_log("[Comissao] Erro ao processar comissao para financeiro #{$idFinanceiro}: " . $e->getMessage());
            if (!$silencioso) {
                throw $e;
            }
            return null;
        }
    }

    /**
     * Analisa, sem gravar, se um recebimento pode gerar comissao.
     *
     * @return array{aplicavel:bool,motivo:string,dados_comissao?:array}
     */
    public function analisarComissaoPorFinanceiro(
        int $idFinanceiro,
        bool $permitirAposCancelamento = true
    ): array
    {
        $financeiro = $this->financeiroModel->buscarPorId($idFinanceiro);
        if (!$financeiro) {
            return ['aplicavel' => false, 'motivo' => 'financeiro_nao_encontrado'];
        }

        if (($financeiro['tipo'] ?? '') !== 'R') {
            return ['aplicavel' => false, 'motivo' => 'nao_e_receita'];
        }
        if (($financeiro['pago'] ?? 'N') !== 'S') {
            return ['aplicavel' => false, 'motivo' => 'receita_nao_paga'];
        }
        if (empty($financeiro['id_locacao']) && empty($financeiro['id_contrato'])) {
            return ['aplicavel' => false, 'motivo' => 'sem_contrato_ou_locacao'];
        }

        $veiculo = $this->resolverVeiculoPorFinanceiro($financeiro);
        if (!$veiculo) {
            return ['aplicavel' => false, 'motivo' => 'veiculo_nao_resolvido'];
        }

        return $this->analisarDadosPagamento([
            'id' => (int) $financeiro['id'],
            'chave' => (string) $financeiro['chave'],
            'valor' => (float) ($financeiro['valor_total'] ?? $financeiro['valor_subtotal']),
            'data_pago' => $financeiro['data_pago'] ?? null,
            'id_locacao' => $financeiro['id_locacao'] ?? null,
            'id_contrato' => $financeiro['id_contrato'] ?? null,
        ], $veiculo, $permitirAposCancelamento);
    }

    /**
     * Cancela a comissao ativa originada pelo recebimento estornado.
     *
     * @return array{cancelada:bool,id_comissao:?int,id_financeiro_repasse:?int,status_anterior:?string}
     */
    public function cancelarPorFinanceiroOrigem(int $idFinanceiroOrigem, string $chave, string $motivo): array
    {
        $comissao = $this->comissaoModel->buscarAtivaPorOrigem($chave, $idFinanceiroOrigem);
        if (!$comissao) {
            return [
                'cancelada' => false,
                'id_comissao' => null,
                'id_financeiro_repasse' => null,
                'status_anterior' => null,
            ];
        }

        $statusAnterior = (string) $comissao['status'];
        $idFinanceiroRepasse = !empty($comissao['id_financeiro'])
            ? (int) $comissao['id_financeiro']
            : null;

        $this->cancelar((int) $comissao['id'], $chave, $motivo);

        return [
            'cancelada' => true,
            'id_comissao' => (int) $comissao['id'],
            'id_financeiro_repasse' => $idFinanceiroRepasse,
            'status_anterior' => $statusAnterior,
        ];
    }

    /** @return array{aplicavel:bool,motivo:string,dados_comissao?:array} */
    private function analisarDadosPagamento(
        array $financeiro,
        array $veiculo,
        bool $permitirAposCancelamento
    ): array
    {
        if (empty($veiculo['id_fornecedor'])) {
            return ['aplicavel' => false, 'motivo' => 'veiculo_sem_fornecedor'];
        }

        $fornecedor = $this->fornecedorModel->buscarPorId((int) $veiculo['id_fornecedor']);
        if (!$fornecedor || (int) ($fornecedor['investidor'] ?? 0) !== 1) {
            return ['aplicavel' => false, 'motivo' => 'fornecedor_nao_investidor'];
        }
        if (empty($veiculo['id_grupo'])) {
            return ['aplicavel' => false, 'motivo' => 'veiculo_sem_grupo'];
        }

        $grupo = $this->grupoModel->buscarPorId((int) $veiculo['id_grupo']);
        if (!$grupo) {
            return ['aplicavel' => false, 'motivo' => 'grupo_nao_encontrado'];
        }

        $regra = $this->resolverRegraComissao(
            (string) $financeiro['chave'],
            (int) $veiculo['id_fornecedor'],
            (int) $veiculo['id_grupo'],
            $grupo
        );
        if (!$regra) {
            return ['aplicavel' => false, 'motivo' => 'sem_regra_comissao'];
        }
        if (in_array($regra['comissao_tipo'], ['fixo_locadora_mensal', 'fixo_investidor_mensal'], true)) {
            return ['aplicavel' => false, 'motivo' => 'regra_mensal'];
        }
        $comissaoExistente = $permitirAposCancelamento
            ? $this->comissaoModel->existeParaOrigem((string) $financeiro['chave'], (int) $financeiro['id'])
            : $this->comissaoModel->existeQualquerParaOrigem((string) $financeiro['chave'], (int) $financeiro['id']);
        if ($comissaoExistente) {
            return ['aplicavel' => false, 'motivo' => 'comissao_ja_existente'];
        }

        $valorBase = (float) $financeiro['valor'];
        $calculo = $this->calcularValores($regra['comissao_tipo'], $regra['comissao_valor'], $valorBase);
        $tipoOrigem = !empty($financeiro['id_locacao']) ? 'locacao' : 'contrato';
        $dataReferencia = !empty($financeiro['data_pago'])
            ? (string) $financeiro['data_pago']
            : today();

        return [
            'aplicavel' => true,
            'motivo' => 'elegivel',
            'dados_comissao' => [
                'chave' => (string) $financeiro['chave'],
                'id_fornecedor' => (int) $veiculo['id_fornecedor'],
                'id_veiculo' => (int) $veiculo['id'],
                'id_grupo' => (int) $veiculo['id_grupo'],
                'tipo_origem' => $tipoOrigem,
                'id_locacao' => $financeiro['id_locacao'] ?? null,
                'id_contrato' => $financeiro['id_contrato'] ?? null,
                'id_financeiro_origem' => (int) $financeiro['id'],
                'valor_base' => $valorBase,
                'comissao_tipo' => $regra['comissao_tipo'],
                'comissao_percentual' => $regra['comissao_tipo'] === 'percentual_locadora'
                    ? $regra['comissao_valor']
                    : null,
                'comissao_valor_fixo' => $regra['comissao_tipo'] === 'fixo_locadora'
                    ? $regra['comissao_valor']
                    : null,
                'valor_comissao_locadora' => $calculo['locadora'],
                'valor_repasse_investidor' => $calculo['investidor'],
                'status' => 'pendente',
                'data_referencia' => $dataReferencia,
            ],
        ];
    }

    private function registrarGeracao(int $comissaoId, array $dados): void
    {
        try {
            AuditLogService::registrarComCampos(
                ($_SESSION['user_name'] ?? 'Sistema') . ", gerou comissao investidor ID [{$comissaoId}]",
                [
                    AuditLogService::campo('Financeiro de origem', null, (string) $dados['id_financeiro_origem']),
                    AuditLogService::campo('Origem', null, (string) $dados['tipo_origem']),
                    AuditLogService::campo('Valor base', null, currency_format((float) $dados['valor_base'], true)),
                    AuditLogService::campo('Repasse ao investidor', null, currency_format((float) $dados['valor_repasse_investidor'], true)),
                    AuditLogService::campo('Status', null, 'pendente'),
                ]
            );
        } catch (\Throwable $e) {
            error_log('[Comissao/Auditoria] ' . $e->getMessage());
        }
    }

    /**
     * Resolve dados do veiculo a partir de um lancamento financeiro.
     * Busca via locacao (locacoes_veiculos) ou contrato (contratos_veiculos).
     *
     * @param array $financeiro Dados do financeiro
     * @return array|null ['id' => int, 'id_fornecedor' => int|null, 'id_grupo' => int|null]
     */
    private function resolverVeiculoPorFinanceiro(array $financeiro): ?array
    {
        $idVeiculo = null;
        $idGrupo = null;

        if (!empty($financeiro['id_locacao'])) {
            $locacaoVeiculoModel = new \App\Models\LocacaoVeiculo();
            $lv = $locacaoVeiculoModel->buscarAtivo((int) $financeiro['id_locacao']);

            // Fallback: ultimo veiculo da locacao (pode estar fechada)
            if (!$lv) {
                $todos = $locacaoVeiculoModel->listarPorLocacao((int) $financeiro['id_locacao']);
                $lv = !empty($todos) ? end($todos) : null;
            }

            if ($lv) {
                $idVeiculo = $lv['id_veiculo'];
                $idGrupo = $lv['id_grupo'] ?? null;
            }
        } elseif (!empty($financeiro['id_contrato'])) {
            $contratoVeiculoModel = new \App\Models\ContratoVeiculo();
            $cv = $contratoVeiculoModel->buscarAtivo((int) $financeiro['id_contrato']);

            // Fallback: ultimo veiculo do contrato
            if (!$cv) {
                $todos = $contratoVeiculoModel->listarPorContrato((int) $financeiro['id_contrato']);
                $cv = !empty($todos) ? end($todos) : null;
            }

            if ($cv) {
                $idVeiculo = $cv['id_veiculo'];
                $idGrupo = $cv['id_grupo'] ?? null;
            }
        }

        if (!$idVeiculo) {
            return null;
        }

        // Buscar veiculo completo para obter id_fornecedor
        $veiculoModel = new \App\Models\Veiculo();
        $veiculo = $veiculoModel->buscarPorId((int) $idVeiculo);

        if (!$veiculo) {
            return null;
        }

        return [
            'id' => (int) $veiculo['id'],
            'id_fornecedor' => $veiculo['id_fornecedor'] ?? null,
            'id_grupo' => $idGrupo ?? $veiculo['id_grupo'] ?? null,
        ];
    }
}
