<?php

namespace App\Controllers\Relatorios;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\CaucaoDeposito;
use App\Models\Relatorios\FinanceiroReport;
use App\Models\MatrizFilial;
use App\Helpers\PdfHelper;

/**
 * Controller de Relatórios Financeiros
 *
 * Gerencia os 10 relatórios financeiros:
 * - Movimentações
 * - Faturamento
 * - DRE
 * - Livro de Caixa
 * - Contas Bancárias
 * - Plano de Contas
 * - Projeção Receitas
 * - Rentabilidade
 * - Inadimplência
 * - Taxas e Serviços
 */
class FinanceiroController extends BaseRelatorioController
{
    /**
     * GET /pages/relatorios/financeiro/caucoes
     */
    public function viewCaucoes(Request $request): void
    {
        $html = Template::render('pages.relatorios.financeiro.caucoes');
        Response::html($html);
    }

    /**
     * GET /api/relatorios/financeiro/caucoes
     */
    public function caucoes(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.financeiro.caucoes')) return;

            if (!$this->validateFilialAccess((string) $request->query('filial', ''))) return;

            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 25)));

            $result = (new CaucaoDeposito())->buscar([
                'data_inicio' => $request->query('data_inicio', ''),
                'data_fim' => $request->query('data_fim', ''),
                'filial' => $request->query('filial', ''),
                'status' => $request->query('status', ''),
                'origem' => $request->query('origem', ''),
                'cliente' => $request->query('cliente', ''),
                'page' => $page,
                'perPage' => $perPage,
            ]);

            $this->reportPaginatedResponse(
                $result['details'],
                $result['totals'],
                $page,
                $perPage,
                $result['total']
            );
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    // =====================================================
    // MOVIMENTAÇÕES
    // =====================================================

    /**
     * Renderiza a página do relatório Movimentações
     *
     * GET /pages/relatorios/financeiro/movimentacoes
     */
    public function viewMovimentacoes(Request $request): void
    {
        $html = Template::render('pages.relatorios.financeiro.movimentacoes');
        Response::html($html);
    }

    /**
     * API - Dados do relatório Movimentações
     *
     * GET /api/relatorios/financeiro/movimentacoes
     */
    public function movimentacoes(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.financeiro.movimentacoes')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $tipo = $request->query('tipo', '');
            $planoConta = $request->query('plano_conta', '');
            $conta = $request->query('conta', '');
            $status = $request->query('status', '');
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 20)));

            $model = new FinanceiroReport();
            $result = $model->movimentacoes(
                $filters['data_inicio'],
                $filters['data_fim'],
                $filialWhere,
                $filialParams,
                $filters['filial'],
                $tipo,
                $planoConta,
                $conta,
                $status,
                $page,
                $perPage
            );

            $this->reportPaginatedResponse(
                $result['details'],
                $result['totals'],
                $page,
                $perPage,
                $result['total'],
                $result['chart']
            );
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    // =====================================================
    // FATURAMENTO
    // =====================================================

    /**
     * Renderiza a página do relatório Faturamento
     *
     * GET /pages/relatorios/financeiro/faturamento
     */
    public function viewFaturamento(Request $request): void
    {
        $html = Template::render('pages.relatorios.financeiro.faturamento');
        Response::html($html);
    }

    /**
     * API - Dados do relatório Faturamento
     *
     * GET /api/relatorios/financeiro/faturamento
     */
    public function faturamento(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.financeiro.faturamento')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $formaPagamento = $request->query('forma_pagamento', '');

            $model = new FinanceiroReport();
            $result = $model->faturamento(
                $filters['data_inicio'],
                $filters['data_fim'],
                $filialWhere,
                $filialParams,
                $filters['filial'],
                $formaPagamento
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    // =====================================================
    // DRE
    // =====================================================

    /**
     * Renderiza a página do relatório DRE
     *
     * GET /pages/relatorios/financeiro/dre
     */
    public function viewDre(Request $request): void
    {
        $html = Template::render('pages.relatorios.financeiro.dre');
        Response::html($html);
    }

    /**
     * API - Dados do relatório DRE
     *
     * GET /api/relatorios/financeiro/dre
     */
    public function dre(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.financeiro.dre')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $model = new FinanceiroReport();
            $result = $model->dre(
                $filters['data_inicio'],
                $filters['data_fim'],
                $filialWhere,
                $filialParams,
                $filters['filial']
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    // =====================================================
    // LIVRO DE CAIXA
    // =====================================================

    /**
     * Renderiza a página do relatório Livro de Caixa
     *
     * GET /pages/relatorios/financeiro/livro-caixa
     */
    public function viewLivroCaixa(Request $request): void
    {
        $html = Template::render('pages.relatorios.financeiro.livro-caixa');
        Response::html($html);
    }

    /**
     * API - Dados do relatório Livro de Caixa
     *
     * GET /api/relatorios/financeiro/livro-caixa
     */
    public function livroCaixa(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.financeiro.livro_caixa')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $conta = $request->query('conta', '');
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 20)));

            $model = new FinanceiroReport();
            $result = $model->livroCaixa(
                $filters['data_inicio'],
                $filters['data_fim'],
                $filialWhere,
                $filialParams,
                $filters['filial'],
                $conta,
                $page,
                $perPage
            );

            $this->reportPaginatedResponse(
                $result['details'],
                $result['totals'],
                $page,
                $perPage,
                $result['total'],
                $result['chart']
            );
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    // =====================================================
    // CONTAS BANCÁRIAS
    // =====================================================

    /**
     * Renderiza a página do relatório Contas Bancárias
     *
     * GET /pages/relatorios/financeiro/contas-bancarias
     */
    public function viewContasBancarias(Request $request): void
    {
        $html = Template::render('pages.relatorios.financeiro.contas-bancarias');
        Response::html($html);
    }

    /**
     * API - Dados do relatório Contas Bancárias
     *
     * GET /api/relatorios/financeiro/contas-bancarias
     */
    public function contasBancarias(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.financeiro.contas_bancarias')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $model = new FinanceiroReport();
            $result = $model->contasBancarias(
                $filters['data_inicio'],
                $filters['data_fim'],
                $filialWhere,
                $filialParams,
                $filters['filial']
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    // =====================================================
    // PLANO DE CONTAS
    // =====================================================

    /**
     * Renderiza a página do relatório Plano de Contas
     *
     * GET /pages/relatorios/financeiro/plano-contas
     */
    public function viewPlanoContas(Request $request): void
    {
        $html = Template::render('pages.relatorios.financeiro.plano-contas');
        Response::html($html);
    }

    /**
     * API - Dados do relatório Plano de Contas
     *
     * GET /api/relatorios/financeiro/plano-contas
     */
    public function planoContas(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.financeiro.plano_contas')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $model = new FinanceiroReport();
            $result = $model->planoContas(
                $filters['data_inicio'],
                $filters['data_fim'],
                $filialWhere,
                $filialParams,
                $filters['filial']
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    // =====================================================
    // PROJEÇÃO RECEITAS
    // =====================================================

    /**
     * Renderiza a página do relatório Projeção Receitas
     *
     * GET /pages/relatorios/financeiro/projecao-receitas
     */
    public function viewProjecaoReceitas(Request $request): void
    {
        $html = Template::render('pages.relatorios.financeiro.projecao-receitas');
        Response::html($html);
    }

    /**
     * API - Dados do relatório Projeção Receitas
     *
     * GET /api/relatorios/financeiro/projecao-receitas
     */
    public function projecaoReceitas(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.financeiro.projecao_receitas')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $model = new FinanceiroReport();
            $result = $model->projecaoReceitas(
                $filters['data_inicio'],
                $filters['data_fim'],
                $filialWhere,
                $filialParams,
                $filters['filial']
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    // =====================================================
    // RENTABILIDADE
    // =====================================================

    /**
     * Renderiza a página do relatório Rentabilidade
     *
     * GET /pages/relatorios/financeiro/rentabilidade
     */
    public function viewRentabilidade(Request $request): void
    {
        $html = Template::render('pages.relatorios.financeiro.rentabilidade');
        Response::html($html);
    }

    /**
     * API - Dados do relatório Rentabilidade
     *
     * GET /api/relatorios/financeiro/rentabilidade
     */
    public function rentabilidade(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.financeiro.rentabilidade')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $dimensao = $request->query('dimensao', '');
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 20)));

            $model = new FinanceiroReport();
            $result = $model->rentabilidade(
                $filters['data_inicio'],
                $filters['data_fim'],
                $filialWhere,
                $filialParams,
                $filters['filial'],
                $dimensao,
                $page,
                $perPage
            );

            $this->reportPaginatedResponse(
                $result['details'],
                $result['totals'],
                $page,
                $perPage,
                $result['total'],
                $result['chart']
            );
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    // =====================================================
    // INADIMPLÊNCIA
    // =====================================================

    /**
     * Renderiza a página do relatório Inadimplência
     *
     * GET /pages/relatorios/financeiro/inadimplencia
     */
    public function viewInadimplencia(Request $request): void
    {
        $html = Template::render('pages.relatorios.financeiro.inadimplencia');
        Response::html($html);
    }

    /**
     * API - Dados do relatório Inadimplência
     *
     * GET /api/relatorios/financeiro/inadimplencia
     */
    public function inadimplencia(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.financeiro.inadimplencia')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $model = new FinanceiroReport();
            $result = $model->inadimplencia(
                $filters['data_inicio'],
                $filters['data_fim'],
                $filialWhere,
                $filialParams,
                $filters['filial']
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    // =====================================================
    // TAXAS E SERVIÇOS
    // =====================================================

    /**
     * Renderiza a página do relatório Taxas e Serviços
     *
     * GET /pages/relatorios/financeiro/taxas-servicos
     */
    public function viewTaxasServicos(Request $request): void
    {
        $html = Template::render('pages.relatorios.financeiro.taxas-servicos');
        Response::html($html);
    }

    /**
     * API - Dados do relatório Taxas e Serviços
     *
     * GET /api/relatorios/financeiro/taxas-servicos
     */
    public function taxasServicos(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.financeiro.taxas_servicos')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $model = new FinanceiroReport();
            $result = $model->taxasServicos(
                $filters['data_inicio'],
                $filters['data_fim'],
                $filialWhere,
                $filialParams,
                $filters['filial']
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    // =====================================================
    // PDF EXPORTS
    // =====================================================

    /**
     * Gera PDF de um relatório Financeiro
     *
     * @param string $templateFile Nome do arquivo template (sem path)
     * @param string $titulo Título do relatório
     * @param string $descricao Descrição do relatório
     * @param array $totals Totalizadores
     * @param array $details Dados detalhados
     * @param string $dataInicio Período início
     * @param string $dataFim Período fim
     * @param string $orientation 'P' portrait, 'L' landscape
     */
    private function renderPdf(
        string $templateFile,
        string $titulo,
        string $descricao,
        array $totals,
        array $details,
        string $dataInicio,
        string $dataFim,
        string $orientation = 'P'
    ): void {
        $user = Auth::user();
        $filialModel = new MatrizFilial();
        $empresa = $filialModel->buscarPorId((int) ($user['id_matriz_filial'] ?? 0));
        $empresa['logo'] = $this->resolveLogoPath($empresa);

        $empresaData = [
            'nome' => $empresa['nome'] ?? '',
            'logo' => $empresa['logo'],
        ];

        $usuario = $user['nome'] ?? '';

        ob_start();
        $viewPath = __DIR__ . '/../../Views/pages/relatorios/imprimir/financeiro/' . $templateFile;
        include $viewPath;
        $html = ob_get_clean();

        PdfHelper::outputInline($html, 'relatorio.pdf', [
            'orientation' => $orientation,
        ]);
    }

    /** GET /relatorios/financeiro/movimentacoes/pdf */
    public function movimentacoesPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.financeiro.movimentacoes')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new FinanceiroReport();
        $result = $model->movimentacoes($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial'], $request->query('tipo', ''), $request->query('plano_conta', ''), $request->query('conta', ''), $request->query('status', ''), 1, 500);

        $this->renderPdf('movimentacoes.php', t('modules.relatorios.financeiro.movimentacoes.title'), t('modules.relatorios.financeiro.movimentacoes.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    /** GET /relatorios/financeiro/faturamento/pdf */
    public function faturamentoPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.financeiro.faturamento')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new FinanceiroReport();
        $result = $model->faturamento($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial'], $request->query('forma_pagamento', ''));

        $this->renderPdf('faturamento.php', t('modules.relatorios.financeiro.faturamento.title'), t('modules.relatorios.financeiro.faturamento.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim']);
    }

    /** GET /relatorios/financeiro/dre/pdf */
    public function drePdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.financeiro.dre')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new FinanceiroReport();
        $result = $model->dre($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);

        $this->renderPdf('dre.php', t('modules.relatorios.financeiro.dre.title'), t('modules.relatorios.financeiro.dre.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim']);
    }

    /** GET /relatorios/financeiro/livro-caixa/pdf */
    public function livroCaixaPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.financeiro.livro_caixa')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new FinanceiroReport();
        $result = $model->livroCaixa($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial'], $request->query('conta', ''), 1, 0);

        $this->renderPdf('livro-caixa.php', t('modules.relatorios.financeiro.livro_caixa.title'), t('modules.relatorios.financeiro.livro_caixa.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim']);
    }

    /** GET /relatorios/financeiro/contas-bancarias/pdf */
    public function contasBancariasPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.financeiro.contas_bancarias')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new FinanceiroReport();
        $result = $model->contasBancarias($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);

        $this->renderPdf('contas-bancarias.php', t('modules.relatorios.financeiro.contas_bancarias.title'), t('modules.relatorios.financeiro.contas_bancarias.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim']);
    }

    /** GET /relatorios/financeiro/plano-contas/pdf */
    public function planoContasPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.financeiro.plano_contas')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new FinanceiroReport();
        $result = $model->planoContas($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);

        $this->renderPdf('plano-contas.php', t('modules.relatorios.financeiro.plano_contas.title'), t('modules.relatorios.financeiro.plano_contas.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim']);
    }

    /** GET /relatorios/financeiro/projecao-receitas/pdf */
    public function projecaoReceitasPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.financeiro.projecao_receitas')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new FinanceiroReport();
        $result = $model->projecaoReceitas($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);

        $this->renderPdf('projecao-receitas.php', t('modules.relatorios.financeiro.projecao_receitas.title'), t('modules.relatorios.financeiro.projecao_receitas.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim']);
    }

    /** GET /relatorios/financeiro/rentabilidade/pdf */
    public function rentabilidadePdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.financeiro.rentabilidade')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new FinanceiroReport();
        $result = $model->rentabilidade($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial'], $request->query('dimensao', ''), 1, 500);

        $this->renderPdf('rentabilidade.php', t('modules.relatorios.financeiro.rentabilidade.title'), t('modules.relatorios.financeiro.rentabilidade.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    /** GET /relatorios/financeiro/inadimplencia/pdf */
    public function inadimplenciaPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.financeiro.inadimplencia')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new FinanceiroReport();
        $result = $model->inadimplencia($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);

        $this->renderPdf('inadimplencia.php', t('modules.relatorios.financeiro.inadimplencia.title'), t('modules.relatorios.financeiro.inadimplencia.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim']);
    }

    /** GET /relatorios/financeiro/taxas-servicos/pdf */
    public function taxasServicosPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.financeiro.taxas_servicos')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new FinanceiroReport();
        $result = $model->taxasServicos($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);

        $this->renderPdf('taxas-servicos.php', t('modules.relatorios.financeiro.taxas_servicos.title'), t('modules.relatorios.financeiro.taxas_servicos.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim']);
    }
}
