<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Contrato;
use App\Models\ContratoCaucao;
use App\Models\ContratoVeiculo;
use App\Models\ContratoOdometro;
use App\Models\ContratoTaxaServico;
use App\Models\Grupo;
use App\Models\Veiculo;
use App\Models\VeiculoDisponibilidadeSync;
use App\Models\MatrizFilial;
use App\Models\Manutencao;
use App\Models\FormaPagamento;
use App\Models\TaxaServico;
use App\Models\ContatoEmail;
use App\Models\ContatoTelefone;
use App\Helpers\FilialHelper;
use App\Helpers\DateHelper;
use App\Helpers\PdfHelper;
use App\Models\Documento;
use App\Models\Cliente;
use App\Models\Fornecedor;
use App\Models\Checklist;
use App\Models\ChecklistModelo;
use App\Models\PlanoDeContas;
use App\Models\Whatsapp;
use App\Models\Sms;
use App\Config\Planos;
use App\I18n\TemplateRenderer;
use App\Services\AuditLogService;
use App\Services\InvoiceBatchNotificationService;
use App\Core\Database;
use App\Helpers\FileHelper;
use SimpleSoftwareIO\QrCode\Generator as QrCodeGenerator;

/**
 * Controller de Contratos
 *
 * Gerencia operacoes CRUD de contratos de locacao de veiculos.
 */
class ContratosController
{
    private array $tmpFiles = [];

    private function mensagemErroBanco(\Throwable $e, string $contexto): string
    {
        if (str_contains($e->getMessage(), 'Lock wait timeout exceeded')) {
            return "{$contexto}: o sistema esta processando outra geracao financeira no momento. Tente novamente em instantes.";
        }

        return "{$contexto}: " . $e->getMessage();
    }

    private function validarCaucaoContrato(array $dados): ?string
    {
        $valor = currency_parse($dados['caucao_valor'] ?? 0);
        if ($valor <= 0) {
            return null;
        }

        if (empty($dados['id_conta_caucao'])) {
            return 'Selecione a conta bancaria da caucao';
        }

        if (empty($dados['id_forma_pagamento_caucao'])) {
            return 'Selecione a forma de pagamento da caucao';
        }

        if (empty($dados['caucao_prazo_devolucao'])) {
            return 'Informe o prazo de devolucao da caucao';
        }

        return null;
    }

    private function diasPorContagemContrato(?string $contagem): int
    {
        return match ($contagem) {
            'semana' => 7,
            'mes' => 30,
            'ano' => 365,
            default => 1,
        };
    }

    private function calcularDiasUsoVeiculoContrato(array $veiculoContrato, ?string $dataReferencia = null): int
    {
        try {
            $saidaRaw = $veiculoContrato['data_saida'] ?? null;
            if (empty($saidaRaw)) {
                return 1;
            }

            $saida = new \DateTimeImmutable((string) $saidaRaw);
            $referencia = !empty($dataReferencia)
                ? new \DateTimeImmutable((string) $dataReferencia)
                : new \DateTimeImmutable(DateHelper::nowForDatabase());

            if ($referencia < $saida) {
                return 1;
            }

            return max(1, (int) $saida->diff($referencia)->format('%a'));
        } catch (\Throwable $e) {
            return 1;
        }
    }

    private function calcularFranquiaKmEfetiva(array $contrato, array $veiculoContrato, ?string $dataReferencia = null): int
    {
        $kmFranquia = (int) ($veiculoContrato['km_franquia'] ?? 0);
        if ($kmFranquia <= 0 || ($veiculoContrato['plano'] ?? '') !== 'KMC') {
            return 0;
        }

        $diasBase = $this->diasPorContagemContrato($contrato['contagem'] ?? null);
        $diasUso = $this->calcularDiasUsoVeiculoContrato($veiculoContrato, $dataReferencia);

        return (int) ceil(($kmFranquia / $diasBase) * $diasUso);
    }

    /**
     * Renderiza a pagina de listagem de contratos
     *
     * GET /pages/contratos
     */
    public function view(Request $request): void
    {
        $permissions = [
            'registrar_odometro' => Auth::can('contratos.editar'),
        ];

        $html = Template::render('pages.contratos.index', [
            'permissions' => $permissions,
        ]);
        Response::html($html);
    }

    /**
     * Renderiza o formulario de novo contrato
     *
     * GET /pages/contratos/adicionar
     */
    public function formView(Request $request): void
    {
        $permissions = [
            'editar_valor_taxas' => Auth::can('contratos.editar_valor_taxas'),
        ];

        $html = Template::render('pages.contratos.adicionar', [
            'permissions' => $permissions
        ]);
        Response::html($html);
    }

    /**
     * Renderiza o formulario de edicao de contrato existente
     *
     * GET /pages/contratos/editar/{id}
     */
    public function editView(Request $request, int $id): void
    {
        $contratoModel = new Contrato();
        $contrato = $contratoModel->buscarCompleto($id);

        if (!$contrato) {
            Response::redirect('/pages/contratos');
            return;
        }

        if (!FilialHelper::temAcessoFilial($contrato['id_matriz_filial_retirada'] ?? null)) {
            Response::redirect('/pages/contratos');
            return;
        }

        $permissions = [
            'editar_valor_taxas' => Auth::can('contratos.editar_valor_taxas'),
        ];

        $html = Template::render('pages.contratos.editar', [
            'contrato' => $contrato,
            'permissions' => $permissions
        ]);
        Response::html($html);
    }

    /**
     * Redirect de compatibilidade: URL antiga /adicionar/{id} -> /editar/{id}
     *
     * GET /pages/contratos/adicionar/{id}
     */
    public function redirectToEdit(Request $request, int $id): void
    {
        Response::redirect("/pages/contratos/editar/{$id}");
    }

    /**
     * Renderiza a tela de substituicao de veiculo
     *
     * GET /pages/contratos/substituir/{id}
     */
    public function substituirView(Request $request, int $id): void
    {
        if (!Auth::can('contratos.substituir')) {
            Response::redirect('/pages/contratos');
            return;
        }

        $contratoModel = new Contrato();
        $contrato = $contratoModel->buscarPorId($id);

        if (!$contrato) {
            Response::redirect('/pages/contratos');
            return;
        }

        if (!FilialHelper::temAcessoFilial($contrato['id_matriz_filial_retirada'] ?? null)) {
            Response::redirect('/pages/contratos');
            return;
        }

        if ($contrato['status'] !== 'A') {
            Response::redirect('/pages/contratos');
            return;
        }

        // Buscar veiculos ativos do contrato
        $contratoVeiculoModel = new ContratoVeiculo();
        $veiculosAtivos = $contratoVeiculoModel->listarAtivos($id);

        if (empty($veiculosAtivos)) {
            Response::redirect('/pages/contratos');
            return;
        }

        $html = Template::render('pages.contratos.substituir', [
            'contrato' => $contrato,
            'veiculosAtivos' => $veiculosAtivos,
        ]);
        Response::html($html);
    }

    /**
     * Offcanvas para adicionar/editar veiculo do contrato
     *
     * GET /pages/contratos/offcanvas-veiculo
     */
    public function offcanvasVeiculo(Request $request): void
    {
        $html = Template::render('pages.contratos.offcanvas-veiculo');
        Response::html($html);
    }

    /**
     * Offcanvas para registrar leitura de odometro durante o contrato.
     *
     * GET /pages/contratos/offcanvas-odometro?id={id}
     */
    public function offcanvasOdometro(Request $request): void
    {
        if (!Auth::can('contratos.editar')) {
            Response::html('<div class="p-4 text-sm text-red-600">Sem permissao para registrar odometro.</div>', 403);
            return;
        }

        $id = (int) $request->query('id', 0);
        $contratoModel = new Contrato();
        $contrato = $contratoModel->buscarPorId($id);

        if (!$contrato || $contrato['chave'] !== Auth::chave() || !FilialHelper::temAcessoFilial($contrato['id_matriz_filial_retirada'] ?? null)) {
            Response::html('<div class="p-4 text-sm text-red-600">Contrato nao encontrado ou acesso negado.</div>', 404);
            return;
        }

        $contratoVeiculoModel = new ContratoVeiculo();
        $odometroModel = new ContratoOdometro();
        $veiculos = $contratoVeiculoModel->listarAtivos($id);

        foreach ($veiculos as &$veiculo) {
            $ultima = $odometroModel->ultimaPorContratoVeiculo((int) $veiculo['id']);
            $odometroSaida = (int) ($veiculo['odometro_saida'] ?? 0);
            $odometroCadastro = (int) ($veiculo['veiculo_odometro'] ?? 0);
            $ultimaOdometro = (int) ($ultima['odometro'] ?? 0);
            $diasUso = $this->calcularDiasUsoVeiculoContrato($veiculo, DateHelper::nowForDatabase());

            $veiculo['ultima_leitura'] = $ultima;
            $veiculo['historico_odometros'] = $odometroModel->listarUltimosPorContratoVeiculo((int) $veiculo['id'], 5);
            $veiculo['odometro_minimo'] = max($odometroSaida, $odometroCadastro, $ultimaOdometro);
            $veiculo['km_rodado_atual'] = max(0, $veiculo['odometro_minimo'] - $odometroSaida);
            $veiculo['dias_uso'] = $diasUso;
            $veiculo['media_km_dia'] = $veiculo['km_rodado_atual'] / $diasUso;
            $veiculo['media_km_semana'] = $veiculo['media_km_dia'] * 7;
            $veiculo['media_km_mes'] = $veiculo['media_km_dia'] * 30;
            $veiculo['km_franquia_efetiva'] = $this->calcularFranquiaKmEfetiva($contrato, $veiculo, DateHelper::nowForDatabase());
        }
        unset($veiculo);

        $html = Template::render('pages.contratos.offcanvas-odometro', [
            'contrato' => $contrato,
            'veiculos' => $veiculos,
            'hoje' => DateHelper::todayForDatabase(),
        ]);
        Response::html($html);
    }

