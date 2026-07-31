<?php

namespace App\Controllers\Relatorios;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Relatorios\ComercialReport;

/**
 * Controller de Relatórios da categoria Comercial.
 *
 * 5 relatórios:
 *  - 8.1 Taxa de Conversão
 *  - 8.2 Origem das Locações
 *  - 8.3 Promoções Utilizadas
 *  - 8.4 Descontos Concedidos
 *  - 8.5 Análise de Temporada
 */
class ComercialController extends BaseRelatorioController
{
    // =====================================================
    // 8.1 TAXA DE CONVERSÃO
    // =====================================================

    public function viewTaxaConversao(Request $request): void
    {
        $html = Template::render('pages.relatorios.comercial.taxa-conversao');
        Response::html($html);
    }

    public function taxaConversao(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.comercial.taxa_conversao')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $model = new ComercialReport();
            $result = $model->taxaConversao(
                $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
                $filters['filial'], $request->query('funcionario', '')
            );
            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    public function taxaConversaoPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.comercial.taxa_conversao')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new ComercialReport();
        $result = $model->taxaConversao(
            $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
            $filters['filial'], $request->query('funcionario', '')
        );
        $this->renderPdf('taxa-conversao.php', t('modules.relatorios.comercial.taxa_conversao.title'), t('modules.relatorios.comercial.taxa_conversao.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim'], 'P');
    }

    // =====================================================
    // 8.2 ORIGEM DAS LOCAÇÕES
    // =====================================================

    public function viewOrigemLocacoes(Request $request): void
    {
        $html = Template::render('pages.relatorios.comercial.origem-locacoes');
        Response::html($html);
    }

    public function origemLocacoes(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.comercial.origem_locacoes')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $model = new ComercialReport();
            $result = $model->origemLocacoes($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);
            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    public function origemLocacoesPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.comercial.origem_locacoes')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new ComercialReport();
        $result = $model->origemLocacoes($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);
        $this->renderPdf('origem-locacoes.php', t('modules.relatorios.comercial.origem_locacoes.title'), t('modules.relatorios.comercial.origem_locacoes.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    // =====================================================
    // 8.3 PROMOÇÕES UTILIZADAS
    // =====================================================

    public function viewPromocoes(Request $request): void
    {
        $html = Template::render('pages.relatorios.comercial.promocoes');
        Response::html($html);
    }

    public function promocoes(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.comercial.promocoes')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $model = new ComercialReport();
            $result = $model->promocoesUtilizadas($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);
            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    public function promocoesPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.comercial.promocoes')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new ComercialReport();
        $result = $model->promocoesUtilizadas($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);
        $this->renderPdf('promocoes.php', t('modules.relatorios.comercial.promocoes.title'), t('modules.relatorios.comercial.promocoes.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    // =====================================================
    // 8.4 DESCONTOS CONCEDIDOS
    // =====================================================

    public function viewDescontos(Request $request): void
    {
        $html = Template::render('pages.relatorios.comercial.descontos');
        Response::html($html);
    }

    public function descontos(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.comercial.descontos')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $model = new ComercialReport();
            $result = $model->descontosConcedidos($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);
            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    public function descontosPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.comercial.descontos')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new ComercialReport();
        $result = $model->descontosConcedidos($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);
        $this->renderPdf('descontos.php', t('modules.relatorios.comercial.descontos.title'), t('modules.relatorios.comercial.descontos.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    // =====================================================
    // 8.5 ANÁLISE DE TEMPORADA
    // =====================================================

    public function viewTemporada(Request $request): void
    {
        $html = Template::render('pages.relatorios.comercial.temporada');
        Response::html($html);
    }

    public function temporada(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.comercial.temporada')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $model = new ComercialReport();
            $result = $model->analiseTemporada(
                $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
                $filters['filial'], $request->query('pais', 'BR')
            );
            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    public function temporadaPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.comercial.temporada')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new ComercialReport();
        $result = $model->analiseTemporada(
            $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
            $filters['filial'], $request->query('pais', 'BR')
        );
        $this->renderPdf('temporada.php', t('modules.relatorios.comercial.temporada.title'), t('modules.relatorios.comercial.temporada.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim'], 'P');
    }

    // =====================================================
    // PDF HELPER
    // =====================================================

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
        $viewPath = __DIR__ . '/../../Views/pages/relatorios/imprimir/comercial/' . $templateFile;
        include $viewPath;
        $html = ob_get_clean();

        $this->outputReportPdf($html, 'relatorio.pdf', [
            'orientation' => $orientation,
        ], 'comercial/' . $templateFile);
    }
}
