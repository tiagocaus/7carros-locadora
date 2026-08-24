<?php

namespace App\Controllers\Relatorios;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Relatorios\OperacionalReport;
use App\Views\Template;

/**
 * Controller dos relatorios da categoria OPERACIONAL (grupo 6).
 */
class OperacionalController extends BaseRelatorioController
{
    // ===========================================================
    // 6.1 — CHECKLISTS REALIZADOS
    // ===========================================================
    public function viewChecklistsRealizados(Request $request): void
    {
        Response::html(Template::render('pages.relatorios.operacional.checklists-realizados'));
    }
    public function checklistsRealizados(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.operacional.checklists_realizados')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            $tipoMomento = $request->query('momento', '');

            [$fw, $fp] = $this->getFilialFilter('id_matriz_filial', 'v');
            $r = (new OperacionalReport())->checklistsRealizados($filters['data_inicio'], $filters['data_fim'], $tipoMomento, $fw, $fp, $filters['filial']);
            $this->reportResponse($r['details'], $r['totals'], $r['chart']);
        } catch (\Exception $e) { Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500); }
    }
    public function checklistsRealizadosPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.operacional.checklists_realizados')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }
        $tipoMomento = $request->query('momento', '');
        [$fw, $fp] = $this->getFilialFilter('id_matriz_filial', 'v');
        $r = (new OperacionalReport())->checklistsRealizados($filters['data_inicio'], $filters['data_fim'], $tipoMomento, $fw, $fp, $filters['filial']);
        $this->renderPdf('checklists-realizados.php',
            t('modules.relatorios.operacional.checklists_realizados.title'),
            t('modules.relatorios.operacional.checklists_realizados.description'),
            $r['totals'], $r['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    // ===========================================================
    // 6.2 — SINISTROS (identificadores tecnicos legados preservados)
    // ===========================================================
    public function viewAvariasSinistros(Request $request): void
    {
        Response::html(Template::render('pages.relatorios.operacional.avarias-sinistros'));
    }
    public function avariasSinistros(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.operacional.avarias_sinistros')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$fw, $fp] = $this->getFilialFilter('id_matriz_filial', 'v');
            $r = (new OperacionalReport())->avariasSinistros($filters['data_inicio'], $filters['data_fim'], $fw, $fp, $filters['filial']);
            $this->reportResponse($r['details'], $r['totals'], $r['chart']);
        } catch (\Exception $e) { Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500); }
    }
    public function avariasSinistrosPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.operacional.avarias_sinistros')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }
        [$fw, $fp] = $this->getFilialFilter('id_matriz_filial', 'v');
        $r = (new OperacionalReport())->avariasSinistros($filters['data_inicio'], $filters['data_fim'], $fw, $fp, $filters['filial']);
        $this->renderPdf('avarias-sinistros.php',
            t('modules.sinistros.report.title'),
            t('modules.sinistros.report.description'),
            $r['totals'], $r['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    // ===========================================================
    // 6.3 — MULTAS DE TRANSITO
    // ===========================================================
    public function viewMultasTransito(Request $request): void
    {
        Response::html(Template::render('pages.relatorios.operacional.multas-transito'));
    }
    public function multasTransito(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.operacional.multas_transito')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            $status = $request->query('status', '');
            [$fw, $fp] = $this->getFilialFilter('id_matriz_filial', 'm');
            $r = (new OperacionalReport())->multasTransito($filters['data_inicio'], $filters['data_fim'], $status, $fw, $fp, $filters['filial']);
            $this->reportResponse($r['details'], $r['totals'], $r['chart']);
        } catch (\Exception $e) { Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500); }
    }
    public function multasTransitoPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.operacional.multas_transito')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }
        $status = $request->query('status', '');
        [$fw, $fp] = $this->getFilialFilter('id_matriz_filial', 'm');
        $r = (new OperacionalReport())->multasTransito($filters['data_inicio'], $filters['data_fim'], $status, $fw, $fp, $filters['filial']);
        $this->renderPdf('multas-transito.php',
            t('modules.relatorios.operacional.multas_transito.title'),
            t('modules.relatorios.operacional.multas_transito.description'),
            $r['totals'], $r['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    // ===========================================================
    // 6.4 — DEVOLUCOES ANTECIPADAS
    // ===========================================================
    public function viewDevolucoesAntecipadas(Request $request): void
    {
        Response::html(Template::render('pages.relatorios.operacional.devolucoes-antecipadas'));
    }
    public function devolucoesAntecipadas(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.operacional.devolucoes_antecipadas')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$fw, $fp] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
            $r = (new OperacionalReport())->devolucoesAntecipadas($filters['data_inicio'], $filters['data_fim'], $fw, $fp, $filters['filial']);
            $this->reportResponse($r['details'], $r['totals'], $r['chart']);
        } catch (\Exception $e) { Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500); }
    }
    public function devolucoesAntecipadasPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.operacional.devolucoes_antecipadas')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }
        [$fw, $fp] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
        $r = (new OperacionalReport())->devolucoesAntecipadas($filters['data_inicio'], $filters['data_fim'], $fw, $fp, $filters['filial']);
        $this->renderPdf('devolucoes-antecipadas.php',
            t('modules.relatorios.operacional.devolucoes_antecipadas.title'),
            t('modules.relatorios.operacional.devolucoes_antecipadas.description'),
            $r['totals'], $r['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    // ===========================================================
    // 6.5 — DEVOLUCOES ATRASADAS
    // ===========================================================
    public function viewDevolucoesAtrasadas(Request $request): void
    {
        Response::html(Template::render('pages.relatorios.operacional.devolucoes-atrasadas'));
    }
    public function devolucoesAtrasadas(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.operacional.devolucoes_atrasadas')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$fw, $fp] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
            $r = (new OperacionalReport())->devolucoesAtrasadas($filters['data_inicio'], $filters['data_fim'], $fw, $fp, $filters['filial']);
            $this->reportResponse($r['details'], $r['totals'], $r['chart']);
        } catch (\Exception $e) { Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500); }
    }
    public function devolucoesAtrasadasPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.operacional.devolucoes_atrasadas')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }
        [$fw, $fp] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
        $r = (new OperacionalReport())->devolucoesAtrasadas($filters['data_inicio'], $filters['data_fim'], $fw, $fp, $filters['filial']);
        $this->renderPdf('devolucoes-atrasadas.php',
            t('modules.relatorios.operacional.devolucoes_atrasadas.title'),
            t('modules.relatorios.operacional.devolucoes_atrasadas.description'),
            $r['totals'], $r['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    // ===========================================================
    // 6.6 — RESERVAS CANCELADAS / EXPIRADAS
    // ===========================================================
    public function viewReservasCanceladas(Request $request): void
    {
        Response::html(Template::render('pages.relatorios.operacional.reservas-canceladas'));
    }
    public function reservasCanceladas(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.operacional.reservas_canceladas')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$fw, $fp] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
            $r = (new OperacionalReport())->reservasCanceladas($filters['data_inicio'], $filters['data_fim'], $fw, $fp, $filters['filial']);
            $this->reportResponse($r['details'], $r['totals'], $r['chart']);
        } catch (\Exception $e) { Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500); }
    }
    public function reservasCanceladasPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.operacional.reservas_canceladas')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }
        [$fw, $fp] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
        $r = (new OperacionalReport())->reservasCanceladas($filters['data_inicio'], $filters['data_fim'], $fw, $fp, $filters['filial']);
        $this->renderPdf('reservas-canceladas.php',
            t('modules.relatorios.operacional.reservas_canceladas.title'),
            t('modules.relatorios.operacional.reservas_canceladas.description'),
            $r['totals'], $r['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    // ===========================================================
    // 6.7 — TURNAROUND
    // ===========================================================
    public function viewTurnaround(Request $request): void
    {
        Response::html(Template::render('pages.relatorios.operacional.turnaround'));
    }
    public function turnaround(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.operacional.turnaround')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$fw, $fp] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
            $r = (new OperacionalReport())->turnaround($filters['data_inicio'], $filters['data_fim'], $fw, $fp, $filters['filial']);
            $this->reportResponse($r['details'], $r['totals'], $r['chart']);
        } catch (\Exception $e) { Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500); }
    }
    public function turnaroundPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.operacional.turnaround')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }
        [$fw, $fp] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
        $r = (new OperacionalReport())->turnaround($filters['data_inicio'], $filters['data_fim'], $fw, $fp, $filters['filial']);
        $this->renderPdf('turnaround.php',
            t('modules.relatorios.operacional.turnaround.title'),
            t('modules.relatorios.operacional.turnaround.description'),
            $r['totals'], $r['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    // ===========================================================
    // 6.8 — COMBUSTIVEL
    // ===========================================================
    public function viewCombustivel(Request $request): void
    {
        Response::html(Template::render('pages.relatorios.operacional.combustivel'));
    }
    public function combustivel(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.operacional.combustivel')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$fw, $fp] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
            $r = (new OperacionalReport())->combustivel($filters['data_inicio'], $filters['data_fim'], $fw, $fp, $filters['filial']);
            $this->reportResponse($r['details'], $r['totals'], $r['chart']);
        } catch (\Exception $e) { Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500); }
    }
    public function combustivelPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.operacional.combustivel')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }
        [$fw, $fp] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
        $r = (new OperacionalReport())->combustivel($filters['data_inicio'], $filters['data_fim'], $fw, $fp, $filters['filial']);
        $this->renderPdf('combustivel.php',
            t('modules.relatorios.operacional.combustivel.title'),
            t('modules.relatorios.operacional.combustivel.description'),
            $r['totals'], $r['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    // ===========================================================
    // Helper renderPdf
    // ===========================================================
    private function renderPdf(
        string $templateFile, string $titulo, string $descricao,
        array $totals, array $details, string $dataInicio, string $dataFim,
        string $orientation = 'P'
    ): void {
        $user = Auth::user();
        $empresa = $this->resolveReportPdfCompany($user);
        $usuario = $user['nome'] ?? '';

        ob_start();
        $viewPath = __DIR__ . '/../../Views/pages/relatorios/imprimir/operacional/' . $templateFile;
        include $viewPath;
        $html = ob_get_clean();

        $this->outputReportPdf($html, 'relatorio.pdf', ['orientation' => $orientation], 'operacional/' . $templateFile);
    }
}
