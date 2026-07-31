<?php

namespace App\Controllers\Relatorios;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
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

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            $filialId = $filters['filial'];
            $clienteId = $this->normalizarClienteId($request->query('cliente', ''));
            $visao = $request->query('visao', 'vencidas');

            if (!$this->validateFilialAccess($filialId)) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $model = new FaturasReport();
            $result = $model->faturasVencidasAVencer(
                $visao,
                $filters['data_inicio'],
                $filters['data_fim'],
                $filialWhere,
                $filialParams,
                $filialId,
                $clienteId
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/faturas/vencidas-a-vencer/pdf */
    public function vencidasAVencerPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.faturas.vencidas_a_vencer')) return;

        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        $filialId = $filters['filial'];
        $clienteId = $this->normalizarClienteId($request->query('cliente', ''));
        $visao = $request->query('visao', 'vencidas');

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new FaturasReport();
        $result = $model->faturasVencidasAVencer(
            $visao,
            $filters['data_inicio'],
            $filters['data_fim'],
            $filialWhere,
            $filialParams,
            $filialId,
            $clienteId
        );

        $this->renderPdfPeriodo(
            'vencidas-a-vencer.php',
            t('modules.relatorios.faturas.vencidas_a_vencer.title'),
            t('modules.relatorios.faturas.vencidas_a_vencer.description'),
            $result['totals'],
            $result['details'],
            $filters['data_inicio'],
            $filters['data_fim'],
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

            $modo = $this->normalizarModoPorVeiculo($request->query('modo', 'agrupado'));
            $veiculoId = $this->normalizarVeiculoId($request->query('veiculo', ''));

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $model = new FaturasReport();
            $result = $model->faturasPorVeiculo(
                $filters['data_inicio'],
                $filters['data_fim'],
                $filialWhere,
                $filialParams,
                $filters['filial'],
                $modo,
                $veiculoId
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

        $modo = $this->normalizarModoPorVeiculo($request->query('modo', 'agrupado'));
        $veiculoId = $this->normalizarVeiculoId($request->query('veiculo', ''));

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new FaturasReport();
        $result = $model->faturasPorVeiculo(
            $filters['data_inicio'],
            $filters['data_fim'],
            $filialWhere,
            $filialParams,
            $filters['filial'],
            $modo,
            $veiculoId
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

            $clienteId = $this->normalizarClienteId($request->query('cliente', ''));
            $fornecedorId = $this->normalizarFornecedorId($request->query('fornecedor', ''));
            $veiculoId = $this->normalizarVeiculoId($request->query('veiculo', ''));
            $status = $this->normalizarStatusConta($request->query('status', ''));

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $model = new FaturasReport();
            $result = $model->pagarReceber(
                $filters['data_inicio'],
                $filters['data_fim'],
                $filialWhere,
                $filialParams,
                $filters['filial'],
                $clienteId,
                $fornecedorId,
                $veiculoId,
                $status
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

        $clienteId = $this->normalizarClienteId($request->query('cliente', ''));
        $fornecedorId = $this->normalizarFornecedorId($request->query('fornecedor', ''));
        $veiculoId = $this->normalizarVeiculoId($request->query('veiculo', ''));
        $status = $this->normalizarStatusConta($request->query('status', ''));

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new FaturasReport();
        $result = $model->pagarReceber(
            $filters['data_inicio'],
            $filters['data_fim'],
            $filialWhere,
            $filialParams,
            $filters['filial'],
            $clienteId,
            $fornecedorId,
            $veiculoId,
            $status
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

    private function normalizarVeiculoId(mixed $veiculoId): string
    {
        $veiculoId = trim((string) $veiculoId);

        if ($veiculoId === '' || !ctype_digit($veiculoId) || (int) $veiculoId <= 0) {
            return '';
        }

        return $veiculoId;
    }

    private function normalizarFornecedorId(mixed $fornecedorId): string
    {
        $fornecedorId = trim((string) $fornecedorId);

        if ($fornecedorId === '' || !ctype_digit($fornecedorId) || (int) $fornecedorId <= 0) {
            return '';
        }

        return $fornecedorId;
    }

    private function normalizarStatusConta(mixed $status): string
    {
        $status = trim((string) $status);
        return in_array($status, ['pago', 'pendente', 'vencida'], true) ? $status : '';
    }

    private function normalizarModoPorVeiculo(mixed $modo): string
    {
        return (string) $modo === 'individualizado' ? 'individualizado' : 'agrupado';
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
        $empresa = $this->resolveReportPdfCompany($user);
        $usuario = $user['nome'] ?? '';

        ob_start();
        $viewPath = __DIR__ . '/../../Views/pages/relatorios/imprimir/faturas/' . $templateFile;
        include $viewPath;
        $html = ob_get_clean();

        $this->outputReportPdf($html, 'relatorio.pdf', [
            'orientation' => $orientation,
        ], 'faturas/' . $templateFile);
    }
}
