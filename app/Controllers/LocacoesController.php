<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Locacao;
use App\Models\LocacaoCaucao;
use App\Models\LocacaoVeiculo;
use App\Models\LocacaoTaxaServico;
use App\Models\Veiculo;
use App\Models\VeiculoDisponibilidadeSync;
use App\Models\TaxaServico;
use App\Models\Cliente;
use App\Models\Fornecedor;
use App\Models\MatrizFilial;
use App\Models\PlanoDeContas;
use App\Helpers\FilialHelper;
use App\Helpers\PdfHelper;
use App\Helpers\FileHelper;
use App\Models\Documento;
use App\Models\Checklist;
use App\Models\ChecklistModelo;
use App\Models\Assinatura;
use App\Models\Multa;
use App\Models\Whatsapp;
use App\Models\Sms;
use App\Config\Planos;
use App\I18n\TemplateRenderer;
use App\Core\Database;
use App\Services\AuditLogService;
use App\Services\GrupoPrecoPeriodoService;
use SimpleSoftwareIO\QrCode\Generator as QrCodeGenerator;

/**
 * Controller de Locacoes/Reservas
 *
 * Gerencia operacoes CRUD de locacoes de veiculos (curto prazo).
 * Fluxo: Reserva (R) -> Aberto (A) -> Fechado (F)
 */
class LocacoesController
{
    private array $tmpFiles = [];

    private function apiMessage(string $key, array $replace = []): string
    {
        return t('modules.locacoes.api.' . $key, $replace);
    }

    private function mensagemErroBanco(\Throwable $e, string $contexto): string
    {
        if (str_contains($e->getMessage(), 'Lock wait timeout exceeded')) {
            return "{$contexto}: o sistema esta processando outra geracao financeira no momento. Tente novamente em instantes.";
        }

        return "{$contexto}: " . $e->getMessage();
    }

    private function validarCaucaoLocacao(array $dados): ?string
    {
        $caucaoValor = currency_parse($dados['caucao_valor'] ?? 0);
        if ($caucaoValor <= 0) {
            return null;
        }

        if (empty($dados['id_conta_caucao'])) {
            return $this->apiMessage('deposit_account_required');
        }

        if (empty($dados['id_forma_pagamento_caucao'])) {
            return $this->apiMessage('deposit_payment_method_required');
        }

        if (!isset($dados['caucao_prazo_devolucao']) || $dados['caucao_prazo_devolucao'] === '') {
            return $this->apiMessage('deposit_return_deadline_required');
        }

        return null;
    }

    /**
     * Mapeia o valor base do plano para a coluna correta em locacoes_veiculos.
     *
     * - KL: diaria_valor -> valor_plano_km_livre
     * - DI/KP: diaria_valor -> valor_plano_km_pago
     * - KMC: diaria_valor -> valor_plano_km_controlado
     */
    private function mapearValoresPlanoVeiculo(array $dados, array $fallback = []): array
    {
        $plano = $dados['plano'] ?? $fallback['plano'] ?? 'KL';
        $diariaValor = $dados['diaria_valor'] ?? null;

        if (($dados['diaria_valor_origem'] ?? 'auto') !== 'manual') {
            $grupoId = (int) ($dados['id_grupo'] ?? $fallback['id_grupo'] ?? 0);
            $filialId = (int) ($dados['id_matriz_filial_retirada'] ?? $fallback['id_matriz_filial_retirada'] ?? 0);
            $dias = max(1, (int) ($dados['dias'] ?? $fallback['dias'] ?? 1));

            if ($grupoId > 0 && $filialId > 0) {
                $calculo = (new GrupoPrecoPeriodoService())->calcularValorDiaria($grupoId, $filialId, (string) $plano, $dias);
                $diariaValor = $calculo['valor'];
            }
        }

        if (($diariaValor === null || $diariaValor === '') && $plano === 'KMC') {
            $diariaValor = $dados['km_controlado_valor']
                ?? $fallback['valor_plano_km_controlado']
                ?? $fallback['km_controlado_valor']
                ?? null;
        }

        if (($diariaValor === null || $diariaValor === '') && $plano === 'KL') {
            $diariaValor = $fallback['valor_plano_km_livre']
                ?? $fallback['km_livre_valor']
                ?? null;
        }

        if (($diariaValor === null || $diariaValor === '') && in_array($plano, ['DI', 'KP'], true)) {
            $diariaValor = $fallback['valor_plano_km_pago']
                ?? null;
        }

        $diariaValor = $diariaValor ?? 0;
        $valores = [
            'valor_plano_km_pago' => '0',
            'valor_plano_km_livre' => '0',
            'valor_plano_km_controlado' => '0',
        ];

        switch ($plano) {
            case 'KL':
                $valores['valor_plano_km_livre'] = $diariaValor;
                break;
            case 'DI':
            case 'KP':
                $valores['valor_plano_km_pago'] = $diariaValor;
                break;
            case 'KMC':
                $valores['valor_plano_km_controlado'] = $diariaValor;
                break;
        }

        return $valores;
    }

    private function calcularDiasComTolerancia(string $dataSaida, string $dataChegada, int $minutosTolerancia = 0): int
    {
        $saidaTimestamp = strtotime($dataSaida);
        $chegadaTimestamp = strtotime($dataChegada);

        if ($saidaTimestamp === false || $chegadaTimestamp === false) {
            return 1;
        }

        $diferencaMinutos = max(0, (int) ceil(($chegadaTimestamp - $saidaTimestamp) / 60));
        $minutosCobrados = max(0, $diferencaMinutos - max(0, $minutosTolerancia));

        return max(1, (int) ceil($minutosCobrados / 1440));
    }

    private function validarVeiculoDisponivelParaSaida(int $veiculoId, string $chave): void
    {
        $veiculo = (new Veiculo())->buscarPorId($veiculoId);
        if (!$veiculo || ($veiculo['chave'] ?? '') !== $chave) {
            throw new \InvalidArgumentException($this->apiMessage('vehicle_not_found'));
        }

        if (!FilialHelper::temAcessoFilial($veiculo['id_matriz_filial'] ?? null)) {
            throw new \InvalidArgumentException($this->apiMessage('vehicle_access_denied'));
        }

        if (($veiculo['disponibilidade'] ?? '') !== 'D') {
            throw new \InvalidArgumentException($this->apiMessage('preferred_vehicle_unavailable'));
        }

        if ((new VeiculoDisponibilidadeSync())->possuiVinculoAtivo($veiculoId, $chave)) {
            throw new \InvalidArgumentException($this->apiMessage('preferred_vehicle_active_link'));
        }
    }

    /**
     * Renderiza a pagina de listagem de locacoes
     *
     * GET /pages/locacoes
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.locacoes.index');
        Response::html($html);
    }

    /**
     * Renderiza o formulario de nova locacao
     *
     * GET /pages/locacoes/adicionar
     */
    public function formView(Request $request): void
    {
        $html = Template::render('pages.locacoes.adicionar');
        Response::html($html);
    }

    /**
     * Renderiza o formulario de edicao de locacao existente
     *
     * GET /pages/locacoes/editar/{id}
     */
    public function editView(Request $request, int $id): void
    {
        $locacaoModel = new Locacao();
        $locacao = $locacaoModel->buscarPorId($id);

        if (!$locacao) {
            Response::redirect('/pages/locacoes');
            return;
        }

        if (!FilialHelper::temAcessoFilial($locacao['id_matriz_filial_retirada'] ?? null)) {
            Response::redirect('/pages/locacoes');
            return;
        }

        $html = Template::render('pages.locacoes.adicionar', [
            'locacao' => $locacao,
        ]);
        Response::html($html);
    }

    /**
     * Renderiza a tela dedicada de substituicao de veiculo.
     *
     * GET /pages/locacoes/substituir/{id}
     */
    public function substituirView(Request $request, int $id): void
    {
        if (!Auth::can('locacoes.substituir')) {
            Response::redirect('/pages/locacoes');
            return;
        }

        $locacaoModel = new Locacao();
        $locacao = $locacaoModel->buscarPorId($id);

        if (!$locacao) {
            Response::redirect('/pages/locacoes');
            return;
        }

        if (($locacao['chave'] ?? '') !== Auth::chave()) {
            Response::redirect('/pages/locacoes');
            return;
        }

        if (!FilialHelper::temAcessoFilial($locacao['id_matriz_filial_retirada'] ?? null)) {
            Response::redirect('/pages/locacoes');
            return;
        }

        if (($locacao['status'] ?? '') !== 'A') {
            Response::redirect('/pages/locacoes');
            return;
        }

        $locacaoVeiculoModel = new LocacaoVeiculo();
        $veiculoAtivo = $locacaoVeiculoModel->buscarAtivo($id);

        if (!$veiculoAtivo || empty($veiculoAtivo['id_veiculo'])) {
            Response::redirect('/pages/locacoes');
            return;
        }

        $html = Template::render('pages.locacoes.substituir', [
            'locacao' => $locacao,
            'veiculoAtivo' => $veiculoAtivo,
        ]);
        Response::html($html);
    }

