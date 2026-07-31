<?php

namespace App\Controllers\Relatorios;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Relatorios\FornecedoresReport;

/**
 * Controller de Relatórios da categoria Fornecedores.
 *
 * 2 relatórios:
 *  - 9.1 Compras e Pagamentos
 *  - 9.2 Fornecedor Investidor
 */
class FornecedoresController extends BaseRelatorioController
{
    // =====================================================
    // 9.1 COMPRAS E PAGAMENTOS
    // =====================================================

    /** GET /pages/relatorios/fornecedores/compras */
    public function viewCompras(Request $request): void
    {
        $html = Template::render('pages.relatorios.fornecedores.compras');
        Response::html($html);
    }

    /** GET /api/relatorios/fornecedores/compras */
    public function compras(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.fornecedores.compras')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $model = new FornecedoresReport();
            $result = $model->compras(
                $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
                $filters['filial'], $request->query('fornecedor', '')
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/fornecedores/compras/pdf */
    public function comprasPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.fornecedores.compras')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new FornecedoresReport();
        $result = $model->compras(
            $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
            $filters['filial'], $request->query('fornecedor', '')
        );

        $this->renderPdf(
            'compras.php',
            t('modules.relatorios.fornecedores.compras.title'),
            t('modules.relatorios.fornecedores.compras.description'),
            $result['totals'], $result['details'],
            $filters['data_inicio'], $filters['data_fim'], 'L'
        );
    }

    // =====================================================
    // 9.2 FORNECEDOR INVESTIDOR
    // =====================================================

    /** GET /pages/relatorios/fornecedores/investidor */
    public function viewInvestidor(Request $request): void
    {
        $html = Template::render('pages.relatorios.fornecedores.investidor');
        Response::html($html);
    }

    /** GET /api/relatorios/fornecedores/investidor */
    public function investidor(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.fornecedores.investidor')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $model = new FornecedoresReport();
            $result = $model->investidor(
                $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
                $request->query('fornecedor', ''), $filters['filial']
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/fornecedores/investidor/pdf */
    public function investidorPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.fornecedores.investidor')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }
        if (!$this->validateFilialAccess($filters['filial'])) return;
        $modelo = $request->query('modelo', 'detalhado') === 'agrupado' ? 'agrupado' : 'detalhado';

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new FornecedoresReport();
        $result = $model->investidor(
            $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
            $request->query('fornecedor', ''), $filters['filial']
        );

        $this->renderPdf(
            'investidor.php',
            t('modules.relatorios.fornecedores.investidor.title'),
            t('modules.relatorios.fornecedores.investidor.description'),
            $result['totals'], $result['details'],
            $filters['data_inicio'], $filters['data_fim'], 'L',
            ['modelo' => $modelo]
        );
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
        string $orientation = 'P',
        array $extraData = []
    ): void {
        $user = Auth::user();
        $empresa = $this->resolveReportPdfCompany($user);
        $usuario = $user['nome'] ?? '';
        extract($extraData, EXTR_SKIP);

        ob_start();
        $viewPath = __DIR__ . '/../../Views/pages/relatorios/imprimir/fornecedores/' . $templateFile;
        include $viewPath;
        $html = ob_get_clean();

        $this->outputReportPdf($html, 'relatorio.pdf', [
            'orientation' => $orientation,
        ], 'fornecedores/' . $templateFile);
    }
}
