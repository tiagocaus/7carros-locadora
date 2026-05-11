<?php

namespace App\Controllers\Relatorios;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Relatorios\FuncionariosReport;
use App\Models\MatrizFilial;
use App\Helpers\PdfHelper;

/**
 * Controller de Relatórios da categoria Funcionários.
 *
 * 4 relatórios:
 *  - 10.1 Vendas
 *  - 10.2 Comissões
 *  - 10.3 Produtividade
 *  - 10.4 Metas vs Realizado
 */
class FuncionariosController extends BaseRelatorioController
{
    // =====================================================
    // 10.1 VENDAS
    // =====================================================

    public function viewVendas(Request $request): void
    {
        $html = Template::render('pages.relatorios.funcionarios.vendas');
        Response::html($html);
    }

    public function vendas(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.funcionarios.vendas')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $model = new FuncionariosReport();
            $result = $model->vendas($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);
            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    public function vendasPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.funcionarios.vendas')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new FuncionariosReport();
        $result = $model->vendas($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);
        $this->renderPdf('vendas.php', t('modules.relatorios.funcionarios.vendas.title'), t('modules.relatorios.funcionarios.vendas.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    // =====================================================
    // 10.2 COMISSÕES
    // =====================================================

    public function viewComissoes(Request $request): void
    {
        $html = Template::render('pages.relatorios.funcionarios.comissoes');
        Response::html($html);
    }

    public function comissoes(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.funcionarios.comissoes')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $model = new FuncionariosReport();
            $result = $model->comissoes(
                $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
                $filters['filial'], $request->query('status', '')
            );
            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    public function comissoesPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.funcionarios.comissoes')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new FuncionariosReport();
        $result = $model->comissoes(
            $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
            $filters['filial'], $request->query('status', '')
        );
        $this->renderPdf('comissoes.php', t('modules.relatorios.funcionarios.comissoes.title'), t('modules.relatorios.funcionarios.comissoes.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    // =====================================================
    // 10.3 PRODUTIVIDADE
    // =====================================================

    public function viewProdutividade(Request $request): void
    {
        $html = Template::render('pages.relatorios.funcionarios.produtividade');
        Response::html($html);
    }

    public function produtividade(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.funcionarios.produtividade')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $model = new FuncionariosReport();
            $result = $model->produtividade($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);
            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    public function produtividadePdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.funcionarios.produtividade')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new FuncionariosReport();
        $result = $model->produtividade($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);
        $this->renderPdf('produtividade.php', t('modules.relatorios.funcionarios.produtividade.title'), t('modules.relatorios.funcionarios.produtividade.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    // =====================================================
    // 10.4 METAS VS REALIZADO
    // =====================================================

    public function viewMetas(Request $request): void
    {
        $html = Template::render('pages.relatorios.funcionarios.metas');
        Response::html($html);
    }

    public function metas(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.funcionarios.metas')) return;
            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $model = new FuncionariosReport();
            $result = $model->metasRealizado($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);
            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    public function metasPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.funcionarios.metas')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new FuncionariosReport();
        $result = $model->metasRealizado($filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']);
        $this->renderPdf('metas.php', t('modules.relatorios.funcionarios.metas.title'), t('modules.relatorios.funcionarios.metas.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
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
        $viewPath = __DIR__ . '/../../Views/pages/relatorios/imprimir/funcionarios/' . $templateFile;
        include $viewPath;
        $html = ob_get_clean();

        PdfHelper::outputInline($html, 'relatorio.pdf', [
            'orientation' => $orientation,
        ]);
    }
}
