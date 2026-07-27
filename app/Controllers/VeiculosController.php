<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Veiculo;
use App\Models\Grupo;
use App\Models\Fornecedor;
use App\Models\MatrizFilial;
use App\Models\VeiculoAcessorio;
use App\Models\Manutencao;
use App\Models\ManutencaoPlano;
use App\Models\VeiculoEncargo;
use App\Models\Financeiro;
use App\Helpers\FilialHelper;
use App\Helpers\PlanoLimiteHelper;
use App\Services\AuditLogService;

/**
 * Controller de Veiculos
 *
 * Gerencia operacoes CRUD de veiculos.
 */
class VeiculosController
{
    /**
     * Exibe a pagina de listagem de veiculos
     *
     * GET /pages/veiculos
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.veiculos.index');
        Response::html($html);
    }

    /**
     * Exibe a pagina de ajuste em lote do valor por fracao.
     *
     * GET /pages/veiculos/ajustar-valores-fracao
     */
    public function viewAjustarValoresFracao(Request $request): void
    {
        if (!Auth::can('veiculos.editar')) {
            Response::forbidden('Voce nao tem permissao para editar veiculos');
        }

        $filialAtual = (int) (Auth::idMatrizFilial() ?? 0);
        $filialSelecionada = null;

        if ($filialAtual > 0 && FilialHelper::temAcessoFilial($filialAtual)) {
            $filial = (new MatrizFilial())->buscarPorId($filialAtual);
            if ($filial && ($filial['status'] ?? '') === 'A') {
                $filialSelecionada = [
                    'id' => (int) $filial['id'],
                    'nome' => $filial['razao_social']
                        ?: ($filial['nome_fantasia'] ?: 'Matriz/Filial #' . $filial['id']),
                ];
            }
        }

        $html = Template::render('pages.veiculos.ajustar-valores-fracao', [
            'filialSelecionada' => $filialSelecionada,
        ]);
        Response::html($html);
    }

