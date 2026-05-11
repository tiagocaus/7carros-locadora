<?php

namespace App\Controllers\Relatorios;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Relatorios\VeicularReport;
use App\Models\MatrizFilial;
use App\Helpers\PdfHelper;

/**
 * Controller de Relatórios da categoria Veicular
 *
 * Implementa os 11 relatórios:
 *  - Manutenções
 *  - Lucro por Veículo
 *  - Despesas Veicular
 *  - Veículo/Cliente
 *  - Licenciamento
 *  - Disponibilidade
 *  - Taxa de Ocupação por Grupo
 *  - Depreciação
 *  - Tempo Médio Parado
 *  - Quilometragem Média
 *  - Custo Total de Propriedade (TCO)
 */
class VeicularController extends BaseRelatorioController
{
    // =====================================================
    // MANUTENÇÕES
    // =====================================================

    /** GET /pages/relatorios/veicular/manutencoes */
    public function viewManutencoes(Request $request): void
    {
        $html = Template::render('pages.relatorios.veicular.manutencoes');
        Response::html($html);
    }

    /** GET /api/relatorios/veicular/manutencoes */
    public function manutencoes(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.veicular.manutencoes')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) {
                Response::json(['success' => false, 'message' => $erro], 422);
                return;
            }

            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $statusFiltro = $request->query('status', '');
            $oficinaId = $request->query('oficina', '');
            $veiculoIds = $this->parseIdsCsv($request->query('veiculos', $request->query('veiculo', '')));

            $model = new VeicularReport();
            $result = $model->manutencoes(
                $filters['data_inicio'],
                $filters['data_fim'],
                $filialWhere,
                $filialParams,
                $filters['filial'],
                $statusFiltro,
                $oficinaId,
                $veiculoIds
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/veicular/manutencoes/pdf */
    public function manutencoesPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.veicular.manutencoes')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new VeicularReport();
        $result = $model->manutencoes(
            $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
            $filters['filial'],
            $request->query('status', ''),
            $request->query('oficina', ''),
            $this->parseIdsCsv($request->query('veiculos', $request->query('veiculo', '')))
        );

        $this->renderPdf(
            'manutencoes.php',
            t('modules.relatorios.veicular.manutencoes.title'),
            t('modules.relatorios.veicular.manutencoes.description'),
            $result['totals'],
            $result['details'],
            $filters['data_inicio'],
            $filters['data_fim'],
            'L'
        );
    }

