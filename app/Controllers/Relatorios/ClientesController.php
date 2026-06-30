<?php

namespace App\Controllers\Relatorios;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\PdfHelper;
use App\Models\MatrizFilial;
use App\Models\Relatorios\ClientesReport;
use App\Views\Template;

/**
 * Controller dos relatorios da categoria CLIENTES (grupo 4).
 */
class ClientesController extends BaseRelatorioController
{
    // ===========================================================
    // 4.1 — POR CLIENTE
    // ===========================================================
    public function viewPorCliente(Request $request): void
    {
        Response::html(Template::render('pages.relatorios.clientes.por-cliente'));
    }
    public function porCliente(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.clientes.por_cliente')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$fw, $fp] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
            $r = (new ClientesReport())->porCliente($filters['data_inicio'], $filters['data_fim'], $fw, $fp, $filters['filial']);
            $this->reportResponse($r['details'], $r['totals'], $r['chart']);
        } catch (\Exception $e) { Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500); }
    }
    public function porClientePdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.clientes.por_cliente')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$fw, $fp] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
        $r = (new ClientesReport())->porCliente($filters['data_inicio'], $filters['data_fim'], $fw, $fp, $filters['filial']);
        $this->renderPdf('por-cliente.php',
            t('modules.relatorios.clientes.por_cliente.title'),
            t('modules.relatorios.clientes.por_cliente.description'),
            $r['totals'], $r['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    // ===========================================================
    // 4.2 — ANIVERSARIANTES
    // ===========================================================
    public function viewAniversariantes(Request $request): void
    {
        Response::html(Template::render('pages.relatorios.clientes.aniversariantes'));
    }
    public function aniversariantes(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.clientes.aniversariantes')) return;
            $mes = (int) $request->query('mes', \App\Helpers\DateHelper::todayForDatabase('n'));
            $dia = $request->query('dia') !== '' ? (int) $request->query('dia') : null;
            $filial = $request->query('filial', '');
            if (!$this->validateFilialAccess($filial)) return;

            [$fw, $fp] = $this->getFilialFilter('id_matriz_filial', 'c');
            $r = (new ClientesReport())->aniversariantes($mes, $dia, $fw, $fp, $filial);
            $this->reportResponse($r['details'], $r['totals'], $r['chart']);
        } catch (\Exception $e) { Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500); }
    }
    public function aniversariantesPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.clientes.aniversariantes')) return;
        $mes = (int) $request->query('mes', \App\Helpers\DateHelper::todayForDatabase('n'));
        $dia = $request->query('dia') !== '' ? (int) $request->query('dia') : null;
        $filial = $request->query('filial', '');

        [$fw, $fp] = $this->getFilialFilter('id_matriz_filial', 'c');
        $r = (new ClientesReport())->aniversariantes($mes, $dia, $fw, $fp, $filial);
        $this->renderPdf('aniversariantes.php',
            t('modules.relatorios.clientes.aniversariantes.title'),
            t('modules.relatorios.clientes.aniversariantes.description'),
            $r['totals'], $r['details'], '', '', 'P');
    }

    // ===========================================================
    // 4.3 — CNH VENCIDAS
    // ===========================================================
    public function viewCnhVencidas(Request $request): void
    {
        Response::html(Template::render('pages.relatorios.clientes.cnh-vencidas'));
    }
    public function cnhVencidas(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.clientes.cnh_vencidas')) return;
            $status = $request->query('status', '');
            $filial = $request->query('filial', '');
            if (!$this->validateFilialAccess($filial)) return;

            [$fw, $fp] = $this->getFilialFilter('id_matriz_filial', 'c');
            $r = (new ClientesReport())->cnhVencidas($status, $fw, $fp, $filial);
            $this->reportResponse($r['details'], $r['totals'], $r['chart']);
        } catch (\Exception $e) { Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500); }
    }
    public function cnhVencidasPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.clientes.cnh_vencidas')) return;
        $status = $request->query('status', '');
        $filial = $request->query('filial', '');
        [$fw, $fp] = $this->getFilialFilter('id_matriz_filial', 'c');
        $r = (new ClientesReport())->cnhVencidas($status, $fw, $fp, $filial);
        $this->renderPdf('cnh-vencidas.php',
            t('modules.relatorios.clientes.cnh_vencidas.title'),
            t('modules.relatorios.clientes.cnh_vencidas.description'),
            $r['totals'], $r['details'], '', '', 'L');
    }

    // ===========================================================
    // 4.4 — TOP CLIENTES
    // ===========================================================
    public function viewTopClientes(Request $request): void
    {
        Response::html(Template::render('pages.relatorios.clientes.top-clientes'));
    }
    public function topClientes(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.clientes.top_clientes')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            $criterio = $request->query('criterio', 'valor');
            $limite = max(1, min(200, (int) $request->query('limite', 10)));

            [$fw, $fp] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
            $r = (new ClientesReport())->topClientes($filters['data_inicio'], $filters['data_fim'], $criterio, $limite, $fw, $fp, $filters['filial']);
            $this->reportResponse($r['details'], $r['totals'], $r['chart']);
        } catch (\Exception $e) { Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500); }
    }
    public function topClientesPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.clientes.top_clientes')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }
        $criterio = $request->query('criterio', 'valor');
        $limite = max(1, min(200, (int) $request->query('limite', 10)));
        [$fw, $fp] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
        $r = (new ClientesReport())->topClientes($filters['data_inicio'], $filters['data_fim'], $criterio, $limite, $fw, $fp, $filters['filial']);
        $this->renderPdf('top-clientes.php',
            t('modules.relatorios.clientes.top_clientes.title'),
            t('modules.relatorios.clientes.top_clientes.description'),
            $r['totals'], $r['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    // ===========================================================
    // 4.5 — FREQUENCIA
    // ===========================================================
    public function viewFrequencia(Request $request): void
    {
        Response::html(Template::render('pages.relatorios.clientes.frequencia'));
    }
    public function frequencia(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.clientes.frequencia')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$fw, $fp] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
            $r = (new ClientesReport())->frequencia($filters['data_inicio'], $filters['data_fim'], $fw, $fp, $filters['filial']);
            $this->reportResponse($r['details'], $r['totals'], $r['chart']);
        } catch (\Exception $e) { Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500); }
    }
    public function frequenciaPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.clientes.frequencia')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }
        [$fw, $fp] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
        $r = (new ClientesReport())->frequencia($filters['data_inicio'], $filters['data_fim'], $fw, $fp, $filters['filial']);
        $this->renderPdf('frequencia.php',
            t('modules.relatorios.clientes.frequencia.title'),
            t('modules.relatorios.clientes.frequencia.description'),
            $r['totals'], $r['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    // ===========================================================
    // 4.6 — TEMPO DE RELACIONAMENTO
    // ===========================================================
    public function viewTempoRelacionamento(Request $request): void
    {
        Response::html(Template::render('pages.relatorios.clientes.tempo-relacionamento'));
    }
    public function tempoRelacionamento(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.clientes.tempo_relacionamento')) return;
            $filial = $request->query('filial', '');
            if (!$this->validateFilialAccess($filial)) return;

            [$fw, $fp] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
            $r = (new ClientesReport())->tempoRelacionamento($fw, $fp, $filial);
            $this->reportResponse($r['details'], $r['totals'], $r['chart']);
        } catch (\Exception $e) { Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500); }
    }
    public function tempoRelacionamentoPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.clientes.tempo_relacionamento')) return;
        $filial = $request->query('filial', '');
        [$fw, $fp] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
        $r = (new ClientesReport())->tempoRelacionamento($fw, $fp, $filial);
        $this->renderPdf('tempo-relacionamento.php',
            t('modules.relatorios.clientes.tempo_relacionamento.title'),
            t('modules.relatorios.clientes.tempo_relacionamento.description'),
            $r['totals'], $r['details'], '', '', 'L');
    }

    // ===========================================================
    // 4.7 — OCORRENCIAS
    // ===========================================================
    public function viewOcorrencias(Request $request): void
    {
        Response::html(Template::render('pages.relatorios.clientes.ocorrencias'));
    }
    public function ocorrencias(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.clientes.ocorrencias')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$fw, $fp] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
            $r = (new ClientesReport())->ocorrencias($filters['data_inicio'], $filters['data_fim'], $fw, $fp, $filters['filial']);
            $this->reportResponse($r['details'], $r['totals'], $r['chart']);
        } catch (\Exception $e) { Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500); }
    }
    public function ocorrenciasPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.clientes.ocorrencias')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }
        [$fw, $fp] = $this->getFilialFilter('id_matriz_filial_retirada', 'l');
        $r = (new ClientesReport())->ocorrencias($filters['data_inicio'], $filters['data_fim'], $fw, $fp, $filters['filial']);
        $this->renderPdf('ocorrencias.php',
            t('modules.relatorios.clientes.ocorrencias.title'),
            t('modules.relatorios.clientes.ocorrencias.description'),
            $r['totals'], $r['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    // ===========================================================
    // 4.8 — INATIVOS
    // ===========================================================
    public function viewInativos(Request $request): void
    {
        Response::html(Template::render('pages.relatorios.clientes.inativos'));
    }
    public function inativos(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.clientes.inativos')) return;
            $filial = $request->query('filial', '');
            if (!$this->validateFilialAccess($filial)) return;

            $diasMin = max(1, (int) $request->query('dias_minimo', 180));

            [$fw, $fp] = $this->getFilialFilter('id_matriz_filial', 'c');
            $r = (new ClientesReport())->inativos($diasMin, $fw, $fp, $filial);
            $this->reportResponse($r['details'], $r['totals'], $r['chart']);
        } catch (\Exception $e) { Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500); }
    }
    public function inativosPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.clientes.inativos')) return;
        $filial = $request->query('filial', '');
        $diasMin = max(1, (int) $request->query('dias_minimo', 180));
        [$fw, $fp] = $this->getFilialFilter('id_matriz_filial', 'c');
        $r = (new ClientesReport())->inativos($diasMin, $fw, $fp, $filial);
        $this->renderPdf('inativos.php',
            t('modules.relatorios.clientes.inativos.title'),
            t('modules.relatorios.clientes.inativos.description'),
            $r['totals'], $r['details'], '', '', 'L');
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
        $filialModel = new MatrizFilial();
        $empresa = $filialModel->buscarPorId((int) ($user['id_matriz_filial'] ?? 0));
        $empresa['logo'] = $this->resolveLogoPath($empresa);

        $empresaData = ['nome' => $empresa['nome'] ?? '', 'logo' => $empresa['logo']];
        $usuario = $user['nome'] ?? '';

        ob_start();
        $viewPath = __DIR__ . '/../../Views/pages/relatorios/imprimir/clientes/' . $templateFile;
        include $viewPath;
        $html = ob_get_clean();

        PdfHelper::outputInline($html, 'relatorio.pdf', ['orientation' => $orientation]);
    }
}