    /**
     * Lista locacoes do tenant (paginado com busca)
     *
     * GET /api/locacoes
     * Query params: page, perPage, search, status
     */
    public function index(Request $request): void
    {
        try {
            $chave = Auth::chave();

            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');
            $status = $request->query('status', '');

            [$filialWhere, $filialParams] = FilialHelper::whereLocacoes('l');

            $locacaoModel = new Locacao();

            $locacoes = $locacaoModel->listarPaginado(
                $chave,
                $page,
                $perPage,
                $search,
                $filialWhere,
                $filialParams,
                $status
            );

            $total = $locacaoModel->contar(
                $chave,
                $search,
                $filialWhere,
                $filialParams,
                $status
            );

            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $locacoes,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'totalPages' => $totalPages,
                    'hasNext' => $page < $totalPages,
                    'hasPrev' => $page > 1
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $this->apiMessage('fetch_rentals_error', ['message' => $e->getMessage()])
            ], 500);
        }
    }

    /**
     * Exibe uma locacao especifica
     *
     * GET /api/locacoes/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);

            if (!$locacao) {
                Response::json([
                    'success' => false,
                    'message' => $this->apiMessage('rental_not_found')
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($locacao['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => $this->apiMessage('rental_not_found')
                ], 404);
                return;
            }

            if (!FilialHelper::temAcessoFilial($locacao['id_matriz_filial_retirada'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => $this->apiMessage('access_denied')
                ], 403);
                return;
            }

            Response::json([
                'success' => true,
                'data' => $locacao
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $this->apiMessage('fetch_rental_error', ['message' => $e->getMessage()])
            ], 500);
        }
    }

    /**
     * Cria uma nova locacao
     *
     * POST /locacoes/salvar
     * Orquestra: locacao -> veiculo (locacoes_veiculos) -> taxas (locacoes_taxaseservicos)
     */
    public function store(Request $request): void
    {
        try {
            $dados = $request->all();
            $chave = Auth::chave();
            $dados['chave'] = $chave;
            $dados['id_funcionario'] = Auth::id();

            if (array_key_exists('odometro_ini', $dados)) {
                $dados['odometro_ini'] = $this->normalizarOdometro($dados['odometro_ini']);
            }

            // Validacao basica
            if (empty($dados['id_cliente'])) {
                Response::json([
                    'success' => false,
                    'message' => $this->apiMessage('client_required')
                ], 400);
                return;
            }

            if (empty($dados['data_saida']) || empty($dados['data_prevista'])) {
                Response::json([
                    'success' => false,
                    'message' => $this->apiMessage('dates_required')
                ], 400);
                return;
            }

            if (empty($dados['id_matriz_filial_retirada'])) {
                Response::json(['success' => false, 'message' => $this->apiMessage('pickup_location_required')], 400);
                return;
            }

            if (empty($dados['id_matriz_filial_devolucao'])) {
                Response::json(['success' => false, 'message' => $this->apiMessage('return_location_required')], 400);
                return;
            }

            if (empty($dados['id_conta'])) {
                Response::json(['success' => false, 'message' => $this->apiMessage('bank_account_required')], 400);
                return;
            }

            if (empty($dados['id_forma_pagamento'])) {
                Response::json(['success' => false, 'message' => $this->apiMessage('payment_method_required')], 400);
                return;
            }

            $statusSolicitado = $dados['status'] ?? 'R';
            if (!in_array($statusSolicitado, ['R', 'P'], true) && empty($dados['id_veiculo'])) {
                Response::json(['success' => false, 'message' => $this->apiMessage('vehicle_required_open_closed')], 400);
                return;
            }

            $erroCaucao = $this->validarCaucaoLocacao($dados);
            if ($erroCaucao !== null) {
                Response::json(['success' => false, 'message' => $erroCaucao], 400);
                return;
            }

            $locacaoModel = new Locacao();

            // Processar arrays JSON (intervenientes)
            if (!empty($dados['condutor_adicional']) && is_array($dados['condutor_adicional'])) {
                $dados['condutor_adicional'] = json_encode($dados['condutor_adicional']);
            }
            if (!empty($dados['array_fiadores']) && is_array($dados['array_fiadores'])) {
                $dados['array_fiadores'] = json_encode($dados['array_fiadores']);
            }
            if (!empty($dados['array_avalistas']) && is_array($dados['array_avalistas'])) {
                $dados['array_avalistas'] = json_encode($dados['array_avalistas']);
            }
            if (!empty($dados['array_testemunhas']) && is_array($dados['array_testemunhas'])) {
                $dados['array_testemunhas'] = json_encode($dados['array_testemunhas']);
            }

            // 1. Criar locacao (sem dados de veiculo/taxa)
            $id = $locacaoModel->criarComAuditoria($dados);
            $locacaoCriadaParaCaucao = $locacaoModel->buscarPorId($id);
            if ($locacaoCriadaParaCaucao) {
                (new LocacaoCaucao())->sincronizarAtual($id, $dados, $locacaoCriadaParaCaucao);
            }

            // 2. Adicionar veiculo em locacoes_veiculos
            //    - Locacao (status A/F): exige id_veiculo.
            //    - Reserva (status R): aceita só id_grupo (id_veiculo NULL).
            $statusLoc = $dados['status'] ?? 'R';
            $temVeiculo = !empty($dados['id_veiculo']);
            $temGrupo = !empty($dados['id_grupo']);
            if ($temVeiculo || (in_array($statusLoc, ['R', 'P'], true) && $temGrupo)) {
                $veiculoModel = new LocacaoVeiculo();

                // Mapear diaria_valor para o campo correto conforme o plano
                $plano = $dados['plano'] ?? 'KL';
                $valoresPlano = $this->mapearValoresPlanoVeiculo($dados);

                $veiculoModel->adicionar([
                    'chave' => $chave,
                    'id_locacao' => $id,
                    'id_veiculo' => $temVeiculo ? (int) $dados['id_veiculo'] : null,
                    'id_grupo' => $temGrupo ? (int) $dados['id_grupo'] : null,
                    'data_saida' => $dados['data_saida'],
                    'plano' => $plano,
                    'valor_plano_km_pago' => $valoresPlano['valor_plano_km_pago'],
                    'valor_plano_km_livre' => $valoresPlano['valor_plano_km_livre'],
                    'valor_plano_km_controlado' => $valoresPlano['valor_plano_km_controlado'],
                    'km_franquia' => $dados['km_controlado_franquia'] ?? null,
                    'valor_km_excedente' => $dados['km_valor'] ?? 0,
                    'minutos_tolerancia' => $dados['minuto_tolerancia'] ?? 0,
                    'valor_tolerancia' => $dados['valor_tolerancia'] ?? 0,
                    'valor_km_retorno' => $dados['valor_km_retorno'] ?? 0,
                    'valor_condutor_adicional' => $dados['valor_condutor_adicional'] ?? 0,
                    'seguro_carro' => ($dados['seguro_carro'] ?? 'N') === 'S' ? 1 : 0,
                    'valor_seguro_carro' => $dados['seguro_carro_valor'] ?? 0,
                    'cobertura_carro' => $dados['cobertura_carro_valor'] ?? 0,
                    'seguro_terceiros' => ($dados['seguro_terceiros'] ?? 'N') === 'S' ? 1 : 0,
                    'valor_seguro_terceiros' => $dados['seguro_terceiros_valor'] ?? 0,
                    'cobertura_terceiros' => $dados['cobertura_terceiros_valor'] ?? 0,
                    'odometro_saida' => $dados['odometro_ini'] ?? 0,
                    'combustivel_saida' => $dados['combustivel_ini'] ?? null,
                ]);

                if ($statusLoc === 'A' && $temVeiculo) {
                    (new VeiculoDisponibilidadeSync())->marcarLocado((int) $dados['id_veiculo']);
                }
            }

            // 3. Sincronizar taxas em locacoes_taxaseservicos
            $taxas = !empty($dados['taxas'])
                ? (is_string($dados['taxas']) ? json_decode($dados['taxas'], true) : $dados['taxas'])
                : [];

            // Se nao ha taxas enviadas, buscar taxas com aplicar='S' e onde_usar='SIS'
            if (empty($taxas) || !is_array($taxas)) {
                $filialId = $dados['id_matriz_filial_retirada'] ?? null;
                $taxaServicoModel = new TaxaServico();
                $taxasAuto = $taxaServicoModel->listarAutoAplicar($chave, $filialId ? (int) $filialId : null);

                $taxas = array_map(fn($t) => [
                    'id_taxa' => $t['id'],
                    'nome' => $t['nome'],
                    'base_calculo' => $t['base_calculo'],
                    'tipo_valor' => $t['tipo_valor'],
                    'quantidade' => 1,
                    'valor_unitario' => $t['valor'],
                ], $taxasAuto);
            }

            if (is_array($taxas) && !empty($taxas)) {
                $taxaModel = new LocacaoTaxaServico();
                $taxaModel->sincronizar($id, $taxas, $chave);
            }

            // 4. Recalcular taxas e totais com a mesma regra do Resumo da Locacao
            $dadosTotais = $dados;
            if (is_array($taxas)) {
                $dadosTotais['taxas'] = $taxas;
            }
            $locacaoModel->sincronizarTotaisResumo($id, $dadosTotais, is_array($taxas));

            // Disparar mensageria para o cliente (rental_confirmation)
            try {
                $locacaoCriada = $locacaoModel->buscarPorId($id);
                $clienteModel = new Cliente();
                $cliente = $clienteModel->buscarPorIdComContatos((int) $dados['id_cliente']);
                $filialModel = new MatrizFilial();
                $empresa = $filialModel->buscarPorId((int) ($dados['id_matriz_filial_retirada'] ?? $_SESSION['id_matriz_filial'] ?? 0));

                $veiculoDados = null;
                if (!empty($dados['id_veiculo'])) {
                    $veiculoMsgModel = new Veiculo();
                    $veiculoDados = $veiculoMsgModel->buscarPorId((int) $dados['id_veiculo']);
                }

                if ($cliente && $empresa && $locacaoCriada) {
                    $context = [
                        'cliente' => $cliente,
                        'empresa' => $empresa,
                        'id_matriz_filial' => (int) ($dados['id_matriz_filial_retirada'] ?? $_SESSION['id_matriz_filial'] ?? 0),
                        'locacao' => [
                            'numero'          => $locacaoCriada['codigo'],
                            'data_retirada'   => $locacaoCriada['data_saida'],
                            'hora_retirada'   => $locacaoCriada['hora_saida'] ?? '',
                            'local_retirada'  => $locacaoCriada['filial_retirada_nome'] ?? '',
                            'data_devolucao'  => $locacaoCriada['data_prevista'],
                            'hora_devolucao'  => $locacaoCriada['hora_devolucao'] ?? '',
                            'local_devolucao' => $locacaoCriada['filial_devolucao_nome'] ?? '',
                            'valor_total'     => $locacaoCriada['valor_total'] ?? 0,
                            'quantidade_dias' => $locacaoCriada['quantidade_dias'] ?? 0,
                        ],
                        'veiculo' => $veiculoDados ?? [],
                    ];

                    foreach (['email', 'whatsapp', 'sms'] as $canal) {
                        try {
                            queue_template_message('rental_confirmation', $canal, $context);
                        } catch (\Throwable $e) {
                            error_log("Erro ao enfileirar rental_confirmation/{$canal}: " . $e->getMessage());
                        }
                    }
                }
            } catch (\Throwable $e) {
                error_log('Erro ao enviar notificacao de locacao: ' . $e->getMessage());
            }

            Response::json([
                'success' => true,
                'message' => $this->apiMessage('created'),
                'data' => ['id' => $id]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $this->apiMessage('create_error', ['message' => $e->getMessage()])
            ], 500);
        }
    }

    /**
     * Atualiza uma locacao
     *
     * POST /locacoes/{id}/atualizar
     * Orquestra: locacao -> veiculo (locacoes_veiculos) -> taxas (locacoes_taxaseservicos)
     */
    public function update(Request $request, int $id): void
    {
        try {
            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);

            if (!$locacao) {
                Response::json([
                    'success' => false,
                    'message' => $this->apiMessage('rental_not_found')
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($locacao['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => $this->apiMessage('cannot_edit')
                ], 403);
                return;
            }

            if (!FilialHelper::temAcessoFilial($locacao['id_matriz_filial_retirada'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => $this->apiMessage('access_denied')
                ], 403);
                return;
            }

            $dados = $request->all();

            // Validacao basica
            if (empty($dados['id_cliente'])) {
                Response::json(['success' => false, 'message' => $this->apiMessage('client_required')], 400);
                return;
            }

            $statusAnteriorValidacao = $locacao['status'] ?? 'R';
            $statusNovoValidacao = $dados['status'] ?? $statusAnteriorValidacao;
            $fechandoLocacao = $statusAnteriorValidacao === 'A' && $statusNovoValidacao === 'F';
            if ($fechandoLocacao) {
                $dados['data_chegada'] = !empty($dados['data_chegada'])
                    ? (string) $dados['data_chegada']
                    : date('Y-m-d H:i:s');
                unset($dados['data_prevista']);

                if (!empty($dados['data_saida'])) {
                    $minutosTolerancia = (int) (
                        $dados['minuto_tolerancia']
                        ?? $locacao['minuto_tolerancia']
                        ?? $locacao['minutos_tolerancia']
                        ?? 0
                    );
                    $dados['dias'] = $this->calcularDiasComTolerancia(
                        (string) $dados['data_saida'],
                        (string) $dados['data_chegada'],
                        $minutosTolerancia
                    );
                }
            }
            $dataPrincipalEhChegada = $statusNovoValidacao === 'F';

            if (empty($dados['data_saida']) || (!$dataPrincipalEhChegada && empty($dados['data_prevista'])) || ($dataPrincipalEhChegada && empty($dados['data_chegada']))) {
                Response::json(['success' => false, 'message' => $dataPrincipalEhChegada ? $this->apiMessage('dates_arrival_required') : $this->apiMessage('dates_required')], 400);
                return;
            }

            if (empty($dados['id_matriz_filial_retirada'])) {
                Response::json(['success' => false, 'message' => $this->apiMessage('pickup_location_required')], 400);
                return;
            }

            if (empty($dados['id_matriz_filial_devolucao'])) {
                Response::json(['success' => false, 'message' => $this->apiMessage('return_location_required')], 400);
                return;
            }

            if (empty($dados['id_conta'])) {
                Response::json(['success' => false, 'message' => $this->apiMessage('bank_account_required')], 400);
                return;
            }

            if (empty($dados['id_forma_pagamento'])) {
                Response::json(['success' => false, 'message' => $this->apiMessage('payment_method_required')], 400);
                return;
            }

            $erroCaucao = $this->validarCaucaoLocacao($dados);
            if ($erroCaucao !== null) {
                Response::json(['success' => false, 'message' => $erroCaucao], 400);
                return;
            }

            // Processar arrays JSON (intervenientes)
            if (isset($dados['condutor_adicional']) && is_array($dados['condutor_adicional'])) {
                $dados['condutor_adicional'] = json_encode($dados['condutor_adicional']);
            }
            if (isset($dados['array_fiadores']) && is_array($dados['array_fiadores'])) {
                $dados['array_fiadores'] = json_encode($dados['array_fiadores']);
            }
            if (isset($dados['array_avalistas']) && is_array($dados['array_avalistas'])) {
                $dados['array_avalistas'] = json_encode($dados['array_avalistas']);
            }
            if (isset($dados['array_testemunhas']) && is_array($dados['array_testemunhas'])) {
                $dados['array_testemunhas'] = json_encode($dados['array_testemunhas']);
            }

            // Extrair dados de auditoria
            $auditChanges = $dados['_audit_changes'] ?? null;
            unset($dados['_audit_data'], $dados['_audit_changes'], $dados['_audit_initial']);

            $statusAnterior = $locacao['status'] ?? 'R';
            $statusNovo = $dados['status'] ?? $statusAnterior;
            $transicaoOperacional = (
                ($statusAnterior === 'R' && $statusNovo === 'A') ||
                ($statusAnterior === 'A' && $statusNovo === 'F')
            );

            if (
                in_array($statusAnterior, ['A', 'F'], true) &&
                array_key_exists('id_veiculo', $dados) &&
                !empty($dados['id_veiculo']) &&
                !empty($locacao['id_veiculo']) &&
                (int) $dados['id_veiculo'] !== (int) $locacao['id_veiculo']
            ) {
                Response::json([
                    'success' => false,
                    'message' => $this->apiMessage(
                        $statusAnterior === 'A'
                            ? 'vehicle_change_use_substitution'
                            : 'vehicle_change_closed_blocked'
                    )
                ], 422);
                return;
            }

            if (!in_array($statusNovo, ['R', 'P'], true) && empty($dados['id_veiculo']) && empty($locacao['id_veiculo'])) {
                Response::json(['success' => false, 'message' => $this->apiMessage('vehicle_required_open_closed')], 400);
                return;
            }

            if ($statusAnterior === 'R' && $statusNovo === 'A') {
                $idVeiculoSaida = !empty($dados['id_veiculo'])
                    ? (int) $dados['id_veiculo']
                    : (int) ($locacao['id_veiculo'] ?? 0);

                if ($idVeiculoSaida <= 0) {
                    Response::json(['success' => false, 'message' => $this->apiMessage('vehicle_required_open_closed')], 400);
                    return;
                }

                $this->validarVeiculoDisponivelParaSaida($idVeiculoSaida, $chave);
            }

            if (array_key_exists('odometro_ini', $dados)) {
                $dados['odometro_ini'] = $this->normalizarOdometro($dados['odometro_ini']);
            }
            if (array_key_exists('odometro_fim', $dados)) {
                $dados['odometro_fim'] = $this->normalizarOdometro($dados['odometro_fim'], true);
            }

            $dadosLocacao = $dados;
            if ($transicaoOperacional) {
                unset($dadosLocacao['status']);
            }

            // 1. Atualizar locacao (sem dados de veiculo/taxa)
            $locacaoModel->atualizar($id, $dadosLocacao);
            $locacaoAtualizadaParaCaucao = $locacaoModel->buscarPorId($id);
            if ($locacaoAtualizadaParaCaucao) {
                (new LocacaoCaucao())->sincronizarAtual($id, $dados, $locacaoAtualizadaParaCaucao);
            }

            // 2. Atualizar veiculo ativo em locacoes_veiculos
            $idLocacaoVeiculo = $locacao['_id_locacao_veiculo'] ?? null;
            $veiculoModel = new LocacaoVeiculo();

            // Mapear diaria_valor para o campo correto conforme o plano
            $valoresPlanoUpdate = $this->mapearValoresPlanoVeiculo($dados, $locacao);

            $dadosVeiculo = [
                'id_grupo' => !empty($dados['id_grupo']) ? (int) $dados['id_grupo'] : null,
                'plano' => $dados['plano'] ?? null,
                'valor_plano_km_pago' => $valoresPlanoUpdate['valor_plano_km_pago'],
                'valor_plano_km_livre' => $valoresPlanoUpdate['valor_plano_km_livre'],
                'valor_plano_km_controlado' => $valoresPlanoUpdate['valor_plano_km_controlado'],
                'km_franquia' => $dados['km_controlado_franquia'] ?? null,
                'valor_km_excedente' => $dados['km_valor'] ?? null,
                'minutos_tolerancia' => $dados['minuto_tolerancia'] ?? null,
                'valor_tolerancia' => $dados['valor_tolerancia'] ?? null,
                'valor_km_retorno' => $dados['valor_km_retorno'] ?? null,
                'valor_condutor_adicional' => $dados['valor_condutor_adicional'] ?? null,
                'odometro_saida' => $dados['odometro_ini'] ?? null,
                'combustivel_saida' => $dados['combustivel_ini'] ?? null,
            ];

            if (isset($dados['seguro_carro'])) {
                $dadosVeiculo['seguro_carro'] = $dados['seguro_carro'] === 'S' ? 1 : 0;
            }
            if (isset($dados['seguro_carro_valor'])) {
                $dadosVeiculo['valor_seguro_carro'] = $dados['seguro_carro_valor'];
            }
            if (isset($dados['cobertura_carro_valor'])) {
                $dadosVeiculo['cobertura_carro'] = $dados['cobertura_carro_valor'];
            }
            if (isset($dados['seguro_terceiros'])) {
                $dadosVeiculo['seguro_terceiros'] = $dados['seguro_terceiros'] === 'S' ? 1 : 0;
            }
            if (isset($dados['seguro_terceiros_valor'])) {
                $dadosVeiculo['valor_seguro_terceiros'] = $dados['seguro_terceiros_valor'];
            }
            if (isset($dados['cobertura_terceiros_valor'])) {
                $dadosVeiculo['cobertura_terceiros'] = $dados['cobertura_terceiros_valor'];
            }

            // Remover nulls (so atualizar campos enviados)
            $dadosVeiculo = array_filter($dadosVeiculo, fn($v) => $v !== null);

            if (!empty($dados['id_veiculo'])) {
                if ($idLocacaoVeiculo && in_array($statusAnterior, ['R', 'P'], true)) {
                    // Em reserva, veiculo especifico e apenas preferencia: atualiza o mesmo registro.
                    $veiculoModel->atualizar($idLocacaoVeiculo, array_merge($dadosVeiculo, [
                        'id_veiculo' => (int) $dados['id_veiculo'],
                        'data_saida' => $dados['data_saida'] ?? $locacao['data_saida'],
                    ]));
                } elseif ($idLocacaoVeiculo && (int) $dados['id_veiculo'] === (int) $locacao['id_veiculo']) {
                    // Mesmo veiculo - atualizar registro existente
                    $veiculoModel->atualizar($idLocacaoVeiculo, $dadosVeiculo);
                } elseif ($idLocacaoVeiculo) {
                    // Veiculo mudou - substituir
                    $idVeiculoAntigo = (int) ($locacao['id_veiculo'] ?? 0);
                    $veiculoModel->substituir($idLocacaoVeiculo, [
                        'motivo_saida' => 'Alteração de veículo na edição',
                    ], array_merge($dadosVeiculo, [
                        'id_veiculo' => (int) $dados['id_veiculo'],
                        'data_saida' => $dados['data_saida'] ?? $locacao['data_saida'],
                    ]), false);
                    $disponibilidadeSync = new VeiculoDisponibilidadeSync();
                    if ($idVeiculoAntigo > 0) {
                        $disponibilidadeSync->liberarSeSemVinculoAtivo($idVeiculoAntigo, 'D');
                    }
                    if ($statusNovo === 'A') {
                        $disponibilidadeSync->marcarLocado((int) $dados['id_veiculo']);
                    }
                } else {
                    // Sem veiculo anterior - adicionar novo
                    $veiculoModel->adicionar(array_merge($dadosVeiculo, [
                        'chave' => $chave,
                        'id_locacao' => $id,
                        'id_veiculo' => (int) $dados['id_veiculo'],
                        'data_saida' => $dados['data_saida'] ?? $locacao['data_saida'],
                    ]));
                    if ($statusNovo === 'A') {
                        (new VeiculoDisponibilidadeSync())->marcarLocado((int) $dados['id_veiculo']);
                    }
                }
            } elseif (in_array($statusNovo, ['R', 'P'], true) && !empty($dados['id_grupo'])) {
                $dadosGrupoReserva = array_merge($dadosVeiculo, [
                    'id_veiculo' => null,
                    'id_grupo' => (int) $dados['id_grupo'],
                ]);

                if ($idLocacaoVeiculo) {
                    $veiculoModel->atualizar($idLocacaoVeiculo, $dadosGrupoReserva);
                } else {
                    $veiculoModel->adicionar(array_merge($dadosGrupoReserva, [
                        'chave' => $chave,
                        'id_locacao' => $id,
                        'data_saida' => $dados['data_saida'] ?? $locacao['data_saida'],
                    ]));
                }
            }

            if ($statusNovo === 'A' && !empty($dados['id_veiculo'])) {
                (new VeiculoDisponibilidadeSync())->marcarLocado((int) $dados['id_veiculo']);
            }

            // 3. Sincronizar taxas em locacoes_taxaseservicos
            if (isset($dados['taxas'])) {
                $taxas = is_string($dados['taxas']) ? json_decode($dados['taxas'], true) : $dados['taxas'];
                if (is_array($taxas)) {
                    $taxaModel = new LocacaoTaxaServico();
                    $taxaModel->sincronizar($id, $taxas, $chave);
                }
            }

            // 4. Recalcular taxas e totais com valores corretos
            $totaisUpdate = $this->calcularTotaisLocacao($id, $locacao, $dados, false);
            $totalFaturaUpdate = $totaisUpdate['total_fatura'];
            $totalPagarUpdate = $totaisUpdate['total_pagar'];

            if (!($statusAnterior === 'A' && $statusNovo === 'F')) {
                $locacaoModel->atualizar($id, [
                    'total_fatura' => $totalFaturaUpdate,
                    'total_pagar' => $totalPagarUpdate,
                ]);
            }

            // 5. Transicoes de status
            if ($statusAnterior === 'R' && $statusNovo === 'A') {
                // R -> A: Registrar saida (veiculo sai da empresa)
                if (!Auth::can('locacoes.saida')) {
                    Response::json(['success' => false, 'message' => $this->apiMessage('no_permission_checkout')], 403);
                    return;
                }

                $locacaoModel->registrarSaida($id, [
                    'data_saida' => $dados['data_saida'] ?? date('Y-m-d H:i:s'),
                    'odometro_ini' => $dados['odometro_ini'] ?? 0,
                    'combustivel_ini' => $dados['combustivel_ini'] ?? 0,
                ]);

                AuditLogService::registrar(
                    ($_SESSION['user_name'] ?? 'Sistema') . ", registrou saida da locacao [{$locacao['codigo']}]"
                );
            } elseif ($statusAnterior === 'A' && $statusNovo === 'F') {
                // A -> F: Registrar devolucao (veiculo volta a empresa)
                if (!Auth::can('locacoes.devolucao')) {
                    Response::json(['success' => false, 'message' => $this->apiMessage('no_permission_return')], 403);
                    return;
                }

                if (
                    empty($dados['data_chegada']) ||
                    empty($dados['odometro_fim']) ||
                    !array_key_exists('combustivel_fim', $dados) ||
                    $dados['combustivel_fim'] === ''
                ) {
                    Response::json([
                        'success' => false,
                        'message' => $this->apiMessage('return_fields_required')
                    ], 400);
                    return;
                }

                $odometroSaida = (int) ($dados['odometro_ini'] ?? $locacao['odometro_ini'] ?? 0);
                $odometroDevolucao = (int) ($dados['odometro_fim'] ?? 0);
                if ($odometroDevolucao < $odometroSaida) {
                    Response::json([
                        'success' => false,
                        'message' => $this->apiMessage('return_odometer_invalid')
                    ], 400);
                    return;
                }

                $resumoFinanceiro = $locacaoModel->resumoFinanceiro($id);
                $totalParcelasFinanceiro = (int) ($resumoFinanceiro['total_parcelas'] ?? 0);
                $totalLancadoFinanceiro = (float) ($resumoFinanceiro['total_lancado'] ?? 0);
                $diferencaFinanceira = round($totalPagarUpdate - $totalLancadoFinanceiro, 2);
                $valorCreditoDevolucao = $diferencaFinanceira < -0.009 ? abs($diferencaFinanceira) : 0.0;

                if ($totalParcelasFinanceiro <= 0) {
                    Response::json([
                        'success' => false,
                        'message' => $this->apiMessage('financial_installments_required')
                    ], 400);
                    return;
                }

                if ($diferencaFinanceira > 0.009) {
                    Response::json([
                        'success' => false,
                        'message' => $this->apiMessage('financial_installments_total_mismatch')
                    ], 400);
                    return;
                }

                if ($valorCreditoDevolucao > 0 && empty($dados['gerar_credito_devolucao'])) {
                    Response::json([
                        'success' => false,
                        'code' => 'return_refund_required',
                        'message' => $this->apiMessage('return_refund_required'),
                        'data' => [
                            'valor_credito_devolucao' => $valorCreditoDevolucao,
                            'total_lancado' => $totalLancadoFinanceiro,
                            'total_pagar' => $totalPagarUpdate,
                        ],
                    ], 409);
                    return;
                }

                $dadosDevolucao = [
                    'data_chegada' => $dados['data_chegada'] ?? date('Y-m-d H:i:s'),
                    'odometro_fim' => $dados['odometro_fim'] ?? 0,
                    'combustivel_fim' => $dados['combustivel_fim'] ?? null,
                    'combustivel_valor' => $dados['combustivel_valor'] ?? null,
                    'dias' => $dados['dias'] ?? $locacao['dias'] ?? 1,
                    'id_matriz_filial_devolucao' => $dados['id_matriz_filial_devolucao'] ?? null,
                    'total_fatura' => $totalFaturaUpdate,
                    'total_pagar' => $totalPagarUpdate,
                    'obs' => $dados['obs'] ?? null,
                ];

                $locacaoModel->registrarDevolucaoComCredito($id, $dadosDevolucao, $valorCreditoDevolucao, $chave);

                AuditLogService::registrar(
                    ($_SESSION['user_name'] ?? 'Sistema') . ", registrou devolucao da locacao [{$locacao['codigo']}]"
                );

                if ($valorCreditoDevolucao > 0) {
                    AuditLogService::registrar(
                        ($_SESSION['user_name'] ?? 'Sistema') . ", gerou credito de devolucao de R$ " . number_format($valorCreditoDevolucao, 2, ',', '.') . " para locacao [{$locacao['codigo']}]"
                    );
                }
            }

            // Log de auditoria com campos alterados
            if ($auditChanges) {
                $decoded = json_decode($auditChanges, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $decoded = json_decode(stripslashes($auditChanges), true);
                }
                if (is_array($decoded) && !empty($decoded)) {
                    AuditLogService::registrarComCampos(
                        ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou a locacao [{$locacao['codigo']}]",
                        $decoded
                    );
                }
            }

            Response::json([
                'success' => true,
                'message' => $this->apiMessage('updated')
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $this->apiMessage('update_error', ['message' => $e->getMessage()])
            ], 500);
        }
    }

    /**
     * Substitui o veiculo de uma locacao aberta.
     *
     * POST /locacoes/{id}/substituir
     */
    public function substituir(Request $request, int $id): void
    {
        try {
            if (!Auth::can('locacoes.substituir')) {
                Response::json([
                    'success' => false,
                    'message' => $this->apiMessage('no_permission_substitute')
                ], 403);
                return;
            }

            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);

            if (!$locacao) {
                Response::json(['success' => false, 'message' => $this->apiMessage('rental_not_found')], 404);
                return;
            }

            $chave = Auth::chave();
            if (($locacao['chave'] ?? '') !== $chave) {
                Response::json(['success' => false, 'message' => $this->apiMessage('access_denied')], 403);
                return;
            }

            if (!FilialHelper::temAcessoFilial($locacao['id_matriz_filial_retirada'] ?? null)) {
                Response::json(['success' => false, 'message' => $this->apiMessage('access_denied')], 403);
                return;
            }

            if (($locacao['status'] ?? '') !== 'A') {
                Response::json(['success' => false, 'message' => $this->apiMessage('substitution_only_open')], 422);
                return;
            }

            $dados = $request->all();

            if (empty($dados['id_locacao_veiculo_antigo'])) {
                Response::json(['success' => false, 'message' => $this->apiMessage('vehicle_to_replace_required')], 400);
                return;
            }

            if (empty($dados['id_veiculo_novo'])) {
                Response::json(['success' => false, 'message' => $this->apiMessage('new_vehicle_required')], 400);
                return;
            }

            $locacaoVeiculoModel = new LocacaoVeiculo();
            $veiculoAntigo = $locacaoVeiculoModel->buscarPorId((int) $dados['id_locacao_veiculo_antigo']);

            if (
                !$veiculoAntigo ||
                (int) ($veiculoAntigo['id_locacao'] ?? 0) !== $id ||
                !empty($veiculoAntigo['data_entrada'])
            ) {
                Response::json(['success' => false, 'message' => $this->apiMessage('vehicle_not_in_rental')], 400);
                return;
            }

            $idVeiculoNovo = (int) $dados['id_veiculo_novo'];
            if ((int) ($veiculoAntigo['id_veiculo'] ?? 0) === $idVeiculoNovo) {
                Response::json(['success' => false, 'message' => $this->apiMessage('new_vehicle_must_differ')], 400);
                return;
            }

            $veiculoModel = new Veiculo();
            $novoVeiculo = $veiculoModel->buscarPorId($idVeiculoNovo);
            if (!$novoVeiculo) {
                Response::json(['success' => false, 'message' => $this->apiMessage('vehicle_not_found')], 404);
                return;
            }

            if (!FilialHelper::temAcessoFilial($novoVeiculo['id_matriz_filial_localizacao'] ?? $novoVeiculo['id_matriz_filial'] ?? null)) {
                Response::json(['success' => false, 'message' => $this->apiMessage('vehicle_access_denied')], 403);
                return;
            }

            $locacaoConflitante = $locacaoVeiculoModel->veiculoEstaLocado($idVeiculoNovo, $id);
            if ($locacaoConflitante) {
                Response::json([
                    'success' => false,
                    'message' => $this->apiMessage('new_vehicle_already_rented', [
                        'code' => $locacaoConflitante['locacao_codigo'] ?? '-'
                    ])
                ], 400);
                return;
            }

            $disponibilidadeSync = new VeiculoDisponibilidadeSync();
            if ($disponibilidadeSync->possuiVinculoAtivo($idVeiculoNovo, $chave)) {
                Response::json(['success' => false, 'message' => $this->apiMessage('new_vehicle_active_link')], 400);
                return;
            }

            $odometroEntradaAntigo = $this->normalizarOdometro($dados['odometro_entrada'] ?? 0);
            $odometroMinimoAntigo = (int) ($veiculoAntigo['odometro_saida'] ?? 0);
            if ($odometroEntradaAntigo > 0 && $odometroEntradaAntigo < $odometroMinimoAntigo) {
                Response::json(['success' => false, 'message' => $this->apiMessage('return_odometer_invalid')], 422);
                return;
            }

            $dataSubstituicao = $this->normalizarDataSubstituicao($dados['data_entrada'] ?? null);
            if ($dataSubstituicao === null) {
                Response::json(['success' => false, 'message' => 'Data da substituição inválida'], 400);
                return;
            }

            $dataSaidaAntigo = $this->normalizarDataSubstituicao($veiculoAntigo['data_saida'] ?? null);
            if ($dataSaidaAntigo !== null && strtotime($dataSubstituicao) < strtotime($dataSaidaAntigo)) {
                Response::json(['success' => false, 'message' => 'Data da substituição não pode ser anterior à saída do veículo atual'], 422);
                return;
            }

            $dadosSaida = [
                'data_entrada' => $dataSubstituicao,
                'odometro_entrada' => $odometroEntradaAntigo,
                'combustivel_entrada' => $dados['combustivel_entrada'] ?? null,
                'motivo_saida' => $dados['motivo_saida'] ?? $this->apiMessage('substitution_default_reason'),
            ];

            $dadosNovo = [
                'id_veiculo' => $idVeiculoNovo,
                'id_grupo' => $dados['id_grupo_novo'] ?? ($novoVeiculo['id_grupo'] ?? null),
                'data_saida' => $dataSubstituicao,
                'odometro_saida' => $this->normalizarOdometro($dados['odometro_saida_novo'] ?? 0),
                'combustivel_saida' => $dados['combustivel_saida_novo'] ?? null,
                'plano' => $dados['plano_novo'] ?? $veiculoAntigo['plano'] ?? 'KL',
            ];

            $camposValores = [
                'valor_plano_km_pago', 'valor_plano_km_controlado', 'valor_plano_km_livre',
                'km_franquia', 'valor_km_excedente', 'minutos_tolerancia', 'valor_tolerancia',
                'valor_km_retorno', 'valor_condutor_adicional',
                'seguro_carro', 'valor_seguro_carro', 'cobertura_carro',
                'seguro_terceiros', 'valor_seguro_terceiros', 'cobertura_terceiros',
            ];

            foreach ($camposValores as $campo) {
                if (array_key_exists($campo, $dados)) {
                    $dadosNovo[$campo] = $dados[$campo];
                }
            }

            $manterValores = !empty($dados['manter_valores']);
            $novoId = $locacaoVeiculoModel->substituir(
                (int) $dados['id_locacao_veiculo_antigo'],
                $dadosSaida,
                $dadosNovo,
                $manterValores
            );

            if (!empty($veiculoAntigo['id_veiculo'])) {
                $veiculoModel->atualizarOdometro((int) $veiculoAntigo['id_veiculo'], $odometroEntradaAntigo);
                $disponibilidadeSync->liberarSeSemVinculoAtivo((int) $veiculoAntigo['id_veiculo'], 'D', $chave);
            }
            $disponibilidadeSync->marcarLocado($idVeiculoNovo, $chave);

            $antigoDescricao = trim(($veiculoAntigo['veiculo_placa'] ?? '') . ' - ' . ($veiculoAntigo['veiculo_marca'] ?? '') . ' ' . ($veiculoAntigo['veiculo_modelo'] ?? ''));
            $novoDescricao = trim(($novoVeiculo['placa'] ?? '') . ' - ' . ($novoVeiculo['marca'] ?? '') . ' ' . ($novoVeiculo['modelo'] ?? ''));

            AuditLogService::registrarComCampos(
                ($_SESSION['user_name'] ?? 'Sistema') . ", substituiu veiculo na locacao [{$locacao['codigo']}]",
                [
                    AuditLogService::campo('Veículo', $antigoDescricao ?: '-', $novoDescricao ?: '-', 'Substituição'),
                    AuditLogService::campo('Motivo', null, $dadosSaida['motivo_saida'] ?: '-', 'Substituição'),
                    AuditLogService::campo('Odômetro Entrada', null, $odometroEntradaAntigo ?: '-', 'Substituição'),
                    AuditLogService::campo('Ação Valores', null, $manterValores ? 'Manter valores atuais' : 'Usar valores do novo grupo', 'Substituição'),
                ]
            );

            Response::json([
                'success' => true,
                'message' => $this->apiMessage('substitution_success'),
                'data' => ['id_locacao_veiculo' => $novoId]
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $this->apiMessage('substitution_error', ['message' => $e->getMessage()])
            ], 500);
        }
    }

    /**
     * Exclui uma locacao
     *
     * POST /locacoes/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);

            if (!$locacao) {
                Response::json([
                    'success' => false,
                    'message' => $this->apiMessage('rental_not_found')
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($locacao['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => $this->apiMessage('cannot_delete')
                ], 403);
                return;
            }

            if (!FilialHelper::temAcessoFilial($locacao['id_matriz_filial_retirada'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => $this->apiMessage('access_denied')
                ], 403);
                return;
            }

            $locacaoModel->deletarComAuditoria($id);

            Response::json([
                'success' => true,
                'message' => $this->apiMessage('deleted')
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $this->apiMessage('delete_error', ['message' => $e->getMessage()])
            ], 500);
        }
    }

    /**
     * Confirma um pedido de reserva (status P -> R) vindo do site publico.
     * Dispara o template confirmacao_reserva (email + whatsapp + sms) ao cliente.
     *
     * POST /api/locacoes/{id}/confirmar-reserva
     */
    public function confirmarReserva(Request $request, int $id): void
    {
        try {
            if (!Auth::can('locacoes.confirmar')) {
                Response::json(['success' => false, 'message' => t('common.errors.forbidden')], 403);
                return;
            }

            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);

            if (!$locacao || $locacao['chave'] !== Auth::chave()) {
                Response::json(['success' => false, 'message' => $this->apiMessage('rental_not_found')], 404);
                return;
            }
            if (($locacao['status'] ?? '') !== 'P') {
                Response::json(['success' => false, 'message' => $this->apiMessage('only_pending_reservations_confirmed')], 422);
                return;
            }
            if (!FilialHelper::temAcessoFilial($locacao['id_matriz_filial_retirada'] ?? null)) {
                Response::json(['success' => false, 'message' => $this->apiMessage('access_denied')], 403);
                return;
            }

            $locacaoModel->atualizarStatus($id, 'R');

            // Monta contexto a partir de obs JSON + dados da locacao
            $obs = json_decode((string) ($locacao['obs'] ?? ''), true) ?: [];
            $mf = new \App\Models\MatrizFilial();
            $filialRet = $mf->buscarPorId((int) ($locacao['id_matriz_filial_retirada'] ?? 0));
            $filialDev = $mf->buscarPorId((int) ($locacao['id_matriz_filial_devolucao'] ?? 0));
            $empresa = $mf->buscarMatriz();

            $primeiroNome = explode(' ', trim((string) ($locacao['cliente_nome'] ?? '')))[0];
            $context = [
                'cliente' => [
                    'nome' => $locacao['cliente_nome'] ?? '',
                    'primeiro_nome' => $primeiroNome,
                    'email' => $obs['email'] ?? '',
                    'telefone' => $obs['telefone'] ?? '',
                    'celular' => $obs['telefone'] ?? '',
                    'cpf_cnpj' => $obs['documento'] ?? '',
                ],
                'empresa' => [
                    'id' => (int) ($locacao['id_matriz_filial_retirada'] ?? 0),
                    'nome_fantasia' => $empresa['nome_fantasia'] ?? '',
                    'razao_social'  => $empresa['razao_social'] ?? '',
                    'email'         => $empresa['email'] ?? '',
                    'telefone'      => $empresa['celular'] ?? $empresa['telefone'] ?? '',
                ],
                'id_matriz_filial' => (int) ($locacao['id_matriz_filial_retirada'] ?? 0),
                'locacao' => [
                    'numero' => $locacao['codigo'] ?? '',
                    'data_retirada' => date('d/m/Y', strtotime((string) $locacao['data_saida'])),
                    'hora_retirada' => date('H:i', strtotime((string) $locacao['data_saida'])),
                    'local_retirada' => trim(($filialRet['estado'] ?? '') . ' - ' . ($filialRet['cidade'] ?? $filialRet['nome_fantasia'] ?? ''), ' -'),
                    'data_devolucao' => date('d/m/Y', strtotime((string) $locacao['data_prevista'])),
                    'hora_devolucao' => date('H:i', strtotime((string) $locacao['data_prevista'])),
                    'local_devolucao' => trim(($filialDev['estado'] ?? '') . ' - ' . ($filialDev['cidade'] ?? $filialDev['nome_fantasia'] ?? ''), ' -'),
                    'quantidade_dias' => (int) ($locacao['dias'] ?? 1),
                    'valor_total' => (float) ($locacao['total_pagar'] ?? 0),
                ],
                'outros' => ['data_atual' => date('d/m/Y')],
            ];

            if (function_exists('queue_template_message')) {
                foreach (['email', 'whatsapp', 'sms'] as $canal) {
                    try {
                        queue_template_message('confirmacao_reserva', $canal, $context, Auth::chave());
                    } catch (\Throwable $e) {
                        error_log("Erro ao enfileirar confirmacao_reserva/{$canal}: " . $e->getMessage());
                    }
                }
            }

            Response::json(['success' => true, 'message' => $this->apiMessage('reservation_confirmed')]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $this->apiMessage('reservation_confirm_error', ['message' => $e->getMessage()])], 500);
        }
    }

    /**
     * Lista veiculos de uma locacao (historico)
     *
     * GET /api/locacoes/{id}/veiculos
     */
    public function listarVeiculos(Request $request, int $id): void
    {
        try {
            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);

            if (!$locacao || $locacao['chave'] !== Auth::chave()) {
                Response::json(['success' => false, 'message' => $this->apiMessage('rental_not_found')], 404);
                return;
            }

            $veiculoModel = new LocacaoVeiculo();
            $veiculos = $veiculoModel->listarPorLocacao($id);

            Response::json(['success' => true, 'data' => $veiculos]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Lista taxas de uma locacao
     *
     * GET /api/locacoes/{id}/taxas
     */
    public function listarTaxas(Request $request, int $id): void
    {
        try {
            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);

            if (!$locacao || $locacao['chave'] !== Auth::chave()) {
                Response::json(['success' => false, 'message' => $this->apiMessage('rental_not_found')], 404);
                return;
            }

            $taxaModel = new LocacaoTaxaServico();
            $taxas = $taxaModel->listarPorLocacao($id);

            Response::json(['success' => true, 'data' => $taxas]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Limpa assinatura de uma locacao
     *
     * POST /locacoes/{id}/limpar-assinatura
     */
    public function limparAssinatura(Request $request, int $id): void
    {
        try {
            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);

            if (!$locacao) {
                Response::json([
                    'success' => false,
                    'message' => $this->apiMessage('rental_not_found')
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($locacao['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => $this->apiMessage('access_denied')
                ], 403);
                return;
            }

            // Remover assinatura vinculada a locacao (registro + arquivo)
            $assinaturaModel = new Assinatura();
            $assinaturaModel->excluirPorLocacao($id);

            Response::json([
                'success' => true,
                'message' => $this->apiMessage('signature_removed')
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $this->apiMessage('signature_clear_error', ['message' => $e->getMessage()])
            ], 500);
        }
    }

    /**
     * Busca assinatura de uma locacao
     *
     * GET /api/locacoes/{id}/assinatura
     */
    public function buscarAssinatura(Request $request, int $id): void
    {
        try {
            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);

            if (!$locacao) {
                Response::json([
                    'success' => false,
                    'message' => $this->apiMessage('rental_not_found')
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($locacao['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => $this->apiMessage('access_denied')
                ], 403);
                return;
            }

            $assinaturaModel = new Assinatura();
            $assinatura = $assinaturaModel->buscarPorLocacao($id);

            if (!$assinatura) {
                Response::json([
                    'success' => false,
                    'message' => $this->apiMessage('signature_not_found')
                ], 404);
                return;
            }

            Response::json([
                'success' => true,
                'data' => [
                    'id' => $assinatura['id'],
                    'url' => $assinatura['url'] ?? '',
                    'data_assinatura' => !empty($assinatura['created_at'])
                        ? date('d/m/Y H:i', strtotime($assinatura['created_at']))
                        : '-',
                    'ip' => $assinatura['ip_address'] ?? '-'
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $this->apiMessage('signature_fetch_error', ['message' => $e->getMessage()])
            ], 500);
        }
    }

    /**
     * Helper para query na tabela assinaturas
     */
    private function qbAssinatura()
    {
        return (new \App\Models\Model())->getQueryBuilder()
            ->table('assinaturas');
    }

    /**
     * Normaliza campos de odometro com mascara PT-BR.
     *
     * Para textos em formato de faixa ("111.111.111 - 111.111.115"),
     * a saida usa o primeiro numero e a devolucao usa o ultimo.
     */
    private function normalizarOdometro($valor, bool $preferirUltimo = false): int
    {
        if (is_int($valor)) {
            return $valor;
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return 0;
        }

        if (str_contains($texto, '-')) {
            $partes = array_values(array_filter(array_map('trim', explode('-', $texto)), fn($v) => $v !== ''));
            if (!empty($partes)) {
                $texto = $preferirUltimo ? end($partes) : reset($partes);
            }
        }

        return (int) preg_replace('/\D/', '', $texto);
    }

    private function normalizarDataSubstituicao(mixed $valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        $formatos = ['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d H:i'];

        foreach ($formatos as $formato) {
            $data = \DateTimeImmutable::createFromFormat($formato, $valor);
            if ($data instanceof \DateTimeImmutable && $data->format($formato) === $valor) {
                return $data->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    private function calcularTotaisLocacao(int $id, array $locacao, array &$dados, bool $usarTaxasEnviadas = false): array
    {
        $totais = (new Locacao())->calcularTotaisResumo($id, $dados, $usarTaxasEnviadas, true);
        $dados['combustivel_valor'] = $totais['total_combustivel'] ?? 0;

        return [
            'total_fatura' => $totais['total_fatura'],
            'total_pagar' => $totais['total_pagar'],
        ];
    }

    // ==================== PARCELAS / FINANCEIRO ====================

    /**
     * Lista parcelas de uma locacao
     *
     * GET /api/locacoes/{id}/parcelas
     */
    public function listarParcelas(Request $request, int $id): void
    {
        try {
            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);

            if (!$locacao || $locacao['chave'] !== Auth::chave()) {
                Response::json(['success' => false, 'message' => $this->apiMessage('rental_not_found')], 404);
                return;
            }

            $parcelas = $locacaoModel->listarParcelas($id);

            Response::json(['success' => true, 'data' => $parcelas]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Gera parcelas automaticas para uma locacao
     *
     * POST /api/locacoes/{id}/gerar-parcelas
     * Body: { quantidade, data_primeiro_vencimento, id_conta, id_forma_pagamento }
     */
    public function gerarParcelas(Request $request, int $id): void
    {
        try {
            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);

            if (!$locacao || $locacao['chave'] !== Auth::chave()) {
                Response::json(['success' => false, 'message' => $this->apiMessage('rental_not_found')], 404);
                return;
            }

            if (!FilialHelper::temAcessoFilial($locacao['id_matriz_filial_retirada'] ?? null)) {
                Response::json(['success' => false, 'message' => $this->apiMessage('access_denied')], 403);
                return;
            }

            $dados = $request->all();

            if (empty($dados['quantidade']) || (int) $dados['quantidade'] < 1) {
                Response::json(['success' => false, 'message' => $this->apiMessage('installments_quantity_required')], 400);
                return;
            }

            if (empty($dados['data_primeiro_vencimento'])) {
                Response::json(['success' => false, 'message' => $this->apiMessage('first_due_date_required')], 400);
                return;
            }

            if (empty($dados['id_conta'])) {
                Response::json(['success' => false, 'message' => $this->apiMessage('bank_account_required')], 400);
                return;
            }

            if (empty($dados['id_forma_pagamento'])) {
                Response::json(['success' => false, 'message' => $this->apiMessage('payment_method_required')], 400);
                return;
            }

            $totais = $this->calcularTotaisLocacao($id, $locacao, $dados, true);
            $locacaoModel->atualizar($id, [
                'total_fatura' => $totais['total_fatura'],
                'total_pagar' => $totais['total_pagar'],
            ]);
            $dados['total_pagar_final'] = $totais['total_pagar'];

            $chave = Auth::chave();
            $ids = $locacaoModel->gerarParcelas($id, $dados, $chave);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", gerou " . count($ids) . " parcela(s) para locacao [{$locacao['codigo']}]"
            );

            Response::json([
                'success' => true,
                'message' => count($ids) . ' parcela(s) gerada(s) com sucesso',
                'data' => ['ids' => $ids]
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $this->mensagemErroBanco($e, 'Erro ao gerar parcelas')], 500);
        }
    }

    /**
     * Adiciona parcela avulsa a uma locacao
     *
     * POST /api/locacoes/{id}/parcelas
     * Body: { valor, data_venci, id_conta, id_forma_pagamento, descricao }
     */
    public function adicionarParcela(Request $request, int $id): void
    {
        try {
            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);

            if (!$locacao || $locacao['chave'] !== Auth::chave()) {
                Response::json(['success' => false, 'message' => $this->apiMessage('rental_not_found')], 404);
                return;
            }

            if (!FilialHelper::temAcessoFilial($locacao['id_matriz_filial_retirada'] ?? null)) {
                Response::json(['success' => false, 'message' => $this->apiMessage('access_denied')], 403);
                return;
            }

            $dados = $request->all();
            $tipoLancamento = (string) ($dados['tipo_lancamento'] ?? '');

            if ($tipoLancamento === 'avaria') {
                $planoAvarias = (new PlanoDeContas())->buscarPorHierarquia(Locacao::PLANO_CONTA_AVARIAS);
                if (!$planoAvarias || ($planoAvarias['tipo'] ?? '') !== 'R') {
                    Response::json([
                        'success' => false,
                        'message' => 'Plano de contas de avarias não encontrado'
                    ], 400);
                    return;
                }

                $dados['id_plano_de_conta'] = (int) $planoAvarias['id'];
                $dados['descricao'] = trim((string) ($dados['descricao'] ?? ''));
                if ($dados['descricao'] === '') {
                    $dados['descricao'] = "Locação #{$locacao['codigo']} - Avaria";
                }
            }

            if (empty($dados['valor'])) {
                Response::json(['success' => false, 'message' => $this->apiMessage('value_required')], 400);
                return;
            }

            if (empty($dados['data_venci'])) {
                Response::json(['success' => false, 'message' => $this->apiMessage('due_date_required')], 400);
                return;
            }

            if (empty($dados['id_conta'])) {
                Response::json(['success' => false, 'message' => $this->apiMessage('bank_account_required')], 400);
                return;
            }

            if (empty($dados['id_forma_pagamento'])) {
                Response::json(['success' => false, 'message' => $this->apiMessage('payment_method_required')], 400);
                return;
            }

            $chave = Auth::chave();
            $idParcela = $locacaoModel->adicionarParcela($id, $dados, $chave);

            $acaoLog = $tipoLancamento === 'avaria'
                ? 'adicionou cobrança de avaria na locacao'
                : 'adicionou parcela avulsa na locacao';

            AuditLogService::registrar(($_SESSION['user_name'] ?? 'Sistema') . ", {$acaoLog} [{$locacao['codigo']}]");

            Response::json([
                'success' => true,
                'message' => $tipoLancamento === 'avaria' ? 'Avaria adicionada com sucesso' : $this->apiMessage('installment_added'),
                'data' => ['id' => $idParcela]
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $this->apiMessage('installment_add_error', ['message' => $e->getMessage()])], 500);
        }
    }

    /**
     * Atualiza uma parcela pendente
     *
     * POST /api/locacoes/{id}/parcelas/{idParcela}/atualizar
     */
    public function atualizarParcela(Request $request, int $id, int $idParcela): void
    {
        try {
            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);

            if (!$locacao || $locacao['chave'] !== Auth::chave()) {
                Response::json(['success' => false, 'message' => $this->apiMessage('rental_not_found')], 404);
                return;
            }

            if (!FilialHelper::temAcessoFilial($locacao['id_matriz_filial_retirada'] ?? null)) {
                Response::json(['success' => false, 'message' => $this->apiMessage('access_denied')], 403);
                return;
            }

            $dados = $request->all();
            $locacaoModel->atualizarParcela($id, $idParcela, $dados);

            Response::json(['success' => true, 'message' => $this->apiMessage('installment_updated')]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $this->apiMessage('installment_update_error', ['message' => $e->getMessage()])], 500);
        }
    }

    /**
     * Remove uma parcela pendente
     *
     * POST /api/locacoes/{id}/parcelas/{idParcela}/excluir
     */
    public function removerParcela(Request $request, int $id, int $idParcela): void
    {
        try {
            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);

            if (!$locacao || $locacao['chave'] !== Auth::chave()) {
                Response::json(['success' => false, 'message' => $this->apiMessage('rental_not_found')], 404);
                return;
            }

            if (!FilialHelper::temAcessoFilial($locacao['id_matriz_filial_retirada'] ?? null)) {
                Response::json(['success' => false, 'message' => $this->apiMessage('access_denied')], 403);
                return;
            }

            $locacaoModel->removerParcela($id, $idParcela);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", removeu parcela da locacao [{$locacao['codigo']}]"
            );

            Response::json(['success' => true, 'message' => $this->apiMessage('installment_removed')]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $this->apiMessage('installment_remove_error', ['message' => $e->getMessage()])], 500);
        }
    }

    /**
     * Marca parcela como paga.
     *
     * POST /api/locacoes/{id}/parcelas/{idParcela}/marcar-pago
     * Body: { data_pago, id_forma_pagamento, id_conta }
     */
    public function marcarParcelaPaga(Request $request, int $id, int $idParcela): void
    {
        try {
            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);

            if (!$locacao || $locacao['chave'] !== Auth::chave()) {
                Response::json(['success' => false, 'message' => $this->apiMessage('rental_not_found')], 404);
                return;
            }

            if (!FilialHelper::temAcessoFilial($locacao['id_matriz_filial_retirada'] ?? null)) {
                Response::json(['success' => false, 'message' => $this->apiMessage('access_denied')], 403);
                return;
            }

            $locacaoModel->marcarParcelaPaga($id, $idParcela, $request->all());

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", marcou parcela como paga na locacao [{$locacao['codigo']}]"
            );

            Response::json(['success' => true, 'message' => $this->apiMessage('installment_marked_paid')]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $this->apiMessage('installment_mark_paid_error', ['message' => $e->getMessage()])], 500);
        }
    }

    /**
     * Estorna pagamento de parcela (volta para Pendente).
     *
     * POST /api/locacoes/{id}/parcelas/{idParcela}/estornar
     */
    public function estornarParcelaPagamento(Request $request, int $id, int $idParcela): void
    {
        try {
            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);

            if (!$locacao || $locacao['chave'] !== Auth::chave()) {
                Response::json(['success' => false, 'message' => $this->apiMessage('rental_not_found')], 404);
                return;
            }

            if (!FilialHelper::temAcessoFilial($locacao['id_matriz_filial_retirada'] ?? null)) {
                Response::json(['success' => false, 'message' => $this->apiMessage('access_denied')], 403);
                return;
            }

            $locacaoModel->estornarParcelaPagamento($id, $idParcela);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", estornou pagamento de parcela na locacao [{$locacao['codigo']}]"
            );

            Response::json(['success' => true, 'message' => $this->apiMessage('payment_reversed')]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $this->apiMessage('payment_reverse_error', ['message' => $e->getMessage()])], 500);
        }
    }

    /**
     * Retorna resumo financeiro da locacao
     *
     * GET /api/locacoes/{id}/resumo-financeiro
     */
    public function resumoFinanceiro(Request $request, int $id): void
    {
        try {
            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);

            if (!$locacao || $locacao['chave'] !== Auth::chave()) {
                Response::json(['success' => false, 'message' => $this->apiMessage('rental_not_found')], 404);
                return;
            }

            $resumo = $locacaoModel->resumoFinanceiro($id);

            Response::json(['success' => true, 'data' => $resumo]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ==================== IMPRESSÃO DE LOCAÇÕES ====================

    /**
     * Renderiza o offcanvas com opcoes de impressao da locacao
     *
     * GET /pages/locacoes/offcanvas-impressao
     */
    public function offcanvasImpressao(Request $request): void
    {
        $id = (int) $request->query('id');

        $locacaoModel = new Locacao();
        $locacao = $locacaoModel->buscarPorId($id);

        if (!$locacao) {
            Response::html('<p>' . htmlspecialchars($this->apiMessage('print_not_found_html')) . '</p>', 404);
            return;
        }

        // Buscar documentos disponiveis (tipo 0=Contrato/Locacao, 2=Locacao, status=1=Ativo)
        $documentoModel = new Documento();
        $todosDocumentos = $documentoModel->listarParaSelect();
        $documentos = array_filter($todosDocumentos, fn($d) => in_array((int) $d['tipo'], [0, 2]));

        // Verificar plano do tenant
        $user = Auth::user();
        $planoCodigo = $user['plano'] ?? 'G';
        $planoInfo = Planos::getPlano($planoCodigo);

        // Buscar checklists digitais vinculados a locacao
        $checklistsDigitais = [];
        if (in_array($planoCodigo, ['P3', 'P4'], true)) {
            $checklistModel = new Checklist();
            $checklistsDigitais = $checklistModel->listarFinalizadosPorLocacao((int) $locacao['id']);
        }
        $temChecklistDigital = !empty($checklistsDigitais);

        // Buscar modelos de checklist impresso (tipo=1)
        $checklistModeloModel = new ChecklistModelo();
        $todosModelos = $checklistModeloModel->listarParaSelect();
        $checklistModelos = array_values(array_filter($todosModelos, fn($m) => (int) $m['tipo'] === 1));

        // Verificar canais de mensageria disponiveis para a filial/cliente.
        $filialId = (int) ($locacao['id_matriz_filial_retirada'] ?? 0);
        $telefoneCliente = trim((string) ($locacao['cliente_telefone'] ?? ''));
        $emailCliente = trim((string) ($locacao['cliente_email'] ?? ''));
        $temEmail = ($planoInfo['smtp'] ?? 0) > 0 && $emailCliente !== '';
        $temWhatsapp = ($planoInfo['whatsapp'] ?? 0) > 0
            && $telefoneCliente !== ''
            && $filialId > 0
            && (new Whatsapp())->buscarConectadaPorFilial($filialId) !== null;
        $temSms = ($planoInfo['sms'] ?? 0) > 0
            && $telefoneCliente !== ''
            && $filialId > 0
            && (new Sms())->buscarValidadaPorFilial($filialId) !== null;

        $html = Template::render('pages.locacoes.offcanvas-impressao', [
            'locacao' => $locacao,
            'documentos' => array_values($documentos),
            'checklistModelos' => $checklistModelos,
            'checklistsDigitais' => $checklistsDigitais,
            'temChecklistDigital' => $temChecklistDigital,
            'temEmail' => $temEmail,
            'temWhatsapp' => $temWhatsapp,
            'temSms' => $temSms,
            'planoCodigo' => $planoCodigo,
        ]);
        Response::html($html);
    }

    /**
     * Gera PDF de impressao da locacao
     *
     * GET /locacoes/{id}/imprimir
     */
    public function imprimir(Request $request, int $id): void
    {
        try {
            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);

            if (!$locacao) {
                Response::html('<h1>' . htmlspecialchars($this->apiMessage('print_not_found_html')) . '</h1>', 404);
                return;
            }

            // Verificar tenant
            $chave = Auth::chave();
            if ($locacao['chave'] !== $chave) {
                Response::html('<h1>' . htmlspecialchars($this->apiMessage('print_access_denied_html')) . '</h1>', 403);
                return;
            }

            // Verificar acesso a filial
            if (!FilialHelper::temAcessoFilial($locacao['id_matriz_filial_retirada'] ?? null)) {
                Response::html('<h1>' . htmlspecialchars($this->apiMessage('print_access_denied_html')) . '</h1>', 403);
                return;
            }

            $tipo = $this->normalizarTipoImpressao((string) $request->query('tipo', ''), $locacao);

            // Buscar dados da empresa
            $empresa = $this->buscarDadosEmpresa($locacao['id_matriz_filial_retirada'] ?? null);

            // Buscar veiculo completo para o template
            $veiculo = null;
            if (!empty($locacao['id_veiculo'])) {
                $veiculoModel = new Veiculo();
                $veiculo = $veiculoModel->buscarPorId((int) $locacao['id_veiculo']);
            }

            // Buscar taxas da locacao
            $taxas = (new LocacaoTaxaServico())->listarPorLocacao($id);

            // Buscar multas vinculadas explicitamente a locacao
            $multas = (new Multa())->listarParaFaturaLocacao($id);
            $totalMultas = array_reduce($multas, fn($total, $multa) => $total + (float) ($multa['valor'] ?? 0), 0.0);

            // Dados complementares da capa/fatura
            $historicoVeiculos = (new LocacaoVeiculo())->listarPorLocacao($id);
            $referenciasFatura = $this->montarReferenciasFaturaLocacao($locacao);
            $locacao['cliente_endereco_completo'] = $this->montarEnderecoFatura($locacao, 'cliente_');

            // Buscar parcelas/recebimentos da locacao para a fatura
            $parcelasFinanceiras = $locacaoModel->listarParcelas($id, true);
            $resumoFinanceiro = $locacaoModel->resumoFinanceiro($id);
            $totaisResumoFatura = null;
            if (($locacao['status'] ?? '') === 'F' && (float) ($locacao['total_fatura'] ?? 0) <= 0) {
                $totaisResumoFatura = $locacaoModel->calcularTotaisResumo($id);
                $locacao['total_fatura'] = $totaisResumoFatura['total_fatura'];
                $locacao['total_pagar'] = $totaisResumoFatura['total_pagar'];
            } elseif (($locacao['status'] ?? '') === 'F') {
                $totaisResumoFatura = $locacaoModel->calcularTotaisResumo($id);
            }

            // Buscar assinatura da locacao
            $assinaturaModel = new Assinatura();
            $assinatura = $assinaturaModel->buscarPorLocacao($id);

            // Buscar documento selecionado (para tipos que incluem "documento")
            $documentoTexto = null;
            $idDocumento = (int) $request->query('id_documento', 0);
            if ($idDocumento > 0 && $this->tipoIncluiDocumento($tipo)) {
                $documentoModel = new Documento();
                $documentoTexto = $documentoModel->buscarPorId($idDocumento);

                // Resolver variaveis {{entidade.campo}} no texto do documento
                if ($documentoTexto && !empty($documentoTexto['texto'])) {
                    $renderer = new TemplateRenderer();
                    $context = $this->buildDocumentoContext($locacao, $empresa, $veiculo);
                    $documentoTexto['texto'] = $renderer->render($documentoTexto['texto'], $context);
                }
            }

            // Preparar dados do checklist (para tipos que incluem "checklist")
            $checklistData = null;
            $checklistDigital = false;
            $diagramaPath = null;
            $checklistModeloQuestoes = [];
            if ($this->tipoIncluiChecklist($tipo)) {
                $idChecklistDigital = (int) $request->query('id_checklist_digital', 0);
                $checklistInfo = $this->prepararDadosChecklist($locacao, $chave, $idChecklistDigital);
                $checklistData = $checklistInfo['data'];
                $checklistDigital = $checklistInfo['digital'];
                $diagramaPath = $checklistInfo['diagramaPath'];

                // Carregar questoes do modelo de checklist impresso
                $idChecklistModelo = (int) $request->query('id_checklist_modelo', 0);
                if (!$checklistDigital && $idChecklistModelo > 0) {
                    $checklistModeloModel = new ChecklistModelo();
                    $modelo = $checklistModeloModel->buscarPorId($idChecklistModelo);
                    if ($modelo) {
                        $checklistModeloQuestoes = json_decode($modelo['questoes'] ?? '[]', true) ?: [];
                    }
                }
            }

            // Logo da empresa e QR code para verificacao
            $logoPath = PdfHelper::resolveImagePath($empresa['logo'] ?? null, $chave);
            $empresaAssinaturaPath = PdfHelper::resolveImagePath($empresa['assinatura'] ?? null, $empresa['chave'] ?? $chave);
            $qrPath = $this->gerarQrCodePath($locacao['codigo']);
            $assinaturaPath = !empty($assinatura['arquivo'])
                ? PdfHelper::resolveImagePath($assinatura['arquivo'], $chave)
                : '';

            // Output buffering para gerar HTML (NUNCA usar Template::render para PDF)
            extract(compact('locacao', 'empresa', 'veiculo', 'taxas', 'multas', 'totalMultas', 'historicoVeiculos', 'referenciasFatura', 'parcelasFinanceiras', 'resumoFinanceiro', 'totaisResumoFatura', 'assinatura', 'assinaturaPath', 'empresaAssinaturaPath', 'documentoTexto', 'checklistData', 'checklistDigital', 'diagramaPath', 'checklistModeloQuestoes', 'logoPath', 'qrPath'));
            ob_start();
            $viewPath = __DIR__ . '/../Views/pages/locacoes/imprimir/' . $tipo . '.php';
            include $viewPath;
            $html = ob_get_clean();

            $pdfOptions = [
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 5,
                'margin_bottom' => 5,
            ];

            // Documento personalizado: header/footer aplicados via SetHTMLHeader/SetHTMLFooter
            // (Method 4 do mPDF). Os parciais usam estilos inline — mPDF nao processa
            // <style> blocks no contexto de header/footer. Ref:
            // https://mpdf.github.io/headers-footers/method-4.html
            if ($tipo === 'documento') {
                $partialsDir = __DIR__ . '/../Views/pages/locacoes/imprimir/_partials';

                ob_start();
                $_docTitulo = t('modules.locacoes.pdf.document_title');
                include $partialsDir . '/_header.php';
                $headerHtml = ob_get_clean();

                ob_start();
                include $partialsDir . '/_footer_assinatura.php';
                $footerHtml = ob_get_clean();

                // Margens superior/inferior do construtor = espaco para header/footer HTML (Method 2).
                // Somente @page no template nao reserva o corpo corretamente apos WriteHTML (orig_tMargin).
                $mpdf = PdfHelper::create(array_merge($pdfOptions, [
                    'margin_top' => PdfHelper::DOCUMENTO_HTML_HEADER_MARGIN_TOP_MM,
                    'margin_bottom' => PdfHelper::DOCUMENTO_HTML_FOOTER_MARGIN_BOTTOM_MM,
                ]));
                // SetHTMLHeader: 3o parametro = true forca aplicacao na pagina atual (1).
                // Sem isso, mPDF so aplica o header a partir da pagina 2.
                $mpdf->SetHTMLHeader($headerHtml, 'O', true);
                $mpdf->SetHTMLFooter($footerHtml, 'O');
                PdfHelper::writeHtml($mpdf, $html);
                $mpdf->Output('locacao-' . $locacao['codigo'] . '.pdf', 'I');
            } else {
                if ($tipo === 'documento_checklist') {
                    $pdfOptions['margin_top'] = PdfHelper::DOCUMENTO_HTML_HEADER_MARGIN_TOP_MM;
                    $pdfOptions['margin_bottom'] = PdfHelper::DOCUMENTO_HTML_FOOTER_MARGIN_BOTTOM_MM;
                }
                PdfHelper::outputInline($html, 'locacao-' . $locacao['codigo'] . '.pdf', $pdfOptions);
            }

            $this->limparArquivosTemporarios();
            exit;

        } catch (\Exception $e) {
            Response::html('<h1>' . htmlspecialchars($this->apiMessage('print_generate_error_html', ['message' => $e->getMessage()])) . '</h1>', 500);
        }
    }

    /**
     * Envia locacao por canal de mensageria (email, whatsapp, sms)
     *
     * POST /locacoes/{id}/enviar
     * Body JSON: { tipo, canal, id_documento, id_checklist_modelo, id_checklist_digital }
     */
    public function enviarLocacao(Request $request, int $id): void
    {
        try {
            $data = $request->all();
            $tipo = $data['tipo'] ?? 'fatura';
            $canal = $data['canal'] ?? 'email';
            $idDocumento = (int) ($data['id_documento'] ?? 0);
            $idChecklistModelo = (int) ($data['id_checklist_modelo'] ?? 0);
            $idChecklistDigital = (int) ($data['id_checklist_digital'] ?? 0);

            if (!in_array($canal, ['email', 'whatsapp', 'sms'], true)) {
                Response::json(['success' => false, 'message' => $this->apiMessage('invalid_channel')], 422);
                return;
            }

            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);

            if (!$locacao) {
                Response::json(['success' => false, 'message' => $this->apiMessage('rental_not_found')], 404);
                return;
            }

            $chave = Auth::chave();
            if ($locacao['chave'] !== $chave) {
                Response::json(['success' => false, 'message' => $this->apiMessage('access_denied')], 403);
                return;
            }

            $tipo = $this->normalizarTipoImpressao((string) $tipo, $locacao);

            $empresa = $this->buscarDadosEmpresa($locacao['id_matriz_filial_retirada'] ?? null);
            $nomeEmpresa = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? t('modules.locacoes.pdf.company_fallback');
            $destinatario = $canal === 'email'
                ? ($locacao['cliente_email'] ?? '')
                : ($locacao['cliente_telefone'] ?? '');

            validate_queue_message($canal, [
                'to' => $destinatario,
                'id_matriz_filial' => $locacao['id_matriz_filial_retirada'] ?? null,
            ]);

            // Gerar PDF como string
            $pdfContent = $this->gerarPdfString($id, $tipo, $idDocumento, $idChecklistModelo, $idChecklistDigital);
            $documentoLabel = $tipo === 'voucher' ? t('modules.locacoes.print.reservation_label') : t('modules.locacoes.print.rental_label');

            // Salvar em arquivo temporario
            $filename = strtolower($documentoLabel) . '_' . $locacao['codigo'] . '_' . time() . '.pdf';
            $tempDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/storage/temp';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            $tempPath = $tempDir . '/' . $filename;
            file_put_contents($tempPath, $pdfContent);

            if ($canal === 'email') {
                queue_message('email', [
                    'to' => $destinatario,
                    'to_name' => $locacao['cliente_nome_completo'] ?? '',
                    'subject' => $documentoLabel . ' - ' . $locacao['codigo'],
                    'body' => t('modules.locacoes.api.document_email_body', [
                        'document' => strtolower($documentoLabel),
                        'code' => '<strong>' . htmlspecialchars($locacao['codigo']) . '</strong>',
                        'company' => htmlspecialchars($nomeEmpresa),
                    ]),
                    'attachments' => [$tempPath],
                    'id_matriz_filial' => $locacao['id_matriz_filial_retirada'] ?? null,
                ]);
            } elseif ($canal === 'whatsapp') {
                $publicUrl = rtrim(env('APP_URL', ''), '/') . '/storage/temp/' . $filename;
                queue_message('whatsapp', [
                    'to' => $destinatario,
                    'media_url' => $publicUrl,
                    'caption' => $documentoLabel . ' ' . $locacao['codigo'] . ' - ' . $nomeEmpresa,
                    'id_matriz_filial' => $locacao['id_matriz_filial_retirada'] ?? null,
                ]);
            } elseif ($canal === 'sms') {
                queue_message('sms', [
                    'to' => $destinatario,
                    'message' => t('modules.locacoes.api.document_sms_body', [
                        'document' => strtolower($documentoLabel),
                        'code' => $locacao['codigo'],
                        'company' => $nomeEmpresa,
                    ]),
                    'id_matriz_filial' => $locacao['id_matriz_filial_retirada'] ?? null,
                ]);
            }

            Response::json(['success' => true, 'message' => $this->apiMessage('document_sent')]);

        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $this->apiMessage('send_error', ['message' => $e->getMessage()])], 500);
        }
    }

    /**
     * Envia link publico de assinatura da locacao por WhatsApp.
     *
     * POST /locacoes/{id}/enviar-link-assinatura
     * Body JSON: { url }
     */
    public function enviarLinkAssinatura(Request $request, int $id): void
    {
        try {
            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);

            if (!$locacao) {
                Response::json(['success' => false, 'message' => $this->apiMessage('rental_not_found')], 404);
                return;
            }

            $chave = Auth::chave();
            if ($locacao['chave'] !== $chave) {
                Response::json(['success' => false, 'message' => $this->apiMessage('access_denied')], 403);
                return;
            }

            if (!FilialHelper::temAcessoFilial($locacao['id_matriz_filial_retirada'] ?? null)) {
                Response::json(['success' => false, 'message' => $this->apiMessage('access_denied')], 403);
                return;
            }

            $telefone = $locacao['cliente_telefone'] ?? '';
            if (empty($telefone)) {
                Response::json(['success' => false, 'message' => $this->apiMessage('customer_phone_missing')], 400);
                return;
            }

            $url = trim((string) ($request->input('url') ?? ''));
            if ($url === '') {
                $url = rtrim(env('APP_URL', ''), '/') . '/assinar/' . $locacao['codigo'];
            }

            $filialId = (int) ($locacao['id_matriz_filial_retirada'] ?? 0);
            $empresa = $this->buscarDadosEmpresa($filialId) ?? [];
            $empresa['id'] = $empresa['id'] ?? $filialId;

            queue_template_message('signature_request', 'whatsapp', [
                'cliente' => [
                    'nome' => $locacao['cliente_nome_completo'] ?? $locacao['cliente_nome'] ?? '',
                    'email' => $locacao['cliente_email'] ?? '',
                    'telefone' => $telefone,
                    'celular' => $telefone,
                ],
                'empresa' => $empresa,
                'locacao' => $locacao,
                'outros' => [
                    'link_assinatura' => $url,
                ],
                'id_matriz_filial' => $filialId,
            ], $chave);

            Response::json(['success' => true, 'message' => $this->apiMessage('signature_link_sent')]);

        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $this->apiMessage('send_link_error', ['message' => $e->getMessage()])], 500);
        }
    }

    // ==================== HELPERS PARA IMPRESSÃO ====================

    /**
     * Verifica se o tipo de impressao inclui "documento"
     */
    private function tipoIncluiDocumento(string $tipo): bool
    {
        return in_array($tipo, ['documento', 'fatura_documento', 'fatura_checklist_documento', 'documento_checklist'], true);
    }

    /**
     * Normaliza o tipo de impressao permitido para o status atual.
     */
    private function normalizarTipoImpressao(string $tipo, array $locacao): string
    {
        $isReservaConfirmada = ($locacao['status'] ?? '') === 'R';
        $tipo = trim($tipo);
        $tipoPadrao = $isReservaConfirmada ? 'voucher' : 'fatura';
        $tiposValidos = [
            'fatura', 'documento', 'fatura_documento',
            'fatura_checklist', 'fatura_checklist_documento',
            'documento_checklist', 'checklist', 'recibo'
        ];

        if ($isReservaConfirmada) {
            $tiposValidos[] = 'voucher';
        }

        return in_array($tipo, $tiposValidos, true) ? $tipo : $tipoPadrao;
    }

    /**
     * Verifica se o tipo de impressao inclui "checklist"
     */
    private function tipoIncluiChecklist(string $tipo): bool
    {
        return in_array($tipo, ['checklist', 'fatura_checklist', 'fatura_checklist_documento', 'documento_checklist'], true);
    }

    /**
     * Monta o contexto para resolver variaveis do TemplateRenderer
     */
    private function buildDocumentoContext(array $locacao, array $empresa, ?array $veiculo): array
    {
        // Buscar dados completos do cliente
        $clienteData = [];
        if (!empty($locacao['id_cliente'])) {
            $clienteModel = new Cliente();
            $clienteData = $clienteModel->buscarPorId((int) $locacao['id_cliente']) ?? [];
        }

        $fornecedorData = $this->resolverFornecedorDocumento($veiculo);
        $caucaoDataPrevistaDevolucao = $this->calcularDataPrevistaDevolucaoCaucao($locacao);
        $dataRetiradaDocumento = $this->dataValidaDocumento($locacao['data_saida'] ?? $locacao['data_retirada'] ?? null);
        $valorTotalDocumento = $this->primeiroValorDocumento([
            $locacao['total_pagar'] ?? null,
            $locacao['valor_total'] ?? null,
            $locacao['total_fatura'] ?? null,
        ], 0);
        $bloqueioValorDocumento = $this->primeiroValorMonetarioPositivoDocumento([
            $locacao['bloqueio_valor'] ?? null,
            $locacao['bloqueio_hold_valor'] ?? null,
            $locacao['valor_bloqueio'] ?? null,
        ]);
        $kmSaidaDocumento = $this->primeiroValorDocumento([
            $locacao['odometro_ini'] ?? null,
            $locacao['odometro_saida'] ?? null,
            $locacao['km_saida'] ?? null,
        ], '');

        $statusLabel = match($locacao['status'] ?? 'R') {
            'R' => t('modules.locacoes.pdf.status_reservation'),
            'A' => t('modules.locacoes.pdf.status_open'),
            'F' => t('modules.locacoes.pdf.status_closed'),
            default => $locacao['status'] ?? '',
        };

        return [
            'cliente' => [
                'nome' => $clienteData['nome_rsocial'] ?? $locacao['cliente_nome_completo'] ?? '',
                'cpf_cnpj' => $clienteData['cpf_cnpj'] ?? $locacao['cliente_cpf_cnpj'] ?? '',
                'email' => $locacao['cliente_email'] ?? $clienteData['email'] ?? '',
                'telefone' => $locacao['cliente_telefone'] ?? '',
                'celular' => $clienteData['celular'] ?? '',
                'nome_fantasia' => $clienteData['nome_fantasia'] ?? '',
                'rg_ie' => $clienteData['rg_ie'] ?? '',
                'rg' => $clienteData['rg'] ?? '',
                'endereco' => $clienteData['rua'] ?? '',
                'numero' => $clienteData['numero'] ?? '',
                'complemento' => $clienteData['complemento'] ?? '',
                'bairro' => $clienteData['bairro'] ?? '',
                'cidade' => $clienteData['cidade'] ?? '',
                'uf' => $clienteData['estado'] ?? '',
                'cep' => $clienteData['cep'] ?? '',
                'pais' => $clienteData['pais'] ?? '',
                'cnh_numero' => $clienteData['cnh_numero'] ?? '',
                'cnh_validade' => $clienteData['cnh_validade'] ?? '',
                'cnh_categoria' => $clienteData['cnh_categoria'] ?? '',
                'data_nascimento' => $clienteData['nascimento'] ?? '',
                'profissao' => $clienteData['profissao'] ?? '',
                'estado_civil' => $clienteData['estado_civil'] ?? '',
            ],
            'empresa' => [
                'id' => $empresa['id'] ?? null,
                'locale' => $empresa['locale'] ?? null,
                'currency_code' => $empresa['currency_code'] ?? null,
                'razao_social' => $empresa['razao_social'] ?? '',
                'nome_fantasia' => $empresa['nome_fantasia'] ?? '',
                'cnpj' => $empresa['cpf_cnpj'] ?? '',
                'email' => $empresa['email'] ?? '',
                'telefone' => $empresa['fixo'] ?? $empresa['celular'] ?? '',
                'whatsapp' => $empresa['celular'] ?? '',
                'endereco' => $empresa['rua'] ?? '',
                'numero' => $empresa['num'] ?? '',
                'bairro' => $empresa['bairro'] ?? '',
                'cidade' => $empresa['cidade'] ?? '',
                'uf' => $empresa['estado'] ?? '',
                'cep' => $empresa['cep'] ?? '',
                'pais' => $empresa['pais'] ?? '',
                'site' => $empresa['site'] ?? '',
                'ie' => $empresa['inscricao_estadual'] ?? '',
                'im' => $empresa['inscricao_municipal'] ?? '',
                'complemento' => $empresa['complemento'] ?? '',
            ],
            'locacao' => [
                'numero' => $locacao['codigo'] ?? '',
                'data_saida' => $locacao['data_saida'] ?? '',
                'data_prevista' => $locacao['data_prevista'] ?? '',
                'data_retorno' => $locacao['data_retorno'] ?? '',
                'hora_saida' => !empty($locacao['data_saida']) ? date('H:i', strtotime($locacao['data_saida'])) : '',
                'hora_prevista' => !empty($locacao['data_prevista']) ? date('H:i', strtotime($locacao['data_prevista'])) : '',
                'data_retirada' => $dataRetiradaDocumento,
                'hora_retirada' => !empty($locacao['data_saida']) ? date('H:i', strtotime($locacao['data_saida'])) : '',
                'data_devolucao' => $locacao['data_prevista'] ?? '',
                'hora_devolucao' => !empty($locacao['data_prevista']) ? date('H:i', strtotime($locacao['data_prevista'])) : '',
                'local_retirada' => $locacao['filial_retirada_nome'] ?? '',
                'local_devolucao' => $locacao['filial_devolucao_nome'] ?? '',
                'valor_total' => $valorTotalDocumento,
                'valor_fatura' => $locacao['total_fatura'] ?? 0,
                'valor_diaria' => $locacao['diaria_valor'] ?? 0,
                'quantidade_dias' => (int) ($locacao['dias'] ?? $locacao['quantidade_dias'] ?? 0),
                'status' => $statusLabel,
                'observacoes' => $locacao['obs'] ?? '',
                'desconto' => $locacao['valor_desconto'] ?? 0,
                'forma_pagamento' => $locacao['forma_pagamento_descricao'] ?? '',
                'primeiro_pagamento' => $locacao['primeiro_pagamento'] ?? 0,
                'filial_retirada' => $locacao['filial_retirada_nome'] ?? '',
                'filial_devolucao' => $locacao['filial_devolucao_nome'] ?? '',
                'total_fatura' => $locacao['total_fatura'] ?? 0,
                'fatura_a_pagar' => $locacao['total_pagar'] ?? 0,
                'bloqueio_valor' => $bloqueioValorDocumento,
                'deposito_valor' => $locacao['caucao_valor'] ?? 0,
                'fatura_paga' => $locacao['fatura_paga'] ?? $locacao['valor_pago'] ?? 0,
                'grupo' => $locacao['grupo_nome'] ?? '',
                'grupo_descricao' => $locacao['grupo_descricao'] ?? $locacao['grupo_nome'] ?? '',
                'tanque_saida' => $this->formatarNivelTanqueDocumento($locacao['combustivel_ini'] ?? null),
                'tanque_chegada' => $this->formatarNivelTanqueDocumento($locacao['combustivel_fim'] ?? null),
                'km_saida' => $kmSaidaDocumento,
                'km_chegada' => $locacao['odometro_fim'] ?? '',
                'plano' => $locacao['plano'] ?? '',
                'info_plano' => $this->formatarInfoPlanoDocumento($locacao),
                'cobertura' => $locacao['cobertura_carro_valor'] ?? '',
                'cobertura_terceiros' => $locacao['cobertura_terceiros_valor'] ?? '',
                'bloqueio_data_devolucao' => $caucaoDataPrevistaDevolucao,
                'caucao_data_devolucao' => $this->dataValidaDocumento($locacao['caucao_data_devolucao'] ?? null),
                'caucao_prazo_devolucao' => $locacao['caucao_prazo_devolucao'] ?? '',
                'caucao_data_prevista_devolucao' => $caucaoDataPrevistaDevolucao,
                'condutores' => !empty($locacao['condutor_adicional']) ? (json_decode($locacao['condutor_adicional'], true) ?: []) : [],
                'fiadores' => !empty($locacao['array_fiadores']) ? (json_decode($locacao['array_fiadores'], true) ?: []) : [],
                'avalistas' => !empty($locacao['array_avalistas']) ? (json_decode($locacao['array_avalistas'], true) ?: []) : [],
                'testemunhas' => !empty($locacao['array_testemunhas']) ? (json_decode($locacao['array_testemunhas'], true) ?: []) : [],
            ],
            'veiculo' => $veiculo ? [
                'placa' => $veiculo['placa'] ?? '',
                'modelo' => $veiculo['modelo'] ?? '',
                'marca' => $veiculo['marca'] ?? '',
                'ano' => $veiculo['ano'] ?? '',
                'cor' => $veiculo['cor'] ?? '',
                'renavam' => $veiculo['renavam'] ?? '',
                'categoria' => $veiculo['grupo_nome'] ?? $locacao['grupo_nome'] ?? '',
                'chassi' => $veiculo['chassi'] ?? '',
                'combustivel_tipo' => $veiculo['tipo_combustivel'] ?? $locacao['veiculo_tipo_combustivel'] ?? '',
                'valor_compra' => $veiculo['valor_compra'] ?? 0,
                'valor_venda' => $veiculo['valor_venda'] ?? 0,
            ] : [],
            'fornecedor' => $this->formatarFornecedorDocumento($fornecedorData),
        ];
    }

    private function formatarNivelTanqueDocumento(mixed $nivel): string
    {
        if ($nivel === null || $nivel === '') {
            return '';
        }

        $niveis = [
            '0' => 'Reserva',
            '1' => '1/8',
            '2' => '1/4',
            '3' => '3/8',
            '4' => '1/2',
            '5' => '5/8',
            '6' => '3/4',
            '7' => '7/8',
            '8' => 'Cheio',
        ];

        return $niveis[(string) $nivel] ?? (string) $nivel;
    }

    private function primeiroValorDocumento(array $valores, mixed $padrao = ''): mixed
    {
        foreach ($valores as $valor) {
            if ($valor !== null && $valor !== '') {
                return $valor;
            }
        }

        return $padrao;
    }

    private function primeiroValorMonetarioPositivoDocumento(array $valores): mixed
    {
        foreach ($valores as $valor) {
            if ($valor !== null && $valor !== '' && (float) $valor > 0) {
                return $valor;
            }
        }

        return $this->primeiroValorDocumento($valores, 0);
    }

    private function calcularDataPrevistaDevolucaoCaucao(array $locacao): string
    {
        $dataEfetiva = $this->dataValidaDocumento($locacao['caucao_data_devolucao'] ?? null);
        if ($dataEfetiva !== '') {
            return $dataEfetiva;
        }

        if (isset($locacao['caucao_prazo_devolucao']) && $locacao['caucao_prazo_devolucao'] !== '') {
            $prazo = (int) $locacao['caucao_prazo_devolucao'];
            $dataBase = $this->dataValidaDocumento($locacao['data_prevista'] ?? null);
            if ($dataBase !== '') {
                try {
                    return (new \DateTimeImmutable($dataBase))
                        ->modify("+{$prazo} days")
                        ->format('Y-m-d');
                } catch (\Exception $e) {
                    return '';
                }
            }
        }

        return $this->dataValidaDocumento($locacao['bloqueio_data_devolucao'] ?? null);
    }

    private function dataValidaDocumento(mixed $data): string
    {
        if (!is_string($data) || trim($data) === '') {
            return '';
        }

        $data = trim($data);
        if (str_starts_with($data, '0000-00-00')) {
            return '';
        }

        return $data;
    }

    /**
     * Resolve o fornecedor do veiculo usado na impressao do documento.
     */
    private function resolverFornecedorDocumento(?array $veiculo): ?array
    {
        if (!$veiculo) {
            return null;
        }

        $idFornecedor = (int) ($veiculo['id_fornecedor'] ?? 0);

        if ($idFornecedor <= 0 && !empty($veiculo['id_veiculo'])) {
            $veiculoCompleto = (new Veiculo())->buscarPorId((int) $veiculo['id_veiculo']);
            $idFornecedor = (int) ($veiculoCompleto['id_fornecedor'] ?? 0);
        }

        if ($idFornecedor <= 0) {
            return null;
        }

        return (new Fornecedor())->buscarPorId($idFornecedor);
    }

    /**
     * Mapeia campos reais de fornecedores para as variaveis {{fornecedor.*}}.
     */
    private function formatarFornecedorDocumento(?array $fornecedor): array
    {
        if (!$fornecedor) {
            return [];
        }

        return [
            'nome' => $fornecedor['nome_rsocial'] ?? '',
            'nome_fantasia' => $fornecedor['nome_fantasia'] ?? '',
            'cpf_cnpj' => $fornecedor['cpf_cnpj'] ?? '',
            'rg_ie' => $fornecedor['rg_ie'] ?? '',
            'endereco' => $fornecedor['rua'] ?? '',
            'numero' => $fornecedor['num'] ?? $fornecedor['numero'] ?? '',
            'bairro' => $fornecedor['bairro'] ?? '',
            'cidade' => $fornecedor['cidade'] ?? '',
            'estado' => $fornecedor['estado'] ?? '',
            'pais' => $fornecedor['pais'] ?? '',
            'email' => $fornecedor['email'] ?? '',
            'observacoes' => $fornecedor['obs'] ?? $fornecedor['observacoes'] ?? '',
        ];
    }

    /**
     * Retorna descrição curta do plano para variáveis de documento.
     */
    private function formatarInfoPlanoDocumento(array $locacao): string
    {
        $plano = $locacao['plano'] ?? '';

        return match ($plano) {
            'KL' => 'Km Livre',
            'KMC' => 'Km Controlado'
                . (!empty($locacao['km_controlado_franquia']) ? ' - ' . $locacao['km_controlado_franquia'] . ' km' : ''),
            'DI', 'KP' => 'Diária',
            default => (string) $plano,
        };
    }

    /**
     * Prepara dados do checklist baseado no plano do tenant
     */
    private function prepararDadosChecklist(array $locacao, string $chave, int $idChecklistDigital = 0): array
    {
        $planoCodigo = Auth::user()['plano'] ?? 'G';

        // P3/P4: usar checklist digital somente quando selecionado na impressao.
        if ($idChecklistDigital > 0 && in_array($planoCodigo, ['P3', 'P4'], true)) {
            $checklistModel = new Checklist();
            $checklistCompleto = $checklistModel->buscarPorId($idChecklistDigital);
            if (!$checklistCompleto || (int) ($checklistCompleto['id_locacao'] ?? 0) !== (int) $locacao['id']) {
                throw new \InvalidArgumentException('Checklist digital nao encontrado para esta locacao');
            }

            return $this->montarChecklistDigitalParaImpressao($checklistModel, $checklistCompleto, $chave);
        }

        // Checklist impresso: usar diagrama do veiculo
        $diagramaPath = null;
        $veiculoModel = new LocacaoVeiculo();
        $veiculoAtivo = $veiculoModel->buscarAtualOuUltimo((int) $locacao['id']);
        if ($veiculoAtivo && !empty($veiculoAtivo['veiculo_diagrama'])) {
            $diagramaPath = PdfHelper::resolvePublicAssetImagePath(
                $veiculoAtivo['veiculo_diagrama'],
                'assets/img/diagramas'
            );
        }

        return [
            'digital' => false,
            'data' => null,
            'diagramaPath' => $diagramaPath,
        ];
    }

    private function montarChecklistDigitalParaImpressao(Checklist $checklistModel, array $checklistCompleto, string $chave): array
    {
        $momento = $checklistCompleto['momento'] ?? 'S';
        $par = $checklistModel->buscarPar($checklistCompleto);

        if ($momento === 'C') {
            $regSaida = $par;
            $regChegada = $checklistCompleto;
        } else {
            $regSaida = $checklistCompleto;
            $regChegada = $par;
        }

        $base = $regSaida ?? $regChegada;
        $vistoriaSaida = $regSaida ? $this->carregarFotosVistoria(
            json_decode($regSaida['vistoria'] ?? $regSaida['vistoria_saida'] ?? '[]', true) ?: [],
            $chave
        ) : [];
        $vistoriaChegada = $regChegada ? $this->carregarFotosVistoria(
            json_decode($regChegada['vistoria'] ?? $regChegada['vistoria_saida'] ?? '[]', true) ?: [],
            $chave
        ) : [];

        $base['obs'] = $regSaida['obs_unica'] ?? $regSaida['obs'] ?? '';
        $base['obs_chegada'] = $regChegada['obs_unica'] ?? $regChegada['obs'] ?? '';
        $base['data_saida'] = $regSaida['data_checklist'] ?? $regSaida['data_saida'] ?? null;
        $base['data_chegada'] = $regChegada['data_checklist'] ?? $regChegada['data_saida'] ?? null;

        return [
            'digital' => true,
            'data' => [
                'checklist' => $base,
                'questoesSaida' => $regSaida ? (json_decode($regSaida['questoes'] ?? $regSaida['questoes_saida'] ?? '[]', true) ?: []) : [],
                'questoesChegada' => $regChegada ? (json_decode($regChegada['questoes'] ?? $regChegada['questoes_saida'] ?? '[]', true) ?: []) : [],
                'vistoriaSaida' => $vistoriaSaida,
                'vistoriaChegada' => $vistoriaChegada,
            ],
            'diagramaPath' => null,
        ];
    }

    private function montarEnderecoFatura(array $dados, string $prefixo = ''): string
    {
        $linha = trim(implode(', ', array_filter([
            trim((string) ($dados[$prefixo . 'rua'] ?? '')),
            trim((string) ($dados[$prefixo . 'numero'] ?? '')),
            trim((string) ($dados[$prefixo . 'complemento'] ?? '')),
        ], fn($valor) => $valor !== '')));

        $cidadeUf = trim(implode('/', array_filter([
            trim((string) ($dados[$prefixo . 'cidade'] ?? '')),
            trim((string) ($dados[$prefixo . 'estado'] ?? '')),
        ], fn($valor) => $valor !== '')));

        $localidade = trim(implode(' - ', array_filter([
            trim((string) ($dados[$prefixo . 'bairro'] ?? '')),
            $cidadeUf,
        ], fn($valor) => $valor !== '')));

        $cep = trim((string) ($dados[$prefixo . 'cep'] ?? ''));
        $pais = trim((string) ($dados[$prefixo . 'pais'] ?? ''));

        return trim(implode(' - ', array_filter([
            $linha,
            $localidade,
            $cep !== '' ? 'CEP ' . $cep : '',
            $pais,
        ], fn($valor) => $valor !== '')));
    }

    private function montarReferenciasFaturaLocacao(array $locacao): array
    {
        $grupos = [
            ['campo' => 'array_fiadores', 'tipo' => t('modules.locacoes.pdf.guarantor_type')],
            ['campo' => 'array_avalistas', 'tipo' => t('modules.locacoes.pdf.endorser_type')],
            ['campo' => 'array_testemunhas', 'tipo' => t('modules.locacoes.pdf.witness_type')],
        ];

        $clienteModel = new Cliente();
        $referencias = [];

        foreach ($grupos as $grupo) {
            $pessoas = !empty($locacao[$grupo['campo']])
                ? (json_decode($locacao[$grupo['campo']], true) ?: [])
                : [];

            foreach ($pessoas as $pessoa) {
                $cliente = null;
                if (!empty($pessoa['id'])) {
                    $cliente = $clienteModel->buscarPorIdComContatos((int) $pessoa['id']);
                }

                $referencias[] = [
                    'tipo' => $grupo['tipo'],
                    'nome' => $pessoa['nome'] ?? $cliente['nome_rsocial'] ?? '-',
                    'doc' => $pessoa['cc'] ?? $cliente['cpf_cnpj'] ?? '-',
                    'telefone' => $pessoa['telefone'] ?? $cliente['telefone'] ?? '-',
                    'endereco' => $pessoa['endereco'] ?? ($cliente ? $this->montarEnderecoFatura($cliente) : ''),
                ];
            }
        }

        return $referencias;
    }

    /**
     * Gera PDF da locacao como string (para envio por mensageria)
     */
    private function gerarPdfString(
        int $id,
        string $tipo,
        int $idDocumento = 0,
        int $idChecklistModelo = 0,
        int $idChecklistDigital = 0
    ): string
    {
        $locacaoModel = new Locacao();
        $locacao = $locacaoModel->buscarPorId($id);
        $chave = Auth::chave();
        $tipo = $this->normalizarTipoImpressao($tipo, $locacao ?? []);

        $empresa = $this->buscarDadosEmpresa($locacao['id_matriz_filial_retirada'] ?? null);

        // Buscar veiculo completo
        $veiculo = null;
        if (!empty($locacao['id_veiculo'])) {
            $veiculoModel = new Veiculo();
            $veiculo = $veiculoModel->buscarPorId((int) $locacao['id_veiculo']);
        }

        // Buscar taxas da locacao
        $taxas = (new LocacaoTaxaServico())->listarPorLocacao($id);

        // Buscar multas vinculadas explicitamente a locacao
        $multas = (new Multa())->listarParaFaturaLocacao($id);
        $totalMultas = array_reduce($multas, fn($total, $multa) => $total + (float) ($multa['valor'] ?? 0), 0.0);

        // Dados complementares da capa/fatura
        $historicoVeiculos = (new LocacaoVeiculo())->listarPorLocacao($id);
        $referenciasFatura = $this->montarReferenciasFaturaLocacao($locacao);
        $locacao['cliente_endereco_completo'] = $this->montarEnderecoFatura($locacao, 'cliente_');

        // Buscar parcelas/recebimentos da locacao para a fatura
        $parcelasFinanceiras = $locacaoModel->listarParcelas($id, true);
        $resumoFinanceiro = $locacaoModel->resumoFinanceiro($id);
        $totaisResumoFatura = null;
        if (($locacao['status'] ?? '') === 'F' && (float) ($locacao['total_fatura'] ?? 0) <= 0) {
            $totaisResumoFatura = $locacaoModel->calcularTotaisResumo($id);
            $locacao['total_fatura'] = $totaisResumoFatura['total_fatura'];
            $locacao['total_pagar'] = $totaisResumoFatura['total_pagar'];
        } elseif (($locacao['status'] ?? '') === 'F') {
            $totaisResumoFatura = $locacaoModel->calcularTotaisResumo($id);
        }

        // Buscar assinatura
        $assinaturaModel = new Assinatura();
        $assinatura = $assinaturaModel->buscarPorLocacao($id);

        $documentoTexto = null;
        if ($idDocumento > 0 && $this->tipoIncluiDocumento($tipo)) {
            $documentoModel = new Documento();
            $documentoTexto = $documentoModel->buscarPorId($idDocumento);
            if ($documentoTexto && !empty($documentoTexto['texto'])) {
                $renderer = new TemplateRenderer();
                $context = $this->buildDocumentoContext($locacao, $empresa, $veiculo);
                $documentoTexto['texto'] = $renderer->render($documentoTexto['texto'], $context);
            }
        }

        $checklistData = null;
        $checklistDigital = false;
        $diagramaPath = null;
        $checklistModeloQuestoes = [];
        if ($this->tipoIncluiChecklist($tipo)) {
            $checklistInfo = $this->prepararDadosChecklist($locacao, $chave, $idChecklistDigital);
            $checklistData = $checklistInfo['data'];
            $checklistDigital = $checklistInfo['digital'];
            $diagramaPath = $checklistInfo['diagramaPath'];

            if (!$checklistDigital && $idChecklistModelo > 0) {
                $checklistModeloModel = new ChecklistModelo();
                $modelo = $checklistModeloModel->buscarPorId($idChecklistModelo);
                if ($modelo) {
                    $checklistModeloQuestoes = json_decode($modelo['questoes'] ?? '[]', true) ?: [];
                }
            }
        }

        $logoPath = PdfHelper::resolveImagePath($empresa['logo'] ?? null, $chave);
        $empresaAssinaturaPath = PdfHelper::resolveImagePath($empresa['assinatura'] ?? null, $empresa['chave'] ?? $chave);
        $qrPath = $this->gerarQrCodePath($locacao['codigo']);
        $assinaturaPath = !empty($assinatura['arquivo'])
            ? PdfHelper::resolveImagePath($assinatura['arquivo'], $chave)
            : '';

        extract(compact('locacao', 'empresa', 'veiculo', 'taxas', 'multas', 'totalMultas', 'historicoVeiculos', 'referenciasFatura', 'parcelasFinanceiras', 'resumoFinanceiro', 'totaisResumoFatura', 'assinatura', 'assinaturaPath', 'empresaAssinaturaPath', 'documentoTexto', 'checklistData', 'checklistDigital', 'diagramaPath', 'checklistModeloQuestoes', 'logoPath', 'qrPath'));

        ob_start();
        $viewPath = __DIR__ . '/../Views/pages/locacoes/imprimir/' . $tipo . '.php';
        include $viewPath;
        $html = ob_get_clean();

        $pdfOptions = [
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 5,
            'margin_bottom' => 5,
        ];

        if ($tipo === 'documento_checklist') {
            $pdfOptions['margin_top'] = PdfHelper::DOCUMENTO_HTML_HEADER_MARGIN_TOP_MM;
            $pdfOptions['margin_bottom'] = PdfHelper::DOCUMENTO_HTML_FOOTER_MARGIN_BOTTOM_MM;
        }

        $result = PdfHelper::generateAsString($html, $pdfOptions);

        $this->limparArquivosTemporarios();

        return $result;
    }

    /**
     * Busca dados da empresa para impressao
     */
    private function buscarDadosEmpresa(?int $filialId): ?array
    {
        $matrizFilialModel = new MatrizFilial();
        return $matrizFilialModel->buscarDadosEmpresa($filialId);
    }

    /**
     * Gera QR code e salva em arquivo temporario para uso em mPDF
     */
    private function gerarQrCodePath(string $codigo): string
    {
        if (empty($codigo)) {
            return '';
        }

        try {
            $baseUrl = rtrim(Database::env('APP_URL', ''), '/');
            $url = $baseUrl . '/verificar/locacao/' . $codigo;

            $qrGenerator = new QrCodeGenerator();
            $qrImage = $qrGenerator->format('png')->size(120)->generate($url);

            $tmpPath = sys_get_temp_dir() . '/qr_locacao_' . $codigo . '.png';
            file_put_contents($tmpPath, $qrImage);
            $this->tmpFiles[] = $tmpPath;

            return $tmpPath;
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Resolve caminhos absolutos das fotos da vistoria para uso em mPDF.
     * PdfHelper::resolveImagePath converte WebP para JPEG temporario (performance) e
     * agenda cleanup automatico ao final do request.
     */
    private function carregarFotosVistoria(array $vistoria, string $chave): array
    {
        foreach ($vistoria as &$item) {
            $item['img_path'] = PdfHelper::resolveImagePath($item['img'] ?? null, $chave);
        }
        return $vistoria;
    }

    /**
     * Remove arquivos temporarios criados durante a geracao do PDF
     */
    private function limparArquivosTemporarios(): void
    {
        foreach ($this->tmpFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tmpFiles = [];
    }

    // ========== BLOQUEIO (Authorization Hold) ==========

    /**
     * Cria um authorization hold no cartao do cliente
     *
     * POST /api/locacoes/{id}/bloqueio/criar
     */
    public function criarBloqueio(Request $request, int $id): void
    {
        try {
            if (!Auth::can('locacoes.editar')) {
                Response::json(['success' => false, 'message' => $this->apiMessage('no_permission')], 403);
                return;
            }

            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);
            if (!$locacao) {
                Response::json(['success' => false, 'message' => $this->apiMessage('rental_not_found')], 404);
                return;
            }

            $dados = $request->all();
            $idCartao = (int) ($dados['id_cartao'] ?? 0);
            $valor = !empty($dados['valor'])
                ? (float) str_replace(['.', ','], ['', '.'], $dados['valor'])
                : 0;

            if ($idCartao <= 0 || $valor <= 0) {
                Response::json(['success' => false, 'message' => $this->apiMessage('card_value_required')], 400);
                return;
            }

            // Buscar cartao do cliente
            $cartaoModel = new \App\Models\ClienteCartao();
            $cartao = $cartaoModel->buscarPorId($idCartao);
            if (!$cartao) {
                Response::json(['success' => false, 'message' => $this->apiMessage('card_not_found')], 404);
                return;
            }

            // Buscar gateway que suporte hold
            $gatewayModel = new \App\Models\GatewayPagamento();
            $gateways = $gatewayModel->listarAtivos();
            $gatewayConfig = null;
            foreach ($gateways as $gw) {
                if ($gw['gateway_code'] === $cartao['gateway']) {
                    $gatewayConfig = $gatewayModel->buscarPorIdComCredenciais((int) $gw['id']);
                    break;
                }
            }

            if (!$gatewayConfig) {
                Response::json(['success' => false, 'message' => $this->apiMessage('card_gateway_missing')], 400);
                return;
            }

            $gateway = \App\Services\Gateways\GatewayFactory::create(
                $gatewayConfig['gateway_code'],
                $gatewayConfig['credentials'] ?? [],
                $gatewayConfig['ambiente'] === 'sandbox',
                (int) $gatewayConfig['id']
            );

            if (!($gateway instanceof \App\Services\Gateways\AuthorizationHoldInterface) || !$gateway->supportsAuthorizationHold()) {
                Response::json(['success' => false, 'message' => $this->apiMessage('gateway_hold_unsupported')], 400);
                return;
            }

            // Criar hold
            $chave = $_SESSION['chave'] ?? '';
            $result = $gateway->createHold([
                'chave' => $chave,
                'payment_method_id' => $cartao['token'],
                'id_cartao_registro' => $idCartao,
                'amount' => $valor,
                'customer_name' => $locacao['cliente_nome_completo'] ?? $locacao['cliente_nome'] ?? '',
                'description' => 'Bloqueio - Locacao ' . ($locacao['codigo'] ?? $id),
                'metadata' => [
                    'id_locacao' => $id,
                    'id_cliente' => $locacao['id_cliente'],
                ],
            ]);

            if (!$result['success']) {
                Response::json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Erro ao criar bloqueio',
                    'client_secret' => $result['client_secret'] ?? null,
                ], 400);
                return;
            }

            // Salvar no banco
            $bloqueioModel = new \App\Models\LocacaoBloqueio();
            $idBloqueio = $bloqueioModel->criar([
                'chave' => $chave,
                'id_locacao' => $id,
                'id_cliente' => (int) $locacao['id_cliente'],
                'id_cartao' => $idCartao,
                'id_gateway' => (int) $gatewayConfig['id'],
                'gateway_code' => $gatewayConfig['gateway_code'],
                'external_id' => $result['external_id'],
                'valor' => $valor,
                'status' => $result['status'] === 'authorized' ? 'authorized' : 'pending',
                'autorizado_em' => $result['status'] === 'authorized' ? date('Y-m-d H:i:s') : null,
                'expira_em' => $result['expires_at'] ?? null,
                'payload' => $result['raw'] ?? null,
            ]);

            // Atualizar locacao com referencia ao bloqueio
            $locacaoModel->atualizar($id, ['id_bloqueio_ativo' => $idBloqueio]);

            Response::json([
                'success' => true,
                'message' => $this->apiMessage('hold_created'),
                'data' => [
                    'id' => $idBloqueio,
                    'status' => $result['status'],
                    'external_id' => $result['external_id'],
                    'expires_at' => $result['expires_at'] ?? null,
                    'client_secret' => $result['client_secret'] ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $this->apiMessage('hold_create_error', ['message' => $e->getMessage()])], 500);
        }
    }

    /**
     * Captura um authorization hold (converte em cobranca real)
     *
     * POST /api/locacoes/{id}/bloqueio/capturar
     */
    public function capturarBloqueio(Request $request, int $id): void
    {
        try {
            if (!Auth::can('locacoes.editar')) {
                Response::json(['success' => false, 'message' => $this->apiMessage('no_permission')], 403);
                return;
            }

            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);
            if (!$locacao || empty($locacao['id_bloqueio_ativo'])) {
                Response::json(['success' => false, 'message' => $this->apiMessage('rental_without_active_hold')], 404);
                return;
            }

            $bloqueioModel = new \App\Models\LocacaoBloqueio();
            $bloqueio = $bloqueioModel->buscarPorId((int) $locacao['id_bloqueio_ativo']);
            if (!$bloqueio || $bloqueio['status'] !== 'authorized') {
                Response::json(['success' => false, 'message' => $this->apiMessage('hold_not_authorized')], 400);
                return;
            }

            $dados = $request->all();
            $valorCaptura = !empty($dados['valor'])
                ? (float) str_replace(['.', ','], ['', '.'], $dados['valor'])
                : null;

            // Instanciar gateway
            $gatewayModel = new \App\Models\GatewayPagamento();
            $gatewayConfig = $gatewayModel->buscarPorIdComCredenciais((int) $bloqueio['id_gateway']);
            if (!$gatewayConfig) {
                Response::json(['success' => false, 'message' => $this->apiMessage('gateway_not_found')], 400);
                return;
            }

            $gateway = \App\Services\Gateways\GatewayFactory::create(
                $gatewayConfig['gateway_code'],
                $gatewayConfig['credentials'] ?? [],
                $gatewayConfig['ambiente'] === 'sandbox',
                (int) $gatewayConfig['id']
            );

            $valorEfetivo = $valorCaptura ?? (float) $bloqueio['valor'];
            $motivo = $dados['motivo'] ?? 'outro';
            $idConta = !empty($dados['id_conta']) ? (int) $dados['id_conta'] : null;

            $result = $gateway->captureHold($bloqueio['external_id'], $valorCaptura);
            if (!$result['success']) {
                Response::json(['success' => false, 'message' => $result['message'] ?? 'Erro ao capturar'], 400);
                return;
            }

            // Atualizar status do bloqueio
            $bloqueioModel->atualizarStatus((int) $bloqueio['id'], 'captured', [
                'capturado_em' => date('Y-m-d H:i:s'),
                'valor_capturado' => $valorEfetivo,
                'payload' => $result['raw'] ?? null,
            ]);

            // Gerar lancamento financeiro (receita)
            $chave = $_SESSION['chave'] ?? '';
            $motivoLabels = [
                'dano' => 'Dano ao veiculo',
                'multa' => 'Multa de transito',
                'combustivel' => 'Combustivel',
                'diaria_extra' => 'Diaria(s) extra(s)',
                'outro' => 'Captura de bloqueio',
            ];
            $descricaoMotivo = $motivoLabels[$motivo] ?? $motivoLabels['outro'];

            // Buscar plano de contas Bloqueio entrada (1.1.5.01)
            $planoModel = new \App\Models\PlanoDeContas();
            $planoBloqueioEntrada = $planoModel->buscarPorHierarquia('1.1.5.01');

            $financeiroModel = new \App\Models\Financeiro();
            $financeiroModel->criar([
                'chave' => $chave,
                'tipo' => 'R',
                'pago' => 'S',
                'descricao' => $descricaoMotivo . ' - Locacao ' . ($locacao['codigo'] ?? $id),
                'id_cliente' => $locacao['id_cliente'],
                'id_conta' => $idConta,
                'id_plano_de_conta' => $planoBloqueioEntrada ? (int) $planoBloqueioEntrada['id'] : null,
                'id_locacao' => $id,
                'data_criada' => date('Y-m-d'),
                'data_venci' => date('Y-m-d'),
                'data_pago' => date('Y-m-d'),
                'valor_subtotal' => $valorEfetivo,
                'parcela' => 1,
                'total_parcelas' => 1,
            ]);

            Response::json([
                'success' => true,
                'message' => $this->apiMessage('hold_captured'),
                'data' => ['status' => 'captured', 'valor_capturado' => $valorEfetivo],
            ]);

        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $this->apiMessage('hold_capture_error', ['message' => $e->getMessage()])], 500);
        }
    }

    /**
     * Libera um authorization hold sem cobrar
     *
     * POST /api/locacoes/{id}/bloqueio/liberar
     */
    public function liberarBloqueio(Request $request, int $id): void
    {
        try {
            if (!Auth::can('locacoes.editar')) {
                Response::json(['success' => false, 'message' => $this->apiMessage('no_permission')], 403);
                return;
            }

            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);
            if (!$locacao || empty($locacao['id_bloqueio_ativo'])) {
                Response::json(['success' => false, 'message' => $this->apiMessage('rental_without_active_hold')], 404);
                return;
            }

            $bloqueioModel = new \App\Models\LocacaoBloqueio();
            $bloqueio = $bloqueioModel->buscarPorId((int) $locacao['id_bloqueio_ativo']);
            if (!$bloqueio || $bloqueio['status'] !== 'authorized') {
                Response::json(['success' => false, 'message' => $this->apiMessage('hold_not_authorized')], 400);
                return;
            }

            // Instanciar gateway
            $gatewayModel = new \App\Models\GatewayPagamento();
            $gatewayConfig = $gatewayModel->buscarPorIdComCredenciais((int) $bloqueio['id_gateway']);
            if (!$gatewayConfig) {
                Response::json(['success' => false, 'message' => $this->apiMessage('gateway_not_found')], 400);
                return;
            }

            $gateway = \App\Services\Gateways\GatewayFactory::create(
                $gatewayConfig['gateway_code'],
                $gatewayConfig['credentials'] ?? [],
                $gatewayConfig['ambiente'] === 'sandbox',
                (int) $gatewayConfig['id']
            );

            $result = $gateway->releaseHold($bloqueio['external_id']);
            if (!$result['success']) {
                Response::json(['success' => false, 'message' => $result['message'] ?? 'Erro ao liberar'], 400);
                return;
            }

            // Atualizar status do bloqueio
            $bloqueioModel->atualizarStatus((int) $bloqueio['id'], 'released', [
                'liberado_em' => date('Y-m-d H:i:s'),
                'payload' => $result['raw'] ?? null,
            ]);

            // Remover referencia na locacao
            $locacaoModel->atualizar($id, ['id_bloqueio_ativo' => null]);

            Response::json([
                'success' => true,
                'message' => $this->apiMessage('hold_released'),
                'data' => ['status' => 'released'],
            ]);

        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $this->apiMessage('hold_release_error', ['message' => $e->getMessage()])], 500);
        }
    }

    /**
     * Consulta status de um authorization hold
     *
     * GET /api/locacoes/{id}/bloqueio/status
     */
    public function statusBloqueio(Request $request, int $id): void
    {
        try {
            if (!Auth::can('locacoes.visualizar')) {
                Response::json(['success' => false, 'message' => $this->apiMessage('no_permission')], 403);
                return;
            }

            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);
            if (!$locacao) {
                Response::json(['success' => false, 'message' => $this->apiMessage('rental_not_found')], 404);
                return;
            }

            $bloqueioModel = new \App\Models\LocacaoBloqueio();
            $bloqueios = $bloqueioModel->listarPorLocacao($id);

            Response::json([
                'success' => true,
                'data' => [
                    'bloqueio_ativo_id' => $locacao['id_bloqueio_ativo'],
                    'bloqueios' => $bloqueios,
                ],
            ]);

        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $this->apiMessage('hold_lookup_error', ['message' => $e->getMessage()])], 500);
        }
    }

    // ========== CAUCAO (Deposito de Garantia) ==========

    /**
     * Registra devolucao de caucao (cria lancamento financeiro de saida)
     *
     * POST /api/locacoes/{id}/caucao/devolver
     */
    public function devolverCaucao(Request $request, int $id): void
    {
        try {
            if (!Auth::can('locacoes.editar')) {
                Response::json(['success' => false, 'message' => $this->apiMessage('no_permission')], 403);
                return;
            }

            $locacaoModel = new Locacao();
            $locacao = $locacaoModel->buscarPorId($id);
            if (!$locacao) {
                Response::json(['success' => false, 'message' => $this->apiMessage('rental_not_found')], 404);
                return;
            }

            if ((float) ($locacao['caucao_valor'] ?? 0) <= 0) {
                Response::json(['success' => false, 'message' => $this->apiMessage('rental_without_deposit')], 400);
                return;
            }

            // Verificar se ja foi devolvido
            if (!empty($locacao['caucao_data_devolucao'])) {
                Response::json(['success' => false, 'message' => $this->apiMessage('deposit_already_returned')], 400);
                return;
            }

            (new LocacaoCaucao())->devolver($id, $locacao);

            Response::json([
                'success' => true,
                'message' => $this->apiMessage('deposit_returned'),
            ]);

        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $this->apiMessage('deposit_return_error', ['message' => $e->getMessage()])], 500);
        }
    }
}