    private function parseIdsCsv(string|array|null $value): array
    {
        if (is_array($value)) {
            $parts = $value;
        } else {
            $parts = explode(',', (string) $value);
        }

        $ids = [];
        foreach ($parts as $part) {
            $id = (int) trim((string) $part);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    // =====================================================
    // 3.2 LUCRO POR VEÍCULO
    // =====================================================

    /** GET /pages/relatorios/veicular/lucro-veiculo */
    public function viewLucroVeiculo(Request $request): void
    {
        $html = Template::render('pages.relatorios.veicular.lucro-veiculo');
        Response::html($html);
    }

    /** GET /api/relatorios/veicular/lucro-veiculo */
    public function lucroVeiculo(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.veicular.lucro_veiculo')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $model = new VeicularReport();
            $result = $model->lucroVeiculo(
                $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
                $filters['filial'], $request->query('grupo', ''), $request->query('veiculo', '')
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/veicular/lucro-veiculo/pdf */
    public function lucroVeiculoPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.veicular.lucro_veiculo')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new VeicularReport();
        $result = $model->lucroVeiculo(
            $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
            $filters['filial'], $request->query('grupo', ''), $request->query('veiculo', '')
        );

        $this->renderPdf(
            'lucro-veiculo.php',
            t('modules.relatorios.veicular.lucro_veiculo.title'),
            t('modules.relatorios.veicular.lucro_veiculo.description'),
            $result['totals'], $result['details'],
            $filters['data_inicio'], $filters['data_fim'], 'L'
        );
    }

    // =====================================================
    // 3.3 DESPESAS VEICULAR
    // =====================================================

    /** GET /pages/relatorios/veicular/despesas */
    public function viewDespesas(Request $request): void
    {
        $html = Template::render('pages.relatorios.veicular.despesas');
        Response::html($html);
    }

    /** GET /api/relatorios/veicular/despesas */
    public function despesas(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.veicular.despesas')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $model = new VeicularReport();
            $result = $model->despesasVeicular(
                $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
                $filters['filial'], $request->query('grupo', ''), $request->query('veiculo', '')
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/veicular/despesas/pdf */
    public function despesasPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.veicular.despesas')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new VeicularReport();
        $result = $model->despesasVeicular(
            $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
            $filters['filial'], $request->query('grupo', ''), $request->query('veiculo', '')
        );

        $this->renderPdf(
            'despesas.php',
            t('modules.relatorios.veicular.despesas.title'),
            t('modules.relatorios.veicular.despesas.description'),
            $result['totals'], $result['details'],
            $filters['data_inicio'], $filters['data_fim'], 'L'
        );
    }

    // =====================================================
    // 3.4 VEÍCULO/CLIENTE
    // =====================================================

    /** GET /pages/relatorios/veicular/veiculo-cliente */
    public function viewVeiculoCliente(Request $request): void
    {
        $html = Template::render('pages.relatorios.veicular.veiculo-cliente');
        Response::html($html);
    }

    /** GET /api/relatorios/veicular/veiculo-cliente */
    public function veiculoCliente(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.veicular.veiculo_cliente')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $model = new VeicularReport();
            $result = $model->veiculoCliente(
                $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
                $filters['filial'],
                $request->query('grupo', ''),
                $request->query('veiculo', ''),
                $request->query('cliente', '')
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/veicular/veiculo-cliente/pdf */
    public function veiculoClientePdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.veicular.veiculo_cliente')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new VeicularReport();
        $result = $model->veiculoCliente(
            $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
            $filters['filial'],
            $request->query('grupo', ''),
            $request->query('veiculo', ''),
            $request->query('cliente', '')
        );

        $this->renderPdf(
            'veiculo-cliente.php',
            t('modules.relatorios.veicular.veiculo_cliente.title'),
            t('modules.relatorios.veicular.veiculo_cliente.description'),
            $result['totals'], $result['details'],
            $filters['data_inicio'], $filters['data_fim'], 'L'
        );
    }

    // =====================================================
    // 3.5 LICENCIAMENTO
    // =====================================================

    /** GET /pages/relatorios/veicular/licenciamento */
    public function viewLicenciamento(Request $request): void
    {
        $html = Template::render('pages.relatorios.veicular.licenciamento');
        Response::html($html);
    }

    /** GET /api/relatorios/veicular/licenciamento */
    public function licenciamento(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.veicular.licenciamento')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $model = new VeicularReport();
            $result = $model->licenciamento(
                $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
                $filters['filial'],
                $request->query('grupo', ''),
                $request->query('status', '')
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/veicular/licenciamento/pdf */
    public function licenciamentoPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.veicular.licenciamento')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new VeicularReport();
        $result = $model->licenciamento(
            $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
            $filters['filial'], $request->query('grupo', ''), $request->query('status', '')
        );

        $this->renderPdf(
            'licenciamento.php',
            t('modules.relatorios.veicular.licenciamento.title'),
            t('modules.relatorios.veicular.licenciamento.description'),
            $result['totals'], $result['details'],
            $filters['data_inicio'], $filters['data_fim'], 'L'
        );
    }

    // =====================================================
    // 3.6 DISPONIBILIDADE
    // =====================================================

    /** GET /pages/relatorios/veicular/disponibilidade */
    public function viewDisponibilidade(Request $request): void
    {
        $html = Template::render('pages.relatorios.veicular.disponibilidade');
        Response::html($html);
    }

    /** GET /api/relatorios/veicular/disponibilidade */
    public function disponibilidade(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.veicular.disponibilidade')) return;

            $filialId = $request->query('filial', '');
            if (!$this->validateFilialAccess($filialId)) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $model = new VeicularReport();
            $result = $model->disponibilidade(
                $filialWhere, $filialParams, $filialId, $request->query('grupo', '')
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/veicular/disponibilidade/pdf */
    public function disponibilidadePdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.veicular.disponibilidade')) return;
        [$filialWhere, $filialParams] = $this->getFilialFilter();

        $model = new VeicularReport();
        $result = $model->disponibilidade(
            $filialWhere, $filialParams, $request->query('filial', ''), $request->query('grupo', '')
        );

        // Disponibilidade não usa período — passa data atual nos dois lados pro header
        $hoje = date('Y-m-d');
        $this->renderPdf(
            'disponibilidade.php',
            t('modules.relatorios.veicular.disponibilidade.title'),
            t('modules.relatorios.veicular.disponibilidade.description'),
            $result['totals'], $result['details'],
            $hoje, $hoje, 'P'
        );
    }

    // =====================================================
    // 3.7 TAXA DE OCUPAÇÃO POR GRUPO
    // =====================================================

    /** GET /pages/relatorios/veicular/ocupacao-grupo */
    public function viewOcupacaoGrupo(Request $request): void
    {
        $html = Template::render('pages.relatorios.veicular.ocupacao-grupo');
        Response::html($html);
    }

    /** GET /api/relatorios/veicular/ocupacao-grupo */
    public function ocupacaoGrupo(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.veicular.ocupacao_grupo')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();

            $model = new VeicularReport();
            $result = $model->ocupacaoPorGrupo(
                $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']
            );

            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/veicular/ocupacao-grupo/pdf */
    public function ocupacaoGrupoPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.veicular.ocupacao_grupo')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new VeicularReport();
        $result = $model->ocupacaoPorGrupo(
            $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams, $filters['filial']
        );

        $this->renderPdf(
            'ocupacao-grupo.php',
            t('modules.relatorios.veicular.ocupacao_grupo.title'),
            t('modules.relatorios.veicular.ocupacao_grupo.description'),
            $result['totals'], $result['details'],
            $filters['data_inicio'], $filters['data_fim'], 'L'
        );
    }

    // =====================================================
    // 3.8 DEPRECIAÇÃO DE FROTA
    // =====================================================

    /** GET /pages/relatorios/veicular/depreciacao */
    public function viewDepreciacao(Request $request): void
    {
        $html = Template::render('pages.relatorios.veicular.depreciacao');
        Response::html($html);
    }

    /** GET /api/relatorios/veicular/depreciacao */
    public function depreciacao(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.veicular.depreciacao')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $model = new VeicularReport();
            $result = $model->depreciacao(
                $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
                $filters['filial'], $request->query('grupo', ''), $request->query('veiculo', '')
            );
            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/veicular/depreciacao/pdf */
    public function depreciacaoPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.veicular.depreciacao')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new VeicularReport();
        $result = $model->depreciacao(
            $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
            $filters['filial'], $request->query('grupo', ''), $request->query('veiculo', '')
        );
        $this->renderPdf('depreciacao.php', t('modules.relatorios.veicular.depreciacao.title'), t('modules.relatorios.veicular.depreciacao.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    // =====================================================
    // 3.9 TEMPO MÉDIO PARADO
    // =====================================================

    /** GET /pages/relatorios/veicular/tempo-parado */
    public function viewTempoParado(Request $request): void
    {
        $html = Template::render('pages.relatorios.veicular.tempo-parado');
        Response::html($html);
    }

    /** GET /api/relatorios/veicular/tempo-parado */
    public function tempoParado(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.veicular.tempo_parado')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $model = new VeicularReport();
            $result = $model->tempoParado(
                $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
                $filters['filial'], $request->query('grupo', ''), $request->query('veiculo', '')
            );
            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/veicular/tempo-parado/pdf */
    public function tempoParadoPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.veicular.tempo_parado')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new VeicularReport();
        $result = $model->tempoParado(
            $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
            $filters['filial'], $request->query('grupo', ''), $request->query('veiculo', '')
        );
        $this->renderPdf('tempo-parado.php', t('modules.relatorios.veicular.tempo_parado.title'), t('modules.relatorios.veicular.tempo_parado.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    // =====================================================
    // 3.10 QUILOMETRAGEM MÉDIA
    // =====================================================

    /** GET /pages/relatorios/veicular/quilometragem-media */
    public function viewQuilometragemMedia(Request $request): void
    {
        $html = Template::render('pages.relatorios.veicular.quilometragem-media');
        Response::html($html);
    }

    /** GET /api/relatorios/veicular/quilometragem-media */
    public function quilometragemMedia(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.veicular.quilometragem_media')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $model = new VeicularReport();
            $result = $model->quilometragemMedia(
                $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
                $filters['filial'], $request->query('grupo', ''), $request->query('veiculo', '')
            );
            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/veicular/quilometragem-media/pdf */
    public function quilometragemMediaPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.veicular.quilometragem_media')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new VeicularReport();
        $result = $model->quilometragemMedia(
            $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
            $filters['filial'], $request->query('grupo', ''), $request->query('veiculo', '')
        );
        $this->renderPdf('quilometragem-media.php', t('modules.relatorios.veicular.quilometragem_media.title'), t('modules.relatorios.veicular.quilometragem_media.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    // =====================================================
    // 3.11 TCO
    // =====================================================

    /** GET /pages/relatorios/veicular/tco */
    public function viewTco(Request $request): void
    {
        $html = Template::render('pages.relatorios.veicular.tco');
        Response::html($html);
    }

    /** GET /api/relatorios/veicular/tco */
    public function tco(Request $request): void
    {
        try {
            if (!$this->checkPermission('relatorios.veicular.tco')) return;

            $filters = $this->parseFilters($request);
            $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
            if ($erro) { Response::json(['success' => false, 'message' => $erro], 422); return; }
            if (!$this->validateFilialAccess($filters['filial'])) return;

            [$filialWhere, $filialParams] = $this->getFilialFilter();
            $model = new VeicularReport();
            $result = $model->tco(
                $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
                $filters['filial'], $request->query('grupo', ''), $request->query('veiculo', '')
            );
            $this->reportResponse($result['details'], $result['totals'], $result['chart']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => t('modules.relatorios.messages.load_error')], 500);
        }
    }

    /** GET /relatorios/veicular/tco/pdf */
    public function tcoPdf(Request $request): void
    {
        if (!$this->checkPermission('relatorios.veicular.tco')) return;
        $filters = $this->parseFilters($request);
        $erro = $this->validatePeriodo($filters['data_inicio'], $filters['data_fim']);
        if ($erro) { Response::html("<h3>{$erro}</h3>"); return; }

        [$filialWhere, $filialParams] = $this->getFilialFilter();
        $model = new VeicularReport();
        $result = $model->tco(
            $filters['data_inicio'], $filters['data_fim'], $filialWhere, $filialParams,
            $filters['filial'], $request->query('grupo', ''), $request->query('veiculo', '')
        );
        $this->renderPdf('tco.php', t('modules.relatorios.veicular.tco.title'), t('modules.relatorios.veicular.tco.description'), $result['totals'], $result['details'], $filters['data_inicio'], $filters['data_fim'], 'L');
    }

    // =====================================================
    // PDF HELPER
    // =====================================================

    /**
     * Renderiza o template PDF de um relatório veicular.
     *
     * Usa output buffering para capturar o HTML do template (não Blade)
     * e envia para o mPDF via PdfHelper::outputInline.
     */
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
        $viewPath = __DIR__ . '/../../Views/pages/relatorios/imprimir/veicular/' . $templateFile;
        include $viewPath;
        $html = ob_get_clean();

        PdfHelper::outputInline($html, 'relatorio.pdf', [
            'orientation' => $orientation,
        ]);
    }
}
