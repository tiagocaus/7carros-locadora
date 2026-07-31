<?php

namespace App\Controllers\Relatorios;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Relatorios\ContratosReport;
use App\Views\Template;

/**
 * Controller dos relatorios da categoria CONTRATOS / LOCACOES (grupo 5).
 */
class ContratosController extends BaseRelatorioController
{
    // ===========================================================
    // 5.1 — VISAO GERAL
    // ===========================================================

    /** GET /pages/relatorios/contratos/visao-geral */
    public function viewVisaoGeral(Request $request): void
    {
        $html = Template::render('pages.relatorios.contratos.visao-geral');
        Response::html($html);
    }

    /** GET /api/relatorios/contratos/visao-geral */
    public function visaoGeral(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.contratos.visao_geral')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');

            $statusFiltro = $request->query('status', '');

            $model = new ContratosReport();
            $result = $model->visaoGeral(
                $filters['data_inicio'],
                $filters['data_fim'],
                $filialWhere,
                $filialParams,
                $filters['filial'],
                $statusFiltro
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/contratos/visao-geral/pdf */
    public function visaoGeralPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.contratos.visao_geral')) return;

        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
        $statusFiltro = $request->query('status', '');

        $model = new ContratosReport();
        $result = $model->visaoGeral(
            $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial'], $statusFiltro
        );

        $this->renderPdf(
            'visao-geral.php',
            t('modules.relatorios.contratos.visao_geral.title'),
            t('modules.relatorios.contratos.visao_geral.description'),
            $result['totals'],
            $result['details'],
            $filters['data_inicio'],
            $filters['data_fim'],
            'L'
        );
    }

    // ===========================================================
    // 5.2 — POR PERIODO
    // ===========================================================

    /** GET /pages/relatorios/contratos/por-periodo */
    public function viewPorPeriodo(Request $request): void
    {
        $html = Template::render('pages.relatorios.contratos.por-periodo');
        Response::html($html);
    }

    /** GET /api/relatorios/contratos/por-periodo */
    public function porPeriodo(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.contratos.por_periodo')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
            $granularidade = $request->query('granularidade', 'mes');

            $model = new ContratosReport();
            $result = $model->porPeriodo(
                $filters['data_inicio'], $filters['data_fim'], $granularidade,
                $filialWhere, $filialParams, $filters['filial']
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/contratos/por-periodo/pdf */
    public function porPeriodoPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.contratos.por_periodo')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
        $granularidade = $request->query('granularidade', 'mes');

        $model = new ContratosReport();
        $result = $model->porPeriodo(
            $filters['data_inicio'], $filters['data_fim'], $granularidade,
            $filialWhere, $filialParams, $filters['filial']
        );

        $this->renderPdf(
            'por-periodo.php',
            t('modules.relatorios.contratos.por_periodo.title'),
            t('modules.relatorios.contratos.por_periodo.description'),
            $result['totals'],
            $result['details'],
            $filters['data_inicio'],
            $filters['data_fim'],
            'P'
        );
    }

    // ===========================================================
    // 5.3 — POR FORMA DE PAGAMENTO
    // ===========================================================

    /** GET /pages/relatorios/contratos/por-forma-pagamento */
    public function viewPorFormaPagamento(Request $request): void
    {
        $html = Template::render('pages.relatorios.contratos.por-forma-pagamento');
        Response::html($html);
    }

    /** GET /api/relatorios/contratos/por-forma-pagamento */
    public function porFormaPagamento(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.contratos.por_forma_pagamento')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
            $model = new ContratosReport();
            $result = $model->porFormaPagamento($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);
            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/contratos/por-forma-pagamento/pdf */
    public function porFormaPagamentoPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.contratos.por_forma_pagamento')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }
        [$filialWhere, $filialParams] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
        $model = new ContratosReport();
        $result = $model->porFormaPagamento($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);
        $this->renderPdf('por-forma-pagamento.php',
            t('modules.relatorios.contratos.por_forma_pagamento.title'),
            t('modules.relatorios.contratos.por_forma_pagamento.description'),
            $result['totals'], $result['details'],
            $filters['data_inicio'], $filters['data_fim'], 'P'
        );
    }

    // ===========================================================
    // 5.4 — EXTENSOES
    // ===========================================================

    /** GET /pages/relatorios/contratos/extensoes */
    public function viewExtensoes(Request $request): void
    {
        $html = Template::render('pages.relatorios.contratos.extensoes');
        Response::html($html);
    }

    /** GET /api/relatorios/contratos/extensoes */
    public function extensoes(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.contratos.extensoes')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
            $model = new ContratosReport();
            $result = $model->extensoes($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);
            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/contratos/extensoes/pdf */
    public function extensoesPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.contratos.extensoes')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }
        [$filialWhere, $filialParams] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
        $model = new ContratosReport();
        $result = $model->extensoes($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);
        $this->renderPdf('extensoes.php',
            t('modules.relatorios.contratos.extensoes.title'),
            t('modules.relatorios.contratos.extensoes.description'),
            $result['totals'], $result['details'],
            $filters['data_inicio'], $filters['data_fim'], 'L'
        );
    }

    // ===========================================================
    // 5.5 — TROCAS DE VEICULO
    // ===========================================================

    /** GET /pages/relatorios/contratos/trocas-veiculo */
    public function viewTrocasVeiculo(Request $request): void
    {
        $html = Template::render('pages.relatorios.contratos.trocas-veiculo');
        Response::html($html);
    }

    /** GET /api/relatorios/contratos/trocas-veiculo */
    public function trocasVeiculo(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.contratos.trocas_veiculo')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
            $model = new ContratosReport();
            $result = $model->trocasVeiculo($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);
            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/contratos/trocas-veiculo/pdf */
    public function trocasVeiculoPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.contratos.trocas_veiculo')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }
        [$filialWhere, $filialParams] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
        $model = new ContratosReport();
        $result = $model->trocasVeiculo($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);
        $this->renderPdf('trocas-veiculo.php',
            t('modules.relatorios.contratos.trocas_veiculo.title'),
            t('modules.relatorios.contratos.trocas_veiculo.description'),
            $result['totals'], $result['details'],
            $filters['data_inicio'], $filters['data_fim'], 'L'
        );
    }

    // ===========================================================
    // Helpers internos
    // ===========================================================

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
        $viewPath = __DIR__ . '/../../Views/pages/relatorios/imprimir/contratos/' . $templateFile;
        include $viewPath;
        $html = ob_get_clean();

        $this->outputReportPdf($html, 'relatorio.pdf', ['orientation' => $orientation], 'contratos/' . $templateFile);
    }
}