    /**
     * Lista contratos do tenant (paginado com busca)
     *
     * GET /api/contratos
     * Query params: page, perPage, search, status
     */
    public function index(Request $request): void
    {
        try {
            $chave = Auth::chave();

            // Parametros de paginacao
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');
            $status = $request->query('status', '');

            // Filtro de filiais
            [$filialWhere, $filialParams] = FilialHelper::whereContratos('c');

            $contratoModel = new Contrato();

            // Buscar contratos paginados
            $contratos = $contratoModel->listarPaginado(
                $chave,
                $page,
                $perPage,
                $search,
                $filialWhere,
                $filialParams,
                $status
            );

            // Contar total de registros
            $total = $contratoModel->contar(
                $chave,
                $search,
                $filialWhere,
                $filialParams,
                $status
            );

            // Calcular total de paginas
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $contratos,
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
                'message' => 'Erro ao buscar contratos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe um contrato especifico
     *
     * GET /api/contratos/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarCompleto($id);

            if (!$contrato) {
                Response::json([
                    'success' => false,
                    'message' => 'Contrato nao encontrado'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($contrato['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Contrato nao encontrado'
                ], 404);
                return;
            }

            // Verificar acesso a filial
            if (!FilialHelper::temAcessoFilial($contrato['id_matriz_filial_retirada'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            // A tela de edicao deve manipular apenas veiculos ativos. Historico
            // de substituicoes permanece no contrato, mas nao pode voltar ao
            // payload editavel nem entrar no calculo do formulario.
            $contrato['veiculos_historico'] = $contrato['veiculos'] ?? [];
            $contrato['veiculos'] = (new ContratoVeiculo())->listarAtivos($id);

            Response::json([
                'success' => true,
                'data' => $contrato
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar contrato: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registra leitura rapida de odometro de um veiculo ativo do contrato.
     *
     * POST /api/contratos/{id}/odometros
     */
    public function registrarOdometro(Request $request, int $id): void
    {
        try {
            if (!Auth::can('contratos.editar')) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.contratos.quick_odometer.errors.no_permission')
                ], 403);
                return;
            }

            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarPorId($id);

            if (!$contrato || $contrato['chave'] !== Auth::chave()) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.contratos.quick_odometer.errors.contract_not_found')
                ], 404);
                return;
            }

            if (!FilialHelper::temAcessoFilial($contrato['id_matriz_filial_retirada'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.contratos.quick_odometer.errors.access_denied')
                ], 403);
                return;
            }

            if (($contrato['status'] ?? '') !== 'A') {
                Response::json([
                    'success' => false,
                    'message' => t('modules.contratos.quick_odometer.errors.active_only')
                ], 422);
                return;
            }

            $dados = $request->all();
            $contratoVeiculoId = (int) ($dados['id_contrato_veiculo'] ?? 0);
            $odometro = $this->normalizarOdometroContrato($dados['odometro'] ?? 0);
            $data = DateHelper::todayForDatabase();
            $createdAt = DateHelper::nowForDatabase();
            $obs = trim((string) ($dados['obs'] ?? ''));
            $obs = $obs !== '' ? mb_substr($obs, 0, 255) : null;

            if ($contratoVeiculoId <= 0 || $odometro <= 0) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.contratos.quick_odometer.errors.invalid_fields')
                ], 422);
                return;
            }

            $contratoVeiculoModel = new ContratoVeiculo();
            $veiculoContrato = $contratoVeiculoModel->buscarPorId($contratoVeiculoId);

            if (!$veiculoContrato || (int) $veiculoContrato['id_contrato'] !== $id) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.contratos.quick_odometer.errors.vehicle_not_found')
                ], 404);
                return;
            }

            if (!empty($veiculoContrato['data_entrada'])) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.contratos.quick_odometer.errors.vehicle_returned')
                ], 422);
                return;
            }

            $odometroModel = new ContratoOdometro();
            $ultima = $odometroModel->ultimaPorContratoVeiculo($contratoVeiculoId);
            $odometroSaida = (int) ($veiculoContrato['odometro_saida'] ?? 0);
            $odometroCadastro = (int) ($veiculoContrato['veiculo_odometro'] ?? 0);
            $ultimaOdometro = (int) ($ultima['odometro'] ?? 0);
            $odometroMinimo = max($odometroSaida, $odometroCadastro, $ultimaOdometro);

            if ($odometro < $odometroMinimo) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.contratos.quick_odometer.errors.minimum_reading', [
                        'referencia' => number_format($odometroMinimo, 0, '', '.'),
                    ])
                ], 422);
                return;
            }

            $registro = $odometroModel->registrarLeitura([
                'chave' => Auth::chave(),
                'id_contrato' => $id,
                'id_contrato_veiculo' => $contratoVeiculoId,
                'id_veiculo' => (int) $veiculoContrato['id_veiculo'],
                'odometro_saida' => $odometroSaida,
                'odometro' => $odometro,
                'data' => $data,
                'obs' => $obs,
                'id_funcionario' => Auth::id(),
                'created_at' => $createdAt,
            ]);

            $kmRodado = max(0, $odometro - $odometroSaida);
            $kmFranquia = $this->calcularFranquiaKmEfetiva($contrato, $veiculoContrato, DateHelper::nowForDatabase());
            $kmExcedente = ($veiculoContrato['plano'] ?? '') === 'KMC' ? max(0, $kmRodado - $kmFranquia) : 0;
            $valorKmExcedente = (float) ($veiculoContrato['valor_km_excedente'] ?? 0);

            Response::json([
                'success' => true,
                'message' => t('modules.contratos.quick_odometer.messages.registered'),
                'data' => [
                    'registro' => $registro,
                    'odometro' => $odometro,
                    'odometro_formatado' => number_format($odometro, 0, '', '.') . ' km',
                    'data' => $data,
                    'data_formatada' => format_date(DateHelper::todayForDatabase()),
                    'created_at' => $registro['created_at'],
                    'km_rodado' => $kmRodado,
                    'km_franquia_efetiva' => $kmFranquia,
                    'km_excedente' => $kmExcedente,
                    'valor_excedente_estimado' => $kmExcedente * $valorKmExcedente,
                ],
            ]);
        } catch (\Exception $e) {
            error_log('Erro ao registrar odometro do contrato: ' . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => t('modules.contratos.quick_odometer.errors.register_failed')
            ], 500);
        }
    }

    /**
     * Corrige uma leitura intermediaria de odometro de um veiculo ativo.
     *
     * PUT /api/contratos/{id}/odometros/{leituraId}
     */
    public function editarOdometro(Request $request, int $id, int $leituraId): void
    {
        try {
            if (!Auth::can('contratos.editar')) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.contratos.quick_odometer.errors.no_permission'),
                ], 403);
                return;
            }

            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarPorId($id);
            if (!$contrato || $contrato['chave'] !== Auth::chave()) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.contratos.quick_odometer.errors.contract_not_found'),
                ], 404);
                return;
            }

            if (!FilialHelper::temAcessoFilial($contrato['id_matriz_filial_retirada'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.contratos.quick_odometer.errors.access_denied'),
                ], 403);
                return;
            }

            if (($contrato['status'] ?? '') !== 'A') {
                Response::json([
                    'success' => false,
                    'message' => t('modules.contratos.quick_odometer.errors.active_only'),
                ], 422);
                return;
            }

            $dados = $request->all();
            $contratoVeiculoId = (int) ($dados['id_contrato_veiculo'] ?? 0);
            $odometro = $this->normalizarOdometroContrato($dados['odometro'] ?? 0);
            $dataInformada = trim((string) ($dados['data'] ?? ''));
            $data = DateHelper::parse($dataInformada);
            $obs = trim((string) ($dados['obs'] ?? ''));
            $obs = $obs !== '' ? mb_substr($obs, 0, 255) : null;

            if ($contratoVeiculoId <= 0 || $leituraId <= 0 || $odometro <= 0 || $data === null || $data !== $dataInformada) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.contratos.quick_odometer.errors.invalid_fields'),
                ], 422);
                return;
            }

            $contratoVeiculoModel = new ContratoVeiculo();
            $veiculoContrato = $contratoVeiculoModel->buscarPorId($contratoVeiculoId);
            if (!$veiculoContrato || (int) $veiculoContrato['id_contrato'] !== $id) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.contratos.quick_odometer.errors.vehicle_not_found'),
                ], 404);
                return;
            }

            if (!empty($veiculoContrato['data_entrada'])) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.contratos.quick_odometer.errors.vehicle_returned'),
                ], 422);
                return;
            }

            $dataSaida = substr((string) ($veiculoContrato['data_saida'] ?? ''), 0, 10);
            $hoje = DateHelper::todayForDatabase();
            if (($dataSaida !== '' && $data < $dataSaida) || $data > $hoje) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.contratos.quick_odometer.errors.invalid_date', [
                        'inicio' => format_date($dataSaida),
                        'fim' => format_date($hoje),
                    ]),
                ], 422);
                return;
            }

            $odometroModel = new ContratoOdometro();
            $resultado = $odometroModel->editarLeitura($leituraId, [
                'id_contrato' => $id,
                'id_contrato_veiculo' => $contratoVeiculoId,
                'id_veiculo' => (int) $veiculoContrato['id_veiculo'],
                'odometro_saida' => (int) ($veiculoContrato['odometro_saida'] ?? 0),
                'data' => $data,
                'odometro' => $odometro,
                'obs' => $obs,
                'id_funcionario' => Auth::id(),
            ]);

            if (!$resultado['success']) {
                $erro = $resultado['error'] ?? 'not_found';
                $status = $erro === 'not_found' ? 404 : 422;
                $replace = isset($resultado['reference'])
                    ? ['referencia' => number_format((int) $resultado['reference'], 0, '', '.')]
                    : [];
                Response::json([
                    'success' => false,
                    'message' => t('modules.contratos.quick_odometer.errors.' . $erro, $replace),
                ], $status);
                return;
            }

            $antigo = $resultado['antigo'];
            $novo = $resultado['novo'];
            if ($resultado['alterado'] ?? true) {
                $placa = $veiculoContrato['veiculo_placa'] ?? (string) $veiculoContrato['id_veiculo'];
                $camposAlterados = array_values(array_filter([
                    AuditLogService::campo('Data', format_date($antigo['data']), format_date($novo['data']), 'Odometro'),
                    AuditLogService::campo('Odometro', number_format((int) $antigo['odometro'], 0, '', '.') . ' km', number_format((int) $novo['odometro'], 0, '', '.') . ' km', 'Odometro'),
                    AuditLogService::campo('Observacao', $antigo['obs'] ?? '-', $novo['obs'] ?? '-', 'Odometro'),
                ], static fn(array $campo): bool => $campo['de'] !== $campo['para']));

                if ($camposAlterados !== []) {
                    AuditLogService::registrarComCampos(
                        "Corrigiu leitura de odometro do contrato [{$contrato['codigo']}] - veiculo [{$placa}]",
                        $camposAlterados
                    );
                }
            }

            $historico = array_map(static function (array $item): array {
                return [
                    'id' => (int) $item['id'],
                    'data' => (string) $item['data'],
                    'data_formatada' => format_date($item['data']),
                    'odometro' => (int) $item['odometro'],
                    'odometro_formatado' => number_format((int) $item['odometro'], 0, '', '.') . ' km',
                    'diferenca' => (int) ($item['diferenca'] ?? 0),
                    'obs' => $item['obs'] ?? '',
                    'created_at' => $item['created_at'] ?? null,
                ];
            }, $resultado['historico'] ?? []);

            $ultima = $historico[0] ?? null;
            $odometroAtual = (int) ($ultima['odometro'] ?? $veiculoContrato['odometro_saida'] ?? 0);
            $kmRodado = max(0, $odometroAtual - (int) ($veiculoContrato['odometro_saida'] ?? 0));
            $kmFranquia = $this->calcularFranquiaKmEfetiva($contrato, $veiculoContrato, DateHelper::nowForDatabase());
            $kmExcedente = ($veiculoContrato['plano'] ?? '') === 'KMC' ? max(0, $kmRodado - $kmFranquia) : 0;

            Response::json([
                'success' => true,
                'message' => t('modules.contratos.quick_odometer.messages.updated'),
                'data' => [
                    'historico' => $historico,
                    'odometro' => $odometroAtual,
                    'odometro_formatado' => number_format($odometroAtual, 0, '', '.') . ' km',
                    'odometro_veiculo' => (int) $resultado['odometro_veiculo'],
                    'ultima_leitura' => $ultima,
                    'km_rodado' => $kmRodado,
                    'km_franquia_efetiva' => $kmFranquia,
                    'km_excedente' => $kmExcedente,
                    'valor_excedente_estimado' => $kmExcedente * (float) ($veiculoContrato['valor_km_excedente'] ?? 0),
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('Erro ao corrigir odometro do contrato: ' . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => t('modules.contratos.quick_odometer.errors.update_failed'),
            ], 500);
        }
    }

    /**
     * Cria um novo contrato
     *
     * POST /contratos/salvar
     */
    public function store(Request $request): void
    {
        try {
            $dados = $request->all();
            $dados['chave'] = Auth::chave();
            $dados['id_funcionario'] = Auth::id();

            // Validacao basica
            if (empty($dados['id_cliente'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Cliente e obrigatorio'
                ], 400);
                return;
            }

            if (empty($dados['data_ini']) || empty($dados['data_fim'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Data de inicio e fim sao obrigatorias'
                ], 400);
                return;
            }

            $contratoModel = new Contrato();

            $erroCaucao = $this->validarCaucaoContrato($dados);
            if ($erroCaucao !== null) {
                Response::json([
                    'success' => false,
                    'message' => $erroCaucao
                ], 400);
                return;
            }

            // Processar arrays JSON
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

            // Criar contrato
            $id = $contratoModel->criarComAuditoria($dados);
            $contratoCriado = $contratoModel->buscarPorId($id);
            if ($contratoCriado) {
                (new ContratoCaucao())->sincronizarAtiva($id, $dados, $contratoCriado);
            }

            // Adicionar veiculos se enviados
            if (!empty($dados['veiculos']) && is_array($dados['veiculos'])) {
                $veiculoModel = new ContratoVeiculo();
                $disponibilidadeSync = new VeiculoDisponibilidadeSync();
                $dataSaidaInicial = $contratoCriado['data_ini'] ?? $dados['data_ini'];
                foreach ($dados['veiculos'] as $veiculo) {
                    if (empty($veiculo['id_veiculo'])) {
                        continue;
                    }
                    $veiculo['chave'] = $dados['chave'];
                    $veiculo['id_contrato'] = $id;
                    $veiculo['data_saida'] = $dataSaidaInicial;
                    $veiculoModel->adicionar($veiculo);

                    // Marcar veiculo como locado
                    $disponibilidadeSync->marcarLocado((int) $veiculo['id_veiculo']);
                }
            }

            // Adicionar taxas
            $taxaModel = new ContratoTaxaServico();
            $taxaServicoModel = new TaxaServico();

            // Se nao ha taxas enviadas, buscar taxas com aplicar='S'
            if (empty($dados['taxas']) || !is_array($dados['taxas'])) {
                $filialId = $dados['id_matriz_filial_retirada'] ?? null;
                $taxasAuto = $taxaServicoModel->listarAutoAplicar($dados['chave'], $filialId ? (int) $filialId : null);

                $dados['taxas'] = array_map(function ($t) {
                    return [
                        'id_taxa' => $t['id'],
                        'nome' => $t['nome'],
                        'base_calculo' => $t['base_calculo'],
                        'tipo_valor' => $t['tipo_valor'],
                        'quantidade' => 1,
                        'valor_unitario' => $t['valor'],
                    ];
                }, $taxasAuto);
            }

            if (!empty($dados['taxas']) && is_array($dados['taxas'])) {
                // Validar valores de taxas - usar valores do cadastro se sem permissao
                if (!Auth::can('contratos.editar_valor_taxas')) {
                    $filialRetirada = isset($dados['id_matriz_filial_retirada'])
                        ? (int) $dados['id_matriz_filial_retirada'] : null;
                    foreach ($dados['taxas'] as $index => $taxa) {
                        if (!empty($taxa['id_taxa'])) {
                            $taxaOriginal = $taxaServicoModel->buscarPorId((int) $taxa['id_taxa']);
                            if ($taxaOriginal) {
                                $dados['taxas'][$index]['nome'] = $taxaOriginal['nome'];
                                $dados['taxas'][$index]['valor_unitario'] = $taxaServicoModel->resolverValor($taxaOriginal, $filialRetirada);
                                $dados['taxas'][$index]['base_calculo'] = $taxaOriginal['base_calculo'];
                                $dados['taxas'][$index]['tipo_valor'] = $taxaOriginal['tipo_valor'];
                            }
                        }
                    }
                }

                $taxaModel->sincronizar($id, $dados['taxas'], $dados['chave']);
            }

            // Recalcular totais
            $contratoModel->recalcularTotais($id);

            // Disparar mensageria para o cliente (contract_confirmation)
            try {
                $contratoCriado = $contratoModel->buscarPorId($id);
                $clienteModel = new Cliente();
                $cliente = $clienteModel->buscarPorIdComContatos((int) $dados['id_cliente']);
                $filialModel = new MatrizFilial();
                $empresa = $filialModel->buscarPorId((int) ($dados['id_matriz_filial_retirada'] ?? $_SESSION['id_matriz_filial'] ?? 0));

                // Dados do primeiro veiculo (para template)
                $veiculoDados = null;
                if (!empty($dados['veiculos'][0]['id_veiculo'])) {
                    $veiculoMsgModel = new Veiculo();
                    $veiculoDados = $veiculoMsgModel->buscarPorId((int) $dados['veiculos'][0]['id_veiculo']);
                }

                if ($cliente && $empresa && $contratoCriado) {
                    $context = [
                        'cliente' => $cliente,
                        'empresa' => $empresa,
                        'id_matriz_filial' => (int) ($dados['id_matriz_filial_retirada'] ?? $_SESSION['id_matriz_filial'] ?? 0),
                        'contrato' => [
                            'numero'      => $contratoCriado['codigo'],
                            'data_inicio' => $contratoCriado['data_ini'],
                            'data_fim'    => $contratoCriado['data_fim'],
                            'valor_total' => $contratoCriado['valor_total'] ?? 0,
                        ],
                        'veiculo' => $veiculoDados ?? [],
                    ];

                    foreach (['email', 'whatsapp', 'sms'] as $canal) {
                        try {
                            queue_template_message('contract_confirmation', $canal, $context);
                        } catch (\Throwable $e) {
                            error_log("Erro ao enfileirar contract_confirmation/{$canal}: " . $e->getMessage());
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Falha na mensageria nao deve impedir criacao do contrato
                error_log('Erro ao enviar notificacao de contrato: ' . $e->getMessage());
            }

            Response::json([
                'success' => true,
                'message' => 'Contrato criado com sucesso',
                'data' => ['id' => $id]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar contrato: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza um contrato
     *
     * POST /contratos/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarPorId($id);

            if (!$contrato) {
                Response::json([
                    'success' => false,
                    'message' => 'Contrato nao encontrado'
                ], 404);
                return;
            }

            // Verificar tenant
            $chave = Auth::chave();
            if ($contrato['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode editar este contrato'
                ], 403);
                return;
            }

            // Verificar acesso a filial
            if (!FilialHelper::temAcessoFilial($contrato['id_matriz_filial_retirada'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            $dados = $request->all();

            $erroCaucao = $this->validarCaucaoContrato($dados);
            if ($erroCaucao !== null) {
                Response::json([
                    'success' => false,
                    'message' => $erroCaucao
                ], 400);
                return;
            }

            // Processar arrays JSON
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

            // Extrair dados de auditoria antes de atualizar (log unificado apos processar veiculos)
            $auditChanges = $dados['_audit_changes'] ?? null;
            unset($dados['_audit_data'], $dados['_audit_changes'], $dados['_audit_initial']);

            // Atualizar contrato
            $contratoModel->atualizar($id, $dados);
            $contratoAtualizado = $contratoModel->buscarPorId($id);
            if ($contratoAtualizado) {
                (new ContratoCaucao())->sincronizarAtiva($id, $dados, $contratoAtualizado);
            }
            $todosVeiculoChanges = [];

            // Adicionar novos veiculos se enviados
            if (!empty($dados['veiculos']) && is_array($dados['veiculos'])) {
                $veiculoModel = new ContratoVeiculo();
                $veiculoModelGeral = new Veiculo();

                // Buscar veiculos atualmente ativos no contrato
                $veiculosAtivos = $veiculoModel->listarAtivos($id);
                $idsAtivos = array_map('intval', array_column($veiculosAtivos, 'id_veiculo'));
                $veiculosDoContrato = $veiculoModel->listarPorContrato($id);
                $idsDoContrato = array_map('intval', array_column($veiculosDoContrato, 'id_veiculo'));

                foreach ($dados['veiculos'] as $veiculo) {
                    if (empty($veiculo['id_veiculo'])) {
                        continue;
                    }

                    $idVeiculo = (int) $veiculo['id_veiculo'];

                    // So adicionar veiculos NOVOS (que nao estao ativos no contrato)
                    if (!in_array($idVeiculo, $idsAtivos, true)) {
                        // Se o veiculo ja existe no contrato apenas como historico
                        // (substituido/devolvido), nao recriar como ativo via edicao.
                        if (in_array($idVeiculo, $idsDoContrato, true)) {
                            continue;
                        }

                        // Verificar se veiculo nao esta alugado em outro contrato
                        $alugado = $veiculoModel->veiculoEstaAlugado($idVeiculo, $id);
                        if ($alugado) {
                            continue;
                        }

                        $veiculo['chave'] = $chave;
                        $veiculo['id_contrato'] = $id;
                        $veiculoModel->adicionar($veiculo);

                        // Marcar veiculo como locado
                        (new VeiculoDisponibilidadeSync())->marcarLocado($idVeiculo);

                        // Log de auditoria
                        $infoVeiculo = $veiculoModelGeral->buscarPorId($idVeiculo);
                        $placa = $infoVeiculo['placa'] ?? 'N/A';
                        $marcaModelo = trim(($infoVeiculo['marca'] ?? '') . ' ' . ($infoVeiculo['modelo'] ?? ''));
                        AuditLogService::registrar(
                            ($_SESSION['user_name'] ?? 'Sistema') . ", adicionou veiculo [{$placa} - {$marcaModelo}] ao contrato [{$contrato['codigo']}]"
                        );
                    } else {
                        // Atualizar veiculo existente (plano, valores, seguros, etc.)
                        $registroAtivo = current(array_filter(
                            $veiculosAtivos,
                            fn($v) => (int) $v['id_veiculo'] === $idVeiculo
                        ));
                        if ($registroAtivo) {
                            // Nao permitir troca de grupo/veiculo em veiculo ja salvo
                            unset($veiculo['id_grupo'], $veiculo['id_veiculo']);
                            $veiculoModel->atualizar((int) $registroAtivo['id'], $veiculo);

                            // Log de auditoria com campos alterados
                            $placa = $registroAtivo['veiculo_placa'] ?? 'N/A';
                            $marcaModelo = trim(($registroAtivo['veiculo_marca'] ?? '') . ' ' . ($registroAtivo['veiculo_modelo'] ?? ''));

                            $camposParaComparar = [
                                'plano' => 'Plano',
                                'valor_plano_km_pago' => 'Valor Km Pago',
                                'valor_plano_km_controlado' => 'Valor Km Controlado',
                                'valor_plano_km_livre' => 'Valor Km Livre',
                                'valor_km_excedente' => 'Valor Km Excedente',
                                'km_franquia' => 'Km Franquia',
                                'seguro_carro' => 'Seguro Veículo',
                                'valor_seguro_carro' => 'Valor Seguro Veículo',
                                'seguro_terceiros' => 'Seguro Terceiros',
                                'valor_seguro_terceiros' => 'Valor Seguro Terceiros',
                                'odometro_saida' => 'Odômetro Saída',
                                'combustivel_saida' => 'Combustível Saída',
                            ];

                            $camposDecimais = [
                                'valor_plano_km_pago', 'valor_plano_km_controlado', 'valor_plano_km_livre',
                                'valor_km_excedente', 'valor_seguro_carro', 'valor_seguro_terceiros',
                                'km_franquia', 'odometro_saida',
                            ];

                            $camposAlterados = [];
                            foreach ($camposParaComparar as $campo => $label) {
                                $antigo = $registroAtivo[$campo] ?? null;
                                $novo = $veiculo[$campo] ?? null;

                                // Normalizar decimais para comparacao (BD: "595.00" vs frontend: 595)
                                if (in_array($campo, $camposDecimais, true)) {
                                    if ((float) $antigo === (float) $novo) continue;
                                } else {
                                    if ((string) $antigo === (string) $novo) continue;
                                }

                                $camposAlterados[] = AuditLogService::campo($label, $antigo, $novo);
                            }

                            if (!empty($camposAlterados)) {
                                $todosVeiculoChanges = array_merge($todosVeiculoChanges, $camposAlterados);
                            }
                        }
                    }
                }
            }

            // Sincronizar taxas se enviadas
            if (isset($dados['taxas']) && is_array($dados['taxas'])) {
                $taxaModel = new ContratoTaxaServico();

                // Validar valores de taxas - manter originais se sem permissao
                if (!Auth::can('contratos.editar_valor_taxas')) {
                    $taxasOriginais = $taxaModel->listarPorContrato($id);
                    $taxaServicoModel = new TaxaServico();

                    // Criar mapa por id_taxa para buscar rapidamente
                    $mapaTaxasOriginais = [];
                    foreach ($taxasOriginais as $t) {
                        if (!empty($t['id_taxa'])) {
                            $mapaTaxasOriginais[$t['id_taxa']] = $t;
                        }
                    }

                    // Para todas as taxas, usar valores originais do cadastro ou do contrato
                    foreach ($dados['taxas'] as $index => $taxa) {
                        if (!empty($taxa['id_taxa'])) {
                            // Taxa ja existe no contrato - usar valores do contrato
                            if (isset($mapaTaxasOriginais[$taxa['id_taxa']])) {
                                $original = $mapaTaxasOriginais[$taxa['id_taxa']];
                                $dados['taxas'][$index]['nome'] = $original['nome'];
                                $dados['taxas'][$index]['quantidade'] = $original['quantidade'];
                                $dados['taxas'][$index]['valor_unitario'] = $original['valor_unitario'];
                                $dados['taxas'][$index]['base_calculo'] = $original['base_calculo'];
                                $dados['taxas'][$index]['tipo_valor'] = $original['tipo_valor'];
                            } else {
                                // Taxa nova - buscar valores do cadastro (resolvendo valor por filial)
                                $taxaOriginal = $taxaServicoModel->buscarPorId((int) $taxa['id_taxa']);
                                if ($taxaOriginal) {
                                    $filialRetirada = isset($dados['id_matriz_filial_retirada'])
                                        ? (int) $dados['id_matriz_filial_retirada'] : null;
                                    $dados['taxas'][$index]['nome'] = $taxaOriginal['nome'];
                                    $dados['taxas'][$index]['valor_unitario'] = $taxaServicoModel->resolverValor($taxaOriginal, $filialRetirada);
                                    $dados['taxas'][$index]['base_calculo'] = $taxaOriginal['base_calculo'];
                                    $dados['taxas'][$index]['tipo_valor'] = $taxaOriginal['tipo_valor'];
                                }
                            }
                        }
                    }
                }

                $taxaModel->sincronizar($id, $dados['taxas'], $chave);
            }

            // Log unificado: contrato + veiculos
            $camposUnificados = [];
            if ($auditChanges) {
                $decoded = json_decode($auditChanges, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $decoded = json_decode(stripslashes($auditChanges), true);
                }
                if (is_array($decoded) && !empty($decoded)) {
                    $camposUnificados = $decoded;
                }
            }
            if (!empty($todosVeiculoChanges)) {
                $camposUnificados['Veículos'] = $todosVeiculoChanges;
            }
            if (!empty($camposUnificados)) {
                AuditLogService::registrarComCampos(
                    ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou o contrato [{$contrato['codigo']}]",
                    $camposUnificados
                );
            }

            // Recalcular totais
            $contratoModel->recalcularTotais($id);

            Response::json([
                'success' => true,
                'message' => 'Contrato atualizado com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar contrato: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui um contrato
     *
     * POST /contratos/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarPorId($id);

            if (!$contrato) {
                Response::json([
                    'success' => false,
                    'message' => 'Contrato nao encontrado'
                ], 404);
                return;
            }

            // Verificar tenant
            $chave = Auth::chave();
            if ($contrato['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode excluir este contrato'
                ], 403);
                return;
            }

            // Verificar acesso a filial
            if (!FilialHelper::temAcessoFilial($contrato['id_matriz_filial_retirada'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            $contratoModel->deletarComAuditoria($id);

            Response::json([
                'success' => true,
                'message' => 'Contrato excluido com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir contrato: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Renderiza a tela de devolucao de veiculo(s)
     *
     * GET /pages/contratos/devolver/{id}
     */
    public function devolverView(Request $request, int $id): void
    {
        if (!Auth::can('contratos.devolver')) {
            Response::redirect('/pages/contratos');
            return;
        }

        $contratoModel = new Contrato();
        $contrato = $contratoModel->buscarPorId($id);

        if (!$contrato) {
            Response::redirect('/pages/contratos');
            return;
        }

        if (!FilialHelper::temAcessoFilial($contrato['id_matriz_filial_retirada'] ?? null)) {
            Response::redirect('/pages/contratos');
            return;
        }

        if ($contrato['status'] !== 'A') {
            Response::redirect('/pages/contratos');
            return;
        }

        $contratoVeiculoModel = new ContratoVeiculo();
        $veiculosAtivos = $contratoVeiculoModel->listarAtivos($id);

        if (empty($veiculosAtivos)) {
            Response::redirect('/pages/contratos');
            return;
        }

        $resumoFinanceiro = $contratoModel->resumoFinanceiroContrato($id);

        $html = Template::render('pages.contratos.devolver', [
            'contrato' => $contrato,
            'veiculosAtivos' => $veiculosAtivos,
            'resumoFinanceiro' => $resumoFinanceiro,
        ]);
        Response::html($html);
    }

    /**
     * Registra devolucao de veiculo(s)
     *
     * POST /contratos/{id}/devolver
     * Aceita batch: { veiculos: [...] } ou legado: { id_contrato_veiculo, ... }
     */
    public function devolver(Request $request, int $id): void
    {
        try {
            if (!Auth::can('contratos.devolver')) {
                Response::json([
                    'success' => false,
                    'message' => 'Sem permissao para registrar devolucao'
                ], 403);
                return;
            }

            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarPorId($id);

            if (!$contrato) {
                Response::json([
                    'success' => false,
                    'message' => 'Contrato nao encontrado'
                ], 404);
                return;
            }

            // Verificar tenant e acesso
            $chave = Auth::chave();
            if ($contrato['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            if (!FilialHelper::temAcessoFilial($contrato['id_matriz_filial_retirada'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            if (($contrato['status'] ?? '') !== 'A') {
                Response::json([
                    'success' => false,
                    'message' => 'Contrato nao esta ativo para devolucao'
                ], 400);
                return;
            }

            $dados = $request->all();
            $veiculoModel = new ContratoVeiculo();
            $veiculoModelGeral = new Veiculo();
            $contratoTaxaModel = new ContratoTaxaServico();
            $taxaServicoModel = new TaxaServico();

            // Normalizar para array de veiculos (batch ou legado)
            $veiculos = [];
            if (!empty($dados['veiculos']) && is_array($dados['veiculos'])) {
                $veiculos = $dados['veiculos'];
            } elseif (!empty($dados['id_contrato_veiculo'])) {
                // Compatibilidade com formato legado
                $veiculoLegado = [
                    'id_contrato_veiculo' => $dados['id_contrato_veiculo'],
                    'odometro_entrada' => $dados['odometro_entrada'] ?? 0,
                    'combustivel_entrada' => $dados['combustivel_entrada'] ?? null,
                    'acao_veiculo' => 'disponivel',
                    'observacao' => $dados['motivo_saida'] ?? null,
                ];

                if (array_key_exists('data_entrada', $dados)) {
                    $veiculoLegado['data_entrada'] = $dados['data_entrada'];
                }

                $veiculos = [$veiculoLegado];
            }

            if (empty($veiculos)) {
                Response::json([
                    'success' => false,
                    'message' => 'Nenhum veiculo informado'
                ], 400);
                return;
            }

            // Validar e normalizar as acoes antes de registrar qualquer devolucao do lote.
            foreach ($veiculos as $indice => $vData) {
                $acaoVeiculo = ($vData['acao_veiculo'] ?? 'disponivel') === 'criar_os'
                    ? 'criar_os'
                    : 'disponivel';
                $observacao = trim((string) ($vData['observacao'] ?? $vData['motivo_saida'] ?? ''));

                if ($acaoVeiculo === 'criar_os' && $observacao === '') {
                    Response::json([
                        'success' => false,
                        'message' => t('modules.contratos.return_page.inform_os_reason')
                    ], 422);
                    return;
                }

                if (mb_strlen($observacao) > 255) {
                    Response::json([
                        'success' => false,
                        'message' => t('modules.contratos.return_page.observation_too_long')
                    ], 422);
                    return;
                }

                $veiculos[$indice]['acao_veiculo'] = $acaoVeiculo;
                $veiculos[$indice]['observacao'] = $observacao !== '' ? $observacao : null;
            }

            $validarFinanceiro = !empty($dados['gerar_financeiro']);
            $idContaFinanceiro = (int) ($dados['id_conta'] ?? 0);
            $idFormaPagamentoFinanceiro = (int) ($dados['id_forma_pagamento'] ?? 0);
            $dataVencimentoFinanceiro = $this->normalizarDataFinanceiroContrato($dados['data_venci'] ?? null);
            $pagoFinanceiro = ($dados['pago'] ?? 'N') === 'S' ? 'S' : 'N';
            $dataPagamentoFinanceiro = $pagoFinanceiro === 'S'
                ? $this->normalizarDataFinanceiroContrato($dados['data_pago'] ?? null)
                : null;

            if ($validarFinanceiro) {
                if ($idContaFinanceiro <= 0) {
                    Response::json([
                        'success' => false,
                        'message' => 'Selecione a conta bancaria para gerar o financeiro'
                    ], 422);
                    return;
                }

                if ($idFormaPagamentoFinanceiro <= 0) {
                    Response::json([
                        'success' => false,
                        'message' => 'Selecione a forma de pagamento para gerar o financeiro'
                    ], 422);
                    return;
                }

                if ($dataVencimentoFinanceiro === null) {
                    Response::json([
                        'success' => false,
                        'message' => 'Informe um vencimento valido para gerar o financeiro'
                    ], 422);
                    return;
                }

                if ($pagoFinanceiro === 'S' && $dataPagamentoFinanceiro === null) {
                    Response::json([
                        'success' => false,
                        'message' => 'Informe uma data de pagamento valida'
                    ], 422);
                    return;
                }
            }

            $resultados = [];
            $ultimaDataEntrada = null;
            $totalCobrancaDevolucao = 0.0;
            $idsVeiculosDevolvidos = [];
            $manutencaoModel = new Manutencao();

            foreach ($veiculos as $vData) {
                $idCv = (int) ($vData['id_contrato_veiculo'] ?? 0);

                // Verificar se veiculo pertence ao contrato
                $veiculoContrato = $veiculoModel->buscarPorId($idCv);
                if (!$veiculoContrato || (int) $veiculoContrato['id_contrato'] !== $id) {
                    continue;
                }

                $dataEntrada = null;
                if (array_key_exists('data_entrada', $vData)) {
                    $dataEntrada = $this->normalizarDataEntradaContrato($vData['data_entrada']);
                    if ($dataEntrada === null) {
                        Response::json([
                            'success' => false,
                            'message' => 'Data/hora de devolucao invalida'
                        ], 422);
                        return;
                    }

                    if (!empty($veiculoContrato['data_saida']) && strtotime($dataEntrada) < strtotime((string) $veiculoContrato['data_saida'])) {
                        $saidaFormatada = DateHelper::formatOperationalDateTime((string) $veiculoContrato['data_saida']);
                        $entradaFormatada = DateHelper::formatOperationalDateTime($dataEntrada);
                        Response::json([
                            'success' => false,
                            'message' => "A devolucao nao pode ser anterior a saida do veiculo. Saida: {$saidaFormatada}. Devolucao informada: {$entradaFormatada}."
                        ], 422);
                        return;
                    }

                }

                $odometroEntrada = $this->normalizarOdometroContrato($vData['odometro_entrada'] ?? 0);
                $odometroMinimo = max(
                    (int) ($veiculoContrato['odometro_saida'] ?? 0),
                    (int) ($veiculoContrato['veiculo_odometro'] ?? 0)
                );
                if ($odometroEntrada > 0 && $odometroEntrada < $odometroMinimo) {
                    Response::json([
                        'success' => false,
                        'message' => 'Odometro de devolucao nao pode ser menor que ' . number_format($odometroMinimo, 0, '', '.') . ' km'
                    ], 422);
                    return;
                }
                $combustivelEntrada = isset($vData['combustivel_entrada']) && $vData['combustivel_entrada'] !== '' && $vData['combustivel_entrada'] !== null
                    ? (int) $vData['combustivel_entrada']
                    : null;
                $observacao = $vData['observacao'] ?? $vData['motivo_saida'] ?? null;

                $dataEntradaEfetiva = $dataEntrada ?: DateHelper::nowForDatabase();
                if ($ultimaDataEntrada === null || $dataEntradaEfetiva > $ultimaDataEntrada) {
                    $ultimaDataEntrada = $dataEntradaEfetiva;
                }

                // 1. Registrar devolucao
                $veiculoModel->devolver($idCv, $odometroEntrada, $combustivelEntrada, $observacao, $dataEntradaEfetiva);
                $veiculoModelGeral->atualizarOdometro((int) $veiculoContrato['id_veiculo'], $odometroEntrada);
                $idsVeiculosDevolvidos[] = (int) $veiculoContrato['id_veiculo'];

                // 2. Criar OS e atualizar disponibilidade do veiculo
                $acaoVeiculo = $vData['acao_veiculo'] ?? 'disponivel';
                $manutencaoId = null;
                $manutencaoOs = null;

                if ($acaoVeiculo === 'criar_os') {
                    $manutencaoId = $manutencaoModel->criar([
                        'chave' => $chave,
                        'id_veiculo' => (int) $veiculoContrato['id_veiculo'],
                        'data_enviado' => $dataEntradaEfetiva,
                        'odo_enviado' => $odometroEntrada,
                        'tanque_enviado' => $combustivelEntrada,
                        'motivo' => $observacao,
                        'status' => 'C',
                    ]);
                    $manutencaoCriada = $manutencaoModel->buscarPorId($manutencaoId);
                    $manutencaoOs = $manutencaoCriada['os'] ?? null;

                    AuditLogService::registrar(
                        ($_SESSION['user_name'] ?? 'Sistema')
                        . ", criou a OS de manutencao [{$manutencaoOs}] na devolucao do veiculo [{$veiculoContrato['veiculo_placa']}]"
                    );
                }

                $statusVeiculo = $acaoVeiculo === 'criar_os' ? 'M' : 'D';
                (new VeiculoDisponibilidadeSync())->liberarSeSemVinculoAtivo((int) $veiculoContrato['id_veiculo'], $statusVeiculo);

                // 3. Calcular e criar taxas (mesmo padrao de substituir)
                $placa = $veiculoContrato['veiculo_placa'] ?? '';
                $totalKmCobranca = 0;
                $totalCombustivelCobranca = 0;

                // Buscar valor_por_fracao do veiculo
                $veiculoCompleto = $veiculoModelGeral->buscarPorId((int) $veiculoContrato['id_veiculo']);
                $valorPorFracao = (float) ($veiculoCompleto['valor_por_fracao'] ?? 0);

                // 3a. Calculo de combustivel
                $combustivelSaida = (int) ($veiculoContrato['combustivel_saida'] ?? 0);
                $diferencaFracoes = $combustivelEntrada !== null ? max(0, $combustivelSaida - $combustivelEntrada) : 0;

                if ($diferencaFracoes > 0 && $valorPorFracao > 0) {
                    $totalCombustivelCobranca = $diferencaFracoes * $valorPorFracao;
                    $contratoTaxaModel->adicionar($id, [
                        'nome' => "Diferenca combustivel - Devolucao [{$placa}]",
                        'base_calculo' => 'FIX',
                        'tipo_valor' => 'MON',
                        'quantidade' => $diferencaFracoes,
                        'valor_unitario' => $valorPorFracao,
                    ], $chave);
                }

                // 3b. Calculo de km (depende do plano do veiculo)
                $plano = $veiculoContrato['plano'] ?? 'KL';
                $odometroSaida = (int) ($veiculoContrato['odometro_saida'] ?? 0);
                $kmRodados = max(0, $odometroEntrada - $odometroSaida);
                $valorKmExcedente = (float) ($veiculoContrato['valor_km_excedente'] ?? 0);

                if ($plano === 'KMC') {
                    $kmFranquia = $this->calcularFranquiaKmEfetiva($contrato, $veiculoContrato, $dataEntradaEfetiva);
                    $kmExcedente = max(0, $kmRodados - $kmFranquia);
                    if ($kmExcedente > 0 && $valorKmExcedente > 0) {
                        $totalKmCobranca = $kmExcedente * $valorKmExcedente;
                        $contratoTaxaModel->adicionar($id, [
                            'nome' => "Km excedente - Devolucao [{$placa}]",
                            'base_calculo' => 'FIX',
                            'tipo_valor' => 'MON',
                            'quantidade' => $kmExcedente,
                            'valor_unitario' => $valorKmExcedente,
                        ], $chave);
                    }
                } elseif ($plano === 'KP') {
                    if ($kmRodados > 0 && $valorKmExcedente > 0) {
                        $totalKmCobranca = $kmRodados * $valorKmExcedente;
                        $contratoTaxaModel->adicionar($id, [
                            'nome' => "Km rodados - Devolucao [{$placa}]",
                            'base_calculo' => 'FIX',
                            'tipo_valor' => 'MON',
                            'quantidade' => $kmRodados,
                            'valor_unitario' => $valorKmExcedente,
                        ], $chave);
                    }
                }

                // 4. Log de auditoria por veiculo
                $marcaModelo = trim(($veiculoContrato['veiculo_marca'] ?? '') . ' ' . ($veiculoContrato['veiculo_modelo'] ?? ''));
                AuditLogService::registrar(
                    ($_SESSION['user_name'] ?? 'Sistema') . ", registrou devolucao de veiculo [{$placa} - {$marcaModelo}] no contrato [{$contrato['codigo']}]"
                );

                $resultados[] = [
                    'id_contrato_veiculo' => $idCv,
                    'placa' => $placa,
                    'total_km' => $totalKmCobranca,
                    'total_combustivel' => $totalCombustivelCobranca,
                    'id_manutencao' => $manutencaoId,
                    'os' => $manutencaoOs,
                ];
                $totalCobrancaDevolucao += $totalKmCobranca + $totalCombustivelCobranca;
            }

            if (empty($resultados)) {
                Response::json([
                    'success' => false,
                    'message' => 'Nenhum veiculo valido para devolucao'
                ], 400);
                return;
            }

            $taxasExtrasCriadas = [];
            if (!empty($dados['taxas_extras']) && is_array($dados['taxas_extras'])) {
                $filialRetirada = !empty($contrato['id_matriz_filial_retirada'])
                    ? (int) $contrato['id_matriz_filial_retirada']
                    : null;

                foreach ($dados['taxas_extras'] as $taxaExtra) {
                    $idTaxa = (int) ($taxaExtra['id_taxa'] ?? 0);
                    if ($idTaxa <= 0) {
                        continue;
                    }

                    $taxaOriginal = $taxaServicoModel->buscarPorId($idTaxa);
                    if (!$taxaOriginal || ($taxaOriginal['chave'] ?? '') !== $chave) {
                        continue;
                    }

                    $filiaisTaxa = $taxaOriginal['filiais'] ?? [];
                    if ($filialRetirada && !empty($filiaisTaxa)) {
                        $idsFiliaisTaxa = array_map(static fn($filial) => (int) ($filial['id'] ?? 0), $filiaisTaxa);
                        if (!in_array($filialRetirada, $idsFiliaisTaxa, true)) {
                            continue;
                        }
                    }

                    $quantidade = max(1, (int) ($taxaExtra['quantidade'] ?? 1));
                    $valorUnitario = Auth::can('contratos.editar_valor_taxas')
                        ? currency_parse($taxaExtra['valor_unitario'] ?? $taxaOriginal['valor'])
                        : $taxaServicoModel->resolverValor($taxaOriginal, $filialRetirada);

                    if ($valorUnitario <= 0) {
                        continue;
                    }

                    $contratoTaxaModel->adicionar($id, [
                        'id_taxa' => $idTaxa,
                        'nome' => $taxaOriginal['nome'],
                        'base_calculo' => $taxaOriginal['base_calculo'],
                        'tipo_valor' => $taxaOriginal['tipo_valor'],
                        'quantidade' => $quantidade,
                        'valor_unitario' => $valorUnitario,
                    ], $chave);

                    $totalTaxa = $quantidade * $valorUnitario;
                    $totalCobrancaDevolucao += $totalTaxa;
                    $taxasExtrasCriadas[] = [
                        'id_taxa' => $idTaxa,
                        'nome' => $taxaOriginal['nome'],
                        'quantidade' => $quantidade,
                        'valor_unitario' => $valorUnitario,
                        'valor_total' => $totalTaxa,
                    ];
                }
            }

            // 5. Recalcular totais do contrato (inclui novas taxas)
            $contratoModel->recalcularTotais($id);

            $idFinanceiroDevolucao = null;
            if ($totalCobrancaDevolucao > 0) {
                if ($idContaFinanceiro <= 0) {
                    Response::json([
                        'success' => false,
                        'message' => 'Selecione a conta bancaria para gerar o financeiro'
                    ], 422);
                    return;
                }

                if ($idFormaPagamentoFinanceiro <= 0) {
                    Response::json([
                        'success' => false,
                        'message' => 'Selecione a forma de pagamento para gerar o financeiro'
                    ], 422);
                    return;
                }

                if ($dataVencimentoFinanceiro === null) {
                    Response::json([
                        'success' => false,
                        'message' => 'Informe um vencimento valido para gerar o financeiro'
                    ], 422);
                    return;
                }

                if ($pagoFinanceiro === 'S' && $dataPagamentoFinanceiro === null) {
                    Response::json([
                        'success' => false,
                        'message' => 'Informe uma data de pagamento valida'
                    ], 422);
                    return;
                }

                $idsVeiculosUnicos = array_values(array_unique($idsVeiculosDevolvidos));
                $idFinanceiroDevolucao = $contratoModel->criarFinanceiroDevolucao($id, [
                    'valor_total' => $totalCobrancaDevolucao,
                    'id_conta' => $idContaFinanceiro,
                    'id_forma_pagamento' => $idFormaPagamentoFinanceiro,
                    'data_venci' => $dataVencimentoFinanceiro,
                    'pago' => $pagoFinanceiro,
                    'data_pago' => $dataPagamentoFinanceiro,
                    'id_veiculo' => count($idsVeiculosUnicos) === 1 ? $idsVeiculosUnicos[0] : null,
                    'descricao' => "Contrato #{$contrato['codigo']} - Devolucao",
                ], $chave);
            }

            // 6. Verificar se ainda ha veiculos ativos
            $veiculosAtivosCount = $veiculoModel->contarAtivos($id);
            if ($veiculosAtivosCount === 0) {
                $contratoModel->atualizarStatus($id, 'F', [
                    'data_fim' => $ultimaDataEntrada ?? DateHelper::nowForDatabase()
                ]);
            }

            $qtd = count($resultados);
            Response::json([
                'success' => true,
                'message' => $qtd . ' devolucao(oes) registrada(s) com sucesso',
                'data' => [
                    'veiculos_ativos' => $veiculosAtivosCount,
                    'devolvidos' => $resultados,
                    'taxas_extras' => $taxasExtrasCriadas,
                    'total_cobranca_devolucao' => $totalCobrancaDevolucao,
                    'id_financeiro_devolucao' => $idFinanceiroDevolucao,
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao registrar devolucao: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Substitui veiculo no contrato
     *
     * POST /contratos/{id}/substituir
     */
    public function substituir(Request $request, int $id): void
    {
        try {
            if (!Auth::can('contratos.substituir')) {
                Response::json([
                    'success' => false,
                    'message' => 'Sem permissao para substituir veiculo'
                ], 403);
                return;
            }

            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarPorId($id);

            if (!$contrato) {
                Response::json([
                    'success' => false,
                    'message' => 'Contrato nao encontrado'
                ], 404);
                return;
            }

            // Verificar tenant e acesso
            $chave = Auth::chave();
            if ($contrato['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            if (!FilialHelper::temAcessoFilial($contrato['id_matriz_filial_retirada'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            $dados = $request->all();

            // Validar campos obrigatorios
            if (empty($dados['id_contrato_veiculo_antigo'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Veiculo a substituir nao informado'
                ], 400);
                return;
            }

            if (empty($dados['id_veiculo_novo'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Novo veiculo nao informado'
                ], 400);
                return;
            }

            $veiculoModel = new ContratoVeiculo();

            // Verificar se veiculo antigo pertence ao contrato
            $veiculoAntigo = $veiculoModel->buscarPorId((int) $dados['id_contrato_veiculo_antigo']);
            if (!$veiculoAntigo || $veiculoAntigo['id_contrato'] != $id) {
                Response::json([
                    'success' => false,
                    'message' => 'Veiculo nao pertence a este contrato'
                ], 400);
                return;
            }

            // Verificar se novo veiculo nao esta alugado
            $alugado = $veiculoModel->veiculoEstaAlugado((int) $dados['id_veiculo_novo'], $id);
            if ($alugado) {
                Response::json([
                    'success' => false,
                    'message' => 'Este veiculo ja esta alugado no contrato ' . $alugado['contrato_codigo']
                ], 400);
                return;
            }

            $odometroEntradaAntigo = $this->normalizarOdometroContrato($dados['odometro_entrada'] ?? 0);
            $odometroSaidaAntigo = (int) ($veiculoAntigo['odometro_saida'] ?? 0);
            $odometroCadastroAntigo = (int) ($veiculoAntigo['veiculo_odometro'] ?? 0);
            $odometroMinimoAntigo = max(
                $odometroSaidaAntigo,
                $odometroCadastroAntigo
            );
            if ($odometroEntradaAntigo > 0 && $odometroEntradaAntigo < $odometroMinimoAntigo) {
                $odometroInformadoFmt = number_format($odometroEntradaAntigo, 0, '', '.');
                $odometroMinimoFmt = number_format($odometroMinimoAntigo, 0, '', '.');
                $mensagemOdometro = $odometroCadastroAntigo >= $odometroSaidaAntigo
                    ? t('modules.contratos.substitution.odometer_vehicle_registration_mismatch', [
                        'informado' => $odometroInformadoFmt,
                        'referencia' => $odometroMinimoFmt,
                    ])
                    : t('modules.contratos.substitution.odometer_lower_than_contract_departure', [
                        'informado' => $odometroInformadoFmt,
                        'referencia' => $odometroMinimoFmt,
                    ]);
                Response::json([
                    'success' => false,
                    'message' => $mensagemOdometro
                ], 422);
                return;
            }

            $dataSubstituicao = $this->normalizarDataEntradaContrato($dados['data_entrada'] ?? null);
            if ($dataSubstituicao === null) {
                Response::json([
                    'success' => false,
                    'message' => 'Data da substituicao invalida'
                ], 400);
                return;
            }

            $dataSaidaAntigo = $this->normalizarDataEntradaContrato($veiculoAntigo['data_saida'] ?? null);
            if ($dataSaidaAntigo !== null && strtotime($dataSubstituicao) < strtotime($dataSaidaAntigo)) {
                Response::json([
                    'success' => false,
                    'message' => 'Data da substituicao nao pode ser anterior a saida do veiculo atual'
                ], 422);
                return;
            }

            // Preparar dados de devolucao (veiculo antigo entra na empresa)
            $dadosSaida = [
                'data_entrada' => $dataSubstituicao,
                'odometro_entrada' => $odometroEntradaAntigo,
                'combustivel_entrada' => $dados['combustivel_entrada'] ?? null,
                'motivo_saida' => $dados['motivo_saida'] ?? 'Substituicao de veiculo'
            ];

            // Preparar dados do novo veiculo (veiculo novo sai da empresa)
            $dadosNovo = [
                'id_veiculo' => (int) $dados['id_veiculo_novo'],
                'id_grupo' => $dados['id_grupo_novo'] ?? null,
                'data_saida' => $dataSubstituicao,
                'odometro_saida' => $dados['odometro_saida_novo'] ?? 0,
                'combustivel_saida' => $dados['combustivel_saida_novo'] ?? null,
                'plano' => $dados['plano_novo'] ?? $veiculoAntigo['plano'],
            ];

            // Valores enviados pelo frontend (editaveis na tela)
            $manterValores = !empty($dados['manter_valores']);
            $camposValoresFrontend = [
                'valor_plano_km_pago', 'valor_plano_km_controlado', 'valor_plano_km_livre',
                'km_franquia', 'valor_km_excedente',
                'seguro_carro', 'valor_seguro_carro', 'seguro_terceiros', 'valor_seguro_terceiros'
            ];
            foreach ($camposValoresFrontend as $campo) {
                if (isset($dados[$campo])) {
                    $dadosNovo[$campo] = $dados[$campo];
                }
            }

            // Realizar substituicao
            $novoId = $veiculoModel->substituir(
                (int) $dados['id_contrato_veiculo_antigo'],
                $dadosSaida,
                $dadosNovo,
                $manterValores
            );

            // Definir status do veiculo antigo conforme acao escolhida
            $veiculoModelGeral = new Veiculo();
            $disponibilidadeSync = new VeiculoDisponibilidadeSync();
            $acaoVeiculo = $dados['acao_veiculo'] ?? 'disponivel';
            $statusVeiculoAntigo = $acaoVeiculo === 'criar_os' ? 'M' : 'D';
            $veiculoModelGeral->atualizarOdometro((int) $veiculoAntigo['id_veiculo'], $odometroEntradaAntigo);
            $disponibilidadeSync->liberarSeSemVinculoAtivo((int) $veiculoAntigo['id_veiculo'], $statusVeiculoAntigo);
            $disponibilidadeSync->marcarLocado((int) $dados['id_veiculo_novo']);

            // --- Calcular diferencas financeiras e criar taxas ---
            $chave = Auth::chave();
            $contratoTaxaModel = new ContratoTaxaServico();
            $antigoPlaca = $veiculoAntigo['veiculo_placa'] ?? '';
            $totalKmCobranca = 0;
            $totalCombustivelCobranca = 0;

            // Buscar valor_por_fracao do veiculo antigo
            $veiculoAntigoCompleto = $veiculoModelGeral->buscarPorId((int) $veiculoAntigo['id_veiculo']);
            $valorPorFracao = (float) ($veiculoAntigoCompleto['valor_por_fracao'] ?? 0);

            // 1. Calculo de combustivel
            $combustivelSaida = (int) ($veiculoAntigo['combustivel_saida'] ?? 0);
            $combustivelEntrada = isset($dadosSaida['combustivel_entrada']) ? (int) $dadosSaida['combustivel_entrada'] : 0;
            $diferencaFracoes = max(0, $combustivelSaida - $combustivelEntrada);

            if ($diferencaFracoes > 0 && $valorPorFracao > 0) {
                $totalCombustivelCobranca = $diferencaFracoes * $valorPorFracao;
                $contratoTaxaModel->adicionar($id, [
                    'nome' => "Diferenca combustivel - Substituicao [{$antigoPlaca}]",
                    'base_calculo' => 'FIX',
                    'tipo_valor' => 'MON',
                    'quantidade' => $diferencaFracoes,
                    'valor_unitario' => $valorPorFracao,
                ], $chave);
            }

            // 2. Calculo de km (depende do plano do veiculo antigo)
            $planoAntigo = $veiculoAntigo['plano'] ?? 'KL';
            $odometroSaida = (int) ($veiculoAntigo['odometro_saida'] ?? 0);
            $odometroEntrada = (int) ($dadosSaida['odometro_entrada'] ?? 0);
            $kmRodados = max(0, $odometroEntrada - $odometroSaida);
            $valorKmExcedente = (float) ($veiculoAntigo['valor_km_excedente'] ?? 0);

            if ($planoAntigo === 'KMC') {
                $kmFranquia = $this->calcularFranquiaKmEfetiva($contrato, $veiculoAntigo, $dadosSaida['data_entrada'] ?? null);
                $kmExcedente = max(0, $kmRodados - $kmFranquia);
                if ($kmExcedente > 0 && $valorKmExcedente > 0) {
                    $totalKmCobranca = $kmExcedente * $valorKmExcedente;
                    $contratoTaxaModel->adicionar($id, [
                        'nome' => "Km excedente - Substituicao [{$antigoPlaca}]",
                        'base_calculo' => 'FIX',
                        'tipo_valor' => 'MON',
                        'quantidade' => $kmExcedente,
                        'valor_unitario' => $valorKmExcedente,
                    ], $chave);
                }
            } elseif ($planoAntigo === 'KP') {
                if ($kmRodados > 0 && $valorKmExcedente > 0) {
                    $totalKmCobranca = $kmRodados * $valorKmExcedente;
                    $contratoTaxaModel->adicionar($id, [
                        'nome' => "Km rodados - Substituicao [{$antigoPlaca}]",
                        'base_calculo' => 'FIX',
                        'tipo_valor' => 'MON',
                        'quantidade' => $kmRodados,
                        'valor_unitario' => $valorKmExcedente,
                    ], $chave);
                }
            }

            // Recalcular totais (agora inclui as novas taxas)
            $contratoModel->recalcularTotais($id);

            // Buscar dados do novo veiculo para log detalhado
            $novoVeiculo = $veiculoModelGeral->buscarPorId((int) $dados['id_veiculo_novo']);
            $novoPlaca = $novoVeiculo['placa'] ?? '';
            $novoModelo = ($novoVeiculo['marca'] ?? '') . ' ' . ($novoVeiculo['modelo'] ?? '');
            $antigoModelo = ($veiculoAntigo['veiculo_marca'] ?? '') . ' ' . ($veiculoAntigo['veiculo_modelo'] ?? '');

            // Buscar nome do grupo novo
            $grupoNovoNome = '';
            if (!empty($dados['id_grupo_novo'])) {
                $grupoModel = new Grupo();
                $grupoNovo = $grupoModel->buscarPorId((int) $dados['id_grupo_novo']);
                $grupoNovoNome = $grupoNovo['nome'] ?? '';
            }

            $planoLabels = ['KP' => 'Km Pago', 'KMC' => 'Km Controlado', 'KL' => 'Km Livre'];

            // Log de auditoria detalhado
            AuditLogService::registrarComCampos(
                ($_SESSION['user_name'] ?? 'Sistema') . ", substituiu veiculo no contrato [{$contrato['codigo']}]",
                [
                    AuditLogService::campo('Veículo', "{$antigoPlaca} - {$antigoModelo}", "{$novoPlaca} - {$novoModelo}", 'Substituição'),
                    AuditLogService::campo('Grupo', $veiculoAntigo['grupo_nome'] ?? '-', $grupoNovoNome ?: '-', 'Substituição'),
                    AuditLogService::campo('Plano', $planoLabels[$veiculoAntigo['plano'] ?? ''] ?? '-', $planoLabels[$dadosNovo['plano'] ?? ''] ?? '-', 'Substituição'),
                    AuditLogService::campo('Ação Valores', null, $manterValores ? 'Manter valores atuais' : 'Usar valores do novo grupo', 'Substituição'),
                    AuditLogService::campo('Ação Veículo', null, $acaoVeiculo === 'criar_os' ? 'Criar OS de manutenção' : 'Colocar como disponível', 'Substituição'),
                    AuditLogService::campo('Motivo', null, $dadosSaida['motivo_saida'] ?? '-', 'Substituição'),
                    AuditLogService::campo('Odômetro Entrada', null, $dadosSaida['odometro_entrada'] ?? '-', 'Substituição'),
                    AuditLogService::campo('Km Rodados', null, $kmRodados . ' km', 'Substituição'),
                    AuditLogService::campo('Total Km Cobrança', null, 'R$ ' . number_format($totalKmCobranca, 2, ',', '.'), 'Substituição'),
                    AuditLogService::campo('Total Combustível', null, 'R$ ' . number_format($totalCombustivelCobranca, 2, ',', '.'), 'Substituição'),
                ]
            );

            Response::json([
                'success' => true,
                'message' => 'Veiculo substituido com sucesso',
                'data' => ['id_contrato_veiculo' => $novoId]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao substituir veiculo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Adiciona veiculo ao contrato
     *
     * POST /contratos/{id}/veiculos
     */
    public function adicionarVeiculo(Request $request, int $id): void
    {
        try {
            if (!Auth::can('contratos.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Sem permissao para adicionar veiculo ao contrato'
                ], 403);
                return;
            }

            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarPorId($id);

            if (!$contrato) {
                Response::json([
                    'success' => false,
                    'message' => 'Contrato nao encontrado'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($contrato['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            $dados = $request->all();

            if (empty($dados['id_veiculo'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Veiculo e obrigatorio'
                ], 400);
                return;
            }

            $veiculoModel = new ContratoVeiculo();

            // Verificar se veiculo ja esta alugado
            $alugado = $veiculoModel->veiculoEstaAlugado((int) $dados['id_veiculo'], $id);
            if ($alugado) {
                Response::json([
                    'success' => false,
                    'message' => 'Este veiculo ja esta alugado no contrato ' . $alugado['contrato_codigo']
                ], 400);
                return;
            }

            $dados['chave'] = $chave;
            $dados['id_contrato'] = $id;

            $veiculoId = $veiculoModel->adicionar($dados);

            // Marcar veiculo como locado
            $veiculoModelGeral = new Veiculo();
            (new VeiculoDisponibilidadeSync())->marcarLocado((int) $dados['id_veiculo']);

            // Log de auditoria
            $infoVeiculo = $veiculoModelGeral->buscarPorId((int) $dados['id_veiculo']);
            $placa = $infoVeiculo['placa'] ?? 'N/A';
            $marcaModelo = trim(($infoVeiculo['marca'] ?? '') . ' ' . ($infoVeiculo['modelo'] ?? ''));
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", adicionou veiculo [{$placa} - {$marcaModelo}] ao contrato [{$contrato['codigo']}]"
            );

            // Recalcular totais
            $contratoModel->recalcularTotais($id);

            Response::json([
                'success' => true,
                'message' => 'Veiculo adicionado com sucesso',
                'data' => ['id' => $veiculoId]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao adicionar veiculo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpa assinatura do contrato
     *
     * POST /contratos/{id}/limpar-assinatura
     */
    public function limparAssinatura(Request $request, int $id): void
    {
        try {
            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarPorId($id);

            if (!$contrato) {
                Response::json([
                    'success' => false,
                    'message' => 'Contrato nao encontrado'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($contrato['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            $contratoModel->limparAssinatura($id);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", limpou assinatura do contrato [{$contrato['codigo']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Assinatura removida com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao limpar assinatura: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca assinatura de um contrato
     *
     * GET /api/contratos/{id}/assinatura
     */
    public function buscarAssinatura(Request $request, int $id): void
    {
        try {
            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarPorId($id);

            if (!$contrato) {
                Response::json([
                    'success' => false,
                    'message' => 'Contrato nao encontrado'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($contrato['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            $assinatura = $contratoModel->buscarAssinatura($id);

            if (!$assinatura) {
                Response::json([
                    'success' => false,
                    'message' => 'Assinatura nao encontrada'
                ], 404);
                return;
            }

            Response::json([
                'success' => true,
                'data' => [
                    'id' => $assinatura['id'],
                    'url' => $assinatura['url'] ?? '',
                    'data_assinatura' => !empty($assinatura['created_at'])
                        ? format_datetime($assinatura['created_at'])
                        : '-',
                    'ip' => $assinatura['ip_address'] ?? '-'
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar assinatura: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gera PDF do contrato para visualizacao inline
     *
     * GET /contratos/{id}/imprimir
     * Pagina publica de verificacao de contrato (acessada via QR code)
     *
     * GET /verificar/contrato/{codigo}
     */
    public function verificarPublico(Request $request, string $codigo): void
    {
        $model = new Contrato();
        $contrato = $model->buscarPorCodigo($codigo);

        if (!$contrato) {
            $html = Template::render('public.verificar.erro', [
                'titulo' => 'Contrato nao encontrado',
                'mensagem' => 'O codigo informado nao foi encontrado ou o link esta incorreto.'
            ]);
            Response::html($html, 404);
            return;
        }

        // Buscar dados da empresa
        $matrizFilialModel = new MatrizFilial();
        $empresa = $matrizFilialModel->buscarDadosEmpresa($contrato['id_matriz_filial_retirada'] ?? null);

        // Buscar veiculo ativo
        $contratoVeiculo = new ContratoVeiculo();
        $veiculoAtivo = $contratoVeiculo->buscarAtivo((int) $contrato['id']);

        $html = Template::render('public.verificar.contrato', [
            'contrato' => $contrato,
            'empresa' => $empresa,
            'veiculo' => $veiculoAtivo
        ]);
        Response::html($html);
    }

    /**
     * Imprime o contrato em PDF
     *
     * GET /contratos/{id}/imprimir
     *
     * Query params:
     *   tipo: fatura, documento, fatura_documento, fatura_checklist,
     *         fatura_checklist_documento, documento_checklist, checklist, recibo
     *   id_documento: ID do documento da tabela documentos (para tipos com "documento")
     */
    public function imprimir(Request $request, int $id): void
    {
        try {
            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarCompleto($id);

            if (!$contrato) {
                Response::html('<h1>Contrato nao encontrado</h1>', 404);
                return;
            }

            // Verificar tenant
            $chave = Auth::chave();
            if ($contrato['chave'] !== $chave) {
                Response::html('<h1>Acesso negado</h1>', 403);
                return;
            }

            // Verificar acesso a filial
            if (!FilialHelper::temAcessoFilial($contrato['id_matriz_filial_retirada'] ?? null)) {
                Response::html('<h1>Acesso negado</h1>', 403);
                return;
            }

            $tipo = $request->query('tipo', 'fatura');
            $tiposValidos = [
                'fatura', 'documento', 'fatura_documento',
                'fatura_checklist', 'fatura_checklist_documento',
                'documento_checklist', 'checklist', 'recibo'
            ];

            if (!in_array($tipo, $tiposValidos, true)) {
                $tipo = 'fatura';
            }

            // Buscar dados da empresa
            $empresa = $this->buscarDadosEmpresa($contrato['id_matriz_filial_retirada'] ?? null);

            // Veiculo ativo (buscarAtivo retorna campos com prefixo veiculo_)
            $veiculoAtivo = $contrato['veiculo_ativo'] ?? null;

            // Buscar assinatura do contrato
            $assinatura = $contratoModel->buscarAssinatura($id);

            // Buscar documento selecionado (para tipos que incluem "documento")
            $documentoTexto = null;
            $idDocumento = (int) $request->query('id_documento', 0);
            if ($idDocumento > 0 && $this->tipoIncluiDocumento($tipo)) {
                $documentoModel = new Documento();
                $documentoTexto = $documentoModel->buscarPorId($idDocumento);

                // Resolver variaveis {{entidade.campo}} no texto do documento
                if ($documentoTexto && !empty($documentoTexto['texto'])) {
                    $renderer = new TemplateRenderer(
                        $empresa['locale'] ?? null,
                        ($empresa['impressao_variavel_negrito'] ?? 'N') === 'S'
                    );
                    $context = $this->buildDocumentoContext($contrato, $empresa, $veiculoAtivo);
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
                $checklistInfo = $this->prepararDadosChecklist($contrato, $veiculoAtivo, $chave, $idChecklistDigital);
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
            $qrPath = $this->gerarQrCodePath($contrato['codigo']);
            $assinaturaPath = !empty($assinatura['arquivo'])
                ? PdfHelper::resolveImagePath($assinatura['arquivo'], $chave)
                : '';

            $veiculo = $veiculoAtivo;
            $viewData = compact('contrato', 'empresa', 'veiculo', 'assinatura', 'assinaturaPath', 'empresaAssinaturaPath', 'documentoTexto', 'checklistData', 'checklistDigital', 'diagramaPath', 'checklistModeloQuestoes', 'logoPath', 'qrPath');

            $pdfOptions = [
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 5,
                'margin_bottom' => 5,
            ];

            if ($this->tipoIncluiDocumento($tipo)) {
                $mpdf = $this->gerarMpdfContratoComposto($tipo, $viewData, $pdfOptions);
                $mpdf->Output('contrato-' . $contrato['codigo'] . '.pdf', 'I');
            } else {
                $html = $this->renderContratoImpressaoView($tipo, $viewData);
                PdfHelper::outputInline($html, 'contrato-' . $contrato['codigo'] . '.pdf', $pdfOptions);
            }

            $this->limparArquivosTemporarios();
            exit;

        } catch (\Exception $e) {
            Response::html('<h1>Erro ao gerar impressao: ' . htmlspecialchars($e->getMessage()) . '</h1>', 500);
        }
    }

    /**
     * Renderiza o offcanvas com opcoes de impressao do contrato
     *
     * GET /pages/contratos/offcanvas-impressao
     */
    public function offcanvasImpressao(Request $request): void
    {
        $id = (int) $request->query('id');

        $contratoModel = new Contrato();
        $contrato = $contratoModel->buscarCompleto($id);

        if (!$contrato) {
            Response::html('<p>Contrato nao encontrado</p>', 404);
            return;
        }

        // Buscar documentos disponiveis (tipo 0=Contrato/Locacao, 1=Contrato, status=1=Ativo)
        $documentoModel = new Documento();
        $todosDocumentos = $documentoModel->listarParaSelect();
        $documentos = array_filter($todosDocumentos, fn($d) => in_array((int) $d['tipo'], [0, 1]));

        // Verificar plano do tenant
        $user = Auth::user();
        $planoCodigo = $user['plano'] ?? 'G';
        $planoInfo = Planos::getPlano($planoCodigo);

        // Buscar checklists digitais vinculados ao contrato
        $checklistsDigitais = [];
        if (in_array($planoCodigo, ['P3', 'P4'], true)) {
            $checklistModel = new Checklist();
            $checklistsDigitais = $checklistModel->listarFinalizadosPorContrato((int) $contrato['id']);
        }
        $temChecklistDigital = !empty($checklistsDigitais);

        // Buscar modelos de checklist impresso (tipo=1)
        $checklistModeloModel = new ChecklistModelo();
        $todosModelos = $checklistModeloModel->listarParaSelect();
        $checklistModelos = array_values(array_filter($todosModelos, fn($m) => (int) $m['tipo'] === 1));

        // Verificar canais de mensageria disponiveis para a filial/cliente.
        $filialId = (int) ($contrato['id_matriz_filial_retirada'] ?? 0);
        $telefoneCliente = trim((string) ($contrato['cliente_telefone'] ?? ''));
        $emailCliente = trim((string) ($contrato['cliente_email'] ?? ''));
        $temEmail = ($planoInfo['smtp'] ?? 0) > 0 && $emailCliente !== '';
        $temWhatsapp = ($planoInfo['whatsapp'] ?? 0) > 0
            && $telefoneCliente !== ''
            && $filialId > 0
            && (new Whatsapp())->buscarConectadaPorFilial($filialId) !== null;
        $temSms = ($planoInfo['sms'] ?? 0) > 0
            && $telefoneCliente !== ''
            && $filialId > 0
            && (new Sms())->buscarValidadaPorFilial($filialId) !== null;

        $html = Template::render('pages.contratos.offcanvas-impressao', [
            'contrato' => $contrato,
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
     * Envia contrato por canal de mensageria (email, whatsapp, sms)
     *
     * POST /contratos/{id}/enviar
     * Body JSON: { tipo, canal, id_documento, id_checklist_modelo, id_checklist_digital }
     */
    public function enviarContrato(Request $request, int $id): void
    {
        try {
            $data = $request->all();
            $tipo = $data['tipo'] ?? 'fatura';
            $canal = $data['canal'] ?? 'email';
            $idDocumento = (int) ($data['id_documento'] ?? 0);
            $idChecklistModelo = (int) ($data['id_checklist_modelo'] ?? 0);
            $idChecklistDigital = (int) ($data['id_checklist_digital'] ?? 0);

            if (!in_array($canal, ['email', 'whatsapp', 'sms'], true)) {
                Response::json(['success' => false, 'message' => 'Canal invalido'], 422);
                return;
            }

            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarCompleto($id);

            if (!$contrato) {
                Response::json(['success' => false, 'message' => 'Contrato nao encontrado'], 404);
                return;
            }

            $chave = Auth::chave();
            if ($contrato['chave'] !== $chave) {
                Response::json(['success' => false, 'message' => 'Acesso negado'], 403);
                return;
            }

            $empresa = $this->buscarDadosEmpresa($contrato['id_matriz_filial_retirada'] ?? null);
            $nomeEmpresa = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'Locadora';
            $destinatario = $canal === 'email'
                ? ($contrato['cliente_email'] ?? '')
                : ($contrato['cliente_telefone'] ?? '');

            if ($canal === 'email') {
                $emailsAutorizados = (new ContatoEmail())->listarParaEnvio('cliente', (int) $contrato['id_cliente'], $chave);
                if ($emailsAutorizados === []) {
                    throw new \InvalidArgumentException('Cliente sem email autorizado para envio');
                }
                $destinatario = (string) $emailsAutorizados[0]['email'];
            }

            validate_queue_message($canal, [
                'to' => $destinatario,
                'id_matriz_filial' => $contrato['id_matriz_filial_retirada'] ?? null,
            ]);

            // Gerar PDF como string
            $pdfContent = $this->gerarPdfString($id, $tipo, $idDocumento, $idChecklistModelo, $idChecklistDigital);

            // Salvar em arquivo temporario
            $filename = 'contrato_' . $contrato['codigo'] . '_' . DateHelper::timestamp() . '.pdf';
            $tempDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/storage/temp';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            $tempPath = $tempDir . '/' . $filename;
            file_put_contents($tempPath, $pdfContent);

            if ($canal === 'email') {
                queue_client_email((int) $contrato['id_cliente'], [
                    'to' => $destinatario,
                    'to_name' => $contrato['cliente_nome'] ?? '',
                    'subject' => 'Contrato de Locacao - ' . $contrato['codigo'],
                    'body' => '<p>Segue em anexo o documento referente ao contrato <strong>' . htmlspecialchars($contrato['codigo']) . '</strong>.</p><p>Atenciosamente,<br>' . htmlspecialchars($nomeEmpresa) . '</p>',
                    'attachments' => [$tempPath],
                    'id_matriz_filial' => $contrato['id_matriz_filial_retirada'] ?? null,
                ], $chave);
            } elseif ($canal === 'whatsapp') {
                $publicUrl = rtrim(env('APP_URL', ''), '/') . '/storage/temp/' . $filename;
                queue_message('whatsapp', [
                    'to' => $destinatario,
                    'media_url' => $publicUrl,
                    'caption' => 'Contrato ' . $contrato['codigo'] . ' - ' . $nomeEmpresa,
                    'id_matriz_filial' => $contrato['id_matriz_filial_retirada'] ?? null,
                ]);
            } elseif ($canal === 'sms') {
                queue_message('sms', [
                    'to' => $destinatario,
                    'message' => 'Seu contrato ' . $contrato['codigo'] . ' esta disponivel. ' . $nomeEmpresa,
                    'id_matriz_filial' => $contrato['id_matriz_filial_retirada'] ?? null,
                ]);
            }

            Response::json(['success' => true, 'message' => 'Documento enviado com sucesso']);

        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao enviar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Envia link publico de assinatura do contrato por WhatsApp.
     *
     * POST /contratos/{id}/enviar-link-assinatura
     * Body JSON: { url }
     */
    public function enviarLinkAssinatura(Request $request, int $id): void
    {
        try {
            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarCompleto($id);

            if (!$contrato) {
                Response::json(['success' => false, 'message' => 'Contrato nao encontrado'], 404);
                return;
            }

            $chave = Auth::chave();
            if ($contrato['chave'] !== $chave) {
                Response::json(['success' => false, 'message' => 'Acesso negado'], 403);
                return;
            }

            if (!FilialHelper::temAcessoFilial($contrato['id_matriz_filial_retirada'] ?? null)) {
                Response::json(['success' => false, 'message' => 'Acesso negado'], 403);
                return;
            }

            $telefone = $contrato['cliente_telefone'] ?? '';
            if (empty($telefone)) {
                Response::json(['success' => false, 'message' => 'Cliente sem telefone cadastrado'], 400);
                return;
            }

            $url = trim((string) ($request->input('url') ?? ''));
            if ($url === '') {
                $url = rtrim(env('APP_URL', ''), '/') . '/assinar/' . $contrato['codigo'];
            }

            $filialId = (int) ($contrato['id_matriz_filial_retirada'] ?? 0);
            $empresa = $this->buscarDadosEmpresa($filialId) ?? [];
            $empresa['id'] = $empresa['id'] ?? $filialId;

            queue_template_message('signature_request', 'whatsapp', [
                'cliente' => [
                    'nome' => $contrato['cliente_nome'] ?? '',
                    'email' => $contrato['cliente_email'] ?? '',
                    'telefone' => $telefone,
                    'celular' => $telefone,
                ],
                'empresa' => $empresa,
                'contrato' => $contrato,
                'outros' => [
                    'link_assinatura' => $url,
                ],
                'id_matriz_filial' => $filialId,
            ], $chave);

            Response::json(['success' => true, 'message' => 'Link de assinatura enviado por WhatsApp']);

        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao enviar link: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Verifica se o tipo de impressao inclui "documento"
     */
    private function tipoIncluiDocumento(string $tipo): bool
    {
        return in_array($tipo, ['documento', 'fatura_documento', 'fatura_checklist_documento', 'documento_checklist'], true);
    }

    /**
     * Monta o contexto para resolver variaveis do TemplateRenderer
     */
    private function buildDocumentoContext(array $contrato, array $empresa, ?array $veiculo): array
    {
        // Buscar dados completos do cliente
        $clienteData = [];
        if (!empty($contrato['id_cliente'])) {
            $clienteModel = new Cliente();
            $clienteData = $clienteModel->buscarPorId((int) $contrato['id_cliente']) ?? [];
        }

        $fornecedorData = $this->resolverFornecedorDocumento($veiculo);
        $parcelasFinanceiras = [];
        if (!empty($contrato['id'])) {
            $parcelasFinanceiras = (new Contrato())->listarParcelasContrato((int) $contrato['id']);
        }

        $kmSaidaDocumento = $veiculo ? ($veiculo['odometro_saida'] ?? '') : '';
        $kmChegadaDocumento = $veiculo ? ($veiculo['odometro_entrada'] ?? '') : '';
        $tanqueSaidaDocumento = $this->formatarNivelTanqueDocumento($veiculo['combustivel_saida'] ?? null);
        $tanqueChegadaDocumento = $this->formatarNivelTanqueDocumento($veiculo['combustivel_entrada'] ?? null);
        $caucaoValorDocumento = $contrato['caucao_valor'] ?? 0;
        $caucaoDataPrevistaDevolucao = $this->calcularDataPrevistaDevolucaoCaucaoContrato($contrato);
        $bloqueioValorDocumento = $this->primeiroValorMonetarioPositivoDocumento([
            $contrato['bloqueio_hold_valor'] ?? null,
            $contrato['bloqueio_valor'] ?? null,
        ]);

        return [
            'cliente' => [
                'nome' => $clienteData['nome_rsocial'] ?? $contrato['cliente_nome'] ?? '',
                'cpf_cnpj' => $clienteData['cpf_cnpj'] ?? $contrato['cliente_cpf_cnpj'] ?? '',
                'email' => $contrato['cliente_email'] ?? $clienteData['email'] ?? '',
                'telefone' => $contrato['cliente_telefone'] ?? '',
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
            'contrato' => [
                'numero' => $contrato['codigo'] ?? '',
                'data_inicio' => $contrato['data_ini'] ?? '',
                'data_fim' => $contrato['data_fim'] ?? '',
                'hora_inicio' => !empty($contrato['data_ini']) ? DateHelper::formatOperationalDateTime($contrato['data_ini'], true, 'H:i') : '',
                'hora_fim' => !empty($contrato['data_fim']) ? DateHelper::formatOperationalDateTime($contrato['data_fim'], true, 'H:i') : '',
                'valor_total' => $contrato['total_pagar'] ?? 0,
                'valor_diaria' => $contrato['total_fatura'] ?? 0,
                'quantidade_dias' => $contrato['dias'] ?? 0,
                'status' => ($contrato['status'] ?? '') === 'A' ? 'Ativo' : 'Finalizado',
                'observacoes' => $contrato['obs'] ?? '',
                'desconto' => $contrato['valor_desconto'] ?? 0,
                'valor_taxas' => $contrato['total_fatura'] ?? 0,
                'forma_pagamento' => $contrato['forma_pagamento_descricao'] ?? $contrato['forma_pagamento_tipo'] ?? '',
                'comando_parcela_comando' => $contrato['comando_parcela_comando'] ?? '',
                'comando_parcela_descricao' => $contrato['comando_parcela_descricao'] ?? '',
                'primeiro_pagamento' => $contrato['primeiro_pagamento'] ?? 0,
                'contagem' => $contrato['contagem'] ?? 'dia',
                'info_plano' => $this->formatarInfoPlanoContratoDocumento($contrato['veiculos'] ?? []),
                'autorenovacao' => match($contrato['auto_renovacao'] ?? '') {
                    '', null => 'Desativada',
                    'auto' => 'Até devolver',
                    default => $contrato['auto_renovacao'] . 'x',
                },
                'data_renovacao' => $contrato['data_renovacao'] ?? '',
                'filial_retirada' => $contrato['filial_nome'] ?? '',
                'km_saida' => $kmSaidaDocumento,
                'km_chegada' => $kmChegadaDocumento,
                'tanque_saida' => $tanqueSaidaDocumento,
                'tanque_chegada' => $tanqueChegadaDocumento,
                'caucao_valor' => $caucaoValorDocumento,
                'deposito_valor' => $caucaoValorDocumento,
                'caucao_status' => $this->formatarStatusCaucaoDocumento($contrato['caucao_status'] ?? ''),
                'caucao_data_devolucao' => $this->dataValidaDocumento($contrato['caucao_data_devolucao'] ?? null),
                'caucao_prazo_devolucao' => $contrato['caucao_prazo_devolucao'] ?? '',
                'caucao_data_prevista_devolucao' => $caucaoDataPrevistaDevolucao,
                'bloqueio_valor' => $bloqueioValorDocumento,
                'bloqueio_status' => $this->formatarStatusBloqueioDocumento($contrato['bloqueio_status'] ?? ''),
                'bloqueio_valor_capturado' => $contrato['bloqueio_valor_capturado'] ?? 0,
                'bloqueio_expira_em' => $this->dataValidaDocumento($contrato['bloqueio_expira_em'] ?? null),
                'filial' => [
                    'endereco' => $empresa['rua'] ?? '',
                    'numero' => $empresa['num'] ?? '',
                    'complemento' => $empresa['complemento'] ?? '',
                    'bairro' => $empresa['bairro'] ?? '',
                    'cidade' => $empresa['cidade'] ?? '',
                    'uf' => $empresa['estado'] ?? '',
                    'cep' => $empresa['cep'] ?? '',
                ],
                'veiculos' => $contrato['veiculos'] ?? [],
                'taxas' => $contrato['taxas'] ?? [],
                'parcelas' => $parcelasFinanceiras,
                'condutores' => !empty($contrato['condutor_adicional']) ? (json_decode($contrato['condutor_adicional'], true) ?: []) : [],
                'fiadores' => !empty($contrato['array_fiadores']) ? (json_decode($contrato['array_fiadores'], true) ?: []) : [],
                'avalistas' => !empty($contrato['array_avalistas']) ? (json_decode($contrato['array_avalistas'], true) ?: []) : [],
                'testemunhas' => !empty($contrato['array_testemunhas']) ? (json_decode($contrato['array_testemunhas'], true) ?: []) : [],
            ],
            'veiculo' => $veiculo ? [
                'placa' => $veiculo['veiculo_placa'] ?? $veiculo['placa'] ?? '',
                'modelo' => $veiculo['veiculo_modelo'] ?? $veiculo['modelo'] ?? '',
                'marca' => $veiculo['veiculo_marca'] ?? $veiculo['marca'] ?? '',
                'ano' => $veiculo['veiculo_ano'] ?? $veiculo['ano'] ?? '',
                'cor' => $veiculo['veiculo_cor'] ?? $veiculo['cor'] ?? '',
                'renavam' => $veiculo['veiculo_renavam'] ?? $veiculo['renavam'] ?? '',
                'chassi' => $veiculo['veiculo_chassi'] ?? $veiculo['chassi'] ?? '',
                // Prefixed for computed vars (descricao_completa)
                'veiculo_placa' => $veiculo['veiculo_placa'] ?? $veiculo['placa'] ?? '',
                'veiculo_modelo' => $veiculo['veiculo_modelo'] ?? $veiculo['modelo'] ?? '',
                'veiculo_marca' => $veiculo['veiculo_marca'] ?? $veiculo['marca'] ?? '',
                'veiculo_ano' => $veiculo['veiculo_ano'] ?? $veiculo['ano'] ?? '',
                'veiculo_cor' => $veiculo['veiculo_cor'] ?? $veiculo['cor'] ?? '',
                'veiculo_chassi' => $veiculo['veiculo_chassi'] ?? $veiculo['chassi'] ?? '',
                'combustivel_tipo' => $veiculo['veiculo_tipo_combustivel'] ?? $veiculo['tipo_combustivel'] ?? '',
                'valor_compra' => $veiculo['veiculo_valor_compra'] ?? $veiculo['valor_compra'] ?? 0,
            ] : [],
            'fornecedor' => $this->formatarFornecedorDocumento($fornecedorData),
        ];
    }

    /**
     * Retorna o nome do plano comum aos veiculos relevantes do contrato.
     * Prioriza os veiculos ativos e usa o historico quando o contrato ja foi finalizado.
     */
    private function formatarInfoPlanoContratoDocumento(array $veiculos): string
    {
        if ($veiculos === []) {
            return '';
        }

        $veiculosAtivos = array_values(array_filter(
            $veiculos,
            static fn (array $veiculo): bool => ($veiculo['data_entrada'] ?? null) === null
                || ($veiculo['data_entrada'] ?? '') === ''
        ));
        $veiculosRelevantes = $veiculosAtivos !== [] ? $veiculosAtivos : $veiculos;

        $planos = [];
        foreach ($veiculosRelevantes as $veiculo) {
            $plano = strtoupper(trim((string) ($veiculo['plano'] ?? '')));
            if ($plano === '') {
                continue;
            }

            $planos[] = match ($plano) {
                'KL' => 'Km Livre',
                'KMC' => 'Km Controlado',
                'KP', 'DI' => 'Km Pago',
                default => $plano,
            };
        }

        $planos = array_values(array_unique($planos));
        if ($planos === []) {
            return '';
        }

        return count($planos) === 1 ? $planos[0] : 'Conforme relação de veículos';
    }

    private function calcularDataPrevistaDevolucaoCaucaoContrato(array $contrato): string
    {
        $dataEfetiva = $this->dataValidaDocumento($contrato['caucao_data_devolucao'] ?? null);
        if ($dataEfetiva !== '') {
            return $dataEfetiva;
        }

        if (isset($contrato['caucao_prazo_devolucao']) && $contrato['caucao_prazo_devolucao'] !== '') {
            $prazo = (int) $contrato['caucao_prazo_devolucao'];
            $dataBase = $this->dataValidaDocumento($contrato['data_fim'] ?? null);
            if ($dataBase !== '') {
                try {
                    return (new \DateTimeImmutable($dataBase))
                        ->modify("+{$prazo} days")
                        ->format('Y-m-d');
                } catch (\Exception) {
                    return '';
                }
            }
        }

        return '';
    }

    private function primeiroValorMonetarioPositivoDocumento(array $valores): mixed
    {
        foreach ($valores as $valor) {
            if ($valor !== null && $valor !== '' && (float) $valor > 0) {
                return $valor;
            }
        }

        return 0;
    }

    private function dataValidaDocumento(mixed $data): string
    {
        if (!is_string($data) || trim($data) === '') {
            return '';
        }

        $data = trim($data);
        if ($data === '0000-00-00' || $data === '0000-00-00 00:00:00') {
            return '';
        }

        return $data;
    }

    private function formatarStatusCaucaoDocumento(mixed $status): string
    {
        return match ((string) $status) {
            'ativa' => 'Ativa',
            'devolvida' => 'Devolvida',
            'cancelada' => 'Cancelada',
            default => (string) $status,
        };
    }

    private function formatarStatusBloqueioDocumento(mixed $status): string
    {
        return match ((string) $status) {
            'pending' => 'Pendente',
            'authorized' => 'Autorizado',
            'captured' => 'Capturado',
            'released' => 'Liberado',
            'expired' => 'Expirado',
            'failed' => 'Falhou',
            default => (string) $status,
        };
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
     * Verifica se o tipo de impressao inclui "checklist"
     */
    private function tipoIncluiChecklist(string $tipo): bool
    {
        return in_array($tipo, ['checklist', 'fatura_checklist', 'fatura_checklist_documento', 'documento_checklist'], true);
    }

    /**
     * Prepara dados do checklist baseado no plano do tenant
     */
    private function prepararDadosChecklist(array $contrato, ?array $veiculo, string $chave, int $idChecklistDigital = 0): array
    {
        $planoCodigo = Auth::user()['plano'] ?? 'G';

        // P3/P4: usar checklist digital somente quando selecionado na impressao.
        if ($idChecklistDigital > 0 && in_array($planoCodigo, ['P3', 'P4'], true)) {
            $checklistModel = new Checklist();
            $checklistCompleto = $checklistModel->buscarPorId($idChecklistDigital);
            if (!$checklistCompleto || (int) ($checklistCompleto['id_contrato'] ?? 0) !== (int) $contrato['id']) {
                throw new \InvalidArgumentException('Checklist digital nao encontrado para este contrato');
            }

            return $this->montarChecklistDigitalParaImpressao($checklistModel, $checklistCompleto, $chave);
        }

        // Checklist impresso: usar diagrama do veiculo
        $diagramaPath = null;
        if ($veiculo && !empty($veiculo['veiculo_diagrama'])) {
            $diagramaPath = PdfHelper::resolvePublicAssetImagePath(
                $veiculo['veiculo_diagrama'],
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
        $base = $checklistCompleto;
        $vistoriaSaida = $this->carregarFotosVistoria(
            json_decode($checklistCompleto['vistoria_saida'] ?? '[]', true) ?: [],
            $chave
        );
        $vistoriaChegada = $this->carregarFotosVistoria(
            json_decode($checklistCompleto['vistoria_entrada'] ?? '[]', true) ?: [],
            $chave
        );

        $base['obs'] = $checklistCompleto['observacoes_saida'] ?? '';
        $base['obs_chegada'] = $checklistCompleto['observacoes_entrada'] ?? '';
        $base['data_chegada'] = $checklistCompleto['data_entrada'] ?? null;

        return [
            'digital' => true,
            'data' => [
                'checklist' => $base,
                'questoesSaida' => json_decode($checklistCompleto['questoes_saida'] ?? '[]', true) ?: [],
                'questoesChegada' => json_decode($checklistCompleto['questoes_entrada'] ?? '[]', true) ?: [],
                'vistoriaSaida' => $vistoriaSaida,
                'vistoriaChegada' => $vistoriaChegada,
            ],
            'diagramaPath' => null,
        ];
    }

    /**
     * Gera PDF do contrato como string (para envio por mensageria)
     */
    private function gerarPdfString(
        int $id,
        string $tipo,
        int $idDocumento = 0,
        int $idChecklistModelo = 0,
        int $idChecklistDigital = 0
    ): string
    {
        $contratoModel = new Contrato();
        $contrato = $contratoModel->buscarCompleto($id);
        $chave = Auth::chave();

        $empresa = $this->buscarDadosEmpresa($contrato['id_matriz_filial_retirada'] ?? null);

        // Veiculo ativo (buscarAtivo retorna campos com prefixo veiculo_)
        $veiculoAtivo = $contrato['veiculo_ativo'] ?? null;

        $assinatura = $contratoModel->buscarAssinatura($id);

        $documentoTexto = null;
        if ($idDocumento > 0 && $this->tipoIncluiDocumento($tipo)) {
            $documentoModel = new Documento();
            $documentoTexto = $documentoModel->buscarPorId($idDocumento);
            if ($documentoTexto && !empty($documentoTexto['texto'])) {
                $renderer = new TemplateRenderer(
                    $empresa['locale'] ?? null,
                    ($empresa['impressao_variavel_negrito'] ?? 'N') === 'S'
                );
                $context = $this->buildDocumentoContext($contrato, $empresa, $veiculoAtivo);
                $documentoTexto['texto'] = $renderer->render($documentoTexto['texto'], $context);
            }
        }

        $checklistData = null;
        $checklistDigital = false;
        $diagramaPath = null;
        $checklistModeloQuestoes = [];
        if ($this->tipoIncluiChecklist($tipo)) {
            $checklistInfo = $this->prepararDadosChecklist($contrato, $veiculoAtivo, $chave, $idChecklistDigital);
            $checklistData = $checklistInfo['data'];
            $checklistDigital = $checklistInfo['digital'];
            $diagramaPath = $checklistInfo['diagramaPath'];

            // Carregar questoes do modelo de checklist impresso
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
        $qrPath = $this->gerarQrCodePath($contrato['codigo']);
        $assinaturaPath = !empty($assinatura['arquivo'])
            ? PdfHelper::resolveImagePath($assinatura['arquivo'], $chave)
            : '';

        $veiculo = $veiculoAtivo;
        $viewData = compact('contrato', 'empresa', 'veiculo', 'assinatura', 'assinaturaPath', 'empresaAssinaturaPath', 'documentoTexto', 'checklistData', 'checklistDigital', 'diagramaPath', 'checklistModeloQuestoes', 'logoPath', 'qrPath');

        $pdfOptions = [
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 5,
            'margin_bottom' => 5,
        ];

        if ($this->tipoIncluiDocumento($tipo)) {
            $mpdf = $this->gerarMpdfContratoComposto($tipo, $viewData, $pdfOptions);
            $result = $mpdf->Output('', 'S');
        } else {
            $html = $this->renderContratoImpressaoView($tipo, $viewData);
            $result = PdfHelper::generateAsString($html, $pdfOptions);
        }

        $this->limparArquivosTemporarios();

        return $result;
    }

    /**
     * Compoe PDFs que incluem documento em paginas separadas com margens reais
     * no mPDF, evitando sobreposicao do corpo com header/footer HTML.
     */
    private function gerarMpdfContratoComposto(string $tipo, array $viewData, array $pdfOptions): \Mpdf\Mpdf
    {
        $iniciaComDocumento = in_array($tipo, ['documento', 'documento_checklist'], true);
        $mpdf = PdfHelper::create(array_merge(
            $pdfOptions,
            $iniciaComDocumento ? $this->documentoPdfMargins() : []
        ));
        $temPagina = false;

        if (in_array($tipo, ['fatura_documento'], true)) {
            PdfHelper::writeHtml($mpdf, $this->renderContratoImpressaoView('fatura', $viewData));
            $temPagina = true;
        }

        if ($tipo === 'fatura_checklist_documento') {
            PdfHelper::writeHtml($mpdf, $this->renderContratoImpressaoView('fatura_checklist', $viewData));
            $temPagina = true;
        }

        if ($this->tipoIncluiDocumento($tipo)) {
            $this->aplicarDocumentoHeaderFooterContrato($mpdf, $viewData, !$temPagina);
            if ($temPagina) {
                $this->addPdfPage($mpdf, PdfHelper::DOCUMENTO_HTML_HEADER_MARGIN_TOP_MM, PdfHelper::DOCUMENTO_HTML_FOOTER_MARGIN_BOTTOM_MM);
            }
            PdfHelper::writeHtml($mpdf, $this->renderContratoImpressaoView('documento', $viewData));
            $temPagina = true;
        }

        if ($tipo === 'documento_checklist') {
            $this->limparDocumentoHeaderFooter($mpdf);
            $this->addPdfPage($mpdf, 5, 45);
            PdfHelper::writeHtml($mpdf, $this->renderContratoImpressaoView('checklist', $viewData));
        }

        return $mpdf;
    }

    private function renderContratoImpressaoView(string $tipo, array $viewData): string
    {
        extract($viewData);
        ob_start();
        $viewPath = __DIR__ . '/../Views/pages/contratos/imprimir/' . $tipo . '.php';
        include $viewPath;
        return ob_get_clean();
    }

    private function aplicarDocumentoHeaderFooterContrato(\Mpdf\Mpdf $mpdf, array $viewData, bool $aplicarPaginaAtual): void
    {
        extract($viewData);
        $partialsDir = __DIR__ . '/../Views/pages/contratos/imprimir/_partials';

        ob_start();
        $_docTitulo = t('modules.contratos.pdf.document_title');
        include $partialsDir . '/_header.php';
        $headerHtml = ob_get_clean();

        ob_start();
        include $partialsDir . '/_footer_assinatura.php';
        $footerHtml = ob_get_clean();

        $mpdf->SetHTMLHeader($headerHtml, 'O', $aplicarPaginaAtual);
        $mpdf->SetHTMLFooter($footerHtml, 'O');
    }

    private function limparDocumentoHeaderFooter(\Mpdf\Mpdf $mpdf): void
    {
        $mpdf->SetHTMLHeader('', 'O');
        $mpdf->SetHTMLFooter('', 'O');
    }

    private function addPdfPage(\Mpdf\Mpdf $mpdf, int $marginTop, int $marginBottom): void
    {
        $mpdf->AddPage('', '', '', '', '', 10, 10, $marginTop, $marginBottom, 5, 5);
    }

    private function documentoPdfMargins(): array
    {
        return [
            'margin_top' => PdfHelper::DOCUMENTO_HTML_HEADER_MARGIN_TOP_MM,
            'margin_bottom' => PdfHelper::DOCUMENTO_HTML_FOOTER_MARGIN_BOTTOM_MM,
        ];
    }

    /**
     * Busca dados da empresa para impressao
     */
    private function buscarDadosEmpresa(?int $filialId): ?array
    {
        $matrizFilialModel = new MatrizFilial();
        return $matrizFilialModel->buscarDadosEmpresa($filialId);
    }

    // ==================== GESTÃO FINANCEIRA DO CONTRATO ====================

    /**
     * Lista parcelas financeiras do contrato
     *
     * GET /api/contratos/{id}/parcelas
     */
    public function parcelas(Request $request, int $id): void
    {
        try {
            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarPorId($id);

            if (!$contrato) {
                Response::json([
                    'success' => false,
                    'message' => 'Contrato não encontrado'
                ], 404);
                return;
            }

            // Verificar tenant
            $chave = Auth::chave();
            if ($contrato['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            // Verificar acesso à filial
            if (!FilialHelper::temAcessoFilial($contrato['id_matriz_filial_retirada'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            // Buscar parcelas e resumo
            $parcelas = $contratoModel->listarParcelasContrato($id);
            $resumo = $contratoModel->resumoFinanceiroContrato($id);

            Response::json([
                'success' => true,
                'data' => [
                    'parcelas' => $parcelas,
                    'resumo' => $resumo
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar parcelas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview da regularizacao manual de autorrenovacao vencida.
     *
     * GET /api/contratos/{id}/regularizacao-renovacao
     */
    public function previewRegularizacaoRenovacao(Request $request, int $id): void
    {
        try {
            $contratoModel = new Contrato();
            $contrato = $this->buscarContratoAutorizado($contratoModel, $id);
            if (!$contrato) {
                return;
            }

            $regularizacao = $contratoModel->calcularRegularizacaoAutorenovacao($contrato);
            if ($regularizacao['ciclos'] <= 0) {
                Response::json([
                    'success' => false,
                    'message' => 'Contrato nao possui autorrenovacao vencida'
                ], 400);
                return;
            }

            $preview = null;
            if (!empty($contrato['id_forma_pagamento'])) {
                $preview = $this->gerarPreviewRegularizacao($contratoModel, $id, $contrato, $regularizacao);
            }
            $canaisDisponiveis = $this->canaisMensageriaContrato($contrato);

            Response::json([
                'success' => true,
                'data' => [
                    'contrato' => [
                        'id' => (int) $contrato['id'],
                        'codigo' => $contrato['codigo'] ?? '',
                        'cliente_nome' => $contrato['cliente_nome'] ?? '',
                        'forma_pagamento' => $contrato['forma_pagamento_descricao'] ?? '',
                        'conta' => $contrato['conta_descricao'] ?? '',
                        'comando_parcela' => trim(($contrato['comando_parcela_comando'] ?? '') . (!empty($contrato['comando_parcela_descricao']) ? ' - ' . $contrato['comando_parcela_descricao'] : '')),
                    ],
                    'regularizacao' => $regularizacao,
                    'financeiro_disponivel' => !empty($contrato['id_forma_pagamento']),
                    'preview_financeiro' => $preview,
                    'canais_disponiveis' => $canaisDisponiveis,
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao gerar preview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Regulariza manualmente autorrenovacao vencida.
     *
     * POST /api/contratos/{id}/regularizar-renovacao
     */
    public function regularizarRenovacao(Request $request, int $id): void
    {
        try {
            if (!Auth::can('contratos.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para regularizar contratos'
                ], 403);
                return;
            }

            $contratoModel = new Contrato();
            $contrato = $this->buscarContratoAutorizado($contratoModel, $id);
            if (!$contrato) {
                return;
            }

            $dados = $request->all();
            $gerarFinanceiro = !empty($dados['gerar_financeiro']);
            $canais = [
                'email' => !empty($dados['enviar_email']),
                'whatsapp' => !empty($dados['enviar_whatsapp']),
                'sms' => !empty($dados['enviar_sms']),
            ];

            $regularizacao = $contratoModel->calcularRegularizacaoAutorenovacao($contrato);
            if ($regularizacao['ciclos'] <= 0) {
                Response::json([
                    'success' => false,
                    'message' => 'Contrato nao possui autorrenovacao vencida'
                ], 400);
                return;
            }

            if ($gerarFinanceiro && empty($contrato['id_forma_pagamento'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Contrato sem forma de pagamento definida'
                ], 400);
                return;
            }

            $idsParcelas = [];
            if ($gerarFinanceiro) {
                $preview = $this->gerarPreviewRegularizacao($contratoModel, $id, $contrato, $regularizacao);
                $idsParcelas = $contratoModel->salvarParcelasContrato($id, $preview['parcelas'], Auth::chave());
            }

            $contratoModel->atualizar($id, [
                'data_renovacao' => $regularizacao['nova_data_renovacao'],
            ]);

            $envios = [];
            if ($gerarFinanceiro && !empty($idsParcelas)) {
                $envios = $this->enfileirarCobrancasRegularizacao($idsParcelas, $canais, $contrato);
            }

            AuditLogService::registrarComCampos(
                ($_SESSION['user_name'] ?? 'Sistema') . ", regularizou autorrenovacao do contrato [{$contrato['codigo']}]",
                [
                    AuditLogService::campo('Ciclos aplicados', '0', (string) $regularizacao['ciclos']),
                    AuditLogService::campo('Período de Cobrança', $regularizacao['periodo_cobranca_ini'], $regularizacao['periodo_cobranca_fim']),
                    AuditLogService::campo('Próxima Renovação', $contrato['data_renovacao'], $regularizacao['nova_data_renovacao']),
                    AuditLogService::campo('Financeiro gerado', 'Nao', $gerarFinanceiro ? 'Sim' : 'Nao'),
                    AuditLogService::campo('Canais cobrança', '-', implode(', ', array_keys(array_filter($canais))) ?: 'Nenhum'),
                ]
            );

            Response::json([
                'success' => true,
                'message' => 'Autorenovacao regularizada com sucesso',
                'data' => [
                    'regularizacao' => $regularizacao,
                    'parcelas_ids' => $idsParcelas,
                    'envios' => $envios,
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao regularizar renovacao: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca contrato validando tenant e filial.
     */
    private function buscarContratoAutorizado(Contrato $contratoModel, int $id): ?array
    {
        $contrato = $contratoModel->buscarPorId($id);

        if (!$contrato) {
            Response::json([
                'success' => false,
                'message' => 'Contrato não encontrado'
            ], 404);
            return null;
        }

        if (($contrato['chave'] ?? '') !== Auth::chave()) {
            Response::json([
                'success' => false,
                'message' => 'Acesso negado'
            ], 403);
            return null;
        }

        if (!FilialHelper::temAcessoFilial($contrato['id_matriz_filial_retirada'] ?? null)) {
            Response::json([
                'success' => false,
                'message' => 'Acesso negado'
            ], 403);
            return null;
        }

        return $contrato;
    }

    /**
     * Gera preview financeiro da regularizacao sem limpar parcelas antigas.
     */
    private function gerarPreviewRegularizacao(Contrato $contratoModel, int $id, array $contrato, array $regularizacao): array
    {
        $preview = $contratoModel->gerarPreviewParcelas($id, [
            'id_forma_pagamento' => (int) ($contrato['id_forma_pagamento'] ?? 0),
            'id_comando_parcela' => (int) ($contrato['id_comando_parcela'] ?? 0),
            'id_conta' => (int) ($contrato['id_conta'] ?? 0),
            'primeiro_vencimento' => $regularizacao['periodo_cobranca_ini'],
            'data_fim' => $regularizacao['periodo_cobranca_fim'],
            'valor_desconto' => 0,
        ]);

        foreach ($preview['parcelas'] as &$parcela) {
            $parcela['valor_subtotal_formatado'] = currency_format((float) ($parcela['valor_subtotal'] ?? 0));
            $parcela['valor_total_formatado'] = currency_format((float) ($parcela['valor_total'] ?? $parcela['valor_subtotal'] ?? 0));
        }
        unset($parcela);

        foreach (['valor_contrato', 'desconto', 'valor_base', 'taxa_total', 'valor_final'] as $campo) {
            if (isset($preview['resumo'][$campo])) {
                $preview['resumo'][$campo . '_formatado'] = currency_format((float) $preview['resumo'][$campo]);
            }
        }

        return $preview;
    }

    /**
     * Enfileira cobrancas das parcelas criadas por canal selecionado.
     */
    private function enfileirarCobrancasParcelas(array $idsParcelas, array $canais, array $contrato): array
    {
        return (new InvoiceBatchNotificationService())->sendInstallmentBatch($idsParcelas, [
            'chave' => Auth::chave(),
            'id_cliente' => (int) ($contrato['id_cliente'] ?? 0),
            'id_matriz_filial' => (int) ($contrato['id_matriz_filial_retirada'] ?? 0),
            'canais' => $canais,
            'origem_label' => !empty($contrato['codigo']) ? 'Contrato #' . $contrato['codigo'] : 'Contrato',
        ]);
    }

    /**
     * Compatibilidade com o fluxo de regularizacao de renovacao.
     */
    private function enfileirarCobrancasRegularizacao(array $idsParcelas, array $canais, array $contrato): array
    {
        return $this->enfileirarCobrancasParcelas($idsParcelas, $canais, $contrato);
    }

    /**
     * Retorna canais efetivamente disponiveis para cobranca do contrato.
     */
    private function canaisMensageriaContrato(array $contrato): array
    {
        $planoInfo = Planos::getPlano(Auth::user()['plano'] ?? 'G') ?? [];
        $cliente = !empty($contrato['id_cliente'])
            ? (new Cliente())->buscarPorIdComContatos((int) $contrato['id_cliente'])
            : null;
        $filialId = (int) ($contrato['id_matriz_filial_retirada'] ?? 0);
        $telefone = trim((string) ($cliente['telefone'] ?? $cliente['celular'] ?? ''));
        $email = trim((string) ($cliente['email'] ?? ''));

        return [
            'email' => ($planoInfo['smtp'] ?? 0) > 0 && $email !== '',
            'whatsapp' => ($planoInfo['whatsapp'] ?? 0) > 0
                && $telefone !== ''
                && $filialId > 0
                && (new Whatsapp())->buscarConectadaPorFilial($filialId) !== null,
            'sms' => ($planoInfo['sms'] ?? 0) > 0
                && $telefone !== ''
                && $filialId > 0
                && (new Sms())->buscarValidadaPorFilial($filialId) !== null,
        ];
    }

    /**
     * Gera preview ou salva parcelas do contrato
     *
     * POST /api/contratos/{id}/gerar-parcelas
     * Body: id_conta, id_forma_pagamento, num_parcelas, primeiro_vencimento, valor_desconto, parcelas[], salvar
     */
    public function gerarParcelas(Request $request, int $id): void
    {
        try {
            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarPorId($id);

            if (!$contrato) {
                Response::json([
                    'success' => false,
                    'message' => 'Contrato não encontrado'
                ], 404);
                return;
            }

            // Verificar tenant
            $chave = Auth::chave();
            if ($contrato['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            // Verificar acesso à filial
            if (!FilialHelper::temAcessoFilial($contrato['id_matriz_filial_retirada'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            $dados = $request->all();
            $salvar = !empty($dados['salvar']);

            // Gerar preview das parcelas (num_parcelas auto-calculado pelo Model se nao fornecido)
            $preview = $contratoModel->gerarPreviewParcelas($id, $dados);

            if (!$salvar) {
                // Retorna apenas preview
                Response::json([
                    'success' => true,
                    'data' => $preview,
                    'message' => 'Preview gerado'
                ]);
                return;
            }

            // Se salvar = true, criar as parcelas no banco
            // Primeiro, remover parcelas pendentes existentes
            $removidas = $contratoModel->limparParcelasPendentes($id);

            // Usar as parcelas do request se enviadas (com possíveis overrides), senão usar as do preview
            $parcelasParaSalvar = !empty($dados['parcelas']) && is_array($dados['parcelas'])
                ? $dados['parcelas']
                : $preview['parcelas'];

            // Garantir que cada parcela tenha os campos necessários
            foreach ($parcelasParaSalvar as $i => &$parcela) {
                $parcela['parcela'] = $parcela['parcela'] ?? ($i + 1);
                $parcela['total_parcelas'] = count($parcelasParaSalvar);
                $parcela['id_conta'] = $parcela['id_conta'] ?? ($dados['id_conta'] ?? null);
                $parcela['id_forma_pagamento'] = $parcela['id_forma_pagamento'] ?? ($dados['id_forma_pagamento'] ?? null);
                $parcela['data_venci'] = $parcela['data_venci'] ?? ($preview['parcelas'][$i]['data_venci'] ?? DateHelper::todayForDatabase());
                $parcela['valor_subtotal'] = $parcela['valor_subtotal'] ?? ($parcela['valor'] ?? $preview['parcelas'][$i]['valor_subtotal'] ?? 0);
                $parcela['valor_total'] = $parcela['valor_total'] ?? $parcela['valor_subtotal'];
            }

            // Salvar parcelas
            $ids = $contratoModel->salvarParcelasContrato($id, $parcelasParaSalvar, $chave);
            $enviosCobranca = [];

            if (!empty($dados['from_creation']) && !empty($ids)) {
                try {
                    $enviosCobranca = $this->enfileirarCobrancasParcelas($ids, [
                        'email' => true,
                        'whatsapp' => true,
                        'sms' => true,
                    ], $contrato);
                } catch (\Exception $e) {
                    $enviosCobranca[] = [
                        'parcela_id' => null,
                        'canal' => 'all',
                        'success' => false,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            // Log de auditoria (skip quando parcelas geradas durante criacao do contrato — ja registrado no log principal)
            if (empty($dados['from_creation'])) {
                AuditLogService::registrar(
                    ($_SESSION['user_name'] ?? 'Sistema') . ", gerou " . count($ids) . " parcela(s) para o contrato [{$contrato['codigo']}]"
                );
            }

            Response::json([
                'success' => true,
                'message' => count($ids) . ' parcela(s) gerada(s) com sucesso',
                'data' => [
                    'ids' => $ids,
                    'parcelas_removidas' => $removidas,
                    'envios_cobranca' => $enviosCobranca
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $this->mensagemErroBanco($e, 'Erro ao gerar parcelas')
            ], 500);
        }
    }

    /**
     * Preview stateless de parcelas (sem contrato salvo)
     *
     * POST /api/contratos/preview-parcelas
     * Body: total_pagar, data_fim, id_forma_pagamento, primeiro_vencimento, valor_desconto, id_conta
     */
    public function previewParcelasStateless(Request $request): void
    {
        try {
            $dados = $request->all();

            if (empty($dados['id_forma_pagamento'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Forma de pagamento obrigatória'
                ], 400);
                return;
            }

            $contratoModel = new Contrato();
            $preview = $contratoModel->gerarPreviewParcelas(0, $dados);

            Response::json([
                'success' => true,
                'data' => $preview,
                'message' => 'Preview gerado'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao gerar preview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Adiciona parcela avulsa ao contrato
     *
     * POST /api/contratos/{id}/parcela-avulsa
     * Body: id_conta, id_forma_pagamento, data_venci, valor, descricao
     */
    public function parcelaAvulsa(Request $request, int $id): void
    {
        try {
            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarPorId($id);

            if (!$contrato) {
                Response::json([
                    'success' => false,
                    'message' => 'Contrato não encontrado'
                ], 404);
                return;
            }

            // Verificar tenant
            $chave = Auth::chave();
            if ($contrato['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            // Verificar acesso à filial
            if (!FilialHelper::temAcessoFilial($contrato['id_matriz_filial_retirada'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            $dados = $request->all();
            $tipoLancamento = (string) ($dados['tipo_lancamento'] ?? '');

            if ($tipoLancamento === 'avaria') {
                $planoAvarias = (new PlanoDeContas())->buscarPorHierarquia(Contrato::PLANO_CONTA_AVARIAS);
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
                    $dados['descricao'] = "Contrato #{$contrato['codigo']} - Avaria";
                }
            }

            // Validações
            if (empty($dados['data_venci'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Data de vencimento é obrigatória'
                ], 400);
                return;
            }

            if (empty($dados['valor']) || (float) $dados['valor'] <= 0) {
                Response::json([
                    'success' => false,
                    'message' => 'Valor deve ser maior que zero'
                ], 400);
                return;
            }

            $parcelaId = $contratoModel->adicionarParcelaAvulsa($id, $dados, $chave);

            // Log de auditoria
            $acaoLog = $tipoLancamento === 'avaria'
                ? 'adicionou cobrança de avaria ao contrato'
                : 'adicionou parcela avulsa ao contrato';

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", {$acaoLog} [{$contrato['codigo']}]"
            );

            Response::json([
                'success' => true,
                'message' => $tipoLancamento === 'avaria' ? 'Avaria adicionada com sucesso' : 'Parcela adicionada com sucesso',
                'data' => ['id' => $parcelaId]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao adicionar parcela: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza uma parcela pendente do contrato.
     *
     * POST /api/contratos/{id}/parcelas/{idParcela}/atualizar
     */
    public function atualizarParcela(Request $request, int $id, int $idParcela): void
    {
        try {
            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarPorId($id);

            if (!$contrato) {
                Response::json(['success' => false, 'message' => 'Contrato não encontrado'], 404);
                return;
            }

            $chave = Auth::chave();
            if ($contrato['chave'] !== $chave) {
                Response::json(['success' => false, 'message' => 'Acesso negado'], 403);
                return;
            }

            if (!FilialHelper::temAcessoFilial($contrato['id_matriz_filial_retirada'] ?? null)) {
                Response::json(['success' => false, 'message' => 'Acesso negado'], 403);
                return;
            }

            $contratoModel->atualizarParcelaContrato($id, $idParcela, $request->all());

            Response::json(['success' => true, 'message' => 'Parcela atualizada com sucesso']);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao atualizar parcela: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove uma parcela pendente do contrato.
     *
     * POST /api/contratos/{id}/parcelas/{idParcela}/excluir
     */
    public function removerParcela(Request $request, int $id, int $idParcela): void
    {
        try {
            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarPorId($id);

            if (!$contrato) {
                Response::json(['success' => false, 'message' => 'Contrato não encontrado'], 404);
                return;
            }

            $chave = Auth::chave();
            if ($contrato['chave'] !== $chave) {
                Response::json(['success' => false, 'message' => 'Acesso negado'], 403);
                return;
            }

            if (!FilialHelper::temAcessoFilial($contrato['id_matriz_filial_retirada'] ?? null)) {
                Response::json(['success' => false, 'message' => 'Acesso negado'], 403);
                return;
            }

            $contratoModel->removerParcelaContrato($id, $idParcela);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", removeu parcela do contrato [{$contrato['codigo']}]"
            );

            Response::json(['success' => true, 'message' => 'Parcela removida com sucesso']);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao remover parcela: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Marca uma parcela do contrato como paga.
     *
     * POST /api/contratos/{id}/parcelas/{idParcela}/marcar-pago
     */
    public function marcarParcelaPaga(Request $request, int $id, int $idParcela): void
    {
        try {
            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarPorId($id);

            if (!$contrato) {
                Response::json(['success' => false, 'message' => 'Contrato não encontrado'], 404);
                return;
            }

            $chave = Auth::chave();
            if ($contrato['chave'] !== $chave) {
                Response::json(['success' => false, 'message' => 'Acesso negado'], 403);
                return;
            }

            if (!FilialHelper::temAcessoFilial($contrato['id_matriz_filial_retirada'] ?? null)) {
                Response::json(['success' => false, 'message' => 'Acesso negado'], 403);
                return;
            }

            $contratoModel->marcarParcelaContratoPaga($id, $idParcela, $request->all());

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", marcou parcela como paga no contrato [{$contrato['codigo']}]"
            );

            Response::json(['success' => true, 'message' => 'Parcela marcada como paga']);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao marcar parcela como paga: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Estorna o pagamento de uma parcela do contrato.
     *
     * POST /api/contratos/{id}/parcelas/{idParcela}/estornar
     */
    public function estornarParcelaPagamento(Request $request, int $id, int $idParcela): void
    {
        try {
            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarPorId($id);

            if (!$contrato) {
                Response::json(['success' => false, 'message' => 'Contrato não encontrado'], 404);
                return;
            }

            $chave = Auth::chave();
            if ($contrato['chave'] !== $chave) {
                Response::json(['success' => false, 'message' => 'Acesso negado'], 403);
                return;
            }

            if (!FilialHelper::temAcessoFilial($contrato['id_matriz_filial_retirada'] ?? null)) {
                Response::json(['success' => false, 'message' => 'Acesso negado'], 403);
                return;
            }

            $contratoModel->estornarParcelaContratoPagamento($id, $idParcela);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", estornou pagamento de parcela do contrato [{$contrato['codigo']}]"
            );

            Response::json(['success' => true, 'message' => 'Pagamento estornado']);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao estornar pagamento: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Recalcula parcelas pendentes quando valor do contrato muda
     *
     * POST /api/contratos/{id}/recalcular-parcelas
     * Body: acao (recalcular ou manter)
     */
    public function recalcularParcelas(Request $request, int $id): void
    {
        try {
            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarPorId($id);

            if (!$contrato) {
                Response::json([
                    'success' => false,
                    'message' => 'Contrato não encontrado'
                ], 404);
                return;
            }

            // Verificar tenant
            $chave = Auth::chave();
            if ($contrato['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            // Verificar acesso à filial
            if (!FilialHelper::temAcessoFilial($contrato['id_matriz_filial_retirada'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            $dados = $request->all();
            $acao = $dados['acao'] ?? 'recalcular';

            if (!in_array($acao, ['recalcular', 'manter'], true)) {
                Response::json([
                    'success' => false,
                    'message' => 'Ação inválida. Use "recalcular" ou "manter"'
                ], 400);
                return;
            }

            $resultado = $contratoModel->recalcularParcelasContrato($id, $acao);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", {$acao} parcelas do contrato [{$contrato['codigo']}]: {$resultado['message']}"
            );

            Response::json([
                'success' => $resultado['success'],
                'message' => $resultado['message'],
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao recalcular parcelas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna opcoes de parcelamento de um comando de parcelas
     *
     * GET /api/contratos/opcoes-parcelamento/{id}
     */
    public function opcoesParcelamento(Request $request, int $id): void
    {
        try {
            $comandoModel = new \App\Models\ComandoParcela();
            $registro = $comandoModel->buscarPorId($id);

            if (!$registro) {
                Response::json([
                    'success' => false,
                    'message' => 'Comando de parcelas não encontrado'
                ], 404);
                return;
            }

            $opcoes = \App\Models\ComandoParcela::parseComando($registro['comando']);

            Response::json([
                'success' => true,
                'data' => $opcoes
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar opções: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca contratos para select (chosen-select server-side)
     *
     * GET /api/contratos/buscar-select
     * Query params: q (termo de busca)
     */
    public function buscarSelect(Request $request): void
    {
        try {
            $termo = $request->query('q', '');
            $chave = Auth::chave();

            // Filtro de filiais
            [$filialWhere, $filialParams] = FilialHelper::whereContratos('c');

            $contratoModel = new Contrato();
            $contratos = $contratoModel->buscarParaSelect($termo, $chave, $filialWhere, $filialParams);

            Response::json([
                'success' => true,
                'data' => $contratos
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar contratos: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== HELPERS PARA PDF (QR) ====================

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
            $url = $baseUrl . '/verificar/contrato/' . $codigo;

            $qrGenerator = new QrCodeGenerator();
            $qrImage = $qrGenerator->format('png')->size(120)->generate($url);

            $tmpPath = sys_get_temp_dir() . '/qr_contrato_' . $codigo . '.png';
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

    // ========== BLOQUEIO (Pre-autorizacao no Cartao) ==========

    /**
     * Cria um authorization hold (pre-autorizacao) no cartao
     *
     * POST /api/contratos/{id}/bloqueio/criar
     */
    public function criarBloqueio(Request $request, int $id): void
    {
        try {
            if (!Auth::can('contratos.editar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarPorId($id);
            if (!$contrato) {
                Response::json(['success' => false, 'message' => 'Contrato nao encontrado'], 404);
                return;
            }

            $dados = $request->all();
            $idCartao = (int) ($dados['id_cartao'] ?? 0);
            $valor = !empty($dados['valor'])
                ? (float) str_replace(['.', ','], ['', '.'], $dados['valor'])
                : 0;

            if ($idCartao <= 0 || $valor <= 0) {
                Response::json(['success' => false, 'message' => 'Cartao e valor sao obrigatorios'], 400);
                return;
            }

            // Buscar cartao do cliente
            $cartaoModel = new \App\Models\ClienteCartao();
            $cartao = $cartaoModel->buscarPorId($idCartao);
            if (!$cartao) {
                Response::json(['success' => false, 'message' => 'Cartao nao encontrado'], 404);
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
                Response::json(['success' => false, 'message' => 'Gateway do cartao nao encontrado ou inativo'], 400);
                return;
            }

            $gateway = \App\Services\Gateways\GatewayFactory::create(
                $gatewayConfig['gateway_code'],
                $gatewayConfig['credentials'] ?? [],
                $gatewayConfig['ambiente'] === 'sandbox',
                (int) $gatewayConfig['id']
            );

            if (!($gateway instanceof \App\Services\Gateways\AuthorizationHoldInterface) || !$gateway->supportsAuthorizationHold()) {
                Response::json(['success' => false, 'message' => 'Gateway nao suporta bloqueio (pre-autorizacao)'], 400);
                return;
            }

            // Criar hold
            $chave = $_SESSION['chave'] ?? '';
            $result = $gateway->createHold([
                'chave' => $chave,
                'payment_method_id' => $cartao['token'],
                'id_cartao_registro' => $idCartao,
                'amount' => $valor,
                'customer_name' => $contrato['cliente_nome'] ?? '',
                'description' => 'Bloqueio - Contrato ' . ($contrato['codigo'] ?? $id),
                'metadata' => [
                    'id_contrato' => $id,
                    'id_cliente' => $contrato['id_cliente'],
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
            $bloqueioModel = new \App\Models\ContratoBloqueio();
            $idBloqueio = $bloqueioModel->criar([
                'chave' => $chave,
                'id_contrato' => $id,
                'id_cliente' => (int) $contrato['id_cliente'],
                'id_cartao' => $idCartao,
                'id_gateway' => (int) $gatewayConfig['id'],
                'gateway_code' => $gatewayConfig['gateway_code'],
                'external_id' => $result['external_id'],
                'valor' => $valor,
                'status' => $result['status'] === 'authorized' ? 'authorized' : 'pending',
                'autorizado_em' => $result['status'] === 'authorized' ? DateHelper::nowForDatabase() : null,
                'expira_em' => $result['expires_at'] ?? null,
                'payload' => $result['raw'] ?? null,
            ]);

            // Atualizar contrato com referencia ao bloqueio
            $contratoModel->atualizar($id, ['id_bloqueio_ativo' => $idBloqueio]);

            Response::json([
                'success' => true,
                'message' => 'Bloqueio criado com sucesso',
                'data' => [
                    'id' => $idBloqueio,
                    'status' => $result['status'],
                    'external_id' => $result['external_id'],
                    'expires_at' => $result['expires_at'] ?? null,
                    'client_secret' => $result['client_secret'] ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao criar bloqueio: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Captura um authorization hold (converte em cobranca real)
     *
     * POST /api/contratos/{id}/bloqueio/capturar
     */
    public function capturarBloqueio(Request $request, int $id): void
    {
        try {
            if (!Auth::can('contratos.editar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarPorId($id);
            if (!$contrato || empty($contrato['id_bloqueio_ativo'])) {
                Response::json(['success' => false, 'message' => 'Contrato sem bloqueio ativo'], 404);
                return;
            }

            $bloqueioModel = new \App\Models\ContratoBloqueio();
            $bloqueio = $bloqueioModel->buscarPorId((int) $contrato['id_bloqueio_ativo']);
            if (!$bloqueio || $bloqueio['status'] !== 'authorized') {
                Response::json(['success' => false, 'message' => 'Bloqueio nao esta autorizado'], 400);
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
                Response::json(['success' => false, 'message' => 'Gateway nao encontrado'], 400);
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
                'capturado_em' => DateHelper::nowForDatabase(),
                'valor_capturado' => $valorEfetivo,
                'payload' => $result['raw'] ?? null,
            ]);

            // Gerar lancamento financeiro (receita)
            $chave = $_SESSION['chave'] ?? '';
            $motivoLabels = [
                'dano' => 'Dano ao veiculo',
                'multa' => 'Multa de transito',
                'combustivel' => 'Combustivel',
                'diaria_extra' => 'Mensalidade(s) extra(s)',
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
                'descricao' => $descricaoMotivo . ' - Contrato ' . ($contrato['codigo'] ?? $id),
                'id_cliente' => $contrato['id_cliente'],
                'id_conta' => $idConta,
                'id_plano_de_conta' => $planoBloqueioEntrada ? (int) $planoBloqueioEntrada['id'] : null,
                'id_contrato' => $id,
                'data_criada' => DateHelper::todayForDatabase(),
                'data_venci' => DateHelper::todayForDatabase(),
                'data_pago' => DateHelper::todayForDatabase(),
                'valor_subtotal' => $valorEfetivo,
                'parcela' => 1,
                'total_parcelas' => 1,
            ]);

            Response::json([
                'success' => true,
                'message' => 'Bloqueio capturado com sucesso',
                'data' => ['status' => 'captured', 'valor_capturado' => $valorEfetivo],
            ]);

        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao capturar bloqueio: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Libera um authorization hold sem cobrar
     *
     * POST /api/contratos/{id}/bloqueio/liberar
     */
    public function liberarBloqueio(Request $request, int $id): void
    {
        try {
            if (!Auth::can('contratos.editar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarPorId($id);
            if (!$contrato || empty($contrato['id_bloqueio_ativo'])) {
                Response::json(['success' => false, 'message' => 'Contrato sem bloqueio ativo'], 404);
                return;
            }

            $bloqueioModel = new \App\Models\ContratoBloqueio();
            $bloqueio = $bloqueioModel->buscarPorId((int) $contrato['id_bloqueio_ativo']);
            if (!$bloqueio || $bloqueio['status'] !== 'authorized') {
                Response::json(['success' => false, 'message' => 'Bloqueio nao esta autorizado'], 400);
                return;
            }

            // Instanciar gateway
            $gatewayModel = new \App\Models\GatewayPagamento();
            $gatewayConfig = $gatewayModel->buscarPorIdComCredenciais((int) $bloqueio['id_gateway']);
            if (!$gatewayConfig) {
                Response::json(['success' => false, 'message' => 'Gateway nao encontrado'], 400);
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
                'liberado_em' => DateHelper::nowForDatabase(),
                'payload' => $result['raw'] ?? null,
            ]);

            // Remover referencia no contrato
            $contratoModel->atualizar($id, ['id_bloqueio_ativo' => null]);

            Response::json([
                'success' => true,
                'message' => 'Bloqueio liberado com sucesso',
                'data' => ['status' => 'released'],
            ]);

        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao liberar bloqueio: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Consulta status de um authorization hold
     *
     * GET /api/contratos/{id}/bloqueio/status
     */
    public function statusBloqueio(Request $request, int $id): void
    {
        try {
            if (!Auth::can('contratos.visualizar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $contratoModel = new Contrato();
            $contrato = $contratoModel->buscarPorId($id);
            if (!$contrato) {
                Response::json(['success' => false, 'message' => 'Contrato nao encontrado'], 404);
                return;
            }

            $bloqueioModel = new \App\Models\ContratoBloqueio();
            $bloqueios = $bloqueioModel->listarPorContrato($id);

            Response::json([
                'success' => true,
                'data' => [
                    'bloqueio_ativo_id' => $contrato['id_bloqueio_ativo'],
                    'bloqueios' => $bloqueios,
                ],
            ]);

        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao consultar bloqueio: ' . $e->getMessage()], 500);
        }
    }

    private function normalizarOdometroContrato(mixed $valor): int
    {
        if (is_int($valor)) {
            return $valor;
        }

        if (is_float($valor)) {
            return (int) $valor;
        }

        return (int) preg_replace('/\D+/', '', (string) $valor);
    }

    private function normalizarDataEntradaContrato(mixed $valor): ?string
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

    private function normalizarDataFinanceiroContrato(mixed $valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        $data = \DateTimeImmutable::createFromFormat('Y-m-d', $valor);
        if ($data instanceof \DateTimeImmutable && $data->format('Y-m-d') === $valor) {
            return $valor;
        }

        return null;
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
}
