<?php

namespace App\Controllers\Relatorios;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\PdfHelper;
use App\Models\MatrizFilial;
use App\Models\Relatorios\FaturasReport;
use App\Views\Template;

/**
 * Controller dos relatorios da categoria FATURAS.
 */
class FaturasController extends BaseRelatorioController
{
    // ===========================================================
    // 7.1 — VENCIDAS / A VENCER
    // ===========================================================

    /** GET /pages/relatorios/faturas/vencidas-a-vencer */
    public function viewVencidasAVencer(Request $request): void
    {
        $html = Template::render('pages.relatorios.faturas.vencidas-a-vencer');
        Response::html($html);
    }

    /** GET /api/relatorios/faturas/vencidas-a-vencer */
    public function vencidasAVencer(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.faturas.vencidas_a_vencer')) return;

            $filialId = $request->query('filial', '');
            $clienteId = $this->normalizarClienteId($request->query('cliente', ''));
            $visao = $request->query('visao', 'vencidas');

            if (!$this->validateFilialAccess($filialId)) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $model = new FaturasReport();
            $result = $model->faturasVencidasAVencer($visao, $filialWhere, $filialParams, $filialId, $clienteId);

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/faturas/vencidas-a-vencer/pdf */
    public function vencidasAVencerPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.faturas.vencidas_a_vencer')) return;

        $filialId = $request->query('filial', '');
        $clienteId = $this->normalizarClienteId($request->query('cliente', ''));
        $visao = $request->query('visao', 'vencidas');

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new FaturasReport();
        $result = $model->faturasVencidasAVencer($visao, $filialWhere, $filialParams, $filialId, $clienteId);

        $this->renderPdf(
            'vencidas-a-vencer.php',
            t('modules.relatorios.faturas.vencidas_a_vencer.title'),
            t('modules.relatorios.faturas.vencidas_a_vencer.description'),
            $result['totals'],
            $result['details'],
            'L'
        );
    }

    // ===========================================================
    // 7.2 — POR VEICULO
    // ===========================================================

    /** GET /pages/relatorios/faturas/por-veiculo */
    public function viewFaturasPorVeiculo(Request $request): void
    {
        $html = Template::render('pages.relatorios.faturas.por-veiculo');
        Response::html($html);
    }

    /** GET /api/relatorios/faturas/por-veiculo */
    public function faturasPorVeiculo(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.faturas.por_veiculo')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $model = new FaturasReport();
            $result = $model->faturasPorVeiculo(
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

    /** GET /relatorios/faturas/por-veiculo/pdf */
    public function faturasPorVeiculoPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.faturas.por_veiculo')) return;

        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new FaturasReport();
        $result = $model->faturasPorVeiculo(
            $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']
        );

        $this->renderPdfPeriodo(
            'por-veiculo.php',
            t('modules.relatorios.faturas.por_veiculo.title'),
            t('modules.relatorios.faturas.por_veiculo.description'),
            $result['totals'],
            $result['details'],
            $filters['data_inicio'],
            $filters['data_fim'],
            'L'
        );
    }

    // ===========================================================
    // 7.3 — PAGAR / RECEBER
    // ===========================================================

    /** GET /pages/relatorios/faturas/pagar-receber */
    public function viewPagarReceber(Request $request): void
    {
        $html = Template::render('pages.relatorios.faturas.pagar-receber');
        Response::html($html);
    }

    /** GET /api/relatorios/faturas/pagar-receber */
    public function pagarReceber(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.faturas.pagar_receber')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $model = new FaturasReport();
            $result = $model->pagarReceber(
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

    /** GET /relatorios/faturas/pagar-receber/pdf */
    public function pagarReceberPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.faturas.pagar_receber')) return;

        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new FaturasReport();
        $result = $model->pagarReceber(
            $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']
        );

        $this->renderPdfPeriodo(
            'pagar-receber.php',
            t('modules.relatorios.faturas.pagar_receber.title'),
            t('modules.relatorios.faturas.pagar_receber.description'),
            $result['totals'],
            $result['details'],
            $filters['data_inicio'],
            $filters['data_fim'],
            'L'
        );
    }

    // ===========================================================
    // Helpers internos
    // ===========================================================

    /**
     * Renderiza um PDF do relatorio de Faturas.
     *
     * @param string $templateFile Nome do arquivo dentro de Views/pages/relatorios/imprimir/faturas/
     * @param string $titulo Titulo
     * @param string $descricao Descricao
     * @param array  $totals Totalizadores
     * @param array  $details Dados detalhados
     * @param string $orientation 'P' portrait, 'L' landscape
     */
    private function renderPdf(
        string $templateFile,
        string $titulo,
        string $descricao,
        array $totals,
        array $details,
        string $orientation = 'P'
    ): void {
        $this->renderPdfPeriodo($templateFile, $titulo, $descricao, $totals, $details, '', '', $orientation);
    }

    private function normalizarClienteId(mixed $clienteId): string
    {
        $clienteId = trim((string) $clienteId);

        if ($clienteId === '' || !ctype_digit($clienteId) || (int) $clienteId <= 0) {
            return '';
        }

        return $clienteId;
    }

    /**
     * Renderiza PDF com periodo no cabecalho (variante usada pelos relatorios
     * que filtram por data_inicio/data_fim, como 7.2 Por Veiculo).
     */
    private function renderPdfPeriodo(
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
        $viewPath = __DIR__ . '/../../Views/pages/relatorios/imprimir/faturas/' . $templateFile;
        include $viewPath;
        $html = ob_get_clean();

        PdfHelper::outputInline($html, 'relatorio.pdf', [
            'orientation' => $orientation,
        ]);
    }
}
