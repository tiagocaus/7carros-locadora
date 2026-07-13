<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Financeiro;
use App\Models\FinanceiroItem;
use App\Models\MatrizFilial;
use App\Models\PlanoDeContas;
use App\Models\Cliente;
use App\Models\ContatoEmail;
use App\Models\Fornecedor;
use App\Models\FormaPagamento;
use App\Models\GatewayPagamento;
use App\Models\Whatsapp;
use App\Models\Sms;
use App\Helpers\FilialHelper;
use App\Helpers\DateHelper;
use App\Helpers\PdfHelper;
use App\Config\Planos;
use App\I18n\TemplateVariables;
use App\Services\AuditLogService;
use App\Services\ComissaoInvestidorService;
use App\Services\PagamentoLinkSyncService;
use SimpleSoftwareIO\QrCode\Generator as QrCodeGenerator;

/**
 * Controller de Financeiro (Lancamentos)
 *
 * Gerencia operacoes CRUD de lancamentos financeiros.
 *
 * Permissoes:
 * - financeiro.visualizar
 * - financeiro.criar
 * - financeiro.editar
 * - financeiro.excluir
 */
class FinanceiroController
{
    /** @var array<int,string> Arquivos temporarios criados durante geracao de PDF (QR code etc) */
    private array $tmpFiles = [];

    private function mensagemErroBanco(\Throwable $e, string $contexto): string
    {
        if (str_contains($e->getMessage(), 'Lock wait timeout exceeded')) {
            return "{$contexto}: o sistema esta processando outro lancamento financeiro no momento. Tente novamente em instantes.";
        }

        return "{$contexto}: " . $e->getMessage();
    }

    /**
     * Valida campos obrigatorios do lancamento
     *
     * @param array $dados Dados do formulario
     * @return string|null Mensagem de erro ou null se valido
     */
    private function validarCamposObrigatorios(array $dados): ?string
    {
        // Campos obrigatorios simples
        $camposObrigatorios = [
            'id_conta' => 'Conta bancária',
            'id_forma_pagamento' => 'Forma de Pagamento',
            'id_plano_de_conta' => 'Plano de Contas',
            'descricao' => 'Descricao',
            'data_criada' => 'Data de Criacao',
            'id_matriz_filial' => 'Matriz/Filial'
        ];

        foreach ($camposObrigatorios as $campo => $nome) {
            if (empty($dados[$campo])) {
                return "Campo obrigatorio nao preenchido: {$nome}";
            }
        }

        // Validar vinculo (pelo menos um: Cliente, Fornecedor, Funcionario ou Veiculo)
        $temCliente = !empty($dados['id_cliente']);
        $temFornecedor = !empty($dados['id_fornecedor']);
        $temFuncionario = !empty($dados['id_funcionario']);
        $temVeiculo = !empty($dados['id_veiculo']);

        if (!$temCliente && !$temFornecedor && !$temFuncionario && !$temVeiculo) {
            return 'Informe pelo menos um: Cliente, Fornecedor, Funcionario ou Veiculo';
        }

        if ($temVeiculo && !empty($dados['itens']) && is_array($dados['itens'])) {
            $idVeiculoCabecalho = (int) $dados['id_veiculo'];
            foreach ($dados['itens'] as $item) {
                $itemValido = currency_parse($item['valor'] ?? 0) > 0 || !empty(trim((string) ($item['descricao'] ?? '')));
                if ($itemValido && !empty($item['id_veiculo']) && (int) $item['id_veiculo'] !== $idVeiculoCabecalho) {
                    return 'O veiculo do vinculo e diferente do veiculo informado em um item. Remova o veiculo do vinculo ou use o mesmo veiculo nos itens.';
                }
            }
        }

        // Itens sao opcionais, mas se nao houver itens, valor_subtotal deve ser > 0
        $temItens = !empty($dados['itens']) && is_array($dados['itens']) && count($dados['itens']) > 0;
        $valorSubtotal = isset($dados['valor_subtotal']) ? floatval($dados['valor_subtotal']) : 0;

        if (!$temItens && $valorSubtotal <= 0) {
            return 'Informe o Subtotal ou adicione pelo menos um item';
        }

        // Se ha itens, validar se pelo menos um e valido (tem valor ou descricao)
        if ($temItens) {
            $temItemValido = false;
            foreach ($dados['itens'] as $item) {
                $valor = isset($item['valor']) ? floatval($item['valor']) : 0;
                $descricao = isset($item['descricao']) ? trim($item['descricao']) : '';
                if ($valor > 0 || !empty($descricao)) {
                    $temItemValido = true;
                    break;
                }
            }

            if (!$temItemValido) {
                return 'Adicione pelo menos um item valido ao lancamento';
            }
        }

        // Validar data de pagamento (obrigatoria se pago = 'S')
        if (isset($dados['pago']) && $dados['pago'] === 'S') {
            if (empty($dados['data_pago'])) {
                return 'Data do Pagamento e obrigatoria quando o lancamento esta marcado como pago';
            }
        }

        return null;
    }

    /**
     * Renderiza a pagina de listagem
     *
     * GET /pages/financeiro
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.financeiro.index');
        Response::html($html);
    }

    /**
     * Renderiza a pagina de adicionar/editar
     *
     * GET /pages/financeiro/adicionar
     */
    public function viewAdicionar(Request $request): void
    {
        $html = Template::render('pages.financeiro.adicionar');
        Response::html($html);
    }

