<?php

namespace App\Controllers\Relatorios;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Relatorios\KpiReport;

/**
 * Controller de Relatórios KPIs / Indicadores de Desempenho
 *
 * Gerencia os 8 relatórios de KPIs:
 * - Taxa de Ocupação da Frota
 * - RevPAR
 * - ADR (Diária Média)
 * - Ticket Médio
 * - Tempo Médio de Locação
 * - % Receitas Adicionais
 * - Receita por Veículo
 * - ROI por Veículo
 */
class KpisController extends BaseRelatorioController
{
    // =====================================================
    // TAXA DE OCUPAÇÃO
    // =====================================================

    /**
     * Renderiza a página do relatório Taxa de Ocupação
     *
     * GET /pages/relatorios/kpis/taxa-ocupacao
     */
    public function viewTaxaOcupacao(Request $request): void
    {
        $html = Template::render('pages.relatorios.kpis.taxa-ocupacao');
        Response::html($html);
    }

    /**
     * API - Dados do relatório Taxa de Ocupação
     *
     * GET /api/relatorios/kpis/taxa-ocupacao
     */
    public function taxaOcupacao(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.kpis.taxa_ocupacao')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $grupoId = $request->query('grupo', '');
            $veiculoId = $request->query('veiculo', '');

            $model = new KpiReport();
            $result = $model->taxaOcupacao(
                $filters['data_inicio'],
                $filters['data_fim'],
                $filialWhere,
                $filialParams,
                $filters['filial'],
                $grupoId,
                $veiculoId
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    // =====================================================
    // REVPAR
    // =====================================================

    /**
     * GET /pages/relatorios/kpis/revpar
     */
    public function viewRevpar(Request $request): void
    {
        $html = Template::render('pages.relatorios.kpis.revpar');
        Response::html($html);
    }

    /**
     * GET /api/relatorios/kpis/revpar
     */
    public function revpar(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.kpis.revpar')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $grupoId = $request->query('grupo', '');

            $model = new KpiReport();
            $result = $model->revpar(
                $filters['data_inicio'],
                $filters['data_fim'],
                $filialWhere,
                $filialParams,
                $filters['filial'],
                $grupoId
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    // =====================================================
    // ADR (DIÁRIA MÉDIA)
    // =====================================================

    /**
     * GET /pages/relatorios/kpis/adr
     */
    public function viewAdr(Request $request): void
    {
        $html = Template::render('pages.relatorios.kpis.adr');
        Response::html($html);
    }

    /**
     * GET /api/relatorios/kpis/adr
     */
    public function adr(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.kpis.adr')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $grupoId = $request->query('grupo', '');

            $model = new KpiReport();
            $result = $model->adr(
                $filters['data_inicio'],
                $filters['data_fim'],
                $filialWhere,
                $filialParams,
                $filters['filial'],
                $grupoId
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    // =====================================================
    // TICKET MÉDIO
    // =====================================================

    /**
     * GET /pages/relatorios/kpis/ticket-medio
     */
    public function viewTicketMedio(Request $request): void
    {
        $html = Template::render('pages.relatorios.kpis.ticket-medio');
        Response::html($html);
    }

    /**
     * GET /api/relatorios/kpis/ticket-medio
     */
    public function ticketMedio(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.kpis.ticket_medio')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $model = new KpiReport();
            $result = $model->ticketMedio(
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
    // TEMPO MÉDIO DE LOCAÇÃO
    // =====================================================

    /**
     * GET /pages/relatorios/kpis/tempo-medio
     */
    public function viewTempoMedio(Request $request): void
    {
        $html = Template::render('pages.relatorios.kpis.tempo-medio');
        Response::html($html);
    }

    /**
     * GET /api/relatorios/kpis/tempo-medio
     */
    public function tempoMedio(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.kpis.tempo_medio_locacao')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $grupoId = $request->query('grupo', '');

            $model = new KpiReport();
            $result = $model->tempoMedioLocacao(
                $filters['data_inicio'],
                $filters['data_fim'],
                $filialWhere,
                $filialParams,
                $filters['filial'],
                $grupoId
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    // =====================================================
    // % RECEITAS ADICIONAIS
    // =====================================================

    /**
     * GET /pages/relatorios/kpis/receitas-adicionais
     */
    public function viewReceitasAdicionais(Request $request): void
    {
        $html = Template::render('pages.relatorios.kpis.receitas-adicionais');
        Response::html($html);
    }

    /**
     * GET /api/relatorios/kpis/receitas-adicionais
     */
    public function receitasAdicionais(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.kpis.receitas_adicionais')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $model = new KpiReport();
            $result = $model->receitasAdicionais(
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
    // RECEITA POR VEÍCULO
    // =====================================================

    /**
     * GET /pages/relatorios/kpis/receita-veiculo
     */
    public function viewReceitaVeiculo(Request $request): void
    {
        $html = Template::render('pages.relatorios.kpis.receita-veiculo');
        Response::html($html);
    }

    /**
     * GET /api/relatorios/kpis/receita-veiculo
     */
    public function receitaVeiculo(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.kpis.revpar')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $grupoId = $request->query('grupo', '');
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 20)));

            $model = new KpiReport();
            $result = $model->receitaPorVeiculo(
                $filters['data_inicio'],
                $filters['data_fim'],
                $filialWhere,
                $filialParams,
                $filters['filial'],
                $grupoId,
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
    // ROI POR VEÍCULO
    // =====================================================

    /**
     * GET /pages/relatorios/kpis/roi-veiculo
     */
    public function viewRoiVeiculo(Request $request): void
    {
        $html = Template::render('pages.relatorios.kpis.roi-veiculo');
        Response::html($html);
    }

    /**
     * GET /api/relatorios/kpis/roi-veiculo
     */
    public function roiVeiculo(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.kpis.roi_veiculo')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $grupoId = $request->query('grupo', '');
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 20)));

            $model = new KpiReport();
            $result = $model->roiPorVeiculo(
                $filters['data_inicio'],
                $filters['data_fim'],
                $filialWhere,
                $filialParams,
                $filters['filial'],
                $grupoId,
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
    // MARGEM BRUTA POR DIA
    // =====================================================

    /**
     * GET /pages/relatorios/kpis/margem-bruta
     */
    public function viewMargemBruta(Request $request): void
    {
        $html = Template::render('pages.relatorios.kpis.margem-bruta');
        Response::html($html);
    }

    /**
     * GET /api/relatorios/kpis/margem-bruta
     */
    public function margemBruta(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.kpis.margem_bruta')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $grupoId = $request->query('grupo', '');

            $model = new KpiReport();
            $result = $model->margemBruta(
                $filters['data_inicio'],
                $filters['data_fim'],
                $filialWhere,
                $filialParams,
                $filters['filial'],
                $grupoId
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
     * Gera PDF de um relatório KPI
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
        $empresa = $this->resolveReportPdfCompany($user);
        $usuario = $user['nome'] ?? '';

        ob_start();
        $viewPath = __DIR__ . '/../../Views/pages/relatorios/imprimir/kpis/' . $templateFile;
        include $viewPath;
        $html = ob_get_clean();

        $this->outputReportPdf($html, 'relatorio.pdf', [
            'orientation' => $orientation,
        ], 'kpis/' . $templateFile);
    }

    /** GET /relatorios/kpis/taxa-ocupacao/pdf */
    public function taxaOcupacaoPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.kpis.taxa_ocupacao')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new KpiReport();
        $result = $model->taxaOcupacao($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial'], $request->query('grupo', ''), $request->query('veiculo', ''));

        $this->renderPdf('taxa-ocupacao.php', t('modules.relatorios.kpis.taxa_ocupacao.title'), t('modules.relatorios.kpis.taxa_ocupacao.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    /** GET /relatorios/kpis/revpar/pdf */
    public function revparPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.kpis.revpar')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new KpiReport();
        $result = $model->revpar($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial'], $request->query('grupo', ''));

        $this->renderPdf('revpar.php', t('modules.relatorios.kpis.revpar.title'), t('modules.relatorios.kpis.revpar.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim']);
    }

    /** GET /relatorios/kpis/adr/pdf */
    public function adrPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.kpis.adr')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new KpiReport();
        $result = $model->adr($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial'], $request->query('grupo', ''));

        $this->renderPdf('adr.php', t('modules.relatorios.kpis.adr.title'), t('modules.relatorios.kpis.adr.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim']);
    }

    /** GET /relatorios/kpis/ticket-medio/pdf */
    public function ticketMedioPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.kpis.ticket_medio')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new KpiReport();
        $result = $model->ticketMedio($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);

        $this->renderPdf('ticket-medio.php', t('modules.relatorios.kpis.ticket_medio.title'), t('modules.relatorios.kpis.ticket_medio.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim']);
    }

    /** GET /relatorios/kpis/tempo-medio/pdf */
    public function tempoMedioPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.kpis.tempo_medio_locacao')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new KpiReport();
        $result = $model->tempoMedioLocacao($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial'], $request->query('grupo', ''));

        $this->renderPdf('tempo-medio.php', t('modules.relatorios.kpis.tempo_medio.title'), t('modules.relatorios.kpis.tempo_medio.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim']);
    }

    /** GET /relatorios/kpis/receitas-adicionais/pdf */
    public function receitasAdicionaisPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.kpis.receitas_adicionais')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new KpiReport();
        $result = $model->receitasAdicionais($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);

        $this->renderPdf('receitas-adicionais.php', t('modules.relatorios.kpis.receitas_adicionais.title'), t('modules.relatorios.kpis.receitas_adicionais.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim']);
    }

    /** GET /relatorios/kpis/receita-veiculo/pdf */
    public function receitaVeiculoPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.kpis.revpar')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new KpiReport();
        $result = $model->receitaPorVeiculo($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial'], $request->query('grupo', ''), 1, 500);

        $this->renderPdf('receita-veiculo.php', t('modules.relatorios.kpis.receita_veiculo.title'), t('modules.relatorios.kpis.receita_veiculo.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    /** GET /relatorios/kpis/margem-bruta/pdf */
    public function margemBrutaPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.kpis.margem_bruta')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new KpiReport();
        $result = $model->margemBruta($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial'], $request->query('grupo', ''));

        $this->renderPdf('margem-bruta.php', t('modules.relatorios.kpis.margem_bruta.title'), t('modules.relatorios.kpis.margem_bruta.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim']);
    }

    /** GET /relatorios/kpis/roi-veiculo/pdf */
    public function roiVeiculoPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.kpis.roi_veiculo')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new KpiReport();
        $result = $model->roiPorVeiculo($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial'], $request->query('grupo', ''), 1, 500);

        $this->renderPdf('roi-veiculo.php', t('modules.relatorios.kpis.roi_veiculo.title'), t('modules.relatorios.kpis.roi_veiculo.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }
}
