<?php

namespace App\Controllers\Relatorios;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Relatorios\ComparativosReport;

/**
 * Controller de Relatórios da categoria Comparativos.
 *
 * 4 relatórios:
 *  - 11.1 Mensal/Anual
 *  - 11.2 Entre Filiais
 *  - 11.3 Ranking Veículos (futuro)
 *  - 11.4 Tendências (futuro)
 */
class ComparativosController extends BaseRelatorioController
{
    // =====================================================
    // 11.1 MENSAL/ANUAL
    // =====================================================

    /** GET /pages/relatorios/comparativos/mensal-anual */
    public function viewMensalAnual(Request $request): void
    {
        $html = Template::render('pages.relatorios.comparativos.mensal-anual');
        Response::html($html);
    }

    /** GET /api/relatorios/comparativos/mensal-anual */
    public function mensalAnual(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.comparativos.mensal_anual')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $model = new ComparativosReport();
            $result = $model->mensalAnual(
                $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
                $filters['filial'],
                $request->query('data_inicio_anterior', ''),
                $request->query('data_fim_anterior', '')
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/comparativos/mensal-anual/pdf */
    public function mensalAnualPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.comparativos.mensal_anual')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new ComparativosReport();
        $result = $model->mensalAnual(
            $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
            $filters['filial'],
            $request->query('data_inicio_anterior', ''),
            $request->query('data_fim_anterior', '')
        );

        $this->renderPdf(
            'mensal-anual.php',
            t('modules.relatorios.comparativos.mensal_anual.title'),
            t('modules.relatorios.comparativos.mensal_anual.description'),
            $result['totals'], $result['details'],
            $filters['data_inicio'], $filters['data_fim'], 'L'
        );
    }

    // =====================================================
    // 11.2 ENTRE FILIAIS
    // =====================================================

    /** GET /pages/relatorios/comparativos/filiais */
    public function viewFiliais(Request $request): void
    {
        $html = Template::render('pages.relatorios.comparativos.filiais');
        Response::html($html);
    }

    /** GET /api/relatorios/comparativos/filiais */
    public function filiais(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.comparativos.filiais')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $model = new ComparativosReport();
            $result = $model->entreFiliais($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams);

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/comparativos/filiais/pdf */
    public function filiaisPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.comparativos.filiais')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new ComparativosReport();
        $result = $model->entreFiliais($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams);

        $this->renderPdf(
            'filiais.php',
            t('modules.relatorios.comparativos.filiais.title'),
            t('modules.relatorios.comparativos.filiais.description'),
            $result['totals'], $result['details'],
            $filters['data_inicio'], $filters['data_fim'], 'L'
        );
    }

    // =====================================================
    // 11.3 RANKING DE VEÍCULOS
    // =====================================================

    /** GET /pages/relatorios/comparativos/ranking-veiculos */
    public function viewRankingVeiculos(Request $request): void
    {
        $html = Template::render('pages.relatorios.comparativos.ranking-veiculos');
        Response::html($html);
    }

    /** GET /api/relatorios/comparativos/ranking-veiculos */
    public function rankingVeiculos(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.comparativos.ranking_veiculos')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $model = new ComparativosReport();
            $result = $model->rankingVeiculos(
                $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
                $filters['filial'],
                $request->query('criterio', 'receita'),
                $request->query('grupo', '')
            );

            // reportResponse espera array de dados — passamos top10 como array principal
            Response::json([
                'success' => true,
                'data' => $result['details']['top10'],
                'totals' => $result['totals'],
                'chart' => $result['chart'],
                'extra' => [
                    'bottom10' => $result['details']['bottom10'],
                    'all' => $result['details']['all'],
                ],
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/comparativos/ranking-veiculos/pdf */
    public function rankingVeiculosPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.comparativos.ranking_veiculos')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new ComparativosReport();
        $result = $model->rankingVeiculos(
            $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
            $filters['filial'],
            $request->query('criterio', 'receita'),
            $request->query('grupo', '')
        );

        // PDF usa estrutura plana — passa all
        $this->renderPdf(
            'ranking-veiculos.php',
            t('modules.relatorios.comparativos.ranking_veiculos.title'),
            t('modules.relatorios.comparativos.ranking_veiculos.description'),
            $result['totals'], $result['details']['all'],
            $filters['data_inicio'], $filters['data_fim'], 'P'
        );
    }

    // =====================================================
    // 11.4 ANÁLISE DE TENDÊNCIAS
    // =====================================================

    /** GET /pages/relatorios/comparativos/tendencias */
    public function viewTendencias(Request $request): void
    {
        $html = Template::render('pages.relatorios.comparativos.tendencias');
        Response::html($html);
    }

    /** GET /api/relatorios/comparativos/tendencias */
    public function tendencias(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.comparativos.tendencias')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $model = new ComparativosReport();
            $result = $model->tendencias(
                $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
                $filters['filial'],
                $request->query('granularidade', 'mes')
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/comparativos/tendencias/pdf */
    public function tendenciasPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.comparativos.tendencias')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new ComparativosReport();
        $result = $model->tendencias(
            $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
            $filters['filial'],
            $request->query('granularidade', 'mes')
        );

        // Para PDF passamos a sequência de períodos como variável extra via $totals
        $totals = $result['totals'];
        $totals['_periodos'] = $result['chart']['labels'] ?? [];

        $this->renderPdf(
            'tendencias.php',
            t('modules.relatorios.comparativos.tendencias.title'),
            t('modules.relatorios.comparativos.tendencias.description'),
            $totals, $result['details'],
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
        $empresa = $this->resolveReportPdfCompany($user);
        $usuario = $user['nome'] ?? '';

        ob_start();
        $viewPath = __DIR__ . '/../../Views/pages/relatorios/imprimir/comparativos/' . $templateFile;
        include $viewPath;
        $html = ob_get_clean();

        $this->outputReportPdf($html, 'relatorio.pdf', [
            'orientation' => $orientation,
        ], 'comparativos/' . $templateFile);
    }
}