    /**
     * Lista lancamentos com paginacao e busca
     *
     * GET /api/financeiro
     * Query params: page, perPage, search, filial, ano, mes, status, tipo
     */
    public function index(Request $request): void
    {
        try {
            if (!Auth::can('financeiro.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para visualizar lancamentos'
                ], 403);
                return;
            }

            $chave = Auth::chave();

            // Parametros de paginacao
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');

            // Parametros de filtro
            $filialId = $request->query('filial', '');
            $ano = $request->query('ano', '');
            $mes = $request->query('mes', '');
            $status = $request->query('status', '');
            $tipo = $request->query('tipo', '');
            if (!in_array($tipo, ['R', 'D'], true)) {
                $tipo = '';
            }

            // Validar acesso a filial selecionada
            if (!empty($filialId) && !FilialHelper::temAcessoFilial((int) $filialId)) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para acessar esta filial'
                ], 403);
                return;
            }

            // Filtro de filial (permissoes do usuario)
            [$filialWhere, $filialParams] = FilialHelper::whereFiliais('id_matriz_filial');

            $financeiroModel = new Financeiro();

            // Buscar lancamentos paginados
            $lancamentos = $financeiroModel->listarPaginado(
                $chave,
                $page,
                $perPage,
                $search,
                $filialWhere,
                $filialParams,
                $filialId,
                $ano,
                $mes,
                $status,
                $tipo
            );

            // Contar total
            $total = $financeiroModel->contar($chave, $search, $filialWhere, $filialParams, $filialId, $ano, $mes, $status, $tipo);

            // Total de paginas
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            // Processar descricao i18n dos planos de contas
            $lancamentos = array_map([$this, 'processarDescricaoPlanoContas'], $lancamentos);

            Response::json([
                'success' => true,
                'data' => $lancamentos,
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
                'message' => 'Erro ao buscar lancamentos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe um lancamento com seus itens
     *
     * GET /api/financeiro/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            if (!Auth::can('financeiro.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para visualizar lancamentos'
                ], 403);
                return;
            }

            $financeiroModel = new Financeiro();
            $lancamento = $financeiroModel->buscarPorIdComItens($id);

            if (!$lancamento) {
                Response::json([
                    'success' => false,
                    'message' => 'Lancamento nao encontrado'
                ], 404);
                return;
            }

            // Verificar tenant
            $chave = Auth::chave();
            if ($lancamento['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Lancamento nao encontrado'
                ], 404);
                return;
            }

            // Verificar acesso a filial
            if (!FilialHelper::temAcessoFilial($lancamento['id_matriz_filial'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para acessar este lancamento'
                ], 403);
                return;
            }

            // Processar descricao i18n do plano de contas
            $lancamento = $this->processarDescricaoPlanoContas($lancamento);

            Response::json([
                'success' => true,
                'data' => $lancamento
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar lancamento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria um novo lancamento
     *
     * POST /financeiro/salvar
     */
    public function store(Request $request): void
    {
        try {
            if (!Auth::can('financeiro.criar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para criar lancamentos'
                ], 403);
                return;
            }

            $dados = $request->all();
            $chave = Auth::chave();
            $dados['chave'] = $chave;

            // Validacao basica
            if (empty($dados['tipo']) || !in_array($dados['tipo'], ['D', 'R'], true)) {
                Response::json([
                    'success' => false,
                    'message' => 'Tipo invalido. Use D (Despesa) ou R (Receita)'
                ], 400);
                return;
            }

            if (empty($dados['data_venci'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Data de vencimento e obrigatoria'
                ], 400);
                return;
            }

            // Validar campos obrigatorios
            $erroValidacao = $this->validarCamposObrigatorios($dados);
            if ($erroValidacao !== null) {
                Response::json([
                    'success' => false,
                    'message' => $erroValidacao
                ], 422);
                return;
            }

            // Criar lancamento completo (com itens e parcelas) atomicamente
            $id = (new Financeiro())->criarCompleto($dados);

            // Registrar log usando dados do frontend (capturados pelo form-audit.js)
            $nomeUsuario = $_SESSION['user_name'] ?? 'Sistema';
            $descricaoResumida = mb_strlen($dados['descricao'] ?? '') > 40
                ? mb_substr($dados['descricao'], 0, 40) . '...'
                : ($dados['descricao'] ?? "#{$id}");

            $mensagem = "{$nomeUsuario}, adicionou o lancamento financeiro [{$descricaoResumida}]";

            AuditLogService::registrarComAuditFrontend($mensagem, $dados['_audit_data'] ?? null, null);

            Response::json([
                'success' => true,
                'message' => 'Lancamento criado com sucesso',
                'data' => ['id' => $id]
            ], 201);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $this->mensagemErroBanco($e, 'Erro ao criar lancamento')
            ], 500);
        }
    }

    /**
     * Atualiza um lancamento
     *
     * POST /financeiro/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            if (!Auth::can('financeiro.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para editar lancamentos'
                ], 403);
                return;
            }

            $financeiroModel = new Financeiro();
            $lancamento = $financeiroModel->buscarPorId($id);

            if (!$lancamento) {
                Response::json([
                    'success' => false,
                    'message' => 'Lancamento nao encontrado'
                ], 404);
                return;
            }

            // Verificar tenant
            $chave = Auth::chave();
            if ($lancamento['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode editar este lancamento'
                ], 403);
                return;
            }

            // Verificar acesso a filial
            if (!FilialHelper::temAcessoFilial($lancamento['id_matriz_filial'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para editar este lancamento'
                ], 403);
                return;
            }

            $dados = $request->all();

            // Para validacao na edicao, considerar dados existentes se nao enviados
            $dadosParaValidacao = $dados;

            // Se valor_subtotal nao foi enviado ou esta vazio, usar o existente
            $valorSubtotalEnviado = isset($dados['valor_subtotal']) ? floatval($dados['valor_subtotal']) : 0;
            if ($valorSubtotalEnviado <= 0) {
                $dadosParaValidacao['valor_subtotal'] = $lancamento['valor_subtotal'] ?? 0;
            }

            // Se itens nao foram enviados, verificar se existem no banco
            if (empty($dados['itens'])) {
                $financeiroItemModel = new FinanceiroItem();
                $itensExistentes = $financeiroItemModel->listarPorFinanceiro($id);
                if (!empty($itensExistentes)) {
                    $dadosParaValidacao['itens'] = $itensExistentes;
                }
            }

            // Validar campos obrigatorios
            $erroValidacao = $this->validarCamposObrigatorios($dadosParaValidacao);
            if ($erroValidacao !== null) {
                Response::json([
                    'success' => false,
                    'message' => $erroValidacao
                ], 422);
                return;
            }

            // Verificar se houve conversao de valor_subtotal para item
            $financeiroItemModel = new FinanceiroItem();
            $itensAntes = $financeiroItemModel->contarPorFinanceiro($id);
            $itensDepois = isset($dados['itens']) && is_array($dados['itens']) ? count($dados['itens']) : 0;
            $valorSubtotalOriginal = floatval($lancamento['valor_subtotal'] ?? 0);

            (new PagamentoLinkSyncService())->invalidarSeDadosAfetamCobranca($id, $chave, $dados);

            // Atualizar lancamento com auditoria
            $financeiroModel->atualizarComAuditoria($id, $dados);

            // Sincronizar multa vinculada (se existir)
            if (isset($dados['pago']) && !empty($lancamento['id_multa'])) {
                $multaModel = new \App\Models\Multa();
                if ($dados['pago'] === 'S') {
                    $multaModel->marcarPagoSemSyncFinanceiro((int) $lancamento['id_multa']);
                } elseif ($dados['pago'] === 'N') {
                    $multaModel->marcarNaoPagoSemSyncFinanceiro((int) $lancamento['id_multa']);
                }
            }

            // Hook: Gerar comissao de investidor quando fatura e marcada como paga
            if (
                isset($dados['pago']) &&
                $dados['pago'] === 'S' &&
                $lancamento['pago'] !== 'S' &&
                (!empty($lancamento['id_locacao']) || !empty($lancamento['id_contrato']))
            ) {
                try {
                    $comissaoService = new \App\Services\ComissaoInvestidorService();
                    $comissaoService->processarComissaoPorFinanceiro($id);
                } catch (\Exception $e) {
                    error_log("[Comissao] Erro no hook do FinanceiroController: " . $e->getMessage());
                }
            }

            // Atualizar itens se enviados
            if (isset($dados['itens']) && is_array($dados['itens'])) {
                $financeiroItemModel->salvarTodos($id, $chave, $dados['itens']);
                if (count($dados['itens']) > 0) {
                    $financeiroModel->recalcularTotal($id);
                }
            }

            // Registrar log de conversao se lancamento nao tinha itens e agora tem
            if ($itensAntes === 0 && $itensDepois > 0 && $valorSubtotalOriginal > 0) {
                AuditLogService::registrarComCampos(
                    "Converteu valor principal R$ " . number_format($valorSubtotalOriginal, 2, ',', '.') . " em item no lancamento financeiro [#{$id}]",
                    [
                        AuditLogService::campo('Subtotal Original', currency_format($valorSubtotalOriginal), null),
                        AuditLogService::campo('Itens Antes', '0', (string) $itensDepois),
                    ]
                );
            }

            Response::json([
                'success' => true,
                'message' => 'Lancamento atualizado com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar lancamento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registra baixa parcial criando uma nova fatura com a diferenca.
     *
     * POST /financeiro/{id}/baixa-parcial
     */
    public function baixaParcial(Request $request, int $id): void
    {
        try {
            if (!Auth::can('financeiro.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para editar lancamentos'
                ], 403);
                return;
            }

            $financeiroModel = new Financeiro();
            $lancamento = $financeiroModel->buscarPorId($id);

            if (!$lancamento) {
                Response::json([
                    'success' => false,
                    'message' => 'Lancamento nao encontrado'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($lancamento['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Lancamento nao encontrado'
                ], 404);
                return;
            }

            if (!FilialHelper::temAcessoFilial($lancamento['id_matriz_filial'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para editar este lancamento'
                ], 403);
                return;
            }

            $dados = $request->all();
            $valorPago = currency_parse($dados['valor_pago'] ?? 0);
            $dataPago = $dados['data_pago'] ?? DateHelper::todayForDatabase();
            $dataVenciDiferenca = $dados['data_venci_diferenca'] ?? ($lancamento['data_venci'] ?? DateHelper::todayForDatabase());

            (new PagamentoLinkSyncService())->invalidarLinksPendentes($id, $chave);

            $resultado = $financeiroModel->baixarParcial(
                $id,
                $valorPago,
                $dataPago,
                $dataVenciDiferenca,
                $chave
            );

            if (
                ($lancamento['tipo'] ?? '') === 'R' &&
                (!empty($lancamento['id_locacao']) || !empty($lancamento['id_contrato']))
            ) {
                try {
                    $comissaoService = new ComissaoInvestidorService();
                    $comissaoService->processarComissaoPorFinanceiro($id);
                } catch (\Exception $e) {
                    error_log("[Comissao] Erro no hook de baixa parcial: " . $e->getMessage());
                }
            }

            $nomeUsuario = $_SESSION['user_name'] ?? 'Sistema';
            AuditLogService::registrarComCampos(
                "{$nomeUsuario}, registrou baixa parcial no lancamento financeiro [#{$id}] e criou a diferenca [#{$resultado['id_diferenca']}]",
                [
                    AuditLogService::campo('Valor Original', currency_format($resultado['valor_original'], true), null),
                    AuditLogService::campo('Valor Pago', null, currency_format($resultado['valor_pago'], true)),
                    AuditLogService::campo('Diferenca Criada', null, currency_format($resultado['valor_diferenca'], true)),
                    AuditLogService::campo('Data do Pagamento', null, format_date($dataPago)),
                    AuditLogService::campo('Vencimento da Diferenca', null, format_date($dataVenciDiferenca)),
                ]
            );

            Response::json([
                'success' => true,
                'message' => 'Baixa parcial registrada com sucesso',
                'data' => $resultado
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $this->mensagemErroBanco($e, 'Erro ao registrar baixa parcial')
            ], 500);
        }
    }

    /**
     * Exclui um lancamento
     *
     * POST /financeiro/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            if (!Auth::can('financeiro.excluir')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para excluir lancamentos'
                ], 403);
                return;
            }

            $financeiroModel = new Financeiro();
            $lancamento = $financeiroModel->buscarPorId($id);

            if (!$lancamento) {
                Response::json([
                    'success' => false,
                    'message' => 'Lancamento nao encontrado'
                ], 404);
                return;
            }

            // Verificar tenant
            $chave = Auth::chave();
            if ($lancamento['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode excluir este lancamento'
                ], 403);
                return;
            }

            // Verificar acesso a filial
            if (!FilialHelper::temAcessoFilial($lancamento['id_matriz_filial'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para excluir este lancamento'
                ], 403);
                return;
            }

            // Verificar vinculos
            $verificacao = $financeiroModel->verificarVinculos($id);
            if ($verificacao['temVinculos']) {
                Response::json([
                    'success' => false,
                    'message' => 'Nao e possivel excluir este lancamento pois existem registros vinculados',
                    'vinculos' => $verificacao['detalhes']
                ], 422);
                return;
            }

            // Identificador simples para mensagem (detalhes vão nos campos)
            $identificador = $lancamento['descricao'] ?? $id;

            // Preparar campos para log detalhado
            $camposLog = $this->prepararCamposLogFinanceiro($lancamento);

            // Excluir (itens sao excluidos via FK CASCADE)
            $financeiroModel->deletar($id);

            // Registrar auditoria com dados completos
            $nomeUsuario = $_SESSION['user_name'] ?? 'Sistema';
            $mensagem = "{$nomeUsuario}, excluiu o lancamento financeiro [{$identificador}]";
            AuditLogService::registrarComCampos($mensagem, $camposLog);

            Response::json([
                'success' => true,
                'message' => 'Lancamento excluido com sucesso',
                'id_cliente' => $lancamento['id_cliente'] ?? null
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir lancamento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista planos de contas para select
     *
     * GET /api/financeiro/planos-de-contas
     */
    public function planosDeContas(Request $request): void
    {
        try {
            $chave = Auth::chave();
            $search = $request->query('q', '');
            $tipo = $request->query('tipo', '');

            // Validar tipo
            if (!empty($tipo) && !in_array($tipo, ['R', 'D', 'P'], true)) {
                $tipo = '';
            }

            $model = new Financeiro();
            $planos = $model->listarPlanosDeContasSelect($chave, $tipo, $search);

            Response::json([
                'success' => true,
                'data' => $planos
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar planos de contas: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==========================================
    // ENDPOINTS DE PARCELAMENTO
    // ==========================================

    /**
     * Lista parcelas de um lancamento
     *
     * GET /api/financeiro/{id}/parcelas
     */
    public function parcelas(Request $request, int $id): void
    {
        try {
            if (!Auth::can('financeiro.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para visualizar lancamentos'
                ], 403);
                return;
            }

            $financeiroModel = new Financeiro();
            $lancamento = $financeiroModel->buscarPorId($id);

            if (!$lancamento) {
                Response::json([
                    'success' => false,
                    'message' => 'Lancamento nao encontrado'
                ], 404);
                return;
            }

            // Verificar tenant
            $chave = Auth::chave();
            if ($lancamento['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Lancamento nao encontrado'
                ], 404);
                return;
            }

            // Determinar o ID da origem
            $idOrigem = $financeiroModel->buscarIdOrigem($id);

            // Listar parcelas
            $parcelas = $financeiroModel->listarParcelas($idOrigem);

            Response::json([
                'success' => true,
                'data' => $parcelas
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar parcelas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza parcelas em lote
     *
     * POST /financeiro/parcelas/atualizar-lote
     */
    public function atualizarParcelasLote(Request $request): void
    {
        try {
            if (!Auth::can('financeiro.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para editar lancamentos'
                ], 403);
                return;
            }

            $dados = $request->all();

            if (empty($dados['ids']) || !is_array($dados['ids'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Informe os IDs das parcelas a atualizar'
                ], 400);
                return;
            }

            $chave = Auth::chave();
            $financeiroModel = new Financeiro();

            $campos = [];
            if (isset($dados['data_venci'])) {
                $campos['data_venci'] = $dados['data_venci'];
            }
            if (isset($dados['pago'])) {
                $campos['pago'] = $dados['pago'];
            }
            if (isset($dados['data_pago'])) {
                $campos['data_pago'] = $dados['data_pago'];
            }

            $idsElegiveisComissao = [];
            if (($campos['pago'] ?? null) === 'S') {
                $idsElegiveisComissao = $financeiroModel->listarIdsElegiveisComissaoPagamentoLote($dados['ids']);
            }

            $atualizados = $financeiroModel->atualizarParcelasLote($dados['ids'], $campos, $chave);
            $comissoesGeradas = 0;

            if (!empty($idsElegiveisComissao)) {
                $comissaoService = new ComissaoInvestidorService();

                foreach ($idsElegiveisComissao as $idFinanceiro) {
                    try {
                        $comissaoId = $comissaoService->processarComissaoPorFinanceiro((int) $idFinanceiro);
                        if ($comissaoId) {
                            $comissoesGeradas++;
                        }
                    } catch (\Exception $e) {
                        error_log("[Comissao] Erro no hook de parcelas em lote: " . $e->getMessage());
                    }
                }
            }

            Response::json([
                'success' => true,
                'message' => "{$atualizados} parcela(s) atualizada(s)",
                'data' => [
                    'atualizados' => $atualizados,
                    'comissoes_geradas' => $comissoesGeradas
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar parcelas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui parcelas em lote
     *
     * POST /financeiro/parcelas/excluir-lote
     */
    public function excluirParcelasLote(Request $request): void
    {
        try {
            if (!Auth::can('financeiro.excluir')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para excluir lancamentos'
                ], 403);
                return;
            }

            $dados = $request->all();

            if (empty($dados['ids']) || !is_array($dados['ids'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Informe os IDs das parcelas a excluir'
                ], 400);
                return;
            }

            $chave = Auth::chave();
            $financeiroModel = new Financeiro();

            $excluidos = $financeiroModel->excluirParcelasLote($dados['ids'], $chave);

            Response::json([
                'success' => true,
                'message' => "{$excluidos} parcela(s) excluida(s)",
                'data' => ['excluidos' => $excluidos]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir parcelas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Prepara campos do financeiro para log de auditoria
     *
     * @param array $dados Dados do lancamento
     * @return array Campos formatados para log
     */
    private function prepararCamposLogFinanceiro(array $dados): array
    {
        $campos = [];
        $mapeamento = [
            'sequencia' => 'Sequência',
            'codigo' => 'Código',
            'descricao' => 'Descrição',
            'tipo' => 'Tipo',
            'pago' => 'Situação',
            'data_venci' => 'Vencimento',
            'data_pago' => 'Pagamento',
            'valor_subtotal' => 'Subtotal',
            'juros' => 'Juros',
            'multa' => 'Multa',
            'desconto' => 'Desconto',
            'valor_total' => 'Valor Total',
            'cliente_nome' => 'Cliente',
            'fornecedor_nome' => 'Fornecedor',
            'filial_nome' => 'Filial',
            'plano_conta_descricao' => 'Plano de Contas',
            'forma_pagamento_descricao' => 'Forma Pagamento',
        ];

        foreach ($mapeamento as $campo => $label) {
            if (isset($dados[$campo]) && $dados[$campo] !== '' && $dados[$campo] !== null) {
                $valor = $dados[$campo];

                // Formatações especiais
                if ($campo === 'tipo') {
                    $valor = $valor === 'R' ? 'Receita' : 'Despesa';
                } elseif ($campo === 'pago') {
                    $valor = $valor === 'S' ? 'Pago' : 'Pendente';
                } elseif (in_array($campo, ['valor_subtotal', 'juros', 'multa', 'desconto', 'valor_total'], true)) {
                    $valor = currency_format((float)$valor, true);
                } elseif (in_array($campo, ['data_venci', 'data_pago'], true) && $valor) {
                    $valor = format_date($valor);
                }

                $campos[] = ['label' => $label, 'de' => $valor, 'para' => ''];
            }
        }

        return $campos;
    }

    /**
     * Retorna ou cria link de pagamento para um lancamento
     *
     * GET /api/financeiro/{id}/link-pagamento
     */
    public function getLinkPagamento(Request $request, int $id): void
    {
        try {
            $financeiroModel = new Financeiro();
            $financeiro = $financeiroModel->buscarPorId($id);

            if (!$financeiro) {
                Response::json([
                    'success' => false,
                    'message' => 'Lancamento nao encontrado'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($financeiro['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Lancamento nao encontrado'
                ], 404);
                return;
            }

            // Verificar se e receita
            if ($financeiro['tipo'] !== 'R') {
                Response::json([
                    'success' => false,
                    'message' => 'Link de pagamento disponivel apenas para receitas'
                ], 400);
                return;
            }

            // Verificar se ja esta pago
            if ($financeiro['pago'] === 'S') {
                Response::json([
                    'success' => false,
                    'message' => 'Este lancamento ja foi pago'
                ], 400);
                return;
            }

            $link = (new PagamentoLinkSyncService())->obterOuCriarLinkAtualizado($id, $chave);

            Response::json([
                'success' => true,
                'url' => $link['url']
            ]);

        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao gerar link: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Renderiza o offcanvas de impressao/envio da fatura.
     *
     * GET /pages/financeiro/offcanvas-impressao?id={id}
     */
    public function offcanvasImpressao(Request $request): void
    {
        $id = (int) $request->query('id');
        $lancamento = (new Financeiro())->buscarPorId($id);

        if (!$lancamento) {
            Response::html('<p>Lancamento nao encontrado</p>', 404);
            return;
        }

        $chave = Auth::chave();
        if ($lancamento['chave'] !== $chave) {
            Response::html('<p>Acesso negado</p>', 403);
            return;
        }

        if (!FilialHelper::temAcessoFilial($lancamento['id_matriz_filial'] ?? null)) {
            Response::html('<p>Acesso negado</p>', 403);
            return;
        }

        $cliente = !empty($lancamento['id_cliente'])
            ? (new Cliente())->buscarPorIdComContatos((int) $lancamento['id_cliente'])
            : null;
        $fornecedor = !empty($lancamento['id_fornecedor'])
            ? (new Fornecedor())->buscarPorId((int) $lancamento['id_fornecedor'])
            : null;
        $tipoReceita = ($lancamento['tipo'] ?? '') === 'R';
        $contraparte = $tipoReceita ? ($cliente ?? []) : ($fornecedor ?? []);

        $planoCodigo = Auth::user()['plano'] ?? 'G';
        $planoInfo = Planos::getPlano($planoCodigo) ?? [];

        $filialId = (int) ($lancamento['id_matriz_filial'] ?? 0);
        $telefone = $tipoReceita ? trim((string) ($cliente['celular'] ?? $cliente['telefone'] ?? '')) : '';
        $temEmail = $tipoReceita && ($planoInfo['smtp'] ?? 0) > 0 && !empty($cliente['email']);
        $temWhatsapp = $tipoReceita
            && ($planoInfo['whatsapp'] ?? 0) > 0
            && $telefone !== ''
            && $filialId > 0
            && (new Whatsapp())->buscarConectadaPorFilial($filialId) !== null;
        $temSms = $tipoReceita
            && ($planoInfo['sms'] ?? 0) > 0
            && $telefone !== ''
            && $filialId > 0
            && (new Sms())->buscarValidadaPorFilial($filialId) !== null;

        $html = Template::render('pages.financeiro.offcanvas-impressao', [
            'lancamento' => $lancamento,
            'cliente' => $cliente ?? [],
            'fornecedor' => $fornecedor ?? [],
            'contraparte' => $contraparte,
            'tipoReceita' => $tipoReceita,
            'temEmail' => $temEmail,
            'temWhatsapp' => $temWhatsapp,
            'temSms' => $temSms,
        ]);
        Response::html($html);
    }

    /**
     * Gera PDF da fatura para impressao inline.
     *
     * GET /financeiro/{id}/imprimir/fatura
     */
    public function imprimirFatura(Request $request, int $id): void
    {
        try {
            $lancamento = (new Financeiro())->buscarPorIdComItens($id);

            if (!$lancamento) {
                Response::html('<h1>Lancamento nao encontrado</h1>', 404);
                return;
            }

            $chave = Auth::chave();
            if ($lancamento['chave'] !== $chave) {
                Response::html('<h1>Acesso negado</h1>', 403);
                return;
            }

            if (!FilialHelper::temAcessoFilial($lancamento['id_matriz_filial'] ?? null)) {
                Response::html('<h1>Acesso negado</h1>', 403);
                return;
            }

            $html = $this->renderFaturaHtml($lancamento, $chave);

            PdfHelper::outputInline($html, 'fatura-' . ($lancamento['codigo'] ?? $id) . '.pdf', [
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 5,
                'margin_bottom' => 5,
            ]);

            $this->limparArquivosTemporarios();
            exit;

        } catch (\Exception $e) {
            Response::html('<h1>Erro ao gerar fatura: ' . htmlspecialchars($e->getMessage()) . '</h1>', 500);
        }
    }

    /**
     * Envia a fatura por email/whatsapp/sms.
     *
     * POST /financeiro/{id}/enviar
     * Body: { canal: 'email'|'whatsapp'|'sms' }
     */
    public function enviar(Request $request, int $id): void
    {
        try {
            $canal = $request->all()['canal'] ?? 'email';
            if (!in_array($canal, ['email', 'whatsapp', 'sms'], true)) {
                Response::json(['success' => false, 'message' => 'Canal invalido'], 400);
                return;
            }

            $lancamento = (new Financeiro())->buscarPorIdComItens($id);
            if (!$lancamento) {
                Response::json(['success' => false, 'message' => 'Lancamento nao encontrado'], 404);
                return;
            }

            $chave = Auth::chave();
            if ($lancamento['chave'] !== $chave) {
                Response::json(['success' => false, 'message' => 'Acesso negado'], 403);
                return;
            }

            if (!FilialHelper::temAcessoFilial($lancamento['id_matriz_filial'] ?? null)) {
                Response::json(['success' => false, 'message' => 'Acesso negado'], 403);
                return;
            }

            if (empty($lancamento['id_cliente'])) {
                Response::json(['success' => false, 'message' => 'Lancamento sem cliente vinculado'], 400);
                return;
            }

            $cliente = (new Cliente())->buscarPorIdComContatos((int) $lancamento['id_cliente']);
            if (!$cliente) {
                Response::json(['success' => false, 'message' => 'Cliente nao encontrado'], 404);
                return;
            }

            $destinatario = match ($canal) {
                'email' => $cliente['email'] ?? '',
                'whatsapp', 'sms' => $cliente['celular'] ?? $cliente['telefone'] ?? '',
            };

            if ($canal === 'email') {
                $emailsAutorizados = (new ContatoEmail())->listarParaEnvio('cliente', (int) $lancamento['id_cliente'], $chave);
                if ($emailsAutorizados === []) {
                    throw new \InvalidArgumentException('Cliente sem email autorizado para envio');
                }
                $destinatario = (string) $emailsAutorizados[0]['email'];
            }

            validate_queue_message($canal, [
                'to' => $destinatario,
                'id_matriz_filial' => $lancamento['id_matriz_filial'] ?? null,
            ]);

            $html = $this->renderFaturaHtml($lancamento, $chave);
            $pdfContent = PdfHelper::generateAsString($html, [
                'margin_left' => 10, 'margin_right' => 10, 'margin_top' => 5, 'margin_bottom' => 5,
            ]);

            $codigoSafe = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($lancamento['codigo'] ?? $id));
            $filename = 'fatura_' . ($codigoSafe ?: $id) . '_' . DateHelper::timestamp() . '.pdf';
            $tempDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/storage/temp';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            $tempPath = $tempDir . '/' . $filename;
            file_put_contents($tempPath, $pdfContent);

            $empresa = (new MatrizFilial())->buscarDadosEmpresa($lancamento['id_matriz_filial'] ?? null) ?? [];
            $nomeEmpresa = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'Locadora';
            $codigo = $lancamento['codigo'] ?? '#' . $id;
            $valorBR = number_format((float) ($lancamento['valor_total'] ?? 0), 2, ',', '.');
            $venciBR = !empty($lancamento['data_venci']) ? format_date($lancamento['data_venci']) : '-';
            $parcelaDescricao = TemplateVariables::formatInvoiceInstallment(
                (int) ($lancamento['parcela'] ?? 0),
                (int) ($lancamento['total_parcelas'] ?? 0),
                (string) ($cliente['preferred_locale'] ?? current_locale())
            );
            $parcelaHtml = $parcelaDescricao
                ? '<p><strong>' . htmlspecialchars($parcelaDescricao, ENT_QUOTES, 'UTF-8') . '</strong></p>'
                : '';
            $parcelaTexto = $parcelaDescricao ? "\n" . $parcelaDescricao : '';

            if ($canal === 'email') {
                queue_client_email((int) $lancamento['id_cliente'], [
                    'to' => $destinatario,
                    'to_name' => $cliente['nome_rsocial'] ?? '',
                    'subject' => 'Fatura ' . $codigo . ' - ' . $nomeEmpresa,
                    'body' => '<p>Ola, ' . htmlspecialchars($cliente['nome_rsocial'] ?? '') . '!</p>'
                        . '<p>Segue em anexo a fatura <strong>' . htmlspecialchars($codigo) . '</strong>.</p>'
                        . $parcelaHtml
                        . '<p>Valor: <strong>R$ ' . $valorBR . '</strong><br>Vencimento: <strong>' . $venciBR . '</strong></p>'
                        . '<p>Atenciosamente,<br>' . htmlspecialchars($nomeEmpresa) . '</p>',
                    'attachments' => [$tempPath],
                    'id_matriz_filial' => $lancamento['id_matriz_filial'] ?? null,
                ], $chave);
            } elseif ($canal === 'whatsapp') {
                $publicUrl = rtrim(env('APP_URL', ''), '/') . '/storage/temp/' . $filename;
                queue_message('whatsapp', [
                    'to' => $destinatario,
                    'media_url' => $publicUrl,
                    'caption' => 'Fatura ' . $codigo . ' - ' . $nomeEmpresa
                        . "\nValor: R$ " . $valorBR
                        . "\nVencimento: " . $venciBR
                        . $parcelaTexto,
                    'id_matriz_filial' => $lancamento['id_matriz_filial'] ?? null,
                ]);
            } else { // sms
                queue_message('sms', [
                    'to' => $destinatario,
                    'message' => $nomeEmpresa . ': Fatura ' . $codigo . ' - R$ ' . $valorBR . ', vence ' . $venciBR . $parcelaTexto,
                    'id_matriz_filial' => $lancamento['id_matriz_filial'] ?? null,
                ]);
            }

            Response::json(['success' => true, 'message' => 'Fatura enviada com sucesso']);

        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao enviar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Monta o HTML da fatura para o PDF (output buffering).
     */
    private function renderFaturaHtml(array $lancamento, string $chave): string
    {
        $empresa = (new MatrizFilial())->buscarDadosEmpresa($lancamento['id_matriz_filial'] ?? null) ?? [];
        $logoPath = PdfHelper::resolveImagePath($empresa['logo'] ?? null, $chave);

        $cliente = !empty($lancamento['id_cliente'])
            ? ((new Cliente())->buscarPorIdComContatos((int) $lancamento['id_cliente']) ?? [])
            : [];
        $fornecedor = !empty($lancamento['id_fornecedor'])
            ? ((new Fornecedor())->buscarPorId((int) $lancamento['id_fornecedor']) ?? [])
            : [];
        $tipoReceita = ($lancamento['tipo'] ?? '') === 'R';
        $contraparte = $tipoReceita ? $cliente : $fornecedor;

        // Link de pagamento (apenas para receitas em aberto com gateway vinculado)
        $linkPagamento = null;
        if (
            ($lancamento['tipo'] ?? '') === 'R'
            && ($lancamento['pago'] ?? 'N') !== 'S'
            && $this->formaPagamentoPermitePagamentoOnline($lancamento, $chave)
        ) {
            $link = (new PagamentoLinkSyncService())->obterOuCriarLinkAtualizado((int) $lancamento['id'], $chave);
            $linkPagamento = $link['url'] ?? null;
        }

        $qrPath = $this->gerarQrCodePath((int) $lancamento['id']);
        $descricaoLancamentoPdf = trim((string) ($lancamento['descricao'] ?? '')) ?: '-';
        $veiculosLancamentoPdf = array_column($this->veiculosDescricaoFatura($lancamento), 'texto');

        extract(compact('lancamento', 'empresa', 'cliente', 'fornecedor', 'contraparte', 'tipoReceita', 'logoPath', 'linkPagamento', 'qrPath', 'descricaoLancamentoPdf', 'veiculosLancamentoPdf'));
        ob_start();
        include __DIR__ . '/../Views/pages/financeiro/imprimir/fatura.php';
        return ob_get_clean();
    }

    private function veiculosDescricaoFatura(array $lancamento): array
    {
        $veiculos = [];
        $this->adicionarVeiculoDescricaoFatura($veiculos, $lancamento);

        foreach (($lancamento['itens'] ?? []) as $item) {
            if (is_array($item)) {
                $this->adicionarVeiculoDescricaoFatura($veiculos, $item);
            }
        }

        return array_values($veiculos);
    }

    private function adicionarVeiculoDescricaoFatura(array &$veiculos, array $dados): void
    {
        $placa = strtoupper(trim((string) ($dados['veiculo_placa'] ?? '')));
        $marca = trim((string) ($dados['veiculo_marca'] ?? ''));
        $modelo = trim((string) ($dados['veiculo_modelo'] ?? ''));

        if ($placa === '' && $marca === '' && $modelo === '') {
            return;
        }

        $chave = $placa !== ''
            ? $this->normalizarTextoVeiculoDescricao($placa)
            : $this->normalizarTextoVeiculoDescricao(trim($marca . ' ' . $modelo));
        if ($chave === '' || isset($veiculos[$chave])) {
            return;
        }

        $descricaoVeiculo = trim($marca . ' ' . $modelo);
        $veiculos[$chave] = [
            'placa' => $placa,
            'texto' => trim(($placa !== '' ? $placa : '-') . ($descricaoVeiculo !== '' ? ' - ' . $descricaoVeiculo : '')),
        ];
    }

    private function normalizarTextoVeiculoDescricao(string $valor): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($valor)) ?? '';
    }

    private function formaPagamentoPermitePagamentoOnline(array $lancamento, string $chave): bool
    {
        $formaPagamentoId = (int) ($lancamento['id_forma_pagamento'] ?? 0);
        if ($formaPagamentoId <= 0) {
            return false;
        }

        $gatewaysVinculados = (new FormaPagamento())->buscarGateways($formaPagamentoId);
        $idsVinculados = array_column($gatewaysVinculados, 'id');
        if (empty($idsVinculados)) {
            return false;
        }

        return !empty((new GatewayPagamento())->listarParaPagamentoPublicoPorIds($chave, $idsVinculados));
    }

    /**
     * Verifica fatura publicamente via token opaco (URL do QR code).
     *
     * GET /verificar/fatura/{token}
     *
     * Token = base64url(encrypt(id)). Nao expoe id sequencial nem permite iterar.
     */
    public function verificarPublico(Request $request, string $token): void
    {
        $id = $this->decodificarTokenVerificacao($token);

        if ($id === null) {
            $html = Template::render('public.verificar.erro', [
                'titulo' => 'Fatura nao encontrada',
                'mensagem' => 'O codigo informado nao foi encontrado ou o link esta incorreto.'
            ]);
            Response::html($html, 404);
            return;
        }

        // Rota publica — sem sessao de tenant. Ler sem filtro de chave.
        $lancamento = (new Financeiro())->buscarPorId($id);

        if (!$lancamento) {
            $html = Template::render('public.verificar.erro', [
                'titulo' => 'Fatura nao encontrada',
                'mensagem' => 'O codigo informado nao foi encontrado ou o link esta incorreto.'
            ]);
            Response::html($html, 404);
            return;
        }

        $empresa = (new MatrizFilial())->buscarDadosEmpresa($lancamento['id_matriz_filial'] ?? null) ?? [];

        $html = Template::render('public.verificar.fatura', [
            'lancamento' => $lancamento,
            'empresa' => $empresa,
        ]);
        Response::html($html);
    }

    /**
     * Codifica id em token opaco URL-safe usando encrypt() global.
     */
    private function gerarTokenVerificacao(int $id): string
    {
        return strtr(rtrim(encrypt((string) $id), '='), '+/', '-_');
    }

    /**
     * Decodifica token opaco para id. Retorna null se invalido.
     */
    private function decodificarTokenVerificacao(string $token): ?int
    {
        $b64 = strtr($token, '-_', '+/');
        $pad = (4 - strlen($b64) % 4) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', $pad);
        }
        $decoded = decrypt($b64);
        return ($decoded !== null && ctype_digit($decoded)) ? (int) $decoded : null;
    }

    /**
     * Gera QR code apontando para a URL publica de verificacao da fatura.
     * Salva PNG em arquivo temp e registra em $tmpFiles para cleanup.
     */
    private function gerarQrCodePath(int $id): string
    {
        try {
            $token = $this->gerarTokenVerificacao($id);
            $baseUrl = rtrim(env('APP_URL', ''), '/');
            $url = $baseUrl . '/verificar/fatura/' . $token;

            $qrImage = (new QrCodeGenerator())->format('png')->size(120)->generate($url);

            $tmp = sys_get_temp_dir() . '/qr_fatura_' . $id . '_' . uniqid() . '.png';
            file_put_contents($tmp, $qrImage);
            $this->tmpFiles[] = $tmp;

            return $tmp;
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Remove arquivos temporarios criados durante a geracao do PDF.
     */
    private function limparArquivosTemporarios(): void
    {
        foreach ($this->tmpFiles as $f) {
            if (file_exists($f)) {
                @unlink($f);
            }
        }
        $this->tmpFiles = [];
    }

    /**
     * Processa dados de lancamento para extrair descricao traduzida do plano de contas
     *
     * @param array $lancamento Dados do lancamento
     * @return array Dados processados com plano_conta_descricao
     */
    private function processarDescricaoPlanoContas(array $lancamento): array
    {
        // Processar descricao do plano de contas principal
        if (!empty($lancamento['plano_conta_descricao_i18n'])) {
            $lancamento['plano_conta_descricao'] = PlanoDeContas::getDescricao([
                'descricao_i18n' => $lancamento['plano_conta_descricao_i18n']
            ]);
        }

        // Processar itens se existirem
        if (!empty($lancamento['itens']) && is_array($lancamento['itens'])) {
            foreach ($lancamento['itens'] as &$item) {
                if (!empty($item['plano_conta_descricao_i18n'])) {
                    $item['plano_conta_descricao'] = PlanoDeContas::getDescricao([
                        'descricao_i18n' => $item['plano_conta_descricao_i18n']
                    ]);
                }
            }
            unset($item);
        }

        return $lancamento;
    }
}