    /**
     * Lista veiculos de uma filial agrupados para ajuste do valor por fracao.
     *
     * GET /api/veiculos/valores-fracao?id_matriz_filial={id}
     */
    public function valoresFracao(Request $request): void
    {
        try {
            if (!Auth::can('veiculos.editar')) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.veiculos.fraction_adjustment.messages.permission_denied'),
                ], 403);
                return;
            }

            $filialId = (int) $request->query('id_matriz_filial', 0);
            if ($filialId <= 0) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.veiculos.fraction_adjustment.messages.branch_required'),
                ], 400);
                return;
            }

            if (!FilialHelper::temAcessoFilial($filialId)) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.veiculos.fraction_adjustment.messages.branch_denied'),
                ], 403);
                return;
            }

            $filial = (new MatrizFilial())->buscarPorId($filialId);
            if (!$filial || ($filial['status'] ?? '') !== 'A') {
                Response::json([
                    'success' => false,
                    'message' => t('modules.veiculos.fraction_adjustment.messages.branch_not_found'),
                ], 404);
                return;
            }

            $veiculos = (new Veiculo())->listarParaAjusteValorFracao($filialId);
            $grupos = [];

            foreach ($veiculos as $veiculo) {
                $grupoId = !empty($veiculo['id_grupo']) ? (int) $veiculo['id_grupo'] : null;
                $grupoKey = $grupoId === null ? 'sem-grupo' : 'grupo-' . $grupoId;

                if (!isset($grupos[$grupoKey])) {
                    $grupos[$grupoKey] = [
                        'id' => $grupoId,
                        'nome' => $grupoId === null
                            ? t('modules.veiculos.fraction_adjustment.without_group')
                            : (string) ($veiculo['grupo_nome'] ?? ''),
                        'veiculos' => [],
                    ];
                }

                $grupos[$grupoKey]['veiculos'][] = [
                    'id' => (int) $veiculo['id'],
                    'placa' => (string) ($veiculo['placa'] ?? ''),
                    'marca' => (string) ($veiculo['marca'] ?? ''),
                    'modelo' => (string) ($veiculo['modelo'] ?? ''),
                    'valor_por_fracao' => number_format((float) ($veiculo['valor_por_fracao'] ?? 0), 2, '.', ''),
                ];
            }

            Response::json([
                'success' => true,
                'data' => [
                    'filial' => [
                        'id' => (int) $filial['id'],
                        'nome' => (string) ($filial['nome_fantasia'] ?: $filial['razao_social']),
                        'currency' => $this->configMoedaFilial($filial),
                    ],
                    'grupos' => array_values($grupos),
                ],
            ]);
        } catch (\Throwable $e) {
            Response::json([
                'success' => false,
                'message' => t('modules.veiculos.fraction_adjustment.messages.load_error'),
            ], 500);
        }
    }

    /**
     * Atualiza o valor por fracao de varios veiculos da mesma filial.
     *
     * POST /veiculos/valores-fracao/atualizar
     */
    public function atualizarValoresFracao(Request $request): void
    {
        try {
            if (!Auth::can('veiculos.editar')) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.veiculos.fraction_adjustment.messages.permission_denied'),
                ], 403);
                return;
            }

            $dados = $request->all();
            $filialId = (int) ($dados['id_matriz_filial'] ?? 0);
            $itens = $dados['veiculos'] ?? [];

            if ($filialId <= 0 || !is_array($itens) || empty($itens)) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.veiculos.fraction_adjustment.messages.no_changes'),
                ], 400);
                return;
            }

            if (!FilialHelper::temAcessoFilial($filialId)) {
                Response::json([
                    'success' => false,
                    'message' => t('modules.veiculos.fraction_adjustment.messages.branch_denied'),
                ], 403);
                return;
            }

            $filialModel = new MatrizFilial();
            $filial = $filialModel->buscarPorId($filialId);
            if (!$filial || ($filial['status'] ?? '') !== 'A') {
                Response::json([
                    'success' => false,
                    'message' => t('modules.veiculos.fraction_adjustment.messages.branch_not_found'),
                ], 404);
                return;
            }

            $alteracoes = [];
            $ids = [];

            foreach ($itens as $item) {
                if (!is_array($item)) {
                    throw new \InvalidArgumentException(t('modules.veiculos.fraction_adjustment.messages.invalid_values'));
                }

                $id = (int) ($item['id'] ?? 0);
                $valorOriginalRaw = (string) ($item['valor_original'] ?? '');
                $novoValorRaw = (string) ($item['novo_valor'] ?? '');

                if (
                    $id <= 0
                    || isset($ids[$id])
                    || !preg_match('/^\d+(?:\.\d{1,2})?$/', $valorOriginalRaw)
                    || !preg_match('/^\d+(?:\.\d{1,2})?$/', $novoValorRaw)
                ) {
                    throw new \InvalidArgumentException(t('modules.veiculos.fraction_adjustment.messages.invalid_values'));
                }

                $valorOriginal = round((float) $valorOriginalRaw, 2);
                $novoValor = round((float) $novoValorRaw, 2);

                if ($valorOriginal < 0 || $novoValor < 0 || $novoValor > 99999999.99) {
                    throw new \InvalidArgumentException(t('modules.veiculos.fraction_adjustment.messages.invalid_values'));
                }

                $ids[$id] = true;
                $alteracoes[] = [
                    'id' => $id,
                    'valor_original' => $valorOriginal,
                    'novo_valor' => $novoValor,
                ];
            }

            $atualizados = (new Veiculo())->atualizarValoresFracaoEmLote($filialId, $alteracoes);

            foreach ($atualizados as $veiculo) {
                $identificacao = trim(
                    $veiculo['placa'] . ' - ' . $veiculo['marca'] . ' ' . $veiculo['modelo'],
                    " -"
                );

                try {
                    AuditLogService::registrarComCampos(
                        ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou em lote o veiculo [{$identificacao}]",
                        [
                            AuditLogService::campo(
                                t('modules.veiculos.fields.fraction_value'),
                                $filialModel->formatarMoeda($veiculo['valor_original'], $filialId),
                                $filialModel->formatarMoeda($veiculo['novo_valor'], $filialId),
                                t('modules.veiculos.fraction_adjustment.title')
                            ),
                        ]
                    );
                } catch (\Throwable $auditError) {
                    error_log('Falha ao registrar auditoria do ajuste de valor por fracao: ' . $auditError->getMessage());
                }
            }

            Response::json([
                'success' => true,
                'message' => t('modules.veiculos.fraction_adjustment.messages.save_success', [
                    'count' => count($atualizados),
                ]),
                'data' => [
                    'atualizados' => count($atualizados),
                ],
            ]);
        } catch (\DomainException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage(),
                'conflict' => true,
            ], 409);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            Response::json([
                'success' => false,
                'message' => t('modules.veiculos.fraction_adjustment.messages.save_error'),
            ], 500);
        }
    }

    /**
     * Exibe a pagina de adicionar/editar veiculo
     *
     * GET /pages/veiculos/adicionar
     * GET /pages/veiculos/{id}/editar
     */
    public function viewAdicionar(Request $request, ?int $id = null): void
    {
        // Se for criação (não edição), verificar limite do plano
        if ($id === null) {
            $redirectUrl = PlanoLimiteHelper::getRedirectSeAtingido('veiculos');
            if ($redirectUrl) {
                Response::redirect($redirectUrl);
                return;
            }
        }

        $html = Template::render('pages.veiculos.adicionar', ['id' => $id]);
        Response::html($html);
    }

    /**
     * Lista todos os veiculos do tenant (com paginacao e busca)
     *
     * GET /api/veiculos
     * Query params: page, perPage, search
     */
    public function index(Request $request): void
    {
        try {
            // Verificar permissao
            if (!Auth::can('veiculos.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para visualizar veiculos'
                ], 403);
                return;
            }

            $chave = Auth::chave();

            // Parametros de paginacao
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');

            // Filtro de filial
            [$filialWhere, $filialParams] = FilialHelper::whereFiliais('v.id_matriz_filial');

            $model = new Veiculo();

            // Buscar veiculos paginados
            $veiculos = $model->listarPaginado($chave, $page, $perPage, $search, $filialWhere, $filialParams);

            // Contar total de registros
            $total = $model->contar($chave, $search, $filialWhere, $filialParams);

            // Calcular total de paginas
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $veiculos,
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
                'message' => 'Erro ao buscar veiculos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe um veiculo especifico
     *
     * GET /api/veiculos/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            // Verificar permissao
            if (!Auth::can('veiculos.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para visualizar veiculos'
                ], 403);
                return;
            }

            $model = new Veiculo();
            $veiculo = $model->buscarPorId($id);

            if (!$veiculo) {
                Response::json([
                    'success' => false,
                    'message' => 'Veiculo nao encontrado'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($veiculo['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Veiculo nao encontrado'
                ], 404);
                return;
            }

            // Verificar acesso a filial
            if (!FilialHelper::temAcessoFilial($veiculo['id_matriz_filial'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem acesso a este veiculo'
                ], 403);
                return;
            }

            // Formatar valores monetarios
            $veiculo['valor_por_fracao_formatted'] = currency_format($veiculo['valor_por_fracao'] ?? 0);
            $veiculo['valor_compra_formatted'] = currency_format($veiculo['valor_compra'] ?? 0);
            $veiculo['valor_venda_formatted'] = currency_format($veiculo['valor_venda'] ?? 0);

            // Formatar datas
            $veiculo['data_compra_formatted'] = $veiculo['data_compra'] ? format_date($veiculo['data_compra']) : '';
            $veiculo['data_venda_formatted'] = $veiculo['data_venda'] ? format_date($veiculo['data_venda']) : '';
            Response::json([
                'success' => true,
                'data' => $veiculo
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar veiculo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca veiculos para select
     *
     * GET /api/veiculos/buscar
     */
    public function buscar(Request $request): void
    {
        try {
            $search = $request->query('q', '');
            $chave = Auth::chave();
            $model = new Veiculo();

            // Aplicar filtro de filial
            if (Auth::can('matrizes_filiais.listar_todas')) {
                $filialWhere = null;
                $filialParams = [];
            } else {
                [$filialWhere, $filialParams] = FilialHelper::whereFiliais('v.id_matriz_filial');
            }

            $veiculos = $model->listarParaSelect($chave, $filialWhere, $filialParams);

            // Aplicar filtro de busca e formatar para chosen
            $data = [];
            $normalize = function ($str) {
                return preg_replace('/[\x{0300}-\x{036f}]/u', '', \Normalizer::normalize($str, \Normalizer::FORM_D));
            };
            $searchNorm = mb_strtolower($normalize($search));
            foreach ($veiculos as $v) {
                $text = $v['placa'] . ' - ' . $v['marca'] . ' ' . $v['modelo'];
                if (empty($search) || str_contains(mb_strtolower($normalize($text)), $searchNorm)) {
                    $data[] = ['id' => $v['id'], 'text' => $text];
                    if (count($data) >= 50) {
                        break;
                    }
                }
            }

            Response::json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Lista veiculos por grupo
     *
     * GET /api/veiculos/por-grupo
     */
    public function porGrupo(Request $request): void
    {
        try {
            $grupoId = $request->query('id_grupo', $request->query('grupo_id', ''));
            $filialId = $request->query('id_filial', $request->query('filial_id', ''));
            $contexto = (string) $request->query('contexto', '');

            if (!empty($filialId) && !FilialHelper::temAcessoFilial((int) $filialId)) {
                Response::json(['success' => false, 'message' => 'Voce nao tem acesso a esta filial'], 403);
                return;
            }

            $model = new Veiculo();
            $veiculos = $contexto === 'reserva'
                ? $model->listarAtivosParaReserva(
                    !empty($grupoId) ? (int) $grupoId : null,
                    !empty($filialId) ? (int) $filialId : null
                )
                : $model->listarDisponiveisParaContrato(
                    !empty($grupoId) ? (int) $grupoId : null,
                    !empty($filialId) ? (int) $filialId : null
                );

            Response::json(['success' => true, 'data' => $veiculos]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Cria um novo veiculo
     *
     * POST /veiculos/salvar
     */
    public function store(Request $request): void
    {
        try {
            // Verificar permissao
            if (!Auth::can('veiculos.criar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para criar veiculos'
                ], 403);
                return;
            }

            // Verificar limite do plano
            if (!PlanoLimiteHelper::podeAdicionar('veiculos')) {
                $usage = PlanoLimiteHelper::getUsage('veiculos');
                Response::json([
                    'success' => false,
                    'message' => "Limite de veículos atingido. Seu plano {$usage['plano']} permite apenas {$usage['limite']} veículos.",
                    'limite_atingido' => true,
                    'redirect_url' => PlanoLimiteHelper::getRedirectSeAtingido('veiculos')
                ], 403);
                return;
            }

            $dados = $request->all();
            $chave = Auth::chave();
            $dados['chave'] = $chave;

            // Validacao de campos obrigatorios
            $camposObrigatorios = [
                'id_matriz_filial' => 'Filial',
                'id_fornecedor' => 'Fornecedor',
                'placa' => 'Placa',
                'marca' => 'Marca',
                'modelo' => 'Modelo',
                'ano' => 'Ano',
                'id_plano_manutencao' => 'Plano de Manutencao'
            ];

            foreach ($camposObrigatorios as $campo => $label) {
                if (empty($dados[$campo])) {
                    Response::json([
                        'success' => false,
                        'message' => "{$label} e obrigatorio"
                    ], 400);
                    return;
                }
            }

            // Verificar acesso a filial
            if (!FilialHelper::temAcessoFilial((int) $dados['id_matriz_filial'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem acesso a esta filial'
                ], 403);
                return;
            }

            $fornecedor = (new Fornecedor())->buscarPorId((int) $dados['id_fornecedor']);
            if (!$fornecedor) {
                Response::json([
                    'success' => false,
                    'message' => 'Fornecedor nao encontrado'
                ], 400);
                return;
            }

            // Verificar unicidade da placa
            $model = new Veiculo();
            $veiculoExistente = $model->buscarPorPlaca($dados['placa']);
            if ($veiculoExistente) {
                Response::json([
                    'success' => false,
                    'message' => 'Ja existe um veiculo com esta placa'
                ], 400);
                return;
            }

            // Converter campos monetarios
            if (!empty($dados['valor_por_fracao'])) {
                $dados['valor_por_fracao'] = currency_parse($dados['valor_por_fracao']);
            }
            if (!empty($dados['valor_compra'])) {
                $dados['valor_compra'] = currency_parse($dados['valor_compra']);
            }
            if (!empty($dados['valor_venda'])) {
                $dados['valor_venda'] = currency_parse($dados['valor_venda']);
            }

            // Converter campos de data — string vazia vira NULL (coluna DATE rejeita '')
            if (isset($dados['data_compra'])) {
                $dados['data_compra'] = !empty($dados['data_compra']) ? parse_date($dados['data_compra']) : null;
            }
            if (isset($dados['data_venda'])) {
                $dados['data_venda'] = !empty($dados['data_venda']) ? parse_date($dados['data_venda']) : null;
            }
            // Tratar campos nullable
            $nullableFields = [
                'id_grupo', 'id_matriz_filial_localizacao',
                'renavam', 'chassi', 'cor', 'motor', 'transmissao', 'peso_max',
                'tipo_combustivel', 'tanque_litros', 'tanque_fracao',
                'disponibilidade', 'odometro', 'descricao'
            ];

            foreach ($nullableFields as $field) {
                if (isset($dados[$field]) && $dados[$field] === '') {
                    $dados[$field] = null;
                }
            }

            // Tratar checkbox vender
            $dados['vender'] = isset($dados['vender']) && $dados['vender'] ? 'S' : 'N';

            // Converter plano_manutencao_array para JSON
            if (!empty($dados['plano_manutencao_array'])) {
                $planoArray = is_array($dados['plano_manutencao_array'])
                    ? $dados['plano_manutencao_array']
                    : json_decode($dados['plano_manutencao_array'], true);

                if (is_array($planoArray)) {
                    // Formatar valores com ponto como separador de milhar
                    $planoFormatado = [];
                    foreach ($planoArray as $item => $valor) {
                        $valorInt = (int) $valor;
                        $planoFormatado[$item] = $valorInt > 0 ? number_format($valorInt, 0, '', '.') : '0';
                    }
                    $dados['plano_manutencao_array'] = json_encode($planoFormatado, JSON_UNESCAPED_UNICODE);
                }
            }

            $id = $model->criar($dados);

            // Sincronizar acessorios se fornecidos
            if (!empty($dados['acessorios_ids'])) {
                $acessoriosIds = is_array($dados['acessorios_ids'])
                    ? $dados['acessorios_ids']
                    : json_decode($dados['acessorios_ids'], true);

                if (is_array($acessoriosIds)) {
                    $model->sincronizarAcessorios($id, $acessoriosIds, $chave);
                }
            }

            // Log de auditoria
            AuditLogService::registrarComAuditFrontend(
                ($_SESSION['user_name'] ?? 'Sistema') . ", adicionou veiculo [{$dados['placa']} - {$dados['marca']} {$dados['modelo']}]",
                $dados['_audit_data'] ?? null,
                null
            );

            Response::json([
                'success' => true,
                'message' => 'Veiculo criado com sucesso',
                'data' => ['id' => $id]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar veiculo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza um veiculo
     *
     * POST /veiculos/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            // Verificar permissao
            if (!Auth::can('veiculos.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para editar veiculos'
                ], 403);
                return;
            }

            $model = new Veiculo();
            $veiculo = $model->buscarPorId($id);

            if (!$veiculo) {
                Response::json([
                    'success' => false,
                    'message' => 'Veiculo nao encontrado'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($veiculo['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode editar este veiculo'
                ], 403);
                return;
            }

            // Verificar acesso a filial atual
            if (!FilialHelper::temAcessoFilial($veiculo['id_matriz_filial'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem acesso a este veiculo'
                ], 403);
                return;
            }

            $dados = $request->all();

            // Validacao de campos obrigatorios
            $camposObrigatorios = [
                'id_matriz_filial' => 'Filial',
                'id_fornecedor' => 'Fornecedor',
                'placa' => 'Placa',
                'marca' => 'Marca',
                'modelo' => 'Modelo',
                'ano' => 'Ano',
                'id_plano_manutencao' => 'Plano de Manutencao'
            ];

            foreach ($camposObrigatorios as $campo => $label) {
                if (isset($dados[$campo]) && empty($dados[$campo])) {
                    Response::json([
                        'success' => false,
                        'message' => "{$label} e obrigatorio"
                    ], 400);
                    return;
                }
            }

            // Verificar acesso a nova filial (se alterou)
            if (!empty($dados['id_matriz_filial']) && !FilialHelper::temAcessoFilial((int) $dados['id_matriz_filial'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem acesso a esta filial'
                ], 403);
                return;
            }

            if (isset($dados['id_fornecedor'])) {
                $fornecedor = (new Fornecedor())->buscarPorId((int) $dados['id_fornecedor']);
                if (!$fornecedor) {
                    Response::json([
                        'success' => false,
                        'message' => 'Fornecedor nao encontrado'
                    ], 400);
                    return;
                }
            }

            // Verificar unicidade da placa (se alterou)
            if (!empty($dados['placa']) && $dados['placa'] !== $veiculo['placa']) {
                $veiculoExistente = $model->buscarPorPlaca($dados['placa'], $id);
                if ($veiculoExistente) {
                    Response::json([
                        'success' => false,
                        'message' => 'Ja existe um veiculo com esta placa'
                    ], 400);
                    return;
                }
            }

            // Validar reativacao de veiculo inativo (vendido/roubado/excluido)
            if (
                isset($dados['disponibilidade'])
                && in_array($veiculo['disponibilidade'], Veiculo::DISPONIBILIDADE_INATIVA)
                && !in_array($dados['disponibilidade'], Veiculo::DISPONIBILIDADE_INATIVA)
            ) {
                if (!PlanoLimiteHelper::podeAdicionar('veiculos')) {
                    $usage = PlanoLimiteHelper::getUsage('veiculos');
                    Response::json([
                        'success' => false,
                        'message' => t('modules.veiculos.messages.plan_limit_reached', [
                            'plano' => $usage['plano'],
                            'limite' => $usage['limite']
                        ]),
                        'limite_atingido' => true
                    ], 403);
                    return;
                }
            }

            // Converter campos monetarios
            if (isset($dados['valor_por_fracao'])) {
                $dados['valor_por_fracao'] = currency_parse($dados['valor_por_fracao']);
            }
            if (isset($dados['valor_compra'])) {
                $dados['valor_compra'] = currency_parse($dados['valor_compra']);
            }
            if (isset($dados['valor_venda'])) {
                $dados['valor_venda'] = currency_parse($dados['valor_venda']);
            }

            // Converter campos de data
            if (isset($dados['data_compra'])) {
                $dados['data_compra'] = !empty($dados['data_compra']) ? parse_date($dados['data_compra']) : null;
            }
            if (isset($dados['data_venda'])) {
                $dados['data_venda'] = !empty($dados['data_venda']) ? parse_date($dados['data_venda']) : null;
            }
            // Tratar campos nullable
            $nullableFields = [
                'id_grupo', 'id_matriz_filial_localizacao',
                'renavam', 'chassi', 'cor', 'motor', 'transmissao', 'peso_max',
                'tipo_combustivel', 'tanque_litros', 'tanque_fracao',
                'disponibilidade', 'odometro', 'descricao'
            ];

            foreach ($nullableFields as $field) {
                if (isset($dados[$field]) && $dados[$field] === '') {
                    $dados[$field] = null;
                }
            }

            // Tratar checkbox vender
            if (isset($dados['vender'])) {
                $dados['vender'] = $dados['vender'] ? 'S' : 'N';
            }

            // Converter plano_manutencao_array para JSON
            if (!empty($dados['plano_manutencao_array'])) {
                $planoArray = is_array($dados['plano_manutencao_array'])
                    ? $dados['plano_manutencao_array']
                    : json_decode($dados['plano_manutencao_array'], true);

                if (is_array($planoArray)) {
                    // Formatar valores com ponto como separador de milhar
                    $planoFormatado = [];
                    foreach ($planoArray as $item => $valor) {
                        $valorInt = (int) $valor;
                        $planoFormatado[$item] = $valorInt > 0 ? number_format($valorInt, 0, '', '.') : '0';
                    }
                    $dados['plano_manutencao_array'] = json_encode($planoFormatado, JSON_UNESCAPED_UNICODE);
                }
            }

            $model->atualizar($id, $dados);

            // Sincronizar acessorios se fornecidos
            if (isset($dados['acessorios_ids'])) {
                $acessoriosIds = is_array($dados['acessorios_ids'])
                    ? $dados['acessorios_ids']
                    : json_decode($dados['acessorios_ids'], true);

                $model->sincronizarAcessorios($id, $acessoriosIds ?? [], $chave);
            }

            // Log de auditoria
            AuditLogService::registrarComAuditFrontend(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou veiculo [{$veiculo['placa']} - {$veiculo['marca']} {$veiculo['modelo']}]",
                null,
                $dados['_audit_changes'] ?? null
            );

            Response::json([
                'success' => true,
                'message' => 'Veiculo atualizado com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar veiculo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista manutencoes de um veiculo
     *
     * GET /api/veiculos/{id}/manutencoes
     */
    public function manutencoes(Request $request, int $id): void
    {
        try {
            if (!Auth::can('manutencoes.visualizar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $model = new Veiculo();
            $veiculo = $model->buscarPorId($id);

            if (!$veiculo || $veiculo['chave'] !== Auth::chave()) {
                Response::json(['success' => false, 'message' => 'Veiculo nao encontrado'], 404);
                return;
            }

            if (!FilialHelper::temAcessoFilial($veiculo['id_matriz_filial'])) {
                Response::json(['success' => false, 'message' => 'Sem acesso'], 403);
                return;
            }

            $manutencaoModel = new Manutencao();
            $manutencoes = $manutencaoModel->listarPorVeiculo($id);

            // Formatar valores
            foreach ($manutencoes as &$m) {
                $m['total_servicos_formatted'] = currency_format($m['total_servicos'] ?? 0);
                $m['data_enviado_formatted'] = $m['data_enviado'] ? format_operational_datetime($m['data_enviado']) : '';
                $m['data_retorno_formatted'] = $m['data_retorno'] ? format_operational_datetime($m['data_retorno']) : '';

                $statusMap = ['C' => 'Criada', 'A' => 'Aberta', 'F' => 'Fechada'];
                $m['status_label'] = $statusMap[$m['status']] ?? $m['status'];
            }

            Response::json(['success' => true, 'data' => $manutencoes]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao listar manutencoes: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Lista faturas vinculadas a um veiculo
     *
     * GET /api/veiculos/{id}/faturas
     */
    public function faturas(Request $request, int $id): void
    {
        try {
            if (!Auth::can('veiculos.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para visualizar veiculos'
                ], 403);
                return;
            }

            $veiculoModel = new Veiculo();
            $veiculo = $veiculoModel->buscarPorId($id);

            if (!$veiculo || $veiculo['chave'] !== Auth::chave()) {
                Response::json([
                    'success' => false,
                    'message' => 'Veiculo nao encontrado'
                ], 404);
                return;
            }

            if (!FilialHelper::temAcessoFilial($veiculo['id_matriz_filial'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem acesso a este veiculo'
                ], 403);
                return;
            }

            [$filialWhere, $filialParams] = FilialHelper::whereFiliais('id_matriz_filial');

            $financeiroModel = new Financeiro();
            $faturas = $financeiroModel->listarPorVeiculo($id, $filialWhere, $filialParams);

            foreach ($faturas as &$fatura) {
                $fatura['valor_total_formatted'] = currency_format($fatura['valor_total'] ?? 0);
                $fatura['data_venci_formatted'] = $fatura['data_venci'] ? format_date($fatura['data_venci']) : '';
                $fatura['data_pago_formatted'] = $fatura['data_pago'] ? format_date($fatura['data_pago']) : '';

                if (($fatura['pago'] ?? 'N') === 'S') {
                    $fatura['status'] = 'paid';
                    $fatura['status_label'] = 'Pago';
                } elseif (!empty($fatura['data_venci']) && $fatura['data_venci'] < today()) {
                    $fatura['status'] = 'overdue';
                    $fatura['status_label'] = 'Vencido';
                } else {
                    $fatura['status'] = 'pending';
                    $fatura['status_label'] = 'Pendente';
                }

                if (!empty($fatura['id_locacao'])) {
                    $fatura['origem'] = 'Locacao';
                } elseif (!empty($fatura['id_contrato'])) {
                    $fatura['origem'] = 'Contrato';
                } else {
                    $fatura['origem'] = 'Avulsa';
                }
            }
            unset($fatura);

            Response::json([
                'success' => true,
                'data' => $faturas
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao listar faturas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui um veiculo
     *
     * POST /veiculos/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            // Verificar permissao
            if (!Auth::can('veiculos.excluir')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para excluir veiculos'
                ], 403);
                return;
            }

            $model = new Veiculo();
            $veiculo = $model->buscarPorId($id);

            if (!$veiculo) {
                Response::json([
                    'success' => false,
                    'message' => 'Veiculo nao encontrado'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($veiculo['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode excluir este veiculo'
                ], 403);
                return;
            }

            // Verificar acesso a filial
            if (!FilialHelper::temAcessoFilial($veiculo['id_matriz_filial'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem acesso a este veiculo'
                ], 403);
                return;
            }

            // Verificar vinculos
            $verificacao = $model->verificarVinculos($id);
            if ($verificacao['temVinculos']) {
                Response::json([
                    'success' => false,
                    'message' => 'Nao e possivel excluir o veiculo pois existem vinculos',
                    'vinculos' => $verificacao['detalhes'],
                    'pode_desativar' => true
                ], 422);
                return;
            }

            $model->excluir($id);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu veiculo [{$veiculo['placa']} - {$veiculo['marca']} {$veiculo['modelo']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Veiculo excluido com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir veiculo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Desativa um veiculo marcando disponibilidade como Excluido.
     *
     * POST /veiculos/{id}/desativar
     */
    public function desativar(Request $request, int $id): void
    {
        try {
            if (!Auth::can('veiculos.excluir')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para desativar veiculos'
                ], 403);
                return;
            }

            $model = new Veiculo();
            $veiculo = $model->buscarPorId($id);

            if (!$veiculo) {
                Response::json([
                    'success' => false,
                    'message' => 'Veiculo nao encontrado'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($veiculo['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode desativar este veiculo'
                ], 403);
                return;
            }

            if (!FilialHelper::temAcessoFilial($veiculo['id_matriz_filial'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem acesso a este veiculo'
                ], 403);
                return;
            }

            $model->atualizarDisponibilidade($id, 'E');

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", desativou veiculo [{$veiculo['placa']} - {$veiculo['marca']} {$veiculo['modelo']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Veiculo desativado com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao desativar veiculo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista encargos de um veiculo
     *
     * GET /api/veiculos/{id}/encargos
     */
    public function listarEncargos(Request $request, int $id): void
    {
        try {
            $model = new VeiculoEncargo();
            $encargos = $model->listarPorVeiculo($id);

            // Formatar valores para exibicao
            foreach ($encargos as &$encargo) {
                $encargo['valor_formatted'] = $encargo['valor'] !== null ? currency_format($encargo['valor']) : '';
                $encargo['vencimento_formatted'] = $encargo['vencimento'] ? format_date($encargo['vencimento']) : '';
            }

            Response::json([
                'success' => true,
                'data' => $encargos
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao listar encargos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria um encargo para um veiculo
     *
     * POST /veiculos/{id}/encargos/salvar
     */
    public function criarEncargo(Request $request, int $id): void
    {
        try {
            $chave = Auth::chave();
            $dados = $request->all();

            // Validar campos obrigatorios
            if (empty($dados['nome'])) {
                Response::json([
                    'success' => false,
                    'message' => 'O campo nome e obrigatorio'
                ], 422);
                return;
            }

            // Converter valor monetario
            if (!empty($dados['valor'])) {
                $dados['valor'] = currency_parse($dados['valor']);
            }

            // Converter data
            if (!empty($dados['vencimento'])) {
                $vencimento = $this->normalizarVencimentoEncargo($dados['vencimento']);
                if ($vencimento === null) {
                    Response::json([
                        'success' => false,
                        'message' => 'Informe uma data de vencimento valida'
                    ], 422);
                    return;
                }

                $dados['vencimento'] = $vencimento;
            }

            $dados['chave'] = $chave;
            $dados['id_veiculo'] = $id;

            $model = new VeiculoEncargo();
            $id = $model->criar($dados);

            Response::json([
                'success' => true,
                'message' => 'Encargo adicionado com sucesso',
                'data' => ['id' => $id]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar encargo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza um encargo
     *
     * POST /veiculos/{id}/encargos/{encargoId}/atualizar
     */
    public function atualizarEncargo(Request $request, int $id, int $encargoId): void
    {
        try {
            $dados = $request->all();

            $model = new VeiculoEncargo();
            $encargo = $model->buscarPorId($encargoId);

            if (!$encargo) {
                Response::json([
                    'success' => false,
                    'message' => 'Encargo nao encontrado'
                ], 404);
                return;
            }

            // Converter valor monetario
            if (isset($dados['valor'])) {
                $dados['valor'] = !empty($dados['valor']) ? currency_parse($dados['valor']) : null;
            }

            // Converter data
            if (isset($dados['vencimento'])) {
                if (!empty($dados['vencimento'])) {
                    $vencimento = $this->normalizarVencimentoEncargo($dados['vencimento']);
                    if ($vencimento === null) {
                        Response::json([
                            'success' => false,
                            'message' => 'Informe uma data de vencimento valida'
                        ], 422);
                        return;
                    }

                    $dados['vencimento'] = $vencimento;
                } else {
                    $dados['vencimento'] = null;
                }
            }

            $model->atualizar($encargoId, $dados);

            Response::json([
                'success' => true,
                'message' => 'Encargo atualizado com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar encargo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui um encargo
     *
     * POST /veiculos/{id}/encargos/{encargoId}/excluir
     */
    public function excluirEncargo(Request $request, int $id, int $encargoId): void
    {
        try {

            $model = new VeiculoEncargo();
            $encargo = $model->buscarPorId($encargoId);

            if (!$encargo) {
                Response::json([
                    'success' => false,
                    'message' => 'Encargo nao encontrado'
                ], 404);
                return;
            }

            $model->excluir($encargoId);

            Response::json([
                'success' => true,
                'message' => 'Encargo excluido com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir encargo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Monta a configuracao usada pelo helper Currency no iframe.
     */
    private function configMoedaFilial(array $filial): array
    {
        $locale = (string) ($filial['locale'] ?? 'pt_BR');
        $currency = (string) ($filial['currency_code'] ?? 'BRL');

        $locales = [
            'pt_BR' => ['decimal' => ',', 'thousands' => '.', 'symbolPosition' => 'before'],
            'en_US' => ['decimal' => '.', 'thousands' => ',', 'symbolPosition' => 'before'],
            'pt_PT' => ['decimal' => ',', 'thousands' => '.', 'symbolPosition' => 'after'],
            'es_ES' => ['decimal' => ',', 'thousands' => '.', 'symbolPosition' => 'after'],
            'it_IT' => ['decimal' => ',', 'thousands' => '.', 'symbolPosition' => 'after'],
        ];
        $symbols = [
            'BRL' => 'R$',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
        ];
        $localeConfig = $locales[$locale] ?? $locales['pt_BR'];

        return [
            'locale' => $locale,
            'currency' => $currency,
            'symbol' => $symbols[$currency] ?? $currency,
            'decimal' => $localeConfig['decimal'],
            'thousands' => $localeConfig['thousands'],
            'symbolPosition' => $localeConfig['symbolPosition'],
        ];
    }

    private function normalizarVencimentoEncargo(?string $vencimento): ?string
    {
        $vencimento = trim((string) $vencimento);
        if ($vencimento === '') {
            return null;
        }

        $iso = \DateTime::createFromFormat('!Y-m-d', $vencimento);
        $isoErrors = \DateTime::getLastErrors();
        if ($iso !== false
            && ($isoErrors === false || ($isoErrors['warning_count'] === 0 && $isoErrors['error_count'] === 0))
            && $iso->format('Y-m-d') === $vencimento) {
            return $vencimento;
        }

        preg_match_all('/\d+/', $vencimento, $matches);
        if (count($matches[0]) < 3) {
            return null;
        }

        $config = date_config();
        $format = $config['date_format'] ?? 'd/m/Y';
        $date = \DateTime::createFromFormat('!' . $format, $vencimento);
        $errors = \DateTime::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date->format('Y-m-d');
    }
}
