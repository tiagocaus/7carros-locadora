<?php

namespace App\Controllers\Relatorios;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Relatorios\FornecedoresReport;
use App\Models\MatrizFilial;
use App\Helpers\PdfHelper;

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

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $model = new FornecedoresReport();
            $result = $model->investidor(
                $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
                $request->query('fornecedor', '')
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

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new FornecedoresReport();
        $result = $model->investidor(
            $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
            $request->query('fornecedor', '')
        );

        $this->renderPdf(
            'investidor.php',
            t('modules.relatorios.fornecedores.investidor.title'),
            t('modules.relatorios.fornecedores.investidor.description'),
            $result['totals'], $result['details'],
            $filters['data_inicio'], $filters['data_fim'], 'L'
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
        $viewPath = __DIR__ . '/../../Views/pages/relatorios/imprimir/fornecedores/' . $templateFile;
        include $viewPath;
        $html = ob_get_clean();

        PdfHelper::outputInline($html, 'relatorio.pdf', [
            'orientation' => $orientation,
        ]);
    }
}
