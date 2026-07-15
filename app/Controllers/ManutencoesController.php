<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Cliente;
use App\Models\Manutencao;
use App\Models\ManutencaoItem;
use App\Models\MatrizFilial;
use App\Models\Financeiro;
use App\Helpers\FileHelper;
use App\Helpers\FilialHelper;
use App\Helpers\PdfHelper;
use App\Services\AuditLogService;
use App\Services\CrossTenantDetectionService;

/**
 * Controller de Manutencoes
 *
 * Gerencia operacoes CRUD de ordens de manutencao.
 *
 * Permissoes:
 * - manutencoes.visualizar
 * - manutencoes.criar
 * - manutencoes.editar
 * - manutencoes.excluir
 */
class ManutencoesController
{
    /**
     * Renderiza a pagina de listagem
     *
     * GET /pages/manutencoes
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.manutencoes.index');
        Response::html($html);
    }

    /**
     * Renderiza a pagina de adicionar/editar
     *
     * GET /pages/manutencoes/adicionar
     */
    public function viewAdicionar(Request $request): void
    {
        $html = Template::render('pages.manutencoes.adicionar');
        Response::html($html);
    }

    /**
     * Lista manutencoes com paginacao e busca
     *
     * GET /api/manutencoes
     */
    public function index(Request $request): void
    {
        try {
            if (!Auth::can('manutencoes.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para visualizar manutencoes'
                ], 403);
                return;
            }

            $chave = Auth::chave();

            // Parametros de paginacao
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');

            // Filtro de filiais permitidas
            [$filialWhere, $filialParams] = FilialHelper::whereFiliais('id_matriz_filial');

            $model = new Manutencao();

            // Buscar dados
            $manutencoes = $model->listarPaginado(
                $chave,
                $page,
                $perPage,
                $search,
                $filialWhere,
                $filialParams
            );

            $total = $model->contar(
                $chave,
                $search,
                $filialWhere,
                $filialParams
            );

            $totalPages = (int) ceil($total / $perPage);

            // Formatar valores
            foreach ($manutencoes as &$m) {
                $m['total_servicos_formatted'] = currency_format((float) $m['total_servicos']);
                $m['total_pago_formatted'] = currency_format((float) $m['total_pago']);
                $m['total_pendente_formatted'] = currency_format((float) $m['total_pendente']);
                $m['data_enviado_formatted'] = $m['data_enviado'] ? format_operational_datetime($m['data_enviado']) : '';
                $m['data_retorno_formatted'] = $m['data_retorno'] ? format_operational_datetime($m['data_retorno']) : '';
                $m['tem_financeiro_vinculado'] = !empty($m['id_financeiro_principal']) || (int) ($m['qtd_financeiros_itens'] ?? 0) > 0;
                $m['tem_estoque_utilizado'] = (int) ($m['qtd_itens_estoque'] ?? 0) > 0;

                // Status formatado
                $statusMap = ['C' => 'Criada', 'A' => 'Aberta', 'F' => 'Fechada'];
                $m['status_label'] = $statusMap[$m['status']] ?? $m['status'];
            }

            Response::json([
                'success' => true,
                'data' => $manutencoes,
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
                'message' => 'Erro ao listar manutencoes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca uma manutencao por ID
     *
     * GET /api/manutencoes/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            if (!Auth::can('manutencoes.visualizar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $model = new Manutencao();
            $manutencao = $model->buscarPorIdComItens($id);

            if (!$manutencao) {
                Response::json(['success' => false, 'message' => 'Manutencao nao encontrada'], 404);
                return;
            }

            // Verificar tenant
            if ($manutencao['chave'] !== Auth::chave()) {
                // Detectar e logar tentativa cross-tenant
                CrossTenantDetectionService::check('manutencoes', $id);

                Response::json(['success' => false, 'message' => 'Manutencao nao encontrada'], 404);
                return;
            }

            // Verificar acesso a filial
            if (!empty($manutencao['id_matriz_filial']) && !FilialHelper::temAcessoFilial((int) $manutencao['id_matriz_filial'])) {
                Response::json(['success' => false, 'message' => 'Sem acesso a esta filial'], 403);
                return;
            }

            // Formatar valores
            $manutencao['total_servicos_formatted'] = currency_format((float) $manutencao['total_servicos']);
            $manutencao['total_pago_formatted'] = currency_format((float) $manutencao['total_pago']);
            $manutencao['total_pendente_formatted'] = currency_format((float) $manutencao['total_pendente']);

            // Formatar itens
            if (!empty($manutencao['itens'])) {
                foreach ($manutencao['itens'] as &$item) {
                    $item['valor_unitario_formatted'] = currency_format((float) $item['valor_unitario']);
                    $item['desconto_formatted'] = currency_format((float) ($item['desconto'] ?? 0));
                    $item['valor_total_formatted'] = currency_format((float) $item['valor_total']);
                }
            }

            Response::json(['success' => true, 'data' => $manutencao]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Cria uma nova manutencao
     *
     * POST /manutencoes/salvar
     */
    public function store(Request $request): void
    {
        try {
            if (!Auth::can('manutencoes.criar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $dados = $request->all();
            $dados['chave'] = Auth::chave();

            // Validar campos obrigatorios
            if (empty($dados['id_veiculo'])) {
                Response::json(['success' => false, 'message' => 'Veiculo e obrigatorio'], 400);
                return;
            }

            // Validar acesso a filial
            if (!empty($dados['id_matriz_filial']) && !FilialHelper::temAcessoFilial((int) $dados['id_matriz_filial'])) {
                Response::json(['success' => false, 'message' => 'Sem acesso a esta filial'], 403);
                return;
            }

            if (!$this->validarClienteOpcional($dados['id_cliente'] ?? null)) {
                return;
            }

            $idClientePagador = $this->normalizarClientePagador($dados['id_cliente'] ?? null);
            if ($idClientePagador !== null) {
                if (!$this->boolInput($dados['_cliente_pagador_confirmado'] ?? false, false)) {
                    Response::json([
                        'success' => false,
                        'message' => t('modules.manutencao.messages.payer_confirmation_required')
                    ], 400);
                    return;
                }

                $dados['_audit_data'] = $this->adicionarConfirmacaoClientePagadorAudit(
                    $dados['_audit_data'] ?? null,
                    false
                );
            }

            $model = new Manutencao();
            $statusSolicitado = $dados['status'] ?? 'C';

            if ($statusSolicitado === 'A') {
                $dados['status'] = 'C';
            }

            // Criar manutencao
            $id = $model->criar($dados);

            if ($statusSolicitado === 'A') {
                $dadosVeiculo = null;
                if (!empty($dados['_veiculo_odometro']) || !empty($dados['_veiculo_tanque'])) {
                    $dadosVeiculo = [
                        'odometro' => $dados['_veiculo_odometro'] ?? null,
                        'tanque' => $dados['_veiculo_tanque'] ?? null
                    ];
                }

                $resultado = $model->mudarStatus($id, 'A', $dadosVeiculo);
                if (!$resultado['success']) {
                    $model->deletar($id);
                    Response::json(['success' => false, 'message' => $resultado['message']], 400);
                    return;
                }
            }

            // Salvar itens
            if (!empty($dados['itens']) && is_array($dados['itens'])) {
                $itemModel = new ManutencaoItem();
                $itemModel->salvarTodos($id, $dados['chave'], $dados['itens']);
            }

            // Auditoria
            $manutencao = $model->buscarPorId($id);
            AuditLogService::registrarComAuditFrontend(
                $_SESSION['user_name'] . " adicionou manutencao [{$manutencao['os']}]",
                $dados['_audit_data'] ?? null,
                null
            );

            Response::json([
                'success' => true,
                'message' => 'Manutencao criada com sucesso',
                'data' => ['id' => $id, 'os' => $manutencao['os']]
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Atualiza uma manutencao
     *
     * POST /manutencoes/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            if (!Auth::can('manutencoes.editar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $model = new Manutencao();
            $manutencao = $model->buscarPorId($id);

            if (!$manutencao) {
                Response::json(['success' => false, 'message' => 'Manutencao nao encontrada'], 404);
                return;
            }

            if ($manutencao['chave'] !== Auth::chave()) {
                // Detectar e logar tentativa cross-tenant
                CrossTenantDetectionService::check('manutencoes', $id);

                Response::json(['success' => false, 'message' => 'Manutencao nao encontrada'], 404);
                return;
            }

            $dados = $request->all();

            $idClientePagadorAnterior = $this->normalizarClientePagador($manutencao['id_cliente'] ?? null);
            $idClientePagadorNovo = array_key_exists('id_cliente', $dados)
                ? $this->normalizarClientePagador($dados['id_cliente'])
                : $idClientePagadorAnterior;
            $clientePagadorAlterado = $idClientePagadorAnterior !== $idClientePagadorNovo;

            if ($clientePagadorAlterado) {
                if (!$this->boolInput($dados['_cliente_pagador_confirmado'] ?? false, false)) {
                    Response::json([
                        'success' => false,
                        'message' => t('modules.manutencao.messages.payer_confirmation_required')
                    ], 400);
                    return;
                }

                $temFinanceiroVinculado = !empty($model->listarFinanceirosVinculados($id));
                $dados['_audit_changes'] = $this->adicionarConfirmacaoClientePagadorAudit(
                    $dados['_audit_changes'] ?? null,
                    $temFinanceiroVinculado
                );
            }

            // Validar acesso a filial
            if (!empty($dados['id_matriz_filial']) && !FilialHelper::temAcessoFilial((int) $dados['id_matriz_filial'])) {
                Response::json(['success' => false, 'message' => 'Sem acesso a esta filial'], 403);
                return;
            }

            if (!$this->validarClienteOpcional($dados['id_cliente'] ?? null)) {
                return;
            }

            // Verificar se houve mudanca de status
            $statusAnterior = $manutencao['status'];
            $novoStatus = $dados['status'] ?? $statusAnterior;

            // Validar campos obrigatorios de retorno quando A -> F
            if ($statusAnterior === 'A' && $novoStatus === 'F') {
                $camposRetornoObrigatorios = ['data_retorno', 'odo_retorno', 'tanque_retorno'];
                $camposFaltando = [];

                foreach ($camposRetornoObrigatorios as $campo) {
                    if (empty($dados[$campo])) {
                        $camposFaltando[] = $campo;
                    }
                }

                if (!empty($camposFaltando)) {
                    Response::json([
                        'success' => false,
                        'message' => 'Para fechar a manutencao, preencha os campos de retorno da oficina: Data Retorno, Odometro e Tanque'
                    ], 400);
                    return;
                }
            }

            // Se mudou o status, usar metodo especifico com regras de negocio
            if ($novoStatus !== $statusAnterior) {
                // Preparar dados do veiculo para auto-preenchimento
                $dadosVeiculo = null;
                if (!empty($dados['_veiculo_odometro']) || !empty($dados['_veiculo_tanque'])) {
                    $dadosVeiculo = [
                        'odometro' => $dados['_veiculo_odometro'] ?? null,
                        'tanque' => $dados['_veiculo_tanque'] ?? null
                    ];
                }

                $resultado = $model->mudarStatus($id, $novoStatus, $dadosVeiculo);

                if (!$resultado['success']) {
                    Response::json(['success' => false, 'message' => $resultado['message']], 400);
                    return;
                }

                // Remover status dos dados para nao atualizar novamente
                unset($dados['status']);
            }

            // Atualizar demais dados da manutencao
            $model->atualizar($id, $dados);

            // Atualizar itens
            if (isset($dados['itens']) && is_array($dados['itens'])) {
                $itemModel = new ManutencaoItem();
                $itemModel->salvarTodos($id, $manutencao['chave'], $dados['itens']);
            }

            // Auditoria
            $acaoLog = 'editou';
            if ($novoStatus !== $statusAnterior) {
                $statusLabels = ['C' => 'criada', 'A' => 'aberta', 'F' => 'fechada'];
                $acaoLog = "alterou status para {$statusLabels[$novoStatus]} da";
            }
            AuditLogService::registrarComAuditFrontend(
                $_SESSION['user_name'] . " {$acaoLog} manutencao [{$manutencao['os']}]",
                null,
                $dados['_audit_changes'] ?? null
            );

            Response::json(['success' => true, 'message' => 'Manutencao atualizada com sucesso']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Exclui uma manutencao
     *
     * POST /manutencoes/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            if (!Auth::can('manutencoes.excluir')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $model = new Manutencao();
            $manutencao = $model->buscarPorId($id);

            if (!$manutencao) {
                Response::json(['success' => false, 'message' => 'Manutencao nao encontrada'], 404);
                return;
            }

            if ($manutencao['chave'] !== Auth::chave()) {
                // Detectar e logar tentativa cross-tenant
                CrossTenantDetectionService::check('manutencoes', $id);

                Response::json(['success' => false, 'message' => 'Manutencao nao encontrada'], 404);
                return;
            }

            $financeirosVinculados = $model->listarFinanceirosVinculados($id);
            $temFinanceiroVinculado = !empty($financeirosVinculados);
            $temEstoqueUtilizado = $model->temEstoqueUtilizado($id);
            $excluirFinanceiro = $this->boolInput(
                $request->input('excluir_financeiro', $temFinanceiroVinculado),
                $temFinanceiroVinculado
            );
            $reporEstoque = $this->boolInput(
                $request->input('repor_estoque', $temEstoqueUtilizado),
                $temEstoqueUtilizado
            );

            $model->excluirComImpactos(
                $id,
                $financeirosVinculados,
                $temFinanceiroVinculado && $excluirFinanceiro,
                $temEstoqueUtilizado && $reporEstoque
            );

            // Log de mudanca de disponibilidade
            if ($manutencao['status'] === 'A' && !empty($manutencao['id_veiculo'])) {
                AuditLogService::registrar(
                    "Manutencao [{$manutencao['os']}] excluida mudou disponibilidade do veiculo [{$manutencao['veiculo_placa']}] de O para D"
                );
            }

            // Auditoria
            AuditLogService::registrarComCampos(
                $_SESSION['user_name'] . " excluiu manutencao [{$manutencao['os']}]",
                [
                    AuditLogService::campo(
                        'Excluir financeiro vinculado',
                        null,
                        $temFinanceiroVinculado ? ($excluirFinanceiro ? 'Sim' : 'Nao') : 'Nao aplicavel',
                        'Exclusao'
                    ),
                    AuditLogService::campo(
                        'Repor estoque utilizado',
                        null,
                        $temEstoqueUtilizado ? ($reporEstoque ? 'Sim' : 'Nao') : 'Nao aplicavel',
                        'Exclusao'
                    ),
                ]
            );

            Response::json(['success' => true, 'message' => 'Manutencao excluida com sucesso']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function boolInput(mixed $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 's', 'sim', 'yes', 'on'], true);
    }

    private function validarClienteOpcional(mixed $idCliente): bool
    {
        if (empty($idCliente)) {
            return true;
        }

        $cliente = (new Cliente())->buscarPorId((int) $idCliente);
        if (!$cliente) {
            Response::json(['success' => false, 'message' => 'Cliente nao encontrado'], 404);
            return false;
        }

        if ($cliente['chave'] !== Auth::chave()) {
            CrossTenantDetectionService::check('clientes', (int) $idCliente);
            Response::json(['success' => false, 'message' => 'Cliente nao encontrado'], 404);
            return false;
        }

        if (!empty($cliente['id_matriz_filial']) && !FilialHelper::temAcessoFilial((int) $cliente['id_matriz_filial'])) {
            Response::json(['success' => false, 'message' => 'Sem acesso a filial do cliente'], 403);
            return false;
        }

        return true;
    }

    private function normalizarClientePagador(mixed $idCliente): ?int
    {
        if ($idCliente === null || $idCliente === '' || (int) $idCliente <= 0) {
            return null;
        }

        return (int) $idCliente;
    }

    private function adicionarConfirmacaoClientePagadorAudit(?string $auditJson, bool $temFinanceiroVinculado): string
    {
        $audit = [];
        if ($auditJson !== null && $auditJson !== '') {
            $decoded = json_decode($auditJson, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $audit = $decoded;
            }
        }

        $aba = t('modules.manutencao.tabs.data');
        if (!isset($audit[$aba]) || !is_array($audit[$aba])) {
            $audit[$aba] = [];
        }

        $audit[$aba][] = [
            'label' => t('modules.manutencao.audit_payer.confirmation_label'),
            'de' => null,
            'para' => t($temFinanceiroVinculado
                ? 'modules.manutencao.audit_payer.confirmed_existing_financial'
                : 'modules.manutencao.audit_payer.confirmed'),
        ];

        return json_encode($audit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // ===== ACOES DE NEGOCIO =====

    /**
     * Abre uma manutencao (status = 'A')
     *
     * POST /manutencoes/{id}/abrir
     */
    public function abrir(Request $request, int $id): void
    {
        try {
            if (!Auth::can('manutencoes.editar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $model = new Manutencao();
            $model->abrir($id);

            $manutencao = $model->buscarPorId($id);
            AuditLogService::registrar($_SESSION['user_name'] . " abriu manutencao [{$manutencao['os']}]");

            Response::json(['success' => true, 'message' => 'Manutencao aberta com sucesso']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Fecha uma manutencao (status = 'F')
     *
     * POST /manutencoes/{id}/fechar
     */
    public function fechar(Request $request, int $id): void
    {
        try {
            if (!Auth::can('manutencoes.editar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $model = new Manutencao();

            // Validar campos obrigatorios de retorno
            $manutencao = $model->buscarPorId($id);
            if (!$manutencao) {
                Response::json(['success' => false, 'message' => 'Manutencao nao encontrada'], 404);
                return;
            }

            if (empty($manutencao['data_retorno']) || empty($manutencao['odo_retorno']) || empty($manutencao['tanque_retorno'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Para fechar a manutencao, preencha os campos de retorno da oficina: Data Retorno, Odometro e Tanque'
                ], 400);
                return;
            }

            $model->fechar($id);

            $manutencao = $model->buscarPorId($id);
            AuditLogService::registrar($_SESSION['user_name'] . " fechou manutencao [{$manutencao['os']}]");

            Response::json(['success' => true, 'message' => 'Manutencao fechada com sucesso']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // ===== ACOES FINANCEIRAS =====

    /**
     * Cria lancamento financeiro completo
     *
     * POST /manutencoes/{id}/financeiro/criar
     */
    public function criarFinanceiro(Request $request, int $id): void
    {
        try {
            if (!Auth::can('manutencoes.editar') || !Auth::can('financeiro.criar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $dados = $request->all();
            $model = new Manutencao();

            $idFinanceiro = $model->criarLancamentoFinanceiro($id, $dados);

            $manutencao = $model->buscarPorId($id);
            $auditChanges = $dados['_audit_changes'] ?? null;
            if (is_array($auditChanges)) {
                $auditChanges = json_encode($auditChanges, JSON_UNESCAPED_UNICODE);
            }
            AuditLogService::registrarComAuditFrontend(
                $_SESSION['user_name'] . " criou lancamento financeiro para manutencao [{$manutencao['os']}]",
                null,
                $auditChanges
            );

            Response::json([
                'success' => true,
                'message' => 'Lancamento financeiro criado com sucesso',
                'data' => ['id_financeiro' => $idFinanceiro]
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Cria lancamento financeiro parcial (itens selecionados)
     *
     * POST /manutencoes/{id}/financeiro/parcial
     */
    public function criarFinanceiroParcial(Request $request, int $id): void
    {
        try {
            if (!Auth::can('manutencoes.editar') || !Auth::can('financeiro.criar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $dados = $request->all();
            $idsItens = $dados['itens'] ?? [];

            if (empty($idsItens)) {
                Response::json(['success' => false, 'message' => 'Selecione pelo menos um item'], 400);
                return;
            }

            $model = new Manutencao();

            $idFinanceiro = $model->criarLancamentoParcial($id, $idsItens, $dados);

            $manutencao = $model->buscarPorId($id);
            $auditChanges = $dados['_audit_changes'] ?? null;
            if (is_array($auditChanges)) {
                $auditChanges = json_encode($auditChanges, JSON_UNESCAPED_UNICODE);
            }
            AuditLogService::registrarComAuditFrontend(
                $_SESSION['user_name'] . " criou lancamento financeiro parcial para manutencao [{$manutencao['os']}]",
                null,
                $auditChanges
            );

            Response::json([
                'success' => true,
                'message' => 'Lancamento financeiro parcial criado com sucesso',
                'data' => ['id_financeiro' => $idFinanceiro]
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // ===== ITENS =====

    /**
     * Lista itens de uma manutencao
     *
     * GET /api/manutencoes/{id}/itens
     */
    public function itens(Request $request, int $id): void
    {
        try {
            if (!Auth::can('manutencoes.visualizar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $itemModel = new ManutencaoItem();
            $itens = $itemModel->listarPorManutencao($id);

            // Formatar valores
            foreach ($itens as &$item) {
                $item['valor_unitario_formatted'] = currency_format((float) $item['valor_unitario']);
                $item['desconto_formatted'] = currency_format((float) ($item['desconto'] ?? 0));
                $item['valor_total_formatted'] = currency_format((float) $item['valor_total']);
            }

            Response::json(['success' => true, 'data' => $itens]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Lista itens pendentes de pagamento
     *
     * GET /api/manutencoes/{id}/itens/pendentes
     */
    public function itensPendentes(Request $request, int $id): void
    {
        try {
            if (!Auth::can('manutencoes.visualizar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $itemModel = new ManutencaoItem();
            $itens = $itemModel->listarPendentes($id);

            // Formatar valores
            foreach ($itens as &$item) {
                $item['valor_unitario_formatted'] = currency_format((float) $item['valor_unitario']);
                $item['desconto_formatted'] = currency_format((float) ($item['desconto'] ?? 0));
                $item['valor_total_formatted'] = currency_format((float) $item['valor_total']);
            }

            Response::json(['success' => true, 'data' => $itens]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Salva um item individual da manutencao.
     *
     * POST /manutencoes/{id}/itens/salvar
     */
    public function salvarItem(Request $request, int $id): void
    {
        try {
            if (!Auth::can('manutencoes.editar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $manutencao = $this->buscarManutencaoAutorizada($id);
            if (!$manutencao) {
                return;
            }

            $dados = $request->all();
            $itemModel = new ManutencaoItem();

            if (!empty($dados['id'])) {
                $itemAtual = $itemModel->buscarPorId((int) $dados['id']);
                if (!$itemAtual || (int) $itemAtual['id_manutencao'] !== $id || $itemAtual['chave'] !== Auth::chave()) {
                    Response::json(['success' => false, 'message' => 'Item nao encontrado'], 404);
                    return;
                }
                if ($itemAtual['pago'] === 'S') {
                    Response::json(['success' => false, 'message' => 'Nao e possivel alterar itens ja pagos'], 400);
                    return;
                }

                $itemModel->atualizar((int) $dados['id'], $dados);
                $itemId = (int) $dados['id'];
            } else {
                $dados['id_manutencao'] = $id;
                $dados['chave'] = Auth::chave();
                $itemId = $itemModel->criar($dados);
            }

            $item = $itemModel->buscarPorId($itemId);
            if (!$item) {
                Response::json(['success' => false, 'message' => 'Item nao encontrado apos salvar'], 500);
                return;
            }
            $this->formatarItemManutencao($item);

            Response::json([
                'success' => true,
                'message' => 'Item salvo com sucesso',
                'data' => $item,
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Exclui um item individual da manutencao.
     *
     * POST /manutencoes/{id}/itens/{itemId}/excluir
     */
    public function excluirItem(Request $request, int $id, int $itemId): void
    {
        try {
            if (!Auth::can('manutencoes.editar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $manutencao = $this->buscarManutencaoAutorizada($id);
            if (!$manutencao) {
                return;
            }

            $itemModel = new ManutencaoItem();
            $item = $itemModel->buscarPorId($itemId);
            if (!$item || (int) $item['id_manutencao'] !== $id || $item['chave'] !== Auth::chave()) {
                Response::json(['success' => false, 'message' => 'Item nao encontrado'], 404);
                return;
            }
            if ($item['pago'] === 'S') {
                Response::json(['success' => false, 'message' => 'Nao e possivel excluir itens ja pagos'], 400);
                return;
            }

            $itemModel->deletar($itemId);
            Response::json(['success' => true, 'message' => 'Item excluido com sucesso']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    private function buscarManutencaoAutorizada(int $id): ?array
    {
        $model = new Manutencao();
        $manutencao = $model->buscarPorId($id);

        if (!$manutencao || $manutencao['chave'] !== Auth::chave()) {
            if ($manutencao && $manutencao['chave'] !== Auth::chave()) {
                CrossTenantDetectionService::check('manutencoes', $id);
            }
            Response::json(['success' => false, 'message' => 'Manutencao nao encontrada'], 404);
            return null;
        }

        if (!empty($manutencao['id_matriz_filial']) && !FilialHelper::temAcessoFilial((int) $manutencao['id_matriz_filial'])) {
            Response::json(['success' => false, 'message' => 'Sem acesso a esta filial'], 403);
            return null;
        }

        return $manutencao;
    }

    private function formatarItemManutencao(array &$item): void
    {
        $item['valor_unitario_formatted'] = currency_format((float) $item['valor_unitario']);
        $item['desconto_formatted'] = currency_format((float) ($item['desconto'] ?? 0));
        $item['valor_total_formatted'] = currency_format((float) $item['valor_total']);
    }

    // ===== IMPRESSAO =====

    /**
     * Gera PDF da Ordem de Servico
     *
     * GET /manutencoes/{id}/imprimir
     */
    public function imprimir(Request $request, int $id): void
    {
        try {
            if (!Auth::can('manutencoes.visualizar')) {
                Response::html('<h1>Acesso negado</h1>', 403);
                return;
            }

            $model = new Manutencao();
            $manutencao = $model->buscarPorIdComItens($id);

            if (!$manutencao) {
                Response::html('<h1>Manutencao nao encontrada</h1>', 404);
                return;
            }

            $chave = Auth::chave();
            if ($manutencao['chave'] !== $chave) {
                Response::html('<h1>Acesso negado</h1>', 403);
                return;
            }

            if (!FilialHelper::temAcessoFilial($manutencao['id_matriz_filial'] ?? null)) {
                Response::html('<h1>Acesso negado</h1>', 403);
                return;
            }

            // Buscar dados da empresa
            $matrizFilialModel = new MatrizFilial();
            $empresa = $matrizFilialModel->buscarDadosEmpresa($manutencao['id_matriz_filial'] ?? null);

            // Logo da empresa (caminho absoluto para mPDF)
            $logoPath = PdfHelper::resolveImagePath($empresa['logo'] ?? null, $chave);

            // Capturar HTML via output buffering
            ob_start();
            $viewPath = __DIR__ . '/../Views/pages/manutencoes/imprimir/template.php';
            extract([
                'manutencao' => $manutencao,
                'empresa' => $empresa,
                'logoPath' => $logoPath,
            ]);
            include $viewPath;
            $html = ob_get_clean();

            // Gerar PDF
            $mpdf = PdfHelper::create([
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 5,
                'margin_bottom' => 10,
            ]);

            PdfHelper::writeHtml($mpdf, $html);
            $mpdf->Output('os-' . ($manutencao['os'] ?? $id) . '.pdf', 'I');
            exit;
        } catch (\Exception $e) {
            Response::html('<h1>Erro ao gerar PDF: ' . htmlspecialchars($e->getMessage()) . '</h1>', 500);
        }
    }

    // ===== SELECTS =====

}
